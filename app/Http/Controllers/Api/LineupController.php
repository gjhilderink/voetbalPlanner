<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LineupResource;
use App\Models\Lineup;
use App\Models\LineupPlayer;
use App\Models\FootballMatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LineupController extends Controller
{
    public function show(FootballMatch $match): JsonResponse
    {
        $lineup = $match->lineup()->with('players.member')->first();

        if (!$lineup) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Geen opstelling gevonden.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new LineupResource($lineup),
            'message' => '',
        ]);
    }

    public function store(Request $request, FootballMatch $match): JsonResponse
    {
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

        return response()->json([
            'success' => true,
            'data' => new LineupResource($lineup->load('players.member')),
            'message' => 'Opstelling opgeslagen.',
        ], 201);
    }
}
