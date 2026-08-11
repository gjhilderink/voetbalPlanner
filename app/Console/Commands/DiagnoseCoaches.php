<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Models\Team;
use App\Models\User;
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
    protected $signature   = 'coaches:diagnose {--backfill : Koppel de team-coach(es) aan wedstrijden zonder coach} {--email= : Toon hoe deze gebruiker aan zijn teams hangt}';
    protected $description  = 'Rapporteert coach-koppelingen en kan wedstrijden zonder coach alsnog koppelen.';

    public function handle(): int
    {
        if ($this->option('email')) {
            return $this->reportUser((string) $this->option('email'));
        }

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

    /**
     * Toont precies hoe één gebruiker aan zijn teams hangt: via user_team
     * (app-account) en/of member_team (rooster-lid), met rol en team-externe-id,
     * plus eventuele dubbele team-records met wedstrijd- en coach-aantallen.
     */
    private function reportUser(string $email): int
    {
        $user = User::where('email', $email)->first();
        if (! $user) {
            $this->error("Geen gebruiker met e-mail {$email}.");
            return self::FAILURE;
        }

        $this->info("Gebruiker: {$user->name} <{$user->email}> (id {$user->id})");
        $member = $user->resolveMember();
        $this->line('Gekoppeld lid (member): '
            . ($member ? "{$member->name} (id {$member->id})" : '— GEEN Member-record (alleen app-account)'));

        $this->info('Teams via user_team (app-account):');
        $userTeams = $user->managedTeams()->orderBy('name')->get();
        if ($userTeams->isEmpty()) {
            $this->line('  — geen');
        }
        foreach ($userTeams as $t) {
            $this->line(sprintf('  %-26s rol=%-8s ext=%-10s (team-id %s)',
                $t->name, $t->pivot->role ?? '—', $t->external_id ?? '—', $t->id));
        }

        if ($member) {
            $this->info('Teams via member_team (rooster-lid):');
            $memberTeams = $member->teams()->orderBy('name')->get();
            if ($memberTeams->isEmpty()) {
                $this->line('  — geen');
            }
            foreach ($memberTeams as $t) {
                $this->line(sprintf('  %-26s rol=%-8s ext=%-10s (team-id %s)',
                    $t->name, $t->pivot->role ?? '—', $t->external_id ?? '—', $t->id));
            }
        }

        // Dubbele team-records + wedstrijd/coach per team van deze persoon.
        $teamNames = collect($userTeams->pluck('name'))
            ->merge($member ? $member->teams()->pluck('name') : [])
            ->unique()
            ->sort();

        $this->info('Teamrecords voor jouw teams (let op dubbele namen):');
        foreach ($teamNames as $name) {
            foreach (Team::where('name', $name)->get() as $t) {
                $coach = $t->matchDefaultCoaches()->pluck('name')->join(', ');
                $this->line(sprintf('  %-26s ext=%-10s wedstrijden=%-4d default-coach=%s (team-id %s)',
                    $t->name, $t->external_id ?? '—', $t->matches()->count(),
                    $coach !== '' ? $coach : '—', $t->id));
            }
        }

        $this->newLine();
        $this->comment('Zie je jezelf alleen onder "user_team" (en niet als member met rol coach), dan word je '
            . 'niet als wedstrijd-coach gekoppeld: match_coaches verwijst alleen naar members. '
            . 'Staan er dubbele team-records met verschillende ext-ids, dan hangen de wedstrijden mogelijk '
            . 'aan een ander record dan waaraan jij gekoppeld bent.');

        return self::SUCCESS;
    }
}
