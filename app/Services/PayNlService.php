<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Betalen via Pay.nl.
 *
 * Elke club heeft zijn eigen account: het geld gaat rechtstreeks naar de club en
 * VoetbalPlanner zit er niet tussen. De sleutels staan daarom clubgeschaald in
 * de settings-tabel, net als de MCP- en WhatsApp-koppeling, en niet globaal
 * zoals de SMTP-instellingen.
 *
 * Dit is de enige plek in de applicatie die iets van Pay.nl af weet. Verandert
 * er iets aan hun API, dan verandert er hier iets en verder nergens.
 *
 * Fouten komen als array terug en niet als uitzondering - hetzelfde als
 * SportlinkMcpService en WhatsAppService. Een betaalprovider die er even uit
 * ligt hoort een nette melding op te leveren, geen witte pagina.
 */
class PayNlService
{
    /** De REST-API van Pay.nl. Basic auth met de AT-code en het token. */
    private const BASIS_URL = 'https://rest.pay.nl/v2';

    /**
     * Onszelf bij naam noemen. De standaardnaam van Guzzle wordt aan de rand
     * van sommige netwerken tegengehouden, en dat ziet er precies zo uit als
     * een geweigerde sleutel: een 403 zonder inhoud.
     */
    private const USER_AGENT = 'VoetbalPlanner (+https://voetbalplanner.nl)';

    private ?string $clubId    = null;
    private string  $serviceId = '';
    private string  $tokenCode = '';
    private string  $apiToken  = '';
    private bool    $testModus = true;

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

