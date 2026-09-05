<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén ruimte, één tijdvak, één gebruiker.
 *
 * Ook de afspraken die rechtstreeks in Outlook zijn gemaakt staan hier, met
 * `source = SOURCE_OUTLOOK`. Eén tabel voor allebei, zodat de vraag "is deze
 * ruimte vrij" één antwoord heeft in plaats van twee die je moet samenvoegen.
 *
 * Geannuleerd blijft staan in plaats van te verdwijnen: de afspraak in Outlook
 * moet nog weggehaald worden, en zonder de rij weten we niet meer welke dat was.
 */
class RoomReservation extends Model
{
    use HasUuids;

    public const STATUS_BEVESTIGD   = 'bevestigd';
    public const STATUS_GEANNULEERD = 'geannuleerd';

    public const SOURCE_PORTAL  = 'portal';
    public const SOURCE_APP     = 'app';
    public const SOURCE_OUTLOOK = 'outlook';

    /** Wat er in Outlook komt te staan bij een privé-reservering. */
    public const PRIVE_TITEL = 'Gereserveerd';

    protected $fillable = [
        'club_id',
        'room_id',
        'agenda_item_id',
        'user_id',
        'requester_name',
        'title',
        'notes',
        'starts_at',
        'ends_at',
        'is_private',
        'status',
        'source',
        'ms_event_id',
        'ms_icaluid',
        'ms_synced_at',
        'ms_last_error',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at'    => 'datetime',
            'ends_at'      => 'datetime',
            'is_private'   => 'boolean',
            'ms_synced_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function agendaItem(): BelongsTo
    {
        return $this->belongsTo(AgendaItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeBevestigd(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_BEVESTIGD);
    }

    /**
     * Alles wat dit tijdvak raakt.
     *
     * Half open aan beide kanten: een reservering van 10 tot 11 botst niet met
     * één van 11 tot 12. Zonder dat zou elke aansluitende boeking geweigerd
     * worden, en juist die wil je in een kantine achter elkaar kunnen zetten.
     */
    public function scopeOverlapt(Builder $query, \DateTimeInterface $begin, \DateTimeInterface $eind): Builder
    {
        return $query->where('starts_at', '<', $eind)
            ->where('ends_at', '>', $begin);
    }

    public function isGeannuleerd(): bool
    {
        return $this->status === self::STATUS_GEANNULEERD;
    }

    /** Komt deze afspraak uit Outlook in plaats van uit VoetbalPlanner? */
    public function isExtern(): bool
    {
        return $this->source === self::SOURCE_OUTLOOK;
    }

    /** Staat hij inmiddels in Microsoft? */
    public function isGesynchroniseerd(): bool
    {
        return filled($this->ms_event_id);
    }

    /**
     * Mag deze kijker zien waar de reservering voor is?
     *
     * Privé betekent: de ruimte is bezet, maar niet waarvoor en door wie. Wie de
     * ruimtes beheert ziet wel alles - die moet immers kunnen plannen - en je
     * eigen reservering zie je altijd.
     */
    public function magDetailsZien(?User $kijker): bool
    {
        if (! $this->is_private) {
            return true;
        }

        if (! $kijker) {
            return false;
        }

        return $kijker->magRuimtesPlannen() || $this->user_id === $kijker->id;
    }

    /** De titel zoals deze kijker hem mag lezen. */
    public function titelVoor(?User $kijker): string
    {
        return $this->magDetailsZien($kijker) ? (string) $this->title : self::PRIVE_TITEL;
    }

    /** De aanvrager zoals deze kijker hem mag lezen; leeg als het privé is. */
    public function aanvragerVoor(?User $kijker): string
    {
        return $this->magDetailsZien($kijker) ? (string) ($this->requester_name ?? '') : '';
    }
}
