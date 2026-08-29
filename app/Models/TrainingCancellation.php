<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén training die niet doorgaat.
 *
 * Trainingen zijn berekende gebeurtenissen uit een herhaalschema; alleen de
 * uitzonderingen staan in de database. Geen rij betekent dus: gaat gewoon door.
 */
class TrainingCancellation extends Model
{
    use HasUuids;

    protected $fillable = [
        'training_schedule_id', 'date', 'reason', 'cancelled_by_user_id',
    ];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(TrainingSchedule::class, 'training_schedule_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }
}
