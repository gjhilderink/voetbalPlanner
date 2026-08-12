<?php

declare(strict_types=1);

namespace App\Filament\Resources\BarDutyResource\Pages;

use App\Exports\BarDutiesExport;
use App\Exports\BarDutyTemplateExport;
use App\Filament\Resources\BarDutyResource;
use App\Imports\BarDutiesImport;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;

class ListBarDuties extends ListRecords
{
    protected static string $resource = BarDutyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->visible(fn() => BarDutyResource::canCreate()),

            Action::make('export')
                ->label('Exporteren')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function (array $data): \Symfony\Component\HttpFoundation\BinaryFileResponse {
                    $filters = $this->tableFilters;

                    return Excel::download(
                        new BarDutiesExport(
                            clubId:    filament()->getTenant()?->id,
                            teamId:    $filters['team_id']['value']   ?? null,
                            fromDate:  $filters['period']['from']      ?? null,
                            untilDate: $filters['period']['until']     ?? null,
                        ),
                        'bardiensten-' . now()->format('Y-m-d') . '.xlsx',
                    );
                }),

            Action::make('template')
                ->label('Sjabloon downloaden')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->visible(fn() => BarDutyResource::canCreate())
                ->form([
                    Forms\Components\DatePicker::make('from')
                        ->label('Vanaf')
                        ->displayFormat('d-m-Y')
                        ->default(now())
                        ->required(),
                    Forms\Components\DatePicker::make('until')
                        ->label('Tot en met')
                        ->displayFormat('d-m-Y')
                        ->default(now()->addWeeks(8))
                        ->required(),
                    Forms\Components\Placeholder::make('template_hint')
                        ->label('')
                        ->content('Bevat alle za/zo-dagdelen met datum, tijd en een leeg Elftal-veld. Vul de elftallen in en importeer om ze toe te wijzen.'),
                ])
                ->action(fn(array $data): \Symfony\Component\HttpFoundation\BinaryFileResponse => Excel::download(
                    new BarDutyTemplateExport(fromDate: $data['from'], untilDate: $data['until']),
                    'bardienst-sjabloon-' . now()->format('Y-m-d') . '.xlsx',
                )),

            Action::make('import')
                ->label('Importeren')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->visible(fn() => BarDutyResource::canCreate())
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
                        ->label('Vereist kolomformaat')
                        ->content('Datum | Dagdeel | Tijd | Elftal | Lid 1 | Lid 2 | Lid 3 | Status | Opmerkingen')
                        ->helperText('Gebruik het sjabloon of de export. Datum: dd-mm-jjjj · Dagdeel: Ochtend/Middag/Avond 1/… · een bestaand dagdeel wordt bijgewerkt (elftal toegewezen).'),
                ])
                ->action(function (array $data): void {
                    $path    = storage_path('app/private/imports/' . $data['file']);
                    $clubId  = filament()->getTenant()?->id;
                    $import  = new BarDutiesImport($clubId);

                    Excel::import($import, $path);

                    $body = "Geïmporteerd: {$import->imported}";
                    if ($import->skipped) {
                        $body .= " · Overgeslagen: {$import->skipped}";
                    }

                    if ($import->errors) {
                        Notification::make()
                            ->warning()
                            ->title("{$import->imported} geïmporteerd, {$import->skipped} mislukt")
                            ->body(implode("\n", array_slice($import->errors, 0, 5))
                                . (count($import->errors) > 5 ? "\n…en meer" : ''))
                            ->persistent()
                            ->send();
                    } else {
                        Notification::make()
                            ->success()
                            ->title('Import geslaagd')
                            ->body($body)
                            ->send();
                    }

                    // Clean up the uploaded file
                    if (file_exists($path)) {
                        unlink($path);
                    }
                }),
        ];
    }
}
