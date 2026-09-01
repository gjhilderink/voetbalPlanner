<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Een uitgedeelde toegangscode bij een activiteit.
 *
 * De teller zit op de code zelf en niet in een telling over access_entries: bij
 * de deur moet in één opzoeking duidelijk zijn of er nog gebruik over is, en een
 * teller die per scan wordt opgehoogd binnen dezelfde transactie is daar
 * betrouwbaarder in dan een count die met de lock mee moet.
 */
class AccessCode extends Model
{
    use HasUuids;

    /** Zonder 0/O/1/I: die haalt niemand foutloos over aan de telefoon. */
    public const ALFABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    protected $fillable = [
        'club_id',
        'agenda_item_id',
        'order_id',
        'code',
        'label',
        'max_uses',
        'used_count',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'max_uses'   => 'integer',
            'used_count' => 'integer',
            'is_active'  => 'boolean',
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

    public function entries(): HasMany
    {
        return $this->hasMany(AccessEntry::class);
    }

    /** Gevuld bij een gekocht kaartje; leeg bij een code die de club zelf maakte. */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** Is dit kaartje verkocht via de ticketshop? */
    public function isVerkocht(): bool
    {
        return $this->order_id !== null;
    }

    /** Is er nog gebruik over? */
    public function heeftRuimte(): bool
    {
        return $this->is_active && $this->used_count < $this->max_uses;
    }

    /**
     * Een nieuwe, goed leesbare code van tien tekens.
     *
     * Niet gegarandeerd uniek - dat is de unieke sleutel op activiteit + code,
     * en de aanroeper probeert het gewoon opnieuw bij een botsing.
     */
    public static function nieuweCode(int $lengte = 10): string
    {
        $alfabet = self::ALFABET;
        $code    = '';

        for ($i = 0; $i < $lengte; $i++) {
            $code .= $alfabet[random_int(0, strlen($alfabet) - 1)];
        }

        return $code;
    }
}
