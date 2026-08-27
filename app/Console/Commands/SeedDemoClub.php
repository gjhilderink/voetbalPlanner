<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Club;
use Database\Seeders\DemoClubSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bouwt de demo-club op, en veegt hem met --fresh eerst helemaal weg.
 *
 * De vlag zit hier en niet in de seeder omdat `db:seed` geen eigen opties
 * doorgeeft. De seeder blijft los aanroepbaar; dit commando is de handige kant.
 */
class SeedDemoClub extends Command
{
    protected $signature = 'demo:club
        {--fresh : Verwijder de bestaande demo-club eerst volledig}
        {--force : Sla de bevestigingsvraag over}';

    protected $description = 'Zet een demo-club klaar met leden, wedstrijden, trainingen, bardiensten, nieuws en agenda.';

    public function handle(): int
    {
        if ($this->option('fresh')) {
            if (! $this->wisDemoClub()) {
                return self::FAILURE;
            }
        }

        $this->info('Demo-club opbouwen…');
        $this->call('db:seed', ['--class' => DemoClubSeeder::class, '--force' => true]);

        $this->newLine();
        $this->info('Klaar. Inloggen kan met:');
        $this->line('  beheerder@' . DemoClubSeeder::MAIL_DOMAIN . '   (club_admin + coach)');
        $this->line('  speler@' . DemoClubSeeder::MAIL_DOMAIN . '      (speler)');
        $this->line('  ouder@' . DemoClubSeeder::MAIL_DOMAIN . '       (ouder van de speler)');
        $this->line('  wachtwoord: ' . DemoClubSeeder::PASSWORD);
        $this->newLine();
        $this->line('Portal: /admin/' . DemoClubSeeder::CLUB_SLUG);

        return self::SUCCESS;
    }

