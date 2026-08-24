<?php

declare(strict_types=1);

namespace App\Imports;

use App\Exports\MembersExport;
use App\Filament\Resources\MemberResource;
use App\Imports\Concerns\ParsesCells;
use App\Models\Member;
use App\Models\Team;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Leest een (aangepaste) ledenexport terug in.
 *
 * Matchen gaat op ID, anders op relatiecode; is er geen van beide, dan wordt
 * een nieuw lid aangemaakt. Teams worden alléén aangeraakt als de Teams-cel is
 * ingevuld — zo kun je een bestand met enkel e-mailadressen terugzetten zonder
 * per ongeluk alle teamkoppelingen te wissen.
 */
class MembersImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
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

        foreach ($rows as $index => $row) {
            // +2: rij 1 is de kopregel, en de index is nul-gebaseerd.
            $rowNum = $index + 2;

            $id       = trim((string) ($row['id'] ?? ''));
            $name     = trim((string) ($row['naam'] ?? ''));
            $external = trim((string) ($row['relatiecode'] ?? ''));

            $member = $this->resolveMember($id, $external);

            if ($id !== '' && ! $member) {
                $this->errors[] = "Rij {$rowNum}: lid met ID '{$id}' niet gevonden in deze club";
                $this->skipped++;
                continue;
            }

            $isNew = $member === null;

            if ($isNew && $name === '') {
                $this->errors[] = "Rij {$rowNum}: nieuw lid zonder naam overgeslagen";
                $this->skipped++;
                continue;
            }

            // Teamkoppelingen uit de Teams-cel ("JO11-1: Speler; Heren 1: Leider").
            [$teamRows, $teamErrors] = $this->teamRows($row, $teams, $rowNum);
            $this->errors = array_merge($this->errors, $teamErrors);

            if ($isNew && ! $teamRows) {
                $this->errors[] = "Rij {$rowNum}: nieuw lid '{$name}' heeft minstens één geldig team nodig";
                $this->skipped++;
                continue;
            }

            $attributes = $this->attributes($row, $rowNum, $isNew);
            if ($attributes === null) {
                $this->skipped++;
                continue;
            }

            // Relatiecode vastleggen op leden die er nog geen hebben. Zonder dit
            // werd de kolom alleen gelézen: een nieuw lid kwam binnen zónder
            // relatiecode, en een tweede import van hetzelfde bestand vond hem
            // dus niet terug en maakte iedereen nóg een keer aan. Een afwijkende
            // code op een bestaand lid laten we met rust — dat is een correctie
            // die iemand bewust moet doen.
            if ($external !== '' && ($isNew || ($member?->external_id ?? '') === '')) {
                $bezet = Member::withTrashed()
                    ->where('external_id', $external)
                    ->when($member, fn ($q) => $q->whereKeyNot($member->getKey()))
                    ->exists();

                if ($bezet) {
                    $this->errors[] = "Rij {$rowNum}: relatiecode '{$external}' is al in gebruik bij een ander lid";
                } else {
                    $attributes['external_id'] = $external;
                }
            }

            if ($isNew) {
                $member = Member::create($attributes);
                $this->created++;
            } else {
                $member->fill($attributes)->save();
            }

            // Alleen syncen als de cel daadwerkelijk teams bevatte; anders zou
            // een lege cel alle koppelingen weggooien.
            if ($teamRows) {
                MemberResource::syncTeamFunctions($member, $teamRows);
            }

            $this->imported++;
        }
    }

    /**
     * Zoekt het bestaande lid op ID of relatiecode, altijd binnen de eigen club.
     */
    private function resolveMember(string $id, string $external): ?Member
    {
        $inClub = fn ($query) => $query->whereHas('teams', fn ($t) => $t->where('club_id', $this->clubId));

        if ($id !== '') {
            return Member::query()->tap($inClub)->whereKey($id)->first();
        }

        if ($external !== '') {
            return Member::query()->tap($inClub)->where('external_id', $external)->first();
        }

        return null;
    }

    /**
     * Bouwt de te schrijven kolommen. Lege cellen laten bij een bestaand lid de
     * huidige waarde staan; bij een nieuw lid vallen ze terug op de standaard.
     * Retourneert null als de rij een harde fout bevat (bv. rare datum).
     */
    private function attributes(Collection|array $row, int $rowNum, bool $isNew): ?array
    {
        $attributes = [];

        $name = trim((string) ($row['naam'] ?? ''));
        if ($name !== '') {
            $attributes['name'] = $name;
        }

        $email = trim((string) ($row['e_mail'] ?? ($row['email'] ?? '')));
        if ($email !== '') {
            $attributes['email'] = $email;
        }

        $phone = trim((string) ($row['mobiel'] ?? ''));
        if ($phone !== '') {
            $attributes['phone'] = $phone;
        }

        $dob = trim((string) ($row['geboortedatum'] ?? ''));
        if ($dob !== '') {
            $date = self::parseDate($dob);
            if (! $date) {
                $this->errors[] = "Rij {$rowNum}: ongeldige geboortedatum '{$dob}'";

                return null;
            }
            $attributes['date_of_birth'] = $date->toDateString();
        }

        $role = trim((string) ($row['rol'] ?? ''));
        if ($role !== '') {
            $key = self::roleKey($role);
            if (! $key) {
                $this->errors[] = "Rij {$rowNum}: onbekende rol '{$role}'";

                return null;
            }
            $attributes['role'] = $key;
        } elseif ($isNew) {
            $attributes['role'] = Member::ROLE_PLAYER;
        }

        $active = trim((string) ($row['actief'] ?? ''));
        if ($active !== '') {
            $attributes['is_active'] = self::parseBool($active);
        } elseif ($isNew) {
            $attributes['is_active'] = true;
        }

        return $attributes;
    }

    /**
     * Leest de Teams-cel uit: "JO11-1: Speler; JO13-2: Leider". De functie mag
     * ontbreken ("JO11-1"), dan wordt het Speler.
     *
     * @return array{0: array<int, array{team_id: string, role: string}>, 1: array<string>}
     */
    private function teamRows(Collection|array $row, Collection $teams, int $rowNum): array
    {
        $rows   = [];
        $errors = [];

        $cell = trim((string) ($row['teams'] ?? ''));
        if ($cell === '') {
            return [$rows, $errors];
        }

        $separator = preg_quote(MembersExport::TEAM_SEPARATOR, '/');

        foreach (preg_split('/[' . $separator . "\n]+/", $cell) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            [$teamName, $functionLabel] = array_pad(
                array_map('trim', explode(MembersExport::FUNCTION_SEPARATOR, $part, 2)),
                2,
                '',
            );

            $team = $teams->get(mb_strtolower($teamName));
            if (! $team) {
                $errors[] = "Rij {$rowNum}: team '{$teamName}' niet gevonden (overgeslagen)";
                continue;
            }

            $rows[] = [
                'team_id' => $team->id,
                'role'    => self::functionKey($functionLabel) ?? Member::ROLE_PLAYER,
            ];
        }

        return [$rows, $errors];
    }

    /** Label of sleutel naar de hoofdrol van een lid. */
    public static function roleKey(string $value): ?string
    {
        return match (mb_strtolower(trim($value))) {
            'speler', 'player'                    => 'player',
            'coach', 'trainer'                    => 'coach',
            'medische staf', 'medical'            => 'medical',
            'overige staf', 'staf', 'staff'       => 'staff',
            default                               => null,
        };
    }

    /** Label of sleutel naar een functie binnen een team (member_team.role). */
    public static function functionKey(string $value): ?string
    {
        $normalised = mb_strtolower(trim($value));

        if ($normalised === '') {
            return null;
        }

        // Exacte sleutels uit TEAM_FUNCTIONS accepteren we ook.
        if (array_key_exists($normalised, Member::TEAM_FUNCTIONS)) {
            return $normalised;
        }

        return match ($normalised) {
            'speler'                                        => Member::ROLE_PLAYER,
            'coach / trainer', 'coach', 'trainer'           => Member::ROLE_COACH,
            'assistent-trainer', 'assistent', 'assistent-coach' => Member::ROLE_ASSISTANT,
            'leider'                                        => Member::ROLE_LEIDER,
            default                                         => null,
        };
    }
}
