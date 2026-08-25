<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lineup extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'match_id', 'formation', 'tactical_notes',
        'players_on_field', 'match_format', 'published_at',
        'substitution_blocks',
    ];

    protected function casts(): array
    {
        return [
            'published_at'        => 'datetime',
            'players_on_field'    => 'integer',
            'substitution_blocks' => 'integer',
        ];
    }

    /** Het geplande wisselschema, op wisselmoment en daarbinnen op volgorde. */
    public function substitutions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LineupSubstitution::class)
            ->orderBy('block')
            ->orderBy('sort_order');
    }

    /** Mogen spelers deze opstelling al zien? */
    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    public function match(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(FootballMatch::class, 'match_id');
    }

    public function players(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LineupPlayer::class);
    }

    public function starters(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LineupPlayer::class)->where('is_substitute', false);
    }

    public function substitutes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LineupPlayer::class)->where('is_substitute', true);
    }
}
