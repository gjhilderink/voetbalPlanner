<?php

declare(strict_types=1);

namespace App\Filament\Resources\ClubResource\Pages;

use App\Filament\Resources\ClubResource;
use Database\Seeders\DemoClubSeeder;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Artisan;

class ListClubs extends ListRecords
{
    protected static string $resource = ClubResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->demoClubAction(),
            Actions\CreateAction::make(),
        ];
    }

    /**
     * Zet de demo-club klaar zonder de terminal.
     *
     * Alleen voor super admins — net als de rest van deze pagina, die met
     * ClubResource::canViewAny() al zo is afgeschermd. De knop staat hier en niet
     * bij de instellingen omdat hij een club aanmaakt; dit is de plek waar je
     * naar clubs kijkt.
     */
    private function demoClubAction(): Actions\Action
    {
        return Actions\Action::make('demoClub')
            ->label('Demo-club klaarzetten')
            ->icon('heroicon-o-beaker')
            ->color('gray')
            ->visible(fn (): bool => auth()->user()?->hasRole('super_admin') ?? false)
            ->modalHeading('Demo-club klaarzetten')
            ->modalDescription(
                'Zet een demo-club neer met een elftal, leden, wedstrijden, trainingen, '
                . 'bardiensten, nieuws en agenda. Bestaande demo-gegevens worden bijgewerkt, '
                . 'niet verdubbeld.'
            )
            ->modalSubmitActionLabel('Klaarzetten')
            ->form([
                Forms\Components\Toggle::make('fresh')
                    ->label('Eerst volledig wissen')
                    ->helperText(
                        'Verwijdert de bestaande demo-club met alles erin en bouwt hem vers op. '
                        . 'Dat is onomkeerbaar, maar raakt uitsluitend de demo-club — andere clubs blijven ongemoeid.'
                    )
                    ->default(false),
            ])
            ->action(function (array $data): void {
                $vers = (bool) ($data['fresh'] ?? false);

                // Via het commando en niet de seeder rechtstreeks: daar zit de
                // volledige wisvolgorde in. --force slaat de bevestigingsvraag
                // over die op de terminal thuishoort; hier is die al gesteld in
                // het venster hierboven.
                //
                // array_filter haalt --fresh eruit als hij uit staat: een
                // meegegeven vlag met waarde false telt voor Artisan alsnog als
                // gezet, en dan zou elke druk op de knop wissen.
                $opties = array_filter([
                    '--fresh' => $vers,
                    '--force' => true,
                ]);

                try {
                    Artisan::call('demo:club', $opties);
                } catch (\Throwable $e) {
                    report($e);

                    Notification::make()
                        ->danger()
                        ->title('Demo-club klaarzetten mislukt')
                        ->body($e->getMessage())
                        ->persistent()
                        ->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title($vers ? 'Demo-club opnieuw opgebouwd' : 'Demo-club bijgewerkt')
                    ->body(
                        'Inloggen kan met beheerder@' . DemoClubSeeder::MAIL_DOMAIN
                        . ' en wachtwoord ' . DemoClubSeeder::PASSWORD . '.'
                    )
                    ->persistent()
                    ->send();
            });
    }
}
