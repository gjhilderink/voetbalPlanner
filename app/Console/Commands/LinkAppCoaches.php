<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Member;
use App\Models\Team;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Geeft app-account-coaches (gekoppeld via user_team, zonder rooster-lid) een
 * echt Member-record en koppelt dat met de juiste rol aan het team. Pas dan
 * kunnen zij als wedstrijd-coach worden gekoppeld (match_coaches verwijst naar
 * members) en verschijnen zij in de selectielijsten.
 *
 *   php artisan coaches:link-app-coaches                 # alle app-coaches
 *   php artisan coaches:link-app-coaches --email=..      # alleen deze gebruiker
 *   php artisan coaches:link-app-coaches --dry-run       # toon, wijzig niets
 *   php artisan coaches:link-app-coaches --no-backfill   # niet aan wedstrijden koppelen
 */
class LinkAppCoaches extends Command
{
    protected $signature = 'coaches:link-app-coaches
        {--email= : Alleen deze gebruiker}
        {--dry-run : Toon wat er zou gebeuren, wijzig niets}
        {--no-backfill : Koppel daarna niet automatisch aan wedstrijden}';

    protected $description = 'Maakt/lidkoppelt app-account-coaches zodat ze als wedstrijd-coach gelden.';

    public function handle(): int
    {
        $roles = [Member::ROLE_COACH, Member::ROLE_LEIDER, Member::ROLE_ASSISTANT];
        $dry   = (bool) $this->option('dry-run');

        $userQuery = User::query()
            ->whereHas('managedTeams', fn($q) => $q->wherePivotIn('role', $roles));
        if ($email = $this->option('email')) {
            $userQuery->where('email', $email);
        }
        $users = $userQuery->get();

        if ($users->isEmpty()) {
            $this->warn('Geen app-account-coaches (user_team met beheerrol) gevonden.');
            return self::SUCCESS;
        }

        $affectedTeamIds = [];
        $created = 0;
        $linked  = 0;
        $couplings = 0;

        foreach ($users as $user) {
            $teamPivots = $user->managedTeams()->wherePivotIn('role', $roles)->get();
            if ($teamPivots->isEmpty()) {
                continue;
            }

            // 1. Vind of maak het gekoppelde lid.
            $member = Member::where('user_id', $user->id)->first();
            $how    = 'bestaand lid (via user_id)';

            if (! $member && $user->email) {
                $member = Member::where('email', $user->email)->first();
                if ($member) {
                    $how = 'bestaand lid (via e-mail), user gekoppeld';
                    if (! $dry && $member->user_id !== $user->id) {
                        $member->user_id = $user->id;
                        $member->save();
                    }
                    $linked++;
                }
            }

            if (! $member) {
                $how = 'nieuw lid aangemaakt';
                $created++;
                if (! $dry) {
                    $member = Member::create([
                        'user_id'   => $user->id,
                        'name'      => $user->name ?: ($user->email ?? 'Coach'),
                        'email'     => $user->email,
                        'role'      => $teamPivots->first()->pivot->role ?? Member::ROLE_COACH,
                        'is_active' => true,
                    ]);
                }
            }

            $this->line("Gebruiker {$user->name} <{$user->email}>: {$how}");

            // 2. Koppel het lid aan elk beheerteam met dezelfde rol (handmatig, blijft behouden).
            foreach ($teamPivots as $team) {
                $role = $team->pivot->role ?? Member::ROLE_COACH;
                $affectedTeamIds[$team->id] = true;
                $couplings++;
                $this->line(sprintf('   -> %s als %s', $team->name, $role));
                if (! $dry && $member) {
                    $member->teams()->syncWithoutDetaching([
                        $team->id => ['role' => $role, 'is_manual' => true],
                    ]);
                }
            }
        }

        $this->info("Leden nieuw: {$created}, gekoppeld aan bestaand lid: {$linked}, team-koppelingen: {$couplings}");

        if ($dry) {
            $this->comment('DRY-RUN: er is niets gewijzigd.');
            return self::SUCCESS;
        }

        // 3. Wedstrijden van de betrokken teams alsnog koppelen.
        if (! $this->option('no-backfill')) {
            $coupled = 0;
            foreach (array_keys($affectedTeamIds) as $teamId) {
                $team = Team::find($teamId);
                if (! $team) {
                    continue;
                }
                $coachIds = $team->matchDefaultCoaches()->pluck('id')->all();
                if (empty($coachIds)) {
                    continue;
                }
                foreach ($team->matches()->get() as $match) {
                    if ($match->coaches()->count() === 0) {
                        $match->coaches()->syncWithoutDetaching($coachIds);
                        $coupled++;
                    }
                    if (! $match->coach_id) {
                        $match->coach_id = $coachIds[0];
                        $match->save();
                    }
                }
            }
            $this->info("Wedstrijden bijgekoppeld: {$coupled}");
        }

        return self::SUCCESS;
    }
}
