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
        'season', 'photo', 'is_active', 'last_synced_at', 'club_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
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
