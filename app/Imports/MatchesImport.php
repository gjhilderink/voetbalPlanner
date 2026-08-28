<?php

declare(strict_types=1);

namespace App\Imports;

use App\Imports\Concerns\ParsesCells;
use App\Models\FootballMatch;
use App\Models\Member;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Leest een (aangepaste) wedstrijdexport terug in.
 *
 * Matchen gaat op ID; is die leeg, dan wordt een nieuwe wedstrijd aangemaakt
 * (team, tegenstander, datum en tijd zijn dan verplicht). Lege cellen laten bij
 * een bestaande wedstrijd de huidige waarde staan — wissen doe je in het paneel.
 */
class MatchesImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    use ParsesCells;

    public int $imported = 0;
    public int $created  = 0;
    public int $skipped  = 0;

    /** @var array<string> */
    public array $errors = [];

    public function __construct(private readonly string $clubId) {}

    public function collection(Collection $rows): void
    {
        $teams = Team::where('club_id', $this->clubId)
            ->get()
            ->keyBy(fn ($t) => mb_strtolower(trim($t->name)));

        $members = Member::whereHas('teams', fn ($q) => $q->where('club_id', $this->clubId))
            ->get()
            ->keyBy(fn ($m) => mb_strtolower(trim($m->name)));

        foreach ($rows as $index => $row) {
            // +2: rij 1 is de kopregel, en de index is nul-gebaseerd.
            $rowNum = $index + 2;

            $id       = trim((string) ($row['id'] ?? ''));
            $teamName = trim((string) ($row['team'] ?? ''));
            $opponent = trim((string) ($row['tegenstander'] ?? ''));
            $dateRaw  = trim((string) ($row['datum'] ?? ''));
            $timeRaw  = trim((string) ($row['tijd'] ?? ''));

            $match = $id !== ''
                ? FootballMatch::query()
                    ->whereHas('team', fn ($q) => $q->where('club_id', $this->clubId))
                    ->whereKey($id)
                    ->first()
                : null;

            if ($id !== '' && ! $match) {
                $this->errors[] = "Rij {$rowNum}: wedstrijd met ID '{$id}' niet gevonden in deze club";
                $this->skipped++;
                continue;
            }

            $isNew = $match === null;

            // Team oplossen (verplicht bij een nieuwe wedstrijd).
            $team = null;
            if ($teamName !== '') {
                $team = $teams->get(mb_strtolower($teamName));
                if (! $team) {
                    $this->errors[] = "Rij {$rowNum}: team '{$teamName}' niet gevonden";
                    $this->skipped++;
                    continue;
                }
            }

            if ($isNew && (! $team || $opponent === '' || $dateRaw === '')) {
                $this->errors[] = "Rij {$rowNum}: nieuwe wedstrijd heeft team, tegenstander en datum nodig";
                $this->skipped++;
                continue;
            }

            $attributes = [];

            if ($team) {
                $attributes['team_id'] = $team->id;
            }
            if ($opponent !== '') {
                $attributes['opponent'] = $opponent;
            }

            // Datum en tijd vormen samen match_datetime. Staat alleen de tijd in
            // het bestand, dan houden we de bestaande datum aan (en omgekeerd).
            if ($dateRaw !== '' || $timeRaw !== '') {
                $datetime = $this->resolveDateTime($dateRaw, $timeRaw, $match, $rowNum);
                if (! $datetime) {
                    $this->skipped++;
                    continue;
                }
                $attributes['match_datetime'] = $datetime;
            }

            $home = trim((string) ($row['thuis'] ?? ''));
            if ($home !== '') {
                $attributes['is_home'] = self::parseBool($home);
            }

            $location = trim((string) ($row['locatie'] ?? ''));
            if ($location !== '') {
                $attributes['location'] = $location;
            }

            $status = trim((string) ($row['status'] ?? ''));
            if ($status !== '') {
                $key = self::statusKey($status);
                if (! $key) {
                    $this->errors[] = "Rij {$rowNum}: onbekende status '{$status}'";
                    $this->skipped++;
                    continue;
                }
                $attributes['status'] = $key;
            }

            $arrival = trim((string) ($row['aanwezig'] ?? ''));
            if ($arrival !== '') {
                $time = self::parseTime($arrival);
                if (! $time) {
                    $this->errors[] = "Rij {$rowNum}: ongeldige aanwezigtijd '{$arrival}'";
                    $this->skipped++;
                    continue;
                }
                $attributes['arrival_time'] = $time;
            }

            $dressingRoom = trim((string) ($row['kleedkamer'] ?? ''));
            if ($dressingRoom !== '') {
                $attributes['dressing_room'] = $dressingRoom;
            }

            $notes = trim((string) ($row['opmerkingen'] ?? ''));
            if ($notes !== '') {
                $attributes['notes'] = $notes;
            }

            // Vlagger en fruitheld op naam, binnen de leden van de club.
            foreach (['vlagger' => 'vlagger_id', 'fruitheld' => 'fruit_hero_id'] as $column => $field) {
                $name = trim((string) ($row[$column] ?? ''));
                if ($name === '') {
                    continue;
                }
                $member = $members->get(mb_strtolower($name));
                if (! $member) {
                    $this->errors[] = "Rij {$rowNum}: lid '{$name}' niet gevonden voor {$column} (overgeslagen)";
                    continue;
                }
                $attributes[$field] = $member->id;
            }

            if ($isNew) {
                $attributes['status'] ??= 'scheduled';
                $match = FootballMatch::create($attributes);
                $this->created++;
            } else {
                $match->fill($attributes)->save();
            }

            // Dezelfde behandeling als bij het ophalen uit Sportlink: de coaches
            // van het elftal komen er standaard op. Zonder dit stond een
            // geïmporteerde wedstrijd zonder coach in de app, terwijl bij het
            // elftal wél iemand bekend is.
            $match->koppelTeamCoaches();

            $this->imported++;
        }
    }

    /**
     * Combineert de datum- en tijdkolom tot één moment. Ontbreekt er een, dan
     * vult de bestaande wedstrijd het gat. Null bij een onleesbare waarde.
     */
    private function resolveDateTime(string $dateRaw, string $timeRaw, ?FootballMatch $match, int $rowNum): ?Carbon
    {
        $date = $dateRaw !== ''
            ? self::parseDate($dateRaw)
            : $match?->match_datetime?->copy();

        if (! $date) {
            $this->errors[] = "Rij {$rowNum}: ongeldige datum '{$dateRaw}'";

            return null;
        }

        $time = $timeRaw !== '' ? self::parseTime($timeRaw) : $match?->match_datetime?->format('H:i:s');

        if ($timeRaw !== '' && ! $time) {
            $this->errors[] = "Rij {$rowNum}: ongeldige tijd '{$timeRaw}'";

            return null;
        }

        [$hour, $minute] = array_pad(explode(':', (string) ($time ?: '00:00')), 2, '0');

        return $date->copy()->setTime((int) $hour, (int) $minute);
    }

    /** Label of sleutel naar een wedstrijdstatus. */
    public static function statusKey(string $value): ?string
    {
        return match (mb_strtolower(trim($value))) {
            'gepland', 'scheduled'     => 'scheduled',
            'gespeeld', 'played'       => 'played',
            'geannuleerd', 'cancelled' => 'cancelled',
            'uitgesteld', 'postponed'  => 'postponed',
            default                    => null,
        };
    }
}
