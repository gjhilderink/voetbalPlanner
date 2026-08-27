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
            ->with(['team', 'members', 'users'])
            ->where('club_id', $user->club_id)
            ->when(
                !$user->hasAnyRole(['super_admin', 'club_admin']),
                function ($q) use ($user) {
                    // Regular users only see groups they belong to — either as a
                    // member (Member) or as a directly linked account (User).
                    $memberId = $user->resolveMember()?->id;
                    $q->where(function ($sub) use ($user, $memberId) {
                        $sub->whereHas('users', fn($u) => $u->where('users.id', $user->id));
                        if ($memberId) {
                            $sub->orWhereHas('members', fn($m) => $m->where('members.id', $memberId));
                        }
                    });
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

        $staffGroup->load(['team', 'members', 'users']);

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

        $group->load(['team', 'members', 'users']);

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
        $staffGroup->load(['team', 'members', 'users']);

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
        $staffGroup->load(['members.teams', 'users']);

        $memberList = MemberResource::collection($staffGroup->members)->resolve();

        // Gekoppelde accounts (User) in dezelfde SwapMember-shape: ze hebben per
        // definitie een app-account, dus hasAppAccount = true.
        $userList = $staffGroup->users->map(fn($u) => [
            'id'            => $u->id,
            'name'          => $u->name,
            'email'         => $u->email,
            'phone'         => $u->phone,
            'role'          => null,
            'profile_photo' => $u->profile_photo,
            'is_active'     => $u->is_active ?? true,
            'external_id'   => $u->external_id,
            'externalId'    => $u->external_id,
            'hasAppAccount' => true,
            'teams'         => [],
        ])->values()->all();

        return response()->json(array_merge($memberList, $userList));
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
        $staffGroup->load(['team', 'members', 'users']);

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

        // Non-admins may only view groups they belong to — als gekoppeld lid
        // (Member) of als direct gekoppeld account (User).
        if (!$user->hasAnyRole(['super_admin', 'club_admin'])) {
            $isLinkedUser = $staffGroup->users()->where('users.id', $user->id)->exists();
            $memberId     = $user->resolveMember()?->id;
            $isMember     = $memberId && $staffGroup->members()->where('members.id', $memberId)->exists();
            if (!$isLinkedUser && !$isMember) {
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
