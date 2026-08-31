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

        $rijen = [];

        foreach ($this->personen($request) as $index => $lid) {
            // Per persoon opnieuw: een ouder hoort bij een andere club dan zijn
            // kind kan horen, en wie zelf nergens in een elftal zit krijgt geen
            // kleding. Zo houdt een ouder die alleen ouder is wél de secties van
            // zijn kinderen, maar geen eigen rijtje - hij krijgt niets van de club.
            $stukken = $this->kledingstukken($lid);

            if ($stukken->isEmpty()) {
                continue;
            }

            $gekozen = MemberClothingSize::query()
                ->where('member_id', $lid->id)
                ->with('size')
                ->get()
                ->keyBy('clothing_item_id');

            $ingevuld = $stukken->filter(fn (ClothingItem $s) => $gekozen->has($s->id))->count();

            foreach ($stukken as $positie => $stuk) {
                $rij  = $gekozen->get($stuk->id);
                $maat = $rij?->size;

                $rijen[] = [
                    'memberId'   => (string) $lid->id,
                    'memberName' => (string) $lid->name,
                    // Leeg bij jezelf; de app toont die naam bij de regel.
                    'ownerLabel' => $index === 0 ? '' : (string) $lid->name,
                    // Kop van de uitklap. De app groepeert niet zelf: hij toont
                    // een kop bij de eerste regel van een persoon, en dat is
                    // precies wat 'isFirstOfOwner' hier zegt.
                    'ownerKey'      => (string) $lid->id,
                    'ownerTitle'    => $index === 0 ? 'Mijn kleding' : (string) $lid->name,
                    'ownerSummary'  => $ingevuld . ' van ' . $stukken->count() . ' ingevuld',
                    'isFirstOfOwner' => $positie === 0 ? 'true' : 'false',
                    'itemId'     => (string) $stuk->id,
                    'itemName'   => (string) $stuk->name,
                    'sizeId'     => (string) ($maat->id ?? ''),
                    'sizeLabel'  => (string) ($maat->label ?? ''),
                    // Het nummer op dit kledingstuk. Leeg als er geen is; de app
                    // toont dan niets in plaats van een nul.
                    'number'     => (string) ($rij?->number ?? ''),
                    'melding'    => '',
                ];
            }
        }

        if (! $rijen) {
            return response()->json([self::leeg(
                'Er is voor jou geen kleding in te vullen. Hoor je wel bij een elftal? Neem dan contact op met de club.'
            )]);
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

        // Van iedereen voor wie je mag invullen: een ouder zonder eigen elftal
        // heeft anders geen enkele maat om uit te kiezen voor zijn kind.
        $stukIds = $this->personen($request)
            ->flatMap(fn (Member $lid) => $this->kledingstukken($lid)->pluck('id'))
            ->unique();

        $maten = ClothingSize::query()
            ->whereIn('clothing_item_id', $stukIds)
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
     * POST /v1/profile/clothing/number?member_id=&item_id=&number=
     *
     * Een eigen weg naast het kiezen van een maat, en niet een extra parameter
     * daarop: bij die aanroep betekent een lege maat "haal de opgave weg", en
     * dan zou een leeg nummer meesturen de hele regel wissen.
     *
     * Leeg nummer = het nummer weghalen, de maat blijft staan.
     */
    public function setNumber(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'member_id' => ['required', 'uuid'],
            'item_id'   => ['required', 'uuid'],
            'number'    => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $lid = $this->personen($request)->firstWhere('id', $validated['member_id']);

        if (! $lid) {
            return response()->json([
                'success' => false,
                'message' => 'Je kunt het nummer van dit lid niet aanpassen.',
            ], 403);
        }

        $stuk = $this->kledingstukken($lid)->firstWhere('id', $validated['item_id']);

        if (! $stuk) {
            return response()->json([
                'success' => false,
                'message' => 'Dit kledingstuk bestaat niet (meer).',
            ], 422);
        }

        $rij = MemberClothingSize::where('member_id', $lid->id)
            ->where('clothing_item_id', $stuk->id)
            ->first();

        // Zonder maat is er geen regel om een nummer aan te hangen. Er zelf een
        // aanmaken kan niet: clothing_size_id is verplicht, en een maat gokken
        // is erger dan om de maat vragen.
        if (! $rij) {
            return response()->json([
                'success' => false,
                'message' => "Kies eerst een maat voor {$stuk->name}.",
            ], 422);
        }

        $nummer = $validated['number'] ?? null;

        $rij->forceFill([
            'number'             => $nummer,
            'updated_by_user_id' => $request->user()?->id,
        ])->save();

        $voor = $lid->id === $request->user()?->resolveMember()?->id
            ? ''
            : $lid->name . ' — ';

        return response()->json([
            'success' => true,
            'message' => $nummer === null
                ? "{$voor}{$stuk->name}: nummer weggehaald."
                : "{$voor}{$stuk->name}: nummer {$nummer}.",
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
            'memberId'       => '',
            'memberName'     => '',
            'ownerLabel'     => '',
            'ownerKey'       => '',
            'ownerTitle'     => '',
            'ownerSummary'   => '',
            'isFirstOfOwner' => 'false',
            'itemId'         => '',
            'itemName'       => '',
            'sizeId'         => '',
            'sizeLabel'      => '',
            'melding'        => $melding,
        ];
    }

    /** @return array<string, string> */
    private static function leegMaat(string $melding): array
    {
        return ['id' => '', 'itemId' => '', 'label' => '', 'melding' => $melding];
    }
}
