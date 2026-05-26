<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MemberResource;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $members = Member::query()
            ->when($request->team_id, fn($q, $id) => $q->whereHas('teams', fn($q) => $q->where('teams.id', $id)))
            ->when($request->role, fn($q, $r) => $q->where('role', $r))
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when($request->boolean('active_only', true), fn($q) => $q->where('is_active', true))
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => MemberResource::collection($members),
            'meta' => [
                'current_page' => $members->currentPage(),
                'last_page' => $members->lastPage(),
                'per_page' => $members->perPage(),
                'total' => $members->total(),
            ],
            'message' => '',
        ]);
    }

    public function show(Member $member): JsonResponse
    {
        $member->load(['teams', 'goals.match', 'assists.match']);

        return response()->json([
            'success' => true,
            'data' => new MemberResource($member),
            'message' => '',
        ]);
    }
}
