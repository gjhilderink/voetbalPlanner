<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\VerifiesRecaptcha;
use App\Models\AgendaItem;
use App\Models\Club;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\PayNlService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * De publieke ticketshop op voetbalplanner.nl/{clubslug}/ticketshop.
 *
 * Geen login, geen sessie. Dat laatste is geen toeval: de winkel moet in een
 * iframe op de eigen website van een club kunnen draaien, en een sessiecookie
 * met SameSite=lax komt daar niet mee. Alles wat deze pagina's nodig hebben zit
 * in de URL.
 */
class TicketShopController extends Controller
{
    use VerifiesRecaptcha;

    /**
     * Eerste padsegmenten die al iets anders betekenen.
     *
     * De route matcht alleen op twee segmenten die eindigen op 'ticketshop',
     * dus botsen is onwaarschijnlijk - maar een club die zich 'admin' noemt
     * hoort geen winkel op /admin/ticketshop te krijgen.
     *
     * @var array<int, string>
     */
    private const VERBODEN_SLUGS = [
        'admin', 'api', 'live', 'magic', 'wedstrijd', 'aanmelden', 'demo',
        'privacy', 'tarieven', 'huisstijl', 'login', 'dashboard', 'up',
        'storage', 'build', 'impersonate',
    ];

    /** De winkel: welke activiteiten zijn er kaarten voor? */
    public function show(Request $request, string $clubslug): View
    {
        $club = $this->club($clubslug);

        $activiteiten = $this->teKoop($club);

        return view('shop.index', [
            'club'         => $club,
            'activiteiten' => $activiteiten,
            'embed'        => $request->boolean('embed'),
        ]);
    }

    /** Eén activiteit, met de kaartsoorten en het bestelformulier. */
    public function event(Request $request, string $clubslug, string $event): View
    {
        $club = $this->club($clubslug);

        $activiteit = $this->teKoop($club)->firstWhere('id', $event);

        abort_if($activiteit === null, 404);

        return view('shop.event', $this->eventGegevens($club, $activiteit, $request->boolean('embed')));
    }

    /**
     * Afrekenen: kaarten vastleggen en doorsturen naar de betaalpagina.
     *
     * Deze route staat in de CSRF-uitzondering, want de winkel draait zonder
     * sessie zodat hij ook in een iframe op een ander domein werkt. Wat er
     * overblijft aan beveiliging: throttling op de route, reCAPTCHA als de
     * beheerder dat aan heeft staan, en het feit dat er niets onomkeerbaars
     * gebeurt - het ergste is een onbetaalde bestelling die vanzelf verloopt.
     */
    public function checkout(Request $request, string $clubslug, string $event, OrderService $orders): RedirectResponse|View
    {
        $club       = $this->club($clubslug);
        $activiteit = $this->teKoop($club)->firstWhere('id', $event);

        abort_if($activiteit === null, 404);

        $embed = $request->boolean('embed');

        if (! $this->recaptchaGoedgekeurd($request)) {
            return $this->terugMetFouten($club, $activiteit, $embed, $request,
                ['Bevestig even dat je geen robot bent.']);
        }

        $regels = Validator::make($request->all(), [
            'buyer_name'  => ['required', 'string', 'max:150'],
            'buyer_email' => ['required', 'email', 'max:190'],
            'aantal'      => ['required', 'array'],
            'aantal.*'    => ['integer', 'min:0', 'max:50'],
        ], [
            'buyer_name.required'  => 'Vul je naam in.',
            'buyer_email.required' => 'Vul je e-mailadres in.',
            'buyer_email.email'    => 'Dat e-mailadres klopt niet.',
        ]);

        if ($regels->fails()) {
            return $this->terugMetFouten($club, $activiteit, $embed, $request,
                $regels->errors()->all());
        }

        $gegevens = $regels->validated();

        $uitkomst = $orders->maakBestelling(
            $club,
            $activiteit,
            $gegevens['aantal'],
            trim($gegevens['buyer_name']),
            strtolower(trim($gegevens['buyer_email'])),
        );

        if (! $uitkomst['ok']) {
            return $this->terugMetFouten($club, $activiteit, $embed, $request,
                $uitkomst['fouten'] ?? ['Er ging iets mis.']);
        }

        /** @var Order $order */
        $order = $uitkomst['order'];

        $klaarUrl = url("/{$club->slug}/ticketshop/klaar/{$order->public_token}"
            . ($embed ? '?embed=1' : ''));

        $betaling = app(PayNlService::class)->forClub($club->id)->start(
            $order,
            $klaarUrl,
            url('/api/v1/paynl/exchange'),
        );

        if (! ($betaling['ok'] ?? false)) {
            // De reservering meteen loslaten: laten staan zou voorraad
            // vasthouden voor een betaling die nooit begonnen is.
            $orders->mislukt($order);

            return $this->terugMetFouten($club, $activiteit, $embed, $request,
                [$betaling['error'] ?? 'De betaling kon niet worden gestart.']);
        }

        $order->update(['paynl_transaction_id' => $betaling['transactionId']]);

        return redirect()->away($betaling['paymentUrl']);
    }

