<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Team extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'external_id', 'name', 'category', 'age_group',
        'season', 'photo', 'is_active', 'is_first_team', 'last_synced_at', 'club_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_first_team' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    public function members(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Member::class)
            ->withPivot(['role', 'season', 'is_active'])
            ->withTimestamps();
    }

    /**
     * De leden van dit elftal die daadwerkelijk meespelen: coaches, trainers,
     * leiders en overige staf vallen weg. Gebruikt voor de opstelling en voor
     * af- en aanmelden bij een wedstrijd — een trainer staat niet in de basis en
     * meldt zich ook niet af.
     *
     * Twee filters, want de functie staat op twee plekken: members.role is de
     * hoofdrol in de club, member_team.role de functie binnen dít elftal. Iemand
     * kan speler zijn in het ene team en leider in het andere.
     *
     * Een lege of onbekende functie telt als speler: NOT IN sluit NULL anders
     * stilzwijgend uit, en dan zou een lid zonder ingevulde functie verdwijnen.
     */
    public function playingMembers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        $staf = [Member::ROLE_COACH, Member::ROLE_ASSISTANT, Member::ROLE_LEIDER];

        return $this->members()
            ->where(fn ($q) => $q
                ->whereNull('members.role')
                ->orWhereNotIn('members.role', ['coach', 'medical', 'staff']))
            ->where(fn ($q) => $q
                ->whereNull('member_team.role')
                ->orWhereNotIn('member_team.role', $staf));
    }

    /**
     * User-accounts gekoppeld via user_team pivot. Wordt gebruikt om
     * app-gebruikers (zoals bardienst@..., coaches zonder Member-record,
     * staff-leiders) óók in de team-leden-API te tonen.
     */
    public function users(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_team')->withPivot('role')->withTimestamps();
    }

    public function matches(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FootballMatch::class);
    }

    public function coaches(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Member::class)
            ->wherePivot('role', 'coach')
            ->withTimestamps();
    }

    /**
     * Leden die als default-coach van een wedstrijd gelden. Eerst de echte
     * coaches (rol coach); heeft het team die niet, dan vallen we terug op
     * leiders/assistent-coaches. Retourneert een Collection<Member>.
     */
    public function matchDefaultCoaches(): \Illuminate\Support\Collection
    {
        $coaches = $this->members()->wherePivot('role', Member::ROLE_COACH)->get();
        if ($coaches->isNotEmpty()) {
            return $coaches;
        }
        return $this->members()
            ->wherePivotIn('role', [Member::ROLE_LEIDER, Member::ROLE_ASSISTANT])
            ->get();
    }

    public function club(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Club::class);
    }
}
