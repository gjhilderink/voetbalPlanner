<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Een ruimte van de club: kantine, bestuurskamer, kleedkamer.
 *
 * De koppeling met Microsoft 365 loopt via `ms_room_email`, de postbus van de
 * ruimte, en niet via de naam - die mag hier anders heten dan daar. Blijft dat
 * veld leeg, dan werkt de ruimte gewoon; hij staat dan alleen niet in Outlook.
 */
class Room extends Model
{
    use HasUuids;

    protected $fillable = [
        'club_id',
        'name',
        'description',
        'capacity',
        'color',
        'is_active',
        'sort_order',
        'ms_room_email',
        'ms_room_id',
        'ms_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active'    => 'boolean',
            'capacity'     => 'integer',
            'sort_order'   => 'integer',
            'ms_synced_at' => 'datetime',
        ];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(RoomReservation::class);
    }

    /** In de volgorde waarin ze in het rooster horen te staan. */
    public function scopeGeordend(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function scopeActief(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Is deze ruimte aan een postbus in Microsoft 365 gekoppeld? */
    public function isGekoppeld(): bool
    {
        return filled($this->ms_room_email);
    }

    /**
     * De kleur van het blok in het rooster.
     *
     * Een vaste terugval en geen willekeurige kleur: een ruimte die elke keer
     * anders oplicht is in een weekoverzicht niet terug te vinden.
     */
    public function kleur(): string
    {
        return $this->color ?: '#64748B';
    }
}
