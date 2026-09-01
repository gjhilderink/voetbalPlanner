<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Member extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    // ── Functies per gekoppeld team (member_team.role / user_team.role) ──────────
    public const ROLE_PLAYER    = 'player';
    public const ROLE_COACH     = 'coach';
    public const ROLE_ASSISTANT = 'assistant_coach';
    public const ROLE_LEIDER    = 'leider';

    /** Selecteerbare functies per gekoppeld team. */
    public const TEAM_FUNCTIONS = [
        self::ROLE_PLAYER    => 'Speler',
        self::ROLE_COACH     => 'Coach / Trainer',
        self::ROLE_ASSISTANT => 'Assistent-trainer',
        self::ROLE_LEIDER    => 'Leider',
    ];

    /** Functies die beheerrechten (opstelling & score) geven voor dat team. */
    public const MANAGEMENT_ROLES = [self::ROLE_COACH, self::ROLE_LEIDER];

    /**
     * Alles wat geen speler is, in beide woordenlijsten.
     *
     * De functie per team (member_team.role) en de hoofdrol bij de club
     * (members.role) komen uit verschillende bronnen en gebruiken niet dezelfde
     * woorden: de een kent 'assistant_coach' en 'leider', de ander 'medical' en
     * 'staff'. Wie op een van beide plekken staf is, is geen speler - dus geldt
     * hier de samenvoeging.
     *
     * Twee aparte lijstjes hebben hier eerder een coach in de opstelling laten
     * belanden: zijn teamfunctie stond op 'staff', en dat woord kwam alleen in
     * het andere lijstje voor.
     */
    public const STAF_ROLES = [
        self::ROLE_COACH,
        self::ROLE_ASSISTANT,
        self::ROLE_LEIDER,
        'medical',
        'staff',
    ];

    protected $fillable = [
        'external_id', 'user_id', 'name', 'last_name', 'email', 'phone',
        'date_of_birth', 'role', 'profile_photo', 'sportlink_photo',
        'sportlink_photo_hash', 'is_active', 'last_synced_at',
        'shirt_number',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'is_active' => 'boolean',
            'last_synced_at' => 'datetime',
            'shirt_number' => 'integer',
        ];
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** De opgegeven kledingmaten, één per kledingstuk. */
    public function clothingSizes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MemberClothingSize::class);
    }

    public function teams(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Team::class)
            ->withPivot(['role', 'season', 'is_active', 'is_manual'])
            ->withTimestamps();
    }

    /** Staf-/commissiegroepen waar dit lid in zit (StaffGroup::members). */
    public function staffGroups(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(StaffGroup::class, 'staff_group_member');
    }

    public function matchesAsCoach(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FootballMatch::class, 'coach_id');
    }

    public function goals(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Goal::class, 'scorer_id');
    }

    public function assists(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Goal::class, 'assist_id');
    }

    public function lineupPlayers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LineupPlayer::class);
    }

    // ── Guardian/verzorger relaties ────────────────────────────────────────────

    /** Alle koppelverzoeken waarbij dit lid de ouder/verzorger is. */
    public function guardianLinks(): HasMany
    {
        return $this->hasMany(GuardianLink::class, 'guardian_member_id');
    }

    /** Alle koppelverzoeken waarbij dit lid het kind is. */
    public function childLinks(): HasMany
    {
        return $this->hasMany(GuardianLink::class, 'child_member_id');
    }

    /** Goedgekeurde gekoppelde kinderen van deze ouder/verzorger. */
    public function guardianChildren(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            Member::class,
            'guardian_links',
            'guardian_member_id',
            'child_member_id'
        )->wherePivot('status', 'approved');
    }

    /** Goedgekeurde ouders/verzorgers van dit kind. */
    public function guardians(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            Member::class,
            'guardian_links',
            'child_member_id',
            'guardian_member_id'
        )->wherePivot('status', 'approved');
    }

    // ── Rol helpers ────────────────────────────────────────────────────────────

    public function isCoach(): bool
    {
        return $this->role === 'coach';
    }

    public function isPlayer(): bool
    {
        return $this->role === 'player';
    }
    /**
     * De foto die de app moet tonen, als volledige URL.
     *
     * Een zelf geüploade foto wint van de clubpasfoto: wie de moeite neemt om er
     * zelf een te kiezen, wil niet dat de volgende sync die terugzet.
     */
    public function photoUrl(): string
    {
        if ($this->profile_photo) {
            return asset('storage/' . $this->profile_photo);
        }

        if ($this->sportlink_photo) {
            return asset($this->sportlink_photo);
        }

        return '';
    }
}
