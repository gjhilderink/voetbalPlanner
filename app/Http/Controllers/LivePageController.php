<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FootballMatch;
use App\Services\LiveMatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * De publieke livepagina: meekijken zonder app en zonder account, via een
 * geheime link die de coach deelt.
 *
 * De link werkt zolang de wedstrijd loopt en tot enkele uren na het
 * eindsignaal; daarna bestaat hij niet meer. Bij elke nieuwe start krijgt de
 * wedstrijd een verse token, zodat een oude link nooit iets nieuws opent.
 */
class LivePageController extends Controller
{
    public function __construct(private readonly LiveMatchService $live)
    {
    }

    /**
     * GET /live/{token}
     *
     * Hier wordt bewust géén meekijker geteld. Zodra de coach de link in de
     * teamapp plakt halen WhatsApp, Signal en Telegram deze pagina op voor hun
     * voorbeeldkaartje. Die bots draaien geen JavaScript en komen dus nooit bij
     * het poll-endpoint - maar ze zouden de teller wel meteen op drie zetten.
     */
    public function show(string $token)
    {
        $match = $this->findMatch($token);
        $state = $this->live->state($match);

        return response()
            ->view('live', [
                'state'    => $state,
                'stateUrl' => route('live.state', ['token' => $token]),
                'club'     => $match->team?->club,
            ])
            // Een livepagina hoort nooit uit de cache te komen.
            ->header('Cache-Control', 'no-store, max-age=0');
    }

    /** GET /live/{token}/state — voor het pollen vanaf de pagina zelf. */
    public function state(Request $request, string $token): JsonResponse
    {
        $match = $this->findMatch($token);
        $this->live->registerViewer($match, $this->viewerKey($request), 'web');

        return response()
            ->json($this->live->state($match))
            ->header('Cache-Control', 'no-store, max-age=0');
    }

    /**
     * Herkenningssleutel van een meekijker zonder account, zodat vijf keer
     * verversen niet als vijf kijkers telt.
     *
     * De sessie-id gehasht, en niet het IP-adres: het IP is een persoonsgegeven
     * en zou hier uren blijven staan voor niets meer dan een getal. Deze pagina
     * valt onder de web-middleware, dus er is altijd een sessie - en die leeft
     * twee uur, ruim langer dan een wedstrijd.
     */
    private function viewerKey(Request $request): string
    {
        // Alleen tellen als de browser het sessiecookie ook echt teruggeeft.
        // Wie cookies blokkeert krijgt bij elk verzoek een verse sessie, en zou
        // dan zes keer per minuut als nieuwe kijker meetellen. Liever iemand
        // niet meetellen dan hem zesvoudig meetellen.
        if (! $request->hasSession()
            || ! $request->cookies->has((string) config('session.cookie'))) {
            return '';
        }

        $id = $request->session()->getId();

        return $id === '' ? '' : 's:' . substr(hash('sha256', $id), 0, 40);
    }

    /**
     * 404 en geen 403 bij een verlopen of onbekende link: of een token ooit
     * bestaan heeft is niets wat een buitenstaander hoeft te weten.
     */
    private function findMatch(string $token): FootballMatch
    {
        $match = FootballMatch::query()
            ->where('live_token', $token)
            ->with(['team.club', 'events.member', 'events.relatedMember'])
            ->first();

        if (! $match || ! $this->live->publicLinkIsOpen($match)) {
            throw new NotFoundHttpException();
        }

        return $match;
    }
}
