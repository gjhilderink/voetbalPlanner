<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name ?? '',
            'description' => $this->description ?? '',
            'teamId'      => $this->team_id ?? '',
            'teamName'    => $this->team?->name ?? '',
            'memberCount' => $this->members?->count() ?? 0,
            'members'     => $this->members?->map(fn($m) => [
                'id'   => $m->id,
                'name' => $m->name,
            ])->values()->all() ?? [],
        ];
    }
}
