<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LineupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'match_id' => $this->match_id,
            'formation' => $this->formation,
            'tactical_notes' => $this->tactical_notes,
            'starters' => LineupPlayerResource::collection($this->whenLoaded('starters')),
            'substitutes' => LineupPlayerResource::collection($this->whenLoaded('substitutes')),
            'players' => LineupPlayerResource::collection($this->whenLoaded('players')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
