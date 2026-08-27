<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Absence;
use App\Models\AgendaCategory;
use App\Models\AgendaItem;
use App\Models\AgendaRegistration;
use App\Models\BarDuty;
use App\Models\Club;
use App\Models\FootballMatch;
use App\Models\Goal;
use App\Models\GuardianLink;
use App\Models\MatchEvent;
use App\Models\Member;
use App\Models\NewsItem;
use App\Models\Setting;
use App\Models\StaffGroup;
use App\Models\Team;
use App\Models\TrainingSchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Eén demo-club met alles erop en eraan, om de app en de portal mee te tonen of
 * te testen zonder aan echte leden te zitten.
 *
 * Idempotent: alles hangt aan een vaste sleutel (clubslug, DEMO-voorvoegsel op
 * external_id, vaste e-mailadressen), dus een tweede run werkt bij in plaats van
 * te verdubbelen. Datums worden alleen bij het aanmaken gezet — wil je verse
 * datums, gebruik dan `php artisan demo:club --fresh`.
 *
 * Draai RolesAndPermissionsSeeder eerst; assignRole() vereist bestaande rollen.
 */
class DemoClubSeeder extends Seeder
{
    /** Waaraan de demo-gegevens te herkennen zijn. Ook gebruikt door --fresh. */
    public const CLUB_SLUG   = 'demo';
    public const ID_PREFIX   = 'DEMO-';
    public const MAIL_DOMAIN = 'demo.example.com';

    /**
     * Vast wachtwoord, bewust in de broncode.
     *
     * Testen zonder mailbox was de eis. Het geeft alleen toegang tot deze
     * demo-club; echte gegevens zitten er niet in.
     */
    public const PASSWORD = 'demo-voetbal-2026';

    public function run(): void
    {
        $this->ruimResten();

        $club   = $this->club();
        $team   = $this->team($club);
        $leden  = $this->leden($team);
        $users  = $this->accounts($club, $team, $leden);

        $this->trainingen($club, $team);
        $wedstrijden = $this->wedstrijden($team, $leden);
        $this->verslag($wedstrijden['gespeeld'], $leden, $users['beheerder']);
        $this->afmeldingen($club, $wedstrijden['komend'], $leden);
        $this->bardiensten($club, $team, $leden, $users['beheerder']);
        $this->stafgroep($club, $team, $leden);
        $this->nieuws($club, $users['beheerder']);

        // Bestaande per-club seeders; beide slaan clubs over die al gevuld zijn.
        $this->call(AgendaCategoriesSeeder::class);
        $this->call(OnboardingSlidesSeeder::class);

        $this->agenda($club, $users['beheerder'], $leden);
    }

    /**
     * Verwijderde demo-rijen van een eerdere run definitief opruimen.
     *
     * Teams, leden en wedstrijden gebruiken SoftDeletes, maar de unieke index op
     * external_id geldt gewoon door. Een verwijderde rij houdt zijn sleutel dus
     * bezet en laat firstOrCreate hieronder stuklopen op een sleutelbotsing in
     * plaats van hem netjes te hergebruiken.
     */
    private function ruimResten(): void
    {
        $demo = self::ID_PREFIX . '%';

        Member::onlyTrashed()->where('external_id', 'like', $demo)->forceDelete();
        Team::onlyTrashed()->where('external_id', 'like', $demo)->forceDelete();
        FootballMatch::onlyTrashed()->where('external_id', 'like', $demo)->forceDelete();
    }

    // ── Club ────────────────────────────────────────────────────────────────

    private function club(): Club
    {
        $club = Club::firstOrCreate(
            ['slug' => self::CLUB_SLUG],
            [
                'name'            => 'Demo Voetbalvereniging',
                'is_active'       => true,
                'city'            => 'Denekamp',
                'email'           => 'info@' . self::MAIL_DOMAIN,
                'primary_color'   => '#1e3a5f',
                'secondary_color' => '#3b82f6',
                'accent_color'    => '#10b981',
            ],
        );

        // De belangrijkste vangrail: zonder dit kan een Sportlink-sync op deze
        // club losgaan en de demo-leden loskoppelen van hun elftal.
        Setting::set('mcp_enabled', '0', 'integraties', false, $club->id);

        return $club;
    }

