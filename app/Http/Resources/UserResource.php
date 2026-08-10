<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Alle teams die de gebruiker mag zien (eigen + kinderteams), één keer
        // berekend. Default-team = eigen team indien aanwezig, anders het eerste
        // toegankelijke team (zodat een ouder zonder eigen team tóch een
        // currentTeamId krijgt).
        $accessibleTeams = $this->accessibleTeams();
        $defaultTeam = $this->managedTeams->first()
            ?? $this->member?->teams->first()
            ?? $accessibleTeams->first();

        // Rol/functie van de gebruiker per team (member_team.role of user_team.role).
        // Voor de eigen teams; kinderteams (guardian) hebben geen eigen rol.
        $roleByTeam = [];
        if ($this->member) {
            foreach ($this->member->teams as $t) {
                $r = $t->pivot->role ?? null;
                if ($r) {
                    $roleByTeam[$t->id] = $r;
                }
            }
        }
        foreach ($this->managedTeams as $t) {
            $r = $t->pivot->role ?? null;
            if ($r && ! isset($roleByTeam[$t->id])) {
                $roleByTeam[$t->id] = $r;
            }
        }
        $roleLabels = \App\Models\Member::TEAM_FUNCTIONS;
        $roleLabelFor = fn ($teamId) => isset($roleByTeam[$teamId])
            ? ($roleLabels[$roleByTeam[$teamId]] ?? $roleByTeam[$teamId])
            : '';

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
                'logo_url'        => $this->club->logo_path ? \Illuminate\Support\Facades\Storage::disk('logos')->url($this->club->logo_path) : '',
                'primary_color'   => $this->club->primary_color ?? '#1e3a5f',
                'secondary_color' => $this->club->secondary_color ?? '#3b82f6',
                'accent_color'    => $this->club->accent_color ?? '#10b981',
            ] : ['id' => '', 'name' => '', 'slug' => '', 'logo_path' => null, 'logo_url' => '', 'primary_color' => '#1e3a5f', 'secondary_color' => '#3b82f6', 'accent_color' => '#10b981'],
            'roles'         => $this->getRoleNames()->values(),
            'managed_teams' => $this->managedTeams->map(fn($t) => [
                'id'   => $t->id,
                'name' => $t->name,
            ])->values(),
            'team_id'   => $defaultTeam?->id ?? '',
            'team_name' => $defaultTeam?->name ?? '',
            // Alle toegankelijke teams voor de teamkeuze in de app (multi-team,
            // bv. ouder met kinderen in meerdere teams).
            'teams' => $accessibleTeams->map(fn ($t) => [
                'id'   => $t->id,
                'name' => $t->name,
                'role' => $roleLabelFor($t->id),
            ])->values(),
            'member_id'         => $this->member?->id ?? '',
            'relatiecode'       => $this->member?->external_id ?? '',
            'profile_photo_url' => ($this->profile_photo
                    ?? $this->member?->profile_photo)
                ? asset('storage/' . ($this->profile_photo ?? $this->member->profile_photo))
                : '',
            'is_active'     => $this->is_active,
            'created_at'    => $this->created_at?->toISOString(),
        ];
    }
}
