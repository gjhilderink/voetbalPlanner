<?php

declare(strict_types=1);

namespace App\Imports;

use App\Models\AccessCode;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Toegangscodes inlezen uit een Excel-bestand.
 *
 * Eén kolom 'code' is genoeg; 'omschrijving' mag erbij. Codes die al bij deze
 * activiteit staan worden overgeslagen en geteld, niet als fout gemeld: een
 * lijst opnieuw importeren om er twintig aan toe te voegen is normaal gebruik,
 * geen vergissing.
 */
class AccessCodesImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    public int $imported = 0;
    public int $created  = 0;
    public int $skipped  = 0;

    /** @var array<string> */
    public array $errors = [];

    public function __construct(
        private readonly string $clubId,
        private readonly string $agendaItemId,
        private readonly int $maxUses,
    ) {}

    public function collection(Collection $rows): void
    {
        $bestaand = AccessCode::where('agenda_item_id', $this->agendaItemId)
            ->pluck('code')
            ->flip();

        $nu     = now();
        $nieuwe = [];

        foreach ($rows as $index => $row) {
            // +2: rij 1 is de kop, en Excel telt vanaf 1.
            $rowNum = $index + 2;

            $code = trim((string) ($row['code'] ?? ''));
            if ($code === '') {
                continue;
            }

            if (mb_strlen($code) > 64) {
                $this->errors[] = "Rij {$rowNum}: code is te lang (max 64 tekens).";
                continue;
            }

            if ($bestaand->has($code)) {
                $this->skipped++;
                continue;
            }

            $bestaand[$code] = true;

            $label = trim((string) ($row['omschrijving'] ?? ($row['label'] ?? '')));

            $nieuwe[] = [
                'id'             => (string) Str::uuid(),
                'club_id'        => $this->clubId,
                'agenda_item_id' => $this->agendaItemId,
                'code'           => $code,
                'label'          => $label !== '' ? $label : null,
                'max_uses'       => $this->maxUses,
                'used_count'     => 0,
                'is_active'      => true,
                'created_at'     => $nu,
                'updated_at'     => $nu,
            ];
        }

        foreach (array_chunk($nieuwe, 500) as $blok) {
            AccessCode::insert($blok);
        }

        $this->created  = count($nieuwe);
        $this->imported = $this->created;
    }
}
