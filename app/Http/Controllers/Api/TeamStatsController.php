<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Absence;
use App\Models\FootballMatch;
use App\Models\Goal;
use App\Models\MatchEvent;
use App\Models\Team;
use App\Models\TrainingSchedule;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Seizoenscijfers voor het dashboard: één blok over het team en één over de
 * ingelogde gebruiker zelf.
 *
 * Het meeste wordt afgeleid uit gegevens die de app al bijhoudt (uitslagen,
 * doelpunten, assists, afmeldingen, trainingsschema's). De competitiepositie kan
 * daar niet uit komen — de app kent alleen de eigen wedstrijden — en komt daarom
 * uit de poulestand. Die telt ook wedstrijden mee die de app niet kent, dus de
 * cijfers uit de stand staan náást de eigen telling en niet in plaats daarvan.
 */
class TeamStatsController extends Controller
{
    public function __construct(private readonly \App\Services\StandingService $standen)
    {
    }

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

        // Doelpunten en assists komen uit het live verslag: dat is de plek waar
        // een wedstrijd echt wordt vastgelegd, mét minuut en assist.
        //
        // Doelpunten die alleen via het tabblad Doelpunten zijn ingevoerd tellen
        // er los bij op. Zonder dat zouden de cijfers van iedereen kelderen zodra
        // er een keer geen verslag is bijgehouden, en dat leest als verlies van
        // gegevens. Een live vastgelegd doelpunt maakt óók een Goal-rij aan, en
        // die is via goal_id aan de gebeurtenis gekoppeld — daarop filteren we,
        // zodat er niets dubbel wordt geteld.
        // Alle wedstrijden van het seizoen en niet alleen de afgeronde: een
        // doelpunt dat in het live verslag staat is gemaakt, ook als de coach
        // vergat op "Einde" te drukken en er dus nooit een uitslag is
        // weggeschreven. Dat kostte spelers hun doelpunten.
        $wedstrijdIds = $matches->pluck('id');

        $uitVerslag = fn (string $veld) => $memberId
            ? MatchEvent::query()
                ->whereIn('match_id', $wedstrijdIds)
                ->where('type', MatchEvent::TYPE_GOAL)
                ->where('side', MatchEvent::SIDE_OWN)
                ->where($veld, $memberId)
                ->count()
            : 0;

        $losseDoelpunten = $memberId
            ? Goal::query()
                ->whereIn('match_id', $wedstrijdIds)
                ->where('scorer_id', $memberId)
                ->where('is_own_goal', false)
                ->whereNotIn('id', MatchEvent::query()
                    ->whereIn('match_id', $wedstrijdIds)
                    ->whereNotNull('goal_id')
                    ->select('goal_id'))
                ->count()
            : 0;

        $losseAssists = $memberId
            ? Goal::query()
                ->whereIn('match_id', $wedstrijdIds)
                ->where('assist_id', $memberId)
                ->whereNotIn('id', MatchEvent::query()
                    ->whereIn('match_id', $wedstrijdIds)
                    ->whereNotNull('goal_id')
                    ->select('goal_id'))
                ->count()
            : 0;

        $goals   = $uitVerslag('member_id') + $losseDoelpunten;
        $assists = $uitVerslag('related_member_id') + $losseAssists;

        // Kaarten bestaan alleen in het live verslag; anders dan bij doelpunten
        // is er geen tweede plek waar ze vandaan kunnen komen. Een wedstrijd
        // zonder verslag telt dus als nul kaarten.
        $kaarten = $memberId
            ? MatchEvent::query()
                ->whereIn('match_id', $wedstrijdIds)
                ->where('type', MatchEvent::TYPE_CARD)
                ->where('member_id', $memberId)
                ->selectRaw('card_type, count(*) as aantal')
                ->groupBy('card_type')
                ->pluck('aantal', 'card_type')
            : collect();

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
            'myYellowCards'  => (string) ($kaarten[MatchEvent::CARD_YELLOW] ?? 0),
            'myRedCards'     => (string) ($kaarten[MatchEvent::CARD_RED] ?? 0),
        ] + $this->standCijfers($team));
    }

    /**
     * De officiële cijfers uit de poulestand.
     *
     * standingAvailable zegt of er iets te tonen valt; zonder die vlag zou het
     * dashboard "positie 0 van 0" laten zien zodra de stand ontbreekt, en dat
     * leest als een slechte seizoenstart in plaats van als ontbrekende data.
     *
     * @return array<string, string>
     */
    private function standCijfers(Team $team): array
    {
        $stand = $this->standen->forTeam($team);
        $eigen = null;
        foreach ($stand['rijen'] as $rij) {
            if (($rij['isEigenTeam'] ?? 'false') === 'true') {
                $eigen = $rij;
                break;
            }
        }

        if (! $eigen) {
            return [
                'standingAvailable'      => 'false',
                'standingPosition'       => '',
                'standingTeams'          => '',
                'standingPlayed'         => '',
                'standingPoints'         => '',
                'standingGoalDifference' => '',
                'standingMessage'        => $stand['melding'],
            ];
        }

        $saldo = $eigen['doelsaldo'] ?? '';

        return [
            'standingAvailable'      => 'true',
            'standingPosition'       => $eigen['positie'] ?? '',
            'standingTeams'          => (string) count($stand['rijen']),
            'standingPlayed'         => $eigen['gespeeld'] ?? '',
            'standingPoints'         => $eigen['punten'] ?? '',
            // Een plusteken erbij; zonder teken leest "3" als een aantal in
            // plaats van als een saldo.
            'standingGoalDifference' => ($saldo !== '' && (int) $saldo > 0) ? '+' . $saldo : $saldo,
            'standingMessage'        => '',
        ];
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
        if ($match->isCancelled()) {
            return false;
        }
        if ($match->score_home === null || $match->score_away === null) {
            return false;
        }

        // Een uitslag én de wedstrijd is geweest: dan is hij gespeeld.
        //
        // De status er óók bij eisen was te streng. Die komt alleen op 'played'
        // te staan als Sportlink de wedstrijd via de uitslagen aanlevert of
        // letterlijk "Uitgespeeld" meldt. Een oefenwedstrijd, een handmatig
        // ingevoerde wedstrijd en een wedstrijd waarvan de coach de uitslag zelf
        // invulde bleven zo buiten élk cijfer op het dashboard - terwijl de
        // uitslag er gewoon bij stond.
        if (in_array(strtolower((string) $match->status),
                ['played', 'completed', 'finished'], true)) {
            return true;
        }

        return $match->match_datetime === null || $match->match_datetime->isPast();
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
