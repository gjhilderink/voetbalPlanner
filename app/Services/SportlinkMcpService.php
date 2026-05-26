<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SportlinkMcpService
{
    private string $baseUrl;
    private string $apiKey;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = Setting::get('mcp_base_url', config('services.mcp.base_url', ''));
        $this->apiKey = Setting::get('mcp_api_key', config('services.mcp.api_key', ''));
        $this->timeout = (int) Setting::get('mcp_timeout', config('services.mcp.timeout', 30));
    }

    public function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->timeout($this->timeout)
            ->retry(3, 1000);
    }

    public function healthCheck(): array
    {
        try {
            $response = $this->client()->get('/health');
            return [
                'connected' => $response->successful(),
                'status' => $response->status(),
                'message' => $response->successful() ? 'Verbinding succesvol' : 'Verbinding mislukt',
            ];
        } catch (\Exception $e) {
            Log::error('MCP health check failed', ['error' => $e->getMessage()]);
            return [
                'connected' => false,
                'status' => 0,
                'message' => 'Verbinding mislukt: ' . $e->getMessage(),
            ];
        }
    }

    public function getTeams(): array
    {
        return $this->get('/teams');
    }

    public function getMembers(?string $teamId = null): array
    {
        $endpoint = '/members';
        if ($teamId) {
            $endpoint .= '?team_id=' . $teamId;
        }
        return $this->get($endpoint);
    }

    public function getMatches(?string $teamId = null, ?string $season = null): array
    {
        $params = array_filter([
            'team_id' => $teamId,
            'season' => $season,
        ]);
        $endpoint = '/matches' . ($params ? '?' . http_build_query($params) : '');
        return $this->get($endpoint);
    }

    public function getCoaches(): array
    {
        return $this->get('/coaches');
    }

    private function get(string $endpoint): array
    {
        try {
            $response = $this->client()->get($endpoint);

            if ($response->failed()) {
                Log::warning('MCP request failed', [
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return [];
            }

            return $response->json() ?? [];
        } catch (\Exception $e) {
            Log::error('MCP request error', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    public function isConfigured(): bool
    {
        return !empty($this->baseUrl) && !empty($this->apiKey);
    }
}
