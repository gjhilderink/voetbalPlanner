<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Absence;
use App\Models\FootballMatch;
use App\Models\Goal;
use App\Models\Team;
use App\Models\TrainingSchedule;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Seizoenscijfers voor het dashboard: één blok over het team en één over de
 * ingelogde gebruiker zelf.
 *
 * Alles wordt afgeleid uit gegevens die de app al bijhoudt (uitslagen, doelpunten,
 * assists, afmeldingen, trainingsschema's). Twee dingen uit het ontwerp kunnen
 * hier niet uit komen en ontbreken daarom bewust:
 *  - de positie in de competitie: daarvoor is de volledige standenlijst nodig,
 *    en de app kent alleen de eigen wedstrijden;
 *  - fair play: er worden geen kaarten geregistreerd.
 */
class TeamStatsController extends Controller
{
    /** GET /v1/teams/{team}/stats */
    public function show(Request $request, Team $team): JsonResponse
    {
        $user = $request->user();
        if (! $user?->accessibleTeams()->contains('id', $team->id)) {
            return response()->json(['message' => 'Geen toegang tot dit team.'], 403);
        }

        [$from, $until] = self::seasonWindow();

        $matches = FootballMatch::query()
            ->where('team_id', $team->id)
            ->whereBetween('match_datetime', [$from, $until])
            ->get();

        $played = $matches->filter(fn ($m) => self::isPlayed($m));

        $won = $drawn = $lost = 0;
        $goalsFor = $goalsAgainst = 0;
        foreach ($played as $match) {
            [$ours, $theirs] = self::scoreFor($match);
            $goalsFor     += $ours;
            $goalsAgainst += $theirs;
            if ($ours > $theirs) {
                $won++;
            } elseif ($ours === $theirs) {
                $drawn++;
            } else {
                $lost++;
            }
        }

        $memberId = $user->resolveMember()?->id;

        // Wedstrijden waar de gebruiker bij was = gespeelde wedstrijden minus
        // de eigen afmeldingen.
        $matchAbsences = $memberId
            ? Absence::query()
                ->where('type', Absence::TYPE_MATCH)
                ->whereIn('match_id', $played->pluck('id'))
                ->where(fn ($q) => $q->where('member_id', $memberId)->orWhere('user_id', $user->id))
                ->count()
            : 0;

        // Trainingen: de occurrences uit de schema's, van het seizoensbegin tot
        // vandaag. Toekomstige trainingen tellen nog niet mee.
        $trainingDates   = self::trainingDates($team->id, $from, Carbon::today());
        $trainingTotal   = count($trainingDates);
        $trainingAbsences = $memberId || $user
            ? Absence::query()
                ->where('type', Absence::TYPE_TRAINING)
                ->whereBetween('training_date', [$from->toDateString(), Carbon::today()->toDateString()])
                ->whereIn('training_schedule_id', TrainingSchedule::where('team_id', $team->id)->pluck('id'))
                ->where(function ($q) use ($memberId, $user) {
                    $q->where('user_id', $user->id);
                    if ($memberId) {
                        $q->orWhere('member_id', $memberId);
                    }
                })
                ->count()
            : 0;

        $myMatches   = max(0, $played->count() - $matchAbsences);
        $myTrainings = max(0, $trainingTotal - $trainingAbsences);

        $opportunities = $played->count() + $trainingTotal;
        $attended      = $myMatches + $myTrainings;
        $attendance    = $opportunities > 0
            ? (int) round($attended / $opportunities * 100)
            : 0;

        $goals = $memberId
            ? Goal::query()
                ->whereIn('match_id', $played->pluck('id'))
                ->where('scorer_id', $memberId)
                ->where('is_own_goal', false)
                ->count()
            : 0;
        $assists = $memberId
            ? Goal::query()
                ->whereIn('match_id', $played->pluck('id'))
                ->where('assist_id', $memberId)
                ->count()
            : 0;

        $difference = $goalsFor - $goalsAgainst;

        // Alles als string: de app-struct typeert deze velden als String.
        return response()->json([
            'seasonLabel'    => self::seasonLabel($from),
            'played'         => (string) $played->count(),
            'won'            => (string) $won,
            'drawn'          => (string) $drawn,
            'lost'           => (string) $lost,
            'record'         => $won . '-' . $drawn . '-' . $lost,
            'points'         => (string) ($won * 3 + $drawn),
            'goalsFor'       => (string) $goalsFor,
            'goalsAgainst'   => (string) $goalsAgainst,
            'goalDifference' => ($difference > 0 ? '+' : '') . $difference,
            'myMatches'      => (string) $myMatches,
            'myTrainings'    => (string) $myTrainings,
            'myAttendance'   => (string) $attendance,
            'myGoals'        => (string) $goals,
            'myAssists'      => (string) $assists,
        ]);
    }

    /** Voetbalseizoen loopt van 1 juli tot en met 30 juni. */
    private static function seasonWindow(): array
    {
        $now  = Carbon::now();
        $year = $now->month >= 7 ? $now->year : $now->year - 1;

        return [
            Carbon::create($year, 7, 1)->startOfDay(),
            Carbon::create($year + 1, 6, 30)->endOfDay(),
        ];
    }

    private static function seasonLabel(Carbon $from): string
    {
        return $from->year . '/' . ($from->year + 1);
    }

    private static function isPlayed(FootballMatch $match): bool
    {
        if ($match->score_home === null || $match->score_away === null) {
            return false;
        }

        return in_array(
            strtolower((string) $match->status),
            ['played', 'completed', 'finished'],
            true,
        );
    }

    /** @return array{0:int,1:int} eigen doelpunten, tegendoelpunten */
    private static function scoreFor(FootballMatch $match): array
    {
        return $match->is_home
            ? [(int) $match->score_home, (int) $match->score_away]
            : [(int) $match->score_away, (int) $match->score_home];
    }

    /**
     * Alle trainingsdatums van een team tussen twee datums, berekend uit de
     * herhaal-schema's (zelfde aanpak als TrainingController::index).
     *
     * @return list<string>
     */
    private static function trainingDates(string $teamId, Carbon $from, Carbon $until): array
    {
        $schedules = TrainingSchedule::query()
            ->where('team_id', $teamId)
            ->where('is_active', true)
            ->get();

        $dates = [];
        foreach ($schedules as $schedule) {
            $date = $from->copy();
            while ($date->dayOfWeekIso !== $schedule->weekday) {
                $date->addDay();
            }
            for (; $date <= $until; $date->addWeek()) {
                $dates[] = $date->toDateString();
            }
        }

        return $dates;
    }
}
