<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Member;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Ledenlijst als Excel-bestand, bedoeld om terug te importeren
 * (zie MembersImport). De ID-kolom is de sleutel waarop de import bestaande
 * leden bijwerkt; blijft die leeg, dan maakt de import een nieuw lid aan.
 *
 * De filters komen mee vanaf de Leden-lijst, zodat je exporteert wat je ziet.
 */
class MembersExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    /** Scheidingsteken tussen de teams van één lid in de Teams-kolom. */
    public const TEAM_SEPARATOR = ';';

    /** Scheidingsteken tussen teamnaam en functie ("JO11-1: Speler"). */
    public const FUNCTION_SEPARATOR = ':';

    /**
     * @param array<string>      $teamIds        Team-filter van de tabel (leeg = alle).
     * @param array<string>|null $allowedTeamIds Beperking voor niet-beheerders (null = geen).
     */
    public function __construct(
        private readonly ?string $clubId,
        private readonly array $teamIds = [],
        private readonly ?string $role = null,
        private readonly ?string $isActive = null,
        private readonly ?array $allowedTeamIds = null,
    ) {}

    public function collection(): Collection
    {
        return Member::query()
            ->with('teams')
            ->when($this->clubId, fn ($q) => $q->whereHas('teams', fn ($t) => $t->where('club_id', $this->clubId)))
            ->when($this->allowedTeamIds !== null, fn ($q) => $q->whereHas('teams', fn ($t) => $t->whereIn('teams.id', $this->allowedTeamIds)))
            ->when($this->teamIds, fn ($q) => $q->whereHas('teams', fn ($t) => $t->whereIn('teams.id', $this->teamIds)))
            ->when($this->role, fn ($q) => $q->where('role', $this->role))
            ->when($this->isActive !== null && $this->isActive !== '', fn ($q) => $q->where('is_active', (bool) $this->isActive))
            ->orderBy('name')
            ->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Naam',
            'Rugnummer',
            'Geboortedatum',
            'Rol',
            'E-mail',
            'Mobiel',
            'Actief',
            'Teams',
            'Relatiecode',
        ];
    }

    public function map($record): array
    {
        return [
            $record->id,
            $record->name,
            (string) ($record->shirt_number ?? ''),
            $record->date_of_birth?->format('d-m-Y') ?? '',
            self::roleLabel($record->role),
            $record->email ?? '',
            $record->phone ?? '',
            $record->is_active ? 'Ja' : 'Nee',
            self::teamsCell($record),
            // Relatiecode komt uit Sportlink en is alleen ter herkenning: de
            // import gebruikt hem om te matchen, maar schrijft hem nooit weg.
            $record->external_id ?? '',
        ];
    }

    /**
     * Alle teams van een lid in één cel: "JO11-1: Speler; JO13-2: Leider".
     * Eén kolom in plaats van vaste kolomparen, zodat een lid met veel teams
     * niet stilzwijgend wordt afgekapt bij het terugimporteren.
     */
    public static function teamsCell($record): string
    {
        return $record->teams
            ->sortBy('name')
            ->map(fn ($team) => $team->name
                . self::FUNCTION_SEPARATOR . ' '
                . (Member::TEAM_FUNCTIONS[$team->pivot->role] ?? $team->pivot->role))
            ->implode(self::TEAM_SEPARATOR . ' ');
    }

    /** Nederlands label voor de hoofdrol van een lid. */
    public static function roleLabel(?string $role): string
    {
        return match ($role) {
            'player'  => 'Speler',
            'coach'   => 'Coach',
            'medical' => 'Medische staf',
            'staff'   => 'Overige staf',
            default   => (string) $role,
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
