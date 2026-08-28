<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Team extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'external_id', 'name', 'category', 'age_group',
        'season', 'photo', 'is_active', 'is_first_team', 'last_synced_at', 'club_id',
        'default_lineup',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_first_team' => 'boolean',
            'last_synced_at' => 'datetime',
            // De standaardopstelling van dit elftal; zie de migratie waarom hij
            // hier staat en niet in de lineups-tabel.
            'default_lineup' => 'array',
        ];
    }

    public function members(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Member::class)
            ->withPivot(['role', 'season', 'is_active'])
            ->withTimestamps();
    }

    /**
     * De leden van dit elftal die daadwerkelijk meespelen: coaches, trainers,
     * leiders en overige staf vallen weg. Gebruikt voor de opstelling en voor
     * af- en aanmelden bij een wedstrijd — een trainer staat niet in de basis en
     * meldt zich ook niet af.
     *
     * De functie staat op twee plekken: member_team.role is de functie binnen
     * dít elftal, members.role de hoofdrol in de club. De teamfunctie wint,
     * want die zegt iets over dit team; iemand kan speler zijn in het ene team
     * en leider in het andere. Pas als de teamfunctie leeg is, beslist de
     * hoofdrol.
     *
     * Bij twijfel telt iemand als speler. Een naam te veel ziet de coach staan
     * en negeert hij; een speler die stilzwijgend ontbreekt merkt niemand op tot
     * hij langs de lijn blijkt te missen.
     */
    public function playingMembers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        $teamStaf = [Member::ROLE_COACH, Member::ROLE_ASSISTANT, Member::ROLE_LEIDER];
        $clubStaf = ['coach', 'medical', 'staff'];

        return $this->members()->where(function ($q) use ($teamStaf, $clubStaf) {
            // Teamfunctie ingevuld: die beslist, en alleen die.
            $q->where(fn ($sub) => $sub
                ->whereNotNull('member_team.role')
                ->where('member_team.role', '!=', '')
                ->whereNotIn('member_team.role', $teamStaf));

            // Geen teamfunctie: dan valt de club-hoofdrol terug in.
            $q->orWhere(fn ($sub) => $sub
                ->where(fn ($leeg) => $leeg
                    ->whereNull('member_team.role')
                    ->orWhere('member_team.role', '=', ''))
                ->where(fn ($rol) => $rol
                    ->whereNull('members.role')
                    ->orWhereNotIn('members.role', $clubStaf)));
        });
    }

    /**
     * User-accounts gekoppeld via user_team pivot. Wordt gebruikt om
     * app-gebruikers (zoals bardienst@..., coaches zonder Member-record,
     * staff-leiders) óók in de team-leden-API te tonen.
     */
    public function users(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_team')->withPivot('role')->withTimestamps();
    }

    public function matches(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FootballMatch::class);
    }

    public function coaches(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Member::class)
            ->wherePivot('role', 'coach')
            ->withTimestamps();
    }

    /**
     * Leden die als default-coach van een wedstrijd gelden. Eerst de echte
     * coaches (rol coach); heeft het team die niet, dan vallen we terug op
     * leiders/assistent-coaches. Retourneert een Collection<Member>.
     */
    /**
     * Leden die als staf bij dit elftal horen, uit alle drie de plekken waar
     * dat kan staan.
     *
     * De functie bij het lid in het elftal (member_team, uit Sportlink), de
     * teamkoppeling van een account (user_team, uit de portal) en de clubfunctie
     * op het lid zelf (members.role). Dat zijn drie verschillende antwoorden op
     * dezelfde vraag, en elk scherm keek naar een andere - waardoor een coach
     * die je in de portal had aangewezen niet eens in de keuzelijst van een
     * wedstrijd stond.
     *
     * @return array<int, string>
     */
    public function staffMemberIds(): array
    {
        $rollen = [Member::ROLE_COACH, Member::ROLE_LEIDER, Member::ROLE_ASSISTANT];

        $viaLeden = $this->members()->wherePivotIn('role', $rollen)->pluck('members.id');

        $viaAccounts = $this->users()
            ->wherePivotIn('role', $rollen)
            ->get()
            ->map(fn (User $account) => $account->resolveMember()?->id)
            ->filter();

        $viaClubfunctie = $this->members()
            ->whereIn('members.role', ['coach', 'staff'])
            ->pluck('members.id');

        return $viaLeden
            ->concat($viaAccounts)
            ->concat($viaClubfunctie)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Wie er standaard als coach op een wedstrijd van dit elftal komt.
     *
     * Twee bronnen, want een club legt dit op twee plekken vast. In Sportlink
     * (en dus in member_team) staat de functie bij het lid; in de portal koppel
     * je onder "Toegewezen teams & functies" een account aan een elftal, en dat
     * belandt in user_team. Alleen naar de eerste kijken betekende dat een in de
     * portal aangewezen coach nooit op een wedstrijd terechtkwam - zonder enig
     * spoor, want er ging niets mis.
     *
     * Het resultaat is altijd een lijst leden: match_coaches verwijst naar
     * leden, niet naar accounts. Een account zonder lidprofiel kan dus geen
     * wedstrijdcoach zijn.
     *
     * Coaches gaan voor. Zijn die er niet, dan vallen leiders en assistenten in.
     *
     * @return \Illuminate\Support\Collection<int, Member>
     */
    public function matchDefaultCoaches(): \Illuminate\Support\Collection
    {
        /** @param array<int, string> $rollen */
        $viaLeden = fn (array $rollen) => $this->members()->wherePivotIn('role', $rollen)->get();

        /** @param array<int, string> $rollen */
        $viaAccounts = fn (array $rollen) => $this->users()
            ->wherePivotIn('role', $rollen)
            ->get()
            ->map(fn (User $account) => $account->resolveMember())
            ->filter();

        $samen = fn (array $rollen) => $viaLeden($rollen)
            ->concat($viaAccounts($rollen))
            ->unique('id')
            ->values();

        $coaches = $samen([Member::ROLE_COACH]);

        if ($coaches->isNotEmpty()) {
            return $coaches;
        }

        return $samen([Member::ROLE_LEIDER, Member::ROLE_ASSISTANT]);
    }

    public function club(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Club::class);
    }
}
