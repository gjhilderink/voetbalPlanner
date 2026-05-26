<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\Client\Response;
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
        $this->apiKey = Setting::get('mcp_api_key', config('services.mcp.api_key', ''));
        $this->timeout = (int) Setting::get('mcp_timeout', config('services.mcp.timeout', 30));
    }

    // Build absolute URL, avoiding Guzzle base-URL path-replacement quirks.
    private function url(string $path = ''): string
    {
        return $this->baseUrl . '/' . ltrim($path, '/');
    }

    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
        ];
    }

    public function healthCheck(): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->timeout(10)
                ->retry(1, 0, null, false)
                ->get($this->baseUrl);

            return match(true) {
                $response->successful() => [
                    'connected' => true,
                    'status'    => $response->status(),
                    'message'   => 'Verbinding succesvol (HTTP ' . $response->status() . ')',
                ],
                in_array($response->status(), [401, 403]) => [
                    'connected' => true,
                    'status'    => $response->status(),
                    'message'   => 'Server bereikbaar maar authenticatie mislukt — controleer uw API sleutel.',
                ],
                $response->status() === 404 => [
                    'connected' => true,
                    'status'    => $response->status(),
                    'message'   => 'Verbinding werkt — server reageert correct.',
                ],
                default => [
                    'connected' => true,
                    'status'    => $response->status(),
                    'message'   => 'Server bereikbaar (HTTP ' . $response->status() . ').',
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
        return $this->get('teams');
    }

    public function getMembers(?string $teamId = null): array
    {
        $params = $teamId ? '?team_id=' . $teamId : '';
        return $this->get('members' . $params);
    }

    public function getMatches(?string $teamId = null, ?string $season = null): array
    {
        $params = array_filter(['team_id' => $teamId, 'season' => $season]);
        $qs = $params ? '?' . http_build_query($params) : '';
        return $this->get('matches' . $qs);
    }

    public function getCoaches(): array
    {
        return $this->get('coaches');
    }

    private function get(string $path): array
    {
        $url = $this->url($path);
        try {
            $response = Http::withHeaders($this->headers())
                ->timeout($this->timeout)
                ->retry(3, 1000, null, false)
                ->get($url);

            Log::debug('MCP GET', [
                'url'    => $url,
                'status' => $response->status(),
                'body'   => substr($response->body(), 0, 500),
            ]);

            if ($response->failed()) {
                Log::warning('MCP request failed', ['url' => $url, 'status' => $response->status()]);
                return [];
            }

            return $response->json() ?? [];
        } catch (\Throwable $e) {
            Log::error('MCP request error', ['url' => $url, 'error' => $e->getMessage()]);
            return [];
        }
    }

    public function isConfigured(): bool
    {
        return !empty($this->baseUrl) && !empty($this->apiKey);
    }

    public function discoverApi(): array
    {
        $base    = $this->baseUrl;
        $results = ['base_url_used' => $base, 'api_key_set' => !empty($this->apiKey)];

        $probe = function (string $method, string $url, array $body = [], array $override = []) use (&$results): void {
            $label = $method . ' ' . str_replace($this->baseUrl, '', $url);
            try {
                $headers  = array_merge($this->headers(), $override);
                $r        = Http::withHeaders($headers)->timeout(10)->retry(1, 0, null, false);
                $response = $method === 'POST' ? $r->post($url, $body) : $r->get($url);
                $results[$label] = [
                    'status' => $response->status(),
                    'body'   => $response->json() ?? substr($response->body(), 0, 500),
                ];
            } catch (\Throwable $e) {
                $results[$label] = ['error' => $e->getMessage()];
            }
        };

        // FIX: PHP [] encodes as JSON array — MCP needs {} (object) for empty params
        $toolsList = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list', 'params' => new \stdClass()];

        // MCP endpoint with fixed params — should return tool list
        $probe('POST', $base . '/mcp', $toolsList);

        // REST /teams — try every common auth format
        $probe('GET', $base . '/teams');                                                              // Bearer (default)
        $probe('GET', $base . '/teams', [], ['Authorization' => $this->apiKey]);                     // raw token, no Bearer
        $probe('GET', $base . '/teams', [], ['Authorization' => 'Token ' . $this->apiKey]);          // Token prefix
        $probe('GET', $base . '/teams', [], ['Authorization' => '', 'X-API-Key' => $this->apiKey]); // X-API-Key header
        $probe('GET', $base . '/teams', [], ['Authorization' => '']);                                // no auth → expect 401

        return $results;
    }
}