    private function team(Club $club): Team
    {
        return Team::firstOrCreate(
            ['external_id' => self::ID_PREFIX . 'TEAM'],
            [
                'club_id'   => $club->id,
                'name'      => 'JO13-1',
                'category'  => 'jeugd',
                'age_group' => 'JO13',
                'season'    => self::seizoen(),
                'is_active' => true,
            ],
        );
    }

    // ── Leden ───────────────────────────────────────────────────────────────

    /**
     * Twaalf spelers, een coach en een leider.
     *
     * is_manual op de koppeling staat bewust aan: daarmee laat de
     * Sportlink-sync deze leden met rust, ook als de MCP later toch aangaat.
     *
     * @return array<string, Member>  gesleuteld op het DEMO-nummer
     */
    private function leden(Team $team): array
    {
        $spelers = [
            ['Sem Bakker', 1], ['Daan Visser', 2], ['Luuk de Jong', 3],
            ['Finn Mulder', 4], ['Milan Smit', 5], ['Noah Bos', 6],
            ['Levi Dijkstra', 7], ['Jesse Vermeulen', 8], ['Tim Hoekstra', 9],
            ['Bram Kuipers', 10], ['Lars Peters', 11], ['Sven Willems', 12],
        ];

        $leden  = [];
        $nummer = 1;

        foreach ($spelers as [$naam, $rugnummer]) {
            $leden[$this->code($nummer)] = $this->lid(
                $team,
                $this->code($nummer),
                $naam,
                'player',
                $rugnummer,
                Carbon::now()->subYears(12)->subMonths($nummer * 2)->toDateString(),
            );
            $nummer++;
        }

        $leden['staf-coach'] = $this->lid(
            $team, self::ID_PREFIX . 'C01', 'Erik van Dalen', 'coach', null,
            Carbon::now()->subYears(41)->toDateString(),
        );

        $leden['staf-leider'] = $this->lid(
            $team, self::ID_PREFIX . 'L01', 'Marloes Groothuis', 'leider', null,
            Carbon::now()->subYears(38)->toDateString(),
        );

        return $leden;
    }

    private function lid(
        Team $team,
        string $externalId,
        string $naam,
        string $rol,
        ?int $rugnummer,
        string $geboortedatum,
    ): Member {
        $delen = explode(' ', $naam);

        $lid = Member::firstOrCreate(
            ['external_id' => $externalId],
            [
                'name'          => $naam,
                'last_name'     => implode(' ', array_slice($delen, 1)),
                'email'         => strtolower($delen[0]) . '.' . strtolower(end($delen))
                    . '@' . self::MAIL_DOMAIN,
                'phone'         => '06' . str_pad((string) random_int(10000000, 99999999), 8, '0'),
                'date_of_birth' => $geboortedatum,
                'role'          => $rol === 'leider' ? 'staff' : $rol,
                'shirt_number'  => $rugnummer,
                'is_active'     => true,
            ],
        );

        // syncWithoutDetaching: bij een tweede run bestaat de koppeling al, en
        // attach() zou dan op de unieke index stuklopen.
        $lid->teams()->syncWithoutDetaching([
            $team->id => [
                'role'      => $rol,
                'season'    => self::seizoen(),
                'is_active' => true,
                'is_manual' => true,
            ],
        ]);

        return $lid;
    }

    // ── Accounts ────────────────────────────────────────────────────────────

