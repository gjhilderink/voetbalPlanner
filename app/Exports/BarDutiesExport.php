<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\BarDuty;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BarDutiesExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    public function __construct(
        private readonly ?string $clubId,
        private readonly ?string $teamId = null,
        private readonly ?string $fromDate = null,
        private readonly ?string $untilDate = null,
    ) {}

    public function collection(): Collection
    {
        return BarDuty::query()
            ->with(['team', 'members'])
            ->when($this->clubId,   fn($q) => $q->where('club_id', $this->clubId))
            ->when($this->teamId,   fn($q) => $q->where('team_id', $this->teamId))
            ->when($this->fromDate, fn($q) => $q->whereDate('date', '>=', $this->fromDate))
            ->when($this->untilDate, fn($q) => $q->whereDate('date', '<=', $this->untilDate))
            ->orderBy('date')
            ->orderBy('shift')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Datum',
            'Dienst',
            'Elftal',
            'Lid 1',
            'Lid 2',
            'Status',
            'Opmerkingen',
        ];
    }

    public function map($record): array
    {
        $shiftLabel = match($record->shift) {
            'ochtend' => 'Ochtend',
            'middag'  => 'Middag',
            'avond'   => 'Avond',
            default   => $record->shift,
        };

        $statusLabel = match($record->status) {
            'open'      => 'Open',
            'bevestigd' => 'Bevestigd',
            'vervuld'   => 'Vervuld',
            default     => $record->status,
        };

        $members = $record->members->pluck('name')->values();

        return [
            $record->date?->format('d-m-Y'),
            $shiftLabel,
            $record->team?->name ?? '',
            $members->get(0, ''),
            $members->get(1, ''),
            $statusLabel,
            $record->notes ?? '',
        ];
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
