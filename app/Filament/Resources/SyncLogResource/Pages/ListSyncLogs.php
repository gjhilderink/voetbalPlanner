<?php

declare(strict_types=1);

namespace App\Filament\Resources\SyncLogResource\Pages;

use App\Filament\Resources\SyncLogResource;
use App\Filament\Widgets\SyncStatusWidget;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Artisan;

class ListSyncLogs extends ListRecords
{
    protected static string $resource = SyncLogResource::class;

    /** Het statusblok boven de lijst: draait de planner, en ging alles goed? */
    protected function getHeaderWidgets(): array
    {
        return [SyncStatusWidget::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('nuSynchroniseren')
                ->label('Nu synchroniseren')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Nu synchroniseren?')
                ->modalDescription('Dit haalt teams, leden en wedstrijden op uit Sportlink. Bij een grote club duurt dat een paar minuten; blijf op deze pagina wachten tot het klaar is.')
                ->modalSubmitActionLabel('Starten')
                ->action(function (): void {
                    try {
                        // Zonder mail: wie hier op de knop drukt, staat naar de
                        // uitkomst te kijken en heeft geen bericht nodig.
                        Artisan::call('sportlink:sync', ['--geen-mail' => true]);

                        Notification::make()
                            ->title('Synchronisatie afgerond')
                            ->body('De uitkomst staat hieronder in de lijst.')
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Synchronisatie afgebroken')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
