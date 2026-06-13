<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class GuardianLink extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'club_id',
        'guardian_member_id',
        'child_member_id',
        'status',
        'request_token',
        'resolved_by_member_id',
        'resolved_at',
        'revoked_by_member_id',
        'revoked_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
            'revoked_at'  => 'datetime',
            'expires_at'  => 'datetime',
        ];
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    /** Openstaande (niet-verlopen) verzoeken. */
    public function scopePending($query)
    {
        return $query->where('status', 'pending')
                     ->where('expires_at', '>', now());
    }

    /** Goedgekeurde koppelingen. */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    // ── Relaties ───────────────────────────────────────────────────────────────

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'guardian_member_id');
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'child_member_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'resolved_by_member_id');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'revoked_by_member_id');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    public static function generateToken(): string
    {
        return Str::random(64);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending' && $this->expires_at->isFuture();
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /** Mag dit verzoek nog geannuleerd/ingetrokken worden? */
    public function isRevocable(): bool
    {
        return in_array($this->status, ['pending', 'approved']);
    }
}
