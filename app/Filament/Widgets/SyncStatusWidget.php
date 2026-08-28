<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Setting;
use App\Models\SyncLog;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * De stand van zaken van de automatische synchronisatie.
 *
 * Drie vragen, en ze hebben elk een ander antwoord nodig: draait de planner
 * überhaupt, wanneer is er voor het laatst gesynchroniseerd, en ging er iets
 * mis? Zonder het eerste blokje is "er is vandaag niets gesynchroniseerd" niet
 * te onderscheiden van "de cron staat uit" — en dat zijn twee heel verschillende
 * problemen.
 */
class SyncStatusWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            $this->planner(),
            $this->laatsteRonde(),
            $this->fouten(),
        ];
    }

    private function planner(): Stat
    {
        $ruw = Setting::get('scheduler_heartbeat', '', null);

        if (! $ruw) {
            return Stat::make('Planner', 'Onbekend')
                ->description('Nog geen levensteken ontvangen')
                ->color('warning')
                ->icon('heroicon-o-question-mark-circle');
        }

        $moment = Carbon::parse($ruw);
        // Het levensteken komt elke vijf minuten; een kwartier stilte is meer
        // dan een gemiste beurt.
        $leeft = $moment->gt(now()->subMinutes(15));

        return Stat::make('Planner', $leeft ? 'Draait' : 'Stil')
            ->description('Laatste teken van leven ' . $moment->diffForHumans())
            ->color($leeft ? 'success' : 'danger')
            ->icon($leeft ? 'heroicon-o-play-circle' : 'heroicon-o-exclamation-triangle');
    }

    private function laatsteRonde(): Stat
    {
        $laatste = $this->query()->whereNotNull('completed_at')->latest('completed_at')->first();

        if (! $laatste) {
            return Stat::make('Laatste synchronisatie', 'Nooit')
                ->description('Er is nog niet gesynchroniseerd')
                ->color('gray')
                ->icon('heroicon-o-arrow-path');
        }

        // De ronde draait om 06:00 en 18:00; ouder dan een etmaal betekent dat
        // er minstens twee beurten zijn overgeslagen.
        $recent = $laatste->completed_at->gt(now()->subDay());

        return Stat::make('Laatste synchronisatie', $laatste->completed_at->format('d-m-Y H:i'))
            ->description($laatste->completed_at->diffForHumans())
            ->color($recent ? 'success' : 'warning')
            ->icon('heroicon-o-arrow-path');
    }

    private function fouten(): Stat
    {
        $aantal = $this->query()
            ->where('status', '!=', 'completed')
            ->where('started_at', '>=', now()->subDay())
            ->count();

        return Stat::make('Mislukt (24 uur)', (string) $aantal)
            ->description($aantal === 0 ? 'Alles is goed gegaan' : 'Zie de lijst hieronder')
            ->color($aantal === 0 ? 'success' : 'danger')
            ->icon($aantal === 0 ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle');
    }

    /** Binnen de club waar je nu in werkt; een super-admin ziet alles. */
    private function query(): \Illuminate\Database\Eloquent\Builder
    {
        $tenant = filament()->getTenant();

        return SyncLog::query()->when($tenant, fn ($q) => $q->where('club_id', $tenant->id));
    }
}
