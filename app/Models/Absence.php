<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Absence extends Model
{
    use HasUuids;

    public const TYPE_TRAINING = 'training';
    public const TYPE_MATCH    = 'match';

    protected $fillable = [
        'member_id', 'user_id', 'club_id', 'type',
        'match_id', 'training_schedule_id', 'training_date',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'training_date' => 'date',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(FootballMatch::class, 'match_id');
    }

    public function trainingSchedule(): BelongsTo
    {
        return $this->belongsTo(TrainingSchedule::class);
    }
}