    /** @return array<string, User> */
    private function accounts(Club $club, Team $team, array $leden): array
    {
        $beheerder = $this->account(
            $club, 'beheerder@' . self::MAIL_DOMAIN, 'Erik van Dalen', 'club_admin',
            $leden['staf-coach'],
        );

        // Als coach aan het elftal: dat geeft in de app de rechten op opstelling,
        // rijders, verslag en aanwezigheid.
        $beheerder->managedTeams()->syncWithoutDetaching([$team->id => ['role' => 'coach']]);

        $speler = $this->account(
            $club, 'speler@' . self::MAIL_DOMAIN, 'Sem Bakker', 'member',
            $leden[$this->code(1)],
        );

        $ouder = $this->account(
            $club, 'ouder@' . self::MAIL_DOMAIN, 'Anouk Bakker', 'guardian', null,
        );

        // De ouder heeft zelf een lid nodig: guardian_links koppelt twee leden,
        // geen twee accounts.
        $ouderLid = Member::firstOrCreate(
            ['external_id' => self::ID_PREFIX . 'O01'],
            [
                'name'      => 'Anouk Bakker',
                'last_name' => 'Bakker',
                'email'     => $ouder->email,
                'role'      => 'staff',
                'is_active' => true,
                'user_id'   => $ouder->id,
            ],
        );

        GuardianLink::firstOrCreate(
            [
                'guardian_member_id' => $ouderLid->id,
                'child_member_id'    => $leden[$this->code(1)]->id,
            ],
            [
                'club_id'       => $club->id,
                'status'        => 'approved',
                'request_token' => GuardianLink::generateToken(),
                'resolved_at'   => Carbon::now()->subWeeks(3),
                'expires_at'    => Carbon::now()->addYears(10),
            ],
        );

        return ['beheerder' => $beheerder, 'speler' => $speler, 'ouder' => $ouder];
    }

    /**
     * withTrashed vóór het aanmaken: een verwijderd account houdt zijn
     * e-mailadres bezet in de unieke index, en User::create klapt daar dan op
     * met een sleutelbotsing in plaats van een uitlegbare fout.
     */
    private function account(
        Club $club,
        string $email,
        string $naam,
        string $rol,
        ?Member $lid,
    ): User {
        $user = User::withTrashed()->where('email', $email)->first();

        if ($user) {
            $user->restore();
            $user->forceFill([
                'name'      => $naam,
                'password'  => self::PASSWORD,
                'club_id'   => $club->id,
                'is_active' => true,
            ])->save();
        } else {
            $user = User::create([
                'name'      => $naam,
                'email'     => $email,
                'password'  => self::PASSWORD,
                'club_id'   => $club->id,
                'is_active' => true,
            ]);
        }

        $user->syncRoles([$rol]);

        if ($lid && $lid->user_id !== $user->id) {
            $lid->update(['user_id' => $user->id]);
        }

        return $user;
    }

    // ── Trainingen ──────────────────────────────────────────────────────────

    private function trainingen(Club $club, Team $team): void
    {
        // weekday is ISO 1..7 (maandag..zondag) — let op: BarDuty gebruikt de
        // Carbon-conventie met zondag = 0.
        foreach ([[2, '18:00', '19:15'], [4, '18:00', '19:15']] as [$dag, $start, $eind]) {
            TrainingSchedule::firstOrCreate(
                ['team_id' => $team->id, 'weekday' => $dag, 'start_time' => $start],
                [
                    'club_id'   => $club->id,
                    'end_time'  => $eind,
                    'location'  => 'Sportpark De Demo, veld 2',
                    'is_active' => true,
                ],
            );
        }
    }

    // ── Wedstrijden ─────────────────────────────────────────────────────────

    /** @return array{gespeeld: FootballMatch, komend: FootballMatch} */
    private function wedstrijden(Team $team, array $leden): array
    {
        $coach  = $leden['staf-coach'];
        $vlagger = $leden['staf-leider'];

        $rijen = [
            ['W01', 'SV Voorbeeld JO13-1',  -21, true,  'Sportpark De Demo',    3, 1],
            ['W02', 'VV Testdorp JO13-2',   -14, false, 'Sportpark Testdorp',   1, 2],
            ['W03', 'FC Proefstad JO13-1',   -7, true,  'Sportpark De Demo',    4, 4],
            ['W04', 'RKSV Steekproef JO13-1', 3, false, 'Sportpark Steekproef', null, null],
            ['W05', 'SC Voorbeeldveen JO13-1', 10, true, 'Sportpark De Demo',   null, null],
            ['W06', 'VV Naslag JO13-1',      17, false, 'Sportpark Naslag',     null, null],
        ];

        $gespeeld = null;
        $komend   = null;

        foreach ($rijen as $i => [$code, $tegenstander, $dagen, $thuis, $locatie, $voor, $tegen]) {
            $aftrap = Carbon::now()->startOfWeek()->addDays($dagen)->setTime(9, 30);
            $isGespeeld = $voor !== null;

            $wedstrijd = FootballMatch::firstOrCreate(
                ['external_id' => self::ID_PREFIX . $code],
                [
                    'team_id'        => $team->id,
                    'opponent'       => $tegenstander,
                    'match_datetime' => $aftrap,
                    'arrival_time'   => $aftrap->copy()->subMinutes(45)->format('H:i'),
                    'is_home'        => $thuis,
                    'location'       => $locatie,
                    'status'         => $isGespeeld ? 'played' : 'scheduled',
                    'score_home'     => $isGespeeld ? ($thuis ? $voor : $tegen) : null,
                    'score_away'     => $isGespeeld ? ($thuis ? $tegen : $voor) : null,
                    'coach_id'       => $coach->id,
                    'vlagger_id'     => $vlagger->id,
                    'fruit_hero_id'  => $leden[$this->code(($i % 12) + 1)]->id,
                ],
            );

            // Rijders bij de uitwedstrijden; thuis rijdt niemand.
            if (! $thuis) {
                $wedstrijd->drivers()->syncWithoutDetaching([
                    $leden['staf-coach']->id,
                    $leden['staf-leider']->id,
                ]);
            }

            if ($isGespeeld) {
                $this->doelpunten($wedstrijd, $leden, $voor);
                $gespeeld ??= $wedstrijd;
            } else {
                $komend ??= $wedstrijd;
            }
        }

        return ['gespeeld' => $gespeeld, 'komend' => $komend];
    }

