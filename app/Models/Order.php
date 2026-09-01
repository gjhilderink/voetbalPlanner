<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Een bestelling uit de ticketshop.
 *
 * Open bestellingen houden voorraad vast tot expires_at; daarna geven ze hem
 * terug. Dat is wat voorkomt dat twee mensen tegelijk voor dezelfde laatste
 * kaart betalen - de reservering gebeurt vóór de betaling, niet erna.
 */
class Order extends Model
{
    use HasUuids;

    public const STATUS_PENDING   = 'pending';
    public const STATUS_PAID      = 'paid';
    public const STATUS_EXPIRED   = 'expired';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_FAILED    = 'failed';

    /** Voor de portal. */
    public const STATUSES = [
        self::STATUS_PENDING   => 'Wacht op betaling',
        self::STATUS_PAID      => 'Betaald',
        self::STATUS_EXPIRED   => 'Verlopen',
        self::STATUS_CANCELLED => 'Geannuleerd',
        self::STATUS_FAILED    => 'Mislukt',
    ];

    /** Hoelang een onbetaalde bestelling zijn kaarten vasthoudt. */
    public const RESERVERING_MINUTEN = 30;

    protected $fillable = [
        'club_id',
        'agenda_item_id',
        'order_number',
        'public_token',
        'buyer_name',
        'buyer_email',
        'total_cents',
        'status',
        'paynl_transaction_id',
        'paid_at',
        'expires_at',
        'mail_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'total_cents'  => 'integer',
            'paid_at'      => 'datetime',
            'expires_at'   => 'datetime',
            'mail_sent_at' => 'datetime',
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

    /** De toegangscodes die bij deze bestelling horen. */
    public function accessCodes(): HasMany
    {
        return $this->hasMany(AccessCode::class);
    }

    public function isBetaald(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function aantalKaarten(): int
    {
        return (int) $this->lines->sum('quantity');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Een kort, voorleesbaar bestelnummer.
     *
     * Zelfde alfabet als de toegangscodes: zonder 0, O, 1 en I, want dit nummer
     * wordt aan de telefoon doorgegeven. Geen doorlopende nummering - die
     * vraagt om een teller met een slot, en niets hier hoeft opeenvolgend te zijn.
     */
    public static function nieuwBestelnummer(): string
    {
        $alfabet = AccessCode::ALFABET;
        $kern    = '';

        for ($i = 0; $i < 6; $i++) {
            $kern .= $alfabet[random_int(0, strlen($alfabet) - 1)];
        }

        return 'VP-' . $kern;
    }

    public static function nieuwToken(): string
    {
        return Str::random(64);
    }

    public function scopeOpenEnVerlopen(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }
}
