<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Models\Team;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Diagnose + backfill van de default-coach op wedstrijden.
 *
 *   php artisan coaches:diagnose            # alleen rapporteren
 *   php artisan coaches:diagnose --backfill # ontbrekende coaches alsnog koppelen
 *
 * Beantwoordt de vraag "worden leden met rol coach wel aan teams gekoppeld?"
 * en zet, met --backfill, de team-coach(es) op wedstrijden die er nog geen
 * hebben (zonder een sync af te wachten).
 */
class DiagnoseCoaches extends Command
{
    protected $signature   = 'coaches:diagnose {--backfill : Koppel de team-coach(es) aan wedstrijden zonder coach}';
    protected $description  = 'Rapporteert coach-koppelingen en kan wedstrijden zonder coach alsnog koppelen.';

    public function handle(): int
    {
        // 1. Rolverdeling in member_team.
        $this->info('Rolverdeling in member_team:');
        $roles = DB::table('member_team')
            ->select('role', DB::raw('count(*) as n'))
            ->groupBy('role')
            ->pluck('n', 'role');
        foreach ($roles as $role => $n) {
            $this->line(sprintf('  %-10s %d', $role ?? '(leeg)', $n));
        }
        if (! $roles->has('coach')) {
            $this->warn('  → GEEN enkele koppeling met rol "coach"! Coaches komen kennelijk niet '
                . 'als "Technische staf" uit Sportlink. Stel de coach handmatig in bij het team/lid, '
                . 'of pas de rol-afbakening aan.');
        }

        // 2. Teams met/zonder coach.
        $teamsTotal    = Team::count();
        $teamsWithCoach = Team::whereHas('coaches')->count();
        $this->info("Teams met minstens één coach: {$teamsWithCoach} / {$teamsTotal}");
        Team::with('coaches')->get()->each(function (Team $t) {
            $names = $t->coaches->pluck('name')->join(', ');
            $this->line(sprintf('  %-24s %s', $t->name, $names !== '' ? $names : '— (geen coach)'));
        });

        // 3. Wedstrijden met coach.
        $matchesTotal   = FootballMatch::count();
        $withPivot      = DB::table('match_coaches')->distinct('match_id')->count('match_id');
        $withCoachId    = FootballMatch::whereNotNull('coach_id')->count();
        $this->info("Wedstrijden totaal: {$matchesTotal}");
        $this->line("  met coach in match_coaches: {$withPivot}");
        $this->line("  met coach_id (fallback):    {$withCoachId}");

        // 4. Optioneel: backfill.
        if ($this->option('backfill')) {
            $this->info('Backfill: team-coach(es) koppelen aan wedstrijden zonder coach...');
            $coachIdsByTeam = [];
            $coupled = 0;

            FootballMatch::with('team')->chunkById(200, function ($matches) use (&$coachIdsByTeam, &$coupled) {
                foreach ($matches as $match) {
                    if (! $match->team_id) {
                        continue;
                    }
                    $coachIdsByTeam[$match->team_id] ??= Team::find($match->team_id)?->matchDefaultCoaches()->pluck('id')->all() ?? [];
                    $coachIds = $coachIdsByTeam[$match->team_id];
                    if (empty($coachIds)) {
                        continue;
                    }
                    if ($match->coaches()->count() === 0) {
                        $match->coaches()->syncWithoutDetaching($coachIds);
                        $coupled++;
                    }
                    if (! $match->coach_id) {
                        $match->coach_id = $coachIds[0];
                        $match->save();
                    }
                }
            });

            $this->info("Klaar. Wedstrijden bijgekoppeld: {$coupled}");
        } else {
            $this->comment('Tip: draai met --backfill om wedstrijden zonder coach alsnog te koppelen.');
        }

        return self::SUCCESS;
    }
}
