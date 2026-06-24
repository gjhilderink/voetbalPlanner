<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BarDuty extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    public const REQUIRED_MEMBERS = 2;

    protected $fillable = [
        'club_id', 'team_id', 'date', 'shift', 'notes', 'status',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'bar_duty_member');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'bar_duty_user');
    }

    public function refreshStatus(): void
    {
        if ($this->status === 'vervuld') {
            return;
        }

        // Leden én losse User-accounts tellen mee.
        $count = $this->members()->count() + $this->users()->count();
        $this->update(['status' => $count >= self::REQUIRED_MEMBERS ? 'bevestigd' : 'open']);
    }
}
