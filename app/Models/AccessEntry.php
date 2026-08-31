<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén geslaagde binnenkomst bij een activiteit.
 *
 * Alleen geslaagde: een afgewezen scan levert geen regel op. De unieke sleutel
 * op activiteit + lid ís de controle op "deze is al binnen", en die kan zijn
 * werk niet doen als er ook afgewezen pogingen in de tabel staan.
 */
class AccessEntry extends Model
{
    use HasUuids;

    protected $fillable = [
        'agenda_item_id',
        'access_code_id',
        'member_id',
        'user_id',
        'entered_at',
    ];

    protected function casts(): array
    {
        return ['entered_at' => 'datetime'];
    }

    public function agendaItem(): BelongsTo
    {
        return $this->belongsTo(AgendaItem::class);
    }

    public function accessCode(): BelongsTo
    {
        return $this->belongsTo(AccessCode::class);
    }

    /** Gevuld als iemand met zijn eigen lidnummer binnenkwam. */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** Wie er scande. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
