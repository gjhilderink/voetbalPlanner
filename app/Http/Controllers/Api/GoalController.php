<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GoalResource;
use App\Models\Goal;
use App\Models\FootballMatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoalController extends Controller
{
    public function index(FootballMatch $match): JsonResponse
    {
        $goals = $match->goals()->with(['scorer', 'assist'])->orderBy('minute')->get();

        return response()->json(
            $goals->map(fn($g) => (new GoalResource($g))->resolve())->values()
        );
    }

    public function store(Request $request, FootballMatch $match): JsonResponse
    {
        if (! $request->user()->canManageLineup($match->team_id)) {
            return response()->json(['success' => false, 'message' => 'Alleen de coach mag de score beheren.'], 403);
        }

        $validated = $request->validate([
            'scorer_id'   => 'nullable|uuid|exists:members,id',
            'scorer_name' => 'nullable|string|max:255',
            'assist_id'   => 'nullable|uuid|exists:members,id',
            'minute'      => 'nullable|integer|min:1|max:120',
            'is_own_goal' => 'boolean',
            'is_penalty'  => 'boolean',
        ]);

        // Maker: via id (bv. dropdown) óf via naam binnen het team van de wedstrijd.
        $scorerId = $validated['scorer_id'] ?? null;
        if (!$scorerId && !empty($validated['scorer_name'])) {
            $member = \App\Models\Member::query()
                ->when($match->team_id, fn ($q) => $q->whereHas('teams', fn ($t) => $t->whereKey($match->team_id)))
                ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($validated['scorer_name']))])
                ->first();
            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => "Speler '{$validated['scorer_name']}' niet gevonden in dit team.",
                ], 422);
            }
            $scorerId = $member->id;
        }
        if (!$scorerId) {
            return response()->json(['success' => false, 'message' => 'Geef een speler (naam) op.'], 422);
        }

        $goal = Goal::create([
            'match_id'    => $match->id,
            'scorer_id'   => $scorerId,
            'assist_id'   => $validated['assist_id'] ?? null,
            'minute'      => $validated['minute'] ?? null,
            'is_own_goal' => $validated['is_own_goal'] ?? false,
            'is_penalty'  => $validated['is_penalty'] ?? false,
        ]);

        return response()->json([
            'success' => true,
            'data' => new GoalResource($goal->load(['scorer', 'assist'])),
            'goals_summary' => $match->goalsSummary(),
            'message' => 'Doelpunt geregistreerd.',
        ], 201);
    }

    public function destroy(Request $request, FootballMatch $match, Goal $goal): JsonResponse
    {
        if (! $request->user()->canManageLineup($match->team_id)) {
            return response()->json(['success' => false, 'message' => 'Alleen de coach mag de score beheren.'], 403);
        }

        $goal->delete();

        return response()->json([
            'success' => true,
            'data' => null,
            'goals_summary' => $match->goalsSummary(),
            'message' => 'Doelpunt verwijderd.',
        ]);
    }

    /**
     * POST /v1/matches/{match}/goals/delete-last
     * Verwijdert het laatst toegevoegde doelpunt (coach-only). POST i.p.v. DELETE
     * zodat shared hosts 'm niet blokkeren.
     */
    public function destroyLast(Request $request, FootballMatch $match): JsonResponse
    {
        if (! $request->user()->canManageLineup($match->team_id)) {
            return response()->json(['success' => false, 'message' => 'Alleen de coach mag de score beheren.'], 403);
        }

        $last = $match->goals()->latest()->first();
        if ($last) {
            $last->delete();
        }

        return response()->json([
            'success' => true,
            'goals_summary' => $match->goalsSummary(),
            'message' => $last ? 'Laatste doelpunt verwijderd.' : 'Er zijn geen doelpunten.',
        ]);
    }
}
