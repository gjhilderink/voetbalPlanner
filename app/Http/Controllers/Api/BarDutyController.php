<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BarDutyResource;
use App\Models\BarDuty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BarDutyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $duties = BarDuty::query()
            ->with(['team', 'members'])
            ->where('club_id', $user->club_id)
            ->when(
                !$user->hasAnyRole(['super_admin', 'club_admin', 'bar_commissie']),
                fn($q) => $q->whereIn('team_id', $user->managedTeamIds()),
            )
            ->when($request->team_id, fn($q, $id) => $q->where('team_id', $id))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->date_from, fn($q, $d) => $q->whereDate('date', '>=', $d))
            ->when($request->date_to, fn($q, $d) => $q->whereDate('date', '<=', $d))
            ->orderBy('date')
            ->paginate($request->integer('per_page', 25));

        return response()->json([
            'success' => true,
            'data'    => BarDutyResource::collection($duties),
            'meta'    => [
                'current_page' => $duties->currentPage(),
                'last_page'    => $duties->lastPage(),
                'per_page'     => $duties->perPage(),
                'total'        => $duties->total(),
            ],
            'message' => '',
        ]);
    }

    public function show(BarDuty $barDuty): JsonResponse
    {
        $this->authorizeAccess($barDuty);

        $barDuty->load(['team', 'members']);

        return response()->json([
            'success' => true,
            'data'    => new BarDutyResource($barDuty),
            'message' => '',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeRole(['super_admin', 'club_admin', 'bar_commissie']);

        $validated = $request->validate([
            'date'    => 'required|date_format:Y-m-d',
            'shift'   => 'required|in:ochtend,middag,avond',
            'team_id' => 'nullable|uuid|exists:teams,id',
            'status'  => 'sometimes|in:open,bevestigd,vervuld',
            'notes'   => 'nullable|string|max:2000',
            'member_ids' => 'nullable|array|max:2',
            'member_ids.*' => 'uuid|exists:members,id',
        ]);

        $duty = BarDuty::create([
            'club_id' => $request->user()->club_id,
            'team_id' => $validated['team_id'] ?? null,
            'date'    => $validated['date'],
            'shift'   => $validated['shift'],
            'status'  => $validated['status'] ?? 'open',
            'notes'   => $validated['notes'] ?? null,
        ]);

        if (!empty($validated['member_ids'])) {
            $duty->members()->sync($validated['member_ids']);
            $duty->refreshStatus();
        }

        $duty->load(['team', 'members']);

        return response()->json([
            'success' => true,
            'data'    => new BarDutyResource($duty),
            'message' => 'Bardienst aangemaakt.',
        ], 201);
    }

    public function update(Request $request, BarDuty $barDuty): JsonResponse
    {
        $this->authorizeRole(['super_admin', 'club_admin', 'bar_commissie']);
        $this->authorizeAccess($barDuty);

        $validated = $request->validate([
            'date'    => 'sometimes|date_format:Y-m-d',
            'shift'   => 'sometimes|in:ochtend,middag,avond',
            'team_id' => 'sometimes|nullable|uuid|exists:teams,id',
            'status'  => 'sometimes|in:open,bevestigd,vervuld',
            'notes'   => 'nullable|string|max:2000',
        ]);

        $barDuty->update($validated);
        $barDuty->load(['team', 'members']);

        return response()->json([
            'success' => true,
            'data'    => new BarDutyResource($barDuty),
            'message' => 'Bardienst bijgewerkt.',
        ]);
    }

    public function destroy(BarDuty $barDuty): JsonResponse
    {
        $this->authorizeRole(['super_admin', 'club_admin', 'bar_commissie']);
        $this->authorizeAccess($barDuty);

        $barDuty->delete();

        return response()->json([
            'success' => true,
            'data'    => null,
            'message' => 'Bardienst verwijderd.',
        ]);
    }

    public function assignMembers(Request $request, BarDuty $barDuty): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasAnyRole(['super_admin', 'club_admin', 'bar_commissie'])) {
            // Coaches may only assign to their own teams
            if (!in_array($barDuty->team_id, $user->managedTeamIds()->all())) {
                return response()->json(['success' => false, 'message' => 'Geen toegang.'], 403);
            }
        }

        $this->authorizeAccess($barDuty);

        $validated = $request->validate([
            'member_ids'   => 'required|array|max:2',
            'member_ids.*' => 'uuid|exists:members,id',
        ]);

        $barDuty->members()->sync($validated['member_ids']);
        $barDuty->refreshStatus();
        $barDuty->load(['team', 'members']);

        return response()->json([
            'success' => true,
            'data'    => new BarDutyResource($barDuty),
            'message' => 'Leden opgeslagen.',
        ]);
    }

    private function authorizeAccess(BarDuty $barDuty): void
    {
        $user = request()->user();
        if ($user->club_id !== $barDuty->club_id) {
            abort(403, 'Geen toegang.');
        }
    }

    private function authorizeRole(array $roles): void
    {
        if (!request()->user()->hasAnyRole($roles)) {
            abort(403, 'Geen toegang.');
        }
    }
}
