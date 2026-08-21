<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén stemming van één teamlid over de sfeer in het team, voor één week.
 */
class TeamMood extends Model
{
    use HasUuids;

    /** Score → label, zoals de app het toont. */
    public const LABELS = [
        1 => 'Kan beter',
        2 => 'Redelijk',
        3 => 'Goed',
        4 => 'Top!',
    ];

    protected $fillable = [
        'club_id', 'team_id', 'user_id', 'member_id', 'score', 'week',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** ISO-week van een moment, als 'YYYY-WW'. */
    public static function weekKey(\Carbon\Carbon $moment): string
    {
        return $moment->isoWeekYear . '-' . str_pad((string) $moment->isoWeek, 2, '0', STR_PAD_LEFT);
    }
}
