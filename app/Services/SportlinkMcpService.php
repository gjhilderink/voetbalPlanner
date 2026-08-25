<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SportlinkMcpService
{
    private string $baseUrl;
    private string $apiKey;
    private int $timeout;
    private ?string $clubId = null;

    public function __construct()
    {
        $this->bootSettings();
    }

    public function forClub(?string $clubId): static
    {
        $this->clubId = $clubId;
        $this->bootSettings();
        return $this;
    }

    private function bootSettings(): void
    {
        $this->baseUrl = rtrim(
            Setting::get('mcp_base_url', config('services.mcp.base_url', ''), $this->clubId),
            '/'
        );
        $this->apiKey  = Setting::get('mcp_api_key', config('services.mcp.api_key', ''), $this->clubId);
        $this->timeout = (int) Setting::get('mcp_timeout', config('services.mcp.timeout', 30), $this->clubId);
    }

    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
        ];
    }

    // Parse SSE response body: "event: message\ndata: {...}\n\n"
    private function parseSSE(string $body): array
    {
        foreach (explode("\n", $body) as $line) {
            $line = trim($line);
            if (str_starts_with($line, 'data: ')) {
                $decoded = json_decode(substr($line, 6), true);
                if ($decoded !== null) {
                    return $decoded;
                }
            }
        }
        return [];
    }

    // Central MCP JSON-RPC caller
    private function mcpPost(string $method, array $params = []): array
    {
        $url = $this->baseUrl . '/mcp';
        try {
            $response = Http::withHeaders($this->headers())
                ->timeout($this->timeout)
                ->retry(3, 1000, null, false)
                ->post($url, [
                    'jsonrpc' => '2.0',
                    'id'      => time(),
                    'method'  => $method,
                    'params'  => empty($params) ? new \stdClass() : $params,
                ]);

            $data = $this->parseSSE($response->body()) ?: ($response->json() ?? []);

            Log::debug('MCP call', [
                'method' => $method,
                'status' => $response->status(),
                'result' => $data,
            ]);

            return $data;
        } catch (\Throwable $e) {
            Log::error('MCP call failed', ['method' => $method, 'error' => $e->getMessage()]);
            return [];
        }
    }

    // Call a named tool and return its content payload
    private function callTool(string $name, array $arguments = []): mixed
    {
        $data = $this->mcpPost('tools/call', [
            'name'      => $name,
            'arguments' => empty($arguments) ? new \stdClass() : $arguments,
        ]);

        // MCP result: {result: {content: [{type: "text", text: "...JSON..."}]}}
        $content = $data['result']['content'] ?? [];

        foreach ($content as $item) {
            if (($item['type'] ?? '') === 'text') {
                $decoded = json_decode($item['text'], true);
                return $decoded ?? $item['text'];
            }
        }

        // Fallback: return raw result
        return $data['result'] ?? $data;
    }

    public function healthCheck(): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->timeout(10)
                ->retry(1, 0, null, false)
                ->get($this->baseUrl . '/health');

            return match(true) {
                $response->successful() => [
                    'connected' => true,
                    'status'    => $response->status(),
                    'message'   => 'Verbinding succesvol — ' . ($response->json('server') ?? 'server') . ' is bereikbaar.',
                ],
                in_array($response->status(), [401, 403]) => [
                    'connected' => true,
                    'status'    => $response->status(),
                    'message'   => 'Server bereikbaar maar authenticatie mislukt — controleer uw API sleutel.',
                ],
                default => [
                    'connected' => true,
                    'status'    => $response->status(),
                    'message'   => 'Verbinding werkt — server reageert correct.',
                ],
            };
        } catch (\Throwable $e) {
            Log::error('MCP health check failed', ['error' => $e->getMessage()]);
            return [
                'connected' => false,
                'status'    => 0,
                'message'   => 'Server niet bereikbaar: ' . $e->getMessage(),
            ];
        }
    }

    public function getTeams(): array
    {
        $result = $this->callTool('get_teams');
        return is_array($result) ? $result : [];
    }

    public function getMembers(?string $teamCode = null): array
    {
        // teamcode MUST be a string — the MCP schema validates it as string
        if ($teamCode) {
            $result = $this->callTool('get_team_players', ['teamcode' => (string) $teamCode, 'toon_foto' => false]);
            return is_array($result) ? $result : [];
        }

        // Deduplicate teams by teamcode before iterating (same team appears once per competition)
        $seen       = [];
        $allPlayers = [];
        foreach ($this->getTeams() as $team) {
            $code = (string) ($team['teamcode'] ?? $team['id'] ?? '');
            if ($code === '' || isset($seen[$code])) {
                continue;
            }
            $seen[$code] = true;

            $players = $this->callTool('get_team_players', ['teamcode' => $code, 'toon_foto' => false]);
            if (is_array($players)) {
                foreach ($players as $p) {
                    $p['_teamcode'] = $code;
                    $allPlayers[]   = $p;
                }
            }
        }
        return $allPlayers;
    }

    public function getSchedule(?string $teamCode = null, int $days = 365): array
    {
        $args = array_filter(['teamcode' => $teamCode, 'aantaldagen' => $days]);
        $result = $this->callTool('get_schedule', $args);
        return is_array($result) ? $result : [];
    }

    public function getResults(?string $teamCode = null, int $days = 365): array
    {
        $args = array_filter(['teamcode' => $teamCode, 'aantaldagen' => $days]);
        $result = $this->callTool('get_results', $args);
        return is_array($result) ? $result : [];
    }

    public function getMatches(?string $teamCode = null): array
    {
        $schedule = $this->getSchedule($teamCode);
        $results  = $this->getResults($teamCode);
        return array_merge($schedule, $results);
    }

    public function getCoaches(): array
    {
        $result = $this->callTool('get_coaches');
        if (!is_array($result)) {
            $result = $this->callTool('get_trainers');
        }
        return is_array($result) ? $result : [];
    }

    public function listTools(): array
    {
        $data = $this->mcpPost('tools/list');
        return $data['result']['tools'] ?? [];
    }

    /**
     * Welke argumenten accepteert een tool? Uit tools/list, kort gecachet.
     *
     * De MCP-kant gebruikt Nederlandse namen (teamcode, aantaldagen), maar die
     * verschillen per tool. Door het schema te lezen in plaats van te gokken
     * sturen we nooit een argument dat de server niet kent — dat leverde anders
     * een stilzwijgend leeg antwoord op.
     *
     * @return array<string>
     */
    private function toolArguments(string $tool): array
    {
        $alle = Cache::remember(
            'mcp_tool_args_' . md5($this->baseUrl),
            now()->addMinutes(30),
            function (): array {
                $map = [];
                foreach ($this->listTools() as $t) {
                    $naam = $t['name'] ?? null;
                    if (! $naam) {
                        continue;
                    }
                    $map[$naam] = array_keys($t['inputSchema']['properties'] ?? []);
                }
                return $map;
            },
        );

        return $alle[$tool] ?? [];
    }

    /**
     * Roept een tool aan met alleen de argumenten die hij kent.
     *
     * @param  array<string, mixed>  $kandidaten  naam => waarde; lege waarden vallen weg
     */
    private function callToolFiltered(string $name, array $kandidaten): mixed
    {
        $toegestaan = $this->toolArguments($name);

        $args = [];
        foreach ($kandidaten as $sleutel => $waarde) {
            if ($waarde === null || $waarde === '') {
                continue;
            }
            // Kent de server geen schema (lege lijst), dan alles meesturen: beter
            // een afgewezen argument dan helemaal geen filter.
            if ($toegestaan && ! in_array($sleutel, $toegestaan, true)) {
                continue;
            }
            $args[$sleutel] = $waarde;
        }

        return $this->callTool($name, $args);
    }

    /**
     * De naam waaronder een tool op déze server staat.
     *
     * Namen verschillen per MCP-server en bevatten wel eens een typefout
     * (get_staning). Opzoeken in plaats van vastleggen scheelt een aanpassing
     * aan beide kanten zodra er iets hernoemd wordt.
     *
     * @param  array<string>  $kandidaten  exacte namen, in volgorde van voorkeur
     * @param  string  $bevat  losse zoekterm als geen enkele kandidaat past
     */
    private function resolveTool(array $kandidaten, string $bevat): ?string
    {
        $beschikbaar = array_keys(Cache::remember(
            'mcp_tool_args_' . md5($this->baseUrl),
            now()->addMinutes(30),
            function (): array {
                $map = [];
                foreach ($this->listTools() as $t) {
                    if ($naam = $t['name'] ?? null) {
                        $map[$naam] = array_keys($t['inputSchema']['properties'] ?? []);
                    }
                }
                return $map;
            },
        ));

        foreach ($kandidaten as $kandidaat) {
            if (in_array($kandidaat, $beschikbaar, true)) {
                return $kandidaat;
            }
        }

        foreach ($beschikbaar as $naam) {
            if (str_contains(strtolower($naam), $bevat)) {
                return $naam;
            }
        }

        return null;
    }

    /** Hoe de stand-tool op deze server heet, of null als hij ontbreekt. */
    private function standingTool(): ?string
    {
        return $this->resolveTool(['get_standings', 'get_standing', 'get_staning', 'get_stand'], 'stan');
    }

    /** De poules waarin de club uitkomt. */
    public function getPoules(?string $teamCode = null): array
    {
        $tool = $this->resolveTool(['get_poules', 'get_poule'], 'poule');
        if (! $tool) {
            return [];
        }

        $result = $this->callToolFiltered($tool, [
            'teamcode' => $teamCode !== null ? (string) $teamCode : null,
        ]);

        return self::rijenUit($result);
    }

    /** Heeft deze server überhaupt een tool voor de stand? */
    public function hasStandingTool(): bool
    {
        return $this->standingTool() !== null;
    }

    /**
     * De stand van een poule. Accepteert de tool een teamcode, dan is dat genoeg;
     * anders moet er een poulecode bij.
     */
    public function getStanding(?string $pouleCode = null, ?string $teamCode = null): array
    {
        $tool = $this->standingTool();
        if (! $tool) {
            return [];
        }

        $result = $this->callToolFiltered($tool, [
            'poulecode' => $pouleCode !== null ? (string) $pouleCode : null,
            'teamcode'  => $teamCode !== null ? (string) $teamCode : null,
        ]);

        return self::rijenUit($result);
    }

    /**
     * Haalt de regels uit een antwoord. Een tool geeft soms een platte lijst en
     * soms een object met de lijst eronder ({"poule": "...", "stand": [...]});
     * dan zou een simpele is_array-check nul bruikbare regels opleveren.
     *
     * @param  mixed  $result
     */
    private static function rijenUit($result): array
    {
        // Soms komt het antwoord als JSON-tekst binnen in plaats van als array:
        // dat gebeurt zodra de tool niet de gebruikelijke content-verpakking
        // gebruikt en callTool de ruwe waarde teruggeeft.
        if (is_string($result)) {
            $decoded = json_decode($result, true);
            $result = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($result)) {
            return [];
        }

        // Platte lijst: eerste element is een rij.
        if (array_is_list($result)) {
            return $result;
        }

        // Object: de eerste sleutel met een lijst van rijen erin wint. Een
        // diagnose-blok ernaast valt vanzelf af — dat is geen lijst, en zijn
        // veldnamenlijst bevat strings in plaats van rijen.
        foreach ($result as $waarde) {
            if (is_array($waarde) && array_is_list($waarde) && $waarde !== []
                && is_array($waarde[0])) {
                return $waarde;
            }
        }

        return [];
    }

    /**
     * De stand voor één elftal, ongeacht hoe de tool hem wil hebben.
     *
     * Kan get_standing overweg met een teamcode, dan zijn we klaar. Zo niet, dan
     * zoeken we de poulecode op — eerst in get_poules, anders in de uitslagen van
     * dat team, want die dragen die code sinds kort mee.
     */
    public function standingForTeam(string $teamCode, ?string $teamNaam = null): array
    {
        $tool = $this->standingTool();
        if (! $tool) {
            return [];
        }

        // Eén elftal staat in get_teams één keer per competitie, elk met een
        // eigen teamcode. De sync bewaart er daarvan één, en dat hoeft niet de
        // competitie te zijn waar een poule aan hangt — dan antwoordt de MCP met
        // "geen poule gevonden". Daarom alle codes van dit elftal langs.
        $codes = [$teamCode];
        if ($teamNaam !== null) {
            foreach ($this->teamCodesVoorNaam($teamNaam) as $code) {
                if (! in_array($code, $codes, true)) {
                    $codes[] = $code;
                }
            }
        }

        $kanOpTeamcode = in_array('teamcode', $this->toolArguments($tool), true);

        foreach ($codes as $code) {
            if ($kanOpTeamcode) {
                $stand = $this->getStanding(teamCode: $code);
                if ($stand) {
                    return $stand;
                }
            }

            $pouleCode = $this->pouleCodeVoorTeam($code);
            if ($pouleCode) {
                $stand = $this->getStanding(pouleCode: $pouleCode);
                if ($stand) {
                    return $stand;
                }
            }
        }

        return [];
    }

    /**
     * Alle teamcodes waaronder dit elftal in get_teams voorkomt.
     *
     * Losjes op naam vergelijken: hoofdletters en spaties verschillen nogal eens
     * tussen wat er in de database staat en wat Sportlink teruggeeft.
     *
     * @return array<string>
     */
    public function teamCodesVoorNaam(string $naam): array
    {
        $normaliseer = fn (string $s) => preg_replace('/[^a-z0-9]/', '', mb_strtolower($s));
        $doel = $normaliseer($naam);
        if ($doel === '') {
            return [];
        }

        $codes = [];
        foreach ($this->getTeams() as $t) {
            if (! is_array($t)) {
                continue;
            }
            $code = (string) ($t['teamcode'] ?? '');
            $naamUitBron = (string) ($t['teamnaam'] ?? $t['naam'] ?? '');
            if ($code === '' || $naamUitBron === '') {
                continue;
            }
            if ($normaliseer($naamUitBron) === $doel) {
                $codes[] = $code;
            }
        }

        return array_values(array_unique($codes));
    }

    /** Zoekt de poulecode van een elftal op. Null als hij nergens te vinden is. */
    public function pouleCodeVoorTeam(string $teamCode): ?string
    {
        foreach ($this->getPoules($teamCode) as $poule) {
            if ($code = self::pouleCodeUit($poule)) {
                return $code;
            }
        }

        foreach ($this->getResults($teamCode, 365) as $uitslag) {
            if ($code = self::pouleCodeUit($uitslag)) {
                return $code;
            }
        }

        return null;
    }

    /**
     * Vist de poulecode uit een rij. De sleutel heet niet overal hetzelfde, dus
     * we zoeken op de eerste sleutel waar 'poule' in zit met een gevulde waarde.
     *
     * @param  mixed  $rij
     */
    private static function pouleCodeUit($rij): ?string
    {
        if (! is_array($rij)) {
            return null;
        }

        foreach (['poulecode', 'poule_code', 'pouleCode'] as $sleutel) {
            if (! empty($rij[$sleutel])) {
                return (string) $rij[$sleutel];
            }
        }

        foreach ($rij as $sleutel => $waarde) {
            if (is_scalar($waarde) && $waarde !== '' && str_contains(strtolower((string) $sleutel), 'poule')) {
                return (string) $waarde;
            }
        }

        return null;
    }

    public function isConfigured(): bool
    {
        return !empty($this->baseUrl) && !empty($this->apiKey);
    }

    public function discoverApi(): array
    {
        $base    = $this->baseUrl;
        $results = ['base_url_used' => $base, 'api_key_set' => !empty($this->apiKey)];

        // Full tools list (no truncation)
        $toolsData = $this->mcpPost('tools/list');
        $results['tools_list'] = $toolsData['result']['tools'] ?? $toolsData;

        // Call get_teams — first 3 unique teams to see field names
        $teams = $this->callTool('get_teams');
        $results['get_teams_sample'] = is_array($teams) ? array_slice($teams, 0, 3) : $teams;

        // Get players for first team — show RAW MCP response to reveal actual field names
        if (is_array($teams) && !empty($teams)) {
            $firstCode = (string) ($teams[0]['teamcode'] ?? '');
            if ($firstCode) {
                // Raw MCP call so we see the full undecoded text response
                $rawResponse = $this->mcpPost('tools/call', [
                    'name'      => 'get_team_players',
                    'arguments' => ['teamcode' => $firstCode, 'toon_foto' => false],
                ]);
                $rawText = $rawResponse['result']['content'][0]['text'] ?? null;
                $decoded = $rawText ? json_decode($rawText, true) : null;

                $results['get_team_players_teamcode']  = $firstCode;
                $results['get_team_players_raw_text']  = $rawText;   // raw JSON string
                $results['get_team_players_decoded']   = is_array($decoded) ? array_slice($decoded, 0, 2) : $decoded;
                $results['get_team_players_type']      = gettype($decoded);
                $results['get_team_players_keys']      = is_array($decoded) && !empty($decoded)
                    ? array_keys((array) $decoded[0])
                    : (is_array($decoded) ? array_keys($decoded) : 'not-array');
            }
        }

        // Upcoming schedule (first 5 matches)
        $scheduleRaw = $this->mcpPost('tools/call', ['name' => 'get_schedule', 'arguments' => ['aantalregels' => 5]]);
        $results['get_schedule_sample'] = $scheduleRaw['result']['content'][0]['text'] ?? $scheduleRaw;

        // Past results (first 5)
        $resultsRaw = $this->mcpPost('tools/call', ['name' => 'get_results', 'arguments' => ['aantalregels' => 5]]);
        $results['get_results_sample'] = $resultsRaw['result']['content'][0]['text'] ?? $resultsRaw;

        return $results;
    }
}
