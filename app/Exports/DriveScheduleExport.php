<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\FootballMatch;
use App\Models\Team;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Rijschema als Excel-bestand, bedoeld om terug te importeren (zie
 * DriveScheduleImport). De ID-kolom is de sleutel waarop de import de wedstrijd
 * terugvindt; blijft die leeg, dan wordt de rij overgeslagen.
 *
 * Anders dan de tabel op het scherm bevat dit bestand óók uitwedstrijden waar
 * nog géén rijder aan hangt. Dat is juist het punt: je exporteert de lege
 * planning, vult de rijders in Excel in en zet hem in één keer terug.
 */
class DriveScheduleExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    /** Scheidingsteken tussen de rijders van één wedstrijd. */
    public const DRIVER_SEPARATOR = ';';

    /**
     * @param array<string>|null $allowedTeamIds Beperking voor niet-beheerders (null = geen).
     */
    public function __construct(
        private readonly ?string $clubId,
        private readonly ?string $teamId = null,
        private readonly ?string $from = null,
        private readonly ?string $until = null,
        private readonly ?array $allowedTeamIds = null,
    ) {}

    public function collection(): Collection
    {
        return FootballMatch::query()
            ->with(['team', 'drivers', 'coaches'])
            ->where('is_home', false)
            ->when($this->clubId, fn ($q) => $q->whereHas('team', fn ($t) => $t->where('club_id', $this->clubId)))
            ->when($this->allowedTeamIds !== null, fn ($q) => $q->whereIn('team_id', $this->allowedTeamIds))
            ->when($this->teamId, fn ($q) => $q->where('team_id', $this->teamId))
            ->when($this->from, fn ($q) => $q->whereDate('match_datetime', '>=', $this->from))
            ->when($this->until, fn ($q) => $q->whereDate('match_datetime', '<=', $this->until))
            ->orderBy('match_datetime')
            ->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Datum',
            'Aanvang',
            'Elftal',
            'Tegenstander',
            'Accommodatie',
            'Verzameltijd',
            'Rijders',
            'Coach(es)',
        ];
    }

    public function map($record): array
    {
        return [
            $record->id,
            $record->match_datetime?->format('d-m-Y') ?? '',
            $record->match_datetime?->format('H:i') ?? '',
            $record->team?->name ?? '',
            $record->opponent ?? '',
            $record->location ?? '',
            self::timeCell($record->arrival_time),
            self::driversCell($record),
            $record->coaches->pluck('name')->implode(', '),
        ];
    }

    /** Alle rijders van één wedstrijd in één cel: "Jan Jansen; Piet Pietersen". */
    public static function driversCell($record): string
    {
        return $record->drivers
            ->sortBy('name')
            ->pluck('name')
            ->implode(self::DRIVER_SEPARATOR . ' ');
    }

    /** "09:30:00" uit de database wordt "09:30" in het bestand. */
    public static function timeCell(?string $time): string
    {
        if (! $time) {
            return '';
        }

        return preg_match('/^(\d{1,2}):(\d{2})/', $time, $m)
            ? sprintf('%02d:%02d', (int) $m[1], (int) $m[2])
            : $time;
    }

    /** Naam van het gefilterde elftal, voor de bestandsnaam. */
    public static function teamSlug(?string $teamId): string
    {
        $name = $teamId ? Team::find($teamId)?->name : null;

        return $name ? '-' . str($name)->slug() : '';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill'      => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF16A34A']],
                'alignment' => ['horizontal' => 'center'],
            ],
        ];
    }
}