    /** Doelpunten met maker en assist; die voeden de cijfers op het dashboard. */
    private function doelpunten(FootballMatch $wedstrijd, array $leden, int $aantal): void
    {
        if (Goal::where('match_id', $wedstrijd->id)->exists()) {
            return;
        }

        for ($n = 0; $n < $aantal; $n++) {
            Goal::create([
                'match_id'  => $wedstrijd->id,
                'scorer_id' => $leden[$this->code(9 + ($n % 3))]->id,
                'assist_id' => $leden[$this->code(7 + ($n % 2))]->id,
                'minute'    => 8 + ($n * 13),
            ]);
        }
    }

    /**
     * Een live verslag bij één gespeelde wedstrijd, zodat het tabblad Verslag en
     * de verslagenlijst onder Meer niet leeg zijn.
     */
    private function verslag(?FootballMatch $wedstrijd, array $leden, User $door): void
    {
        if ($wedstrijd === null || MatchEvent::where('match_id', $wedstrijd->id)->exists()) {
            return;
        }

        $regels = [
            ['kickoff', 0, null, null, null, null],
            ['goal', 8, 'own', $this->code(9), $this->code(7), null],
            ['goal', 21, 'opponent', null, null, null],
            ['halftime', 30, null, null, null, null],
            ['second_half', 30, null, null, null, null],
            ['goal', 42, 'own', $this->code(10), $this->code(8), null],
            ['card', 48, 'own', $this->code(4), null, 'yellow'],
            ['substitution', 52, null, $this->code(12), $this->code(3), null],
            ['goal', 57, 'own', $this->code(11), null, null],
            ['fulltime', 60, null, null, null, null],
        ];

        foreach ($regels as [$type, $minuut, $kant, $lid, $tweede, $kaart]) {
            MatchEvent::create([
                'match_id'          => $wedstrijd->id,
                'type'              => $type,
                'minute'            => $minuut,
                'side'              => $kant,
                'member_id'         => $lid ? $leden[$lid]->id : null,
                'related_member_id' => $tweede ? $leden[$tweede]->id : null,
                'card_type'         => $kaart,
                'created_by'        => $door->id,
            ]);
        }

        $wedstrijd->update([
            'live_started_at' => $wedstrijd->match_datetime,
            'live_ended_at'   => $wedstrijd->match_datetime->copy()->addMinutes(75),
        ]);
    }

    // ── Af- en aanmeldingen ─────────────────────────────────────────────────

    private function afmeldingen(Club $club, ?FootballMatch $komend, array $leden): void
    {
        if ($komend === null) {
            return;
        }

        foreach ([[5, 'Ziek'], [6, 'Familieweekend']] as [$nr, $reden]) {
            Absence::firstOrCreate(
                [
                    'member_id' => $leden[$this->code($nr)]->id,
                    'match_id'  => $komend->id,
                    'type'      => 'match',
                ],
                ['club_id' => $club->id, 'reason' => $reden],
            );
        }
    }

    // ── Bardiensten ─────────────────────────────────────────────────────────

