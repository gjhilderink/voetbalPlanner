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
 * Dit commando doet dat eenmalig. Standaard vult het alleen aan en haalt het
 * niemand weg. Met --opschonen gaan ook coaches eraf die geen staf (meer) zijn
 * van het elftal: dat zijn de resten van iemand die ooit verkeerd stond en later
 * is rechtgezet, want de koppeling op de wedstrijd blijft dan gewoon staan.
 */
class BackfillMatchCoaches extends Command
{
    protected $signature = 'wedstrijden:coaches-aanvullen
        {--dry-run : Alleen tonen wat er zou veranderen}
        {--team= : Beperk tot dit elftal (naam of id)}
        {--opschonen : Haal ook coaches weg die geen staf (meer) zijn van het elftal}';

    protected $description = 'Vult ontbrekende standaardcoaches aan op bestaande wedstrijden';

    public function handle(): int
    {
        $droog     = (bool) $this->option('dry-run');
        $opschonen = (bool) $this->option('opschonen');
        $team      = $this->option('team');

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
        /** @var array<string, array<int, string>> $staf */
        $staf = [];
        $gewijzigd = 0;
        $bekeken   = 0;

        FootballMatch::query()
            ->whereNotNull('team_id')
            ->when($teamId, fn ($q) => $q->where('team_id', $teamId))
            ->with(['team', 'coaches:id,name'])
            ->chunkById(200, function ($wedstrijden) use (&$standaard, &$staf, &$gewijzigd, &$bekeken, $droog, $opschonen): void {
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

                    // Coaches die geen staf (meer) zijn van dit elftal. Dat
                    // gebeurt als iemand ooit verkeerd stond en dat later is
                    // rechtgezet: de bron klopt dan weer, maar de koppeling op de
                    // wedstrijd blijft staan, want die voegt alleen toe.
                    $vreemd = [];
                    if ($opschonen) {
                        $staf[$wedstrijd->team_id] ??= $wedstrijd->team?->staffMemberIds() ?? [];
                        $vreemd = array_values(array_diff($huidig, $staf[$wedstrijd->team_id]));
                    }

                    if (empty($ontbreekt) && empty($vreemd) && $wedstrijd->coach_id) {
                        continue;
                    }

                    $namen = $wedstrijd->team?->matchDefaultCoaches()
                        ->whereIn('id', $ontbreekt)
                        ->pluck('name')
                        ->join(', ');

                    $weg = $wedstrijd->coaches
                        ->whereIn('id', $vreemd)
                        ->pluck('name')
                        ->join(', ');

                    $wat = array_filter([
                        $namen !== '' ? "+ {$namen}" : null,
                        $weg !== '' ? "- {$weg}" : null,
                    ]);

                    $this->line(sprintf(
                        '  %s %s — %s: %s',
                        $wedstrijd->match_datetime?->format('d-m-Y') ?? '??-??-????',
                        $wedstrijd->team?->name ?? '?',
                        $wedstrijd->opponent ?? '?',
                        $wat ? implode('   ', $wat) : 'coach_id invullen',
                    ));

                    if (! $droog) {
                        if ($vreemd) {
                            $wedstrijd->coaches()->detach($vreemd);

                            // Stond de weggehaalde coach ook als coach_id, dan
                            // schuift de eerstvolgende door; anders blijft er een
                            // naam staan die nergens meer aan hangt.
                            if (in_array($wedstrijd->coach_id, $vreemd, true)) {
                                $wedstrijd->coach_id = null;
                                $wedstrijd->save();
                            }
                        }

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
