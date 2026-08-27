<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Anti-spam voor de openbare formulieren.
 *
 * Gedeeld en niet per formulier overgeschreven: een tweede kopie gaat een keer
 * uit de pas lopen met de eerste, en dan staat er een formulier open waarvan
 * niemand weet dat de controle er niet meer op zit.
 */
trait VerifiesRecaptcha
{
    protected function recaptchaIngeschakeld(): bool
    {
        return Setting::get('recaptcha_enabled', '0', null) === '1';
    }

    protected function recaptchaSleutel(): string
    {
        return (string) Setting::get('recaptcha_site_key', '', null);
    }

    /** Verifieert het token bij Google met de ingestelde secret key. */
    protected function recaptchaGoedgekeurd(Request $request): bool
    {
        $secret = (string) Setting::get('recaptcha_secret_key', '', null);
        $token  = (string) $request->input('g-recaptcha-response', '');

        if ($secret === '' || $token === '') {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(10)
                ->post('https://www.google.com/recaptcha/api/siteverify', [
                    'secret'   => $secret,
                    'response' => $token,
                    'remoteip' => $request->ip(),
                ])
                ->json();

            return ($response['success'] ?? false) === true;
        } catch (\Throwable $e) {
            // Google onbereikbaar telt als niet goedgekeurd. Liever een bezoeker
            // die het opnieuw moet proberen dan een open formulier.
            return false;
        }
    }
}
