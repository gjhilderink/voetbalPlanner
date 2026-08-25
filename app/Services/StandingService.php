<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Team;
use Illuminate\Support\Facades\Cache;

/**
 * De poulestand van een elftal, opgehaald bij de MCP en teruggebracht tot vaste
 * veldnamen.
 *
 * Eigen klasse en niet in de controller, omdat twee schermen dezelfde gegevens
 * gebruiken: de standpagina toont alle regels, het dashboard alleen die van het
 * eigen elftal. De sleutelnamen van de bron horen op één plek te staan.
 *
 * Niet opgeslagen in een tabel: een stand is puur weergave en verandert per
 * speelronde. Wel kort gecachet — dertig ouders die zondagavond hetzelfde scherm
 * openen hoeven de MCP niet dertig keer te bevragen.
 */
class StandingService
{
    private const CACHE_MINUTEN = 15;

    public function __construct(private readonly SportlinkMcpService $mcp)
    {
    }

    /**
     * Alle regels van de poule, of een lege lijst met een reden.
     *
     * @return array{rijen: array<int, array<string, string>>, melding: string}
     */
    public function forTeam(Team $team): array
    {
        $teamCode = (string) ($team->external_id ?? '');
        if ($teamCode === '') {
            return $this->leeg('Voor dit elftal is geen competitiekoppeling bekend.');
        }

        $service = $this->mcp->forClub($team->club_id);
        if (! $service->isConfigured()) {
            return $this->leeg('De competitiekoppeling is niet ingesteld.');
        }

        // Onderscheid tussen "de koppeling kent geen stand" en "er is nog geen
        // stand": bij het eerste zoek je in de verkeerde hoek naar de oorzaak.
        if (! $service->hasStandingTool()) {
            return $this->leeg('De koppeling levert geen standen aan.');
        }

        try {
            $ruw = Cache::remember(
                'standing_' . $team->id,
                now()->addMinutes(self::CACHE_MINUTEN),
                // Naam erbij: terugval wanneer de opgeslagen teamcode bij een
                // competitie zonder poule hoort. Zie standingForTeam().
                fn () => $service->standingForTeam($teamCode, $team->name),
            );
        } catch (\Throwable $e) {
            report($e);

            return $this->leeg('De stand kon niet worden opgehaald.');
        }

        $rijen = collect($ruw)
            ->filter(fn ($r) => is_array($r))
            ->map(fn (array $r) => $this->rij($r, $team->name))
            ->values()
            ->all();

        return $rijen
            ? ['rijen' => $rijen, 'melding' => '']
            : $this->leeg('Er is nog geen stand beschikbaar voor dit elftal.');
    }

    /** De regel van het eigen elftal, of null als de stand er niet is. */
    public function ownRow(Team $team): ?array
    {
        foreach ($this->forTeam($team)['rijen'] as $rij) {
            if (($rij['isEigenTeam'] ?? 'false') === 'true') {
                return $rij;
            }
        }

        return null;
    }

    /** @return array{rijen: array<int, array<string, string>>, melding: string} */
    private function leeg(string $melding): array
    {
        return ['rijen' => [], 'melding' => $melding];
    }

    /**
     * Eén regel met de veldnamen die de app verwacht.
     *
     * De sleutels van de bron wisselen, dus per waarde een lijstje kandidaten.
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

        // De bron markeert de eigen ploeg zelf; die vlag wint van een
        // naamsvergelijking, want die gaat mis zodra de schrijfwijze afwijkt.
        $eigen = isset($r['eigenteam'])
            ? filter_var($r['eigenteam'], FILTER_VALIDATE_BOOLEAN)
            : $this->zelfdeTeam($naam, $eigenTeam);

        return [
            'positie'   => $pak(['positie', 'plaats', 'stand', 'rank', 'nr']),
            'team'      => $naam,
            'logo'      => $pak(['clublogo', 'teamlogo', 'logo']),
            'gespeeld'  => $pak(['gespeeldewedstrijden', 'gespeeld', 'wedstrijden', 'aantalwedstrijden', 'gs']),
            'gewonnen'  => $pak(['gewonnen', 'winst', 'w']),
            'gelijk'    => $pak(['gelijk', 'gelijkspel', 'g']),
            'verloren'  => $pak(['verloren', 'verlies', 'v']),
            'punten'    => $pak(['punten', 'ptn', 'pt']),
            'doelsaldo' => $pak(['doelsaldo', 'saldo', 'ds']),
            'voor'      => $pak(['doelpuntenvoor', 'voor', 'dv']),
            'tegen'     => $pak(['doelpuntentegen', 'tegen', 'dt']),
            'isEigenTeam' => $eigen ? 'true' : 'false',
        ];
    }

    /** Losjes vergelijken: hoofdletters en spaties verschillen nogal eens. */
    private function zelfdeTeam(string $a, string $b): bool
    {
        $normaliseer = fn (string $s) => preg_replace('/[^a-z0-9]/', '', mb_strtolower($s));

        return $a !== '' && $normaliseer($a) === $normaliseer($b);
    }
}
