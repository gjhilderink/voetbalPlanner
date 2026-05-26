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

        return response()->json([
            'success' => true,
            'data' => GoalResource::collection($goals),
            'message' => '',
        ]);
    }

    public function store(Request $request, FootballMatch $match): JsonResponse
    {
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
            'message' => 'Doelpunt geregistreerd.',
        ], 201);
    }

    public function destroy(FootballMatch $match, Goal $goal): JsonResponse
    {
        $goal->delete();

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Doelpunt verwijderd.',
        ]);
    }
}
