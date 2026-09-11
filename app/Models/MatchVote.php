<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén stem van één account op de man of the match van één wedstrijd.
 *
 * Anoniem naar buiten toe: de app krijgt alleen de aantallen per speler terug,
 * nooit wie op wie heeft gestemd.
 */
class MatchVote extends Model
{
    use HasUuids;

    protected $fillable = [
        'club_id', 'match_id', 'user_id', 'member_id', 'voted_member_id',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(FootballMatch::class, 'match_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Het lid dat hoort bij de stemmer; alleen ter informatie. */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** Op wie er gestemd is. */
    public function votedMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'voted_member_id');
    }
}
