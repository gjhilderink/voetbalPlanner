<?php

declare(strict_types=1);

namespace App\Filament\Resources\MatchResource\Pages;

use App\Exports\MatchesExport;
use App\Filament\Resources\MatchResource;
use App\Filament\Support\ImportNotifier;
use App\Filament\Support\TeamFilter;
use App\Imports\MatchesImport;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ListMatches extends ListRecords
{
    protected static string $resource = MatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),

            Action::make('export')
                ->label('Exporteren')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function (): BinaryFileResponse {
                    $filters = $this->tableFilters;

                    // Het 'Periode'-filter werkt hier hetzelfde als in de tabel:
                    // blanco toont vanaf een week geleden, 'Alle' laat alles zien
                    // en 'Alleen ouder dan 1 week' kijkt terug.
                    $periode   = $filters['periode']['value'] ?? null;
                    $fromDate  = null;
                    $untilDate = null;
                    if ($periode === null || $periode === '') {
                        $fromDate = now()->subWeek()->toDateTimeString();
                    } elseif (! filter_var($periode, FILTER_VALIDATE_BOOLEAN)) {
                        $untilDate = now()->subWeek()->toDateTimeString();
                    }

                    return Excel::download(
                        new MatchesExport(
                            clubId:         filament()->getTenant()?->id,
                            teamIds:        $filters['team']['values'] ?? [],
                            status:         $filters['status']['value'] ?? null,
                            fromDate:       $fromDate,
                            untilDate:      $untilDate,
                            allowedTeamIds: TeamFilter::allowedTeamIds(),
                        ),
                        'wedstrijden-' . now()->format('Y-m-d') . '.xlsx',
                    );
                }),

            Action::make('import')
                ->label('Importeren')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->visible(fn () => MatchResource::canCreate())
                ->form([
                    Forms\Components\FileUpload::make('file')
                        ->label('Excel bestand (.xlsx)')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                        ])
                        ->disk('local')
                        ->directory('imports')
                        ->required(),
                    Forms\Components\Placeholder::make('format_hint')
                        ->label('Zo werkt het')
                        ->content('Exporteer eerst, pas de kolommen aan in Excel en importeer het bestand terug.')
                        ->helperText(
                            'Laat de ID-kolom staan: daarop worden bestaande wedstrijden bijgewerkt. '
                            . 'Een rij zonder ID maakt een nieuwe wedstrijd aan; vul dan Team, Tegenstander, Datum en Tijd in. '
                            . 'Lege cellen laten de huidige waarde staan. Coaches, schoonmakers en rijders lopen niet mee.'
                        ),
                ])
                ->action(function (array $data): void {
                    $clubId = filament()->getTenant()?->id;
                    if (! $clubId) {
                        Notification::make()->danger()->title('Geen club geselecteerd')->send();

                        return;
                    }

                    // Zie ListMembers: FileUpload levert het pad al mét de map erin.
                    $path   = Storage::disk('local')->path($data['file']);
                    $import = new MatchesImport($clubId);

                    Excel::import($import, $path);

                    ImportNotifier::report($import->imported, $import->created, $import->skipped, $import->errors, 'wedstrijden');

                    Storage::disk('local')->delete($data['file']);
                }),
        ];
    }
}
