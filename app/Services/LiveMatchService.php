<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FootballMatch;
use App\Models\Goal;
use App\Models\MatchEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Het live wedstrijdverslag: starten, gebeurtenissen vastleggen, afsluiten en
 * de complete toestand samenstellen.
 *
 * Zowel de app-API als de publieke webpagina lezen hier hun toestand op, zodat
 * ze niet uit elkaar kunnen lopen.
 */
class LiveMatchService
{
    /** Zo lang blijft de publieke link na het eindsignaal nog werken. */
    private const GRACE_HOURS = 3;

    /**
     * Start het verslag. Levert altijd een verse token op, zodat een eerder
     * gedeelde link nooit een volgende wedstrijd opent.
     */
    public function start(FootballMatch $match, User $user): void
    {
        DB::transaction(function () use ($match, $user) {
            $match->forceFill([
                'live_started_at'  => now(),
                'live_halftime_at' => null,
                'live_ended_at'    => null,
                'live_token'       => Str::random(64),
            ])->save();

            // Een herstart begint met een schone lei; anders blijft het verslag
            // van de vorige poging eronder hangen.
            $match->events()->delete();

            MatchEvent::create([
                'match_id'   => $match->id,
                'type'       => MatchEvent::TYPE_KICKOFF,
                'minute'     => 0,
                'created_by' => $user->id,
            ]);
        });
    }

    /**
     * Legt één gebeurtenis vast. Een eigen doelpunt met maker komt óók in de
     * doelpuntenadministratie terecht, anders zou het uit de seizoenscijfers
     * verdwijnen.
     *
     * @param array{type:string,side?:?string,member_id?:?string,related_member_id?:?string,card_type?:?string,detail?:?string,minute?:?int} $data
     */
    public function record(FootballMatch $match, array $data, User $user): MatchEvent
    {
        return DB::transaction(function () use ($match, $data, $user) {
            $type   = $data['type'];
            $minute = $data['minute'] ?? $this->currentMinute($match);

            $goalId = null;
            if ($type === MatchEvent::TYPE_GOAL
                && ($data['side'] ?? null) === MatchEvent::SIDE_OWN
                && ! empty($data['member_id'])
            ) {
                $goalId = Goal::create([
                    'match_id'    => $match->id,
                    'scorer_id'   => $data['member_id'],
                    'assist_id'   => $data['related_member_id'] ?? null,
                    'minute'      => $minute,
                    'is_own_goal' => ($data['detail'] ?? null) === 'own_goal',
                    'is_penalty'  => ($data['detail'] ?? null) === 'penalty',
                ])->id;
            }

            $event = MatchEvent::create([
                'match_id'          => $match->id,
                'type'              => $type,
                'minute'            => $minute,
                'side'              => $data['side'] ?? null,
                'member_id'         => $data['member_id'] ?? null,
                'related_member_id' => $data['related_member_id'] ?? null,
                'card_type'         => $data['card_type'] ?? null,
                'detail'            => $data['detail'] ?? null,
                'goal_id'           => $goalId,
                'created_by'        => $user->id,
            ]);

            // Rust en tweede helft bepalen de klok, dus die worden ook op de
            // wedstrijd zelf vastgelegd.
            if ($type === MatchEvent::TYPE_HALFTIME) {
                $match->forceFill(['live_halftime_at' => now()])->save();
            }
            if ($type === MatchEvent::TYPE_FULLTIME) {
                $this->finalise($match);
            }

            // Wissel ook doorzetten naar de opstelling, als die bestaat.
            if ($type === MatchEvent::TYPE_SUBSTITUTION && ! empty($data['related_member_id'])) {
                $match->lineup?->players()
                    ->where('member_id', $data['related_member_id'])
                    ->update(['substituted_at_minute' => $minute]);
            }

            return $event;
        });
    }

    /** Verwijdert de laatst vastgelegde gebeurtenis, inclusief het gekoppelde doelpunt. */
    public function undoLast(FootballMatch $match): ?MatchEvent
    {
        return DB::transaction(function () use ($match) {
            $event = $match->events()
                ->where('type', '!=', MatchEvent::TYPE_KICKOFF)
                ->latest('created_at')
                ->first();

            if (! $event) {
                return null;
            }

            if ($event->goal_id) {
                Goal::whereKey($event->goal_id)->delete();
            }
            // De klok terugdraaien hoort erbij: anders blijft de wedstrijd in de
            // rust of op afgelopen staan terwijl de gebeurtenis weg is.
            if ($event->type === MatchEvent::TYPE_HALFTIME) {
                $match->forceFill(['live_halftime_at' => null])->save();
            }
            if ($event->type === MatchEvent::TYPE_FULLTIME) {
                $match->forceFill(['live_ended_at' => null])->save();
            }

            $event->delete();

            return $event;
        });
    }

