<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LineupPlayer extends Model
{
    use HasFactory;

    protected $fillable = [
        'lineup_id', 'period', 'member_id', 'position',
        'shirt_number', 'is_substitute', 'substituted_at_minute',
        'slot_x', 'slot_y', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_substitute' => 'boolean',
            'shirt_number' => 'integer',
            'substituted_at_minute' => 'integer',
            'slot_x' => 'float',
            'slot_y' => 'float',
            'sort_order' => 'integer',
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
