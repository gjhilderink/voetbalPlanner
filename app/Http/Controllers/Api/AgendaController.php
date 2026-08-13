<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AgendaCategoryResource;
use App\Http\Resources\AgendaItemResource;
use App\Models\AgendaCategory;
use App\Models\AgendaItem;
use App\Models\AgendaRegistration;
use App\Models\GuardianLink;
use App\Models\Member;
use App\Services\IcsBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Verenigingsagenda voor de app: lijst, detail, aan-/afmelden en .ics-download.
 *
 * Elke query loopt via published() + visibleTo(); daar zit zowel de club-scope
 * als de doelgroepbepaling in. Losse where('club_id')-checks zijn dus niet nodig
 * en zouden alleen maar uit de pas kunnen gaan lopen.
 */
class AgendaController extends Controller
{
    /** Bovengrens op wat één call teruggeeft. */
    private const MAX_LIMIT = 50;

    /** Standaard-venster vooruit als er geen einddatum is meegegeven. */
    private const DEFAULT_DAYS = 120;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $items = AgendaItem::query()
            ->with(['category', 'teams:id,name', 'staffGroups:id,name'])
            ->withCount(['registrations as going_people' => fn ($q) => $q->going()])
            ->withSum(['registrations as going_guests' => fn ($q) => $q->going()], 'guest_count')
            ->published()
            ->visibleTo($user)
            ->when(! $request->boolean('include_past'), fn ($q) => $q->upcoming())
            ->when($request->query('from'), fn ($q, $from) => $q->whereDate('starts_at', '>=', $from))
            ->when($request->query('until'), fn ($q, $until) => $q->whereDate('starts_at', '<=', $until))
            ->when(! $request->query('until'), fn ($q) => $q->whereDate('starts_at', '<=',
                now()->addDays($this->days($request))->toDateString()))
            ->when($request->query('category'), fn ($q, $slug) => $q->whereHas('category',
                fn ($c) => $c->where('slug', $slug)))
            ->when($request->query('category_id'), fn ($q, $id) => $q->where('agenda_category_id', $id))
            ->orderByDesc('is_highlighted')
            ->orderBy('starts_at')
            ->limit($this->limit($request))
            ->get();

        $this->attachMyRegistrations($items, $request);

