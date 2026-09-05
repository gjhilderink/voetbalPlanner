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

        // De keuze moet uit $accessibleTeams komen en niet rechtstreeks uit de
        // relaties: die laatste bevatten ook elftallen van vorig seizoen. Wees
        // je standaardteam er een aan dat niet in de lijst staat, dan wijst
        // currentTeamId nergens naar en oogt de app leeg terwijl er niets kapot is.
        $zichtbaar = fn ($teams) => $teams->first(
            fn ($t) => $accessibleTeams->contains('id', $t->id),
        );

        $defaultTeam = $zichtbaar($this->managedTeams)
            ?? $zichtbaar($this->member?->teams ?? collect())
            ?? $accessibleTeams->first();

        // Functies van de gebruiker per team (member_team.role én user_team.role).
        // Álle functies, niet één: iemand kan in hetzelfde elftal speler zijn
        // (member_team) én coach (user_team). De app leidt hier zijn rol-tabs uit
        // af, en met één label zou zo'n spelende coach zijn stafblok verliezen in
        // juist het team waar hij traint.
        //
        // resolveMember() i.p.v. de member-relatie: leden die alleen via e-mail
        // aan hun account hangen kregen anders bij elk team een lege functie.
        // Kinderteams (guardian) hebben terecht geen eigen functie.
        $rolesByTeam = [];
        $addRole = function (string $teamId, ?string $role) use (&$rolesByTeam): void {
            if (! $role) {
                return;
            }
            $rolesByTeam[$teamId] ??= [];
            if (! in_array($role, $rolesByTeam[$teamId], true)) {
                $rolesByTeam[$teamId][] = $role;
            }
        };
        // Eén keer opzoeken; hieronder wordt het lid nog een paar keer gebruikt
        // voor het lidnummer en de pasfoto.
        $lid = $this->resolveMember();
        if ($lid) {
            foreach ($lid->teams as $t) {
                $addRole($t->id, $t->pivot->role ?? null);
            }
        }
        foreach ($this->managedTeams as $t) {
            $addRole($t->id, $t->pivot->role ?? null);
        }

        $roleLabels = \App\Models\Member::TEAM_FUNCTIONS;
        $labelOf = fn (string $role) => $roleLabels[$role] ?? $role;
        // 'role' blijft één label (member_team vóór user_team, zoals altijd) voor
        // bestaande app-versies; 'roles' draagt de volledige lijst.
        $roleLabelFor = fn ($teamId) => isset($rolesByTeam[$teamId][0])
            ? $labelOf($rolesByTeam[$teamId][0])
            : '';
        $roleLabelsFor = fn ($teamId) => implode(
            ',',
            array_map($labelOf, $rolesByTeam[$teamId] ?? [])
        );

        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'email'         => $this->email,
            'phone'         => $this->phone,
            'club_id'       => $this->club_id,
            // Dit is de plek waar de app zijn clubhuisstijl vandaan haalt: bij
            // het inloggen en bij elke dashboard-load. Wat hier niet in staat,
            // kent de app niet.
            'club'          => $this->club ? [
                'id'              => $this->club->id,
                'name'            => $this->club->name,
                'slug'            => $this->club->slug,
                'logo_path'       => $this->club->logo_path,
                'logo_url'        => self::logoUrl($this->club->logo_path),
                'primary_color'   => $this->club->primary_color ?? '#1e3a5f',
                'secondary_color' => $this->club->secondary_color ?? '#3b82f6',
                'accent_color'    => $this->club->accent_color ?? '#10b981',
                'app_icon_url'    => self::logoUrl($this->club->app_icon_path),
                'splash_url'      => self::logoUrl($this->club->splash_path),
                'splash_bg_color' => $this->club->splash_bg_color ?? '',
                // Staat de toegangsmodule aan? Bepaalt of elk lid zijn
                // persoonlijke QR in het menu ziet.
                'access_enabled'  => $this->club->access_enabled ? 'true' : 'false',
                'rooms_enabled'   => $this->club->rooms_enabled ? 'true' : 'false',
            ] : self::legeClub(),
            'roles'         => $this->getRoleNames()->values(),
            // Mag deze gebruiker bij de ingang scannen? Als vlag en niet als rol:
            // de app leidt zijn rollen af uit teamfuncties (Speler, Coach,
            // Trainer, Ouder, Leider) en kent de portalrollen helemaal niet.
            // Dezelfde aanpak als magBeheren en canManage elders.
            // Rol én module: staat toegangscontrole uit bij de club, dan hoort
            // de scanknop nergens te verschijnen.
            'can_scan_access' => $this->hasAnyRole(['super_admin', 'club_admin', 'toegang'])
                && (bool) $this->club?->access_enabled,
            // Mag deze gebruiker ruimtes reserveren? Zelfde opzet als
            // hierboven: rol en module samen. Staat er 'false', dan hoort de
            // hele module nergens in beeld te komen.
            'can_plan_rooms' => $this->hasAnyRole(['super_admin', 'club_admin', 'room-planning'])
                && (bool) $this->club?->rooms_enabled ? 'true' : 'false',
            'managed_teams' => $this->managedTeams->map(fn($t) => [
                'id'   => $t->id,
                'name' => $t->name,
            ])->values(),
            'team_id'   => $defaultTeam?->id ?? '',
            'team_name' => $defaultTeam?->name ?? '',
            // Alle toegankelijke teams voor de teamkeuze in de app (multi-team,
            // bv. ouder met kinderen in meerdere teams).
            'teams' => $accessibleTeams->map(fn ($t) => [
                'id'    => $t->id,
                'name'  => $t->name,
                'role'  => $roleLabelFor($t->id),
                'roles' => $roleLabelsFor($t->id),
            ])->values(),
            // resolveMember() en niet de member-relatie: een lid dat alleen op
            // e-mailadres gekoppeld is had hier een leeg lidnummer, en zou dus
            // een lege QR in zijn profiel krijgen. De teams hierboven doen het
            // al met resolveMember().
            'member_id'         => $lid?->id ?? '',
            'relatiecode'       => $lid?->external_id ?? '',
            // Eigen upload eerst; anders de pasfoto uit Sportlink van het
            // gekoppelde lid. Zie Member::photoUrl().
            'profile_photo_url' => $this->profile_photo
                ? asset('storage/' . $this->profile_photo)
                : ($lid?->photoUrl() ?? ''),
            'is_active'     => $this->is_active,
            'created_at'    => $this->created_at?->toISOString(),
        ];
    }

    /** Publieke URL van een bestand op de logo-disk, of leeg. */
    private static function logoUrl(?string $pad): string
    {
        return $pad
            ? \Illuminate\Support\Facades\Storage::disk('logos')->url($pad)
            : '';
    }

    /**
     * Een gebruiker zonder club krijgt dezelfde sleutels met lege waarden.
     *
     * Uitgeschreven en niet weggelaten: de app leest deze velden blind uit het
     * antwoord, en een ontbrekende sleutel is daar een fout in plaats van een
     * lege waarde.
     *
     * @return array<string, string|null>
     */
    private static function legeClub(): array
    {
        return [
            'id' => '', 'name' => '', 'slug' => '',
            'logo_path' => null, 'logo_url' => '',
            'primary_color' => '#1e3a5f',
            'secondary_color' => '#3b82f6',
            'accent_color' => '#10b981',
            'app_icon_url' => '', 'splash_url' => '', 'splash_bg_color' => '',
            'access_enabled' => 'false',
            'rooms_enabled' => 'false',
        ];
    }
}
