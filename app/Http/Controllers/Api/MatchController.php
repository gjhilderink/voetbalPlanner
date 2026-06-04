<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MatchResource;
use App\Models\FootballMatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MatchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $matches = FootballMatch::query()
            ->with(['team', 'coach', 'fruitHero', 'drivers'])
            ->when($request->has('is_home'), fn($q) => $q->where('is_home', $request->boolean('is_home')))
            ->when($request->boolean('has_drivers'), fn($q) => $q->has('drivers'))
            ->when($request->team_id, fn($q, $id) => $q->where('team_id', $id))
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

    public function show(FootballMatch $match): JsonResponse
    {
        $match->load(['team', 'coach', 'fruitHero', 'drivers', 'lineup.players.member', 'goals.scorer', 'goals.assist']);

        return response()->json((new MatchResource($match))->resolve());
    }

    public function update(Request $request, FootballMatch $match): JsonResponse
    {
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
            'data' => new MatchResource($match->fresh(['team', 'coach', 'fruitHero', 'drivers'])),
            'message' => 'Wedstrijd bijgewerkt.',
        ]);
    }
}
