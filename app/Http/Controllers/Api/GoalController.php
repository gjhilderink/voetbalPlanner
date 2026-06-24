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
            'scorer_id' => 'required|uuid|exists:members,id',
            'assist_id' => 'nullable|uuid|exists:members,id',
            'minute' => 'nullable|integer|min:1|max:120',
            'is_own_goal' => 'boolean',
            'is_penalty' => 'boolean',
        ]);

        $goal = Goal::create([
            'match_id' => $match->id,
            ...$validated,
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
