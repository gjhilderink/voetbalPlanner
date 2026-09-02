<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Club;
use App\Models\Team;
use App\Services\SportlinkMcpService;
use Illuminate\Console\Command;

/**
 * Wat geeft Sportlink werkelijk terug over de elftallen?
 *
 *   php artisan sportlink:teams              # de ruwe velden van elk elftal
 *   php artisan sportlink:teams --dubbel     # elftallen die dubbel in de portal staan
 *
 * De afbeelding van Sportlink naar onze kolommen staat in TeamDTO en werkt met
 * een rij mogelijke sleutels, omdat de namen per koppeling verschillen. Blijft
 * speeldag of geslacht leeg, dan staat hier hoe het veld daar dan wél heet.
 */
class DiagnoseTeams extends Command
{
    protected $signature = 'sportlink:teams {--club= : Clubnaam of id; standaard de eerste actieve club} {--dubbel : Alleen de dubbele elftallen in de portal tonen}';

    protected $description = 'Toont de ruwe teamgegevens uit Sportlink en de dubbele elftallen in de portal.';

    public function handle(SportlinkMcpService $mcp): int
    {
        $club = $this->kiesClub();

        if (! $club) {
            $this->error('Geen club gevonden.');

            return self::FAILURE;
        }

        $this->info("Club: {$club->name} (id {$club->id})");
        $this->newLine();

        if ($this->option('dubbel')) {
            return $this->toonDubbele($club);
        }

        return $this->toonSportlink($mcp, $club);
    }

    /** De ruwe velden zoals Sportlink ze aanlevert. */
    private function toonSportlink(SportlinkMcpService $mcp, Club $club): int
    {
        $teams = $mcp->forClub($club->id)->getTeams();

        if ($teams === []) {
            $this->warn('Sportlink gaf geen elftallen terug. Staat de koppeling aan?');

            return self::FAILURE;
        }

        $this->info('Sleutels die in het antwoord voorkomen:');
        $sleutels = [];
        foreach ($teams as $team) {
            foreach (array_keys((array) $team) as $sleutel) {
                $sleutels[$sleutel] = ($sleutels[$sleutel] ?? 0) + 1;
            }
        }
        ksort($sleutels);
        foreach ($sleutels as $sleutel => $aantal) {
            $this->line(sprintf('  %-28s in %d van de %d', $sleutel, $aantal, count($teams)));
        }

        $this->newLine();
        $this->info('Per elftal, alles wat er staat:');

        foreach ($teams as $team) {
            $team = (array) $team;
            $this->line('  ── ' . ($team['teamnaam'] ?? $team['name'] ?? '?'));

            foreach ($team as $sleutel => $waarde) {
                if (is_array($waarde) || is_object($waarde)) {
                    $waarde = json_encode($waarde, JSON_UNESCAPED_UNICODE);
                }

                $this->line(sprintf('     %-26s %s', $sleutel, mb_strimwidth((string) $waarde, 0, 60, '…')));
            }
        }

        $this->newLine();
        $this->comment('Zoek hierboven het veld met de speeldag en met het geslacht. Staat de naam'
            . ' niet in TeamDTO::fromMcpData(), voeg hem daar toe aan de rij alternatieven.');

        return self::SUCCESS;
    }

    /** Elftallen die onder dezelfde naam meer dan één keer in de portal staan. */
    private function toonDubbele(Club $club): int
    {
        $teams = Team::where('club_id', $club->id)->orderBy('name')->get()->groupBy('name');
        $dubbel = $teams->filter(fn ($groep) => $groep->count() > 1);

        if ($dubbel->isEmpty()) {
            $this->info('Geen dubbele elftalnamen.');

            return self::SUCCESS;
        }

        $this->warn("Dubbele elftalnamen: {$dubbel->count()}");
        $this->newLine();

        foreach ($dubbel as $naam => $groep) {
            $this->line("  {$naam}");

            foreach ($groep as $team) {
                $this->line(sprintf(
                    '     ext=%-12s soort=%-14s leden=%-4d wedstrijden=%-4d (id %s)',
                    $team->external_id ?? '—',
                    $team->category ?: '—',
                    $team->members()->count(),
                    $team->matches()->count(),
                    $team->id,
                ));
            }
        }

        $this->newLine();
        $this->comment('De regel met de meeste leden en wedstrijden is doorgaans de echte.'
            . ' Verwijderen doe je met de hand in de portal: aan zo\'n elftal kunnen'
            . ' wedstrijden en opstellingen hangen die je niet wilt kwijtraken.');

        return self::SUCCESS;
    }

    private function kiesClub(): ?Club
    {
        $opgegeven = (string) ($this->option('club') ?? '');

        if ($opgegeven !== '') {
            return Club::where('id', $opgegeven)
                ->orWhere('name', 'like', '%' . $opgegeven . '%')
                ->first();
        }

        return Club::where('is_active', true)->orderBy('name')->first();
    }
}
