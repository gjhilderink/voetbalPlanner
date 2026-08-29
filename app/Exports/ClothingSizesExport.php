<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\ClothingItem;
use App\Models\Member;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * De kledingmaten van een elftal als Excel-bestand — het lijstje dat mee kan
 * naar de leverancier.
 *
 * De kolommen komen uit de kledingstukken van de club, net als op het scherm.
 * Zou de export een vaste kolomindeling hebben, dan klopt hij niet meer zodra de
 * commissie er een kledingstuk bij zet.
 */
class ClothingSizesExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    /** @var \Illuminate\Support\Collection<int, ClothingItem>|null */
    private ?Collection $stukken = null;

    public function __construct(
        private readonly ?string $clubId,
        private readonly ?string $teamId = null,
    ) {}

    /** @return \Illuminate\Support\Collection<int, ClothingItem> */
    private function kledingstukken(): Collection
    {
        return $this->stukken ??= ClothingItem::query()
            ->when($this->clubId, fn (Builder $q) => $q->where('club_id', $this->clubId))
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function collection(): Collection
    {
        return Member::query()
            ->with(['clothingSizes.size', 'teams:id,name'])
            ->where('is_active', true)
            ->when(
                $this->clubId,
                fn (Builder $q) => $q->whereHas('teams', fn (Builder $t) => $t->where('teams.club_id', $this->clubId)),
            )
            ->when(
                $this->teamId,
                fn (Builder $q) => $q->whereHas('teams', fn (Builder $t) => $t->where('teams.id', $this->teamId)),
            )
            ->orderBy('name')
            ->get();
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return [
            'Lid',
            'Elftal',
            ...$this->kledingstukken()->pluck('name')->all(),
        ];
    }

    /** @return array<int, string> */
    public function map($member): array
    {
        // De elftallen erbij: exporteer je zonder filter, dan is anders niet te
        // zien bij welke ploeg iemand hoort.
        $elftallen = $this->teamId
            ? (Team::find($this->teamId)?->name ?? '')
            : $member->teams->pluck('name')->join(', ');

        return [
            $member->name,
            $elftallen,
            ...$this->kledingstukken()
                ->map(fn (ClothingItem $stuk) => $member->clothingSizes
                    ->firstWhere('clothing_item_id', $stuk->id)?->size?->label ?? '')
                ->all(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
