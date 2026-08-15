<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MatchResource extends JsonResource
{
    private static array $statusLabels = [
        'scheduled'  => 'Gepland',
        'cancelled'  => 'Geannuleerd',
        'postponed'  => 'Uitgesteld',
        'played'     => 'Gespeeld',
        'completed'  => 'Gespeeld',
        'finished'   => 'Gespeeld',
        'live'       => 'Live',
    ];

    public function toArray(Request $request): array
    {
        $rawStatus  = $this->status ?? '';
        $myMemberId = $request->user()?->member?->id;

        return [
            'id'             => $this->id,
            'opponent'       => $this->opponent ?? '',
            'opponentLogo'   => $this->opponent_logo ?? '',
            'location'       => $this->location ?? '',
            'matchDatetime'  => $this->match_datetime?->format('d-m-Y H:i') ?? '',
            // arrival_time is een tijdkolom zonder cast, dus ruw '14:30:00'.
            // Afkappen op H:i, anders staan de seconden in de app.
            'arrivalTime'    => substr((string) ($this->arrival_time ?? ''), 0, 5),
            'isHome'         => (bool) $this->is_home,
            'status'         => self::$statusLabels[strtolower($rawStatus)] ?? $rawStatus,
            'scoreHome'      => $this->score_home ?? 0,
            'scoreAway'      => $this->score_away ?? 0,
            'teamName'       => $this->team?->name ?? '',
            'teamId'         => $this->team_id ?? '',
            'coachName'      => $this->whenLoaded(
                'coaches',
                fn() => $this->coaches->isNotEmpty()
                    ? $this->coaches->pluck('name')->join(', ')
                    : ($this->coach?->name ?? ''),
                $this->coach?->name ?? ''
            ),
            'fruitHeroName'  => $this->fruitHero?->name ?? '',
            'fruitHeroId'    => $this->fruit_hero_id ?? '',
            'vlaggerName'    => $this->vlagger?->name ?? '',
            'vlaggerId'      => $this->vlagger_id ?? '',
            'notes'          => $this->notes ?? '',
            'isFruitHero'    => $myMemberId
                ? $this->fruit_hero_id === $myMemberId
                : false,
            'isDriver'       => $myMemberId
                ? (bool) $this->whenLoaded(
                    'drivers',
                    fn() => $this->drivers->contains('id', $myMemberId),
                    false,
                )
                : false,
            'driverNames'    => $this->whenLoaded(
                'drivers',
                fn() => $this->drivers->pluck('name')->join(', '),
                '',
            ),
        ];
    }
}
