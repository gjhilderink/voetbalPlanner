<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén gebeurtenis in het live verslag van een wedstrijd.
 */
class MatchEvent extends Model
{
    use HasUuids;

    public const TYPE_KICKOFF      = 'kickoff';
    public const TYPE_HALFTIME     = 'halftime';
    public const TYPE_SECOND_HALF  = 'second_half';
    public const TYPE_FULLTIME     = 'fulltime';
    public const TYPE_GOAL         = 'goal';
    public const TYPE_CARD         = 'card';
    public const TYPE_SUBSTITUTION = 'substitution';
    /** Schot op doel. Alleen de kant telt; wie schoot leggen we niet vast. */
    public const TYPE_SHOT         = 'shot';

    /**
     * Een gemiste strafschop.
     *
     * Een eigen soort en niet een schot met een detail: een strafschop naast
     * het doel is geen schot op doel, en die twee op een hoop gooien vervuilt
     * het schotenaantal. Ook geen doelpunt, dus de stand blijft ongemoeid.
     */
    public const TYPE_PENALTY_MISS = 'penalty_miss';

    public const TYPES = [
        self::TYPE_KICKOFF,
        self::TYPE_HALFTIME,
        self::TYPE_SECOND_HALF,
        self::TYPE_FULLTIME,
        self::TYPE_GOAL,
        self::TYPE_CARD,
        self::TYPE_SUBSTITUTION,
        self::TYPE_SHOT,
        self::TYPE_PENALTY_MISS,
    ];

    public const SIDE_OWN      = 'own';
    public const SIDE_OPPONENT = 'opponent';

    public const CARD_YELLOW = 'yellow';
    public const CARD_RED    = 'red';

    protected $fillable = [
        'match_id', 'type', 'minute', 'side',
        'member_id', 'related_member_id',
        'card_type', 'detail', 'goal_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'minute' => 'integer',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(FootballMatch::class, 'match_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function relatedMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'related_member_id');
    }

    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class, 'goal_id');
    }

    /** Icoonnaam voor de app en de webpagina — één keer bepaald, twee keer gebruikt. */
    public function icon(): string
    {
        return match ($this->type) {
            self::TYPE_GOAL         => 'sports_soccer',
            self::TYPE_CARD         => 'style',
            self::TYPE_SUBSTITUTION => 'swap_horiz',
            self::TYPE_SHOT         => 'my_location',
            self::TYPE_PENALTY_MISS => 'block',
            self::TYPE_HALFTIME,
            self::TYPE_FULLTIME     => 'sports',
            default                 => 'play_arrow',
        };
    }

    /**
     * Eén regel Nederlands over deze gebeurtenis. Staat hier en niet in de app
     * of de Blade-view, zodat beide precies hetzelfde tonen.
     */
    public function label(): string
    {
        $naam    = $this->member?->name ?? '';
        $tweede  = $this->relatedMember?->name ?? '';
        $tegen   = $this->match?->opponent ?: 'de tegenstander';

        return match ($this->type) {
            self::TYPE_KICKOFF     => 'Aftrap',
            self::TYPE_HALFTIME    => 'Rust',
            self::TYPE_SECOND_HALF => 'Tweede helft',
            self::TYPE_FULLTIME    => 'Eindsignaal',

            self::TYPE_GOAL => $this->side === self::SIDE_OPPONENT
                ? 'Doelpunt ' . $tegen
                : trim(implode(' ', array_filter([
                    $naam !== '' ? $naam : 'Doelpunt',
                    $tweede !== '' ? "(assist {$tweede})" : '',
                    $this->detail === 'penalty' ? '· strafschop' : '',
                    $this->detail === 'own_goal' ? '· eigen doelpunt' : '',
                ]))),

            self::TYPE_SHOT => $this->side === self::SIDE_OPPONENT
                ? 'Schot op doel ' . $tegen
                : ($naam !== '' ? 'Schot op doel · ' . $naam : 'Schot op doel'),

            self::TYPE_PENALTY_MISS => $this->side === self::SIDE_OPPONENT
                ? 'Strafschop gemist ' . $tegen
                : ($naam !== ''
                    ? $naam . ' · strafschop gemist'
                    : 'Strafschop gemist'),

            self::TYPE_CARD => trim(
                ($this->card_type === self::CARD_RED ? 'Rood' : 'Geel')
                . ' voor '
                . ($this->side === self::SIDE_OPPONENT
                    ? $tegen
                    : ($naam !== '' ? $naam : 'onbekend'))
            ),

            self::TYPE_SUBSTITUTION => $naam !== '' && $tweede !== ''
                ? "{$naam} in, {$tweede} uit"
                : ($naam !== '' ? "{$naam} erin" : 'Wissel'),

            default => '',
        };
    }
}
