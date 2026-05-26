<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\FootballMatch;
use App\Models\Member;
use App\Models\SyncLog;
use App\Models\Team;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $lastSync = SyncLog::where('status', 'completed')->latest()->first();

        return [
            Stat::make('Teams', Team::where('is_active', true)->count())
                ->description('Actieve teams')
                ->color('success')
                ->icon('heroicon-o-user-group'),
            Stat::make('Leden', Member::where('is_active', true)->count())
                ->description('Actieve leden')
                ->color('primary')
                ->icon('heroicon-o-users'),
            Stat::make('Komende wedstrijden', FootballMatch::where('match_datetime', '>=', now())->where('status', 'scheduled')->count())
                ->description('Geplande wedstrijden')
                ->color('warning')
                ->icon('heroicon-o-calendar'),
            Stat::make('Laatste sync', $lastSync ? $lastSync->completed_at->diffForHumans() : 'Nog niet gesynchroniseerd')
                ->description($lastSync ? $lastSync->records_synced . ' records gesynchroniseerd' : '')
                ->color('gray')
                ->icon('heroicon-o-arrow-path'),
        ];
    }
}
