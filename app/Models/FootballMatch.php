<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FootballMatch extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'matches';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'external_id', 'team_id', 'opponent', 'match_datetime',
        'location', 'is_home', 'status', 'score_home', 'score_away',
        'arrival_time', 'dressing_room', 'coach_id', 'fruit_hero_id', 'notes', 'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'match_datetime' => 'datetime',
            'is_home' => 'boolean',
            'score_home' => 'integer',
            'score_away' => 'integer',
            'last_synced_at' => 'datetime',
        ];
    }

    public function team(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function coach(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Member::class, 'coach_id');
    }

    public function fruitHero(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Member::class, 'fruit_hero_id');
    }

    public function coaches(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'match_coaches', 'match_id', 'member_id');
    }

    public function cleaners(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'match_cleaners', 'match_id', 'member_id');
    }

    public function drivers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'match_drivers', 'match_id', 'member_id');
    }

    public function lineup(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Lineup::class);
    }

    public function goals(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Goal::class);
    }
}
