<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Models\Team;
use Illuminate\Console\Command;

/**
 * Zet de standaardcoaches van een elftal alsnog op bestaande wedstrijden.
 *
 * De sync en de import vullen alleen een gat: staat er al iemand op de
 * wedstrijd, dan blijven ze eraf. Dat is bewust — anders komt een coach die je
 * er bewust afhaalt bij de volgende sync gewoon terug. Het gevolg is wel dat een
 * wedstrijd die ooit met één coach is aangemaakt die tweede nooit meer krijgt.
 *
 * Dit commando doet dat eenmalig. Het haalt niemand weg; het vult alleen aan.
 */
class BackfillMatchCoaches extends Command
{
    protected $signature = 'wedstrijden:coaches-aanvullen
        {--dry-run : Alleen tonen wat er zou veranderen}
        {--team= : Beperk tot dit elftal (naam of id)}';

    protected $description = 'Vult ontbrekende standaardcoaches aan op bestaande wedstrijden';

    public function handle(): int
    {
        $droog = (bool) $this->option('dry-run');
        $team  = $this->option('team');

        $teamId = null;
        if ($team) {
            $gevonden = Team::where('id', $team)->orWhere('name', $team)->first();

            if (! $gevonden) {
                $this->error("Elftal '{$team}' niet gevonden.");

                return self::FAILURE;
            }

            $teamId = $gevonden->id;
            $this->line("Beperkt tot {$gevonden->name}.");
        }

        /** @var array<string, array<int, string>> $standaard */
        $standaard = [];
        $gewijzigd = 0;
        $bekeken   = 0;

        FootballMatch::query()
            ->whereNotNull('team_id')
            ->when($teamId, fn ($q) => $q->where('team_id', $teamId))
            ->with(['team', 'coaches:id,name'])
            ->chunkById(200, function ($wedstrijden) use (&$standaard, &$gewijzigd, &$bekeken, $droog): void {
                foreach ($wedstrijden as $wedstrijd) {
                    $bekeken++;

                    // Per elftal één keer opzoeken; anders is het een query per
                    // wedstrijd en dat zijn er al gauw een paar honderd.
                    $standaard[$wedstrijd->team_id] ??= $wedstrijd->team
                        ?->matchDefaultCoaches()
                        ->pluck('id')
                        ->all() ?? [];

                    $ids = $standaard[$wedstrijd->team_id];

                    if (empty($ids)) {
                        continue;
                    }

                    $huidig    = $wedstrijd->coaches->pluck('id')->all();
                    $ontbreekt = array_diff($ids, $huidig);

                    if (empty($ontbreekt) && $wedstrijd->coach_id) {
                        continue;
                    }

                    $namen = $wedstrijd->team?->matchDefaultCoaches()
                        ->whereIn('id', $ontbreekt)
                        ->pluck('name')
                        ->join(', ');

                    $this->line(sprintf(
                        '  %s %s — %s: %s',
                        $wedstrijd->match_datetime?->format('d-m-Y') ?? '??-??-????',
                        $wedstrijd->team?->name ?? '?',
                        $wedstrijd->opponent ?? '?',
                        $namen !== '' ? "+ {$namen}" : 'coach_id invullen',
                    ));

                    if (! $droog) {
                        $wedstrijd->koppelTeamCoaches($ids, true);
                    }

                    $gewijzigd++;
                }
            });

        $this->newLine();
        $this->info(sprintf(
            '%d wedstrijden bekeken, %d %s.',
            $bekeken,
            $gewijzigd,
            $droog ? 'zouden worden aangevuld' : 'aangevuld',
        ));

        if ($droog && $gewijzigd > 0) {
            $this->comment('Draai zonder --dry-run om dit door te voeren.');
        }

        return self::SUCCESS;
    }
}
