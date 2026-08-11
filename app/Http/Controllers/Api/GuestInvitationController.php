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

        // Push UITSLUITEND naar het persoonlijke topic van de gastspeler zelf
        // (user_<eigen-login-e-mail>) — nooit naar een team of broadcast. We
        // gebruiken bewust alléén het eigen app-account van de gast (member->user)
        // en niet het Sportlink-e-mailveld: dat laatste is bij jeugdspelers vaak
        // een gedeelde ouder-inbox, waardoor de melding bij iemand anders dan de
        // gast terecht zou komen. Zonder eigen app-account geen push.
        $guestEmail = $member->user?->email;
        if (! empty($guestEmail)) {
            try {
                $inviter = $user->member?->name ?: $user->name;
                $when    = $match->match_datetime?->format('d-m H:i');
                $topic   = 'user_' . FcmService::sanitizeTopicEmail($guestEmail);
                app(FcmService::class)->sendToTopic(
                    $topic,
                    'Uitnodiging wedstrijd',
                    trim($inviter . ' heeft je uitgenodigd voor ' . ($match->opponent ?? 'een wedstrijd')
                        . ($when ? ' op ' . $when : '') . '.'),
                    ['initialPageName' => 'WedstrijdDetailPage', 'parameterData' => json_encode(['matchId' => $match->id])],
                );
                Log::info('[GuestInvite] push naar gast', ['member_id' => $member->id, 'topic' => $topic]);
            } catch (\Throwable $e) {
                Log::warning('[GuestInvite] push naar gast mislukt', ['error' => $e->getMessage()]);
            }
        } else {
            Log::info('[GuestInvite] gast heeft geen eigen app-account; geen push', ['member_id' => $member->id]);
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
                // String i.p.v. bool: de app-struct GuestInvitation heeft alleen
                // string-velden (codeExpressie vergelijkt op 'true').
                'isHome'         => $inv->match->is_home ? 'true' : 'false',
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

    /**
     * GET /v1/matches/{match}/guests
     *
     * Kale array van actieve gastspelers voor deze wedstrijd: [{id, name}]
     * (id = member-id). Voor de gebonden lijst met verwijderknoppen op het
     * info-tabblad.
     */
    public function guests(Request $request, FootballMatch $match): JsonResponse
    {
        $guests = MatchGuestInvitation::query()
            ->where('match_id', $match->id)
            ->active()
            ->with('member:id,name')
            ->get()
            ->filter(fn ($g) => $g->member !== null)
            ->map(fn ($g) => ['id' => $g->member->id, 'name' => $g->member->name])
            ->values();

        return response()->json($guests);
    }

    /**
     * POST /v1/matches/{match}/guest-invite/remove?memberId=..
     *
     * Coach verwijdert een gastspeler-uitnodiging van deze wedstrijd op basis van
     * het member-id (handig vanaf het info-tabblad, waar we member-id's tonen).
     */
    public function removeByMember(Request $request, FootballMatch $match): JsonResponse
    {
        $user = $request->user();
        if (! $user->canManageLineup($match->team_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Je hebt geen rechten om gastspelers te verwijderen.',
            ], 403);
        }

        $memberId = trim((string) $request->input('memberId', ''));
        if ($memberId === '') {
            return response()->json(['success' => false, 'message' => 'Geen speler opgegeven.'], 422);
        }

        MatchGuestInvitation::query()
            ->where('match_id', $match->id)
            ->where('member_id', $memberId)
            ->where('status', 'active')
            ->update([
                'status'             => 'revoked',
                'revoked_by_user_id' => $user->id,
                'revoked_at'         => now(),
            ]);

        return response()->json(['success' => true, 'message' => 'Gastspeler verwijderd.']);
    }
}
