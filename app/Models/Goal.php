<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Goal extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'match_id', 'scorer_id', 'assist_id',
        'minute', 'is_own_goal', 'is_penalty',
    ];

    protected function casts(): array
    {
        return [
            'minute' => 'integer',
            'is_own_goal' => 'boolean',
            'is_penalty' => 'boolean',
        ];
    }

    public function match(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(FootballMatch::class, 'match_id');
    }

    public function scorer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Member::class, 'scorer_id');
    }

    public function assist(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Member::class, 'assist_id');
    }
}
