<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LineupPlayer extends Model
{
    use HasFactory;

    protected $fillable = [
        'lineup_id', 'member_id', 'position',
        'shirt_number', 'is_substitute', 'substituted_at_minute',
    ];

    protected function casts(): array
    {
        return [
            'is_substitute' => 'boolean',
            'shirt_number' => 'integer',
            'substituted_at_minute' => 'integer',
        ];
    }

    public function lineup(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Lineup::class);
    }

    public function member(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
