<?php

declare(strict_types=1);

namespace App\Imports;

use App\Models\BarDuty;
use App\Models\Member;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class BarDutiesImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    public int $imported = 0;
    public int $skipped  = 0;

    /** @var array<string> */
    public array $errors = [];

    public function __construct(private readonly string $clubId) {}

    public function collection(Collection $rows): void
    {
        // Cache teams and members by name for this club
        $teams = Team::where('club_id', $this->clubId)
            ->where('is_active', true)
            ->get()
            ->keyBy(fn($t) => mb_strtolower(trim($t->name)));

        $members = Member::whereHas('teams', fn($q) => $q->where('club_id', $this->clubId))
            ->where('is_active', true)
            ->get()
            ->keyBy(fn($m) => mb_strtolower(trim($m->name)));

        foreach ($rows as $index => $row) {
            $rowNum = $index + 2;

            $dateRaw  = trim((string) ($row['datum']       ?? ''));
            $shift    = trim((string) ($row['dienst']      ?? ''));
            $teamName = trim((string) ($row['elftal']      ?? ''));
            $lid1     = trim((string) ($row['lid_1']       ?? ''));
            $lid2     = trim((string) ($row['lid_2']       ?? ''));
            $status   = trim((string) ($row['status']      ?? 'open'));
            $notes    = trim((string) ($row['opmerkingen'] ?? ''));

            if ($dateRaw === '' && $teamName === '') {
                continue;
            }

            // Parse date (accepts d-m-Y or Y-m-d)
            try {
                $date = Carbon::createFromFormat('d-m-Y', $dateRaw)
                    ?? Carbon::parse($dateRaw);
            } catch (\Throwable) {
                $this->errors[] = "Rij {$rowNum}: ongeldige datum '{$dateRaw}'";
                $this->skipped++;
                continue;
            }

            // Normalise shift
            $shiftMap = [
                'ochtend' => 'ochtend',
                'morning' => 'ochtend',
                'middag'  => 'middag',
                'afternoon' => 'middag',
                'avond'   => 'avond',
                'evening' => 'avond',
            ];
            $shiftKey = $shiftMap[mb_strtolower($shift)] ?? null;
            if (!$shiftKey) {
                $this->errors[] = "Rij {$rowNum}: onbekende dienst '{$shift}' (gebruik Ochtend/Middag/Avond)";
                $this->skipped++;
                continue;
            }

            // Resolve team
            $team = $teams->get(mb_strtolower($teamName));
            if (!$team && $teamName !== '') {
                $this->errors[] = "Rij {$rowNum}: elftal '{$teamName}' niet gevonden";
                $this->skipped++;
                continue;
            }

            // Normalise status
            $statusMap = [
                'open'      => 'open',
                'bevestigd' => 'bevestigd',
                'confirmed' => 'bevestigd',
                'vervuld'   => 'vervuld',
                'fulfilled' => 'vervuld',
            ];
            $statusKey = $statusMap[mb_strtolower($status)] ?? 'open';

            $duty = BarDuty::create([
                'club_id' => $this->clubId,
                'team_id' => $team?->id,
                'date'    => $date->toDateString(),
                'shift'   => $shiftKey,
                'status'  => $statusKey,
                'notes'   => $notes ?: null,
            ]);

            // Attach up to 2 members
            $memberIds = [];
            foreach ([$lid1, $lid2] as $lidName) {
                if ($lidName === '') continue;
                $member = $members->get(mb_strtolower($lidName));
                if ($member) {
                    $memberIds[] = $member->id;
                } else {
                    $this->errors[] = "Rij {$rowNum}: lid '{$lidName}' niet gevonden (overgeslagen)";
                }
            }
            if ($memberIds) {
                $duty->members()->sync($memberIds);
            }

            $this->imported++;
        }
    }
}
