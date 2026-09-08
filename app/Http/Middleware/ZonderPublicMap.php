<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Eén adres voor de site: zonder /public erin.
 *
 * Op deze hosting is de map public/ ook los te bereiken, dus
 * voetbalplanner.nl/public/bon-boys/ticketshop geeft dezelfde pagina als
 * voetbalplanner.nl/bon-boys/ticketshop. Dat is niet onschuldig: Laravel ziet
 * /public dan als de basis van de site en zet het onder élke gemaakte link, dus
 * wie op zo'n pagina binnenkomt blijft er de rest van zijn bezoek in hangen. Een
 * bezoeker die de winkel zo doorstuurt geeft die /public gewoon door.
 *
 * Vandaar één keer omleiden naar hetzelfde adres zonder /public. Het domein
 * blijft staan zoals de bezoeker het intypte: de portal draait ook op
 * voetbalplanner.nubix.nl, en die hoort niet ineens op een ander domein uit te
 * komen.
 */
class ZonderPublicMap
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! str_ends_with($request->getBaseUrl(), '/public')) {
            return $next($request);
        }

        // Alleen omleiden bij het ophalen van een pagina. Een POST die je
        // omleidt komt als GET terug aan en verliest onderweg zijn inhoud - de
        // betaalmelding van Pay.nl bijvoorbeeld. Die handelen we gewoon af; de
        // links in het antwoord kloppen dan alsnog.
        if (! $request->isMethodSafe()) {
            URL::forceRootUrl($request->getSchemeAndHttpHost());

            return $next($request);
        }

        $query = $request->getQueryString();

        return redirect()->to(
            $request->getSchemeAndHttpHost() . $request->getPathInfo() . ($query === null ? '' : '?' . $query),
            301,
        );
    }
}
