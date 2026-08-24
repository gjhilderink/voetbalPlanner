<?php

declare(strict_types=1);

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Lab404\Impersonate\Models\Impersonate;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasTenants
{
    use HasApiTokens, HasFactory, HasRoles, HasUuids, Impersonate, Notifiable, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'date_of_birth',
        'profile_photo', 'external_id', 'is_active', 'club_id',
        'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'date_of_birth'     => 'date',
            'is_active'         => 'boolean',
            'last_login_at'     => 'datetime',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) $this->is_active;
    }

    public function getTenants(Panel $panel): array|Collection
    {
        if ($this->hasRole('super_admin')) {
            return Club::where('is_active', true)->orderBy('name')->get();
        }

        if ($this->club_id) {
            return Club::where('id', $this->club_id)->where('is_active', true)->get();
        }

        return collect();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        if ($this->hasRole('super_admin')) {
            return true;
        }

        return $this->club_id === $tenant->id;
    }

    public function isAdmin(): bool
    {
        return $this->hasAnyRole(['super_admin', 'club_admin']);
    }

    public function isBarCommissie(): bool
    {
        return $this->hasRole('bar_commissie');
    }

    public function canImpersonate(): bool
    {
        return $this->hasRole('super_admin');
    }

    public function canBeImpersonated(): bool
    {
        return !$this->hasRole('super_admin');
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function member(): HasOne
    {
        return $this->hasOne(Member::class);
    }

    /**
     * De leden waarvoor dit account als ouder/verzorger is gekoppeld.
     *
     * Loopt via het Member-record van dit account: guardian_links koppelt twee
     * leden aan elkaar, niet twee accounts. Alleen goedgekeurde koppelingen —
     * een openstaand of ingetrokken verzoek geeft geen toegang.
     *
     * @return \Illuminate\Support\Collection<int, Member>
     */
    public function guardianChildren(): \Illuminate\Support\Collection
    {
        $member = $this->resolveMember();
        if (! $member) {
            return collect();
        }

        return GuardianLink::query()
            ->where('guardian_member_id', $member->id)
            ->where('status', 'approved')
            ->with('child')
            ->get()
            ->map(fn ($link) => $link->child)
            ->filter()
            ->values();
    }

    /**
     * Het lid bij dit account: via de directe koppeling (user_id) óf, als die
     * ontbreekt, via een e-mail-match. Voorkomt 'Alleen leden'-403's bij leden
     * waarvan de Member-User-koppeling niet (goed) is gezet.
     */
    public function resolveMember(): ?Member
    {
        return $this->member
            ?? ($this->email ? Member::where('email', $this->email)->first() : null);
    }

    public function managedTeams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'user_team')->withPivot('role');
    }

    /** Staf-/commissiegroepen waar dit account direct aan hangt (StaffGroup::users). */
    public function staffGroups(): BelongsToMany
    {
        return $this->belongsToMany(StaffGroup::class, 'staff_group_user');
    }

    public function managedTeamIds(): Collection
    {
        return $this->managedTeams()->pluck('teams.id');
    }

    /**
     * Mag deze gebruiker de opstelling + score van een wedstrijd van dit team
     * beheren? Beheerders altijd; verder wie een beheer-functie (coach/leider)
     * heeft voor dat team — via de user_team-koppeling óf via de member_team-
     * koppeling van het gekoppelde lid (speler in team A, coach van team B).
     */
    public function canManageLineup(?string $teamId): bool
    {
        if ($this->hasAnyRole(['super_admin', 'club_admin'])) {
            return true;
        }

        if ($teamId === null) {
            return false;
        }

        $mgmt = Member::MANAGEMENT_ROLES;

        // Beheer-functie op de user_team-koppeling (bv. coach zonder rooster-lid).
        $viaUser = $this->managedTeams()
            ->where('teams.id', $teamId)
            ->wherePivotIn('role', $mgmt)
            ->exists();
        if ($viaUser) {
            return true;
        }

        // Beheer-functie op de member_team-koppeling van het gekoppelde lid.
        $member = $this->resolveMember();
        if ($member) {
            return $member->teams()
                ->where('teams.id', $teamId)
                ->wherePivotIn('role', $mgmt)
                ->exists();
        }

        return false;
    }

    /**
     * Alle teams die deze gebruiker mag zien: eigen teams (via user_team én via
     * het Member/lidnummer) plus de teams van gekoppelde kinderen (approved
     * guardian_links). Uniek op id. Gebruikt voor de teamkeuze in de app
     * (bv. een ouder met kinderen in meerdere teams).
     */
    /** Per-request memoisatie (voorkomt N+1 als een Resource dit per item aanroept). */
    protected ?\Illuminate\Support\Collection $accessibleTeamsCache = null;

    public function accessibleTeams(): \Illuminate\Support\Collection
    {
        if ($this->accessibleTeamsCache !== null) {
            return $this->accessibleTeamsCache;
        }

        $teams = $this->managedTeams()->get();

        // resolveMember() i.p.v. de directe member-relatie: leden die alleen via
        // e-mail aan hun account hangen (geen user_id op het lid) hielden anders
        // een lege teamlijst over, waardoor 'alleen mijn teams' niets opleverde.
        $member = $this->resolveMember();

        if ($member) {
            $teams = $teams->merge($member->teams);

            $childMemberIds = \App\Models\GuardianLink::query()
                ->where('guardian_member_id', $member->id)
                ->where('status', 'approved')
                ->pluck('child_member_id');

            if ($childMemberIds->isNotEmpty()) {
                $childTeams = \App\Models\Team::query()
                    ->whereHas('members', fn ($q) => $q->whereIn('members.id', $childMemberIds))
                    ->get();
                $teams = $teams->merge($childTeams);
            }
        }

        return $this->accessibleTeamsCache = $teams->unique('id')->values();
    }
}
