<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Club;
use App\Models\Room;
use App\Models\RoomReservation;
use App\Services\MicrosoftGraphService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * De ruimtes in beide richtingen gelijk houden met Microsoft 365.
 *
 *   php artisan rooms:sync                # alle clubs met de module aan
 *   php artisan rooms:sync --club=Bon     # één club
 *   php artisan rooms:sync --dagen=30     # een langer venster
 *   php artisan rooms:sync --droog        # alleen vertellen wat er zou gebeuren
 *
 * Drie dingen per ronde. Wat hier is vastgelegd maar nog niet in Outlook staat
 * alsnog wegschrijven; wat daar staat en hier niet als externe boeking
 * binnenhalen; en externe boekingen die daar verdwenen zijn hier opruimen.
 *
 * Zonder dit teruglezen kun je in VoetbalPlanner boeken op een ruimte die in
 * Outlook al bezet is - en dat is precies het probleem dat deze module moest
 * oplossen.
 */
class SyncRooms extends Command
{
    protected $signature = 'rooms:sync
        {--club= : Clubnaam of id; standaard alle clubs met de module aan}
        {--dagen=21 : Hoeveel dagen vooruit er wordt gekeken}
        {--droog : Niets wijzigen, alleen tonen wat er zou gebeuren}';

    protected $description = 'Houdt de ruimtereserveringen gelijk met de agenda in Microsoft 365.';

    private bool $droog = false;

    public function handle(MicrosoftGraphService $graph): int
    {
        $this->droog = (bool) $this->option('droog');
        $dagen = max(1, min(120, (int) $this->option('dagen')));

        $clubs = $this->clubs();

        if ($clubs->isEmpty()) {
            $this->info('Geen club met de ruimtemodule aan.');

            return self::SUCCESS;
        }

        foreach ($clubs as $club) {
            $this->syncClub($graph->forClub($club->id), $club, $dagen);
        }

        return self::SUCCESS;
    }

    private function syncClub(MicrosoftGraphService $graph, Club $club, int $dagen): void
    {
        if (! $graph->isConfigured()) {
            $this->line("  {$club->name}: geen Microsoft-koppeling ingesteld, overgeslagen.");

            return;
        }

        $this->info("Club: {$club->name}");

        $van = Carbon::today();
        $tot = Carbon::today()->addDays($dagen)->endOfDay();

        $this->duwen($graph, $club, $van);

        $ruimtes = Room::where('club_id', $club->id)
            ->actief()
            ->whereNotNull('ms_room_email')
            ->get();

        if ($ruimtes->isEmpty()) {
            $this->line('  Geen ruimte met een postbus; niets te lezen.');

            return;
        }

        foreach ($ruimtes as $ruimte) {
            $this->lezen($graph, $ruimte, $van, $tot);
        }
    }

    /**
     * Wat hier vastligt maar nog niet in Outlook staat.
     *
     * Het hoofdpad is het wegschrijven bij het opslaan zelf; dit is het vangnet
     * voor de keren dat Microsoft er toen even uit lag.
     */
    private function duwen(MicrosoftGraphService $graph, Club $club, Carbon $van): void
    {
        $wachtenden = RoomReservation::query()
            ->where('club_id', $club->id)
            ->where('source', '!=', RoomReservation::SOURCE_OUTLOOK)
            ->whereNull('ms_event_id')
            ->bevestigd()
            ->where('ends_at', '>=', $van)
            ->with('room')
            ->get()
            ->filter(fn (RoomReservation $r) => $r->room?->isGekoppeld() === true);

        // Geannuleerd, maar de afspraak staat er nog: die moet daar weg, anders
        // blijft de ruimte in Outlook bezet terwijl hij hier vrij is.
        $ingetrokken = RoomReservation::query()
            ->where('club_id', $club->id)
            ->where('status', RoomReservation::STATUS_GEANNULEERD)
            ->whereNotNull('ms_event_id')
            ->with('room')
            ->get()
            ->filter(fn (RoomReservation $r) => $r->room?->isGekoppeld() === true);

        foreach ($wachtenden as $reservering) {
            if ($this->droog) {
                $this->line("  [droog] zou wegschrijven: {$reservering->title}");
                continue;
            }

            $uitkomst = $graph->maakAfspraak($reservering, $reservering->room->ms_room_email);

            if ($uitkomst['ok']) {
                $reservering->forceFill([
                    'ms_event_id'   => $uitkomst['eventId'] ?? null,
                    'ms_icaluid'    => $uitkomst['icalUid'] ?? null,
                    'ms_synced_at'  => now(),
                    'ms_last_error' => null,
                ])->save();

                $this->line("  weggeschreven: {$reservering->title}");
            } else {
                $reservering->forceFill([
                    'ms_last_error' => mb_substr((string) ($uitkomst['error'] ?? ''), 0, 191),
                ])->save();

                $this->warn("  mislukt: {$reservering->title} — " . ($uitkomst['error'] ?? ''));
            }
        }

        foreach ($ingetrokken as $reservering) {
            if ($this->droog) {
                $this->line("  [droog] zou weghalen: {$reservering->title}");
                continue;
            }

            $uitkomst = $graph->verwijderAfspraak(
                $reservering->ms_event_id,
                $reservering->room->ms_room_email,
            );

            if ($uitkomst['ok']) {
                $reservering->forceFill([
                    'ms_event_id'   => null,
                    'ms_icaluid'    => null,
                    'ms_synced_at'  => now(),
                    'ms_last_error' => null,
                ])->save();

                $this->line("  weggehaald uit Outlook: {$reservering->title}");
            }
        }
    }

