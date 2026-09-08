<?php

declare(strict_types=1);

namespace App\Support\Wallet;

use App\Models\AccessCode;
use App\Support\Kaart;
use Illuminate\Support\Facades\Log;

/**
 * Een kaartje als pas voor Google Wallet.
 *
 * Anders dan bij Apple wordt hier geen bestand gemaakt: Google wil een
 * ondertekend jwt in een link. De kaart zelf staat in dat jwt, zodat er geen
 * verzoek naar de Wallet-api hoeft - klikt de koper op de knop, dan maakt Google
 * de pas aan bij het opslaan. Dat scheelt een koppeling die kan uitvallen op het
 * moment dat iemand net zijn kaarten binnenkrijgt.
 */
class GooglePass
{
    /** Is er een sleutelbestand en een issuer-nummer ingesteld? */
    public static function beschikbaar(): bool
    {
        $config = config('wallet.google');

        return ! empty($config['key_file'])
            && ! empty($config['issuer_id'])
            && is_file((string) $config['key_file']);
    }

    /**
     * De link achter de knop "Toevoegen aan Google Wallet", of niets.
     *
     * Geen uitzondering bij een fout, om dezelfde reden als bij de Apple-pas:
     * de koper heeft zijn kaarten al, en die pagina hoort niet om te vallen over
     * een sleutel die niet klopt.
     */
    public static function bewaarUrl(AccessCode $code): ?string
    {
        if (! self::beschikbaar()) {
            return null;
        }

        try {
            return 'https://pay.google.com/gp/v/save/' . self::jwt($code);
        } catch (\Throwable $e) {
            Log::error('[Wallet] Google-pas maken mislukt', [
                'code'  => $code->code,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** @throws \RuntimeException */
    private static function jwt(AccessCode $code): string
    {
        $sleutel = self::sleutel();
        $issuer  = (string) config('wallet.google.issuer_id');

        $payload = [
            'iss' => $sleutel['client_email'],
            'aud' => 'google',
            'typ' => 'savetowallet',
            'iat' => time(),
            'payload' => [
                'eventTicketClasses' => [self::klasse($code, $issuer)],
                'eventTicketObjects' => [self::object($code, $issuer)],
            ],
        ];

        $kop  = self::base64url((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $body = self::base64url((string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $handtekening = '';

        if (! openssl_sign($kop . '.' . $body, $handtekening, $sleutel['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Ondertekenen mislukte: ' . openssl_error_string());
        }

        return $kop . '.' . $body . '.' . self::base64url($handtekening);
    }

    /**
     * De soort kaart: één per activiteit.
     *
     * Hier staat wat voor alle kaarten van die activiteit hetzelfde is - naam,
     * datum, locatie. Google maakt hem aan bij de eerste kaart die iemand
     * opslaat en herkent hem daarna aan zijn nummer.
     *
     * @return array<string, mixed>
     */
    private static function klasse(AccessCode $code, string $issuer): array
    {
        $club = $code->club;
        $item = $code->agendaItem;

        $klasse = [
            'id'           => $issuer . '.' . self::klasseNummer($code),
            'issuerName'   => $club?->name ?? (string) config('app.name'),
            // Zonder eigen goedkeuring bij Google mag een klasse alleen naar de
            // mensen die de link krijgen; dat is precies wat hier gebeurt.
            'reviewStatus' => 'UNDER_REVIEW',
            'eventName'    => self::tekst($item?->title ?? 'Toegangsbewijs'),
            'hexBackgroundColor' => Kaart::hex($club?->primary_color),
        ];

        if ($item?->location) {
            $klasse['venue'] = [
                'name'    => self::tekst($item->location),
                'address' => self::tekst($item->location),
            ];
        }

        $moment = Kaart::momentIso($item?->starts_at);

        if ($moment !== null) {
            $klasse['dateTime'] = ['start' => $moment];

            $eind = Kaart::momentIso($item?->ends_at);

            if ($eind !== null) {
                $klasse['dateTime']['end'] = $eind;
            }
        }

        return $klasse;
    }

    /**
     * De kaart zelf.
     *
     * @return array<string, mixed>
     */
    private static function object(AccessCode $code, string $issuer): array
    {
        [$houder, $soort] = Kaart::houderEnSoort($code->label, $code->order?->buyer_name);

        $object = [
            'id'      => $issuer . '.' . $code->code,
            'classId' => $issuer . '.' . self::klasseNummer($code),
            'state'   => 'ACTIVE',
            'barcode' => [
                'type'          => 'QR_CODE',
                'value'         => $code->code,
                'alternateText' => $code->code,
            ],
        ];

        if ($houder) {
            $object['ticketHolderName'] = $houder;
        }

        if ($soort) {
            $object['ticketType'] = self::tekst($soort);
        }

        if ($code->order?->order_number) {
            $object['ticketNumber'] = $code->order->order_number;
        }

        return $object;
    }

    /**
     * Het nummer van de klasse bij deze activiteit.
     *
     * Alleen letters, cijfers, punt, streepje en liggend streepje zijn
     * toegestaan; de streepjes van een uuid mogen dus blijven staan.
     */
    private static function klasseNummer(AccessCode $code): string
    {
        return 'activiteit-' . preg_replace('/[^A-Za-z0-9._-]/', '', (string) $code->agenda_item_id);
    }

    /** @return array<string, mixed> */
    private static function tekst(string $waarde): array
    {
        return ['defaultValue' => ['language' => 'nl', 'value' => $waarde]];
    }

    /**
     * Het service-account uit het sleutelbestand.
     *
     * @return array{client_email: string, private_key: string}
     *
     * @throws \RuntimeException
     */
    private static function sleutel(): array
    {
        $inhoud = json_decode(
            (string) file_get_contents((string) config('wallet.google.key_file')),
            true,
        );

        if (! is_array($inhoud) || empty($inhoud['client_email']) || empty($inhoud['private_key'])) {
            throw new \RuntimeException('Het sleutelbestand van Google Wallet mist client_email of private_key.');
        }

        return [
            'client_email' => (string) $inhoud['client_email'],
            'private_key'  => (string) $inhoud['private_key'],
        ];
    }

    private static function base64url(string $ruw): string
    {
        return rtrim(strtr(base64_encode($ruw), '+/', '-_'), '=');
    }
}
