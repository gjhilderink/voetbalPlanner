<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén meekijker van een live wedstrijdverslag.
 *
 * De rij wordt bij elk poll-verzoek bijgewerkt; `last_seen_at` is dus altijd
 * hooguit tien seconden oud zolang iemand kijkt. Zie LiveMatchService voor het
 * tijdvenster waarbinnen een kijker als aanwezig geldt.
 */
class LiveViewer extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'match_id', 'viewer_key', 'source', 'last_seen_at',
    ];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(FootballMatch::class, 'match_id');
    }
}
