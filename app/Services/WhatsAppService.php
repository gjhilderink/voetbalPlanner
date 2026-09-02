<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private const DEFAULT_BRIDGE_URL = 'https://mcp.nubixhosting.nl/mcp/whatsapp/mcp';

    private ?string $clubId    = null;
    private bool    $enabled   = false;
    private string  $apiKey    = '';
    private string  $bridgeUrl = self::DEFAULT_BRIDGE_URL;
    private string  $sendTool  = 'send_message';
    private int     $requestId = 1;

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

        $result = $this->mcpRequest('tools/list', new \stdClass());

        if (!$result['success']) {
            return ['connected' => false, 'message' => $result['error']];
        }

        $tools = collect($result['data']['tools'] ?? []);

        $sendTool = self::kiesVerzendTool($tools);

        if ($sendTool && $this->clubId) {
            Setting::set('whatsapp_send_tool', $sendTool, 'whatsapp', false, $this->clubId);
            $this->sendTool = $sendTool;
        }

        return [
            'connected' => true,
            'message'   => 'Verbonden. Verzendtool: ' . ($sendTool ?? $this->sendTool)
                . '. Tools: ' . $tools->pluck('name')->join(', '),
            'tools'     => $tools->all(),
        ];
    }

    /**
     * Welke tool verstuurt een gewoon bericht?
     *
     * De eerste met 'send' in de naam volstond zolang er één was. De nieuwe
     * bridge biedt er twee: send_message en send_template. Op volgorde afgaan
     * levert dan een kans op send_template, en die wil een sjabloonnaam met
     * parameters - geen vrije tekst. Het bericht komt dan nooit aan, met een
     * fout die niets met de naam van de tool te maken lijkt te hebben.
     *
     * Sjablonen slaan we hier bewust over: deze klasse stuurt losse tekst.
     * Ondersteuning voor sjablonen is een eigen aanroep, geen andere naam.
     *
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $tools
     */
    private static function kiesVerzendTool(\Illuminate\Support\Collection $tools): ?string
    {
        $namen = $tools->pluck('name')->filter(fn ($n) => is_string($n))->values();

        return $namen->first(fn (string $n) => strtolower($n) === 'send_message')
            ?? $namen->first(fn (string $n) => str_contains(strtolower($n), 'send')
                && ! str_contains(strtolower($n), 'template'));
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

        return $this->mcpRequest('tools/call', [
            'name'      => $this->sendTool,
            'arguments' => [
                'to'      => $formatted,
                'message' => $message,
            ],
        ]);
    }

    /**
     * Returns a full diagnostic dump for the settings debug modal.
     */
    public function discoverTools(): array
    {
        $raw = $this->rawPost('tools/list', new \stdClass());
        return [
            'request_url'    => $this->bridgeUrl,
            'response_status'=> $raw['status'],
            'response_type'  => $raw['content_type'],
            'response_body'  => $raw['body'],
            'parsed'         => $raw['parsed'],
        ];
    }

    // -------------------------------------------------------------------------
    // Internal
    // -------------------------------------------------------------------------

    private function mcpRequest(string $method, array|object $params): array
    {
        $raw = $this->rawPost($method, $params);

        if ($raw['status'] === 0) {
            return ['success' => false, 'error' => $raw['body']];
        }

        if ($raw['status'] >= 400) {
            return ['success' => false, 'error' => $this->foutTekst($raw)];
        }

        $body = $raw['parsed'];

        if ($body === null) {
            return ['success' => false, 'error' => 'Leeg of onleesbaar antwoord: ' . $raw['body']];
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
    }

    /**
     * Wat er misging, in gewone taal, met het adres van de bridge erbij.
     *
     * De bridge stuurt de reden in zijn antwoord mee; die hoort iemand te lezen
     * in plaats van een statuscode met een lap json erachter. En het adres
     * erbij, want dat is bij zo'n fout de eerste vraag: welke verbinding
     * bedoelt hij? Wie net een nieuwe koppeling heeft gemaakt kan dan zien of
     * de portal nog naar de oude wijst.
     *
     * @param  array<string, mixed>  $raw
     */
    private function foutTekst(array $raw): string
    {
        $reden = is_array($raw['parsed'] ?? null)
            ? ($raw['parsed']['error'] ?? null)
            : null;

        if (is_array($reden)) {
            $reden = $reden['message'] ?? null;
        }

        if (! is_string($reden) || trim($reden) === '') {
            return 'HTTP ' . $raw['status'] . ': ' . $raw['body'];
        }

        return trim($reden) . ' — bridge: ' . $this->bridgeUrl;
    }

    private function rawPost(string $method, array|object $params): array
    {
        try {
            $payload = [
                'jsonrpc' => '2.0',
                'id'      => $this->requestId++,
                'method'  => $method,
                'params'  => $params,
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json, text/event-stream',
            ])->timeout(15)->post($this->bridgeUrl, $payload);

            $status      = $response->status();
            $contentType = $response->header('Content-Type') ?? '';
            $rawBody     = $response->body();

            Log::debug('WhatsApp bridge', compact('method', 'status', 'contentType', 'rawBody'));

            $parsed = str_contains($contentType, 'text/event-stream')
                ? $this->parseSse($rawBody)
                : json_decode($rawBody, true);

            return [
                'status'       => $status,
                'content_type' => $contentType,
                'body'         => $rawBody,
                'parsed'       => $parsed,
            ];

        } catch (\Throwable $e) {
            Log::error('WhatsApp bridge exception', ['method' => $method, 'error' => $e->getMessage()]);
            return ['status' => 0, 'content_type' => '', 'body' => $e->getMessage(), 'parsed' => null];
        }
    }

    private function parseSse(string $raw): ?array
    {
        foreach (explode("\n", $raw) as $line) {
            $line = trim($line);
            if (!str_starts_with($line, 'data: ')) continue;
            $data = substr($line, 6);
            if ($data === '' || $data === '[DONE]') continue;
            $decoded = json_decode($data, true);
            if ($decoded !== null) return $decoded;
        }
        return null;
    }

    private function formatPhone(string $phone): ?string
    {
        $clean = preg_replace('/[^\d]/', '', $phone);
        if (empty($clean)) return null;

        if (strlen($clean) >= 11 && str_starts_with($clean, '31')) return $clean;
        if (str_starts_with($clean, '0')) return '31' . substr($clean, 1);
        if (strlen($clean) === 9) return '31' . $clean;

        return $clean;
    }
}
