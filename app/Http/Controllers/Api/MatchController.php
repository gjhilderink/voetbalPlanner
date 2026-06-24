<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MatchResource;
use App\Models\Absence;
use App\Models\FootballMatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MatchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $matches = FootballMatch::query()
            ->with(['team', 'coach', 'coaches', 'fruitHero', 'drivers'])
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

    public function show(Request $request, FootballMatch $match): JsonResponse
    {
        $match->load(['team', 'coach', 'coaches', 'fruitHero', 'drivers', 'lineup.players.member', 'goals.scorer', 'goals.assist']);

        $data = (new MatchResource($match))->resolve();

        // Af-/aanmeld-status: standaard is iedereen aangemeld; afmelden = een rij in absences.
        $absences = Absence::query()
            ->where('type', Absence::TYPE_MATCH)
            ->where('match_id', $match->id)
            ->with(['member:id,name', 'user:id,name'])
            ->get();
        $myMemberId = $request->user()?->resolveMember()?->id;
        $myUserId   = $request->user()?->id;

        $data['mijn_status'] = $absences->first(fn ($a) =>
            ($myMemberId && $a->member_id === $myMemberId) ||
            ($myUserId && $a->user_id === $myUserId)
        ) ? 'afgemeld' : 'aangemeld';
        $data['afmeldingen'] = $absences->map(fn ($a) => [
            'naam'  => $a->member?->name ?? $a->user?->name ?? '',
            'reden' => $a->reason,
        ])->values();

        return response()->json($data);
    }

    /**
     * POST /v1/matches/{match}/afmelden   body: { reason }
     */
    public function afmelden(Request $request, FootballMatch $match): JsonResponse
    {
        $user   = $request->user();
        $member = $user?->resolveMember();

        $validated = $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        $matchAttrs = [
            'type'     => Absence::TYPE_MATCH,
            'match_id' => $match->id,
        ];
        $matchAttrs += $member ? ['member_id' => $member->id] : ['user_id' => $user->id];

        Absence::updateOrCreate($matchAttrs, [
            'club_id' => $user->club_id,
            'reason'  => $validated['reason'],
        ]);

        return response()->json(['success' => true, 'message' => 'Je bent afgemeld voor deze wedstrijd.']);
    }

    /**
     * DELETE /v1/matches/{match}/afmelden   (= weer aanmelden)
     */
    public function aanmelden(Request $request, FootballMatch $match): JsonResponse
    {
        $user   = $request->user();
        $member = $user?->resolveMember();

        $q = Absence::query()
            ->where('type', Absence::TYPE_MATCH)
            ->where('match_id', $match->id);
        $member ? $q->where('member_id', $member->id) : $q->where('user_id', $user->id);
        $q->delete();

        return response()->json(['success' => true, 'message' => 'Je bent weer aangemeld voor deze wedstrijd.']);
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
            'data' => new MatchResource($match->fresh(['team', 'coach', 'coaches', 'fruitHero', 'drivers'])),
            'message' => 'Wedstrijd bijgewerkt.',
        ]);
    }
}
