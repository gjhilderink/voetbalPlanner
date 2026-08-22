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
            // Als string: de app typeert dit veld zo, en lineup_players heeft
            // een oplopend getal als sleutel.
            'id'           => (string) $this->id,
            'memberId'     => $this->member_id ?? '',
            'memberName'   => $this->member?->name ?? '',
            'position'     => $this->position ?? '',
            'jerseyNumber' => (string) ($this->shirt_number ?? ''),
            'isStarter'    => !(bool) $this->is_substitute,
            'isCaptain'    => false,
        ];
    }
}
