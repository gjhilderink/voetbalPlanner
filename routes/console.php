<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduled via cPanel cron: * * * * * php artisan schedule:run
//
// Twee keer per dag, en rechtstreeks in plaats van via de wachtrij: een
// queue-worker is op deze hosting niet gegarandeerd, en een synchronisatie die
// stil in een wachtrij blijft staan is erger dan er geen te hebben. Het
// commando stuurt zelf een statusmail.
//
// withoutOverlapping omdat een volledige ronde langer kan duren dan het
// interval; anders lopen er twee door elkaar heen op dezelfde tabellen.
Schedule::command('sportlink:sync')
    ->twiceDaily(6, 18)
    ->name('sportlink-sync')
    ->withoutOverlapping();

// Levensteken van de planner zelf. Zonder dit is "de synchronisatie liep niet"
// niet te onderscheiden van "de cron draait helemaal niet" - en dat zijn twee
// heel verschillende problemen. Kost één rij in settings.
Schedule::call(fn () => \App\Models\Setting::set(
    'scheduler_heartbeat', now()->toISOString(), 'system',
))->everyFiveMinutes()->name('scheduler-heartbeat');

// Markeer verlopen ouder/verzorger koppelverzoeken als geweigerd
Schedule::command('guardian:expire')->dailyAt('02:00')->name('guardian-expire');

// Meekijkers van live verslagen opruimen. De rijen worden al gewist bij het
// starten en het weggooien van een verslag, maar een wedstrijd die gewoon
// afgelopen is laat ze staan - en niemand kijkt een dag later nog mee.
Schedule::call(fn () => \App\Models\LiveViewer::where(
    'last_seen_at', '<', now()->subDay(),
)->delete())->dailyAt('03:30')->name('live-viewers-opruimen');

// Onbetaalde bestellingen in de ticketshop geven hun kaarten terug. Elke vijf
// minuten: de reservering duurt een half uur, dus dat is ruim op tijd zonder
// dat het iets kost.
Schedule::command('ticketshop:verlopen')->everyFiveMinutes()->name('ticketshop-verlopen');
