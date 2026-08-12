<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\BarDuty;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Leeg invul-sjabloon: alle dagdelen van elke zaterdag/zondag in het bereik,
 * met een leeg "Elftal"-veld. Vul de elftallen in en importeer om ze toe te
 * wijzen. Zelfde kolommen als BarDutiesExport, zodat de import beide leest.
 */
class BarDutyTemplateExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles
{
    public function __construct(
        private readonly ?string $fromDate = null,
        private readonly ?string $untilDate = null,
    ) {}

    public function collection(): Collection
    {
        $from  = $this->fromDate  ? Carbon::parse($this->fromDate)->startOfDay()  : Carbon::now()->startOfDay();
        $until = $this->untilDate ? Carbon::parse($this->untilDate)->endOfDay()   : Carbon::now()->addWeeks(8)->endOfDay();

        $rows = collect();
        foreach (CarbonPeriod::create($from, $until) as $day) {
            foreach (BarDuty::shiftsForDate($day) as $def) {
                $rows->push([
                    $day->format('d-m-Y'),
                    $def['label'],
                    "{$def['start']} - {$def['end']}",
                    '', // Elftal — in te vullen
                    '', // Lid 1
                    '', // Lid 2
                    '', // Lid 3
                    'Open',
                    '',
                ]);
            }
        }

        return $rows;
    }

    public function headings(): array
    {
        return ['Datum', 'Dagdeel', 'Tijd', 'Elftal', 'Lid 1', 'Lid 2', 'Lid 3', 'Status', 'Opmerkingen'];
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