        return response()->json(
            $items->map(fn (AgendaItem $item) => (new AgendaItemResource($item))->resolve())
        );
    }

    public function categories(Request $request): JsonResponse
    {
        $categories = AgendaCategory::query()
            ->where('club_id', $request->user()->club_id)
            ->activeOrdered()
            ->get();

        return response()->json(
            $categories->map(fn ($c) => (new AgendaCategoryResource($c))->resolve())
        );
    }

    public function show(Request $request, AgendaItem $agendaItem): JsonResponse
    {
        $this->assertVisible($request, $agendaItem);

        $agendaItem->load(['category', 'teams:id,name', 'staffGroups:id,name']);
        $items = collect([$agendaItem]);
        $this->attachMyRegistrations($items, $request);

        return response()->json((new AgendaItemResource($agendaItem))->resolve());
    }

    /**
     * Deelnemerslijst. Alleen als de beheerder die zichtbaar heeft gemaakt —
     * anders zou een lijst met (kinder)namen ongefilterd rondgaan.
     */
    public function participants(Request $request, AgendaItem $agendaItem): JsonResponse
    {
        $this->assertVisible($request, $agendaItem);

        $user = $request->user();
        if (! $agendaItem->show_participants && ! $user->isAdmin()) {
            return response()->json(['message' => 'De deelnemerslijst is niet zichtbaar.'], 403);
        }

        $memberId = $user->resolveMember()?->id;

        $participants = $agendaItem->registrations()
            ->going()
            ->orderBy('name')
            ->get()
            ->map(fn (AgendaRegistration $r) => [
                'name'       => $r->name,
                'guestCount' => (int) $r->guest_count,
                'isMe'       => $r->user_id === $user->id || ($memberId && $r->member_id === $memberId),
            ]);

        return response()->json($participants);
    }

    public function aanmelden(Request $request, AgendaItem $agendaItem): JsonResponse
    {
        $this->assertVisible($request, $agendaItem);
        $user = $request->user();

        $data = $request->validate([
            'member_id'   => 'nullable|uuid|exists:members,id',
            'guest_count' => 'nullable|integer|min:0|max:10',
            'note'        => 'nullable|string|max:255',
        ]);

        if (! $agendaItem->isRegistrationOpen()) {
            return response()->json([
                'success' => false,
                'message' => 'Aanmelden is niet (meer) mogelijk voor deze activiteit.',
            ], 422);
        }

        $memberId = $this->resolveSubjectMember($request, $data['member_id'] ?? null);
        if ($memberId === false) {
            return response()->json([
                'success' => false,
                'message' => 'Je kunt dit lid niet aanmelden.',
            ], 403);
        }

        $guests = $agendaItem->allow_guests ? (int) ($data['guest_count'] ?? 0) : 0;

        $registration = DB::transaction(function () use ($agendaItem, $user, $memberId, $guests, $data) {
            $subjectKey = AgendaRegistration::subjectKey($memberId, $user->id);

            // Capaciteit binnen de transactie tellen, anders kunnen twee
            // gelijktijdige aanmeldingen er samen overheen gaan.
            if ($agendaItem->capacity !== null) {
                $taken = AgendaRegistration::query()
                    ->where('agenda_item_id', $agendaItem->id)
                    ->where('status', AgendaRegistration::STATUS_GOING)
                    ->where('subject_key', '!=', $subjectKey)
                    ->lockForUpdate()
                    ->sum(DB::raw('1 + guest_count'));

                if (($taken + 1 + $guests) > $agendaItem->capacity) {
                    return null;
                }
            }

            return AgendaRegistration::updateOrCreate(
                ['agenda_item_id' => $agendaItem->id, 'subject_key' => $subjectKey],
                [
                    'club_id'       => $agendaItem->club_id,
                    'user_id'       => $user->id,
                    'member_id'     => $memberId,
                    'name'          => $memberId ? (Member::find($memberId)?->name ?? $user->name) : $user->name,
                    'status'        => AgendaRegistration::STATUS_GOING,
                    'guest_count'   => $guests,
                    'note'          => $data['note'] ?? null,
                    'registered_at' => now(),
                ],
            );
        });

        if (! $registration) {
            return response()->json([
                'success' => false,
                'message' => 'De activiteit zit vol.',
            ], 422);
        }

        return $this->itemResponse($request, $agendaItem, 'Je bent aangemeld.');
    }

    public function afmelden(Request $request, AgendaItem $agendaItem): JsonResponse
    {
        $this->assertVisible($request, $agendaItem);
        $user = $request->user();

        $data = $request->validate(['member_id' => 'nullable|uuid|exists:members,id']);

        $memberId = $this->resolveSubjectMember($request, $data['member_id'] ?? null);
        if ($memberId === false) {
            return response()->json([
                'success' => false,
                'message' => 'Je kunt dit lid niet afmelden.',
            ], 403);
        }

        // De rij blijft staan met status 'afgemeld', zodat het beheer ziet wie
        // expliciet heeft afgezegd.
        AgendaRegistration::updateOrCreate(
            [
                'agenda_item_id' => $agendaItem->id,
                'subject_key'    => AgendaRegistration::subjectKey($memberId, $user->id),
            ],
            [
                'club_id'     => $agendaItem->club_id,
                'user_id'     => $user->id,
                'member_id'   => $memberId,
                'name'        => $memberId ? (Member::find($memberId)?->name ?? $user->name) : $user->name,
                'status'      => AgendaRegistration::STATUS_NOT_GOING,
                'guest_count' => 0,
            ],
        );

        return $this->itemResponse($request, $agendaItem, 'Je bent afgemeld.');
    }

    /** Losse activiteit als .ics-bestand voor de eigen kalender. */
    public function ics(Request $request, AgendaItem $agendaItem): StreamedResponse
    {
        $this->assertVisible($request, $agendaItem);

        $ics = app(IcsBuilder::class)->item($agendaItem);

        return response()->streamDownload(
            fn () => print($ics),
            Str::slug($agendaItem->title) . '.ics',
            ['Content-Type' => 'text/calendar; charset=utf-8'],
        );
    }

    // ── Hulpmethodes ───────────────────────────────────────────────────────────

    /**
     * 404 als het item niet bestaat, niet gepubliceerd is of niet voor deze
     * gebruiker bedoeld is. Bewust 404 en geen 403: een 403 verklapt dat het
     * item bestaat.
     */
    private function assertVisible(Request $request, AgendaItem $agendaItem): void
    {
        $visible = AgendaItem::query()
            ->whereKey($agendaItem->id)
            ->published()
            ->visibleTo($request->user())
            ->exists();

        abort_unless($visible, 404);
    }

    /**
     * Voor welk lid geldt de (af)melding? Standaard het eigen lid. Een ander lid
     * mag alleen als er een goedgekeurde ouder/verzorger-koppeling is.
     *
     * @return string|null|false  false = niet toegestaan
     */
    private function resolveSubjectMember(Request $request, ?string $requestedMemberId): string|null|false
    {
        $ownMemberId = $request->user()->resolveMember()?->id;

        if (! $requestedMemberId || $requestedMemberId === $ownMemberId) {
            return $requestedMemberId ?: $ownMemberId;
        }

        if (! $ownMemberId) {
            return false;
        }

        $allowed = GuardianLink::query()
            ->where('guardian_member_id', $ownMemberId)
            ->where('child_member_id', $requestedMemberId)
            ->where('status', 'approved')
            ->exists();

        return $allowed ? $requestedMemberId : false;
    }

    /** Eén extra query voor "ben ik aangemeld?", ongeacht het aantal items. */
    private function attachMyRegistrations(\Illuminate\Support\Collection $items, Request $request): void
    {
        if ($items->isEmpty()) {
            return;
        }

        $user     = $request->user();
        $memberId = $user->resolveMember()?->id;

        $registrations = AgendaRegistration::query()
            ->whereIn('agenda_item_id', $items->pluck('id'))
            ->where(fn ($q) => $q
                ->where('user_id', $user->id)
                ->when($memberId, fn ($x) => $x->orWhere('member_id', $memberId)))
            ->get()
            ->keyBy('agenda_item_id');

        foreach ($items as $item) {
            $item->setRelation('myRegistration', $registrations->get($item->id));
        }
    }

    /** Verse staat van één item teruggeven na een (af)melding. */
    private function itemResponse(Request $request, AgendaItem $agendaItem, string $message): JsonResponse
    {
        $fresh = AgendaItem::query()
            ->with(['category', 'teams:id,name', 'staffGroups:id,name'])
            ->withCount(['registrations as going_people' => fn ($q) => $q->going()])
            ->withSum(['registrations as going_guests' => fn ($q) => $q->going()], 'guest_count')
            ->findOrFail($agendaItem->id);

        $items = collect([$fresh]);
        $this->attachMyRegistrations($items, $request);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => (new AgendaItemResource($fresh))->resolve(),
        ]);
    }

    private function limit(Request $request): int
    {
        return min(self::MAX_LIMIT, max(1, (int) $request->query('limit', self::MAX_LIMIT)));
    }

    private function days(Request $request): int
    {
        return min(365, max(1, (int) $request->query('days', self::DEFAULT_DAYS)));
    }
}
