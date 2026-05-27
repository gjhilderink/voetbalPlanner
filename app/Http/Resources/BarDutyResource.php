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
            'id'       => $this->id,
            'date'     => $this->date?->format('Y-m-d') ?? '',
            'shift'    => $this->shift ?? '',
            'status'   => $this->status ?? '',
            'teamName' => $this->whenLoaded('team', fn() => $this->team?->name ?? '', ''),
            'members'  => $this->whenLoaded('members', fn() => $this->members->pluck('name')->join(', '), ''),
            'notes'    => $this->notes ?? '',
        ];
    }
}
