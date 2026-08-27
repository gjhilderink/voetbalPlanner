<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Een vrijblijvende aanvraag voor een demo, vanaf de website.
 *
 * Bewust losgekoppeld van ClubRequest: dat is een club die wíl, met
 * Sportlink-gegevens en al. Dit is iemand die eerst wil kijken. Ze door
 * elkaar heen laten lopen zou betekenen dat de een de status van de ander
 * krijgt, en dat een demo-aanvrager om zijn wachtwoord wordt gevraagd.
 */
class DemoRequest extends Model
{
    use HasUuids;

    protected $fillable = [
        'club_name',
        'contact_name',
        'email',
        'phone',
        'member_count',
        'notes',
        'status',
        'admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'member_count' => 'integer',
        ];
    }

    /** @var array<string, string> */
    public const STATUSSEN = [
        'pending'   => 'Nieuw',
        'scheduled' => 'Ingepland',
        'completed' => 'Gehad',
        'cancelled' => 'Vervallen',
    ];

    public function statusLabel(): string
    {
        return self::STATUSSEN[$this->status] ?? self::STATUSSEN['pending'];
    }
}
