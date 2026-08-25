<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén geplande wissel binnen een wisselmoment: speler eruit, speler erin.
 *
 * Dit is een plan, geen gebeurtenis. Wat er tijdens de wedstrijd echt gebeurt
 * komt in match_events terecht; loopt het anders dan gepland, dan blijft dit
 * schema staan zoals het was en klopt de tijdlijn nog steeds.
 */
class LineupSubstitution extends Model
{
    use HasFactory;

    protected $fillable = [
        'lineup_id', 'block', 'member_out_id', 'member_in_id', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'block'      => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function lineup(): BelongsTo
    {
        return $this->belongsTo(Lineup::class);
    }

    public function memberOut(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_out_id');
    }

    public function memberIn(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_in_id');
    }
}
