<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Absence;
use App\Models\TrainingSchedule;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrainingController extends Controller
{
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

        $myMemberId = $request->user()?->resolveMember()?->id;
        $myUserId   = $request->user()?->id;

        // Aantal teamleden (voor 'aangemeld' = leden - afmeldingen). Eén query;
        // alle schema's horen bij hetzelfde team (team_id-filter).
        $team        = $schedules->first()?->team;
        $memberCount = $team ? (int) $team->members()->count() : 0;

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
                    'mijn_status' => $abs->first(fn ($a) =>
                        ($myMemberId && $a->member_id === $myMemberId) ||
                        ($myUserId && $a->user_id === $myUserId)
                    ) ? 'afgemeld' : 'aangemeld',
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
     * POST /v1/trainings/{schedule}/{date}/afmelden   body: { reason }
     */
    public function afmelden(Request $request, TrainingSchedule $schedule, string $date): JsonResponse
    {
        $user   = $request->user();
        $member = $user?->resolveMember();

        $validated = $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        $day = Carbon::parse($date)->toDateString();

        // Een lid hangt aan member_id; een los account (User zonder lidnummer)
        // aan user_id.
        $match = [
            'type'                 => Absence::TYPE_TRAINING,
            'training_schedule_id' => $schedule->id,
            'training_date'        => $day,
        ];
        $match += $member ? ['member_id' => $member->id] : ['user_id' => $user->id];

        Absence::updateOrCreate($match, [
            'club_id' => $schedule->club_id,
            'reason'  => $validated['reason'],
        ]);

        return response()->json(['success' => true, 'message' => 'Je bent afgemeld voor deze training.']);
    }

    /**
     * DELETE /v1/trainings/{schedule}/{date}/afmelden   (= weer aanmelden)
     */
    public function aanmelden(Request $request, TrainingSchedule $schedule, string $date): JsonResponse
    {
        $user   = $request->user();
        $member = $user?->resolveMember();

        $q = Absence::query()
            ->where('type', Absence::TYPE_TRAINING)
            ->where('training_schedule_id', $schedule->id)
            ->whereDate('training_date', Carbon::parse($date)->toDateString());
        $member ? $q->where('member_id', $member->id) : $q->where('user_id', $user->id);
        $q->delete();

        return response()->json(['success' => true, 'message' => 'Je bent weer aangemeld voor deze training.']);
    }
}
