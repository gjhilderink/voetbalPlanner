<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\TeamMood;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Teamsfeer: teamleden geven per week aan hoe de sfeer is, het dashboard toont
 * het gemiddelde. Anoniem naar buiten toe — de app krijgt alleen het gemiddelde,
 * het aantal reacties en de eigen stem terug, nooit wie wat gestemd heeft.
 */
class TeamMoodController extends Controller
{
    /** GET /v1/teams/{team}/mood */
    public function show(Request $request, Team $team): JsonResponse
    {
        $user = $request->user();
        if (! $user?->accessibleTeams()->contains('id', $team->id)) {
            return response()->json(['message' => 'Geen toegang tot dit team.'], 403);
        }

        return response()->json(self::summary($team, $request));
    }

    /** POST /v1/teams/{team}/mood { score: 1..4 } */
    public function store(Request $request, Team $team): JsonResponse
    {
        $user = $request->user();
        if (! $user?->accessibleTeams()->contains('id', $team->id)) {
            return response()->json(['message' => 'Geen toegang tot dit team.'], 403);
        }

        $validated = $request->validate([
            'score' => 'required|integer|min:1|max:4',
        ]);

        $week = TeamMood::weekKey(Carbon::now());

        TeamMood::updateOrCreate(
            ['team_id' => $team->id, 'user_id' => $user->id, 'week' => $week],
            [
                'club_id'   => $team->club_id ?? $user->club_id,
                'member_id' => $user->resolveMember()?->id,
                'score'     => (int) $validated['score'],
            ],
        );

        return response()->json(self::summary($team, $request));
    }

    /**
     * Samenvatting van deze week. Alle velden als string: de app-struct
     * typeert ze zo.
     */
    private static function summary(Team $team, Request $request): array
    {
        $week  = TeamMood::weekKey(Carbon::now());
        $votes = TeamMood::query()
            ->where('team_id', $team->id)
            ->where('week', $week)
            ->get();

        $count   = $votes->count();
        $average = $count > 0 ? $votes->avg('score') : 0.0;
        // Afronden naar de dichtstbijzijnde smiley; zonder stemmen geen smiley.
        $rounded = $count > 0 ? (int) round($average) : 0;

        $mine = $votes->firstWhere('user_id', $request->user()?->id);

        return [
            'week'         => $week,
            'count'        => (string) $count,
            'average'      => $count > 0 ? number_format($average, 1, '.', '') : '',
            'score'        => (string) $rounded,
            'label'        => TeamMood::LABELS[$rounded] ?? 'Nog geen reacties',
            'myScore'      => (string) ($mine?->score ?? 0),
            'hasVoted'     => $mine ? 'true' : 'false',
        ];
    }
}
