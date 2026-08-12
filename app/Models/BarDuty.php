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
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    /** Definitie van dit dagdeel, of null bij een onbekende/oude shift. */
    public function shiftDef(): ?array
    {
        return self::SHIFTS[$this->shift] ?? null;
    }

    /** Weergavelabel (bv. "Middag 1"); valt terug op de ruwe sleutel. */
    public function shiftLabel(): string
    {
        return $this->shiftDef()['label'] ?? ucfirst((string) $this->shift);
    }

    public function startTime(): string
    {
        return $this->shiftDef()['start'] ?? '';
    }

    public function endTime(): string
    {
        return $this->shiftDef()['end'] ?? '';
    }

    /** "10:30 - 13:30" of leeg als er geen tijden bekend zijn. */
    public function timeRange(): string
    {
        $def = $this->shiftDef();
        return $def ? "{$def['start']} - {$def['end']}" : '';
    }

    /** Benodigde bezetting van dit dagdeel (2 of 3); fallback 2. */
    public function requiredCount(): int
    {
        return $this->shiftDef()['required'] ?? self::REQUIRED_MEMBERS;
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
        return $this->belongsToMany(Member::class, 'bar_duty_member');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'bar_duty_user');
    }

    public function refreshStatus(): void
    {
        if ($this->status === 'vervuld') {
            return;
        }

        // Leden én losse User-accounts tellen mee.
        $count = $this->members()->count() + $this->users()->count();
        $this->update(['status' => $count >= $this->requiredCount() ? 'bevestigd' : 'open']);
    }
}
