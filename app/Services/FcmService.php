<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Verstuurt FCM push-notificaties naar een topic via de FCM HTTP v1 API.
 *
 * Zelfstandig (alleen guzzle + openssl, geen extra composer-dependency): mint
 * zelf een OAuth-token uit de Firebase service-account (JWT/RS256) en cachet dat.
 *
 * Vereist een service-account JSON; pad in config('services.fcm.credentials')
 * (env FCM_CREDENTIALS). Zonder credential faalt alles stil (log-warning), zodat
 * aanroepers (bv. een koppelverzoek) nooit breken.
 *
 * De client abonneert per gebruiker op topic `user_<sanitize(email)>` (zie de
 * Dart-action subscribeToChatTopics + firebase-chat-functions/index.js). Houd
 * sanitizeTopicEmail() daarmee identiek.
 */
class FcmService
{
    private const TOKEN_CACHE_KEY = 'fcm_access_token';

    /**
     * Push naar een FCM-topic. Returnt true bij succes.
     */
    public function sendToTopic(string $topic, string $title, string $body, array $data = []): bool
    {
        $sa = $this->serviceAccount();
        if ($sa === null) {
            Log::warning('[FCM] geen service-account geconfigureerd; push overgeslagen.', ['topic' => $topic]);
            return false;
        }

        try {
            $accessToken = $this->accessToken($sa);
            if ($accessToken === null) {
                return false;
            }

            // FCM-eis: alle data-waarden moeten strings zijn.
            $stringData = [];
            foreach ($data as $key => $value) {
                $stringData[$key] = is_string($value) ? $value : (string) json_encode($value);
            }

            $response = Http::withToken($accessToken)
                ->post("https://fcm.googleapis.com/v1/projects/{$sa['project_id']}/messages:send", [
                    'message' => [
                        'topic'        => $topic,
                        'notification' => ['title' => $title, 'body' => $body],
                        'data'         => $stringData,
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('[FCM] push mislukt', [
                    'topic'  => $topic,
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return false;
            }

            // FCM accepteerde het bericht voor het topic. LET OP: dit zegt niets
            // over aflevering — FCM geeft ook 200 als niemand op het topic
            // geabonneerd is. Aflevering vereist een echte app-build (geen web-
            // testmode), notificatie-permissie én een toestel dat op het topic
            // geabonneerd is (subscribeToChatTopics draait op de Chats-/
            // Wedstrijden-pagina).
            Log::info('[FCM] push geaccepteerd door FCM', [
                'topic' => $topic,
                'name'  => $response->json('name'),
            ]);
            return true;
        } catch (\Throwable $e) {
            Log::warning('[FCM] push-exception', ['topic' => $topic, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Topicnaam-veilige e-mail. MOET identiek zijn aan sanitize() in de app
     * (subscribeToChatTopics) en de Cloud Function.
     */
    public static function sanitizeTopicEmail(string $email): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '_', strtolower($email));
    }

    /**
     * Laadt + valideert de service-account JSON. Null als niet geconfigureerd/ongeldig.
     *
     * @return array{client_email:string,private_key:string,project_id:string}|null
     */
    private function serviceAccount(): ?array
    {
        $path = config('services.fcm.credentials');
        if (! is_string($path) || $path === '') {
            return null;
        }
        // Relatief pad → t.o.v. de projectroot.
        if (! str_starts_with($path, '/') && ! preg_match('/^[A-Za-z]:/', $path)) {
            $path = base_path($path);
        }
        if (! is_file($path)) {
            return null;
        }

        $json = json_decode((string) file_get_contents($path), true);
        if (! is_array($json)
            || empty($json['client_email'])
            || empty($json['private_key'])
            || empty($json['project_id'])) {
            return null;
        }

        return $json;
    }

    /**
     * OAuth2 access-token voor FCM (gecachet ~55 min). Null bij falen.
     */
    private function accessToken(array $sa): ?string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $now = time();
        $b64url = static fn (string $bin): string => rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');

        $header  = $b64url((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claim   = $b64url((string) json_encode([
            'iss'   => $sa['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'iat'   => $now,
            'exp'   => $now + 3600,
        ]));
        $signingInput = $header . '.' . $claim;

        $signature = '';
        if (! openssl_sign($signingInput, $signature, $sa['private_key'], OPENSSL_ALGO_SHA256)) {
            Log::warning('[FCM] JWT ondertekenen mislukt.');
            return null;
        }
        $jwt = $signingInput . '.' . $b64url($signature);

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]);

        if (! $response->successful()) {
            Log::warning('[FCM] access-token ophalen mislukt', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return null;
        }

        $token = $response->json('access_token');
        $expiresIn = (int) ($response->json('expires_in') ?? 3600);
        if (! is_string($token) || $token === '') {
            return null;
        }

        Cache::put(self::TOKEN_CACHE_KEY, $token, max(60, $expiresIn - 300));
        return $token;
    }
}
