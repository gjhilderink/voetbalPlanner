<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LineupPlayerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'position' => $this->position,
            'shirt_number' => $this->shirt_number,
            'is_substitute' => $this->is_substitute,
            'substituted_at_minute' => $this->substituted_at_minute,
            'member' => new MemberResource($this->whenLoaded('member')),
        ];
    }
}
