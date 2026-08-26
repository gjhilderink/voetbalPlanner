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
            ->with(['team', 'members', 'users'])
            ->where('club_id', $user->club_id)
            // Alleen de diensten van de elftallen waar je bij hoort - en dan wel
            // van al je elftallen. Dit gold eerder niet voor beheerders en de
            // barcommissie, die kregen in de app de hele club te zien. Plannen
            // gebeurt in de portal; hier gaat het om wat jou aangaat.
            //
            // accessibleTeams() en niet managedTeams + eigen lid: die eerste telt
            // ook de elftallen van gekoppelde kinderen mee, en een ouder die voor
            // zijn kind achter de bar staat hoort die dienst te zien.
            ->where(function ($q) use ($user) {
                $teamIds = $user->accessibleTeams()->pluck('id');

                if ($teamIds->isNotEmpty()) {
                    $q->whereIn('team_id', $teamIds);
                } else {
                    $q->whereRaw('0 = 1');
                }

                // Diensten zonder elftal zijn clubbreed; die gaan iedereen aan en
                // zouden anders voor niemand meer zichtbaar zijn.
                $q->orWhereNull('team_id');
            })
            ->when($request->team_id, fn($q, $id) => $q->where('team_id', $id))
            // mine=1: alleen de bardiensten waarvoor deze gebruiker zelf is
            // ingedeeld. Het dashboard toont die als persoonlijke taak; zonder
            // deze filter zou de eerste dienst uit de teamlijst getoond worden.
            ->when(
                $request->boolean('mine'),
                function ($q) use ($user) {
                    $memberId = $user->member?->id;
                    $q->where(function ($sub) use ($user, $memberId) {
                        $sub->whereHas('users', fn($u) => $u->where('users.id', $user->id));
                        if ($memberId) {
                            $sub->orWhereHas('members', fn($m) => $m->where('members.id', $memberId));
                        }
                    });
                }
            )
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            // Standaard alleen toekomstige bardiensten (vandaag telt mee); verleden
            // verbergen. Te overrulen met een expliciete date_from of include_past=1.
            ->when(
                !$request->filled('date_from') && !$request->boolean('include_past'),
                fn($q) => $q->whereDate('date', '>=', now()->toDateString())
            )
            ->when($request->date_from, fn($q, $d) => $q->whereDate('date', '>=', $d))
            ->when($request->date_to, fn($q, $d) => $q->whereDate('date', '<=', $d))
            ->orderBy('date')
            ->paginate($request->integer('per_page', 25));

        return response()->json(
            collect($duties->items())->map(fn($d) => (new BarDutyResource($d))->resolve())
        );
    }

    public function show(BarDuty $barDuty): JsonResponse
    {
        $this->authorizeAccess($barDuty);

        $barDuty->load(['team', 'members', 'users']);

        return response()->json(new BarDutyResource($barDuty));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeRole(['super_admin', 'club_admin', 'bar_commissie']);

        $shiftKeys  = implode(',', array_keys(BarDuty::SHIFTS));
        $maxMembers = BarDuty::SHIFTS[$request->input('shift')]['required'] ?? 3;

        $validated = $request->validate([
            'date'         => 'required|date_format:Y-m-d',
            'shift'        => "required|in:{$shiftKeys}",
            'team_id'      => 'nullable|uuid|exists:teams,id',
            'status'       => 'sometimes|in:open,bevestigd,vervuld',
            'notes'        => 'nullable|string|max:2000',
            'member_ids'   => "nullable|array|max:{$maxMembers}",
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

        $duty->load(['team', 'members', 'users']);

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

        $shiftKeys = implode(',', array_keys(BarDuty::SHIFTS));
        $validated = $request->validate([
            'date'    => 'sometimes|date_format:Y-m-d',
            'shift'   => "sometimes|in:{$shiftKeys}",
            'team_id' => 'sometimes|nullable|uuid|exists:teams,id',
            'status'  => 'sometimes|in:open,bevestigd,vervuld',
            'notes'   => 'nullable|string|max:2000',
        ]);

        $barDuty->update($validated);
        $barDuty->load(['team', 'members', 'users']);

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
            if (!in_array($barDuty->team_id, $user->managedTeamIds()->all())) {
                return response()->json(['success' => false, 'message' => 'Geen toegang.'], 403);
            }
        }

        $this->authorizeAccess($barDuty);

        $validated = $request->validate([
            'member_ids'   => 'required|array|max:' . $barDuty->requiredCount(),
            'member_ids.*' => 'uuid|exists:members,id',
        ]);

        $barDuty->members()->sync($validated['member_ids']);
        $barDuty->refreshStatus();
        $barDuty->load(['team', 'members', 'users']);

        return response()->json([
            'success' => true,
            'data'    => new BarDutyResource($barDuty),
            'message' => 'Leden opgeslagen.',
        ]);
    }

    /**
     * PATCH /bar-duties/{barDuty}/self-assign
     *
     * Laat een ingelogde gebruiker zichzelf inschrijven voor een open bardienst.
     * Voorwaarden:
     *  - Gebruiker heeft een member-profiel en zit in het team van de bardienst
     *  - Bardienst is nog niet vol (< BarDuty::REQUIRED_MEMBERS)
     *  - Gebruiker staat nog niet op de bardienst
     */
    public function selfAssign(Request $request, BarDuty $barDuty): JsonResponse
    {
        $this->authorizeAccess($barDuty);

        $user   = $request->user();
        $member = $user?->resolveMember();

        // Lid (member_id) óf los account (user_id) mag zich aanmelden, mits aan
        // het team van de bardienst gekoppeld (als lid/coach of als ouder).
        if ($barDuty->team_id) {
            $isInTeam = $user->accessibleTeams()->contains('id', $barDuty->team_id);
            if (! $isInTeam) {
                return response()->json([
                    'success' => false,
                    'message' => 'Deze bardienst is voor een ander team.',
                ], 403);
            }
        }

        // Ouders komen vaak met z'n tweeën en melden zich via één account aan.
        // Zonder aantal telt de aanmelding voor één plek, zoals voorheen.
        $validated = $request->validate([
            'spots' => 'nullable|integer|min:1|max:10',
        ]);
        $spots = (int) ($validated['spots'] ?? 1);

        $barDuty->load(['members', 'users']);
        $memberIds = $barDuty->members->pluck('id');
        $userIds   = $barDuty->users->pluck('id');

        $already = ($member && $memberIds->contains($member->id)) || $userIds->contains($user->id);

        // Al aangemeld? Dan is dit een bijstelling van het aantal, niet een
        // dubbele aanmelding. Anders zou iemand die er eentje bij wil nemen
        // zich eerst moeten afmelden.
        $eigenPlekken = 0;
        if ($already) {
            $eigen = $member
                ? $barDuty->members->firstWhere('id', $member->id)
                : $barDuty->users->firstWhere('id', $user->id);
            $eigenPlekken = max(1, (int) ($eigen?->pivot->spots ?? 1));
        }

        $bezetDoorAnderen = $barDuty->filledCount() - $eigenPlekken;
        $vrij             = $barDuty->requiredCount() - $bezetDoorAnderen;

        if ($vrij < 1) {
            return response()->json([
                'success' => false,
                'message' => 'Deze bardienst is al vol.',
            ], 422);
        }
        if ($spots > $vrij) {
            return response()->json([
                'success' => false,
                'message' => $vrij === 1
                    ? 'Er is nog maar één plek vrij.'
                    : "Er zijn nog maar {$vrij} plekken vrij.",
            ], 422);
        }

        $relatie = $member ? $barDuty->members() : $barDuty->users();
        $id      = $member ? $member->id : $user->id;
        // syncWithoutDetaching werkt zowel voor een nieuwe aanmelding als voor
        // het bijstellen van het aantal.
        $relatie->syncWithoutDetaching([$id => ['spots' => $spots]]);
        // Eerst opnieuw inlezen, dán de status bepalen: de relaties hierboven
        // waren al geladen en zouden anders de stand van vóór het aanmelden
        // gebruiken.
        $barDuty->load(['team', 'members', 'users']);
        $barDuty->refreshStatus();

        return response()->json([
            'success' => true,
            'data'    => new BarDutyResource($barDuty),
            'message' => $spots > 1
                ? "Je bent aangemeld voor deze bardienst met {$spots} personen."
                : 'Je bent aangemeld voor deze bardienst.',
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
