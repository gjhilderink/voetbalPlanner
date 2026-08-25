<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Services\SportlinkMcpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * De poulestand van een elftal, opgehaald bij de MCP-server.
 *
 * Niet opgeslagen in een eigen tabel: een stand is puur weergave en verandert
 * per speelronde. Een tabel zou synchronisatie, een migratie en een tweede
 * waarheid opleveren voor iets wat je net zo goed kunt opvragen. Wel een korte
 * cache, want dertig ouders die zondagavond hetzelfde scherm openen hoeven de
 * MCP niet dertig keer te bevragen.
 */
class StandingController extends Controller
{
    /** Zo lang blijft een opgehaalde stand goed. */
    private const CACHE_MINUTEN = 15;

    public function __construct(private readonly SportlinkMcpService $mcp)
    {
    }

    /** GET /v1/teams/{team}/standing */
    public function show(Request $request, Team $team): JsonResponse
    {
        // Alleen elftallen waar je bij hoort; een stand is niet geheim, maar er
        // is ook geen reden om de hele competitie van een vreemde club te openen.
        $mag = $request->user()?->accessibleTeams()->contains('id', $team->id) ?? false;
        if (! $mag) {
            return response()->json(self::alleenMelding('Je hebt geen toegang tot dit elftal.'), 403);
        }

        $teamCode = (string) ($team->external_id ?? '');
        if ($teamCode === '') {
            return response()->json(
                self::alleenMelding('Voor dit elftal is geen competitiekoppeling bekend.')
            );
        }

        $service = $this->mcp->forClub($team->club_id);
        if (! $service->isConfigured()) {
            return response()->json(
                self::alleenMelding('De competitiekoppeling is niet ingesteld.')
            );
        }

        // Onderscheid tussen "de koppeling kent geen stand" en "er is nog geen
        // stand": bij het eerste zoek je in de verkeerde hoek naar de oorzaak.
        if (! $service->hasStandingTool()) {
            return response()->json(
                self::alleenMelding('De koppeling levert geen standen aan.')
            );
        }

        try {
            $ruw = Cache::remember(
                'standing_' . $team->id,
                now()->addMinutes(self::CACHE_MINUTEN),
                // Naam erbij: dient als terugval wanneer de opgeslagen teamcode
                // bij een competitie zonder poule hoort. Zie standingForTeam().
                fn () => $service->standingForTeam($teamCode, $team->name),
            );
        } catch (\Throwable $e) {
            report($e);

            return response()->json(self::alleenMelding('De stand kon niet worden opgehaald.'));
        }

        $rijen = collect($ruw)
            ->filter(fn ($r) => is_array($r))
            ->map(fn (array $r) => $this->rij($r, $team->name) + ['melding' => ''])
            ->values()
            ->all();

        if (! $rijen) {
            return response()->json(
                self::alleenMelding('Er is nog geen stand beschikbaar voor dit elftal.')
            );
        }

        return response()->json($rijen);
    }

    /**
     * Eén lege regel die alleen een melding draagt.
     *
     * De app leest de melding van de eerste regel, net als bij de opkomstlijsten:
     * een platte array is wat elk ander lijst-endpoint hier teruggeeft, en een
     * omhullend object zou de app dwingen tot JSON-pad-gedoe rond een structlijst.
     *
     * @return array<int, array<string, string>>
     */
    private static function alleenMelding(string $melding): array
    {
        return [[
            'positie'     => '',
            'team'        => '',
            'gespeeld'    => '',
            'punten'      => '',
            'doelsaldo'   => '',
            'voor'        => '',
            'tegen'       => '',
            'isEigenTeam' => 'false',
            'melding'     => $melding,
        ]];
    }

    /**
     * Eén regel van de stand, met de veldnamen die de app verwacht.
     *
     * De MCP-sleutels wisselen per bron, dus per waarde een lijstje kandidaten.
     * Alles als string: de app-structs typeren zo.
     *
     * @param  array<string, mixed>  $r
     * @return array<string, string>
     */
    private function rij(array $r, string $eigenTeam): array
    {
        $pak = function (array $sleutels) use ($r): string {
            foreach ($sleutels as $s) {
                if (isset($r[$s]) && $r[$s] !== '' && is_scalar($r[$s])) {
                    return (string) $r[$s];
                }
            }
            return '';
        };

        $naam = $pak(['teamnaam', 'team', 'naam', 'ploeg']);

        return [
            'positie'      => $pak(['positie', 'plaats', 'stand', 'rank', 'nr']),
            'team'         => $naam,
            'gespeeld'     => $pak(['gespeeld', 'wedstrijden', 'aantalwedstrijden', 'gs']),
            'punten'       => $pak(['punten', 'ptn', 'pt']),
            'doelsaldo'    => $pak(['doelsaldo', 'saldo', 'ds']),
            'voor'         => $pak(['doelpuntenvoor', 'voor', 'dv']),
            'tegen'        => $pak(['doelpuntentegen', 'tegen', 'dt']),
            // Zodat de app de eigen ploeg kan uitlichten zonder namen te
            // vergelijken; dat gaat mis zodra de schrijfwijze net afwijkt.
            'isEigenTeam'  => $this->zelfdeTeam($naam, $eigenTeam) ? 'true' : 'false',
        ];
    }

    /** Losjes vergelijken: hoofdletters en spaties verschillen nogal eens. */
    private function zelfdeTeam(string $a, string $b): bool
    {
        $normaliseer = fn (string $s) => preg_replace('/[^a-z0-9]/', '', mb_strtolower($s));

        return $a !== '' && $normaliseer($a) === $normaliseer($b);
    }
}
