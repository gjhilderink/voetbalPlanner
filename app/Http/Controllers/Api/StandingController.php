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
            return response()->json([
                'rijen'   => [],
                'melding' => 'Je hebt geen toegang tot dit elftal.',
            ], 403);
        }

        $teamCode = (string) ($team->external_id ?? '');
        if ($teamCode === '') {
            return response()->json([
                'rijen'   => [],
                'melding' => 'Voor dit elftal is geen competitiekoppeling bekend.',
            ]);
        }

        $service = $this->mcp->forClub($team->club_id);
        if (! $service->isConfigured()) {
            return response()->json([
                'rijen'   => [],
                'melding' => 'De competitiekoppeling is niet ingesteld.',
            ]);
        }

        try {
            $ruw = Cache::remember(
                'standing_' . $team->id,
                now()->addMinutes(self::CACHE_MINUTEN),
                fn () => $service->standingForTeam($teamCode),
            );
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'rijen'   => [],
                'melding' => 'De stand kon niet worden opgehaald.',
            ]);
        }

        $rijen = collect($ruw)
            ->filter(fn ($r) => is_array($r))
            ->map(fn (array $r) => $this->rij($r, $team->name))
            ->values()
            ->all();

        return response()->json([
            'rijen'   => $rijen,
            'melding' => $rijen ? '' : 'Er is nog geen stand beschikbaar voor dit elftal.',
        ]);
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
