<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FootballMatch;
use App\Models\MatchEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * De cijfers van één wedstrijd, afgeleid uit het live verslag.
 *
 * Alles komt uit match_events en niet uit de losse doelpuntenadministratie: het
 * verslag is de plek waar een wedstrijd echt wordt vastgelegd, mét minuut,
 * assist, kaarten en wissels. Is er geen verslag bijgehouden, dan valt er ook
 * niets te tonen — en dat zegt het scherm dan.
 *
 * De vorm is bewust een platte lijst regels in plaats van een uitgewerkt object:
 * de app kan er dan één lijst van maken zonder per soort cijfer een eigen blok
 * te hoeven bouwen, en er kan een categorie bij zonder wijziging in de app.
 */
class MatchStatsController extends Controller
{
    /** GET /v1/matches/{match}/stats */
    public function show(Request $request, FootballMatch $match): JsonResponse
    {
        $mag = $request->user()?->accessibleTeams()->contains('id', $match->team_id) ?? false;

        if (! $mag) {
            return response()->json([self::leeg('Je hebt geen toegang tot deze wedstrijd.')], 403);
        }

        $events = $match->events()->with(['member', 'relatedMember'])->get();

        if ($events->isEmpty()) {
            return response()->json([self::leeg(
                'Van deze wedstrijd is geen live verslag bijgehouden, dus er zijn geen cijfers.'
            )]);
        }

        $doelpunten = $events->where('type', MatchEvent::TYPE_GOAL);
        $voor       = $doelpunten->where('side', MatchEvent::SIDE_OWN)->count();
        $tegen      = $doelpunten->where('side', MatchEvent::SIDE_OPPONENT)->count();

        $regels = [];

        $regels[] = self::kop('Uitslag');
        $regels[] = self::regel('Eindstand', $voor . ' - ' . $tegen);
        $regels[] = self::regel('Doelpunten voor', (string) $voor);
        $regels[] = self::regel('Doelpunten tegen', (string) $tegen);

        // Alleen eigen doelpunten hebben een maker; die van de tegenstander
        // leggen we niet op naam vast.
        $makers = self::telPerSpeler(
            $doelpunten->where('side', MatchEvent::SIDE_OWN),
            fn (MatchEvent $e) => $e->member?->name,
        );

        if ($makers->isNotEmpty()) {
            $regels[] = self::kop('Doelpuntenmakers');
            foreach ($makers as $naam => $aantal) {
                $regels[] = self::regel($naam, (string) $aantal);
            }
        }

        $assists = self::telPerSpeler(
            $doelpunten,
            fn (MatchEvent $e) => $e->relatedMember?->name,
        );

        if ($assists->isNotEmpty()) {
            $regels[] = self::kop('Assists');
            foreach ($assists as $naam => $aantal) {
                $regels[] = self::regel($naam, (string) $aantal);
            }
        }

        $kaarten = $events->where('type', MatchEvent::TYPE_CARD);

        if ($kaarten->isNotEmpty()) {
            $regels[] = self::kop('Kaarten');

            foreach ([MatchEvent::CARD_YELLOW => 'Geel', MatchEvent::CARD_RED => 'Rood'] as $soort => $label) {
                $vanSoort = $kaarten->where('card_type', $soort);

                if ($vanSoort->isEmpty()) {
                    continue;
                }

                // De namen erbij: "Geel · 2" zegt minder dan wie hem kreeg.
                $namen = $vanSoort->map(fn (MatchEvent $e) => $e->member?->name)
                    ->filter()
                    ->join(', ');

                $regels[] = self::regel(
                    $namen !== '' ? $label . ' — ' . $namen : $label,
                    (string) $vanSoort->count(),
                );
            }
        }

        $wissels = $events->where('type', MatchEvent::TYPE_SUBSTITUTION);

        if ($wissels->isNotEmpty()) {
            $regels[] = self::kop('Wissels');
            $regels[] = self::regel('Aantal wissels', (string) $wissels->count());
        }

        return response()->json($regels);
    }

    /**
     * Telt per speler, aflopend, met de meest scorende bovenaan.
     *
     * @return Collection<string, int>
     */
    private static function telPerSpeler(Collection $events, callable $naamVan): Collection
    {
        return $events
            ->map($naamVan)
            ->filter()
            ->countBy()
            ->sortDesc();
    }

    /** @return array<string, string> */
    private static function kop(string $label): array
    {
        return ['kind' => 'kop', 'label' => $label, 'value' => '', 'melding' => ''];
    }

    /** @return array<string, string> */
    private static function regel(string $label, string $value): array
    {
        return ['kind' => 'regel', 'label' => $label, 'value' => $value, 'melding' => ''];
    }

    /** @return array<string, string> */
    private static function leeg(string $melding): array
    {
        return ['kind' => '', 'label' => '', 'value' => '', 'melding' => $melding];
    }
}
