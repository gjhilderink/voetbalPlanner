<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Room;
use App\Models\RoomReservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Ruimtes vastleggen, verplaatsen en vrijgeven.
 *
 * De enige plek waar een reservering ontstaat of verschuift. De portal, de app
 * en het teruglezen uit Outlook gaan er allemaal doorheen, zodat de controle op
 * dubbele boekingen op één plek staat en niet op drie plekken half.
 *
 * Foutmeldingen komen als array terug in plaats van als uitzondering, net als
 * bij PayNlService en WhatsAppService: een bezette ruimte is een antwoord, geen
 * storing.
 */
class RoomReservationService
{
    /**
     * Leg een reservering vast.
     *
     * @param  array<string, mixed>  $gegevens  title, notes, is_private, agenda_item_id, source
     * @return array{ok: bool, reservering?: RoomReservation, error?: string}
     */
    public function reserveer(
        Room $room,
        Carbon $begin,
        Carbon $eind,
        ?User $aanvrager,
        array $gegevens = [],
    ): array {
        if ($eind->lessThanOrEqualTo($begin)) {
            return ['ok' => false, 'error' => 'De eindtijd moet na de begintijd liggen.'];
        }

        if (! $room->is_active) {
            return ['ok' => false, 'error' => 'Deze ruimte is niet in gebruik.'];
        }

        $reservering = DB::transaction(function () use ($room, $begin, $eind, $aanvrager, $gegevens) {
            if ($this->botst($room->id, $begin, $eind)) {
                return null;
            }

            return RoomReservation::create([
                'club_id'        => $room->club_id,
                'room_id'        => $room->id,
                'agenda_item_id' => $gegevens['agenda_item_id'] ?? null,
                'user_id'        => $aanvrager?->id,
                'requester_name' => $aanvrager?->name,
                'title'          => trim((string) ($gegevens['title'] ?? '')) ?: 'Reservering',
                'notes'          => $gegevens['notes'] ?? null,
                'starts_at'      => $begin,
                'ends_at'        => $eind,
                'is_private'     => (bool) ($gegevens['is_private'] ?? false),
                'status'         => RoomReservation::STATUS_BEVESTIGD,
                'source'         => $gegevens['source'] ?? RoomReservation::SOURCE_PORTAL,
            ]);
        });

        if (! $reservering) {
            return ['ok' => false, 'error' => $this->bezetMelding($room, $begin, $eind)];
        }

        $this->naarMicrosoft($reservering, $room);

        return ['ok' => true, 'reservering' => $reservering->refresh()];
    }

    /**
     * Verplaats of verleng een bestaande reservering.
     *
     * @return array{ok: bool, reservering?: RoomReservation, error?: string}
     */
    public function verplaats(RoomReservation $reservering, Carbon $begin, Carbon $eind): array
    {
        if ($eind->lessThanOrEqualTo($begin)) {
            return ['ok' => false, 'error' => 'De eindtijd moet na de begintijd liggen.'];
        }

        $gelukt = DB::transaction(function () use ($reservering, $begin, $eind) {
            if ($this->botst($reservering->room_id, $begin, $eind, $reservering->id)) {
                return false;
            }

            $reservering->update([
                'starts_at'    => $begin,
                'ends_at'      => $eind,
                // De afspraak in Outlook klopt nu niet meer; het commando pakt
                // hem opnieuw op.
                'ms_synced_at' => null,
            ]);

            return true;
        });

        if (! $gelukt) {
            return ['ok' => false, 'error' => 'Op dat tijdstip is de ruimte al bezet.'];
        }

        $this->naarMicrosoft($reservering->refresh(), $reservering->room);

        return ['ok' => true, 'reservering' => $reservering->refresh()];
    }

    /**
     * Annuleren, niet verwijderen.
     *
     * De rij blijft staan omdat de afspraak in Outlook nog weg moet. Zonder de
     * rij weten we niet meer welke dat was, en dan blijft de ruimte daar bezet
     * terwijl hij hier vrij is.
     */
    public function annuleer(RoomReservation $reservering): void
    {
        if ($reservering->isGeannuleerd()) {
            return;
        }

        $reservering->update([
            'status'       => RoomReservation::STATUS_GEANNULEERD,
            'cancelled_at' => now(),
            'ms_synced_at' => null,
        ]);

        $this->uitMicrosoft($reservering);
    }