    /**
     * Zeven bardiensten over drie weekenden.
     *
     * Genoeg om het rooster echt te zien werken: een dienst op naam van het
     * demo-account (zodat "Mijn taken" op het dashboard gevuld is), een paar
     * volle diensten, een paar die nog open staan zodat je je kunt aanmelden, en
     * één handmatige dienst met een eigen tijd en bezetting.
     *
     * De shift-sleutel moet bij de weekdag van de datum horen: BarDuty::SHIFTS
     * kent alleen za_* (zaterdag) en zo_* (zondag), in de Carbon-conventie met
     * zondag = 0. Dat is een andere telling dan training_schedules.weekday, dat
     * ISO 1–7 gebruikt.
     */
    private function bardiensten(Club $club, Team $team, array $leden, User $beheerder): void
    {
        $zaterdag = Carbon::now()->next(Carbon::SATURDAY)->startOfDay();
        $zondag   = Carbon::now()->next(Carbon::SUNDAY)->startOfDay();

        // [datum, shift, wie erop staat, notitie]
        // 'ik'   = het demo-account, zodat het dashboard een taak toont
        // 'staf' = twee stafleden; die vullen een dienst helemaal
        // 'half' = één lid op een dienst voor drie, dus nog plek over
        // 'leeg' = niemand; hierop kun je jezelf aanmelden in de app
        $rijen = [
            [$zaterdag,                        'za_ochtend', 'ik',   null],
            [$zaterdag,                        'za_middag',  'staf', null],
            [$zaterdag,                        'za_avond1',  'half', 'Na afloop opruimen met het team.'],
            [$zondag,                          'zo_ochtend', 'leeg', null],
            [$zondag,                          'zo_middag1', 'staf', null],
            [$zaterdag->copy()->addWeek(),     'za_ochtend', 'leeg', null],
            [$zaterdag->copy()->addWeeks(2),   'za_middag',  'half', null],
        ];

        foreach ($rijen as [$datum, $shift, $bezetting, $notitie]) {
            $dienst = BarDuty::firstOrCreate(
                ['club_id' => $club->id, 'date' => $datum->toDateString(), 'shift' => $shift],
                ['team_id' => $team->id, 'status' => 'open', 'notes' => $notitie],
            );

            match ($bezetting) {
                'ik' => $dienst->users()->syncWithoutDetaching([$beheerder->id => ['spots' => 1]]),
                'staf' => $dienst->members()->syncWithoutDetaching([
                    $leden['staf-coach']->id  => ['spots' => 2],
                    $leden['staf-leider']->id => ['spots' => 1],
                ]),
                'half' => $dienst->members()->syncWithoutDetaching([
                    $leden['staf-leider']->id => ['spots' => 1],
                ]),
                default => null,
            };

            // Zet open of bevestigd op basis van de bezetting; 'vervuld' blijft
            // staan als iemand die status handmatig heeft gezet.
            $dienst->refreshStatus();
        }

        // Eén handmatige dienst: eigen label, eigen tijd en eigen bezetting.
        // Die tak van het rooster werkt anders dan de vaste dagdelen en is
        // zonder voorbeeld niet te zien.
        $toernooi = BarDuty::firstOrCreate(
            [
                'club_id' => $club->id,
                'date'    => $zaterdag->copy()->addWeeks(3)->toDateString(),
                'shift'   => BarDuty::SHIFT_CUSTOM,
            ],
            [
                'team_id'        => $team->id,
                'custom_label'   => 'Toernooidag',
                'start_time'     => '08:30',
                'end_time'       => '18:00',
                'required_count' => 4,
                'status'         => 'open',
                'notes'          => 'Lange dag; we wisselen halverwege.',
            ],
        );

        $toernooi->members()->syncWithoutDetaching([
            $leden['staf-coach']->id => ['spots' => 1],
        ]);
        $toernooi->refreshStatus();
    }

    // ── Stafgroep ───────────────────────────────────────────────────────────

    private function stafgroep(Club $club, Team $team, array $leden): void
    {
        $groep = StaffGroup::firstOrCreate(
            ['club_id' => $club->id, 'name' => 'Bardienstcommissie'],
            ['team_id' => $team->id, 'description' => 'Regelt de bezetting achter de bar.'],
        );

        $groep->members()->syncWithoutDetaching([
            $leden['staf-coach']->id,
            $leden['staf-leider']->id,
        ]);
    }

