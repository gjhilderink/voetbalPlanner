<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SwapRequest extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'type', 'target_id', 'requester_id', 'requestee_id', 'status', 'message',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'requester_id');
    }

    public function requestee(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'requestee_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