    /**
     * Eindsignaal: het verslag sluit en de uitslag wordt op de wedstrijd
     * vastgelegd. Dit is de eerste plek waar de app zelf een uitslag schrijft;
     * tot nu toe kwam die alleen uit de Sportlink-sync.
     */
    public function finalise(FootballMatch $match): void
    {
        $score = $match->liveScore();

        $match->forceFill([
            'live_ended_at' => $match->live_ended_at ?? now(),
            'score_home'    => $match->is_home ? $score['own'] : $score['opponent'],
            'score_away'    => $match->is_home ? $score['opponent'] : $score['own'],
            'status'        => 'played',
        ])->save();
    }

    /**
     * Verstreken speelminuut, berekend op de server zodat de coach, de app en
     * de webpagina dezelfde klok zien.
     *
     * De rustpauze telt niet mee: staat de wedstrijd in de rust, dan blijft de
     * klok staan op het moment van rust.
     */
    public function currentMinute(FootballMatch $match): int
    {
        if (! $match->live_started_at) {
            return 0;
        }

        $eind = $match->live_ended_at ?? now();

        // In de rust: klok bevriezen op het rustmoment.
        if ($match->live_halftime_at && ! $this->secondHalfStarted($match)) {
            $eind = $match->live_halftime_at;
        }

        $minuten = $match->live_started_at->diffInMinutes($eind);

        // Is de tweede helft begonnen, dan de duur van de pauze eraf halen.
        if ($match->live_halftime_at && ($start2 = $this->secondHalfStartedAt($match))) {
            $minuten -= $match->live_halftime_at->diffInMinutes($start2);
        }

        return max(0, (int) $minuten);
    }

    /** De volledige toestand: stand, klok, periode en de tijdlijn. */
    public function state(FootballMatch $match, bool $canManage = false): array
    {
        $match->loadMissing(['team', 'events.member', 'events.relatedMember']);

        $score  = $match->liveScore();
        $ended  = $match->live_ended_at !== null;
        $period = $this->period($match);

        $events = $match->events
            ->sortByDesc('created_at')
            ->values()
            ->map(fn (MatchEvent $e) => [
                'id'     => $e->id,
                'minute' => $e->minute !== null ? $e->minute . "'" : '',
                'label'  => $e->label(),
                'type'   => $e->type,
                'side'   => (string) ($e->side ?? ''),
                'icon'   => $e->icon(),
            ])
            ->all();

        return [
            'matchId'       => $match->id,
            // De app laadt hiermee de spelerkeuze voor doelpunten, wissels en
            // kaarten; zonder teamId zou daar een tweede call voor nodig zijn.
            'teamId'        => $match->team_id ?? '',
            'teamName'      => $match->team?->name ?? '',
            'opponent'      => $match->opponent ?? '',
            'opponentLogo'  => $match->opponent_logo ?? '',
            'isHome'        => $match->is_home ? 'true' : 'false',
            'scoreOwn'      => (string) $score['own'],
            'scoreOpponent' => (string) $score['opponent'],
            'period'        => $period,
            'periodLabel'   => $this->periodLabel($period),
            'minute'        => (string) $this->currentMinute($match),
            'isLive'        => $match->isLive() ? 'true' : 'false',
            'hasEnded'      => $ended ? 'true' : 'false',
            'canManage'     => $canManage ? 'true' : 'false',
            'shareUrl'      => $match->live_token ? url('/live/' . $match->live_token) : '',
            'events'        => $events,
        ];
    }

    /** Is deze token nog bruikbaar voor de publieke pagina? */
    public function publicLinkIsOpen(FootballMatch $match): bool
    {
        if (! $match->live_started_at) {
            return false;
        }
        if (! $match->live_ended_at) {
            return true;
        }

        return $match->live_ended_at->greaterThan(Carbon::now()->subHours(self::GRACE_HOURS));
    }

    private function period(FootballMatch $match): string
    {
        if (! $match->live_started_at) {
            return 'not_started';
        }
        if ($match->live_ended_at) {
            return 'ended';
        }
        if ($match->live_halftime_at && ! $this->secondHalfStarted($match)) {
            return 'halftime';
        }
        if ($this->secondHalfStarted($match)) {
            return 'second_half';
        }

        return 'first_half';
    }

    private function periodLabel(string $period): string
    {
        return match ($period) {
            'first_half'  => '1e helft',
            'halftime'    => 'Rust',
            'second_half' => '2e helft',
            'ended'       => 'Afgelopen',
            default       => 'Nog niet begonnen',
        };
    }

    private function secondHalfStarted(FootballMatch $match): bool
    {
        return $this->secondHalfStartedAt($match) !== null;
    }

    private function secondHalfStartedAt(FootballMatch $match): ?Carbon
    {
        $event = $match->relationLoaded('events')
            ? $match->events->firstWhere('type', MatchEvent::TYPE_SECOND_HALF)
            : $match->events()->where('type', MatchEvent::TYPE_SECOND_HALF)->first();

        return $event?->created_at;
    }
}
