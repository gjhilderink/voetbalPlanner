<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GuardianLinkResource;
use App\Http\Resources\MemberResource;
use App\Models\Club;
use App\Models\GuardianLink;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GuardianController extends Controller
{
    /**
     * POST /v1/guardian/request
     *
     * Ouder/verzorger dient een koppelverzoek in voor een kind/lid.
     * Throttle: 5 per minuut, 20 per uur (zie routes).
     */
    public function request(Request $request): JsonResponse
    {
        $user = $request->user();
        $guardian = $user->member;

        if (! $guardian) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'Geen lid-profiel gevonden voor uw account.',
            ], 422);
        }

        $validated = $request->validate([
            'lidnummer'     => 'required|string|max:50',
            'achternaam'    => 'required|string|min:2|max:100',
            'geboortedatum' => 'required|date_format:Y-m-d|before:today',
        ]);

        // Zoek het kind via COMBINATIE van drie velden — nooit op één veld alleen.
        // Scope naar dezelfde club zodat leden van andere clubs niet vindbaar zijn.
        $child = Member::where('external_id', $validated['lidnummer'])
            ->where('name', 'like', '%' . $validated['achternaam'] . '%')
            ->whereDate('date_of_birth', $validated['geboortedatum'])
            ->where('is_active', true)
            ->whereHas('teams', fn ($q) => $q->where('teams.club_id', $user->club_id))
            ->first();

        // Altijd dezelfde foutmelding — voorkomt user-enumeration op lidnummer/naam
        if (! $child) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'Geen lid gevonden met de opgegeven gegevens.',
            ], 404);
        }

        if ($child->id === $guardian->id) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'Je kunt jezelf niet als kind/lid koppelen.',
            ], 422);
        }

        // Check op bestaand pending of approved verzoek
        $existing = GuardianLink::where('guardian_member_id', $guardian->id)
            ->where('child_member_id', $child->id)
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($existing) {
            $message = $existing->status === 'approved'
                ? 'Er bestaat al een actieve koppeling met dit lid.'
                : 'Er staat al een openstaand verzoek voor dit lid.';

            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => $message,
            ], 409);
        }

        $link = GuardianLink::create([
            'club_id'            => $user->club_id,
            'guardian_member_id' => $guardian->id,
            'child_member_id'    => $child->id,
            'status'             => 'pending',
            'request_token'      => GuardianLink::generateToken(),
            'expires_at'         => now()->addDays(14),
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'id'         => $link->id,
                'status'     => $link->status,
                'childName'  => $child->name,
                'expiresAt'  => $link->expires_at->toISOString(),
            ],
            'message' => 'Koppelverzoek verstuurd. Het lid wordt gevraagd dit te bevestigen.',
        ], 201);
    }

    /**
     * GET /v1/guardian/pending
     *
     * Kind/lid haalt zijn openstaande koppelverzoeken op.
     * Wordt ook aangeroepen bij login om de gebruiker te informeren.
     */
    public function pendingForMe(Request $request): JsonResponse
    {
        $member = $request->user()->member;

        if (! $member) {
            return response()->json(['success' => true, 'data' => [], 'message' => '']);
        }

        $links = GuardianLink::with('guardian')
            ->where('child_member_id', $member->id)
            ->pending()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($link) => [
                'id'            => $link->id,
                'guardianName'  => $link->guardian?->name ?? '',
                'guardianEmail' => $link->guardian?->email ?? '',
                'requestedAt'   => $link->created_at->toISOString(),
                'expiresAt'     => $link->expires_at->toISOString(),
            ]);

        return response()->json([
            'success' => true,
            'data'    => $links,
            'message' => '',
        ]);
    }

    /**
     * POST /v1/guardian/{guardianLink}/respond
     *
     * Kind/lid accepteert of weigert een koppelverzoek.
     * Alleen het kind/lid zelf mag reageren.
     */
    public function respond(Request $request, GuardianLink $guardianLink): JsonResponse
    {
        $member = $request->user()->member;

        // Alleen het kind/lid waarop het verzoek betrekking heeft mag reageren
        if (! $member || $member->id !== $guardianLink->child_member_id) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'Niet gemachtigd.',
            ], 403);
        }

        if (! $guardianLink->isPending()) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'Dit verzoek is niet meer geldig of al verwerkt.',
            ], 409);
        }

        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
        ]);

        $newStatus = $validated['action'] === 'approve' ? 'approved' : 'rejected';

        $guardianLink->update([
            'status'                => $newStatus,
            'resolved_by_member_id' => $member->id,
            'resolved_at'           => now(),
        ]);

        $message = $newStatus === 'approved'
            ? 'Koppeling goedgekeurd. De ouder/verzorger heeft nu toegang tot uw gegevens.'
            : 'Verzoek geweigerd.';

        return response()->json([
            'success' => true,
            'data'    => ['status' => $newStatus],
            'message' => $message,
        ]);
    }

    /**
     * DELETE /v1/guardian/{guardianLink}/revoke
     *
     * Trekt een koppeling in. Mag door:
     * - Het kind/lid zelf
     * - De ouder/verzorger zelf
     * - Een beheerder (super_admin / club_admin)
     */
    public function revoke(Request $request, GuardianLink $guardianLink): JsonResponse
    {
        $user   = $request->user();
        $member = $user->member;

        $isChild    = $member && $member->id === $guardianLink->child_member_id;
        $isGuardian = $member && $member->id === $guardianLink->guardian_member_id;
        $isAdmin    = $user->hasAnyRole(['super_admin', 'club_admin']);

        if (! $isChild && ! $isGuardian && ! $isAdmin) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'Niet gemachtigd.',
            ], 403);
        }

        // Zorg ook dat admins alleen koppelingen binnen hun eigen club kunnen intrekken
        if ($isAdmin && ! $isChild && ! $isGuardian) {
            if ($guardianLink->club_id !== $user->club_id) {
                return response()->json([
                    'success' => false,
                    'data'    => null,
                    'message' => 'Niet gemachtigd.',
                ], 403);
            }
        }

        if (! $guardianLink->isRevocable()) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'Deze koppeling kan niet worden ingetrokken.',
            ], 409);
        }

        $guardianLink->update([
            'status'               => 'revoked',
            'revoked_by_member_id' => $member?->id,
            'revoked_at'           => now(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => null,
            'message' => 'Koppeling ingetrokken.',
        ]);
    }

    /**
     * GET /v1/guardian/children
     *
     * Ouder/verzorger haalt zijn/haar goedgekeurde gekoppelde kinderen op.
     */
    public function children(Request $request): JsonResponse
    {
        $member = $request->user()->member;

        if (! $member) {
            return response()->json(['success' => true, 'data' => [], 'message' => '']);
        }

        $links = GuardianLink::with('child')
            ->where('guardian_member_id', $member->id)
            ->approved()
            ->get();

        $children = $links->map(fn ($link) => [
            'linkId'     => $link->id,
            'memberId'   => $link->child?->id,
            'name'       => $link->child?->name ?? '',
            'email'      => $link->child?->email ?? '',
            'externalId' => $link->child?->external_id ?? '',
            'dateOfBirth'=> $link->child?->date_of_birth?->format('Y-m-d'),
            'approvedAt' => $link->resolved_at?->toISOString(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $children,
            'message' => '',
        ]);
    }

    /**
     * GET /v1/guardian/my-requests
     *
     * Ouder/verzorger ziet al zijn/haar ingediende verzoeken (alle statussen).
     */
    public function myRequests(Request $request): JsonResponse
    {
        $member = $request->user()->member;

        if (! $member) {
            return response()->json(['success' => true, 'data' => [], 'message' => '']);
        }

        $links = GuardianLink::with('child')
            ->where('guardian_member_id', $member->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => GuardianLinkResource::collection($links),
            'message' => '',
        ]);
    }

    /**
     * GET /v1/guardian/members/{member}/data
     *
     * Ouder/verzorger bekijkt basisgegevens van een gekoppeld kind.
     * Geeft alleen data terug als er een goedgekeurde koppeling bestaat.
     */
    public function childData(Request $request, Member $member): JsonResponse
    {
        $guardian = $request->user()->member;

        if (! $guardian) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'Niet gemachtigd.',
            ], 403);
        }

        $hasLink = GuardianLink::where('guardian_member_id', $guardian->id)
            ->where('child_member_id', $member->id)
            ->approved()
            ->exists();

        if (! $hasLink) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'Geen actieve koppeling met dit lid.',
            ], 403);
        }

        $member->load(['teams']);

        return response()->json([
            'success' => true,
            'data'    => new MemberResource($member),
            'message' => '',
        ]);
    }

    /**
     * POST /v1/guardian/create-parent-account
     *
     * Een lid maakt een ouder/verzorger-account aan voor zichzelf.
     * De koppeling wordt direct goedgekeurd — geen bevestiging nodig.
     * De ouder krijgt een e-mail om in te loggen via een magic link.
     */
    public function createParentAccount(Request $request): JsonResponse
    {
        $user  = $request->user();
        $child = $user->member;

        if (! $child) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'Geen lid-profiel gevonden voor uw account.',
            ], 422);
        }

        $validated = $request->validate([
            'naam'  => 'required|string|min:2|max:100',
            'email' => 'required|email|max:191',
        ]);

        $email = strtolower($validated['email']);

        // Controleer of het e-mailadres al in gebruik is
        if (User::where('email', $email)->exists()) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'Dit e-mailadres is al in gebruik. '
                    . 'De ouder kan inloggen en vanuit zijn profiel een koppeling aanvragen.',
            ], 409);
        }

        // Maak een Member-record aan voor de ouder (geen club-lid, maar wel nodig voor koppeling)
        $parentMember = Member::create([
            'name'      => $validated['naam'],
            'email'     => $email,
            'is_active' => true,
            'role'      => 'player',
        ]);

        // Maak een User-account aan (wachtwoord is willekeurig — ouder logt in via magic link)
        $parentUser = User::create([
            'name'      => $validated['naam'],
            'email'     => $email,
            'password'  => Str::random(32),
            'club_id'   => $user->club_id,
            'is_active' => true,
        ]);

        // Koppel User aan Member
        $parentMember->update(['user_id' => $parentUser->id]);

        // Maak de koppeling direct goedgekeurd aan — het kind heeft zelf het account aangemaakt
        GuardianLink::create([
            'club_id'               => $user->club_id,
            'guardian_member_id'    => $parentMember->id,
            'child_member_id'       => $child->id,
            'status'                => 'approved',
            'request_token'         => GuardianLink::generateToken(),
            'expires_at'            => now()->addYears(10),
            'resolved_by_member_id' => $child->id,
            'resolved_at'           => now(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'parentName'  => $parentMember->name,
                'parentEmail' => $parentMember->email,
            ],
            'message' => 'Ouder account aangemaakt. De ouder kan nu inloggen via de magic link in de app.',
        ], 201);
    }
}