    /**
     * De reservering in de agenda van de ruimte zetten of bijwerken.
     *
     * Buiten de transactie, met opzet. Een HTTP-aanroep binnen DB::transaction
     * houdt het slot op de ruimte vast zolang die aanroep duurt, en gijzelt
     * daarmee elke andere boeking van diezelfde ruimte.
     *
     * Mislukken is niet fataal. De reservering staat er en de ruimte is bij ons
     * bezet; alleen Outlook loopt achter. De reden gaat in ms_last_error, de
     * portal toont daar een melding bij, en het commando rooms:sync probeert het
     * opnieuw. Dezelfde afweging als bij de ticketmail: wat vastligt mag niet
     * stranden op een dienst van een ander.
     */
    private function naarMicrosoft(RoomReservation $reservering, ?Room $room): void
    {
        if (! $room?->isGekoppeld() || $reservering->isExtern()) {
            return;
        }

        $graph = app(MicrosoftGraphService::class)->forClub($reservering->club_id);

        if (! $graph->isConfigured()) {
            return;
        }

        $uitkomst = $reservering->ms_event_id
            ? $graph->wijzigAfspraak($reservering, $room->ms_room_email)
            : $graph->maakAfspraak($reservering, $room->ms_room_email);

        if (! $uitkomst['ok']) {
            $reservering->forceFill([
                'ms_last_error' => mb_substr((string) ($uitkomst['error'] ?? ''), 0, 191),
                'ms_synced_at'  => null,
            ])->save();

            return;
        }

        $reservering->forceFill(array_filter([
            'ms_event_id'   => $uitkomst['eventId'] ?? $reservering->ms_event_id,
            'ms_icaluid'    => $uitkomst['icalUid'] ?? $reservering->ms_icaluid,
        ]) + [
            'ms_synced_at'  => now(),
            'ms_last_error' => null,
        ])->save();
    }

    /** De afspraak weer uit de agenda halen. Zelfde afweging als hierboven. */
    private function uitMicrosoft(RoomReservation $reservering): void
    {
        $room = $reservering->room;

        if (! $reservering->ms_event_id || ! $room?->isGekoppeld()) {
            return;
        }

        $graph = app(MicrosoftGraphService::class)->forClub($reservering->club_id);

        if (! $graph->isConfigured()) {
            return;
        }

        $uitkomst = $graph->verwijderAfspraak($reservering->ms_event_id, $room->ms_room_email);

        $reservering->forceFill($uitkomst['ok']
            ? ['ms_event_id' => null, 'ms_icaluid' => null, 'ms_synced_at' => now(), 'ms_last_error' => null]
            : ['ms_last_error' => mb_substr((string) ($uitkomst['error'] ?? ''), 0, 191)])
            ->save();
    }

    /**
     * Is er iets dat dit tijdvak raakt?
     *
     * Publiek, zodat een formulier vooraf kan waarschuwen. De echte grendel
     * blijft de controle binnen de transactie hierboven: tussen "kijken" en
     * "vastleggen" kan een ander er precies tussen komen.
     */
    public function isVrij(string $roomId, Carbon $begin, Carbon $eind, ?string $negeerId = null): bool
    {
        return ! RoomReservation::query()
            ->where('room_id', $roomId)
            ->bevestigd()
            ->overlapt($begin, $eind)
            ->when($negeerId, fn ($q) => $q->whereKeyNot($negeerId))
            ->exists();
    }

    /**
     * De overlapcontrole binnen de transactie.
     *
     * Eerst een slot op de ruimte zelf, en niet op de reserveringen. Elke
     * boeking voor deze ruimte komt hier langs, dus dat ene slot zet ze
     * betrouwbaar achter elkaar. Een slot op een reeks rijen zou leunen op
     * gap-locks, en die gelden alleen op rijen die er al zijn - precies het
     * geval dat we willen voorkomen. Dezelfde aanpak als OrderService, die de
     * kaartsoort grendelt en niet de bestelregels.
     */
    private function botst(string $roomId, Carbon $begin, Carbon $eind, ?string $negeerId = null): bool
    {
        Room::whereKey($roomId)->lockForUpdate()->first();

        return RoomReservation::query()
            ->where('room_id', $roomId)
            ->bevestigd()
            ->overlapt($begin, $eind)
            ->when($negeerId, fn ($q) => $q->whereKeyNot($negeerId))
            ->exists();
    }

    /**
     * Waarom het niet kon, met de tijden erbij.
     *
     * "Bezet" alleen laat iemand zoeken; met het tijdvak erbij weet hij meteen
     * waar hij omheen moet plannen. Bij een privé-reservering blijft de titel
     * weg - de melding hoort niet te vertellen wat de deur dichthoudt.
     */
    private function bezetMelding(Room $room, Carbon $begin, Carbon $eind): string
    {
        $bezet = RoomReservation::query()
            ->where('room_id', $room->id)
            ->bevestigd()
            ->overlapt($begin, $eind)
            ->orderBy('starts_at')
            ->first();

        if (! $bezet) {
            return 'Deze ruimte is op dat tijdstip al bezet.';
        }

        return sprintf(
            '%s is dan al bezet, van %s tot %s.',
            $room->name,
            $bezet->starts_at?->format('H:i') ?? '',
            $bezet->ends_at?->format('H:i') ?? '',
        );
    }
}