    // ── Nieuws ──────────────────────────────────────────────────────────────

    private function nieuws(Club $club, User $auteur): void
    {
        $berichten = [
            ['Seizoen van start', 'algemeen', 21,
                "De competitie is weer begonnen. Alle elftallen zijn ingedeeld en de eerste wedstrijden staan op het programma.\n\nKom je kijken? Er is koffie."],
            ['Nieuwe kleding voor de jeugd', 'jeugd', 10,
                "Dankzij onze sponsor lopen alle jeugdteams dit seizoen in nieuwe tenues.\n\nDe uitreiking is na de training van donderdag."],
            ['Vrijwilligers gezocht voor de bar', 'algemeen', 3,
                "We zoeken nog een paar handen achter de bar op zaterdagochtend.\n\nMeld je via de app aan bij een dienst, of spreek iemand van de commissie aan."],
        ];

        foreach ($berichten as [$titel, $categorie, $dagenGeleden, $tekst]) {
            NewsItem::firstOrCreate(
                ['club_id' => $club->id, 'title' => $titel],
                [
                    'author_id'    => $auteur->id,
                    'body'         => $tekst,
                    'category'     => $categorie,
                    'is_published' => true,
                    // In het verleden: scopePublished() laat toekomstige
                    // publicatiedatums buiten beeld.
                    'published_at' => Carbon::now()->subDays($dagenGeleden),
                ],
            );
        }
    }

    // ── Agenda ──────────────────────────────────────────────────────────────

    private function agenda(Club $club, User $auteur, array $leden): void
    {
        $categorie = fn (string $slug) => AgendaCategory::where('club_id', $club->id)
            ->where('slug', $slug)->value('id');

        $items = [
            ['Algemene ledenvergadering', 'vereniging', 12, false,
                'Jaarlijkse vergadering in de kantine. Aanvang 20:00 uur.'],
            ['Vrijwilligersavond', 'vrijwilligers', 26, true,
                'Een avond voor iedereen die zich het hele jaar inzet. Meld je aan zodat we weten op hoeveel mensen we rekenen.'],
            ['Voorjaarstoernooi', 'toernooi', 40, false,
                'Toernooi voor alle jeugdteams, de hele dag op het sportpark.'],
        ];

        foreach ($items as [$titel, $slug, $dagen, $inschrijven, $tekst]) {
            $item = AgendaItem::firstOrCreate(
                ['club_id' => $club->id, 'title' => $titel],
                [
                    'agenda_category_id'   => $categorie($slug),
                    'created_by'           => $auteur->id,
                    'summary'              => $tekst,
                    'starts_at'            => Carbon::now()->addDays($dagen)->setTime(20, 0),
                    'ends_at'              => Carbon::now()->addDays($dagen)->setTime(22, 0),
                    'location'             => 'Kantine Sportpark De Demo',
                    'audience'             => 'everyone',
                    'registration_enabled' => $inschrijven,
                    'is_published'         => true,
                    'published_at'         => Carbon::now()->subDays(2),
                ],
            );

            if (! $inschrijven) {
                continue;
            }

            // name is verplicht; subject_key en registered_at zet het model zelf.
            foreach (['staf-coach', 'staf-leider'] as $sleutel) {
                AgendaRegistration::firstOrCreate(
                    ['agenda_item_id' => $item->id, 'member_id' => $leden[$sleutel]->id],
                    [
                        'club_id'     => $club->id,
                        'name'        => $leden[$sleutel]->name,
                        'status'      => 'aangemeld',
                        'guest_count' => 1,
                    ],
                );
            }
        }
    }

    // ── Hulpjes ─────────────────────────────────────────────────────────────

    private function code(int $nummer): string
    {
        return self::ID_PREFIX . 'M' . str_pad((string) $nummer, 2, '0', STR_PAD_LEFT);
    }

    /** Voetbalseizoen loopt van juli tot juli; zelfde notatie als het dashboard. */
    private static function seizoen(): string
    {
        $jaar = Carbon::now()->month >= 7 ? Carbon::now()->year : Carbon::now()->year - 1;

        return $jaar . '/' . ($jaar + 1);
    }
}
