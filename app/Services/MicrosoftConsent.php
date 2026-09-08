<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Crypt;

/**
 * Een club aan Microsoft 365 verbinden door in te loggen.
 *
 * De club maakt geen eigen app-registratie meer en typt geen ID's over. Een
 * beheerder van de club logt in bij Microsoft, ziet welke rechten VoetbalPlanner
 * vraagt, en geeft toestemming voor zijn hele organisatie. Microsoft stuurt ons
 * daarna het nummer van die organisatie terug - dat is het enige wat we
 * bewaren.
 *
 * Beheerderstoestemming en geen gewone inlog: de koppeling moet 's nachts
 * doorlopen als er niemand ingelogd is (SyncRooms), en dat kan alleen met
 * applicatierechten. Een gewone inlog geeft een token dat aan één persoon hangt
 * en dat vervalt zodra die persoon van de club af is.
 */
class MicrosoftConsent
{
    /** Zoveel tijd heeft iemand om het inloggen bij Microsoft af te ronden. */
    private const STATE_MINUTEN = 20;

    /** Is er een gedeelde app-registratie ingesteld om mee te verbinden? */
    public static function beschikbaar(): bool
    {
        return trim((string) config('services.microsoft_graph.client_id', '')) !== ''
            && trim((string) config('services.microsoft_graph.client_secret', '')) !== '';
    }

    /**
     * Waar Microsoft de beheerder na afloop naartoe stuurt.
     *
     * Bij voorkeur uit de instellingen, want dit adres moet letterlijk gelijk
     * zijn aan wat er in de app-registratie staat en de portal is op meer dan
     * één adres bereikbaar.
     */
    public static function redirectUri(): string
    {
        $ingesteld = trim((string) config('services.microsoft_graph.redirect', ''));

        return $ingesteld !== '' ? $ingesteld : route('microsoft.verbonden');
    }

    /**
     * Het adres waar de beheerder van de club naartoe gaat.
     *
     * /organizations en niet een vast tenant-nummer: welke organisatie het is
     * weten we juist nog niet - dat is wat deze stap ons vertelt.
     */
    public static function url(?string $clubId, string $terugUrl): string
    {
        $vraag = http_build_query([
            'client_id'    => trim((string) config('services.microsoft_graph.client_id', '')),
            'scope'        => 'https://graph.microsoft.com/.default',
            'redirect_uri' => self::redirectUri(),
            'state'        => self::maakState($clubId, $terugUrl),
        ]);

        return 'https://login.microsoftonline.com/organizations/v2.0/adminconsent?' . $vraag;
    }

    /**
     * De state die mee heen en terug reist.
     *
     * Versleuteld en met een houdbaarheidsdatum, want dit is wat bepaalt welke
     * club er gekoppeld wordt. Zou het een leesbaar clubnummer zijn, dan kon
     * iemand met een eigen tenant zich aan de club van een ander hangen.
     */
    public static function maakState(?string $clubId, string $terugUrl): string
    {
        return Crypt::encryptString(json_encode([
            'club' => $clubId,
            'terug' => $terugUrl,
            'tot'  => now()->addMinutes(self::STATE_MINUTEN)->timestamp,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * De state terug uitpakken, of niets als hij niet klopt.
     *
     * @return array{club: ?string, terug: string}|null
     */
    public static function leesState(?string $state): ?array
    {
        if (! is_string($state) || $state === '') {
            return null;
        }

        try {
            $inhoud = json_decode(Crypt::decryptString($state), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        if (! is_array($inhoud) || (int) ($inhoud['tot'] ?? 0) < now()->timestamp) {
            return null;
        }

        $terug = (string) ($inhoud['terug'] ?? '');

        // Alleen terug naar onze eigen portal. Een adres dat van buiten komt is
        // een open doorverwijzing, ook al hebben we hem zelf versleuteld.
        if (! str_starts_with($terug, url('/'))) {
            $terug = url('/');
        }

        return [
            'club'  => isset($inhoud['club']) ? (string) $inhoud['club'] : null,
            'terug' => $terug,
        ];
    }
}
