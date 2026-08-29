<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ManagesAttendance;
use App\Http\Controllers\Controller;
use App\Http\Resources\MatchResource;
use App\Models\Absence;
use App\Models\LineupPlayer;
use App\Models\FootballMatch;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MatchController extends Controller
{
    use ManagesAttendance;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        // De app stuurt team_id altijd mee, ook leeg; een lege string telt hier
        // als "niet opgegeven" (zie ook TrainingController).
        $teamId = $request->query('team_id') ?: null;

        $matches = FootballMatch::query()
            ->with(['team', 'coach', 'coaches', 'fruitHero', 'drivers'])
            // Altijd binnen de eigen club. Zonder deze regel gaf een call zonder
            // team_id én zonder mine=1 alle wedstrijden van álle clubs terug.
            ->whereHas('team', fn($t) => $t->where('club_id', $user?->club_id))
            // ?mine=1 beperkt tot de teams waaraan de gebruiker gekoppeld is
            // (eigen teams via user_team/member + kinderen). Gebruikt o.a. door
            // het rijschema zodat je alleen ritten van je eigen teams ziet.
            // Zonder expliciet team gebeurt dat nu ook: standaard je eigen teams,
            // niet de hele club.
            ->when($request->boolean('mine') || ! $teamId, function ($q) use ($user) {
                $ids = $user?->accessibleTeams()->pluck('id') ?? collect();
                $q->whereIn('team_id', $ids);
            })
            ->when($request->has('is_home'), fn($q) => $q->where('is_home', $request->boolean('is_home')))
            ->when($request->boolean('has_drivers'), fn($q) => $q->has('drivers'))
            ->when($teamId, fn($q, $id) => $q->where('team_id', $id))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->upcoming, fn($q) => $q->where('match_datetime', '>=', now()))
            ->when($request->date_from, fn($q, $d) => $q->where('match_datetime', '>=', $d))
            ->when($request->date_to, fn($q, $d) => $q->where('match_datetime', '<=', $d))
            ->orderBy('match_datetime')
            ->paginate($request->integer('per_page', 15));

        return response()->json(
            collect($matches->items())->map(fn($m) => (new MatchResource($m))->resolve())
        );
    }

    public function show(Request $request, FootballMatch $match): JsonResponse
    {
        $match->load(['team', 'coach', 'coaches', 'fruitHero', 'vlagger', 'drivers', 'lineup.players.member', 'goals.scorer', 'goals.assist']);

        $data = (new MatchResource($match))->resolve();

        // Af-/aanmeld-status: standaard is iedereen aangemeld; afmelden = een rij in absences.
        $absences = Absence::query()
            ->where('type', Absence::TYPE_MATCH)
            ->where('match_id', $match->id)
            ->with(['member:id,name', 'user:id,name'])
            ->get();
        $myMemberId = $request->user()?->resolveMember()?->id;
        $myUserId   = $request->user()?->id;

        $benLid  = $request->user()?->belongsToTeam($match->team_id) ?? false;
        $kindIds = $benLid
            ? collect()
            : $this->attendanceChildren($request, $match->team_id)->pluck('id');

        $data['mijn_status'] = self::statusVoor(
            $absences, $benLid, $myMemberId, $myUserId, $kindIds
        );
        $data['afmeldingen'] = $absences->map(fn ($a) => [
            'naam'  => $a->member?->name ?? $a->user?->name ?? '',
            'reden' => $a->reason,
        ])->values();

        // Af-/aanmelden alleen tonen als je (als lid/coach óf als ouder) aan het
        // team van deze wedstrijd gekoppeld bent.
        $accessibleTeamIds = $request->user()?->accessibleTeams()->pluck('id') ?? collect();
        $data['mag_afmelden'] = $accessibleTeamIds->contains($match->team_id);

        // Opstelling + score beheren mag alleen de coach van het team (of beheerder).
        $data['mag_opstelling'] = $request->user()?->canManageLineup($match->team_id) ?? false;

        // Is er al een live verslag aangemaakt? Niet hetzelfde als isLive(): ook
        // een afgefloten wedstrijd heeft er een. De app gebruikt dit om de knop
        // "Start live verslag" te vervangen door "Open live verslag", zodat een
        // tweede coach niet opnieuw begint aan iets wat al loopt.
        $data['live_gestart'] = $match->live_started_at !== null;

        // Afgelast: de app zet er een balk boven en verbergt het af-/aanmelden.
        // mag_afgelasten is dezelfde rechtencheck als hierboven, apart benoemd
        // zodat de app niet hoeft te raden wat "mag opstelling" nog meer inhoudt.
        $data['is_afgelast']     = $match->isCancelled();
        $data['afgelast_reden']  = (string) ($match->cancel_reason ?? '');
        $data['mag_afgelasten']  = $data['mag_opstelling'];

        // Korte doelpunten-samenvatting voor het coach-scherm ("12' Jan, 45' Piet").
        $data['goals_summary'] = $match->goalsSummary();

        // Uitgenodigde gastspelers (actief + niet-verlopen): zowel een komma-lijst
        // (weergave) als een array met member-id + naam (voor per-gast verwijderen).
        $guestInvites = \App\Models\MatchGuestInvitation::query()
            ->where('match_id', $match->id)
            ->active()
            ->with('member:id,name')
            ->get()
            ->filter(fn ($g) => $g->member !== null)
            ->values();
        $data['guestNames'] = $guestInvites->map(fn ($g) => $g->member->name)->join(', ');
        $data['guests'] = $guestInvites->map(fn ($g) => [
            'memberId' => $g->member->id,
            'name'     => $g->member->name,
        ])->values();

        return response()->json($data);
    }

    /**
     * POST /v1/matches/{match}/afgelasten   body: { reason }
     *
     * De wedstrijd gaat niet door. Alleen de coach; en de reden is verplicht,
     * want "afgelast" zonder uitleg levert precies de telefoontjes op die je
     * met deze knop wilde voorkomen.
     */
    public function afgelasten(Request $request, FootballMatch $match): JsonResponse
    {
        if (! $request->user()?->canManageLineup($match->team_id)) {
            return response()->json([
                'success' => false,
                'melding' => 'Alleen de coach kan een wedstrijd afgelasten.',
            ], 403);
        }

        $validated = $request->validate(['reason' => 'required|string|max:255']);

        $match->forceFill([
            'cancelled_at'         => now(),
            'cancel_reason'        => $validated['reason'],
            'cancelled_by_user_id' => $request->user()->id,
        ])->save();

        return response()->json([
            'success' => true,
            'melding' => 'De wedstrijd is afgelast.',
        ]);
    }

    /**
     * POST /v1/matches/{match}/vrijgeven
     *
     * Toch weer door. Alleen de eigen afgelasting wordt teruggedraaid: heeft de
     * bond de wedstrijd afgelast, dan staat dat in status en daar gaat deze knop
     * niet over.
     */
    public function vrijgeven(Request $request, FootballMatch $match): JsonResponse
    {
        if (! $request->user()?->canManageLineup($match->team_id)) {
            return response()->json([
                'success' => false,
                'melding' => 'Alleen de coach kan een wedstrijd vrijgeven.',
            ], 403);
        }

        $match->forceFill([
            'cancelled_at'         => null,
            'cancel_reason'        => null,
            'cancelled_by_user_id' => null,
        ])->save();

        return response()->json([
            'success' => true,
            'melding' => 'De wedstrijd gaat weer door.',
        ]);
    }

    /**
     * POST /v1/matches/{match}/afmelden   body: { reason }
     */
    public function afmelden(Request $request, FootballMatch $match): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'reason'    => 'required|string|max:255',
            // Alleen de coach mag deze meegeven; zie ManagesAttendance.
            'member_id' => 'nullable|uuid',
        ]);

        [$leden, $fout] = $this->attendanceTargets($request, $match->team_id);
        if ($fout) {
            return $fout;
        }

        foreach ($leden as $lid) {
            Absence::updateOrCreate($this->attendanceKey([
                'type'     => Absence::TYPE_MATCH,
                'match_id' => $match->id,
            ], $lid, $request), [
                'club_id' => $user->club_id,
                'reason'  => $validated['reason'],
            ]);
        }

        $this->haalUitOpstelling($match, $leden);

        return response()->json([
            'success' => true,
            'message' => $this->attendanceMessage($request, $leden, 'afgemeld', 'deze wedstrijd'),
        ]);
    }

    /**
     * DELETE /v1/matches/{match}/afmelden   (= weer aanmelden)
     */
    public function aanmelden(Request $request, FootballMatch $match): JsonResponse
    {
        $request->validate(['member_id' => 'nullable|uuid']);

        [$leden, $fout] = $this->attendanceTargets($request, $match->team_id);
        if ($fout) {
            return $fout;
        }

        foreach ($leden as $lid) {
            Absence::query()
                ->where($this->attendanceKey([
                    'type'     => Absence::TYPE_MATCH,
                    'match_id' => $match->id,
                ], $lid, $request))
                ->delete();
        }

        return response()->json([
            'success' => true,
            'message' => $this->attendanceMessage($request, $leden, 'weer aangemeld', 'deze wedstrijd'),
        ]);
    }

    /**
     * Haalt afgemelde spelers uit de opstelling.
     *
     * Ze werden alleen rood gekleurd, en bleven zo een plek bezetten. Op het
     * veld leek het dan alsof er elf man stonden terwijl er tien konden spelen,
     * en de coach moest zelf bedenken dat hij iemand moest weghalen voor hij de
     * vervanger kwijt kon.
     *
     * Alle perioden ineens: een speler die er niet is, is er in geen enkele
     * periode. Meldt hij zich later weer aan, dan komt hij niet vanzelf terug op
     * zijn oude plek - die is dan misschien al door een ander ingenomen, en dat
     * is een keuze van de coach en niet van de administratie.
     *
     * @param  array<int, ?\App\Models\Member>  $leden
     */
    private function haalUitOpstelling(FootballMatch $match, array $leden): void
    {
        $ids = collect($leden)
            ->filter()
            ->map(fn ($lid) => $lid->id)
            ->all();

        if (! $ids) {
            return;
        }

        $lineup = $match->lineup()->first();

        if (! $lineup) {
            return;
        }

        LineupPlayer::query()
            ->where('lineup_id', $lineup->id)
            ->whereIn('member_id', $ids)
            ->delete();
    }

    /**
     * GET /v1/matches/{match}/deelnemers
     *
     * De hele selectie met per speler of hij is af- of aangemeld. De coach heeft
     * die lijst nodig om iemand namens hem af te melden; /afmeldingen geeft
     * alleen de afmeldingen en dus niet wie er nog wél staat.
     */
    public function deelnemers(Request $request, FootballMatch $match): JsonResponse
    {
        $absences = Absence::query()
            ->where('type', Absence::TYPE_MATCH)
            ->where('match_id', $match->id)
            ->with(['member:id,name', 'user:id,name'])
            ->get();

        $redenPerLid = $absences
            ->filter(fn (Absence $a) => $a->member_id !== null)
            ->mapWithKeys(fn (Absence $a) => [$a->member_id => (string) $a->reason]);

        $mag = $request->user()?->canManageLineup($match->team_id) ? 'true' : 'false';

        $rows = [];
        // Alleen spelers: een coach of leider staat niet in de opkomstlijst en
        // meldt zich ook niet af voor een wedstrijd.
        foreach ($match->team?->playingMembers()->orderBy('members.name')->get() ?? collect() as $member) {
            $afgemeld = $redenPerLid->has($member->id);
            $rows[] = [
                'memberId' => $member->id,
                'naam'     => $member->name,
                'status'   => $afgemeld ? 'afgemeld' : 'aangemeld',
                'reden'    => $afgemeld ? $redenPerLid->get($member->id) : '',
            ];
        }

        // Losse accounts zonder lidnummer staan niet in de teamlijst maar horen
        // er wel bij; de coach kan ze niet omzetten, vandaar een lege memberId.
        foreach ($absences->filter(fn (Absence $a) => $a->member_id === null) as $absence) {
            if (! $naam = $absence->user?->name) {
                continue;
            }
            $rows[] = [
                'memberId' => '',
                'naam'     => $naam,
                'status'   => 'afgemeld',
                'reden'    => (string) $absence->reason,
            ];
        }

        usort($rows, fn ($a, $b) => [$a['status'], $a['naam']] <=> [$b['status'], $b['naam']]);

        // Tellingen op elke regel: de app kan een gefilterde lijst niet tellen,
        // en zo kan de kop "Aanwezig (11)" tonen zonder een tweede endpoint.
        $aangemeld = count(array_filter($rows, fn ($r) => $r['status'] === 'aangemeld'));
        $afgemeld  = count($rows) - $aangemeld;

        return response()->json(array_map(fn ($r) => $r + [
            'aantalAangemeld' => (string) $aangemeld,
            'aantalAfgemeld'  => (string) $afgemeld,
            // Ook het totaal, zodat de kop "13 / 15" kan tonen zonder dat de
            // app twee getallen uit strings hoeft op te tellen.
            'aantalTotaal'    => (string) ($aangemeld + $afgemeld),
            'magBeheren'      => $mag,
        ], $rows));
    }

    public function update(Request $request, FootballMatch $match): JsonResponse
    {
        // Deze endpoint had geen enkele rechtencheck: elke ingelogde gebruiker
        // kon aanwezigtijd, coach, fruitheld, rijders en opmerkingen van elke
        // wedstrijd wijzigen. Zelfde check als setVlagger hieronder.
        if (! $request->user()->canManageLineup($match->team_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Je hebt geen rechten om deze wedstrijd te wijzigen.',
            ], 403);
        }

        $validated = $request->validate([
            'arrival_time' => 'nullable|date_format:H:i',
            'coach_id' => 'nullable|uuid|exists:members,id',
            'fruit_hero_id' => 'nullable|uuid|exists:members,id',
            'driver_ids' => 'nullable|array',
            'driver_ids.*' => 'uuid|exists:members,id',
            'notes' => 'nullable|string|max:2000',
        ]);

        $match->update($validated);

        if (isset($validated['driver_ids'])) {
            $match->drivers()->sync($validated['driver_ids']);
        }

        return response()->json([
            'success' => true,
            'data' => new MatchResource($match->fresh(['team', 'coach', 'coaches', 'fruitHero', 'drivers'])),
            'message' => 'Wedstrijd bijgewerkt.',
        ]);
    }

    /**
     * POST /v1/matches/{match}/vlagger?memberId=..
     *
     * Coach kiest de vlagger (grensrechter) uit het team van de wedstrijd. Lege
     * memberId verwijdert de vlagger.
     */
    public function setVlagger(Request $request, FootballMatch $match): JsonResponse
    {
        if (! $request->user()->canManageLineup($match->team_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Je hebt geen rechten om de vlagger te wijzigen.',
            ], 403);
        }

        $memberId = trim((string) $request->input('memberId', ''));

        if ($memberId === '') {
            $match->update(['vlagger_id' => null]);
            return response()->json(['success' => true, 'message' => 'Vlagger verwijderd.']);
        }

        // Het lid moet in het team van de wedstrijd zitten.
        $member = Member::where('id', $memberId)
            ->whereHas('teams', fn ($q) => $q->where('teams.id', $match->team_id))
            ->first();
        if (! $member) {
            return response()->json([
                'success' => false,
                'message' => 'Speler niet gevonden in dit team.',
            ], 422);
        }

        $match->update(['vlagger_id' => $member->id]);

        return response()->json([
            'success' => true,
            'data'    => ['vlaggerId' => $member->id, 'vlaggerName' => $member->name],
            'message' => $member->name . ' is als vlagger ingesteld.',
        ]);
    }

    /**
     * POST /v1/matches/{match}/rijder?memberId=..
     *
     * Zet een speler aan of uit als rijder. Een eigen endpoint en niet het
     * update()-endpoint met driver_ids: dat is een PATCH met een array in de
     * body, en de app kan geen van beide - de hosting blokkeert PATCH en
     * FlutterFlow krijgt geen lijst in een URL. Omzetten per persoon past
     * bovendien beter bij hoe het gaat: je vult de rijders één voor één aan.
     */
    public function toggleRijder(Request $request, FootballMatch $match): JsonResponse
    {
        if (! $request->user()->canManageLineup($match->team_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Je hebt geen rechten om de rijders te wijzigen.',
            ], 403);
        }

        $memberId = trim((string) $request->input('memberId', ''));
        if ($memberId === '') {
            return response()->json([
                'success' => false,
                'message' => 'Geen speler opgegeven.',
            ], 422);
        }

        $member = Member::where('id', $memberId)
            ->whereHas('teams', fn ($q) => $q->where('teams.id', $match->team_id))
            ->first();

        if (! $member) {
            return response()->json([
                'success' => false,
                'message' => 'Speler niet gevonden in dit team.',
            ], 422);
        }

        // toggle() geeft terug wat er is aan- en losgekoppeld; daarmee weten we
        // zonder tweede query welke kant het op ging.
        $resultaat = $match->drivers()->toggle([$member->id]);
        $toegevoegd = ! empty($resultaat['attached']);

        return response()->json([
            'success'  => true,
            'data'     => [
                'memberId' => $member->id,
                'isDriver' => $toegevoegd ? 'true' : 'false',
            ],
            'message'  => $toegevoegd
                ? $member->name . ' rijdt mee.'
                : $member->name . ' rijdt niet meer mee.',
        ]);
    }

    /**
     * POST /v1/matches/{match}/fruithero?memberId=..
     *
     * Coach kiest de fruitheld uit het team van de wedstrijd. Lege memberId
     * verwijdert de fruitheld. Eigen endpoint en niet het update()-endpoint
     * hierboven: dat is een PATCH, en shared hosting blokkeert PATCH regelmatig
     * — zelfde afweging als bij de vlagger en de notitie.
     */
    public function setFruitHero(Request $request, FootballMatch $match): JsonResponse
    {
        if (! $request->user()->canManageLineup($match->team_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Je hebt geen rechten om de fruitheld te wijzigen.',
            ], 403);
        }

        $memberId = trim((string) $request->input('memberId', ''));

        if ($memberId === '') {
            $match->update(['fruit_hero_id' => null]);
            return response()->json(['success' => true, 'message' => 'Fruitheld verwijderd.']);
        }

        // Het lid moet in het team van de wedstrijd zitten.
        $member = Member::where('id', $memberId)
            ->whereHas('teams', fn ($q) => $q->where('teams.id', $match->team_id))
            ->first();
        if (! $member) {
            return response()->json([
                'success' => false,
                'message' => 'Speler niet gevonden in dit team.',
            ], 422);
        }

        $match->update(['fruit_hero_id' => $member->id]);

        return response()->json([
            'success' => true,
            'data'    => ['fruitHeroId' => $member->id, 'fruitHeroName' => $member->name],
            'message' => $member->name . ' is als fruitheld ingesteld.',
        ]);
    }

    /**
     * GET /v1/matches/{match}/afmeldingen
     *
     * Platte lijst met wie zich heeft afgemeld en waarom. Apart endpoint en niet
     * het genestelde veld uit show(): de app kan een structlijst alleen mappen
     * uit een respons die zelf die lijst ís — zelfde reden als bij de
     * doelpuntenlijst.
     */
    public function afmeldingen(Request $request, FootballMatch $match): JsonResponse
    {
        $absences = Absence::query()
            ->where('type', Absence::TYPE_MATCH)
            ->where('match_id', $match->id)
            ->with(['member:id,name', 'user:id,name'])
            ->orderBy('created_at')
            ->get();

        return response()->json(
            $absences->map(fn (Absence $a) => [
                'naam'  => $a->member?->name ?? $a->user?->name ?? '',
                'reden' => (string) ($a->reason ?? ''),
            ])->values()
        );
    }

    /**
     * POST /v1/matches/{match}/notitie
     *
     * Coach of leider zet of wijzigt de opmerking bij een wedstrijd. Een lege
     * waarde wist de notitie. POST en niet PATCH: shared hosting blokkeert PATCH
     * regelmatig — zelfde afweging als bij af-/aanmelden.
     */
    public function setNote(Request $request, FootballMatch $match): JsonResponse
    {
        if (! $request->user()->canManageLineup($match->team_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Je hebt geen rechten om een notitie bij deze wedstrijd te zetten.',
            ], 403);
        }

        $validated = $request->validate([
            'notes' => 'nullable|string|max:2000',
        ]);

        $notes = trim((string) ($validated['notes'] ?? ''));
        $match->update(['notes' => $notes !== '' ? $notes : null]);

        return response()->json([
            'success' => true,
            'data'    => ['notes' => $match->notes ?? ''],
            'message' => $notes !== '' ? 'Notitie opgeslagen.' : 'Notitie verwijderd.',
        ]);
    }
}
