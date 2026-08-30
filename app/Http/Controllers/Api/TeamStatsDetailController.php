<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Absence;
use App\Models\FootballMatch;
use App\Models\Goal;
use App\Models\MatchEvent;
use App\Models\Member;
use App\Models\Team;
use App\Models\TrainingSchedule;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * De cijfers van een heel elftal over het seizoen, voor de coach.
 *
 * Het dashboard toont per gebruiker één blok met zijn eigen cijfers. Dat zegt de
 * coach weinig: die wil weten wie er scoort, wie er kaarten pakt en wie er
 * structureel niet is. Dit is dat overzicht.
 *
 * Vorm: platte regels (kop of label met waarde), net als de wedstrijdstatistiek.
 * De app rendert er één lijst uit, en er kan een categorie bij zonder dat de app
 * mee hoeft te veranderen.
 */
class TeamStatsDetailController extends Controller
{
    /** GET /v1/teams/{team}/team-stats */
    public function show(Request $request, Team $team): JsonResponse
    {
        // Alleen wie het elftal beheert. Dit zijn cijfers over andere mensen -
        // wie er weinig scoort en wie er vaak afwezig is - en dat gaat een
        // medespeler niets aan.
        if (! $request->user()?->canManageLineup($team->id)) {
            return response()->json(
                [self::leeg('Alleen de coach of trainer ziet de teamcijfers.')],
                403,
            );
        }

        [$from, $until] = self::seizoen();

        $wedstrijden = FootballMatch::query()
            ->where('team_id', $team->id)
            ->whereBetween('match_datetime', [$from, $until])
            ->get();

        if ($wedstrijden->isEmpty()) {
            return response()->json([self::leeg(
                'Er staan dit seizoen nog geen wedstrijden voor dit elftal.'
            )]);
        }

        $ids      = $wedstrijden->pluck('id');
        $gespeeld = $wedstrijden->filter(fn ($m) => self::isGespeeld($m));

        $events = MatchEvent::query()
            ->whereIn('match_id', $ids)
            ->with(['member:id,name', 'relatedMember:id,name'])
            ->get();

        $regels = [];

        // ── Resultaten ────────────────────────────────────────────────────
        $gewonnen = $gelijk = $verloren = 0;
        $voor = $tegen = 0;
        foreach ($gespeeld as $m) {
            [$o, $t] = self::stand($m);
            $voor  += $o;
            $tegen += $t;
            $o > $t ? $gewonnen++ : ($o === $t ? $gelijk++ : $verloren++);
        }

        $saldo = $voor - $tegen;

        $regels[] = self::kop('Seizoen ' . $from->year . '/' . ($from->year + 1));
        $regels[] = self::regel('Gespeeld', (string) $gespeeld->count());
        $regels[] = self::regel('Gewonnen', (string) $gewonnen);
        $regels[] = self::regel('Gelijk', (string) $gelijk);
        $regels[] = self::regel('Verloren', (string) $verloren);
        $regels[] = self::regel('Punten', (string) ($gewonnen * 3 + $gelijk));
        $regels[] = self::regel('Doelpunten voor', (string) $voor);
        $regels[] = self::regel('Doelpunten tegen', (string) $tegen);
        $regels[] = self::regel('Doelsaldo', ($saldo > 0 ? '+' : '') . $saldo);

        // ── Topscorers ────────────────────────────────────────────────────
        //
        // Uit het live verslag én uit de losse doelpuntenadministratie. Een live
        // vastgelegd doelpunt maakt óók een Goal-rij aan en is daaraan gekoppeld
        // via goal_id; daarop filteren we, zodat niets dubbel telt.
        $gekoppeld = $events->pluck('goal_id')->filter()->all();

        $losseGoals = Goal::query()
            ->whereIn('match_id', $ids)
            ->when($gekoppeld, fn ($q) => $q->whereNotIn('id', $gekoppeld))
            ->with(['scorer:id,name', 'assist:id,name'])
            ->get();

        $doelpunten = $events
            ->where('type', MatchEvent::TYPE_GOAL)
            ->where('side', MatchEvent::SIDE_OWN)
            ->map(fn (MatchEvent $e) => $e->member?->name)
            ->merge($losseGoals->where('is_own_goal', false)->map(fn (Goal $g) => $g->scorer?->name));

        $regels = array_merge($regels, self::sectie('Topscorers', $doelpunten));

        // ── Assists ───────────────────────────────────────────────────────
        $assists = $events
            ->where('type', MatchEvent::TYPE_GOAL)
            ->where('side', MatchEvent::SIDE_OWN)
            ->map(fn (MatchEvent $e) => $e->relatedMember?->name)
            ->merge($losseGoals->map(fn (Goal $g) => $g->assist?->name));

        $regels = array_merge($regels, self::sectie('Assists', $assists));

        // ── Kaarten ───────────────────────────────────────────────────────
        $kaarten = $events->where('type', MatchEvent::TYPE_CARD);

        if ($kaarten->isNotEmpty()) {
            $regels[] = self::kop('Kaarten');
            foreach ([MatchEvent::CARD_YELLOW => 'Geel', MatchEvent::CARD_RED => 'Rood'] as $soort => $label) {
                $vanSoort = $kaarten->where('card_type', $soort);
                if ($vanSoort->isEmpty()) {
                    continue;
                }
                $regels[] = self::regel($label . ' totaal', (string) $vanSoort->count());
                foreach (self::tel($vanSoort->map(fn (MatchEvent $e) => $e->member?->name)) as $naam => $aantal) {
                    $regels[] = self::regel($label . ' · ' . $naam, (string) $aantal);
                }
            }
        }

        // ── Schoten op doel ───────────────────────────────────────────────
        $schoten = $events->where('type', MatchEvent::TYPE_SHOT);

        if ($schoten->isNotEmpty()) {
            $regels[] = self::kop('Schoten op doel');
            $regels[] = self::regel('Voor',
                (string) $schoten->where('side', MatchEvent::SIDE_OWN)->count());
            $regels[] = self::regel('Tegen',
                (string) $schoten->where('side', MatchEvent::SIDE_OPPONENT)->count());
        }

        // ── Opkomst per speler ────────────────────────────────────────────
        //
        // Waar de coach het vaakst naar vraagt: wie is er structureel niet. Het
        // wordt geteld als afwezigheden, want dát is wat er is vastgelegd -
        // aanwezigheid is de rest.
        $trainingen = self::trainingsDatums($team->id, $from, Carbon::today());

        $afmeldingen = Absence::query()
            ->where(function ($q) use ($gespeeld, $team, $from) {
                $q->where(fn ($x) => $x->where('type', Absence::TYPE_MATCH)
                        ->whereIn('match_id', $gespeeld->pluck('id')))
                  ->orWhere(fn ($x) => $x->where('type', Absence::TYPE_TRAINING)
                        ->whereIn('training_schedule_id',
                            TrainingSchedule::where('team_id', $team->id)->pluck('id'))
                        ->whereBetween('training_date',
                            [$from->toDateString(), Carbon::today()->toDateString()]));
            })
            ->with('member:id,name')
            ->get();

        $momenten = $gespeeld->count() + count($trainingen);

        $regels[] = self::kop('Opkomst');
        $regels[] = self::regel('Wedstrijden', (string) $gespeeld->count());
        $regels[] = self::regel('Trainingen', (string) count($trainingen));

        if ($momenten > 0) {
            $spelers = $team->playingMembers()->where('members.is_active', true)
                ->orderBy('members.name')->get();

            $perLid = $afmeldingen->groupBy('member_id');

            // Aflopend op gemiste momenten: bovenaan staat wie je moet spreken.
            $rijen = $spelers
                ->map(fn (Member $lid) => [
                    'naam'   => $lid->name,
                    'gemist' => ($perLid[$lid->id] ?? collect())->count(),
                ])
                ->sortByDesc('gemist')
                ->values();

            foreach ($rijen as $rij) {
                $aanwezig = max(0, $momenten - $rij['gemist']);
                $regels[] = self::regel(
                    $rij['naam'],
                    round($aanwezig / $momenten * 100) . '%  (' . $aanwezig . '/' . $momenten . ')',
                );
            }
        }

        return response()->json($regels);
    }