        // Trimmen, want deze drie worden geplakt uit het Pay.nl-account. Een
        // meegekomen spatie of regeleinde levert een 403 op waar niets aan te
        // zien is: de sleutel lijkt goed en werkt toch niet.
        $this->serviceId = trim((string) (Setting::get('paynl_service_id', '', $id) ?? ''));
        $this->tokenCode = trim((string) (Setting::get('paynl_token_code', '', $id) ?? ''));
        $this->apiToken  = trim((string) (Setting::get('paynl_api_token', '', $id) ?? ''));
        $this->testModus = filter_var(
            Setting::get('paynl_test_mode', true, $id),
            FILTER_VALIDATE_BOOLEAN,
        );
    }

    public function isConfigured(): bool
    {
        return $this->serviceId !== '' && $this->tokenCode !== '' && $this->apiToken !== '';
    }

    public function isTestModus(): bool
    {
        return $this->testModus;
    }

    /**
     * Start een betaling en geef de URL terug waar de koper naartoe moet.
     *
     * @return array{ok: bool, paymentUrl?: string, transactionId?: string, error?: string}
     */
    public function start(Order $order, string $returnUrl, string $exchangeUrl): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'error' => 'De betaalkoppeling is nog niet ingesteld.'];
        }

        // In reference laat Pay.nl alleen letters en cijfers toe. Ons
        // bestelnummer heeft een streepje (VP-ABC123) en daar loopt de hele
        // transactie op stuk, met een validatiefout en geen betaalpagina.
        $referentie = preg_replace('/[^A-Za-z0-9]/', '', $order->order_number) ?? '';

        $body = [
            'serviceId'   => $this->serviceId,
            'amount'      => [
                'value'    => $order->total_cents,
                'currency' => 'EUR',
            ],
            'returnUrl'   => $returnUrl,
            'exchangeUrl' => $exchangeUrl,
            'reference'   => $referentie,
            // Hooguit tweeëndertig tekens; dit past ruim.
            'description' => 'Kaarten ' . $order->order_number,
            'customer'    => [
                'email' => $order->buyer_email,
            ],
            // Testmodus hoort in het integration-object. Bovenin doet hij
            // niets, en dan rekent Pay.nl een echte betaling af terwijl de
            // instelling zegt dat je aan het testen bent.
            'integration' => [
                'testMode' => $this->testModus,
            ],
        ];

        try {
            $antwoord = Http::withBasicAuth($this->tokenCode, $this->apiToken)
                ->withUserAgent(self::USER_AGENT)
                ->acceptJson()
                ->timeout(20)
                // Twee pogingen, en niet werpen bij een 4xx: een afwijzing van
                // Pay.nl is een antwoord dat we willen lezen, geen uitzondering.
                ->retry(2, 500, null, false)
                ->post(self::BASIS_URL . '/transactions', $body);

            $data = $antwoord->json() ?? [];

            if (! $antwoord->successful()) {
                // Ook de ruwe tekst, want een 403 van de rand van hun netwerk
                // heeft een leeg json-antwoord en dan staat er alleen [] in de
                // log. En erbij wat we meestuurden, gemaskeerd, zodat te zien
                // is of de sleutels overkomen zoals bedoeld.
                Log::error('[Pay.nl] transactie starten mislukt', [
                    'order'      => $order->order_number,
                    'status'     => $antwoord->status(),
                    'body'       => self::kortVoorLog($data),
                    'ruw'        => mb_substr($antwoord->body(), 0, 500),
                    'verstuurd'  => $this->sleutelsVoorLog(),
                ]);

                return [
                    'ok'    => false,
                    'error' => 'De betaling kon niet worden gestart.'
                        . $this->uitleg($data, $antwoord->status()),
                ];
            }

            $url = $data['paymentUrl']
                ?? ($data['links']['redirect'] ?? null)
                ?? ($data['transaction']['paymentUrl'] ?? null);
            $id  = $data['id'] ?? ($data['orderId'] ?? null);

            if (! $url || ! $id) {
                Log::error('[Pay.nl] antwoord zonder betaal-URL of transactie-id', [
                    'order' => $order->order_number,
                    'body'  => self::kortVoorLog($data),
                ]);

                return ['ok' => false, 'error' => 'Onverwacht antwoord van de betaaldienst.'];
            }

            Log::debug('[Pay.nl] transactie gestart', [
                'order'       => $order->order_number,
                'transaction' => $id,
            ]);

            return ['ok' => true, 'paymentUrl' => (string) $url, 'transactionId' => (string) $id];
        } catch (\Throwable $e) {
            Log::error('[Pay.nl] transactie starten gooide een fout', [
                'order' => $order->order_number,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'error' => 'De betaaldienst is even niet bereikbaar.'];
        }
    }

    /**
     * Vraag de stand van een transactie op.
     *
     * Altijd ophalen en nooit afgaan op wat er in de terugkeer-URL of de
     * webhook staat: dat is door de bezoeker te verzinnen.
     *
     * @return array{ok: bool, betaald?: bool, mislukt?: bool, ruw?: array, error?: string}
     */
    public function status(string $transactionId): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'error' => 'De betaalkoppeling is nog niet ingesteld.'];
        }

        try {
            $antwoord = Http::withBasicAuth($this->tokenCode, $this->apiToken)
                ->withUserAgent(self::USER_AGENT)
                ->acceptJson()
                ->timeout(20)
                ->retry(2, 500, null, false)
                ->get(self::BASIS_URL . '/transactions/' . urlencode($transactionId) . '/status');

            if (! $antwoord->successful()) {
                Log::error('[Pay.nl] status opvragen mislukt', [
                    'transaction' => $transactionId,
                    'status'      => $antwoord->status(),
                    'ruw'         => mb_substr($antwoord->body(), 0, 500),
                    'verstuurd'   => $this->sleutelsVoorLog(),
                ]);

                return ['ok' => false, 'error' => 'Kon de betaalstatus niet opvragen.'];
            }

            $data = $antwoord->json() ?? [];
            $code = (string) ($data['status']['code'] ?? ($data['statusCode'] ?? ''));
            $naam = strtoupper((string) ($data['status']['action'] ?? ($data['statusName'] ?? '')));

            // Pay.nl kent 100 als betaald; de overige eindtoestanden zijn
            // afgebroken, geweigerd of verlopen. Beide vormen worden gelezen,
            // want de code en de naam komen niet in elk antwoord allebei mee.
            $betaald = $code === '100' || in_array($naam, ['PAID', 'PAID_CHECKAMOUNT'], true);
            $mislukt = in_array($code, ['-90', '-80', '-72', '-71', '-70', '-63', '-60'], true)
                || in_array($naam, ['CANCEL', 'EXPIRED', 'DENIED', 'FAILURE', 'CHARGEBACK', 'REFUND'], true);

            return ['ok' => true, 'betaald' => $betaald, 'mislukt' => $mislukt, 'ruw' => $data];
        } catch (\Throwable $e) {
            Log::error('[Pay.nl] status opvragen gooide een fout', [
                'transaction' => $transactionId,
                'error'       => $e->getMessage(),
            ]);

            return ['ok' => false, 'error' => 'De betaaldienst is even niet bereikbaar.'];
        }
    }

    /**
     * De sleutels zoals ze de deur uitgaan, veilig genoeg voor een logregel.
     *
     * Het service-ID en de tokencode zijn geen geheim - ze staan zichtbaar in
     * de instellingen. Van het token gaat alleen de lengte mee: daaraan zie je
     * of het compleet is overgekomen, zonder het op te schrijven.
     *
     * @return array<string, mixed>
     */
    private function sleutelsVoorLog(): array
    {
        return [
            'serviceId'   => $this->serviceId,
            'tokenCode'   => $this->tokenCode,
            'tokenLengte' => strlen($this->apiToken),
            'testModus'   => $this->testModus,
        ];
    }

    /**
     * Wat Pay.nl zelf van de afwijzing zei, maar alleen in testmodus.
     *
     * Tijdens het inregelen is "er ging iets mis" nutteloos: je wilt weten dát
     * het bijvoorbeeld over het service-ID gaat. Zodra de club echt verkoopt
     * staat testmodus uit en zien kopers alleen de nette zin.
     *
     * @param  array<mixed>  $data
     */
    private function uitleg(array $data, int $status): string
    {
        if (! $this->testModus) {
            return '';
        }

        // Een validatiefout zegt in violations welk veld niet deugt. Dat is
        // precies wat je wilt lezen, dus die gaat voor op de algemene titel.
        if (is_array($data['violations'] ?? null)) {
            $regels = [];

            foreach ($data['violations'] as $schending) {
                if (! is_array($schending)) {
                    continue;
                }

                $veld    = (string) ($schending['propertyPath'] ?? '');
                $melding = (string) ($schending['message'] ?? '');
                $regel   = trim($veld === '' ? $melding : $veld . ' - ' . $melding);

                if ($regel !== '') {
                    $regels[] = $regel;
                }
            }

            if ($regels !== []) {
                return ' Pay.nl zei: ' . mb_substr(implode('; ', $regels), 0, 200);
            }
        }

        $tekst = $data['detail']
            ?? ($data['title'] ?? ($data['message'] ?? ($data['error'] ?? null)));

        if (! is_string($tekst) || trim($tekst) === '') {
            // Een 403 komt zonder inhoud binnen. Dan is de code zelf het enige
            // aanknopingspunt, en die hoort iemand die zit in te regelen te
            // zien in plaats van "er ging iets mis".
            return ' Pay.nl antwoordde met HTTP ' . $status . '.'
                . ($status === 401 || $status === 403
                    ? ' Controleer het service-ID, de tokencode en het API-token.'
                    : '');
        }

        return ' Pay.nl zei: ' . trim(mb_substr($tekst, 0, 200));
    }

    /**
     * Lange waarden inkorten voordat ze in de log belanden.
     *
     * Overgenomen van SportlinkMcpService, waar base64-foto's ooit logbestanden
     * van megabytes opleverden.
     *
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    private static function kortVoorLog(array $data): array
    {
        return array_map(function ($waarde) {
            if (is_array($waarde)) {
                return self::kortVoorLog($waarde);
            }

            return is_string($waarde) && strlen($waarde) > 300
                ? substr($waarde, 0, 300) . '…'
                : $waarde;
        }, $data);
    }
}
