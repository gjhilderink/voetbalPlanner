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
            'id' => $this->id,
            'opponent' => $this->opponent,
            'match_datetime' => $this->match_datetime?->toISOString(),
            'location' => $this->location,
            'is_home' => $this->is_home,
            'status' => $this->status,
            'score_home' => $this->score_home,
            'score_away' => $this->score_away,
            'arrival_time' => $this->arrival_time,
            'notes' => $this->notes,
            'external_id' => $this->external_id,
            'team' => new TeamResource($this->whenLoaded('team')),
            'coach' => new MemberResource($this->whenLoaded('coach')),
            'fruit_hero' => new MemberResource($this->whenLoaded('fruitHero')),
            'drivers' => MemberResource::collection($this->whenLoaded('drivers')),
            'lineup' => new LineupResource($this->whenLoaded('lineup')),
            'goals' => GoalResource::collection($this->whenLoaded('goals')),
            'last_synced_at' => $this->last_synced_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
