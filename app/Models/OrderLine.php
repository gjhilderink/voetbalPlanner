<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén regel van een bestelling: zoveel kaarten van deze soort.
 *
 * Naam en prijs staan hier als momentopname, los van de kaartsoort. Past de
 * club later de prijs aan of gooit hij de soort weg, dan blijft zichtbaar wat
 * er destijds verkocht is.
 */
class OrderLine extends Model
{
    use HasUuids;

    protected $fillable = [
        'order_id',
        'ticket_type_id',
        'type_name',
        'unit_price_cents',
        'quantity',
        'line_total_cents',
    ];

    protected function casts(): array
    {
        return [
            'unit_price_cents' => 'integer',
            'quantity'         => 'integer',
            'line_total_cents' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }
}
