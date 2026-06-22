<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MemberResource;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $teams = Team::query()
            ->when($request->boolean('active_only', true), fn($q) => $q->where('is_active', true))
            ->when($request->season, fn($q, $s) => $q->where('season', $s))
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => TeamResource::collection($teams),
            'meta' => [
                'current_page' => $teams->currentPage(),
                'last_page' => $teams->lastPage(),
                'per_page' => $teams->perPage(),
                'total' => $teams->total(),
            ],
            'message' => '',
        ]);
    }

    public function show(Team $team): JsonResponse
    {
        $team->load(['members', 'matches' => fn($q) => $q->orderBy('match_datetime')]);

        return response()->json([
            'success' => true,
            'data' => new TeamResource($team),
            'message' => '',
        ]);
    }

    public function members(Request $request, Team $team): JsonResponse
    {
        $myMemberId = $request->user()?->member?->id;
        $myUserId   = $request->user()?->id;

        // 1. Klassieke Sportlink-leden via member_team pivot.
        $members = $team->members()
            ->when($myMemberId, fn($q) => $q->where('members.id', '!=', $myMemberId))
            ->orderBy('members.name')
            ->get();

        $memberPayload = $members
            ->map(fn($m) => (new MemberResource($m))->resolve())
            ->all();

        // 2. App-accounts (User) zonder Member-record gekoppeld via user_team
        //    pivot — bv. een bardienst-user, coach of staff-leider die geen
        //    rooster-lid is. Worden Member-shape gemapt voor de mobile app.
        $linkedMemberUserIds = $members->pluck('user_id')->filter()->all();

        $extraUsers = $team->users()
            ->whereNotIn('users.id', $linkedMemberUserIds)
            ->when($myUserId, fn($q) => $q->where('users.id', '!=', $myUserId))
            ->orderBy('users.name')
            ->get();

        foreach ($extraUsers as $u) {
            // Skip als de User wel een Member-record heeft die ergens anders al
            // is gematched (verdedigende dedup).
            if (in_array($u->id, $linkedMemberUserIds, true)) {
                continue;
            }

            $memberPayload[] = [
                'id'             => 'user_' . $u->id,
                'name'           => $u->name ?: $u->email,
                'email'          => $u->email,
                'phone'          => null,
                'date_of_birth'  => null,
                'role'           => null,
                'profile_photo'  => $u->profile_photo,
                'is_active'      => true,
                'external_id'    => '',
                'externalId'     => '',
                'hasAppAccount'  => true,
                'teams'          => [],
                'created_at'     => $u->created_at?->toISOString(),
            ];
        }

        // Sort merged op naam.
        usort($memberPayload, fn($a, $b) => strcasecmp(($a['name'] ?? ''), ($b['name'] ?? '')));

        return response()->json($memberPayload);
    }
}
