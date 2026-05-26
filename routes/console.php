<?php

use App\Jobs\FullSyncJob;
use App\Services\MatchSyncService;
use App\Services\MemberSyncService;
use App\Services\TeamSyncService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduled via cPanel cron: * * * * * php artisan schedule:run
Schedule::job(new FullSyncJob())->dailyAt('03:00')->name('full-sync')->withoutOverlapping();

// Direct sync command (no queue required, use on shared hosting)
Artisan::command('sportlink:sync', function () {
    $this->info('Synchronisatie gestart...');

    $this->info('Teams...');
    $log = app(TeamSyncService::class)->sync();
    $this->line('  → ' . $log->records_synced . ' teams (' . $log->status . ')');

    $this->info('Leden...');
    $log = app(MemberSyncService::class)->sync();
    $this->line('  → ' . $log->records_synced . ' leden (' . $log->status . ')');

    $this->info('Wedstrijden...');
    $log = app(MatchSyncService::class)->sync();
    $this->line('  → ' . $log->records_synced . ' wedstrijden (' . $log->status . ')');

    $this->info('Klaar!');
})->purpose('Sync teams, members and matches directly from Sportlink (no queue)');
