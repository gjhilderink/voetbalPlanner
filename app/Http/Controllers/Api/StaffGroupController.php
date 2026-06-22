<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MemberResource;
use App\Http\Resources\StaffGroupResource;
use App\Models\StaffGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffGroupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $groups = StaffGroup::query()
            ->with(['team', 'members'])
            ->where('club_id', $user->club_id)
            ->when(
                !$user->hasAnyRole(['super_admin', 'club_admin']),
                function ($q) use ($user) {
                    // Regular users only see groups they are a member of.
                    $memberId = $user->member?->id;
                    $memberId
                        ? $q->whereHas('members', fn($m) => $m->where('members.id', $memberId))
                        : $q->whereRaw('0 = 1');
                }
            )
            ->when($request->team_id, fn($q, $id) => $q->where('team_id', $id))
            ->orderBy('name')
            ->get();

        return response()->json(
            $groups->map(fn($g) => (new StaffGroupResource($g))->resolve())
        );
    }

    public function show(StaffGroup $staffGroup): JsonResponse
    {
        $this->authorizeAccess($staffGroup);

        $staffGroup->load(['team', 'members']);

        return response()->json(new StaffGroupResource($staffGroup));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeRole(['super_admin', 'club_admin']);

        $validated = $request->validate([
            'name'         => 'required|string|max:100',
            'description'  => 'nullable|string|max:2000',
            'team_id'      => 'nullable|uuid|exists:teams,id',
            'member_ids'   => 'nullable|array',
            'member_ids.*' => 'uuid|exists:members,id',
        ]);

        $group = StaffGroup::create([
            'club_id'     => $request->user()->club_id,
            'team_id'     => $validated['team_id'] ?? null,
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        if (!empty($validated['member_ids'])) {
            $group->members()->sync($validated['member_ids']);
        }

        $group->load(['team', 'members']);

        return response()->json([
            'success' => true,
            'data'    => new StaffGroupResource($group),
            'message' => 'Staffgroep aangemaakt.',
        ], 201);
    }

    public function update(Request $request, StaffGroup $staffGroup): JsonResponse
    {
        $this->authorizeRole(['super_admin', 'club_admin']);
        $this->authorizeAccess($staffGroup);

        $validated = $request->validate([
            'name'        => 'sometimes|string|max:100',
            'description' => 'nullable|string|max:2000',
            'team_id'     => 'sometimes|nullable|uuid|exists:teams,id',
        ]);

        $staffGroup->update($validated);
        $staffGroup->load(['team', 'members']);

        return response()->json([
            'success' => true,
            'data'    => new StaffGroupResource($staffGroup),
            'message' => 'Staffgroep bijgewerkt.',
        ]);
    }

    public function destroy(StaffGroup $staffGroup): JsonResponse
    {
        $this->authorizeRole(['super_admin', 'club_admin']);
        $this->authorizeAccess($staffGroup);

        $staffGroup->delete();

        return response()->json([
            'success' => true,
            'data'    => null,
            'message' => 'Staffgroep verwijderd.',
        ]);
    }

    /**
     * Returns the full member list (SwapMember shape) of a staff group.
     * Wordt gebruikt door de mobile TeamMembersPage voor staffgroup chats:
     * de hoofdshow() retourneert {id,name} per lid; deze endpoint geeft de
     * volledige struct met email, externalId en hasAppAccount.
     */
    public function fullMembers(StaffGroup $staffGroup): JsonResponse
    {
        $this->authorizeAccess($staffGroup);
        $staffGroup->load('members');
        return response()->json(
            MemberResource::collection($staffGroup->members)->resolve()
        );
    }

    public function syncMembers(Request $request, StaffGroup $staffGroup): JsonResponse
    {
        $this->authorizeRole(['super_admin', 'club_admin']);
        $this->authorizeAccess($staffGroup);

        $validated = $request->validate([
            'member_ids'   => 'required|array',
            'member_ids.*' => 'uuid|exists:members,id',
        ]);

        $staffGroup->members()->sync($validated['member_ids']);
        $staffGroup->load(['team', 'members']);

        return response()->json([
            'success' => true,
            'data'    => new StaffGroupResource($staffGroup),
            'message' => 'Leden opgeslagen.',
        ]);
    }

    private function authorizeAccess(StaffGroup $staffGroup): void
    {
        $user = request()->user();

        if ($user->club_id !== $staffGroup->club_id) {
            abort(403, 'Geen toegang.');
        }

        // Non-admins may only view groups they belong to.
        if (!$user->hasAnyRole(['super_admin', 'club_admin'])) {
            $memberId = $user->member?->id;
            if (!$memberId || !$staffGroup->members()->where('members.id', $memberId)->exists()) {
                abort(403, 'Geen toegang.');
            }
        }
    }

    private function authorizeRole(array $roles): void
    {
        if (!request()->user()->hasAnyRole($roles)) {
            abort(403, 'Geen toegang.');
        }
    }
}
