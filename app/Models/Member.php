<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Member extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'external_id', 'user_id', 'name', 'email', 'phone',
        'date_of_birth', 'role', 'profile_photo', 'is_active', 'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'is_active' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function teams(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Team::class)
            ->withPivot(['role', 'season', 'is_active'])
            ->withTimestamps();
    }

    public function matchesAsCoach(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FootballMatch::class, 'coach_id');
    }

    public function goals(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Goal::class, 'scorer_id');
    }

    public function assists(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Goal::class, 'assist_id');
    }

    public function lineupPlayers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LineupPlayer::class);
    }

    public function isCoach(): bool
    {
        return $this->role === 'coach';
    }

    public function isPlayer(): bool
    {
        return $this->role === 'player';
    }
}
