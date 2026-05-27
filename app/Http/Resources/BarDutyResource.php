<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BarDutyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'      => $this->id,
            'date'    => $this->date?->format('Y-m-d'),
            'shift'   => $this->shift,
            'status'  => $this->status,
            'notes'   => $this->notes,
            'club_id' => $this->club_id,
            'team'    => $this->whenLoaded('team', fn() => [
                'id'   => $this->team->id,
                'name' => $this->team->name,
            ]),
            'members' => $this->whenLoaded('members', fn() => $this->members->map(fn($m) => [
                'id'    => $m->id,
                'name'  => $m->name,
                'phone' => $m->phone,
            ])->values()),
        ];
    }
}
