<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SwapRequestResource;
use App\Models\BarDuty;
use App\Models\FootballMatch;
use App\Models\Member;
use App\Models\SwapRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SwapRequestController extends Controller
{
    /** GET /swap-requests/incoming — pending requests where I am the requestee. */
    public function incoming(Request $request): JsonResponse
    {
        $member = $request->user()->resolveMember();
        if (!$member) {
            return response()->json(['success' => true, 'data' => [], 'message' => '']);
        }

        $requests = SwapRequest::with(['requester', 'requestee'])
            ->where('requestee_id', $member->id)
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(
            $requests->map(fn($r) => (new SwapRequestResource($r))->resolve())
        );
    }

    /** POST /swap-requests — request a swap. */
    public function store(Request $request): JsonResponse
    {
        $member = $request->user()->resolveMember();
        if (!$member) {
            return response()->json([
                'success' => false, 'data' => null,
                'message' => 'Geen lid-profiel gevonden.',
            ], 422);
        }

        $validated = $request->validate([
            'type'         => 'required|in:bardienst,fruitheld,rijden',
            'target_id'    => 'required|uuid',
            'requestee_id' => 'required|uuid|exists:members,id',
            'message'      => 'nullable|string|max:500',
        ]);

        if ($validated['requestee_id'] === $member->id) {
            return response()->json([
                'success' => false, 'data' => null,
                'message' => 'Je kunt jezelf niet vragen.',
            ], 422);
        }

        $this->authorizeSwapRequest($validated['type'], $validated['target_id'], $member);

        $existing = SwapRequest::where('type', $validated['type'])
            ->where('target_id', $validated['target_id'])
            ->where('requester_id', $member->id)
            ->where('requestee_id', $validated['requestee_id'])
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false, 'data' => null,
                'message' => 'Er staat al een open aanvraag voor dit lid.',
            ], 422);
        }

        $swapRequest = SwapRequest::create([
            'type'         => $validated['type'],
            'target_id'    => $validated['target_id'],
            'requester_id' => $member->id,
            'requestee_id' => $validated['requestee_id'],
            'status'       => 'pending',
            'message'      => $validated['message'] ?? null,
        ]);

        $swapRequest->load(['requester', 'requestee']);

        return response()->json([
            'success' => true,
            'data'    => new SwapRequestResource($swapRequest),
            'message' => 'Wissel aanvraag verstuurd.',
        ], 201);
    }

    /** PATCH /swap-requests/{id}/accept */
    public function accept(Request $request, SwapRequest $swapRequest): JsonResponse
    {
        $member = $request->user()->resolveMember();
        if (!$member || $member->id !== $swapRequest->requestee_id) {
            return response()->json([
                'success' => false, 'data' => null,
                'message' => 'Niet gemachtigd.',
            ], 403);
        }

        if (!$swapRequest->isPending()) {
            return response()->json([
                'success' => false, 'data' => null,
                'message' => 'Aanvraag is al verwerkt.',
            ], 422);
        }

        $this->performSwap($swapRequest);

        $swapRequest->update(['status' => 'accepted']);

        return response()->json([
            'success' => true,
            'data'    => null,
            'message' => 'Wissel bevestigd.',
        ]);
    }

    /** PATCH /swap-requests/{id}/decline */
    public function decline(Request $request, SwapRequest $swapRequest): JsonResponse
    {
        $member = $request->user()->resolveMember();
        if (!$member || $member->id !== $swapRequest->requestee_id) {
            return response()->json([
                'success' => false, 'data' => null,
                'message' => 'Niet gemachtigd.',
            ], 403);
        }

        if (!$swapRequest->isPending()) {
            return response()->json([
                'success' => false, 'data' => null,
                'message' => 'Aanvraag is al verwerkt.',
            ], 422);
        }

        $swapRequest->update(['status' => 'declined']);

        return response()->json([
            'success' => true,
            'data'    => null,
            'message' => 'Wissel afgewezen.',
        ]);
    }

    private function authorizeSwapRequest(string $type, string $targetId, Member $member): void
    {
        $assigned = match ($type) {
            'bardienst' => BarDuty::find($targetId)?->members()->where('members.id', $member->id)->exists(),
            'fruitheld' => FootballMatch::find($targetId)?->fruit_hero_id === $member->id,
            'rijden'    => FootballMatch::find($targetId)?->drivers()->where('members.id', $member->id)->exists(),
            default     => false,
        };

        if (!$assigned) {
            abort(422, 'Je bent niet toegewezen aan deze taak.');
        }
    }

    private function performSwap(SwapRequest $swapRequest): void
    {
        $requesterId = $swapRequest->requester_id;
        $requesteeId = $swapRequest->requestee_id;
        $targetId    = $swapRequest->target_id;

        match ($swapRequest->type) {
            'bardienst' => $this->swapBarDuty($targetId, $requesterId, $requesteeId),
            'fruitheld' => $this->swapFruitHero($targetId, $requesteeId),
            'rijden'    => $this->swapDriver($targetId, $requesterId, $requesteeId),
            default     => null,
        };
    }

    private function swapBarDuty(string $dutyId, string $fromId, string $toId): void
    {
        $duty = BarDuty::find($dutyId);
        if (!$duty) return;
        $duty->members()->detach($fromId);
        $duty->members()->syncWithoutDetaching([$toId]);
        $duty->refreshStatus();
    }

    private function swapFruitHero(string $matchId, string $toId): void
    {
        FootballMatch::where('id', $matchId)->update(['fruit_hero_id' => $toId]);
    }

    private function swapDriver(string $matchId, string $fromId, string $toId): void
    {
        $match = FootballMatch::find($matchId);
        if (!$match) return;
        $match->drivers()->detach($fromId);
        $match->drivers()->syncWithoutDetaching([$toId]);
    }
}
