<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ClubRequest extends Model
{
    use HasUuids;

    protected $fillable = [
        'club_name',
        'contact_name',
        'email',
        'phone',
        'sportlink_username',
        'sportlink_password',
        'notes',
        'status',
        'admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'sportlink_password' => 'encrypted',
        ];
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'approved' => 'Goedgekeurd',
            'rejected' => 'Afgewezen',
            default    => 'In behandeling',
        };
    }
}
