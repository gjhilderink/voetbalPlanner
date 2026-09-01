<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Een activiteit in de verenigingsagenda: los van elftal, wedstrijd of training.
 *
 * Zichtbaarheid: 'everyone' toont het item aan de hele club; 'selection' beperkt
 * het tot de gekoppelde elftallen en/of staf-/vrijwilligersgroepen. Zie
 * scopeVisibleTo() — dat is de enige plek waar die regels worden toegepast.
 */
class AgendaItem extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    public const AUDIENCE_EVERYONE  = 'everyone';
    public const AUDIENCE_SELECTION = 'selection';

    public const AUDIENCES = [
        self::AUDIENCE_EVERYONE  => 'Iedereen in de club',
        self::AUDIENCE_SELECTION => 'Alleen gekozen elftallen / groepen',
    ];

    protected $fillable = [
        'club_id', 'agenda_category_id', 'created_by',
        'title', 'summary', 'description', 'image_path',
        'starts_at', 'ends_at', 'is_all_day',
        'location', 'location_url', 'external_url', 'extra_info',
        'audience',
        'registration_enabled', 'registration_closes_at', 'capacity',
        'allow_guests', 'show_participants',
        'free_for_members',
        'is_published', 'published_at', 'is_highlighted',
    ];

    protected function casts(): array
    {
        return [
            'starts_at'              => 'datetime',
            'ends_at'                => 'datetime',
            'registration_closes_at' => 'datetime',
            'published_at'           => 'datetime',
            'is_all_day'             => 'boolean',
            'registration_enabled'   => 'boolean',
            'allow_guests'           => 'boolean',
            'show_participants'      => 'boolean',
            'is_published'           => 'boolean',
            'is_highlighted'         => 'boolean',
            'capacity'               => 'integer',
            'free_for_members'       => 'boolean',
        ];
    }

    // ── Relaties ───────────────────────────────────────────────────────────────

    public function club(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AgendaCategory::class, 'agenda_category_id');
    }

    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function teams(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'agenda_item_team');
    }

    public function staffGroups(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(StaffGroup::class, 'agenda_item_staff_group');
    }

    /** De kaartsoorten die voor deze activiteit te koop zijn. */
    public function ticketTypes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TicketType::class);
    }

    /** De bestellingen uit de ticketshop voor deze activiteit. */
    public function orders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** De uitgedeelde toegangscodes voor deze activiteit. */
    public function accessCodes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AccessCode::class);
    }

    /** Wie er binnen is: zowel op een uitgedeelde code als op lidnummer. */
    public function accessEntries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AccessEntry::class);
    }

    public function registrations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AgendaRegistration::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    /** Gepubliceerd én de publicatiedatum is verstreken (patroon NewsItem). */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    /**
     * Nog niet voorbij: begint in de toekomst óf loopt op dit moment nog. Zonder
     * de ends_at-tak zou een meerdaags evenement (bazaar) halverwege uit de
     * agenda verdwijnen.
     */
    public function scopeUpcoming(Builder $query, ?Carbon $from = null): Builder
    {
        $from = $from ?? now();

        return $query->where(fn (Builder $q) => $q
            ->where('starts_at', '>=', $from)
            ->orWhere('ends_at', '>=', $from));
    }

    /**
     * Beperkt tot wat deze gebruiker mag zien. Twee EXISTS-subqueries op
     * geïndexeerde kolommen; accessibleTeams() is per request gememoïseerd, dus
     * dit blijft één query ongeacht het aantal items.
     *
     * Let op: dit filtert NIET op publicatie — combineer altijd met published().
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        $query->where('club_id', $user->club_id);

        // Beheerders zien binnen hun eigen club alles.
        if ($user->isAdmin()) {
            return $query;
        }

        $teamIds  = $user->accessibleTeams()->pluck('id')->all();
        $memberId = $user->resolveMember()?->id;

        return $query->where(function (Builder $q) use ($teamIds, $memberId, $user) {
            $q->where('audience', self::AUDIENCE_EVERYONE);

            if ($teamIds !== []) {
                $q->orWhereExists(fn ($s) => $s->selectRaw('1')
                    ->from('agenda_item_team')
                    ->whereColumn('agenda_item_team.agenda_item_id', 'agenda_items.id')
                    ->whereIn('agenda_item_team.team_id', $teamIds));
            }

            // Groepen koppelen zowel losse accounts als leden.
            $q->orWhereExists(fn ($s) => $s->selectRaw('1')
                ->from('agenda_item_staff_group')
                ->join('staff_group_user', 'staff_group_user.staff_group_id', '=', 'agenda_item_staff_group.staff_group_id')
                ->whereColumn('agenda_item_staff_group.agenda_item_id', 'agenda_items.id')
                ->where('staff_group_user.user_id', $user->id));

            if ($memberId) {
                $q->orWhereExists(fn ($s) => $s->selectRaw('1')
                    ->from('agenda_item_staff_group')
                    ->join('staff_group_member', 'staff_group_member.staff_group_id', '=', 'agenda_item_staff_group.staff_group_id')
                    ->whereColumn('agenda_item_staff_group.agenda_item_id', 'agenda_items.id')
                    ->where('staff_group_member.member_id', $memberId));
            }
        });
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    public function isPast(): bool
    {
        return ($this->ends_at ?? $this->starts_at)->isPast();
    }

    /** Aantal personen dat komt, inclusief meegebrachte introducés. */
    public function goingCount(): int
    {
        return (int) $this->registrations()
            ->where('status', AgendaRegistration::STATUS_GOING)
            ->sum(DB::raw('1 + guest_count'));
    }

    public function spotsLeft(): ?int
    {
        return $this->capacity === null ? null : max(0, $this->capacity - $this->goingCount());
    }

    public function isFull(): bool
    {
        return $this->capacity !== null && $this->goingCount() >= $this->capacity;
    }

    /** Staat aanmelden open: ingeschakeld, deadline niet verstreken, nog niet voorbij. */
    public function isRegistrationOpen(): bool
    {
        if (! $this->registration_enabled || $this->isPast()) {
            return false;
        }

        return $this->registration_closes_at === null || $this->registration_closes_at->isFuture();
    }

    /** Korte omschrijving van de doelgroep, voor tabel en app. */
    public function audienceLabel(): string
    {
        if ($this->audience === self::AUDIENCE_EVERYONE) {
            return 'Hele vereniging';
        }

        $parts = $this->teams->pluck('name')
            ->merge($this->staffGroups->pluck('name'))
            ->filter()
            ->values();

        return $parts->isEmpty() ? 'Geen doelgroep gekozen' : $parts->implode(', ');
    }
}
