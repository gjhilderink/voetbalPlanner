<?php

declare(strict_types=1);

namespace App\Filament\Resources\MemberResource\Pages;

use App\Exports\MembersExport;
use App\Filament\Resources\MemberResource;
use App\Filament\Support\ImportNotifier;
use App\Filament\Support\TeamFilter;
use App\Imports\MembersImport;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

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

                    return Excel::download(
                        new MembersExport(
                            clubId:         filament()->getTenant()?->id,
                            teamIds:        $filters['teams']['values'] ?? [],
                            role:           $filters['role']['value'] ?? null,
                            isActive:       $filters['is_active']['value'] ?? null,
                            allowedTeamIds: TeamFilter::allowedTeamIds(),
                        ),
                        'leden-' . now()->format('Y-m-d') . '.xlsx',
                    );
                }),

            Action::make('import')
                ->label('Importeren')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->visible(fn () => MemberResource::canCreate())
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
                            'Laat de ID-kolom staan: daarop worden bestaande leden bijgewerkt. '
                            . 'Een rij zonder ID (en zonder relatiecode) maakt een nieuw lid aan; vul dan minstens Naam en Teams in. '
                            . 'Teams schrijf je als "JO11-1: Speler; JO13-2: Leider". '
                            . 'Lege cellen laten de huidige waarde staan, en een lege Teams-cel laat de teamkoppelingen ongemoeid.'
                        ),
                ])
                ->action(function (array $data): void {
                    $clubId = filament()->getTenant()?->id;
                    if (! $clubId) {
                        Notification::make()->danger()->title('Geen club geselecteerd')->send();

                        return;
                    }

                    // FileUpload bewaart het pad inclusief de map ("imports/x.xlsx"),
                    // dus die map er niet nóg eens voor plakken — dan wijst het pad
                    // naar imports/imports/ en klapt Excel::import op een bestand
                    // dat niet bestaat. Storage kent de wortel van de schijf zelf.
                    $path   = Storage::disk('local')->path($data['file']);
                    $import = new MembersImport($clubId);

                    Excel::import($import, $path);

                    ImportNotifier::report($import->imported, $import->created, $import->skipped, $import->errors, 'leden');

                    Storage::disk('local')->delete($data['file']);
                }),
        ];
    }
}
