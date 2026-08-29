<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FootballMatch;
use App\Models\Goal;
use App\Models\MatchEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
     * Wist het hele verslag: alle gebeurtenissen, de doelpunten die daaruit zijn
     * ontstaan, en de live-klok inclusief de deel-link.
     *
     * De eindstand en de status van de wedstrijd blijven staan. Die horen bij de
     * uitslag en niet bij het verslag, en kunnen ook uit de Sportlink-sync komen
     * — die stilzwijgend leeggooien zou een goede uitslag kunnen kosten.
     * Handmatig ingevoerde doelpunten hangen niet aan een gebeurtenis en blijven
     * dus ook staan, net zoals bij het ongedaan maken van één gebeurtenis.
     */
    public function deleteReport(FootballMatch $match): void
    {
        DB::transaction(function () use ($match) {
            $goalIds = $match->events()->whereNotNull('goal_id')->pluck('goal_id');
            if ($goalIds->isNotEmpty()) {
                Goal::whereIn('id', $goalIds)->delete();
            }

            $match->events()->delete();

            $match->forceFill([
                'live_started_at'  => null,
                'live_halftime_at' => null,
                'live_ended_at'    => null,
                'live_token'       => null,
                // Ook de uitslag. Die wordt bij het eindsignaal uit het verslag
                // weggeschreven (zie finalise), dus na het weggooien van het
                // verslag staat er een score waar niets meer achter zit: de
                // statistiek is leeg, de doelpunten zijn weg, en op de kaart
                // staat nog 3-1.
                //
                // Bij een wedstrijd uit Sportlink komt de officiële uitslag bij
                // de eerstvolgende synchronisatie vanzelf terug; bij een
                // oefenwedstrijd hoort hij weg te zijn.
                'score_home' => null,
                'score_away' => null,
            ])->save();
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
        $match->loadMissing([
            'team.club', 'events.member', 'events.relatedMember',
            'lineup.players.member',
        ]);

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
                // De publieke pagina zet hier een geel of rood blokje bij; zonder
                // dit veld zou die de kleur uit de labeltekst moeten raden.
                'cardType' => (string) ($e->card_type ?? ''),
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
            // Het eigen clubembleem; de tegenstander heeft er al een. De publieke
            // pagina zet ze links en rechts van de stand.
            'teamLogo'      => $this->clubLogo($match),
            'events'        => $events,
            'lineup'        => $this->lineup($match),
            'stats'         => $this->stats($match),
        ];
    }

    /** URL van het clubembleem, of leeg als de club er geen heeft. */
    private function clubLogo(FootballMatch $match): string
    {
        $pad = $match->team?->club?->logo_path;

        return $pad ? Storage::disk('logos')->url($pad) : '';
    }

    /**
     * Basis en bank, op naam. Is er geen opstelling vastgelegd, dan blijven
     * beide lijsten leeg en laat de pagina het blok weg.
     *
     * @return array{starters: array<string>, bench: array<string>}
     */
    private function lineup(FootballMatch $match): array
    {
        $spelers = $match->lineup?->players ?? collect();

        $namen = fn (bool $bank) => $spelers
            ->filter(fn ($p) => (bool) $p->is_substitute === $bank)
            ->map(fn ($p) => $p->member?->name ?? '')
            ->filter()
            ->sort()
            ->values()
            ->all();

        return [
            'starters' => $namen(false),
            'bench'    => $namen(true),
        ];
    }

    /**
     * Wat er uit de tijdlijn te tellen valt: doelpunten, kaarten en wissels.
     * Alles als string, want de app typeert zijn velden zo.
     *
     * @return array<string, string>
     */
    private function stats(FootballMatch $match): array
    {
        $events = $match->events;

        $tel = fn (callable $filter) => (string) $events->filter($filter)->count();

        return [
            'goalsOwn'      => $tel(fn ($e) => $e->type === MatchEvent::TYPE_GOAL && $e->side !== 'opponent'),
            'goalsOpponent' => $tel(fn ($e) => $e->type === MatchEvent::TYPE_GOAL && $e->side === 'opponent'),
            'yellowOwn'     => $tel(fn ($e) => $e->type === MatchEvent::TYPE_CARD && $e->card_type === 'yellow' && $e->side !== 'opponent'),
            'yellowOpponent'=> $tel(fn ($e) => $e->type === MatchEvent::TYPE_CARD && $e->card_type === 'yellow' && $e->side === 'opponent'),
            'redOwn'        => $tel(fn ($e) => $e->type === MatchEvent::TYPE_CARD && $e->card_type === 'red' && $e->side !== 'opponent'),
            'redOpponent'   => $tel(fn ($e) => $e->type === MatchEvent::TYPE_CARD && $e->card_type === 'red' && $e->side === 'opponent'),
            'substitutions' => $tel(fn ($e) => $e->type === MatchEvent::TYPE_SUBSTITUTION),
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