    /** De agenda van één ruimte binnenhalen. */
    private function lezen(MicrosoftGraphService $graph, Room $ruimte, Carbon $van, Carbon $tot): void
    {
        $uitkomst = $graph->agenda($ruimte->ms_room_email, $van, $tot);

        if (! $uitkomst['ok']) {
            $this->warn("  {$ruimte->name}: {$uitkomst['error']}");

            return;
        }

        $gezien = [];
        $nieuw  = 0;

        foreach ($uitkomst['events'] ?? [] as $event) {
            $eventId = (string) ($event['id'] ?? '');

            if ($eventId === '' || ($event['isCancelled'] ?? false) === true) {
                continue;
            }

            $gezien[] = $eventId;

            $begin = $this->leesTijd($event['start'] ?? null);
            $eind  = $this->leesTijd($event['end'] ?? null);

            if (! $begin || ! $eind) {
                continue;
            }

            // Is dit onze eigen afspraak? Herkennen op het event-id en anders op
            // de iCalUId: die laatste blijft gelijk als een afspraak tussen
            // postbussen beweegt. Zonder deze stap zouden we onze eigen
            // reserveringen als externe boekingen terugimporteren en dubbel
            // tonen.
            $eigen = RoomReservation::query()
                ->where('room_id', $ruimte->id)
                ->where('source', '!=', RoomReservation::SOURCE_OUTLOOK)
                ->where(fn ($q) => $q
                    ->where('ms_event_id', $eventId)
                    ->orWhere(fn ($x) => $x
                        ->whereNotNull('ms_icaluid')
                        ->where('ms_icaluid', (string) ($event['iCalUId'] ?? '~geen~'))))
                ->first();

            if ($eigen) {
                $this->eigenBijwerken($eigen, $eventId, $begin, $eind);
                continue;
            }

            if ($this->droog) {
                $this->line("  [droog] extern: " . ($event['subject'] ?? '') . ' ' . $begin->format('d-m H:i'));
                continue;
            }

            $bestond = RoomReservation::where('room_id', $ruimte->id)
                ->where('ms_event_id', $eventId)
                ->exists();

            RoomReservation::updateOrCreate(
                ['room_id' => $ruimte->id, 'ms_event_id' => $eventId],
                [
                    'club_id'        => $ruimte->club_id,
                    'title'          => (string) ($event['subject'] ?? 'Bezet'),
                    'requester_name' => (string) ($event['organizer']['emailAddress']['name'] ?? ''),
                    'starts_at'      => $begin,
                    'ends_at'        => $eind,
                    // Privé in Outlook blijft privé hier. Anders zou een
                    // afspraak die daar afgeschermd is bij ons met titel en al
                    // op het scherm staan.
                    'is_private'     => in_array($event['sensitivity'] ?? '', ['private', 'confidential'], true),
                    'status'         => RoomReservation::STATUS_BEVESTIGD,
                    'source'         => RoomReservation::SOURCE_OUTLOOK,
                    'ms_icaluid'     => (string) ($event['iCalUId'] ?? '') ?: null,
                    'ms_synced_at'   => now(),
                ],
            );

            if (! $bestond) {
                $nieuw++;
            }
        }

        $verwijderd = $this->opruimen($ruimte, $van, $tot, $gezien);

        $this->line(sprintf(
            '  %s: %d uit Outlook, %d nieuw, %d verdwenen',
            $ruimte->name,
            count($gezien),
            $nieuw,
            $verwijderd,
        ));

        if (! $this->droog) {
            $ruimte->forceFill(['ms_synced_at' => now()])->save();
        }
    }

