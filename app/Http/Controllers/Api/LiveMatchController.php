<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FootballMatch;
use App\Models\MatchEvent;
use App\Models\Member;
use App\Services\LiveMatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Live wedstrijdverslag: de coach start, legt gebeurtenissen vast en fluit af;
 * teamleden volgen mee.
 *
 * POST in plaats van PATCH/DELETE, zoals overal in deze API — de shared host
 * blokkeert die methodes.
 */
class LiveMatchController extends Controller
{
    public function __construct(private readonly LiveMatchService $live)
    {
    }

    /** POST /v1/matches/{match}/live/start */
    public function start(Request $request, FootballMatch $match): JsonResponse
    {
        if ($denied = $this->denyIfNotCoach($request, $match)) {
            return $denied;
        }

        $this->live->start($match, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Het live verslag is gestart.',
            'data'    => $this->live->state($match->fresh(), true, withViewers: true),
        ]);
    }

    /** POST /v1/matches/{match}/live/event */
    public function event(Request $request, FootballMatch $match): JsonResponse
    {
        if ($denied = $this->denyIfNotCoach($request, $match)) {
            return $denied;
        }
        if (! $match->isLive()) {
            return response()->json([
                'success' => false,
                'message' => 'Er loopt geen live verslag bij deze wedstrijd.',
            ], 422);
        }

        $validated = $request->validate([
            'type'              => 'required|string|in:' . implode(',', MatchEvent::TYPES),
            'side'              => 'nullable|string|in:own,opponent',
            'member_id'         => 'nullable|uuid|exists:members,id',
            'related_member_id' => 'nullable|uuid|exists:members,id',
            'card_type'         => 'nullable|string|in:yellow,red',
            'detail'            => 'nullable|string|in:penalty,own_goal',
            'minute'            => 'nullable|integer|min:0|max:130',
        ]);

        if ($message = $this->validateForType($validated)) {
            return response()->json(['success' => false, 'message' => $message], 422);
        }
        if ($message = $this->assertMembersInTeam($match, $validated)) {
            return response()->json(['success' => false, 'message' => $message], 422);
        }

        $this->live->record($match, $validated, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Vastgelegd.',
            'data'    => $this->live->state($match->fresh(), true, withViewers: true),
        ]);
    }

    /** POST /v1/matches/{match}/live/undo */
    public function undo(Request $request, FootballMatch $match): JsonResponse
    {
        if ($denied = $this->denyIfNotCoach($request, $match)) {
            return $denied;
        }

        $event = $this->live->undoLast($match);

        return response()->json([
            'success' => true,
            'message' => $event ? 'Laatste gebeurtenis teruggedraaid.' : 'Er is niets om terug te draaien.',
            'data'    => $this->live->state($match->fresh(), true, withViewers: true),
        ]);
    }

    /** POST /v1/matches/{match}/live/stop */
    public function stop(Request $request, FootballMatch $match): JsonResponse
    {
        if ($denied = $this->denyIfNotCoach($request, $match)) {
            return $denied;
        }
        if (! $match->isLive()) {
            return response()->json([
                'success' => false,
                'message' => 'Er loopt geen live verslag bij deze wedstrijd.',
            ], 422);
        }

        $this->live->record($match, ['type' => MatchEvent::TYPE_FULLTIME], $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Het live verslag is afgesloten.',
            'data'    => $this->live->state($match->fresh(), true, withViewers: true),
        ]);
    }

    /**
     * POST /v1/matches/{match}/live/delete
     *
     * Verwijdert het hele verslag. Mag ook als de wedstrijd al is afgesloten —
     * juist dán wil een coach een verslag dat per ongeluk is gestart kwijt.
     */
    public function destroy(Request $request, FootballMatch $match): JsonResponse
    {
        if ($denied = $this->denyIfNotCoach($request, $match)) {
            return $denied;
        }

        if (! $match->live_started_at && $match->events()->doesntExist()) {
            return response()->json([
                'success' => false,
                'message' => 'Bij deze wedstrijd staat geen verslag.',
            ], 422);
        }

        $this->live->deleteReport($match);

        return response()->json([
            'success' => true,
            'message' => 'Het verslag is verwijderd.',
            'data'    => $this->live->state($match->fresh(), true, withViewers: true),
        ]);
    }

    /**
     * GET /v1/matches/{match}/events
     *
     * Het bewaarde verslag van een wedstrijd: alle gebeurtenissen op volgorde
     * van spelen, met kant-en-klare omschrijvingen. Bedoeld om achteraf terug te
     * kijken, dus oudste eerst — andersom dan tijdens de wedstrijd, waar het
     * laatste nieuws bovenaan hoort.
     *
     * Aparte, platte lijst naast /live: de app kan een geneste lijst binnen een
     * struct-antwoord niet uitpakken.
     */
    public function events(Request $request, FootballMatch $match): JsonResponse
    {
        $match->loadMissing(['events.member', 'events.relatedMember']);

        return response()->json(
            $match->events
                ->map(fn (MatchEvent $e) => [
                    'id'     => $e->id,
                    'minute' => $e->minute !== null ? $e->minute . "'" : '',
                    'label'  => $e->label(),
                    'type'   => $e->type,
                    'side'   => (string) ($e->side ?? ''),
                    'icon'   => $e->icon(),
                ])
                ->values()
        );
    }

    /**
     * GET /v1/match-reports — alle wedstrijden waarvan een verslag bestaat.
     *
     * Zoeken gebeurt hier en niet in de app. De app heeft alleen de regels die
     * hij toevallig heeft opgehaald, dus filteren daar zou zoeken in een deel van
     * de lijst zijn — en een lijst die elk seizoen aangroeit hoort niet in zijn
     * geheel over de lijn.
     *
     * Alleen elftallen waar de gebruiker bij hoort. Een verslag is geen geheim,
     * maar er is ook geen reden om de hele club te openen voor wie er één team in
     * heeft.
     */
    public function reports(Request $request): JsonResponse
    {
        $teamIds = $request->user()?->accessibleTeams()->pluck('id') ?? collect();
        if ($teamIds->isEmpty()) {
            return response()->json([self::leegVerslag('Je bent nog niet aan een elftal gekoppeld.')]);
        }

        $zoek = trim((string) $request->query('q', ''));

        $matches = FootballMatch::query()
            ->whereIn('team_id', $teamIds)
            ->whereHas('events')
            ->when($zoek !== '', fn ($q) => $q->where(function ($sub) use ($zoek) {
                $sub->where('opponent', 'like', '%' . $zoek . '%')
                    // Qualificeren: team() joint teams, en dat heeft ook een
                    // kolom name.
                    ->orWhereHas('team', fn ($t) => $t->where('teams.name', 'like', '%' . $zoek . '%'));
            }))
            ->with(['team:id,name', 'events'])
            ->orderByDesc('match_datetime')
            // Een club speelt een paar honderd wedstrijden per seizoen. Verder
            // dan dit scrollt niemand, en het houdt het antwoord klein.
            ->limit(200)
            ->get();

        if ($matches->isEmpty()) {
            return response()->json([self::leegVerslag(
                $zoek !== ''
                    ? 'Geen verslag gevonden voor "' . $zoek . '".'
                    : 'Er zijn nog geen wedstrijdverslagen.'
            )]);
        }

        // Alles als string: de app-struct typeert deze velden zo.
        return response()->json($matches->map(function (FootballMatch $m) {
            $stand = $m->liveScore();

            return [
                'matchId'      => (string) $m->id,
                'teamName'     => (string) ($m->team?->name ?? ''),
                'opponent'     => (string) ($m->opponent ?? ''),
                'opponentLogo' => (string) ($m->opponent_logo ?? ''),
                'isHome'       => $m->is_home ? 'true' : 'false',
                'dateLabel'    => $m->match_datetime?->format('d-m-Y') ?? '',
                'score'        => $stand['own'] . ' - ' . $stand['opponent'],
                'resultLabel'  => self::resultaat($stand),
                'eventCount'   => (string) $m->events->count(),
                'melding'      => '',
            ];
        })->values());
    }

    /**
     * Eén lege regel die alleen een melding draagt.
     *
     * Zelfde vorm als bij de poulestand: de app leest de melding van de eerste
     * regel, want een structlijst uit een JSON-pad trekken is in FlutterFlow
     * onnodig gedoe.
     *
     * @return array<string, string>
     */
    private static function leegVerslag(string $melding): array
    {
        return [
            'matchId'      => '',
            'teamName'     => '',
            'opponent'     => '',
            'opponentLogo' => '',
            'isHome'       => 'false',
            'dateLabel'    => '',
            'score'        => '',
            'resultLabel'  => '',
            'eventCount'   => '',
            'melding'      => $melding,
        ];
    }

    /**
     * W, G of V vanuit het eigen elftal. Kleurt de stip in de lijst; drie letters
     * schelen een blik ten opzichte van twee cijfers uitrekenen.
     *
     * @param  array{own:int, opponent:int}  $stand
     */
    private static function resultaat(array $stand): string
    {
        return match (true) {
            $stand['own'] > $stand['opponent'] => 'W',
            $stand['own'] < $stand['opponent'] => 'V',
            default                            => 'G',
        };
    }

    /** GET /v1/matches/{match}/live — toestand voor volgers (pollen). */
    public function show(Request $request, FootballMatch $match): JsonResponse
    {
        $canManage = (bool) $request->user()?->canManageLineup($match->team_id);

        // Dit verzoek komt elke tien seconden binnen zolang iemand de
        // livepagina open heeft. Daarmee is het meteen het teken van leven
        // waaruit de coach afleest hoeveel mensen er meekijken.
        if ($id = $request->user()?->id) {
            $this->live->registerViewer($match, 'u:' . $id, 'app');
        }

        return response()->json(
            $this->live->state($match, $canManage, withViewers: $canManage),
        );
    }

    /**
     * GET /v1/live — loopt er nu een verslag in een van mijn teams?
     * Voedt de "Nu live"-kaart op het dashboard.
     */
    public function mine(Request $request): JsonResponse
    {
        $teamIds = $request->user()?->accessibleTeams()->pluck('id') ?? collect();
        if ($teamIds->isEmpty()) {
            return response()->json([]);
        }

        $matches = FootballMatch::query()
            ->whereIn('team_id', $teamIds)
            ->whereNotNull('live_started_at')
            ->whereNull('live_ended_at')
            ->with(['team', 'events.member', 'events.relatedMember'])
            ->orderBy('live_started_at')
            ->get();

        return response()->json(
            $matches->map(fn (FootballMatch $m) => $this->live->state(
                $m,
                (bool) $request->user()?->canManageLineup($m->team_id),
            ))->values()
        );
    }

    private function denyIfNotCoach(Request $request, FootballMatch $match): ?JsonResponse
    {
        if ($request->user()?->canManageLineup($match->team_id)) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Alleen de coach mag het live verslag beheren.',
        ], 403);
    }

    /** Wat een gebeurtenis nodig heeft, hangt af van het soort. */
    private function validateForType(array $data): ?string
    {
        $type = $data['type'];

        if (in_array($type, [
                MatchEvent::TYPE_GOAL,
                MatchEvent::TYPE_CARD,
                MatchEvent::TYPE_SHOT,
                MatchEvent::TYPE_PENALTY_MISS,
            ], true)
            && empty($data['side'])
        ) {
            return 'Geef aan of het om het eigen team of de tegenstander gaat.';
        }
        // Bij een eigen gemiste strafschop hoort de nemer erbij. Dat is de hele
        // reden dat dit soort bestaat: anders was het genoeg geweest om niets
        // vast te leggen.
        if ($type === MatchEvent::TYPE_PENALTY_MISS
            && ($data['side'] ?? null) === MatchEvent::SIDE_OWN
            && empty($data['member_id'])
        ) {
            return 'Kies de speler die de strafschop nam.';
        }
        if ($type === MatchEvent::TYPE_CARD && empty($data['card_type'])) {
            return 'Geef aan of het een gele of rode kaart is.';
        }
        if ($type === MatchEvent::TYPE_CARD
            && ($data['side'] ?? null) === MatchEvent::SIDE_OWN
            && empty($data['member_id'])
        ) {
            return 'Kies de speler die de kaart kreeg.';
        }
        if ($type === MatchEvent::TYPE_SUBSTITUTION && empty($data['member_id'])) {
            return 'Kies de speler die erin komt.';
        }

        return null;
    }

    /** Een coach mag alleen spelers uit het eigen team aanwijzen. */
    private function assertMembersInTeam(FootballMatch $match, array $data): ?string
    {
        $ids = array_filter([$data['member_id'] ?? null, $data['related_member_id'] ?? null]);
        if ($ids === [] || ! $match->team_id) {
            return null;
        }

        $aantal = Member::query()
            ->whereIn('id', $ids)
            ->whereHas('teams', fn ($q) => $q->whereKey($match->team_id))
            ->count();

        return $aantal === count($ids)
            ? null
            : 'Die speler hoort niet bij dit team.';
    }
}
