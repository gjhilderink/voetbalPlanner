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
                'id'              => $this->club->id,
                'name'            => $this->club->name,
                'slug'            => $this->club->slug,
                'logo_path'       => $this->club->logo_path,
                'primary_color'   => $this->club->primary_color ?? '#1e3a5f',
                'secondary_color' => $this->club->secondary_color ?? '#3b82f6',
                'accent_color'    => $this->club->accent_color ?? '#10b981',
            ] : ['id' => '', 'name' => '', 'slug' => '', 'logo_path' => null, 'primary_color' => '#1e3a5f', 'secondary_color' => '#3b82f6', 'accent_color' => '#10b981'],
            'roles'         => $this->getRoleNames()->values(),
            'managed_teams' => $this->managedTeams->map(fn($t) => [
                'id'   => $t->id,
                'name' => $t->name,
            ])->values(),
            'team_id' => $this->managedTeams->first()?->id
                ?? $this->member?->teams->first()?->id
                ?? '',
            'is_active'     => $this->is_active,
            'created_at'    => $this->created_at?->toISOString(),
        ];
    }
}
