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

// Markeer verlopen ouder/verzorger koppelverzoeken als geweigerd
Schedule::command('guardian:expire')->dailyAt('02:00')->name('guardian-expire');
