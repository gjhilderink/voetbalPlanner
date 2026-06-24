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
            'minute'     => (string) ($this->minute ?? 0),
            'type'       => $this->is_own_goal ? 'own_goal' : ($this->is_penalty ? 'penalty' : 'regular'),
            'scorerName' => $this->scorer?->name ?? '',
            'assistName' => $this->assist?->name ?? '',
        ];
    }
}
