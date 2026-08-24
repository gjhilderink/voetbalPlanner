<?php

declare(strict_types=1);

namespace App\Imports;

use App\Exports\DriveScheduleExport;
use App\Imports\Concerns\ParsesCells;
use App\Models\FootballMatch;
use App\Models\Member;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Leest een (aangepast) rijschema terug in.
 *
 * Alleen de Rijders- en Verzameltijd-kolom worden geschreven; datum, elftal en
 * tegenstander staan er puur ter herkenning in. Een wedstrijd verplaatsen doe
 * je bij de wedstrijden, niet hier.
 *
 * De Rijders-cel is leidend: staat er niets, dan houdt de wedstrijd geen
 * rijders over. Dat is precies wat het bestand zegt, en zonder die regel kon je
 * een verkeerd ingedeelde rijder nooit via de import weghalen. De Verzameltijd
 * werkt andersom -- die is geen onderwerp van dit bestand, dus een lege cel
 * laat de bestaande tijd staan.
 */
class DriveScheduleImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    use ParsesCells;

    public int $imported = 0;
    public int $created  = 0;
    public int $skipped  = 0;

    /** @var array<string> */
    public array $errors = [];

    /** @var array<string> */
    public array $notices = [];

    /**
     * @param array<string>|null $allowedTeamIds Beperking voor niet-beheerders (null = geen).
     */
    public function __construct(
        private readonly string $clubId,
        private readonly ?array $allowedTeamIds = null,
    ) {}

    public function collection(Collection $rows): void
    {
        // Alle leden van de club één keer opgehaald; namen als sleutel, want dat
        // is wat er in de cel staat. Een naam die twee keer voorkomt levert een
        // lijstje op, zodat we bij twijfel het lid uit het juiste elftal kiezen
        // in plaats van er stilzwijgend eentje te pakken.
        $membersByName = Member::query()
            ->whereHas('teams', fn ($q) => $q->where('club_id', $this->clubId))
            // Niet 'teams:id': bij een belongs-to-many blijft die kolomnaam
            // onbekwaam en botst hij met member_team.id.
            ->with('teams')
            ->get()
            ->groupBy(fn ($m) => mb_strtolower(trim($m->name)));

        foreach ($rows as $index => $row) {
            // +2: rij 1 is de kopregel, en de index is nul-gebaseerd.
            $rowNum = $index + 2;

            $id = trim((string) ($row['id'] ?? ''));
            if ($id === '') {
                $this->errors[] = "Rij {$rowNum}: geen wedstrijd-ID; laat de ID-kolom uit de export staan";
                $this->skipped++;
                continue;
            }

            $match = FootballMatch::query()
                ->with('drivers')
                ->whereHas('team', fn ($t) => $t->where('club_id', $this->clubId))
                ->whereKey($id)
                ->first();

            if (! $match) {
                $this->errors[] = FootballMatch::whereKey($id)->exists()
                    ? "Rij {$rowNum}: wedstrijd met ID '{$id}' hoort niet bij deze club"
                    : "Rij {$rowNum}: wedstrijd met ID '{$id}' bestaat niet";
                $this->skipped++;
                continue;
            }

            // Dezelfde beperking als de tabel: wie alleen zijn eigen elftallen
            // ziet, mag ook alleen díé rijschema's terugzetten.
            if ($this->allowedTeamIds !== null && ! in_array($match->team_id, $this->allowedTeamIds, true)) {
                $this->errors[] = "Rij {$rowNum}: je hebt geen rechten op het elftal van deze wedstrijd";
                $this->skipped++;
                continue;
            }

            [$driverIds, $rowErrors] = $this->driverIds($row, $match, $membersByName, $rowNum);
            if ($rowErrors) {
                // Halve indeling is erger dan geen indeling: laat de wedstrijd
                // met rust en vertel welke naam niet klopte.
                $this->errors = array_merge($this->errors, $rowErrors);
                $this->skipped++;
                continue;
            }

            $arrival = trim((string) ($row['verzameltijd'] ?? ''));
            if ($arrival !== '') {
                $time = self::parseTime($arrival);
                if (! $time) {
                    $this->errors[] = "Rij {$rowNum}: ongeldige verzameltijd '{$arrival}'";
                    $this->skipped++;
                    continue;
                }
                if ($time !== $match->arrival_time) {
                    $match->arrival_time = $time;
                    $match->save();
                }
            }

            $match->drivers()->sync($driverIds);
            $this->imported++;
        }
    }

    /**
     * Zet de Rijders-cel om in lid-id's. Retourneert [ids, fouten]; zodra er een
     * fout in zit hoort de aanroeper de rij te laten liggen.
     *
     * @param  \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, Member>>  $membersByName
     * @return array{0: array<string>, 1: array<string>}
     */
    private function driverIds(
        Collection|array $row,
        FootballMatch $match,
        Collection $membersByName,
        int $rowNum,
    ): array {
        $cel = trim((string) ($row['rijders'] ?? ''));
        if ($cel === '') {
            return [[], []];
        }

        $ids    = [];
        $errors = [];

        // ';' uit de export, maar ook '|' zoals het op het scherm staat en een
        // regelovergang zoals mensen het in Excel typen.
        $delen = preg_split('/[' . preg_quote(DriveScheduleExport::DRIVER_SEPARATOR, '/') . "|\n]+/", $cel) ?: [];

        foreach ($delen as $deel) {
            $naam = trim($deel);
            if ($naam === '') {
                continue;
            }

            $kandidaten = $membersByName[mb_strtolower($naam)] ?? null;
            if (! $kandidaten || $kandidaten->isEmpty()) {
                $errors[] = "Rij {$rowNum}: rijder '{$naam}' niet gevonden bij de leden van deze club";
                continue;
            }

            // Bij een dubbele naam wint het lid uit het elftal van de wedstrijd.
            $lid = $kandidaten->count() === 1
                ? $kandidaten->first()
                : $kandidaten->first(fn ($m) => $m->teams->contains('id', $match->team_id));

            if (! $lid) {
                $errors[] = "Rij {$rowNum}: rijder '{$naam}' komt meerdere keren voor; geen van hen zit in dit elftal";
                continue;
            }

            if (! in_array($lid->id, $ids, true)) {
                $ids[] = $lid->id;
            }
        }

        return [$ids, $errors];
    }
}