    /**
     * Terug uit de betaling.
     *
     * De stand wordt bij Pay.nl opgehaald en niet uit de URL gelezen: wat daar
     * staat kan de bezoeker zelf verzinnen.
     */
    public function klaar(Request $request, string $clubslug, string $token, OrderService $orders): View
    {
        $club = $this->club($clubslug);

        $order = Order::query()
            ->where('club_id', $club->id)
            ->where('public_token', $token)
            ->with(['lines', 'agendaItem', 'accessCodes'])
            ->first();

        abort_if($order === null, 404);

        if (! $order->isBetaald() && $order->paynl_transaction_id) {
            $stand = app(PayNlService::class)->forClub($club->id)->status($order->paynl_transaction_id);

            if ($stand['ok'] ?? false) {
                if ($stand['betaald'] ?? false) {
                    $orders->afronden($order);
                } elseif ($stand['mislukt'] ?? false) {
                    $orders->mislukt($order);
                }

                $order->refresh()->load('accessCodes');
            }
        }

        return view('shop.klaar', [
            'club'  => $club,
            'order' => $order,
            'embed' => $request->boolean('embed'),
        ]);
    }

    /**
     * De gegevens voor het bestelformulier, met wat er eventueel al is ingevuld.
     *
     * Fouten en ingevulde waarden gaan als gewone variabelen mee en niet via de
     * sessie. Zonder sessie werkt old() niet, en juist in een iframe op een
     * ander domein is er geen sessie.
     *
     * @param  array<int, string>  $fouten
     * @param  array<string, mixed>  $ingevuld
     * @return array<string, mixed>
     */
    private function eventGegevens(
        Club $club,
        AgendaItem $activiteit,
        bool $embed,
        array $fouten = [],
        array $ingevuld = [],
    ): array {
        return [
            'club'             => $club,
            'activiteit'       => $activiteit,
            'soorten'          => $activiteit->ticketTypes->where('is_active', true)->sortBy('sort_order'),
            'embed'            => $embed,
            'fouten'           => $fouten,
            'ingevuld'         => $ingevuld,
            'recaptchaEnabled' => $this->recaptchaIngeschakeld(),
            'recaptchaSiteKey' => $this->recaptchaSleutel(),
        ];
    }

    /** @param  array<int, string>  $fouten */
    private function terugMetFouten(
        Club $club,
        AgendaItem $activiteit,
        bool $embed,
        Request $request,
        array $fouten,
    ): View {
        return view('shop.event', $this->eventGegevens($club, $activiteit, $embed, $fouten, [
            'buyer_name'  => (string) $request->input('buyer_name', ''),
            'buyer_email' => (string) $request->input('buyer_email', ''),
            'aantal'      => (array) $request->input('aantal', []),
        ]));
    }

    /**
     * De club achter de slug, of een 404.
     *
     * Ook 404 als de club de ticketshop niet gebruikt: een winkel die niet
     * bestaat hoort niet te verraden dat de club wél bestaat.
     */
    private function club(string $clubslug): Club
    {
        abort_if(in_array(strtolower($clubslug), self::VERBODEN_SLUGS, true), 404);

        $club = Club::query()
            ->where('slug', $clubslug)
            ->where('is_active', true)
            ->where('ticketshop_enabled', true)
            ->first();

        abort_if($club === null, 404);

        return $club;
    }

    /**
     * De activiteiten waar op dit moment kaarten voor zijn.
     *
     * Een eigen leesweg, want AgendaItem::scopeVisibleTo gaat uit van een
     * ingelogde gebruiker en geeft aan een bezoeker niets terug. De regels hier
     * zijn simpeler en expres krap: gepubliceerd, nog niet voorbij, en er is
     * minstens één kaartsoort te koop.
     *
     * @return \Illuminate\Support\Collection<int, AgendaItem>
     */
    private function teKoop(Club $club)
    {
        return AgendaItem::query()
            ->where('club_id', $club->id)
            ->published()
            ->where(function ($q) {
                // Een activiteit die vandaag nog bezig is telt mee; wat gisteren
                // afliep niet.
                $q->where('starts_at', '>=', now()->startOfDay())
                    ->orWhere('ends_at', '>=', now());
            })
            ->whereHas('ticketTypes', fn ($q) => $q->where('is_active', true))
            ->with(['ticketTypes' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->orderBy('starts_at')
            ->get();
    }
}
