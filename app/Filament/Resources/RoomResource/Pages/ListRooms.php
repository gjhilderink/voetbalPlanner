<?php

declare(strict_types=1);

namespace App\Filament\Resources\RoomResource\Pages;

use App\Filament\Resources\RoomResource;
use App\Models\Room;
use App\Services\MicrosoftGraphService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\HtmlString;

class ListRooms extends ListRecords
{
    protected static string $resource = RoomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Ruimte toevoegen'),

            // Meteen de controle op de inrichting: komt hier niets uit, dan zijn
            // de ruimtes in Microsoft geen resource-postbussen en klopt de opzet
            // niet. Dat is beter te zien in een lijst dan te lezen in een
            // handleiding.
            Actions\Action::make('ruimtesUitMicrosoft')
                ->label('Ruimtes ophalen uit Microsoft')
                ->icon('heroicon-o-cloud-arrow-down')
                ->color('gray')
                ->visible(fn (): bool => app(MicrosoftGraphService::class)
                    ->forClub(static::clubId())
                    ->isConfigured())
                ->modalHeading('Ruimtes uit Microsoft 365')
                ->modalSubmitActionLabel('Ontbrekende ruimtes toevoegen')
                ->modalContent(fn (): HtmlString => static::gevondenLijst())
                ->action(function (): void {
                    $uitkomst = app(MicrosoftGraphService::class)
                        ->forClub(static::clubId())
                        ->ruimtes();

                    if (! $uitkomst['ok']) {
                        Notification::make()->danger()
                            ->title('Ophalen mislukt')
                            ->body($uitkomst['error'] ?? '')
                            ->persistent()
                            ->send();

                        return;
                    }

                    $clubId    = static::clubId();
                    $bestaande = Room::where('club_id', $clubId)
                        ->pluck('ms_room_email')
                        ->filter()
                        ->map(fn (string $e) => mb_strtolower($e))
                        ->all();

                    $nieuw = 0;

                    foreach ($uitkomst['rooms'] ?? [] as $ruimte) {
                        if (in_array(mb_strtolower($ruimte['email']), $bestaande, true)) {
                            continue;
                        }

                        Room::create([
                            'club_id'       => $clubId,
                            'name'          => $ruimte['naam'] !== '' ? $ruimte['naam'] : $ruimte['email'],
                            'capacity'      => $ruimte['capacity'] !== '' ? (int) $ruimte['capacity'] : null,
                            'ms_room_email' => $ruimte['email'],
                            'ms_room_id'    => $ruimte['id'] ?: null,
                            'ms_synced_at'  => now(),
                            'is_active'     => true,
                        ]);

                        $nieuw++;
                    }

                    Notification::make()->success()
                        ->title($nieuw > 0
                            ? $nieuw . ' ruimte' . ($nieuw === 1 ? '' : 's') . ' toegevoegd'
                            : 'Er was niets nieuws toe te voegen')
                        ->body($nieuw > 0
                            ? 'Loop ze na op naam en kleur; die kun je hier aanpassen zonder dat het de koppeling raakt.'
                            : 'Alle ruimtes uit Microsoft stonden er al.')
                        ->send();
                }),
        ];
    }

    /** Wat Microsoft nu teruggeeft, met per ruimte of hij hier al staat. */
    protected static function gevondenLijst(): HtmlString
    {
        $uitkomst = app(MicrosoftGraphService::class)
            ->forClub(static::clubId())
            ->ruimtes();

        if (! $uitkomst['ok']) {
            return new HtmlString(
                '<p style="color:#b91c1c">' . e($uitkomst['error'] ?? 'Ophalen mislukt.') . '</p>'
            );
        }

        $ruimtes = $uitkomst['rooms'] ?? [];

        if ($ruimtes === []) {
            return new HtmlString(
                '<p>Microsoft geeft geen ruimtes terug.</p>'
                . '<p style="margin-top:.5rem">Dat betekent doorgaans dat de ruimtes geen postbussen van het type '
                . '<em>ruimte</em> zijn, of dat de app-registratie het recht <code>Place.Read.All</code> mist.</p>'
            );
        }

        $bestaande = Room::where('club_id', static::clubId())
            ->pluck('ms_room_email')
            ->filter()
            ->map(fn (string $e) => mb_strtolower($e))
            ->all();

        $regels = collect($ruimtes)->map(function (array $r) use ($bestaande): string {
            $staatEr = in_array(mb_strtolower($r['email']), $bestaande, true);

            return '<li>' . e($r['naam']) . ' &mdash; <code>' . e($r['email']) . '</code>'
                . ($staatEr ? ' <em>(staat er al)</em>' : '')
                . '</li>';
        })->implode('');

        return new HtmlString(
            '<p>Microsoft kent deze ruimtes:</p><ul style="margin:.5rem 0 0 1.1rem">' . $regels . '</ul>'
        );
    }

    protected static function clubId(): ?string
    {
        return filament()->getTenant()?->id ?? auth()->user()?->club_id;
    }
}