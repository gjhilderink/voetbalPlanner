<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LineupPlayerResource;
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
            return response()->json([]);
        }

        return response()->json(
            $lineup->players->map(fn($p) => (new LineupPlayerResource($p))->resolve())->values()
        );
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
