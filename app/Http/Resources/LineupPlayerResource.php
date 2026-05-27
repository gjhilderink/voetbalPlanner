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
            'id'           => $this->id,
            'memberName'   => $this->whenLoaded('member', fn() => $this->member?->name ?? '', ''),
            'position'     => $this->position ?? '',
            'jerseyNumber' => (string) ($this->shirt_number ?? ''),
            'isStarter'    => !(bool) $this->is_substitute,
            'isCaptain'    => false,
        ];
    }
}
