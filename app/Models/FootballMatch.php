<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FootballMatch extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'matches';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'external_id', 'team_id', 'opponent', 'opponent_logo', 'match_datetime',
        'location', 'is_home', 'status', 'score_home', 'score_away',
        'arrival_time', 'dressing_room', 'coach_id', 'fruit_hero_id', 'vlagger_id', 'notes', 'last_synced_at',
        'live_started_at', 'live_halftime_at', 'live_ended_at', 'live_token',
    ];

    protected function casts(): array
    {
        return [
            'match_datetime' => 'datetime',
            'is_home' => 'boolean',
            'score_home' => 'integer',
            'score_away' => 'integer',
            'last_synced_at' => 'datetime',
            'live_started_at' => 'datetime',
            'live_halftime_at' => 'datetime',
            'live_ended_at' => 'datetime',
        ];
    }

    /** Gebeurtenissen van het live verslag, op registratievolgorde. */
    public function events(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MatchEvent::class, 'match_id')->orderBy('created_at');
    }

    /** Er loopt een verslag: gestart en nog niet afgefloten. */
    public function isLive(): bool
    {
        return $this->live_started_at !== null && $this->live_ended_at === null;
    }

    /**
     * Stand uit de doelpunt-gebeurtenissen.
     *
     * @return array{own:int,opponent:int}
     */
    public function liveScore(): array
    {
        $goals = $this->relationLoaded('events')
            ? $this->events->where('type', MatchEvent::TYPE_GOAL)
            : $this->events()->where('type', MatchEvent::TYPE_GOAL)->get();

        return [
            'own'      => $goals->where('side', MatchEvent::SIDE_OWN)->count(),
            'opponent' => $goals->where('side', MatchEvent::SIDE_OPPONENT)->count(),
        ];
    }

    public function team(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function coach(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Member::class, 'coach_id');
    }

    public function fruitHero(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Member::class, 'fruit_hero_id');
    }

    public function vlagger(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Member::class, 'vlagger_id');
    }

    public function coaches(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'match_coaches', 'match_id', 'member_id');
    }

    public function cleaners(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'match_cleaners', 'match_id', 'member_id');
    }

    public function drivers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'match_drivers', 'match_id', 'member_id');
    }

    /**
     * Zet de coaches van het elftal op deze wedstrijd.
     *
     * Alleen als er nog niemand staat: een keuze die iemand zelf heeft gemaakt
     * blijft staan. Dit vult een gat, het overschrijft niets.
     *
     * @param  array<int, string>|null  $memberIds  vooraf opgezochte coaches;
     *         de sync heeft ze per elftal al bij de hand en bespaart zo een
     *         query per wedstrijd.
     */
    public function koppelTeamCoaches(?array $memberIds = null, bool $vulAan = false): void
    {
        $ids = $memberIds ?? ($this->team?->matchDefaultCoaches()->pluck('id')->all() ?? []);

        if (empty($ids)) {
            return;
        }

        // Many-to-many: dit is wat het paneel én de app tonen. Met $vulAan komen
        // ontbrekende coaches er ook bij als er al iemand op staat; dat is voor
        // het eenmalig bijwerken van bestaande wedstrijden en niet voor de sync,
        // want daar zou het een handmatig weggehaalde coach terugzetten.
        if ($vulAan || $this->coaches()->count() === 0) {
            $this->coaches()->syncWithoutDetaching($ids);
        }

        // Enkelvoudig coach_id als terugval voor de app.
        if (! $this->coach_id) {
            $this->coach_id = $ids[0];
            $this->save();
        }
    }

    public function lineup(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Lineup::class, 'match_id');
    }

    public function goals(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Goal::class, 'match_id');
    }

    /**
     * Zoekt een teamlid op naam binnen het team van deze wedstrijd: eerst exact
     * (case-insensitief), anders 'bevat' (bv. alleen de voornaam). Voor goals +
     * opstelling. Null als niets gevonden.
     */
    public function resolveTeamMemberByName(?string $name): ?Member
    {
        $name = mb_strtolower(trim((string) $name));
        if ($name === '') {
            return null;
        }
        $base = Member::query()
            ->when($this->team_id, fn ($q) => $q->whereHas('teams', fn ($t) => $t->whereKey($this->team_id)));

        return (clone $base)->whereRaw('LOWER(name) = ?', [$name])->first()
            ?? (clone $base)->whereRaw('LOWER(name) LIKE ?', ['%' . $name . '%'])->first();
    }

    /** Korte doelpunten-samenvatting voor het coach-scherm ("12' Jan, 45' Piet"). */
    public function goalsSummary(): string
    {
        $goals = $this->goals()->with('scorer')->orderBy('minute')->get();

        return $goals->isEmpty()
            ? 'Nog geen doelpunten.'
            : $goals->map(fn ($g) => ($g->minute ? $g->minute . "' " : '') . ($g->scorer?->name ?? '?'))
                ->join(', ');
    }
}
