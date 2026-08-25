<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Team;
use App\Services\SportlinkMcpService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Laat de hele keten zien van MCP-tool tot de rijen die de app krijgt.
 *
 * Bij een lege stand is de vraag altijd dezelfde: heet de tool anders, wil hij
 * andere argumenten, zit het antwoord een niveau dieper, of kloppen de
 * sleutelnamen niet? Dit commando beantwoordt ze alle vier in één keer, zodat
 * dat niet elke keer met een reeks tinker-regels hoeft.
 */
class DiagnoseStanding extends Command
{
    protected $signature = 'sportlink:standing {team? : Naam of teamcode; standaard het eerste elftal met een koppeling}';

    protected $description = 'Toont hoe de poulestand bij de MCP wordt opgehaald en wat er terugkomt.';

    public function handle(SportlinkMcpService $mcp): int
    {
        Cache::forget('mcp_tool_args_' . md5((string) config('services.mcp.base_url')));

        $zoek = $this->argument('team');
        $team = $zoek
            ? Team::where('name', 'LIKE', '%' . $zoek . '%')->orWhere('external_id', $zoek)->first()
            : Team::whereNotNull('external_id')->first();

        if (! $team) {
            $this->error('Geen elftal gevonden.');
            return self::FAILURE;
        }

        $this->line("Elftal:   {$team->name}");
        $this->line("Teamcode: " . ($team->external_id ?: '(geen)'));

        $svc = $mcp->forClub($team->club_id);
        if (! $svc->isConfigured()) {
            $this->error('De MCP-koppeling is niet ingesteld (base_url of api_key ontbreekt).');
            return self::FAILURE;
        }

        // 1. Welke tools zijn er, en met welke argumenten?
        $this->newLine();
        $this->info('1. Beschikbare tools');
        foreach ($svc->listTools() as $t) {
            $naam = $t['name'] ?? '?';
            $args = implode(', ', array_keys($t['inputSchema']['properties'] ?? [])) ?: '(geen)';
            $this->line("   {$naam}  →  {$args}");
        }

        // 1b. Onder welke teamcodes staat dit elftal bekend?
        $this->newLine();
        $this->info('1b. teamcodes voor "' . $team->name . '" in get_teams');
        $codes = $svc->teamCodesVoorNaam($team->name);
        $this->line('   ' . ($codes ? implode(', ', $codes) : '(geen — naam wijkt af van Sportlink)'));

        // 2. Wat komt er uit get_poules?
        $this->newLine();
        $this->info('2. get_poules');
        $poules = $svc->getPoules((string) $team->external_id);
        $this->line('   regels: ' . count($poules));
        if ($poules) {
            $this->line('   sleutels: ' . implode(', ', array_keys((array) $poules[0])));
            $this->line('   eerste:   ' . json_encode($poules[0], JSON_UNESCAPED_UNICODE));
        } else {
            $this->ruweAanroep($svc, 'get_poules', ['teamcode' => (string) $team->external_id]);
        }

        $pouleCode = $svc->pouleCodeVoorTeam((string) $team->external_id);
        $this->line('   gevonden poulecode: ' . ($pouleCode ?: '(geen)'));

        // 3. En uit de stand zelf?
        $this->newLine();
        $this->info('3. stand');
        $rijen = $svc->standingForTeam((string) $team->external_id, $team->name);
        $this->line('   regels: ' . count($rijen));

        if (! $rijen) {
            // Tellingen zeggen niets over wát de server terugstuurt: een lege
            // lijst, een foutmelding of een vorm die we niet uitpakken zien er
            // hier allemaal hetzelfde uit. Dus het ruwe antwoord erbij.
            $this->ruweAanroep($svc, 'get_standings', ['teamcode' => (string) $team->external_id]);
            if ($pouleCode) {
                $this->ruweAanroep($svc, 'get_standings', ['poulecode' => $pouleCode]);
            }
            $this->ruweAanroep($svc, 'get_standings', []);

            return self::SUCCESS;
        }

        $this->line('   sleutels: ' . implode(', ', array_keys((array) $rijen[0])));
        foreach (array_slice($rijen, 0, 3) as $r) {
            $this->line('   ' . json_encode($r, JSON_UNESCAPED_UNICODE));
        }

        return self::SUCCESS;
    }

    /**
     * Doet één aanroep buiten de service om en drukt het ruwe antwoord af.
     *
     * Via reflectie omdat mcpPost private is; dit hoort ook privé te blijven,
     * maar bij een lege uitkomst is de onbewerkte tekst het enige dat je verder
     * helpt.
     *
     * @param  array<string, string>  $args
     */
    private function ruweAanroep(SportlinkMcpService $svc, string $tool, array $args): void
    {
        try {
            $post = new \ReflectionMethod($svc, 'mcpPost');
            $post->setAccessible(true);
            $out = $post->invoke($svc, 'tools/call', [
                'name'      => $tool,
                'arguments' => $args ?: new \stdClass(),
            ]);
        } catch (\Throwable $e) {
            $this->error('   ruwe aanroep mislukt: ' . $e->getMessage());
            return;
        }

        $tekst = $out['result']['content'][0]['text'] ?? null;
        $this->newLine();
        $this->comment('   ruw · ' . $tool . ' ' . (json_encode($args) ?: '{}'));

        if ($tekst === null) {
            $this->line('   ' . mb_substr(json_encode($out, JSON_UNESCAPED_UNICODE) ?: '', 0, 700));
            return;
        }

        $this->line('   ' . mb_substr($tekst, 0, 700));
    }
}
