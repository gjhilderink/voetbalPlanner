<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'opponent'      => $this->opponent ?? '',
            'location'      => $this->location ?? '',
            'matchDatetime' => $this->match_datetime?->toISOString() ?? '',
            'arrivalTime'   => $this->arrival_time ?? '',
            'isHome'        => (bool) $this->is_home,
            'status'        => $this->status ?? '',
            'scoreHome'     => $this->score_home ?? 0,
            'scoreAway'     => $this->score_away ?? 0,
            'teamName'      => $this->whenLoaded('team', fn() => $this->team?->name ?? '', ''),
            'coachName'     => $this->whenLoaded('coach', fn() => $this->coach?->name ?? '', ''),
            'fruitHeroName' => $this->whenLoaded('fruitHero', fn() => $this->fruitHero?->name ?? '', ''),
            'notes'         => $this->notes ?? '',
        ];
    }
}
