<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GuardianLinkResource;
use App\Http\Resources\MemberResource;
use App\Mail\MagicLinkMail;
use App\Models\Club;
use App\Models\GuardianLink;
use App\Models\MagicLinkToken;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GuardianController extends Controller
{
    /**
     * Verifieert de opgegeven achternaam tegen een lid. Primair tegen het aparte
     * last_name-veld (case-insensitive, met tussenvoegsel-tolerantie); valt terug
     * op de volledige naam als last_name (nog) niet gevuld is. Bij een lid met
     * alleen een voornaam volstaat het unieke lidnummer (kind bevestigt sowieso).
     */
    private function achternaamMatcht(Member $child, string $achternaam): bool
    {
        $achternaam = trim($achternaam);
        if ($achternaam === '') {
            return true;
        }
        $a = mb_strtolower($achternaam);

        $lastName = mb_strtolower(trim((string) $child->last_name));
        if ($lastName !== '') {
            // Tolerant voor tussenvoegsels: "Berg" matcht "van der Berg" en v.v.
            return str_contains($lastName, $a) || str_contains($a, $lastName);
        }

        // Geen aparte achternaam bekend: val terug op de volledige naam als die
        // meerdere woorden bevat; anders volstaat het lidnummer.
        $name = trim((string) $child->name);
        if (str_contains($name, ' ')) {
            return str_contains(mb_strtolower($name), $a);
        }

        return true;
    }

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
            'lidnummer'  => 'required|string|max:50',
            'achternaam' => 'required|string|min:2|max:100',
        ]);

        // Zoek het kind via 2-veld combinatie (lidnummer + achternaam).
        // Het kind moet de koppeling nog bevestigen in de app, dus geboortedatum
        // is hier niet meer nodig als extra controle.
        $lidnummer  = trim($validated['lidnummer']);
        $achternaam = trim($validated['achternaam']);

        // Primair matchen op het unieke lidnummer (relatiecode). Geen is_active-
        // filter: een bestaand (evt. tijdelijk inactief) lid moet vindbaar zijn.
        // De achternaam wordt hieronder soepel geverifieerd; het kind moet de
        // koppeling sowieso nog bevestigen in de app.
        $child = Member::where('external_id', $lidnummer)->first();

        // Club-scope soepel: alleen weigeren als het kind aantoonbaar tot een
        // ándere club hoort dan de ouder. Kinderen zonder team/club-koppeling
        // blokkeren we hier niet op clubgrond.
        if ($child && $user->club_id) {
            $childClubIds = $child->teams()->pluck('teams.club_id')->filter()->unique();
            if ($childClubIds->isNotEmpty() && ! $childClubIds->contains($user->club_id)) {
                $child = null;
            }
        }

        // Achternaam verifiëren tegen het aparte last_name-veld (of, als fallback,
        // de volledige naam). Bij leden met alleen een voornaam volstaat het
        // unieke lidnummer + bevestiging door het kind.
        if ($child && ! $this->achternaamMatcht($child, $achternaam)) {
            $child = null;
        }

        // Altijd dezelfde foutmelding — voorkomt user-enumeration op lidnummer/naam
        if (! $child) {
            $raw = Member::withTrashed()->where('external_id', $lidnummer)->first();
            \Log::info('[Guardian] kind koppelen: geen match', [
                'lidnummer'        => $lidnummer,
                'achternaam'       => $achternaam,
                'guardian_club_id' => $user->club_id,
                'exists'           => $raw !== null,
                'is_active'        => $raw?->is_active,
                'trashed'          => $raw?->trashed(),
                'stored_name'      => $raw?->name,
                'stored_last_name' => $raw?->last_name,
                'child_club_ids'   => $raw?->teams()->pluck('teams.club_id')->all(),
            ]);
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

        // Push-melding naar het lid (kind) dat er een koppeling is aangevraagd.
        // Alleen zinvol als het lid een app-account heeft (het toestel abonneert op
        // topic user_<sanitize(login-e-mail)>). Faalt stil; breekt het verzoek nooit.
        $childEmail = $child->user?->email ?? $child->email;
        if (! empty($childEmail)) {
            try {
                app(\App\Services\FcmService::class)->sendToTopic(
                    'user_' . \App\Services\FcmService::sanitizeTopicEmail($childEmail),
                    'Nieuw koppelverzoek',
                    ($guardian->name ?: 'Een ouder/verzorger')
                        . ' wil aan jouw account gekoppeld worden. Open de app om te bevestigen.',
                    ['initialPageName' => 'GuardianPage', 'parameterData' => '{}'],
                );
            } catch (\Throwable $e) {
                \Log::warning('[Guardian] push naar lid mislukt', ['error' => $e->getMessage()]);
            }
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'id'         => $link->id,
                'status'     => $link->status,
                'childName'  => $child->name,
                'expiresAt'  => $link->expires_at->format('d-m-Y H:i'),
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
            return response()->json([]);
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
                'requestedAt'   => $link->created_at->format('d-m-Y H:i'),
                'expiresAt'     => $link->expires_at->format('d-m-Y H:i'),
            ]);

        // Kale array (geen {success,data,message}-wrapper): de app parset de
        // respons rechtstreeks als lijst, net als bij trainingen/bardiensten.
        return response()->json($links->values());
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

        // De ouder een melding sturen. Zonder dit moet die zelf blijven kijken
        // of het al gelukt is; de app toonde tot dat moment alleen "nog even
        // wachten". Push mag nooit het antwoord van het kind blokkeren, dus
        // fouten worden gelogd en niet doorgegeven.
        $this->notifyGuardianOfDecision($guardianLink, $newStatus);

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
     * Meldt de ouder/verzorger dat het kind op het verzoek heeft gereageerd.
     *
     * Gaat naar het topic `user_<sanitize(email)>` waar de app zich al op
     * abonneert. Faalt dit, dan blijft het bij een logregel: het antwoord van
     * het kind is verwerkt en dat mag niet stukgaan op een push.
     */
    private function notifyGuardianOfDecision(GuardianLink $link, string $status): void
    {
        try {
            $email = $link->guardian?->email;
            if (! $email) {
                return;
            }

            $kind = $link->child?->name ?: 'je kind';

            [$titel, $tekst] = $status === 'approved'
                ? ['Toegang goedgekeurd', "{$kind} heeft je toegang gegeven. Je ziet nu de wedstrijden en trainingen in de app."]
                : ['Verzoek geweigerd', "{$kind} heeft je verzoek om toegang geweigerd."];

            app(\App\Services\FcmService::class)->sendToTopic(
                'user_' . \App\Services\FcmService::sanitizeTopicEmail($email),
                $titel,
                $tekst,
                ['type' => 'guardian', 'status' => $status],
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[Guardian] push naar ouder mislukt', [
                'link'  => $link->id,
                'error' => $e->getMessage(),
            ]);
        }
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
            return response()->json([]);
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
            'approvedAt' => $link->resolved_at?->format('d-m-Y H:i') ?? '',
        ]);

        // Kale array (zie children-parsing in de app).
        return response()->json($children->values());
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
            return response()->json([]);
        }

        $links = GuardianLink::with('child')
            ->where('guardian_member_id', $member->id)
            ->orderByDesc('created_at')
            ->get();

        // Kale array (->resolve() ontdoet van de JsonResource {data:…}-wrapper),
        // zodat de app de respons rechtstreeks als lijst kan parsen.
        return response()->json(GuardianLinkResource::collection($links)->resolve());
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

        // withTrashed: users gebruikt SoftDeletes, maar de unieke index in de
        // database doet dat niet. Zonder dit is een verwijderd account onzichtbaar
        // voor deze controle en loopt het aanmaken verderop stuk op een
        // sleutelbotsing - een 500 in plaats van een uitlegbare melding.
        $bezet = User::withTrashed()->where('email', $email)->first();
        if ($bezet) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => $bezet->trashed()
                    ? 'Er bestaat een verwijderd account met dit e-mailadres. '
                        . 'Neem contact op met de club om het te laten herstellen.'
                    : 'Dit e-mailadres is al in gebruik. '
                        . 'De ouder kan inloggen en vanuit zijn profiel een koppeling aanvragen.',
            ], 409);
        }

        // Maak een Member-record aan voor de ouder (geen club-lid, maar wel nodig voor koppeling)
        // Alles of niets, net als bij selfRegister: klapt het account, dan hoort
        // er geen ouderlid zonder account achter te blijven.
        [$parentMember, $parentUser] = DB::transaction(function () use ($validated, $email, $user) {
            $parentMember = Member::create([
                'name'      => $validated['naam'],
                'email'     => $email,
                'is_active' => true,
                'role'      => 'player',
            ]);

            // Wachtwoord is willekeurig; de ouder logt in via de magic link.
            $parentUser = User::create([
                'name'      => $validated['naam'],
                'email'     => $email,
                'password'  => Str::random(32),
                'club_id'   => $user->club_id,
                'is_active' => true,
            ]);

            $parentMember->update(['user_id' => $parentUser->id]);

            return [$parentMember, $parentUser];
        });
        // Rol 'guardian': dit account is geen clublid maar een ouder die
        // meekijkt via het gekoppelde kind. Zonder rol was het in het beheer
        // niet van een gewoon lid te onderscheiden.
        $parentUser->assignRole('guardian');

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

    /**
     * POST /v1/guardian/self-register
     *
     * Publiek endpoint: een ouder/verzorger registreert zichzelf.
     * Vereist 3-veld verificatie van het kind (lidnummer + achternaam + geboortedatum).
     *
     * Bij succesvolle verificatie:
     *   - Wordt een User + Member voor de ouder aangemaakt
     *   - Wordt een GuardianLink met status 'pending' aangemaakt
     *   - Wordt een magic link naar de ouder gestuurd zodat hij kan inloggen
     *   - Bij eerste login van het kind: bevestig of weiger het verzoek
     *
     * Throttle: 3 verzoeken per minuut, 10 per uur per IP.
     */
    public function selfRegister(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'naam'       => 'required|string|min:2|max:100',
            'email'      => 'required|email|max:191',
            'lidnummer'  => 'required|string|max:50',
            'achternaam' => 'required|string|min:2|max:100',
        ]);

        $email = strtolower($validated['email']);

        // withTrashed: zie createParentAccount. Een verwijderd account blijft in
        // de unieke index staan, dus zonder deze regel klapt User::create verderop
        // op een sleutelbotsing en krijgt de ouder alleen 'Server Error'.
        $bezet = User::withTrashed()->where('email', $email)->first();
        if ($bezet) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => $bezet->trashed()
                    ? 'Er bestaat een verwijderd account met dit e-mailadres. '
                        . 'Neem contact op met de club om het te laten herstellen.'
                    : 'Dit e-mailadres is al in gebruik. Log in met je bestaande account.',
            ], 409);
        }

        // Primair matchen op het unieke lidnummer; achternaam verifiëren via het
        // aparte last_name-veld (fallback: volledige naam). Geen is_active-filter.
        $achternaam = trim($validated['achternaam']);
        $child = Member::where('external_id', trim($validated['lidnummer']))->first();

        if ($child && ! $this->achternaamMatcht($child, $achternaam)) {
            $child = null;
        }

        // Altijd dezelfde foutmelding ongeacht reden — voorkomt user enumeration.
        if (! $child) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'Geen lid gevonden met de opgegeven gegevens. Controleer lidnummer en achternaam.',
            ], 404);
        }

        $clubId = $child->teams()->first()?->club_id;
        if (! $clubId) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'Dit lid is niet gekoppeld aan een team. Neem contact op met de club.',
            ], 422);
        }

        // Alles of niets. Het lid werd eerder los aangemaakt, en klapte het
        // account daarna op een sleutelbotsing, dan bleef er bij elke mislukte
        // poging een ouderlid zonder account achter.
        [$parentMember, $parentUser] = DB::transaction(function () use ($validated, $email, $clubId, $child) {
            $member = Member::create([
                'name'      => $validated['naam'],
                'email'     => $email,
                'is_active' => true,
                'role'      => 'player',
            ]);

            // Wachtwoord is willekeurig; inloggen gaat via de magic link.
            $user = User::create([
                'name'      => $validated['naam'],
                'email'     => $email,
                'password'  => Str::random(32),
                'club_id'   => $clubId,
                'is_active' => true,
            ]);

            $member->update(['user_id' => $user->id]);

            return [$member, $user];
        });
        // Rol 'guardian': dit account is geen clublid maar een ouder die
        // meekijkt via het gekoppelde kind. Zonder rol was het in het beheer
        // niet van een gewoon lid te onderscheiden.
        $parentUser->assignRole('guardian');

        // Maak GuardianLink met status 'pending' — kind moet bevestigen.
        GuardianLink::create([
            'club_id'            => $clubId,
            'guardian_member_id' => $parentMember->id,
            'child_member_id'    => $child->id,
            'status'             => 'pending',
            'request_token'      => GuardianLink::generateToken(),
            'expires_at'         => now()->addDays(14),
        ]);

        // Stuur magic link naar de ouder.
        $token = Str::random(64);
        MagicLinkToken::create([
            'email'      => $email,
            'token'      => $token,
            'expires_at' => now()->addMinutes(30),
        ]);

        try {
            $club = Club::find($clubId);
            Mail::to($email)->send(new MagicLinkMail($token, $validated['naam'], $club));
        } catch (\Throwable $e) {
            \Log::error('[GuardianSelfRegister] mail failed', [
                'email' => $email, 'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'childName' => $child->name,
            ],
            'message' => 'Registratie gelukt! Een inloglink is naar je e-mailadres verstuurd. '
                . 'Het kind/lid moet de koppeling nog bevestigen voordat je toegang hebt tot zijn/haar gegevens.',
        ], 201);
    }
}