    /**
     * Verwijdert de demo-club en alles wat eraan hangt.
     *
     * Waarom dit niet aan de database overgelaten kan worden: `teams.club_id` en
     * `users.club_id` zijn nullOnDelete, dus die blijven als wezen achter met hun
     * hele tak eronder. En `members` heeft helemaal geen club_id — die hangen
     * alleen via member_team aan een team.
     *
     * Alles gaat via de query builder en niet via Eloquent: teams, members,
     * matches en users gebruiken SoftDeletes, en `$model->delete()` is dan een
     * UPDATE. De unieke indexen op e-mail en external_id blijven daarmee bezet,
     * waardoor de volgende run stukloopt. De query builder omzeilt die scope en
     * ruimt meteen de al soft-deleted resten van eerdere runs op.
     */
    private function wisDemoClub(): bool
    {
        $club = Club::where('slug', DemoClubSeeder::CLUB_SLUG)->first();

        if (! $club) {
            $this->line('Geen demo-club gevonden; er valt niets te wissen.');

            return true;
        }

        if (! $this->option('force')
            && ! $this->confirm("Demo-club '{$club->name}' en alle bijbehorende gegevens definitief verwijderen?")) {
            $this->comment('Afgebroken; er is niets gewijzigd.');

            return false;
        }

        $clubId = $club->id;

        // ── Eerst alles opzoeken; na de eerste delete zijn de sporen weg ──────
        $teamIds = DB::table('teams')->where('club_id', $clubId)->pluck('id');

        $userIds = DB::table('users')
            ->where('club_id', $clubId)
            ->orWhere('email', 'like', '%@' . DemoClubSeeder::MAIL_DOMAIN)
            ->pluck('id');

        $emails = DB::table('users')->whereIn('id', $userIds)->pluck('email');

        $matchIds  = DB::table('matches')->whereIn('team_id', $teamIds)->pluck('id');
        $lineupIds = DB::table('lineups')->whereIn('match_id', $matchIds)->pluck('id');
        $barIds    = DB::table('bar_duties')->where('club_id', $clubId)->pluck('id');
        $groepIds  = DB::table('staff_groups')->where('club_id', $clubId)->pluck('id');
        $agendaIds = DB::table('agenda_items')->where('club_id', $clubId)->pluck('id');

        // Leden op het DEMO-voorvoegsel én via de koppeltabel. Het voorvoegsel
        // alleen zou een half mislukte vorige run kunnen missen; de koppeltabel
        // alleen zou leden missen die al losgekoppeld zijn.
        $memberIds = DB::table('members')
            ->where('external_id', 'like', DemoClubSeeder::ID_PREFIX . '%')
            ->orWhereIn('user_id', $userIds)
            ->orWhereIn('id', DB::table('member_team')->whereIn('team_id', $teamIds)->pluck('member_id'))
            ->pluck('id');

        DB::transaction(function () use (
            $clubId, $teamIds, $userIds, $emails, $matchIds, $lineupIds,
            $barIds, $groepIds, $agendaIds, $memberIds
        ) {
            // Van blad naar wortel. Elke tabel expliciet, zodat het ook klopt
            // wanneer foreign keys uitstaan (SQLite met DB_FOREIGN_KEYS=false).
            $this->wis('match_events', fn ($q) => $q->whereIn('match_id', $matchIds));
            $this->wis('match_photos', fn ($q) => $q->whereIn('match_id', $matchIds));
            $this->wis('goals', fn ($q) => $q->whereIn('match_id', $matchIds));
            $this->wis('lineup_players', fn ($q) => $q->whereIn('lineup_id', $lineupIds));
            $this->wis('lineups', fn ($q) => $q->whereIn('match_id', $matchIds));
            $this->wis('match_drivers', fn ($q) => $q->whereIn('match_id', $matchIds));
            $this->wis('match_coaches', fn ($q) => $q->whereIn('match_id', $matchIds));
            $this->wis('match_cleaners', fn ($q) => $q->whereIn('match_id', $matchIds));
            $this->wis('match_guest_invitations', fn ($q) => $q->where('club_id', $clubId)
                ->orWhereIn('match_id', $matchIds));

            $this->wis('absences', fn ($q) => $q->where('club_id', $clubId)
                ->orWhereIn('match_id', $matchIds)
                ->orWhereIn('member_id', $memberIds)
                ->orWhereIn('user_id', $userIds));

            $this->wis('matches', fn ($q) => $q->whereIn('team_id', $teamIds));
            $this->wis('training_schedules', fn ($q) => $q->where('club_id', $clubId)
                ->orWhereIn('team_id', $teamIds));
            $this->wis('team_moods', fn ($q) => $q->where('club_id', $clubId)
                ->orWhereIn('team_id', $teamIds)
                ->orWhereIn('user_id', $userIds));

            $this->wis('agenda_registrations', fn ($q) => $q->where('club_id', $clubId)
                ->orWhereIn('agenda_item_id', $agendaIds));
            $this->wis('agenda_item_team', fn ($q) => $q->whereIn('agenda_item_id', $agendaIds)
                ->orWhereIn('team_id', $teamIds));
            $this->wis('agenda_item_staff_group', fn ($q) => $q->whereIn('agenda_item_id', $agendaIds)
                ->orWhereIn('staff_group_id', $groepIds));
            $this->wis('agenda_items', fn ($q) => $q->where('club_id', $clubId));
            $this->wis('agenda_categories', fn ($q) => $q->where('club_id', $clubId));

            $this->wis('bar_duty_member', fn ($q) => $q->whereIn('bar_duty_id', $barIds));
            $this->wis('bar_duty_user', fn ($q) => $q->whereIn('bar_duty_id', $barIds));
            $this->wis('bar_duties', fn ($q) => $q->where('club_id', $clubId));

            $this->wis('staff_group_member', fn ($q) => $q->whereIn('staff_group_id', $groepIds));
            $this->wis('staff_group_user', fn ($q) => $q->whereIn('staff_group_id', $groepIds));
            $this->wis('staff_groups', fn ($q) => $q->where('club_id', $clubId));

            $this->wis('guardian_links', fn ($q) => $q->where('club_id', $clubId)
                ->orWhereIn('guardian_member_id', $memberIds)
                ->orWhereIn('child_member_id', $memberIds));

            // swap_requests heeft geen enkele foreign key; zonder deze regel
            // blijft het altijd staan. target_id wijst polymorf naar een
            // wedstrijd óf een bardienst, zonder type-kolom.
            $this->wis('swap_requests', fn ($q) => $q->whereIn('target_id', $matchIds)
                ->orWhereIn('target_id', $barIds)
                ->orWhereIn('requester_id', $memberIds)
                ->orWhereIn('requestee_id', $memberIds));

            $this->wis('member_team', fn ($q) => $q->whereIn('team_id', $teamIds)
                ->orWhereIn('member_id', $memberIds));
            $this->wis('user_team', fn ($q) => $q->whereIn('team_id', $teamIds)
                ->orWhereIn('user_id', $userIds));

            $this->wis('members', fn ($q) => $q->whereIn('id', $memberIds));
            $this->wis('teams', fn ($q) => $q->where('club_id', $clubId));

            $this->wis('team_documents', fn ($q) => $q->where('club_id', $clubId)
                ->orWhereIn('team_id', $teamIds));
            $this->wis('news_items', fn ($q) => $q->where('club_id', $clubId));
            $this->wis('banners', fn ($q) => $q->where('club_id', $clubId));
            $this->wis('onboarding_slides', fn ($q) => $q->where('club_id', $clubId));

            // settings is nullOnDelete: zonder deze regel blijven de instellingen
            // staan met club_id NULL en gedragen ze zich als platformbrede
            // instellingen. Geen foutmelding, wel verkeerd.
            $this->wis('settings', fn ($q) => $q->where('club_id', $clubId));
            $this->wis('sync_logs', fn ($q) => $q->where('club_id', $clubId));
            $this->wis('bug_reports', fn ($q) => $q->where('club_id', $clubId)
                ->orWhereIn('user_id', $userIds));
            $this->wis('release_note_sends', fn ($q) => $q->whereIn('sent_by_user_id', $userIds));

            $this->wis('model_has_roles', fn ($q) => $q->where('model_type', \App\Models\User::class)
                ->whereIn('model_id', $userIds));
            $this->wis('model_has_permissions', fn ($q) => $q->where('model_type', \App\Models\User::class)
                ->whereIn('model_id', $userIds));
            $this->wis('personal_access_tokens', fn ($q) => $q->where('tokenable_type', \App\Models\User::class)
                ->whereIn('tokenable_id', $userIds));
            $this->wis('sessions', fn ($q) => $q->whereIn('user_id', $userIds));
            $this->wis('magic_link_tokens', fn ($q) => $q->whereIn('email', $emails));
            $this->wis('password_reset_tokens', fn ($q) => $q->whereIn('email', $emails));

            // Beheerders van het platform staan hier niet tussen: die kunnen aan
            // een club gekoppeld zijn zonder demo-account te zijn.
            $beheerders = DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('roles.name', 'super_admin')
                ->pluck('model_has_roles.model_id');

            $this->wis('users', fn ($q) => $q->whereIn('id', $userIds)
                ->whereNotIn('id', $beheerders));

            $this->wis('clubs', fn ($q) => $q->where('id', $clubId));
        });

        $this->info('Demo-club verwijderd.');

        return true;
    }

    /**
     * Eén tabel opruimen, met een controle of hij bestaat.
     *
     * Die controle staat er omdat dit commando ook op een oudere database moet
     * kunnen draaien waar een tabel nog niet gemigreerd is.
     */
    private function wis(string $tabel, callable $filter): void
    {
        if (! Schema::hasTable($tabel)) {
            return;
        }

        $query = DB::table($tabel);
        $filter($query);

        $aantal = $query->delete();

        if ($aantal > 0) {
            $this->line(sprintf('  %-28s %d', $tabel, $aantal));
        }
    }
}
