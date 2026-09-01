<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AgendaItem;
use App\Models\Club;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

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

        return view('shop.event', [
            'club'       => $club,
            'activiteit' => $activiteit,
            'soorten'    => $activiteit->ticketTypes->where('is_active', true)->sortBy('sort_order'),
            'embed'      => $request->boolean('embed'),
        ]);
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
