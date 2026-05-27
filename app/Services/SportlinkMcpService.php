<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
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
