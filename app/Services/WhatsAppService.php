<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private const BRIDGE_URL = 'https://mcp.nubixhosting.nl/mcp/whatsapp/mcp';

    private ?string $clubId   = null;
    private bool    $enabled  = false;
    private string  $apiKey   = '';
    private string  $sendTool = 'send_message';

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
        $id = $this->clubId;
        $this->enabled  = filter_var(Setting::get('whatsapp_enabled', false, $id), FILTER_VALIDATE_BOOLEAN);
        $this->apiKey   = Setting::get('whatsapp_api_key', '', $id) ?? '';
        $this->sendTool = Setting::get('whatsapp_send_tool', 'send_message', $id) ?? 'send_message';
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

        // Auto-detect the send tool
        $sendTool = $tools->first(fn($t) => str_contains(strtolower($t['name'] ?? ''), 'send'));
        $toolName = $sendTool['name'] ?? 'send_message';

        if ($this->clubId && $toolName !== $this->sendTool) {
            Setting::set('whatsapp_send_tool', $toolName, 'whatsapp', false, $this->clubId);
            $this->sendTool = $toolName;
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
            return ['success' => false, 'error' => 'Ongeldig telefoonnummer'];
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
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
            ])->timeout(15)->post(self::BRIDGE_URL, [
                'jsonrpc' => '2.0',
                'id'      => 1,
                'method'  => $method,
                'params'  => $params,
            ]);

            $body = $response->json();

            if (!$response->successful()) {
                return ['success' => false, 'error' => 'HTTP ' . $response->status() . ': ' . $response->body()];
            }

            if (isset($body['error'])) {
                $msg = $body['error']['message'] ?? json_encode($body['error']);
                return ['success' => false, 'error' => $msg];
            }

            return ['success' => true, 'data' => $body['result'] ?? $body];

        } catch (\Throwable $e) {
            Log::error('WhatsApp MCP request failed', ['method' => $method, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function formatPhone(string $phone): ?string
    {
        // Strip everything except digits and leading +
        $clean = preg_replace('/[^\d]/', '', $phone);

        if (empty($clean)) {
            return null;
        }

        // Already full international (e.g. 31612345678)
        if (strlen($clean) >= 11 && str_starts_with($clean, '31')) {
            return $clean;
        }

        // Dutch local (0612345678 → 31612345678)
        if (str_starts_with($clean, '0')) {
            return '31' . substr($clean, 1);
        }

        // Add NL country code if only 9 digits
        if (strlen($clean) === 9) {
            return '31' . $clean;
        }

        return $clean;
    }
}
