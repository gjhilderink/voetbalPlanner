<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private const DEFAULT_BRIDGE_URL    = 'https://mcp.nubixhosting.nl/mcp/whatsapp/mcp';
    private const PROTOCOL_VERSION      = '2024-11-05';

    private ?string $clubId    = null;
    private bool    $enabled   = false;
    private string  $apiKey    = '';
    private string  $bridgeUrl = self::DEFAULT_BRIDGE_URL;
    private string  $sendTool  = 'send_message';
    private ?string $sessionId = null;

    public function __construct()
    {
        $this->bootSettings();
    }

    public function forClub(?string $clubId): static
    {
        $this->clubId    = $clubId;
        $this->sessionId = null; // reset session on club switch
        $this->bootSettings();
        return $this;
    }

    private function bootSettings(): void
    {
        $id = $this->clubId;
        $this->enabled   = filter_var(Setting::get('whatsapp_enabled', false, $id), FILTER_VALIDATE_BOOLEAN);
        $this->apiKey    = Setting::get('whatsapp_api_key', '', $id) ?? '';
        $this->bridgeUrl = Setting::get('whatsapp_bridge_url', self::DEFAULT_BRIDGE_URL, $id) ?: self::DEFAULT_BRIDGE_URL;
        $this->sendTool  = Setting::get('whatsapp_send_tool', 'send_message', $id) ?? 'send_message';
    }

    public function isConfigured(): bool
    {
        return $this->enabled && !empty($this->apiKey);
    }

    public function healthCheck(): array
    {
        if (!$this->isConfigured()) {
            return ['connected' => false, 'message' => 'WhatsApp niet geconfigureerd'];
        }

        $result = $this->mcpRequest('tools/list', []);

        if (!$result['success']) {
            return ['connected' => false, 'message' => $result['error']];
        }

        $tools = collect($result['data']['tools'] ?? []);

        $sendTool = $tools->first(fn($t) => str_contains(strtolower($t['name'] ?? ''), 'send'));
        if ($sendTool && $this->clubId) {
            Setting::set('whatsapp_send_tool', $sendTool['name'], 'whatsapp', false, $this->clubId);
            $this->sendTool = $sendTool['name'];
        }

        $toolList = $tools->pluck('name')->join(', ');
        return [
            'connected' => true,
            'message'   => "Verbonden. Beschikbare tools: {$toolList}",
            'tools'     => $tools->all(),
        ];
    }

    public function sendMessage(string $phone, string $message): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'WhatsApp niet geconfigureerd'];
        }

        $formatted = $this->formatPhone($phone);
        if (!$formatted) {
            return ['success' => false, 'error' => 'Ongeldig telefoonnummer: ' . $phone];
        }

        return $this->callTool($this->sendTool, [
            'phone'   => $formatted,
            'message' => $message,
        ]);
    }

    public function discoverTools(): array
    {
        return $this->mcpRequest('tools/list', []);
    }

    // -------------------------------------------------------------------------
    // MCP session management
    // -------------------------------------------------------------------------

    private function ensureSession(): void
    {
        if ($this->sessionId) {
            return;
        }

        $response = Http::withHeaders($this->baseHeaders())
            ->timeout(15)
            ->post($this->bridgeUrl, [
                'jsonrpc' => '2.0',
                'method'  => 'initialize',
                'params'  => [
                    'protocolVersion' => self::PROTOCOL_VERSION,
                    'capabilities'    => new \stdClass(),
                    'clientInfo'      => ['name' => 'VoetbalPlanner', 'version' => '1.0'],
                ],
            ]);

        $sessionId = $response->header('Mcp-Session-Id');

        Log::debug('WhatsApp MCP initialize', [
            'status'     => $response->status(),
            'session_id' => $sessionId,
            'body'       => $response->body(),
        ]);

        if ($sessionId) {
            $this->sessionId = $sessionId;

            // Send the required initialized notification (no response expected)
            Http::withHeaders($this->baseHeaders() + ['Mcp-Session-Id' => $sessionId])
                ->timeout(5)
                ->post($this->bridgeUrl, [
                    'jsonrpc' => '2.0',
                    'method'  => 'notifications/initialized',
                ]);
        }
    }

    // -------------------------------------------------------------------------
    // Internal request helpers
    // -------------------------------------------------------------------------

    private function callTool(string $name, array $arguments): array
    {
        return $this->mcpRequest('tools/call', [
            'name'      => $name,
            'arguments' => $arguments,
        ]);
    }

    private function mcpRequest(string $method, array $params): array
    {
        try {
            $this->ensureSession();

            $headers = $this->baseHeaders();
            if ($this->sessionId) {
                $headers['Mcp-Session-Id'] = $this->sessionId;
            }

            $response = Http::withHeaders($headers)
                ->timeout(15)
                ->post($this->bridgeUrl, [
                    'jsonrpc' => '2.0',
                    'method'  => $method,
                    'params'  => empty($params) ? new \stdClass() : $params,
                ]);

            $rawBody     = $response->body();
            $contentType = $response->header('Content-Type') ?? '';

            Log::debug('WhatsApp MCP response', [
                'method'       => $method,
                'status'       => $response->status(),
                'content_type' => $contentType,
                'body'         => $rawBody,
            ]);

            if (!$response->successful()) {
                return ['success' => false, 'error' => 'HTTP ' . $response->status() . ': ' . $rawBody];
            }

            $body = str_contains($contentType, 'text/event-stream')
                ? $this->parseSseResponse($rawBody)
                : json_decode($rawBody, true);

            if ($body === null) {
                return ['success' => false, 'error' => 'Leeg of onleesbaar antwoord: ' . $rawBody];
            }

            if (isset($body['error'])) {
                $msg = $body['error']['message'] ?? json_encode($body['error']);
                return ['success' => false, 'error' => $msg];
            }

            $result = $body['result'] ?? $body;

            if (!empty($result['isError'])) {
                $text = collect($result['content'] ?? [])->where('type', 'text')->first()['text'] ?? json_encode($result);
                return ['success' => false, 'error' => $text];
            }

            return ['success' => true, 'data' => $result ?: null];

        } catch (\Throwable $e) {
            Log::error('WhatsApp MCP request failed', ['method' => $method, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function parseSseResponse(string $raw): ?array
    {
        foreach (explode("\n", $raw) as $line) {
            $line = trim($line);
            if (!str_starts_with($line, 'data: ')) {
                continue;
            }
            $data = substr($line, 6);
            if ($data === '' || $data === '[DONE]') {
                continue;
            }
            $decoded = json_decode($data, true);
            if ($decoded !== null) {
                return $decoded;
            }
        }
        return null;
    }

    private function baseHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json, text/event-stream',
        ];
    }

    private function formatPhone(string $phone): ?string
    {
        $clean = preg_replace('/[^\d]/', '', $phone);

        if (empty($clean)) {
            return null;
        }

        if (strlen($clean) >= 11 && str_starts_with($clean, '31')) {
            return $clean;
        }

        if (str_starts_with($clean, '0')) {
            return '31' . substr($clean, 1);
        }

        if (strlen($clean) === 9) {
            return '31' . $clean;
        }

        return $clean;
    }
}
