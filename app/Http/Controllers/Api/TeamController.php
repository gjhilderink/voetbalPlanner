<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MemberResource;
use App\Http\Resources\TeamResource;
use App\Models\Team;
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

        $members = $team->members()
            ->where('members.is_active', true)
            ->when($myMemberId, fn($q) => $q->where('members.id', '!=', $myMemberId))
            ->orderBy('members.name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => MemberResource::collection($members),
            'message' => '',
        ]);
    }
}
