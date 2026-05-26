<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'age_group' => $this->age_group,
            'season' => $this->season,
            'photo' => $this->photo,
            'is_active' => $this->is_active,
            'external_id' => $this->external_id,
            'last_synced_at' => $this->last_synced_at?->toISOString(),
            'members' => MemberResource::collection($this->whenLoaded('members')),
            'matches_count' => $this->whenCounted('matches'),
            'members_count' => $this->whenCounted('members'),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