    /**
     * Een kop met de telling eronder, aflopend. Levert niets op als er niets te
     * tellen valt - een kopje boven een lege lijst is erger dan geen kopje.
     *
     * @param  Collection<int, string|null>  $namen
     * @return list<array<string, string>>
     */
    private static function sectie(string $kop, Collection $namen): array
    {
        $telling = self::tel($namen);

        if ($telling->isEmpty()) {
            return [];
        }

        $regels = [self::kop($kop)];
        foreach ($telling as $naam => $aantal) {
            $regels[] = self::regel($naam, (string) $aantal);
        }

        return $regels;
    }

    /** @return Collection<string, int> */
    private static function tel(Collection $namen): Collection
    {
        return $namen->filter()->countBy()->sortDesc();
    }

    /** @return array{0:Carbon,1:Carbon} */
    private static function seizoen(): array
    {
        $nu   = Carbon::now();
        $jaar = $nu->month >= 7 ? $nu->year : $nu->year - 1;

        return [
            Carbon::create($jaar, 7, 1)->startOfDay(),
            Carbon::create($jaar + 1, 6, 30)->endOfDay(),
        ];
    }

    /** Zelfde regel als op het dashboard: een uitslag en de wedstrijd is geweest. */
    private static function isGespeeld(FootballMatch $match): bool
    {
        if ($match->isCancelled()) {
            return false;
        }
        if ($match->score_home === null || $match->score_away === null) {
            return false;
        }
        if (in_array(strtolower((string) $match->status),
                ['played', 'completed', 'finished'], true)) {
            return true;
        }

        return $match->match_datetime === null || $match->match_datetime->isPast();
    }

    /** @return array{0:int,1:int} */
    private static function stand(FootballMatch $match): array
    {
        return $match->is_home
            ? [(int) $match->score_home, (int) $match->score_away]
            : [(int) $match->score_away, (int) $match->score_home];
    }

    /** @return list<string> */
    private static function trainingsDatums(string $teamId, Carbon $van, Carbon $tot): array
    {
        $schemas = TrainingSchedule::query()
            ->where('team_id', $teamId)
            ->where('is_active', true)
            ->get();

        $datums = [];
        foreach ($schemas as $schema) {
            $datum = $van->copy();
            while ($datum->dayOfWeekIso !== $schema->weekday) {
                $datum->addDay();
            }
            for (; $datum <= $tot; $datum->addWeek()) {
                $datums[] = $datum->toDateString();
            }
        }

        return $datums;
    }

    /** @return array<string, string> */
    private static function kop(string $label): array
    {
        return ['kind' => 'kop', 'label' => $label, 'value' => '', 'melding' => ''];
    }

    /** @return array<string, string> */
    private static function regel(string $label, string $value): array
    {
        return ['kind' => 'regel', 'label' => $label, 'value' => $value, 'melding' => ''];
    }

    /** @return array<string, string> */
    private static function leeg(string $melding): array
    {
        return ['kind' => '', 'label' => '', 'value' => '', 'melding' => $melding];
    }
}
