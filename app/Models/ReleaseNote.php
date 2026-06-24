<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReleaseNote extends Model
{
    use HasUuids;

    protected $fillable = [
        'feature_id',
        'title',
        'body',
        'released_at',
    ];

    protected $casts = [
        'released_at' => 'datetime',
    ];

    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }
}
