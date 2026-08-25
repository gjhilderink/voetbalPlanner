<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lineup extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'match_id', 'formation', 'tactical_notes',
        'players_on_field', 'match_format', 'published_at', 'periods',
    ];

    protected function casts(): array
    {
        return [
            'published_at'        => 'datetime',
            'players_on_field'    => 'integer',
            'periods'             => 'integer',
        ];
    }

    /**
     * De wissels tussen twee opeenvolgende perioden, afgeleid uit de opstelling.
     *
     * Niet opgeslagen: het verschil tussen periode 1 en 2 ís de wissel, en een
     * tweede plek die hetzelfde beweert loopt vroeg of laat uit de pas.
     *
     * @return array<int, array{block: string, outId: string, outNaam: string, inId: string, inNaam: string}>
     */
    public function derivedSubstitutions(): array
    {
        $perPeriode = $this->players
            ->where('is_substitute', false)
            ->groupBy('period');

        $wissels = [];
        for ($periode = 1; $periode < max(1, (int) $this->periods); $periode++) {
            $nu     = $perPeriode->get($periode, collect());
            $straks = $perPeriode->get($periode + 1, collect());

            // Nog geen opstelling voor de volgende periode: dan valt er ook
            // niets te wisselen, in plaats van "iedereen eruit".
            if ($straks->isEmpty()) {
                continue;
            }

            $eruit = $nu->whereNotIn('member_id', $straks->pluck('member_id'))->values();
            $erin  = $straks->whereNotIn('member_id', $nu->pluck('member_id'))->values();

            foreach ($eruit as $i => $speler) {
                $vervanger = $erin->get($i);
                if (! $vervanger) {
                    break;
                }
                $wissels[] = [
                    'block'   => (string) $periode,
                    'outId'   => (string) $speler->member_id,
                    'outNaam' => $speler->member?->name ?? '',
                    'inId'    => (string) $vervanger->member_id,
                    'inNaam'  => $vervanger->member?->name ?? '',
                ];
            }
        }

        return $wissels;
    }

    /** Mogen spelers deze opstelling al zien? */
    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    public function match(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(FootballMatch::class, 'match_id');
    }

    public function players(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LineupPlayer::class);
    }

    public function starters(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LineupPlayer::class)->where('is_substitute', false);
    }

    public function substitutes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LineupPlayer::class)->where('is_substitute', true);
    }
}
