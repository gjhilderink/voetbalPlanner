<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class StaffGroup extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'club_id', 'team_id', 'name', 'description',
    ];

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
        return $this->belongsToMany(Member::class, 'staff_group_member');
    }

    /**
     * Losse accounts (User) die geen Member zijn, bijv. bardienst-coordinatoren.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'staff_group_user');
    }
}
