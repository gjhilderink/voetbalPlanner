<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoomResource;
use App\Http\Resources\RoomReservationResource;
use App\Models\Room;
use App\Models\RoomReservation;
use App\Services\RoomReservationService;
use App\Support\Tijd;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ruimtes reserveren vanuit de app.
 *
 * Achter de rol Ruimteplanning én de moduleschakelaar van de club. De routes
 * zelf dragen geen rol-middleware; de grendel zit hier, net als bij de
 * toegangscontrole.
 */
class RoomController extends Controller
{
    /** Wie er mag reserveren. Coach zijn is niet genoeg; dit is een eigen rol. */
    private const ROLLEN = ['super_admin', 'club_admin', 'room-planning'];

    /**
     * GET /v1/rooms
     *
     * Kale array, zoals de agenda: de app leest er rechtstreeks een lijst uit.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeRole();

        $rooms = Room::query()
            ->where('club_id', $request->user()->club_id)
            ->actief()
            ->geordend()
            ->get();

        return response()->json(
            $rooms->map(fn (Room $room) => (new RoomResource($room))->toArray($request))->all(),
        );
    }

    /**
     * GET /v1/rooms/bezetting?datum=dd-mm-jjjj&room_id=
     *
     * Wat er die dag al vastligt. Standaard vandaag, en met room_id beperkt tot
     * één ruimte.
     */
    public function bezetting(Request $request): JsonResponse
    {
        $this->authorizeRole();

        $dag     = $this->leesDatum($request->query('datum'));
        $dagEind = $dag->copy()->addDay();

        $reserveringen = RoomReservation::query()
            ->where('club_id', $request->user()->club_id)
            ->bevestigd()
            ->overlapt($dag, $dagEind)
            ->when($request->query('room_id'), fn ($q, $id) => $q->where('room_id', $id))
            ->with('room:id,name,color')
            ->orderBy('starts_at')
            ->get();

        return response()->json(
            $reserveringen
                ->map(fn (RoomReservation $r) => (new RoomReservationResource($r))->toArray($request))
                ->all(),
        );
    }

    /**
     * GET /v1/rooms/reserveringen
     *
     * De eigen reserveringen, vanaf vandaag. Om te zien wat je hebt vastgelegd
     * en om het weer in te trekken.
     */
    public function mijne(Request $request): JsonResponse
    {
        $this->authorizeRole();

        $reserveringen = RoomReservation::query()
            ->where('club_id', $request->user()->club_id)
            ->where('user_id', $request->user()->id)
            ->bevestigd()
            ->where('ends_at', '>=', now()->startOfDay())
            ->with('room:id,name,color')
            ->orderBy('starts_at')
            ->get();

        return response()->json(
            $reserveringen
                ->map(fn (RoomReservation $r) => (new RoomReservationResource($r))->toArray($request))
                ->all(),
        );
    }

    /**
     * POST /v1/rooms/{room}/reserveren?datum=&van=&tot=&titel=&prive=
     *
     * Query-parameters en geen body: FlutterFlow vult [var] alleen in de URL in,
     * en Laravel leest ze net zo goed.
     */
    public function reserveer(Request $request, Room $room): JsonResponse
    {
        $this->authorizeRole();

        if ($room->club_id !== $request->user()->club_id) {
            return response()->json([
                'success' => false,
                'message' => 'Deze ruimte hoort niet bij jouw club.',
            ], 403);
        }

        $validated = $request->validate([
            'datum' => ['required', 'date_format:d-m-Y'],
            'van'   => ['required', 'string', 'max:8'],
            'tot'   => ['required', 'string', 'max:8'],
            'titel' => ['nullable', 'string', 'max:120'],
            'prive' => ['nullable', 'string'],
        ], [
            'datum.date_format' => 'Kies een datum.',
        ]);

        // Het toetsenbord in de app is numeriek en heeft geen dubbele punt, dus
        // 1900 hoort net zo goed te werken als 19:00.
        foreach (['van' => 'begintijd', 'tot' => 'eindtijd'] as $veld => $woord) {
            $net = Tijd::normaliseer((string) $validated[$veld]);

            if ($net === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vul een ' . $woord . ' in als 1900 of 19:00.',
                ], 422);
            }

            $validated[$veld] = $net;
        }

        $datum = Carbon::createFromFormat('d-m-Y', $validated['datum'])->startOfDay();
        $begin = $datum->copy()->setTimeFromTimeString($validated['van']);
        $eind  = $datum->copy()->setTimeFromTimeString($validated['tot']);

        // Voorbij middernacht hoort op de volgende dag. Een feest van 21:00 tot
        // 01:00 is één reservering en geen invoerfout.
        if ($eind->lessThanOrEqualTo($begin)) {
            $eind->addDay();
        }

        $uitkomst = app(RoomReservationService::class)->reserveer(
            $room,
            $begin,
            $eind,
            $request->user(),
            [
                'title'      => $validated['titel'] ?? '',
                // De app stuurt strings; 'true' is de afspraak, zoals overal.
                'is_private' => ($validated['prive'] ?? '') === 'true',
                'source'     => RoomReservation::SOURCE_APP,
            ],
        );

        if (! $uitkomst['ok']) {
            return response()->json([
                'success' => false,
                'message' => $uitkomst['error'] ?? 'Reserveren is niet gelukt.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => sprintf(
                '%s is voor je vastgelegd van %s tot %s.',
                $room->name,
                $begin->format('H:i'),
                $eind->format('H:i'),
            ),
            'data' => (new RoomReservationResource($uitkomst['reservering']))->toArray($request),
        ]);
    }

    /** POST /v1/rooms/reserveringen/{reservation}/annuleren */
    public function annuleer(Request $request, RoomReservation $reservation): JsonResponse
    {
        $this->authorizeRole();

        $user = $request->user();

        if ($reservation->club_id !== $user->club_id) {
            return response()->json([
                'success' => false,
                'message' => 'Deze reservering hoort niet bij jouw club.',
            ], 403);
        }

        // Een afspraak uit Outlook hoort daar weggehaald te worden; hier zou de
        // eerstvolgende leesronde hem terugzetten.
        if ($reservation->isExtern()) {
            return response()->json([
                'success' => false,
                'message' => 'Deze afspraak komt uit Outlook en moet daar worden afgezegd.',
            ], 422);
        }

        if ($reservation->user_id !== $user->id && ! $user->magRuimtesPlannen()) {
            return response()->json([
                'success' => false,
                'message' => 'Je kunt alleen je eigen reservering intrekken.',
            ], 403);
        }

        app(RoomReservationService::class)->annuleer($reservation);

        return response()->json([
            'success' => true,
            'message' => 'De reservering is ingetrokken.',
        ]);
    }

    /** Vandaag als er niets is meegegeven, en nooit een uitzondering op rommel. */
    private function leesDatum(mixed $ruw): Carbon
    {
        if (! is_string($ruw) || $ruw === '') {
            return Carbon::today();
        }

        try {
            return Carbon::createFromFormat('d-m-Y', $ruw)->startOfDay();
        } catch (\Throwable) {
            return Carbon::today();
        }
    }

    private function authorizeRole(): void
    {
        $user = request()->user();

        if (! $user?->hasAnyRole(self::ROLLEN)) {
            abort(403, 'Geen toegang.');
        }

        if (! $user->club?->rooms_enabled) {
            abort(403, 'Ruimtes staat niet aan voor deze club.');
        }
    }
}
