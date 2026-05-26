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

    public function __construct()
    {
        $this->baseUrl = rtrim(
            Setting::get('mcp_base_url', config('services.mcp.base_url', '')),
            '/'
        );
        $this->apiKey  = Setting::get('mcp_api_key', config('services.mcp.api_key', ''));
        $this->timeout = (int) Setting::get('mcp_timeout', config('services.mcp.timeout', 30));
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
        if ($teamCode) {
            $result = $this->callTool('get_team_players', ['teamcode' => $teamCode]);
            return is_array($result) ? $result : [];
        }

        // Without a team code, get all teams first then collect players
        $allPlayers = [];
        foreach ($this->getTeams() as $team) {
            $code = $team['teamcode'] ?? $team['id'] ?? null;
            if (!$code) continue;
            $players = $this->callTool('get_team_players', ['teamcode' => $code]);
            if (is_array($players)) {
                foreach ($players as $p) {
                    $p['_teamcode'] = $code;
                    $allPlayers[]   = $p;
                }
            }
        }
        return $allPlayers;
    }

    public function getMatches(?string $teamCode = null, ?string $season = null): array
    {
        $args = array_filter(['teamcode' => $teamCode, 'seizoen' => $season]);
        $result = $this->callTool('get_matches', empty($args) ? [] : $args);
        if (!is_array($result)) {
            // Try Dutch variant
            $result = $this->callTool('get_wedstrijden', empty($args) ? [] : $args);
        }
        return is_array($result) ? $result : [];
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

        // Call get_teams to see actual field names and data format
        $results['get_teams_raw'] = $this->callTool('get_teams');

        // Try get_matches / get_wedstrijden
        $matchData = $this->mcpPost('tools/call', ['name' => 'get_matches', 'arguments' => new \stdClass()]);
        $results['get_matches_raw'] = $matchData['result']['content'][0]['text'] ?? $matchData;

        return $results;
    }
}
