<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Een kaartsoort bij een activiteit: volwassene, kind, vrijwilliger.
 *
 * De voorraad wordt niet als teller bijgehouden maar per keer uitgerekend uit
 * de bestelregels. Een teller loopt vroeg of laat uit de pas - bij een
 * afgebroken betaling, een handmatig verwijderde bestelling, of gewoon een
 * gemiste update - en dan verkoop je te veel of te weinig.
 */
class TicketType extends Model
{
    use HasUuids;

    protected $fillable = [
        'club_id',
        'agenda_item_id',
        'name',
        'description',
        'price_cents',
        'stock',
        'max_per_order',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_cents'   => 'integer',
            'stock'         => 'integer',
            'max_per_order' => 'integer',
            'sort_order'    => 'integer',
            'is_active'     => 'boolean',
        ];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function agendaItem(): BelongsTo
    {
        return $this->belongsTo(AgendaItem::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    /**
     * Hoeveel kaarten er al vergeven zijn.
     *
     * Betaalde bestellingen tellen mee, en open bestellingen die nog niet
     * verlopen zijn ook: iemand die op dit moment aan het afrekenen is heeft
     * zijn kaarten al vast. Verlopen, geannuleerde en mislukte bestellingen
     * geven hun plek terug.
     */
    public function verkocht(): int
    {
        return (int) $this->lines()
            ->whereHas('order', fn (Builder $q) => $q->where(fn (Builder $sub) => $sub
                ->where('status', Order::STATUS_PAID)
                ->orWhere(fn (Builder $open) => $open
                    ->where('status', Order::STATUS_PENDING)
                    ->where('expires_at', '>', now()))))
            ->sum('quantity');
    }

    /** Wat er nog te koop is, of null als de voorraad onbeperkt is. */
    public function beschikbaar(): ?int
    {
        if ($this->stock === null) {
            return null;
        }

        return max(0, $this->stock - $this->verkocht());
    }

    public function isUitverkocht(): bool
    {
        return $this->beschikbaar() === 0;
    }

    /** Hoeveel iemand er nu maximaal van kan kopen. */
    public function maximumNu(): int
    {
        $over = $this->beschikbaar();

        return $over === null
            ? $this->max_per_order
            : min($this->max_per_order, $over);
    }

    public function scopeActief(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
