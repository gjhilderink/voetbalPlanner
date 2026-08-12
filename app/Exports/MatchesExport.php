<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\FootballMatch;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Wedstrijdlijst als Excel-bestand, bedoeld om terug te importeren
 * (zie MatchesImport). De ID-kolom is de sleutel waarop de import bestaande
 * wedstrijden bijwerkt; blijft die leeg, dan wordt een wedstrijd aangemaakt.
 *
 * Coaches, schoonmakers en rijders zitten er bewust niet in: dat zijn
 * meervoudige koppelingen die zich slecht in één cel laten bewerken.
 */
class MatchesExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    /**
     * @param array<string>      $teamIds        Team-filter van de tabel (leeg = alle).
     * @param array<string>|null $allowedTeamIds Beperking voor niet-beheerders (null = geen).
     */
    public function __construct(
        private readonly ?string $clubId,
        private readonly array $teamIds = [],
        private readonly ?string $status = null,
        private readonly ?string $fromDate = null,
        private readonly ?string $untilDate = null,
        private readonly ?array $allowedTeamIds = null,
    ) {}

    public function collection(): Collection
    {
        return FootballMatch::query()
            ->with(['team', 'vlagger', 'fruitHero'])
            ->when($this->clubId, fn ($q) => $q->whereHas('team', fn ($t) => $t->where('club_id', $this->clubId)))
            ->when($this->allowedTeamIds !== null, fn ($q) => $q->whereIn('team_id', $this->allowedTeamIds))
            ->when($this->teamIds, fn ($q) => $q->whereIn('team_id', $this->teamIds))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->fromDate, fn ($q) => $q->where('match_datetime', '>=', $this->fromDate))
            ->when($this->untilDate, fn ($q) => $q->where('match_datetime', '<=', $this->untilDate))
            ->orderBy('match_datetime')
            ->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Team',
            'Tegenstander',
            'Datum',
            'Tijd',
            'Thuis',
            'Locatie',
            'Status',
            'Aanwezig',
            'Kleedkamer',
            'Vlagger',
            'Fruitheld',
            'Opmerkingen',
        ];
    }

    public function map($record): array
    {
        return [
            $record->id,
            $record->team?->name ?? '',
            $record->opponent,
            $record->match_datetime?->format('d-m-Y') ?? '',
            $record->match_datetime?->format('H:i') ?? '',
            $record->is_home ? 'Ja' : 'Nee',
            $record->location ?? '',
            self::statusLabel($record->status),
            $record->arrival_time ? substr((string) $record->arrival_time, 0, 5) : '',
            $record->dressing_room ?? '',
            $record->vlagger?->name ?? '',
            $record->fruitHero?->name ?? '',
            $record->notes ?? '',
        ];
    }

    /** Nederlands label voor de wedstrijdstatus. */
    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            'scheduled' => 'Gepland',
            'played'    => 'Gespeeld',
            'cancelled' => 'Geannuleerd',
            'postponed' => 'Uitgesteld',
            default     => (string) $status,
        };
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