    /**
     * Onze eigen afspraak, zoals Outlook hem nu kent.
     *
     * Is hij daar verplaatst, dan nemen we dat over: iemand heeft dat bewust
     * gedaan in de agenda van de ruimte. Behalve als het hier op een andere
     * reservering botst - dan blijft onze tijd staan en komt de botsing in
     * ms_last_error, zodat het zichtbaar is in plaats van dat er stilletjes
     * twee groepen op dezelfde plek terechtkomen.
     */
    private function eigenBijwerken(
        RoomReservation $eigen,
        string $eventId,
        Carbon $begin,
        Carbon $eind,
    ): void {
        if ($this->droog) {
            return;
        }

        $verplaatst = ! $eigen->starts_at?->equalTo($begin) || ! $eigen->ends_at?->equalTo($eind);

        if (! $verplaatst) {
            $eigen->forceFill([
                'ms_event_id'   => $eventId,
                'ms_synced_at'  => now(),
                'ms_last_error' => null,
            ])->save();

            return;
        }

        $botst = RoomReservation::query()
            ->where('room_id', $eigen->room_id)
            ->bevestigd()
            ->overlapt($begin, $eind)
            ->whereKeyNot($eigen->id)
            ->exists();

        if ($botst) {
            $eigen->forceFill([
                'ms_last_error' => 'In Outlook verplaatst naar '
                    . $begin->format('d-m H:i') . ', maar dan is de ruimte hier al bezet.',
            ])->save();

            $this->warn("  botsing na verplaatsing in Outlook: {$eigen->title}");

            return;
        }

        $eigen->forceFill([
            'starts_at'     => $begin,
            'ends_at'       => $eind,
            'ms_event_id'   => $eventId,
            'ms_synced_at'  => now(),
            'ms_last_error' => null,
        ])->save();

        $this->line("  overgenomen uit Outlook: {$eigen->title}");
    }

    /**
     * Externe boekingen die in Outlook zijn verdwenen.
     *
     * Alleen binnen het venster dat we net hebben gelezen, en alleen rijen die
     * uit Outlook kwamen. Wat daarbuiten valt hebben we niet gezien en mag dus
     * niet weg.
     *
     * @param  array<int, string>  $gezien
     */
    private function opruimen(Room $ruimte, Carbon $van, Carbon $tot, array $gezien): int
    {
        if ($this->droog) {
            return 0;
        }

        return RoomReservation::query()
            ->where('room_id', $ruimte->id)
            ->where('source', RoomReservation::SOURCE_OUTLOOK)
            ->overlapt($van, $tot)
            ->when($gezien !== [], fn ($q) => $q->whereNotIn('ms_event_id', $gezien))
            ->delete();
    }

    /**
     * Een tijd uit Graph.
     *
     * Komt binnen als Nederlandse wandkloktijd, omdat de aanroep daarom vraagt
     * met de Prefer-header. Daarom geen tijdzone-omrekening: dat zou hem twee
     * uur verschuiven.
     */
    private function leesTijd(mixed $blok): ?Carbon
    {
        $ruw = is_array($blok) ? ($blok['dateTime'] ?? null) : null;

        if (! is_string($ruw) || $ruw === '') {
            return null;
        }

        try {
            return Carbon::parse(substr($ruw, 0, 19));
        } catch (\Throwable $e) {
            Log::warning('[Ruimtes] onleesbare tijd uit Graph', ['waarde' => $ruw]);

            return null;
        }
    }

    /** @return \Illuminate\Support\Collection<int, Club> */
    private function clubs(): \Illuminate\Support\Collection
    {
        $opgegeven = (string) ($this->option('club') ?? '');

        return Club::query()
            ->where('rooms_enabled', true)
            ->when($opgegeven !== '', fn ($q) => $q
                ->where(fn ($x) => $x
                    ->where('id', $opgegeven)
                    ->orWhere('name', 'like', '%' . $opgegeven . '%')))
            ->orderBy('name')
            ->get();
    }
}
