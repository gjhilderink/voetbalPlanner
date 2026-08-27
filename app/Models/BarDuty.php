<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BarDuty extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    public const REQUIRED_MEMBERS = 2;

    /** Sleutel voor een handmatige bardienst met eigen dag, tijd en bezetting. */
    public const SHIFT_CUSTOM = 'custom';

    /**
     * Vaste dagdelen per weekenddag. Sleutel => definitie. `day` = Carbon
     * dayOfWeek (zondag = 0, zaterdag = 6). De shift-kolom bewaart de sleutel;
     * label/tijd/bezetting worden hieruit afgeleid.
     */
    public const SHIFTS = [
        'za_ochtend' => ['label' => 'Ochtend',  'day' => 6, 'start' => '10:30', 'end' => '13:30', 'required' => 2],
        'za_middag'  => ['label' => 'Middag',   'day' => 6, 'start' => '13:30', 'end' => '16:30', 'required' => 3],
        'za_avond1'  => ['label' => 'Avond 1',  'day' => 6, 'start' => '16:30', 'end' => '19:30', 'required' => 3],
        'za_avond2'  => ['label' => 'Avond 2',  'day' => 6, 'start' => '19:30', 'end' => '22:30', 'required' => 2],
        'zo_ochtend' => ['label' => 'Ochtend',  'day' => 0, 'start' => '09:00', 'end' => '12:00', 'required' => 2],
        'zo_middag1' => ['label' => 'Middag 1', 'day' => 0, 'start' => '12:00', 'end' => '15:00', 'required' => 3],
        'zo_middag2' => ['label' => 'Middag 2', 'day' => 0, 'start' => '15:00', 'end' => '18:00', 'required' => 3],
        'zo_avond'   => ['label' => 'Avond',    'day' => 0, 'start' => '18:00', 'end' => '21:00', 'required' => 2],
    ];

    protected $fillable = [
        'club_id', 'team_id', 'date', 'shift', 'notes', 'status',
        'custom_label', 'start_time', 'end_time', 'required_count',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'required_count' => 'integer',
        ];
    }

    /** Definitie van dit vaste dagdeel, of null bij handmatig/onbekend/oud. */
    public function shiftDef(): ?array
    {
        return self::SHIFTS[$this->shift] ?? null;
    }

    /** Handmatige bardienst (eigen dag/tijd/bezetting), geen vast dagdeel. */
    public function isCustom(): bool
    {
        return $this->shiftDef() === null;
    }

    /** Weergavelabel (bv. "Middag 1"); valt terug op eigen label of de sleutel. */
    public function shiftLabel(): string
    {
        return $this->shiftDef()['label']
            ?? ($this->custom_label ?: ($this->shift === self::SHIFT_CUSTOM ? 'Bardienst' : ucfirst((string) $this->shift)));
    }

    public function startTime(): string
    {
        return $this->shiftDef()['start'] ?? self::asClockTime($this->start_time);
    }

    public function endTime(): string
    {
        return $this->shiftDef()['end'] ?? self::asClockTime($this->end_time);
    }

    /**
     * De vaste dagdelen leveren al '10:30'; een handmatige dienst komt uit een
     * tijdkolom zonder cast en dus als '10:30:00'. Afkappen, anders staan de
     * seconden in het rooster en in de app.
     */
    private static function asClockTime(mixed $value): string
    {
        return substr((string) ($value ?? ''), 0, 5);
    }

    /** "10:30 - 13:30" of leeg als er geen tijden bekend zijn. */
    public function timeRange(): string
    {
        $start = $this->startTime();
        $end   = $this->endTime();
        return ($start && $end) ? "{$start} - {$end}" : '';
    }

    /** Benodigde bezetting (2 of 3 bij vaste dagdelen; eigen aantal bij handmatig); fallback 2. */
    public function requiredCount(): int
    {
        return $this->shiftDef()['required'] ?? $this->required_count ?? self::REQUIRED_MEMBERS;
    }

    /** Dagdelen die op deze datum gelden (op basis van de weekdag). */
    public static function shiftsForDate(\Carbon\Carbon $date): array
    {
        $dow = $date->dayOfWeek;
        return array_filter(self::SHIFTS, fn($def) => $def['day'] === $dow);
    }

    /** Zoekt de shift-sleutel bij een datum + labeltekst (bv. import). */
    public static function resolveShiftKey(\Carbon\Carbon $date, string $label): ?string
    {
        $label = mb_strtolower(trim($label));
        foreach (self::shiftsForDate($date) as $key => $def) {
            if (mb_strtolower($def['label']) === $label) {
                return $key;
            }
        }
        return null;
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'bar_duty_member')->withPivot('spots');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'bar_duty_user')->withPivot('spots');
    }

    /**
     * Aantal gevulde plekken. Niet het aantal aanmeldingen: wie met z'n tweeën
     * komt en zich via één account aanmeldt, vult er twee.
     */
    public function filledCount(): int
    {
        $tel = fn ($relatie) => $relatie
            ->map(fn ($r) => max(1, (int) ($r->pivot->spots ?? 1)))
            ->sum();

        $leden    = $this->relationLoaded('members') ? $this->members : $this->members()->get();
        $accounts = $this->relationLoaded('users')   ? $this->users   : $this->users()->get();

        return (int) ($tel($leden) + $tel($accounts));
    }

    public function refreshStatus(): void
    {
        if ($this->status === 'vervuld') {
            return;
        }

        // Eerst opnieuw inlezen. Deze methode wordt vrijwel altijd aangeroepen
        // vlak na een sync() op de leden, en dan staat een al geladen relatie
        // nog op de bezetting van vóór die wijziging. filledCount() gebruikt de
        // geladen relatie als die er is — dat is precies wat je wilt bij het
        // tonen van een lijst, en precies wat hier misgaat. Gevolg: de dienst
        // bleef "open" terwijl er net genoeg mensen op waren gezet.
        $this->load(['members', 'users']);

        $this->update([
            'status' => $this->filledCount() >= $this->requiredCount() ? 'bevestigd' : 'open',
        ]);
    }
}
