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

    /** GET /live/{token} */
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

        return response()
            ->json($this->live->state($match))
            ->header('Cache-Control', 'no-store, max-age=0');
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
