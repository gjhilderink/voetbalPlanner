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
            'id' => $this->id,
            'minute' => $this->minute,
            'is_own_goal' => $this->is_own_goal,
            'is_penalty' => $this->is_penalty,
            'scorer' => new MemberResource($this->whenLoaded('scorer')),
            'assist' => new MemberResource($this->whenLoaded('assist')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
