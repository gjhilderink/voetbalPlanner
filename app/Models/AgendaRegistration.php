<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Aan- of afmelding van één persoon voor één agenda-item.
 *
 * De persoon is een lid (member_id) of een account zonder lidprofiel (user_id);
 * subject_key vat dat samen tot één sleutel zodat de unique-index sluitend is —
 * zie de migratie voor het waarom.
 */
class AgendaRegistration extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    public const STATUS_GOING     = 'aangemeld';
    public const STATUS_NOT_GOING = 'afgemeld';

    public const STATUSES = [
        self::STATUS_GOING     => 'Aangemeld',
        self::STATUS_NOT_GOING => 'Afgemeld',
    ];

    protected $fillable = [
        'agenda_item_id', 'club_id', 'user_id', 'member_id',
        'subject_key', 'name', 'status', 'guest_count', 'note', 'registered_at',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
            'guest_count'   => 'integer',
        ];
    }

    /** Sleutel die één persoon identificeert, ook zonder lidprofiel. */
    public static function subjectKey(?string $memberId, ?string $userId): string
    {
        return $memberId ? "m:{$memberId}" : "u:{$userId}";
    }

    protected static function booted(): void
    {
        static::saving(function (self $registration): void {
            $registration->subject_key ??= self::subjectKey($registration->member_id, $registration->user_id);
            $registration->registered_at ??= now();
        });
    }

    public function agendaItem(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AgendaItem::class);
    }

    public function club(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function member(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function scopeGoing($query)
    {
        return $query->where('status', self::STATUS_GOING);
    }
}
