<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FootballMatch;
use App\Models\MatchGuestInvitation;
use App\Models\Member;
use App\Models\Team;
use App\Services\FcmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GuestInvitationController extends Controller
{
    /**
     * POST /v1/matches/{match}/guest-invite?teamId=..&memberId=..
     *
     * Coach nodigt een gastspeler uit voor deze wedstrijd. Informatief: de gast
     * krijgt toegang tot de wedstrijdinfo tot kort na de wedstrijd.
     */
    public function invite(Request $request, FootballMatch $match): JsonResponse
    {
        $user = $request->user();

        // Alleen een coach/leider van het team van de wedstrijd (of admin) mag uitnodigen.
        if (! $user->canManageLineup($match->team_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Je hebt geen rechten om voor deze wedstrijd uit te nodigen.',
            ], 403);
        }

        $validated = $request->validate([
            'teamId'   => 'required|string',
            'memberId' => 'required|string',
        ]);

        $match->loadMissing('team');
        $clubId = $match->team?->club_id ?? $user->club_id;

        // Team moet tot dezelfde club horen; lid moet daadwerkelijk in dat team zitten.
        $team = Team::where('id', $validated['teamId'])
            ->when($clubId, fn ($q) => $q->where('club_id', $clubId))
            ->first();
        if (! $team) {
            return response()->json([
                'success' => false,
                'message' => 'Team niet gevonden binnen deze club.',
            ], 422);
        }

        $member = Member::where('id', $validated['memberId'])
            ->whereHas('teams', fn ($q) => $q->where('teams.id', $team->id))
            ->first();
        if (! $member) {
            return response()->json([
                'success' => false,
                'message' => 'Speler niet gevonden in het gekozen team.',
            ], 422);
        }

        if ($member->id === $user->member?->id) {
            return response()->json([
                'success' => false,
                'message' => 'Je kunt jezelf niet als gastspeler uitnodigen.',
            ], 422);
        }

        $invitation = MatchGuestInvitation::updateOrCreate(
            ['match_id' => $match->id, 'member_id' => $member->id],
            [
                'club_id'            => $clubId,
                'team_id'            => $team->id,
                'invited_by_user_id' => $user->id,
                'status'             => 'active',
                'revoked_by_user_id' => null,
                'revoked_at'         => null,
                'expires_at'         => $match->match_datetime?->copy()->addDay() ?? now()->addDays(7),
            ],
        );

        // Push naar de gastspeler (alleen zinvol met app-account). Faalt stil.
        $email = $member->user?->email ?? $member->email;
        if (! empty($email)) {
            try {
                $inviter = $user->member?->name ?: $user->name;
                $when    = $match->match_datetime?->format('d-m H:i');
                app(FcmService::class)->sendToTopic(
                    'user_' . FcmService::sanitizeTopicEmail($email),
                    'Uitnodiging wedstrijd',
                    trim($inviter . ' heeft je uitgenodigd voor ' . ($match->opponent ?? 'een wedstrijd')
                        . ($when ? ' op ' . $when : '') . '.'),
                    ['initialPageName' => 'WedstrijdDetailPage', 'parameterData' => json_encode(['matchId' => $match->id])],
                );
            } catch (\Throwable $e) {
                Log::warning('[GuestInvite] push naar gast mislukt', ['error' => $e->getMessage()]);
            }
        }

        return response()->json([
            'success' => true,
            'data'    => ['id' => $invitation->id],
            'message' => $member->name . ' is uitgenodigd als gastspeler.',
        ], 201);
    }

    /**
     * GET /v1/guest-invite/teams
     *
     * Kale array van actieve teams van de club (voor de team-keuze bij het uitnodigen).
     */
    public function selectableTeams(Request $request): JsonResponse
    {
        $clubId = $request->user()->club_id;

        $teams = Team::query()
            ->when($clubId, fn ($q) => $q->where('club_id', $clubId))
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])
            ->values();

        return response()->json($teams);
    }

    /**
     * GET /v1/guest-invitations
     *
     * Kale array van actieve gastspeler-uitnodigingen voor het ingelogde lid,
     * met de wedstrijd-weergavevelden (zelfde vorm als een wedstrijdkaart).
     */
    public function myInvitations(Request $request): JsonResponse
    {
        $member = $request->user()->member;
        if (! $member) {
            return response()->json([]);
        }

        $invitations = MatchGuestInvitation::with(['match.team', 'invitedBy'])
            ->where('member_id', $member->id)
            ->active()
            ->get()
            ->filter(fn ($inv) => $inv->match !== null)
            ->map(fn ($inv) => [
                'id'             => $inv->id,
                'matchId'        => $inv->match->id,
                'opponent'       => $inv->match->opponent ?? '',
                'opponentLogo'   => $inv->match->opponent_logo ?? '',
                'matchDatetime'  => $inv->match->match_datetime?->format('d-m-Y H:i') ?? '',
                'location'       => $inv->match->location ?? '',
                'isHome'         => (bool) $inv->match->is_home,
                'teamName'       => $inv->match->team?->name ?? '',
                'invitedByName'  => $inv->invitedBy?->name ?? '',
            ])
            ->values();

        return response()->json($invitations);
    }

    /**
     * DELETE /v1/guest-invitations/{invitation}/revoke
     *
     * Coach die uitnodigde of een beheerder trekt de uitnodiging in.
     */
    public function revoke(Request $request, MatchGuestInvitation $invitation): JsonResponse
    {
        $user = $request->user();
        $mayRevoke = $user->id === $invitation->invited_by_user_id
            || $user->canManageLineup($invitation->match?->team_id)
            || $user->hasAnyRole(['super_admin', 'club_admin']);

        if (! $mayRevoke) {
            return response()->json(['success' => false, 'message' => 'Niet gemachtigd.'], 403);
        }

        $invitation->update([
            'status'             => 'revoked',
            'revoked_by_user_id' => $user->id,
            'revoked_at'         => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Uitnodiging ingetrokken.']);
    }
}
