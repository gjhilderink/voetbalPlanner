<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClothingItem;
use App\Models\ClothingSize;
use App\Models\GuardianLink;
use App\Models\Member;
use App\Models\MemberClothingSize;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Kledingmaten in de app.
 *
 * Een lid geeft zijn eigen maten op; een ouder doet dat voor zijn kinderen. Dat
 * laatste is dezelfde regel als bij af- en aanmelden: een goedgekeurde
 * koppeling, niets minder.
 *
 * De lijst bevat de regels van iedereen voor wie je mag invullen, met de naam
 * erbij zodra het niet over jezelf gaat. Zo heeft de app geen keuzemenu nodig om
 * te bepalen wiens maat je invult — bij één kind zou dat een extra tik zijn voor
 * een keuze die er niet is.
 */
class ClothingController extends Controller
{
    /** GET /v1/profile/clothing */
    public function index(Request $request): JsonResponse
    {
        $eigen = $request->user()?->resolveMember();

        if (! $eigen) {
            return response()->json([self::leeg('Je account is nog niet aan een lid gekoppeld.')]);
        }

        $stukken = $this->kledingstukken($eigen);

        if ($stukken->isEmpty()) {
            return response()->json([self::leeg('Je club heeft nog geen kleding ingesteld.')]);
        }

        $rijen = [];

        foreach ($this->personen($request) as $index => $lid) {
            $gekozen = MemberClothingSize::query()
                ->where('member_id', $lid->id)
                ->with('size')
                ->get()
                ->keyBy('clothing_item_id');

            foreach ($stukken as $stuk) {
                $maat = $gekozen->get($stuk->id)?->size;

                $rijen[] = [
                    'memberId'   => (string) $lid->id,
                    'memberName' => (string) $lid->name,
                    // Leeg bij jezelf. De app zet deze naam boven de regel, zodat
                    // een ouder ziet van wie de maat is zonder ernaar te zoeken.
                    'ownerLabel' => $index === 0 ? '' : (string) $lid->name,
                    'itemId'     => (string) $stuk->id,
                    'itemName'   => (string) $stuk->name,
                    'sizeId'     => (string) ($maat->id ?? ''),
                    'sizeLabel'  => (string) ($maat->label ?? ''),
                    'melding'    => '',
                ];
            }
        }

        return response()->json($rijen);
    }

    /** GET /v1/clothing/sizes */
    public function sizes(Request $request): JsonResponse
    {
        $eigen = $request->user()?->resolveMember();

        if (! $eigen) {
            return response()->json([self::leegMaat('Je account is nog niet aan een lid gekoppeld.')]);
        }

        $maten = ClothingSize::query()
            ->whereIn('clothing_item_id', $this->kledingstukken($eigen)->pluck('id'))
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        if ($maten->isEmpty()) {
            return response()->json([self::leegMaat('Er zijn nog geen maten ingesteld.')]);
        }

        return response()->json($maten->map(fn (ClothingSize $maat) => [
            'id'      => (string) $maat->id,
            'itemId'  => (string) $maat->clothing_item_id,
            'label'   => (string) $maat->label,
            'melding' => '',
        ])->all());
    }

    /** POST /v1/profile/clothing?member_id=&item_id=&size_id= */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'member_id' => ['required', 'uuid'],
            'item_id'   => ['required', 'uuid'],
            'size_id'   => ['nullable', 'uuid'],
        ]);

        $lid = $this->personen($request)->firstWhere('id', $validated['member_id']);

        if (! $lid) {
            return response()->json([
                'success' => false,
                'message' => 'Je kunt de maat van dit lid niet aanpassen.',
            ], 403);
        }

        // Het kledingstuk moet van de club van dit lid zijn; anders kun je met een
        // gegokt id een maat bij een andere club wegschrijven.
        $stuk = $this->kledingstukken($lid)->firstWhere('id', $validated['item_id']);

        if (! $stuk) {
            return response()->json([
                'success' => false,
                'message' => 'Dit kledingstuk bestaat niet (meer).',
            ], 422);
        }

        // Leeg = de opgave weer weghalen.
        if (empty($validated['size_id'])) {
            MemberClothingSize::where('member_id', $lid->id)
                ->where('clothing_item_id', $stuk->id)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => "{$stuk->name}: maat weggehaald.",
            ]);
        }

        $maat = ClothingSize::where('id', $validated['size_id'])
            ->where('clothing_item_id', $stuk->id)
            ->first();

        if (! $maat) {
            return response()->json([
                'success' => false,
                'message' => 'Deze maat hoort niet bij dit kledingstuk.',
            ], 422);
        }

        MemberClothingSize::updateOrCreate(
            ['member_id' => $lid->id, 'clothing_item_id' => $stuk->id],
            [
                'clothing_size_id'   => $maat->id,
                'updated_by_user_id' => $request->user()?->id,
            ],
        );

        // De naam erbij zodra het niet over jezelf gaat, net als bij af- en
        // aanmelden: "Sterre - Shirt: M" is een bevestiging, "Shirt: M" een raadsel.
        $voor = $lid->id === $request->user()?->resolveMember()?->id
            ? ''
            : $lid->name . ' — ';

        return response()->json([
            'success' => true,
            'message' => "{$voor}{$stuk->name}: {$maat->label}.",
        ]);
    }

    /**
     * Voor wie mag deze gebruiker een maat invullen? Jezelf eerst, daarna je
     * goedgekeurde kinderen — die volgorde bepaalt welke regels zonder naam
     * worden getoond.
     *
     * @return Collection<int, Member>
     */
    private function personen(Request $request): Collection
    {
        $eigen = $request->user()?->resolveMember();

        if (! $eigen) {
            return collect();
        }

        $kindIds = GuardianLink::query()
            ->where('guardian_member_id', $eigen->id)
            ->where('status', 'approved')
            ->pluck('child_member_id');

        $kinderen = $kindIds->isEmpty()
            ? collect()
            : Member::whereIn('id', $kindIds)->orderBy('name')->get();

        return collect([$eigen])->concat($kinderen)->unique('id')->values();
    }

    /**
     * De kledingstukken van de club waar dit lid bij hoort.
     *
     * Een lid heeft geen club_id; die loopt via zijn elftallen. Hoort hij
     * nergens bij, dan valt er ook niets te kiezen.
     *
     * @return Collection<int, ClothingItem>
     */
    private function kledingstukken(Member $lid): Collection
    {
        static $cache = [];

        $clubId = $lid->teams->first()?->club_id;

        if (! $clubId) {
            return collect();
        }

        return $cache[$clubId] ??= ClothingItem::query()
            ->where('club_id', $clubId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /** @return array<string, string> */
    private static function leeg(string $melding): array
    {
        return [
            'memberId'   => '',
            'memberName' => '',
            'ownerLabel' => '',
            'itemId'     => '',
            'itemName'   => '',
            'sizeId'     => '',
            'sizeLabel'  => '',
            'melding'    => $melding,
        ];
    }

    /** @return array<string, string> */
    private static function leegMaat(string $melding): array
    {
        return ['id' => '', 'itemId' => '', 'label' => '', 'melding' => $melding];
    }
}
