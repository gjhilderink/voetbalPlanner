<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LineupPlayerResource;
use App\Models\Absence;
use App\Models\Lineup;
use App\Models\LineupPlayer;
use App\Models\FootballMatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LineupController extends Controller
{
    public function show(Request $request, FootballMatch $match): JsonResponse
    {
        $lineup = $match->lineup()->with('players.member')->first();

        if (!$lineup) {
            return response()->json([]);
        }

        // Dezelfde regel als bij het opstellingsbord: zolang de coach de
        // opstelling niet heeft vrijgegeven is hij van hem alleen. Deze weg
        // stond nog open - de livepagina las hem hier op en liet elk teamlid
        // meekijken met een indeling waar de coach nog aan zat te schuiven.
        $magBeheren = $request->user()?->canManageLineup($match->team_id) ?? false;

        if (! $magBeheren && ! $lineup->isPublished()) {
            return response()->json([]);
        }

        return response()->json(
            $lineup->players->map(fn($p) => (new LineupPlayerResource($p))->resolve())->values()
        );
    }

    /**
     * GET /v1/matches/{match}/lineup/board
     *
     * Alles wat het opstellingsscherm nodig heeft in één keer: de instellingen,
     * de spelers op het veld met hun plek, de bank, en de selectie waaruit de
     * coach kan kiezen.
     *
     * Zolang de opstelling niet is vrijgegeven zien spelers hem niet. De coach
     * schuift liever rustig dan dat er halverwege tien ouders bellen over een
     * plek die een kwartier later toch weer verandert.
     */
    public function board(Request $request, FootballMatch $match): JsonResponse
    {
        $magBeheren = $request->user()?->canManageLineup($match->team_id) ?? false;
        $lineup     = $match->lineup()->with('players.member')->first();
        $vrijgegeven = $lineup?->isPublished() ?? false;

        if (! $magBeheren && ! $vrijgegeven) {
            return response()->json([
                'magBeheren'    => 'false',
                'isVrijgegeven' => 'false',
                'formation'     => '',
                'playersOnField' => '',
                'matchFormat'   => '',
                'notes'         => '',
                'periods'       => '',
                'veld'          => [],
                'bank'          => [],
                'selectie'      => [],
                'wissels'       => [],
                'melding'       => 'De opstelling is nog niet bekend.',
            ]);
        }

        // Wie zich heeft afgemeld staat wél in de selectie, maar gemarkeerd. Hem
        // eruit filteren zou de coach laten raden waarom iemand mist, en een
        // speler die zich ná het opstellen afmeldt moet juist opvallen.
        $afgemeld = Absence::query()
            ->where('type', Absence::TYPE_MATCH)
            ->where('match_id', $match->id)
            ->whereNotNull('member_id')
            ->pluck('member_id')
            ->all();

        // Uitgenodigde gastspelers horen meteen in de selectie, ook als ze nog
        // niet gereageerd hebben. Je nodigt iemand uit omdát je hem wilt
        // opstellen; wachten op een bevestiging die misschien pas zaterdagochtend
        // komt betekent dat je je opstelling niet kunt maken.
        $gasten = $this->gastLeden($match);
        $gastIds = $gasten->pluck('id')->all();

        $rij = fn (LineupPlayer $p) => [
            'memberId'   => (string) $p->member_id,
            'naam'       => $p->member?->name ?? '',
            // Het nummer van de speler wint van wat er ooit bij deze opstelling
            // is opgeslagen: pas je het bij Leden aan, dan klopt het overal.
            'nummer'     => (string) ($p->member?->shirt_number ?? $p->shirt_number ?? ''),
            // posX/posY en niet x/y: FlutterFlow weigert die veldnamen in een
            // struct omdat ze met een sleutelwoord botsen.
            'posX'       => (string) ($p->slot_x ?? ''),
            'posY'       => (string) ($p->slot_y ?? ''),
            'isAfgemeld' => in_array($p->member_id, $afgemeld, true) ? 'true' : 'false',
            'isGast'     => in_array($p->member_id, $gastIds, true) ? 'true' : 'false',
            // De periode hoort er ook bij. Zonder dit veld leest de app elke
            // speler als periode 1: een opstelling met twee helften kwam dan
            // terug als dubbele pionnen in de eerste en een lege tweede.
            'period'     => (string) ($p->period ?? 1),
        ];

        $spelers = $lineup?->players ?? collect();

        return response()->json([
            'magBeheren'     => $magBeheren ? 'true' : 'false',
            // Het elftal erbij, zodat de app vanaf hier naar de
            // standaardopstelling kan. De coach kan een wedstrijd van een ander
            // elftal openen dan het elftal dat hij op dat moment volgt.
            'teamId'         => (string) $match->team_id,
            'isVrijgegeven'  => $vrijgegeven ? 'true' : 'false',
            'formation'      => (string) ($lineup?->formation ?? ''),
            'playersOnField' => (string) ($lineup?->players_on_field ?? 11),
            'matchFormat'    => (string) ($lineup?->match_format ?? ''),
            // Notitie van de coach: alleen voor wie de opstelling beheert. Hem
            // meesturen en in de app verbergen is geen afscherming - dan staat
            // hij in het antwoord dat elke speler kan opvragen.
            'notes'          => $magBeheren ? (string) ($lineup?->tactical_notes ?? '') : '',
            'periods'        => (string) ($lineup?->periods ?? 2),
            'veld'           => $spelers->where('is_substitute', false)
                ->sortBy('sort_order')->values()->map($rij)->all(),
            'bank'           => $spelers->where('is_substitute', true)
                ->sortBy('sort_order')->values()->map($rij)->all(),
            'selectie'       => $this->selectie($match, $afgemeld, $gasten),
            // Afgeleid uit de perioden, niet opgeslagen — zie Lineup.
            'wissels'        => $lineup?->derivedSubstitutions() ?? [],
            'melding'        => '',
        ]);
    }

    /**
     * De hele teamselectie met af-/aanmeldstatus, zodat de coach ziet wie hij
     * kan opstellen zonder een tweede scherm.
     *
     * @param  array<string>  $afgemeld
     * @param  \Illuminate\Support\Collection<int, \App\Models\Member>  $gasten
     * @return array<int, array<string, string>>
     */
    private function selectie(FootballMatch $match, array $afgemeld, $gasten): array
    {
        // Kolommen kwalificeren: member_team heeft óók een is_active, en zonder
        // tabelnaam maakt MySQL daar "Column 'is_active' is ambiguous" van — een
        // 500 die er in de app uitzag als "kon de opstelling niet ophalen".
        $leden = $match->team?->playingMembers()
            ->where('members.is_active', true)
            ->orderBy('members.name')
            ->get() ?? collect();

        $regel = fn ($m, bool $gast) => [
            'memberId'   => (string) $m->id,
            'naam'       => $m->name,
            'nummer'     => (string) ($m->shirt_number ?? ''),
            'posX'       => '',
            'posY'       => '',
            'isAfgemeld' => in_array($m->id, $afgemeld, true) ? 'true' : 'false',
            'isGast'     => $gast ? 'true' : 'false',
        ];

        $eigenIds = $leden->pluck('id')->all();

        // Gasten onderaan en niet door de alfabetische lijst heen: het is een
        // aparte groep, en de coach wil in één oogopslag zien wie hij erbij
        // heeft gehaald. Wie al gewoon in het elftal zit valt af - dat kan bij
        // een speler die van team wisselt.
        $extra = $gasten
            ->reject(fn ($m) => in_array($m->id, $eigenIds, true))
            ->sortBy('name')
            ->map(fn ($m) => $regel($m, true))
            ->values()
            ->all();

        return array_merge(
            $leden->map(fn ($m) => $regel($m, false))->values()->all(),
            $extra,
        );
    }

    /**
     * De leden die als gast voor deze wedstrijd zijn uitgenodigd.
     *
     * Alleen de status van de uitnodiging telt, niet of de gast hem gezien of
     * geaccepteerd heeft: voor de coach die de opstelling maakt is "ik heb hem
     * gevraagd" het enige wat op dat moment vaststaat.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Member>
     */
    private function gastLeden(FootballMatch $match)
    {
        return \App\Models\MatchGuestInvitation::query()
            ->where('match_id', $match->id)
            ->active()
            ->with('member:id,name,shirt_number')
            ->get()
            ->map(fn ($g) => $g->member)
            ->filter()
            ->unique('id')
            ->values();
    }

    /**
     * POST /v1/matches/{match}/lineup/board
     *
     * Slaat het hele bord in één keer op. Eén call en niet per speler: bij het
     * slepen verandert er van alles tegelijk, en een half opgeslagen opstelling
     * is erger dan geen.
     */
    public function saveBoard(Request $request, FootballMatch $match): JsonResponse
    {
        if (! $request->user()->canManageLineup($match->team_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Alleen de coach mag de opstelling beheren.',
            ], 403);
        }

        $validated = $request->validate([
            'formation'          => 'nullable|string|max:20',
            'players_on_field'   => 'nullable|integer|min:1|max:11',
            'match_format'       => 'nullable|string|max:40',
            'notes'              => 'nullable|string|max:2000',
            'players'            => 'present|array',
            'players.*.member_id'     => 'required|uuid|exists:members,id',
            'players.*.shirt_number'  => 'nullable|integer|min:1|max:99',
            'players.*.is_substitute' => 'nullable|boolean',
            'players.*.slot_x'        => 'nullable|numeric|between:0,1',
            'players.*.slot_y'        => 'nullable|numeric|between:0,1',
            'periods'                 => 'nullable|integer|in:2,4',
            'players.*.period'        => 'nullable|integer|min:1|max:4',
        ]);

        // De uitgenodigde gasten horen er net zo goed bij. Zonder dit vielen ze
        // hieronder stilzwijgend weg: de coach zette een gast op het veld, sloeg
        // op, kreeg "opgeslagen" te zien en zag hem bij het herladen weer
        // verdwenen zijn.
        $teamLeden = array_merge(
            $match->team?->members()->pluck('members.id')->all() ?? [],
            $this->gastLeden($match)->pluck('id')->all(),
        );

        DB::transaction(function () use ($match, $validated, $teamLeden) {
            $lineup = Lineup::updateOrCreate(
                ['match_id' => $match->id],
                [
                    'formation'           => $validated['formation'] ?? null,
                    'players_on_field'    => $validated['players_on_field'] ?? 11,
                    'match_format'        => $validated['match_format'] ?? null,
                    'tactical_notes'      => $validated['notes'] ?? null,
                    'periods'             => $validated['periods'] ?? 2,
                ],
            );

            $lineup->players()->delete();

            $volgorde = 0;
            foreach ($validated['players'] as $speler) {
                // Beheerrechten op dit elftal geven geen toegang tot de rest van
                // de club; een lid van buiten het team - en niet als gast voor
                // deze wedstrijd uitgenodigd - hoort hier niet te staan.
                if ($teamLeden && ! in_array($speler['member_id'], $teamLeden, true)) {
                    continue;
                }

                LineupPlayer::create([
                    'lineup_id'     => $lineup->id,
                    // Elke periode heeft zijn eigen opstelling; dezelfde speler
                    // komt dus meerdere keren voor, één keer per periode.
                    'period'        => $speler['period'] ?? 1,
                    'member_id'     => $speler['member_id'],
                    // Het veld werkt met plekken, niet met categorieën; 'player'
                    // houdt de oude kolom gevuld zonder iets te suggereren.
                    'position'      => 'player',
                    'shirt_number'  => $speler['shirt_number'] ?? null,
                    'is_substitute' => (bool) ($speler['is_substitute'] ?? false),
                    'slot_x'        => $speler['slot_x'] ?? null,
                    'slot_y'        => $speler['slot_y'] ?? null,
                    'sort_order'    => $volgorde++,
                ]);
            }

            // Het wisselschema wordt niet opgeslagen maar afgeleid uit de
            // perioden; zie Lineup::derivedSubstitutions().
        });

        return response()->json([
            'success' => true,
            'message' => 'De opstelling is opgeslagen.',
        ]);
    }

    /**
     * POST /v1/matches/{match}/lineup/publish   body: { published: true|false }
     *
     * Vrijgeven of weer verbergen voor de spelers.
     */
    public function publish(Request $request, FootballMatch $match): JsonResponse
    {
        if (! $request->user()->canManageLineup($match->team_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Alleen de coach mag de opstelling vrijgeven.',
            ], 403);
        }

        $lineup = $match->lineup()->first();
        if (! $lineup) {
            return response()->json([
                'success' => false,
                'message' => 'Er is nog geen opstelling om vrij te geven.',
            ], 422);
        }

        // Standaard vrijgeven; 'published=false' verbergt hem weer.
        $vrij = $request->boolean('published', true);
        $lineup->forceFill(['published_at' => $vrij ? now() : null])->save();

        return response()->json([
            'success' => true,
            'message' => $vrij
                ? 'De opstelling staat nu bij de spelers.'
                : 'De opstelling is weer verborgen.',
        ]);
    }

    public function store(Request $request, FootballMatch $match): JsonResponse
    {
        if (! $request->user()->canManageLineup($match->team_id)) {
            return response()->json(['success' => false, 'message' => 'Alleen de coach mag de opstelling beheren.'], 403);
        }

        $validated = $request->validate([
            'formation' => 'nullable|string|max:20',
            'tactical_notes' => 'nullable|string|max:2000',
            'players' => 'required|array|min:1',
            'players.*.member_id' => 'required|uuid|exists:members,id',
            'players.*.position' => 'required|in:keeper,defender,midfielder,forward',
            'players.*.shirt_number' => 'nullable|integer|min:1|max:99',
            'players.*.is_substitute' => 'boolean',
            'players.*.substituted_at_minute' => 'nullable|integer|min:1|max:120',
        ]);

        $lineup = Lineup::updateOrCreate(
            ['match_id' => $match->id],
            [
                'formation' => $validated['formation'] ?? null,
                'tactical_notes' => $validated['tactical_notes'] ?? null,
            ]
        );

        $lineup->players()->delete();

        foreach ($validated['players'] as $playerData) {
            LineupPlayer::create([
                'lineup_id' => $lineup->id,
                'member_id' => $playerData['member_id'],
                'position' => $playerData['position'],
                'shirt_number' => $playerData['shirt_number'] ?? null,
                'is_substitute' => $playerData['is_substitute'] ?? false,
                'substituted_at_minute' => $playerData['substituted_at_minute'] ?? null,
            ]);
        }

        $saved = $lineup->load('players.member');
        return response()->json(
            $saved->players->map(fn($p) => (new LineupPlayerResource($p))->resolve())->values(),
            201
        );
    }

    /**
     * POST /v1/matches/{match}/lineup/player
     * Voegt één speler toe aan de opstelling (coach-only). Speler via naam binnen
     * het team. Vervangt een bestaande rij voor hetzelfde lid (idempotent).
     */
    public function addPlayer(Request $request, FootballMatch $match): JsonResponse
    {
        if (! $request->user()->canManageLineup($match->team_id)) {
            return response()->json(['success' => false, 'message' => 'Alleen de coach mag de opstelling beheren.'], 403);
        }

        // position is niet meer verplicht: de app laat de coach vlak voor de
        // aftrap alleen basis of bank kiezen, zonder per speler een positie aan
        // te tikken. De kolom is NOT NULL, dus een weggelaten positie wordt
        // 'player' — geen positie opgegeven.
        $validated = $request->validate([
            'player_name'   => 'nullable|string|max:255',
            'member_id'     => 'nullable|uuid|exists:members,id',
            'position'      => 'nullable|in:keeper,defender,midfielder,forward,player',
            'shirt_number'  => 'nullable|integer|min:1|max:99',
            'is_substitute' => 'nullable',
        ]);

        $memberId = $validated['member_id'] ?? $match->resolveTeamMemberByName($validated['player_name'] ?? '')?->id;
        if (! $memberId) {
            return response()->json([
                'success' => false,
                'message' => "Speler '{$request->input('player_name')}' niet gevonden in dit team.",
            ], 422);
        }

        $lineup = Lineup::firstOrCreate(['match_id' => $match->id]);
        $lineup->players()->where('member_id', $memberId)->delete();
        LineupPlayer::create([
            'lineup_id'     => $lineup->id,
            'member_id'     => $memberId,
            'position'      => $validated['position'] ?? 'player',
            'shirt_number'  => $validated['shirt_number'] ?? null,
            'is_substitute' => $request->boolean('is_substitute'),
        ]);

        return $this->playersResponse($match);
    }

    /**
     * POST /v1/matches/{match}/lineup/player/remove   body: { player_id }
     * Verwijdert één speler uit de opstelling (coach-only).
     */
    public function removePlayer(Request $request, FootballMatch $match): JsonResponse
    {
        if (! $request->user()->canManageLineup($match->team_id)) {
            return response()->json(['success' => false, 'message' => 'Alleen de coach mag de opstelling beheren.'], 403);
        }

        // lineup_players heeft een oplopend getal als sleutel, geen uuid zoals de
        // rest van dit project. Op uuid valideren wees elk geldig id af.
        $validated = $request->validate(['player_id' => 'required|integer']);
        LineupPlayer::query()
            ->where('id', $validated['player_id'])
            ->whereHas('lineup', fn ($q) => $q->where('match_id', $match->id))
            ->delete();

        return $this->playersResponse($match);
    }

    private function playersResponse(FootballMatch $match): JsonResponse
    {
        $lineup = $match->lineup()->with('players.member')->first();
        $players = $lineup ? $lineup->players : collect();

        return response()->json(
            $players->map(fn ($p) => (new LineupPlayerResource($p))->resolve())->values()
        );
    }
}
