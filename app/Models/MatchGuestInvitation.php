<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uitnodiging van een gastspeler voor één wedstrijd. Informatief: de gast krijgt
 * toegang tot de wedstrijdinfo zolang de uitnodiging actief + niet-verlopen is.
 */
class MatchGuestInvitation extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'club_id',
        'match_id',
        'member_id',
        'team_id',
        'invited_by_user_id',
        'status',
        'revoked_by_user_id',
        'revoked_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'revoked_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /** Actieve, niet-verlopen uitnodigingen. */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                     ->where('expires_at', '>', now());
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(FootballMatch::class, 'match_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->expires_at->isFuture();
    }
}
