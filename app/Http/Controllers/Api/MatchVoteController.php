<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FootballMatch;
use App\Models\MatchVote;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Man of the match: na afloop kiest het elftal anoniem wie de beste was.
 *
 * Anoniem naar buiten toe — de app krijgt per speler alleen het aantal stemmen
 * terug, nooit wie op wie heeft gestemd. Ook de portal toont enkel de uitslag.
 *
 * De aantallen blijven verborgen tot je zelf hebt gestemd. Wie eerst de stand
 * ziet, stemt op de koploper; dan meet je niet meer wie de beste was maar wie
 * het eerst voorstond.
 *
 * Per elftal aan te zetten (teams.motm_enabled). Staat het uit, dan bestaat de
 * stemming niet: geen kandidaten, en stemmen wordt geweigerd. De app verbergt de
 * knop al, maar een verborgen knop is geen afscherming.
 */
class MatchVoteController extends Controller
{
    /**
     * GET /v1/matches/{match}/motm
     *
     * Platte lijst met de kandidaten en hun stemmen. Een array en geen object
     * met een genest lijstje: de app kan een structlijst alleen mappen uit een
     * antwoord dat zelf die lijst ís — zelfde reden als bij de doelpunten- en
     * afmeldingenlijst.
     */
    public function index(Request $request, FootballMatch $match): JsonResponse
    {
        $user = $request->user();
        if (! $user?->accessibleTeams()->contains('id', $match->team_id)) {
            return response()->json(['message' => 'Geen toegang tot deze wedstrijd.'], 403);
        }

        if (! $match->motmAan()) {
            return response()->json([]);
        }

        $eigenMemberId = $user->resolveMember()?->id;
        $kandidaten    = self::kandidaten($match, $eigenMemberId);

        if ($kandidaten->isEmpty()) {
            return response()->json([]);
        }

        $stemmen  = $match->votes()->get();
        $gestemd  = $stemmen->contains('user_id', $user->id);
        $perLid   = $stemmen->groupBy('voted_member_id')->map->count();
        $mijnStem = $stemmen->firstWhere('user_id', $user->id)?->voted_member_id;

        // Alleen als je hebt gestemd is er een koploper te tonen. Bij een
        // gelijke stand zijn dat er meerdere; allemaal markeren is eerlijker dan
        // er willekeurig één uitkiezen.
        $hoogste = $gestemd ? (int) ($perLid->max() ?? 0) : 0;

        $rijen = $kandidaten->map(function (Member $lid) use ($perLid, $gestemd, $mijnStem, $hoogste) {
            $aantal = (int) ($perLid[$lid->id] ?? 0);

            return [
                'memberId'  => (string) $lid->id,
                'name'      => (string) $lid->name,
                'photoUrl'  => $lid->photoUrl(),
                // Leeg zolang je zelf niet gestemd hebt; de app toont dan geen
                // aantal in plaats van een nul die als "nul stemmen" leest.
                'votes'     => $gestemd ? (string) $aantal : '',
                'isMine'    => $mijnStem === $lid->id ? 'true' : 'false',
                'isWinner'  => $gestemd && $hoogste > 0 && $aantal === $hoogste ? 'true' : 'false',
            ];
        });

        // Vóór je stem op naam: elke andere volgorde zegt iets over de stand.
        // Daarna op aantal, want dan is de uitslag juist het onderwerp.
        $rijen = $gestemd
            ? $rijen->sortByDesc(fn (array $r) => (int) $r['votes'])->values()
            : $rijen->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();

        return response()->json($rijen);
    }

    /**
     * POST /v1/matches/{match}/motm?memberId=..
     *
     * Query-param en geen body: FlutterFlow interpoleert [var] alleen in de URL,
     * en validate() leest query-params net zo goed — zelfde afweging als bij de
     * vlagger en de teamsfeer.
     */
    public function store(Request $request, FootballMatch $match): JsonResponse
    {
        $user = $request->user();

        if (! $match->motmAan()) {
            return response()->json([
                'success' => false,
                'message' => 'Voor dit elftal staat de man-of-the-match-stemming uit.',
            ], 403);
        }

        // Pas stemmen als de wedstrijd echt gespeeld is. Het verslag is daar het
        // bewijs van: zonder verslag weet niemand waar hij over stemt.
        if (! $match->events()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Er is nog geen wedstrijdverslag; stemmen kan zodra dat er is.',
            ], 403);
        }

        if (! $user?->accessibleTeams()->contains('id', $match->team_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Geen toegang tot deze wedstrijd.',
            ], 403);
        }

        $memberId = trim((string) $request->input('memberId', ''));
        if ($memberId === '') {
            return response()->json([
                'success' => false,
                'message' => 'Geen speler opgegeven.',
            ], 422);
        }

        $eigenMemberId = $user->resolveMember()?->id;

        if ($eigenMemberId !== null && $memberId === $eigenMemberId) {
            return response()->json([
                'success' => false,
                'message' => 'Je kunt niet op jezelf stemmen.',
            ], 422);
        }

        $lid = self::kandidaten($match, $eigenMemberId)->firstWhere('id', $memberId);
        if (! $lid) {
            return response()->json([
                'success' => false,
                'message' => 'Deze speler staat niet bij deze wedstrijd.',
            ], 422);
        }

        if ($match->votes()->where('user_id', $user->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Je hebt al gestemd voor deze wedstrijd.',
            ], 422);
        }

        MatchVote::create([
            'club_id'         => $match->team?->club_id ?? $user->club_id,
            'match_id'        => $match->id,
            'user_id'         => $user->id,
            'member_id'       => $eigenMemberId,
            'voted_member_id' => $lid->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Je stem op ' . $lid->name . ' is geteld.',
        ]);
    }

    /**
     * Op wie kun je stemmen: de spelers die deze wedstrijd meededen.
     *
     * De opstelling is de beste bron — basis én wissels, over alle perioden.
     * Is die er niet, dan valt het terug op de hele selectie; een elftal dat
     * geen opstelling invult kan anders helemaal niet stemmen.
     *
     * Zonder jezelf: op jezelf stemmen is de makkelijkste manier om de uitslag
     * te sturen.
     *
     * @return Collection<int, Member>
     */
    public static function kandidaten(FootballMatch $match, ?string $eigenMemberId): Collection
    {
        $match->loadMissing(['lineup.players.member', 'team']);

        $leden = $match->lineup?->players
            ->map(fn ($speler) => $speler->member)
            ->filter()
            ->unique('id')
            ->values() ?? collect();

        if ($leden->isEmpty()) {
            $leden = $match->team?->playingMembers()->orderBy('members.name')->get() ?? collect();
        }

        return $leden
            ->when($eigenMemberId !== null, fn (Collection $c) => $c->where('id', '!=', $eigenMemberId))
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }
}
