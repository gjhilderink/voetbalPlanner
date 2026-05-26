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

    protected $fillable = ['match_id', 'formation', 'tactical_notes'];

    public function match(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(FootballMatch::class);
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
