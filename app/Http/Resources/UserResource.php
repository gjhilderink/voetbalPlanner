<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'email'         => $this->email,
            'phone'         => $this->phone,
            'club_id'       => $this->club_id,
            'club'          => $this->club ? [
                'id'        => $this->club->id,
                'name'      => $this->club->name,
                'slug'      => $this->club->slug,
                'logo_path' => $this->club->logo_path,
            ] : ['id' => '', 'name' => '', 'slug' => '', 'logo_path' => null],
            'roles'         => $this->getRoleNames()->values(),
            'managed_teams' => $this->managedTeams->map(fn($t) => [
                'id'   => $t->id,
                'name' => $t->name,
            ])->values(),
            'is_active'     => $this->is_active,
            'created_at'    => $this->created_at?->toISOString(),
        ];
    }
}
