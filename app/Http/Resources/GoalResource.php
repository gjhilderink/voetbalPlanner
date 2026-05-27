<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GoalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'minute'     => $this->minute ?? 0,
            'type'       => $this->is_own_goal ? 'own_goal' : ($this->is_penalty ? 'penalty' : 'regular'),
            'scorerName' => $this->whenLoaded('scorer', fn() => $this->scorer?->name ?? '', ''),
            'assistName' => $this->whenLoaded('assist', fn() => $this->assist?->name ?? '', ''),
        ];
    }
}
