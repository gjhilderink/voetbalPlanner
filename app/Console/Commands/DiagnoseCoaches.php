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
 *   php artisan coaches:diagnose             # alleen rapporteren
 *   php artisan coaches:diagnose --backfill  # ontbrekende coaches alsnog koppelen
 *   php artisan coaches:diagnose --team=O12-2 # wie zit er in de selectie, en waarom
 *
 * Beantwoordt de vraag "worden leden met rol coach wel aan teams gekoppeld?"
 * en zet, met --backfill, de team-coach(es) op wedstrijden die er nog geen
 * hebben (zonder een sync af te wachten).
 */
class DiagnoseCoaches extends Command
{
    protected $signature   = 'coaches:diagnose {--backfill : Koppel de team-coach(es) aan wedstrijden zonder coach} {--email= : Toon hoe deze gebruiker aan zijn teams hangt} {--team= : Toon de selectie van dit elftal met de rol per lid}';
    protected $description  = 'Rapporteert coach-koppelingen en kan wedstrijden zonder coach alsnog koppelen.';

    public function handle(): int
    {
        if ($this->option('email')) {
            return $this->reportUser((string) $this->option('email'));
        }

        if ($this->option('team')) {
            return $this->reportTeam((string) $this->option('team'));
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
     * Wie komt er in de opstelling van dit elftal terecht, en waarom.
     *
     * De selectie in de app komt uit Team::playingMembers(). Die kijkt eerst
     * naar de teamfunctie en pas als die leeg is naar de hoofdrol bij de club.
     * Staat er iemand tussen die er niet hoort - een coach bij de wissels - dan
     * zit het in een van die twee waarden, en die zijn nergens in de app te
     * zien. Hier staan ze naast elkaar.
     */
    private function reportTeam(string $naam): int
    {
        $teams = Team::where('name', 'like', '%' . $naam . '%')->orderBy('name')->get();

        if ($teams->isEmpty()) {
            $this->error("Geen elftal gevonden waarvan de naam op '{$naam}' lijkt.");
            return self::FAILURE;
        }

        foreach ($teams as $team) {
            $this->info("Elftal: {$team->name} (id {$team->id})");

            // Dezelfde afbakening als de opstelling in de app: playingMembers
            // plus de eis dat het lid actief is. Anders zegt deze kolom JA
            // bij iemand die in de app helemaal niet verschijnt.
            $spelend = $team->playingMembers()
                ->where('members.is_active', true)
                ->pluck('members.id')
                ->all();
            $allen   = $team->members()->orderBy('members.name')->get();

            if ($allen->isEmpty()) {
                $this->line('  — geen leden gekoppeld');
                continue;
            }

            $this->line(sprintf('  %-28s %-18s %-14s %s',
                'Naam', 'teamfunctie', 'clubrol', 'in de selectie?'));

            foreach ($allen as $lid) {
                $inSelectie = in_array($lid->id, $spelend, true);

                $this->line(sprintf('  %-28s %-18s %-14s %s',
                    mb_strimwidth($lid->name, 0, 28, '…'),
                    $lid->pivot->role ?: '(leeg)',
                    $lid->role ?: '(leeg)',
                    $inSelectie ? 'JA' : 'nee',
                ));
            }

            $this->line('');
            $this->comment('  Iemand die er niet in hoort? Pas de teamfunctie aan bij het lid;'
                . ' die beslist, en alleen als hij leeg is telt de clubrol mee.');
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
