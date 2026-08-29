<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ManagesAttendance;
use App\Http\Controllers\Controller;
use App\Models\Absence;
use App\Models\TrainingCancellation;
use App\Models\TrainingSchedule;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrainingController extends Controller
{
    use ManagesAttendance;

    /**
     * GET /v1/trainings?team_id=&days=21
     * Komende training-occurrences (berekend uit de herhaal-schema's) voor een team.
     */
    public function index(Request $request): JsonResponse
    {
        // ?: en niet ??: de app stuurt team_id altijd mee, ook leeg. Met ?? zou
        // een lege string blijven staan, de fallbacks overslaan en hieronder een
        // lege lijst opleveren — het dashboard toonde dan de kop zonder
        // trainingen. resolveMember() i.p.v. member: dat vangt ook leden die
        // alleen via e-mail aan hun account hangen (zelfde als afmelden()).
        $teamId = $request->query('team_id') ?: null;
        $teamId = $teamId
            ?: $request->user()?->resolveMember()?->teams()->first()?->id
            ?: $request->user()?->managedTeams()->first()?->id;

        if (!$teamId) {
            return response()->json([]);
        }

        $days  = (int) ($request->query('days', 21));
        $days  = max(1, min($days, 90));
        $start = Carbon::today();
        $end   = $start->copy()->addDays($days);

        $schedules = TrainingSchedule::query()
            ->where('team_id', $teamId)
            ->where('is_active', true)
            ->with('team:id,name')
            ->get();

        if ($schedules->isEmpty()) {
            return response()->json([]);
        }

        // Alle afmeldingen voor deze schema's binnen het venster, in één query.
        $absences = Absence::query()
            ->where('type', Absence::TYPE_TRAINING)
            ->whereIn('training_schedule_id', $schedules->pluck('id'))
            ->whereBetween('training_date', [$start->toDateString(), $end->toDateString()])
            ->with(['member:id,name', 'user:id,name'])
            ->get()
            ->groupBy(fn ($a) => $a->training_schedule_id . '|' . $a->training_date->toDateString());

        // Afgelaste trainingen binnen hetzelfde venster, in één query. Geen rij
        // betekent: gaat door.
        $afgelast = TrainingCancellation::query()
            ->whereIn('training_schedule_id', $schedules->pluck('id'))
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn ($c) => $c->training_schedule_id . '|' . $c->date->toDateString());

        // Mag deze gebruiker afgelasten? Alle schema's horen bij hetzelfde
        // elftal, dus één keer bepalen is genoeg.
        $magAfgelasten = $request->user()?->canManageLineup($teamId) ?? false;

        $myMemberId = $request->user()?->resolveMember()?->id;
        $myUserId   = $request->user()?->id;

        // Wiens status staat er op de kaart? Voor een speler die van hemzelf,
        // voor een ouder die van zijn kinderen in dit elftal. Eén keer bepaald
        // en niet per training, want het kost twee queries.
        $benLid  = $request->user()?->belongsToTeam($teamId) ?? false;
        $kindIds = $benLid
            ? collect()
            : $this->attendanceChildren($request, $teamId)->pluck('id');

        // Aantal teamleden (voor 'aangemeld' = leden - afmeldingen). Eén query;
        // alle schema's horen bij hetzelfde team (team_id-filter).
        //
        // Alleen spelers, net als de deelnemerslijst: telde dit de trainers mee,
        // dan zou de kaart een hoger aantal tonen dan er namen onder staan.
        $team        = $schedules->first()?->team;
        $memberCount = $team ? (int) $team->playingMembers()->count() : 0;

        $occurrences = [];
        foreach ($schedules as $schedule) {
            // Eerste datum >= start die op de juiste weekdag valt.
            $date = $start->copy();
            while ($date->dayOfWeekIso !== $schedule->weekday) {
                $date->addDay();
            }
            for (; $date <= $end; $date->addWeek()) {
                $key   = $schedule->id . '|' . $date->toDateString();
                $abs   = $absences->get($key) ?? collect();
                $stop  = $afgelast->get($key);
                $occurrences[] = [
                    'schedule_id' => $schedule->id,
                    'date'        => $date->toDateString(),
                    'weekday'     => $schedule->weekday,
                    'day_label'   => TrainingSchedule::$weekdayLabels[$schedule->weekday] ?? '',
                    'start_time'  => substr((string) $schedule->start_time, 0, 5),
                    'end_time'    => $schedule->end_time ? substr((string) $schedule->end_time, 0, 5) : '',
                    'location'      => $schedule->location ?? '',
                    'dressing_room' => $schedule->dressing_room ?? '',
                    'team_name'   => $schedule->team?->name ?? '',
                    // Strings en geen booleans: de app-struct is volledig String.
                    'is_afgelast'    => $stop ? 'true' : 'false',
                    'afgelast_reden' => (string) ($stop?->reason ?? ''),
                    'mag_afgelasten' => $magAfgelasten ? 'true' : 'false',
                    'mijn_status' => self::statusVoor(
                        $abs, $benLid, $myMemberId, $myUserId, $kindIds
                    ),
                    // Telling voor de status-iconen op de kaart.
                    'afgemeld'    => (string) $abs->count(),
                    'aangemeld'   => (string) max(0, $memberCount - $abs->count()),
                    'afmeldingen' => $abs->map(fn ($a) => [
                        'naam'  => $a->member?->name ?? $a->user?->name ?? '',
                        'reden' => $a->reason,
                    ])->values(),
                ];
            }
        }

        // Sorteer op datum + begintijd.
        usort($occurrences, fn ($a, $b) => [$a['date'], $a['start_time']] <=> [$b['date'], $b['start_time']]);

        // Optioneel: beperk tot de eerstvolgende N (bijv. dashboard toont er 2).
        $limit = (int) $request->query('limit', 0);
        if ($limit > 0) {
            $occurrences = array_slice($occurrences, 0, $limit);
        }

        return response()->json($occurrences);
    }

    /**
     * GET /v1/trainings/{schedule}/{date}/deelnemers
     *
     * Alle teamleden voor één training, elk met status 'aangemeld' of
     * 'afgemeld'. Eén platte lijst en geen twee aparte lijsten: de app rendert
     * er twee secties uit met een filter op status, en dat scheelt een
     * geneste struct.
     *
     * Bedoeld voor de trainer, die in één oogopslag wil zien wie er komt.
     */
    public function deelnemers(Request $request, TrainingSchedule $schedule, string $date): JsonResponse
    {
        $user = $request->user();
        if (! $user?->accessibleTeams()->contains('id', $schedule->team_id)) {
            return response()->json(['message' => 'Geen toegang tot dit team.'], 403);
        }

        $day = Carbon::parse($date)->toDateString();

        $absences = Absence::query()
            ->where('type', Absence::TYPE_TRAINING)
            ->where('training_schedule_id', $schedule->id)
            ->whereDate('training_date', $day)
            ->with(['member:id,name', 'user:id,name'])
            ->get();

        // Afmeldingen op lidnummer, zodat ze te koppelen zijn aan de teamleden.
        $reasonByMember = $absences
            ->filter(fn ($a) => $a->member_id !== null)
            ->mapWithKeys(fn ($a) => [$a->member_id => (string) $a->reason]);

        $rows = [];
        // Alleen spelers, net als bij een wedstrijd: een trainer staat niet in
        // de opkomstlijst en meldt zich ook niet af.
        foreach ($schedule->team?->playingMembers()->orderBy('members.name')->get() ?? collect() as $member) {
            $isAfgemeld = $reasonByMember->has($member->id);
            $rows[] = [
                // De coach meldt hiermee iemand namens hem af of aan.
                'memberId' => $member->id,
                'naam'   => $member->name,
                'status' => $isAfgemeld ? 'afgemeld' : 'aangemeld',
                'reden'  => $isAfgemeld ? $reasonByMember->get($member->id) : '',
            ];
        }

        // Afmeldingen van losse accounts (geen lid) staan niet in de teamlijst,
        // maar horen er wel bij te staan — anders mist de trainer ze.
        foreach ($absences->filter(fn ($a) => $a->member_id === null) as $absence) {
            $naam = $absence->user?->name;
            if (! $naam) {
                continue;
            }
            $rows[] = [
                // Zonder lidnummer valt er niets om te zetten; lege memberId.
                'memberId' => '',
                'naam'   => $naam,
                'status' => 'afgemeld',
                'reden'  => (string) $absence->reason,
            ];
        }

        // Aangemeld eerst, daarbinnen op naam — dezelfde volgorde als de app toont.
        usort($rows, fn ($a, $b) => [$a['status'], $a['naam']] <=> [$b['status'], $b['naam']]);

        // De tellingen staan op elke regel. Dat is dubbelop, maar de app kan een
        // gefilterde lijst niet tellen; zo kan de kop "Aanwezig (11)" tonen
        // zonder een tweede endpoint.
        $aangemeld = count(array_filter($rows, fn ($r) => $r['status'] === 'aangemeld'));
        $afgemeld  = count($rows) - $aangemeld;
        // Mag deze gebruiker anderen af- en aanmelden? Op elke regel, zodat de
        // app de knoppen per speler kan tonen zonder een extra call.
        $mag = $request->user()?->canManageLineup($schedule->team_id) ? 'true' : 'false';

        $rows = array_map(fn ($r) => $r + [
            'aantalAangemeld' => (string) $aangemeld,
            'aantalAfgemeld'  => (string) $afgemeld,
            // Ook het totaal, zodat de kop "13 / 15" kan tonen zonder dat de
            // app twee getallen uit strings hoeft op te tellen.
            'aantalTotaal'    => (string) ($aangemeld + $afgemeld),
            'magBeheren'      => $mag,
        ], $rows);

        return response()->json($rows);
    }

    /**
     * POST /v1/trainings/{schedule}/{date}/afmelden   body: { reason }
     */
    /**
     * POST /v1/trainings/{schedule}/{date}/afgelasten   body: { reason }
     *
     * Eén training gaat niet door. Het schema blijft staan: volgende week is er
     * gewoon weer training, en dat is precies waarom dit een uitzondering per
     * datum is en geen schakelaar op het schema.
     */
    public function afgelasten(Request $request, TrainingSchedule $schedule, string $date): JsonResponse
    {
        if (! $request->user()?->canManageLineup($schedule->team_id)) {
            return response()->json([
                'success' => false,
                'melding' => 'Alleen de trainer kan een training afgelasten.',
            ], 403);
        }

        $validated = $request->validate(['reason' => 'required|string|max:255']);

        TrainingCancellation::updateOrCreate(
            [
                'training_schedule_id' => $schedule->id,
                'date'                 => Carbon::parse($date)->toDateString(),
            ],
            [
                'reason'               => $validated['reason'],
                'cancelled_by_user_id' => $request->user()->id,
            ],
        );

        return response()->json([
            'success' => true,
            'melding' => 'De training is afgelast.',
        ]);
    }

    /** POST /v1/trainings/{schedule}/{date}/vrijgeven — gaat toch weer door. */
    public function vrijgeven(Request $request, TrainingSchedule $schedule, string $date): JsonResponse
    {
        if (! $request->user()?->canManageLineup($schedule->team_id)) {
            return response()->json([
                'success' => false,
                'melding' => 'Alleen de trainer kan een training vrijgeven.',
            ], 403);
        }

        TrainingCancellation::query()
            ->where('training_schedule_id', $schedule->id)
            ->whereDate('date', Carbon::parse($date)->toDateString())
            ->delete();

        return response()->json([
            'success' => true,
            'melding' => 'De training gaat weer door.',
        ]);
    }

    public function afmelden(Request $request, TrainingSchedule $schedule, string $date): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:255',
            // Alleen de coach mag deze meegeven; zie ManagesAttendance.
            'member_id' => 'nullable|uuid',
        ]);

        [$leden, $fout] = $this->attendanceTargets($request, $schedule->team_id);
        if ($fout) {
            return $fout;
        }

        $day = Carbon::parse($date)->toDateString();

        foreach ($leden as $lid) {
            // Een lid hangt aan member_id; een los account (User zonder
            // lidnummer) aan user_id.
            Absence::updateOrCreate($this->attendanceKey([
                'type'                 => Absence::TYPE_TRAINING,
                'training_schedule_id' => $schedule->id,
                'training_date'        => $day,
            ], $lid, $request), [
                'club_id' => $schedule->club_id,
                'reason'  => $validated['reason'],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => $this->attendanceMessage($request, $leden, 'afgemeld', 'deze training'),
        ]);
    }

    /**
     * DELETE /v1/trainings/{schedule}/{date}/afmelden   (= weer aanmelden)
     */
    public function aanmelden(Request $request, TrainingSchedule $schedule, string $date): JsonResponse
    {
        $request->validate(['member_id' => 'nullable|uuid']);

        [$leden, $fout] = $this->attendanceTargets($request, $schedule->team_id);
        if ($fout) {
            return $fout;
        }

        foreach ($leden as $lid) {
            Absence::query()
                ->where($this->attendanceKey([
                    'type'                 => Absence::TYPE_TRAINING,
                    'training_schedule_id' => $schedule->id,
                ], $lid, $request))
                ->whereDate('training_date', Carbon::parse($date)->toDateString())
                ->delete();
        }

        return response()->json([
            'success' => true,
            'message' => $this->attendanceMessage($request, $leden, 'weer aangemeld', 'deze training'),
        ]);
    }
}
