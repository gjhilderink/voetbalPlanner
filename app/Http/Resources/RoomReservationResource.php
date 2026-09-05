<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\RoomReservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Eén reservering, zoals de app hem mag zien.
 *
 * Hier staat de afscherming van privé, en nergens anders. Een reservering die
 * privé is verlaat de server zonder titel en zonder naam - niet gemaskeerd in
 * de app, want dan zou het nog steeds over de lijn gaan. Elke weg die een
 * reservering teruggeeft loopt hierlangs, zodat een endpoint dat er later
 * bijkomt het niet kan vergeten.
 *
 * Alle waarden als string en datums vooraf opgemaakt, zoals AgendaItemResource:
 * de structs in de app declareren alles als String, en een echte boolean komt
 * daar als null binnen.
 *
 * @mixin RoomReservation
 */
class RoomReservationResource extends JsonResource
{
    /** @return array<string, string> */
    public function toArray(Request $request): array
    {
        $kijker = $request->user();
        $magAlles = $this->magDetailsZien($kijker);

        return [
            'id'       => (string) $this->id,
            'roomId'   => (string) $this->room_id,
            'roomName' => (string) ($this->room?->name ?? ''),

            'title'     => $this->titelVoor($kijker),
            'requester' => $this->aanvragerVoor($kijker),
            // De opmerking hoort bij de titel: privé is privé.
            'notes'     => $magAlles ? (string) ($this->notes ?? '') : '',

            'date'      => $this->starts_at?->format('d-m-Y') ?? '',
            'startTime' => $this->starts_at?->format('H:i') ?? '',
            'endTime'   => $this->ends_at?->format('H:i') ?? '',
            'dateLabel' => $this->starts_at?->locale('nl')->isoFormat('dddd D MMMM') ?? '',
            'timeLabel' => trim(sprintf(
                '%s - %s',
                $this->starts_at?->format('H:i') ?? '',
                $this->ends_at?->format('H:i') ?? '',
            ), ' -'),

            'isPrivate' => self::boolText((bool) $this->is_private),
            // Komt hij uit Outlook? Dan valt hij hier niet aan te passen.
            'isExtern'  => self::boolText($this->isExtern()),
            'isMine'    => self::boolText($kijker && $this->user_id === $kijker->id),
            'canCancel' => self::boolText($this->magAnnuleren($kijker)),

            'agendaItemId' => (string) ($this->agenda_item_id ?? ''),
            'status'       => (string) $this->status,
            'melding'      => '',
        ];
    }

    /**
     * Mag deze kijker de reservering intrekken?
     *
     * Je eigen reservering altijd; die van een ander alleen als je de ruimtes
     * beheert. Een afspraak uit Outlook nooit - die hoort daar weggehaald te
     * worden, want de eerstvolgende leesronde zet hem hier anders terug.
     */
    private function magAnnuleren(?\App\Models\User $kijker): bool
    {
        if (! $kijker || $this->isExtern() || $this->isGeannuleerd()) {
            return false;
        }

        return $this->user_id === $kijker->id || $kijker->magRuimtesPlannen();
    }

    private static function boolText(bool $waarde): string
    {
        return $waarde ? 'true' : 'false';
    }
}
