<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén logregel per (release note, ontvanger) per verzending.
 */
class ReleaseNoteSend extends Model
{
    use HasUuids;

    protected $fillable = [
        'release_note_id',
        'email',
        'scope',
        'status',
        'sent_by_user_id',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    /** Labels per scope (voor badge in het admin-paneel). */
    public static array $scopeLabels = [
        'self'     => 'Test',
        'selected' => 'Selectie',
        'all'      => 'Iedereen',
    ];

    public function releaseNote(): BelongsTo
    {
        return $this->belongsTo(ReleaseNote::class);
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }
}
