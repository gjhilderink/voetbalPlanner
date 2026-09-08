<?php

declare(strict_types=1);

namespace App\Support\Wallet;

use App\Models\AccessCode;
use App\Support\Kaart;
use Illuminate\Support\Facades\Log;
use ZipArchive;

/**
 * Een kaartje als pas voor Apple Wallet (.pkpass).
 *
 * Een pkpass is een zip met pass.json, de plaatjes, een manifest met van elk
 * bestand een SHA1, en een handtekening over dat manifest. De handtekening is
 * het hele punt: daarmee weet de telefoon dat deze kaart van deze club komt en
 * onderweg niet is aangepast. Vandaar het certificaat - zonder dat kan er geen
 * pas gemaakt worden, en dan blijft de knop weg.
 *
 * Alles wordt in het geheugen opgebouwd, op de handtekening na: openssl werkt
 * in php alleen met bestanden op schijf, dus daarvoor gaan het manifest en de
 * uitkomst kort naar de tijdelijke map en meteen weer weg.
 */
class ApplePass
{
    /** Is er een certificaat ingesteld dat ook echt bestaat? */
    public static function beschikbaar(): bool
    {
        $config = config('wallet.apple');

        return ! empty($config['certificate'])
            && ! empty($config['wwdr'])
            && ! empty($config['pass_type_id'])
            && ! empty($config['team_id'])
            && is_file((string) $config['certificate'])
            && is_file((string) $config['wwdr']);
    }

    /**
     * De pkpass van één kaart, of niets als het niet lukte.
     *
     * Geen uitzondering bij een fout: dit hangt aan een knop op de
     * bevestigingspagina, en een koper die zijn kaarten al heeft hoort geen
     * foutpagina te krijgen omdat een certificaat verlopen is. Het gaat wel het
     * logboek in, want dit is stuk en moet opgelost worden.
     */
    public static function bouw(AccessCode $code): ?string
    {
        if (! self::beschikbaar()) {
            return null;
        }

        try {
            return self::maakBundel($code);
        } catch (\Throwable $e) {
            Log::error('[Wallet] Apple-pas maken mislukt', [
                'code'  => $code->code,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** @throws \RuntimeException */
    private static function maakBundel(AccessCode $code): string
    {
        $bestanden = ['pass.json' => self::passJson($code)];

        foreach (self::plaatjes($code) as $naam => $inhoud) {
            $bestanden[$naam] = $inhoud;
        }

        $manifest = json_encode(
            array_map(fn (string $inhoud): string => sha1($inhoud), $bestanden),
            JSON_UNESCAPED_SLASHES,
        );

        $bestanden['manifest.json'] = (string) $manifest;
        $bestanden['signature']     = self::handtekening((string) $manifest);

        return self::zip($bestanden);
    }

    /**
     * De inhoud van de pas.
     *
     * eventTicket met een QR erop; de code in de QR is dezelfde als op de pdf
     * en in de mail, zodat het bij de ingang niet uitmaakt wat iemand laat zien.
     */
    private static function passJson(AccessCode $code): string
    {
        $club  = $code->club;
        $item  = $code->agendaItem;
        $order = $code->order;

        [$houder, $soort] = Kaart::houderEnSoort($code->label, $order?->buyer_name);

        $kleur = Kaart::hex($club?->primary_color);
        $tekst = Kaart::tekstOp($kleur);

        $wanneer = trim(
            ($item?->starts_at?->translatedFormat('D j M') ?? '')
            . ' ' . (Kaart::tijd($item) ?? '')
        );

        $pas = [
            'formatVersion'        => 1,
            'passTypeIdentifier'   => (string) config('wallet.apple.pass_type_id'),
            'teamIdentifier'       => (string) config('wallet.apple.team_id'),
            'serialNumber'         => $code->code,
            'organizationName'     => $club?->name ?? config('app.name'),
            'description'          => 'Toegangsbewijs ' . ($item?->title ?? ''),
            'logoText'             => $club?->name ?? config('app.name'),
            'backgroundColor'      => Kaart::rgbCss($kleur),
            'foregroundColor'      => Kaart::rgbCss($tekst),
            'labelColor'           => Kaart::rgbCss($tekst),
            'sharingProhibited'    => false,

            // Twee keer dezelfde streepjescode: het oude veld voor toestellen
            // die nog op iOS 8 staan, de lijst voor al het andere.
            'barcode'  => self::barcode($code),
            'barcodes' => [self::barcode($code)],

            'eventTicket' => [
                'primaryFields' => [[
                    'key'   => 'activiteit',
                    'label' => 'Activiteit',
                    'value' => $item?->title ?? 'Toegangsbewijs',
                ]],
                'secondaryFields' => array_values(array_filter([
                    $wanneer === '' ? null : [
                        'key'   => 'wanneer',
                        'label' => 'Wanneer',
                        'value' => $wanneer,
                    ],
                    $item?->location ? [
                        'key'   => 'waar',
                        'label' => 'Waar',
                        'value' => $item->location,
                    ] : null,
                ])),
                'auxiliaryFields' => array_values(array_filter([
                    $houder ? [
                        'key'   => 'houder',
                        'label' => 'Op naam van',
                        'value' => $houder,
                    ] : null,
                    $soort ? [
                        'key'   => 'soort',
                        'label' => 'Soort kaart',
                        'value' => $soort,
                    ] : null,
                ])),
                'backFields' => array_values(array_filter([
                    [
                        'key'   => 'code',
                        'label' => 'Code',
                        'value' => $code->code,
                    ],
                    $order?->order_number ? [
                        'key'   => 'bestelling',
                        'label' => 'Bestelnummer',
                        'value' => $order->order_number,
                    ] : null,
                    [
                        'key'   => 'ingang',
                        'label' => 'Bij de ingang',
                        'value' => $code->max_uses > 1
                            ? 'Deze kaart is ' . $code->max_uses . ' keer te gebruiken.'
                            : 'Geldig voor één persoon, één keer. Laat de code scannen.',
                    ],
                ])),
            ],
        ];

        // Waarmee de telefoon de kaart rond de aanvang op het toegangsscherm
        // zet. Zonder tijdzone zou dat twee uur te vroeg of te laat zijn.
        $moment = Kaart::momentIso($item?->starts_at);

        if ($moment !== null) {
            $pas['relevantDate'] = $moment;
        }

        return (string) json_encode($pas, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** @return array<string, string> */
    private static function barcode(AccessCode $code): array
    {
        return [
            'format'          => 'PKBarcodeFormatQR',
            'message'         => $code->code,
            // Wat Apple voorschrijft voor een QR met gewone letters en cijfers.
            'messageEncoding' => 'iso-8859-1',
            'altText'         => $code->code,
        ];
    }

    /**
     * Het icoon en het logo van de pas.
     *
     * icon.png is verplicht; zonder dat weigert de telefoon de pas. Er wordt
     * geen bestand van de club overgeslagen als het er niet is: dan komt er een
     * vlak in de clubkleur, wat nog altijd beter is dan geen pas.
     *
     * @return array<string, string>
     */
    private static function plaatjes(AccessCode $code): array
    {
        $logo   = Kaart::logoPad($code->club?->logo_path);
        $kleur  = Kaart::hex($code->club?->primary_color);
        $bron   = $logo ? @imagecreatefromstring((string) file_get_contents($logo)) : null;

        $plaatjes = [
            'icon.png'    => self::vierkant($bron ?: null, 29, $kleur),
            'icon@2x.png' => self::vierkant($bron ?: null, 58, $kleur),
        ];

        if ($bron) {
            // Het logo in de kop van de pas: hoogte vast, breedte in verhouding,
            // en niet breder dan Apple toont.
            $plaatjes['logo.png']    = self::geschaald($bron, 160, 50);
            $plaatjes['logo@2x.png'] = self::geschaald($bron, 320, 100);
            imagedestroy($bron);
        }

        return $plaatjes;
    }

    /** Een vierkant icoon: het logo passend erin, of een vlak in de clubkleur. */
    private static function vierkant(?\GdImage $bron, int $zijde, string $kleur): string
    {
        $doek = imagecreatetruecolor($zijde, $zijde);
        imagealphablending($doek, false);
        imagesavealpha($doek, true);

        [$r, $g, $b] = Kaart::rgb($kleur);
        imagefilledrectangle($doek, 0, 0, $zijde, $zijde, imagecolorallocate($doek, $r, $g, $b));
        imagealphablending($doek, true);

        if ($bron) {
            $bx = imagesx($bron);
            $by = imagesy($bron);
            $f  = min(($zijde - 4) / $bx, ($zijde - 4) / $by);
            $nx = (int) round($bx * $f);
            $ny = (int) round($by * $f);

            imagecopyresampled(
                $doek, $bron,
                (int) (($zijde - $nx) / 2), (int) (($zijde - $ny) / 2),
                0, 0,
                $nx, $ny, $bx, $by,
            );
        }

        return self::png($doek);
    }

    /** Het logo, passend binnen een maximale breedte en hoogte. */
    private static function geschaald(\GdImage $bron, int $maxBreed, int $maxHoog): string
    {
        $bx = imagesx($bron);
        $by = imagesy($bron);
        $f  = min($maxBreed / $bx, $maxHoog / $by, 1);

        $nx = max(1, (int) round($bx * $f));
        $ny = max(1, (int) round($by * $f));

        $doek = imagecreatetruecolor($nx, $ny);
        imagealphablending($doek, false);
        imagesavealpha($doek, true);
        imagefilledrectangle($doek, 0, 0, $nx, $ny, imagecolorallocatealpha($doek, 0, 0, 0, 127));
        imagealphablending($doek, true);
        imagecopyresampled($doek, $bron, 0, 0, 0, 0, $nx, $ny, $bx, $by);

        return self::png($doek);
    }

    private static function png(\GdImage $doek): string
    {
        ob_start();
        imagepng($doek);
        $bytes = (string) ob_get_clean();
        imagedestroy($doek);

        return $bytes;
    }

    /**
     * De handtekening over het manifest.
     *
     * openssl werkt in php met bestanden en niet met tekst, dus het manifest
     * gaat kort naar de tijdelijke map. De uitkomst is een s/mime-bericht met
     * kopregels eromheen; Apple wil alleen het ruwe deel eronder.
     *
     * @throws \RuntimeException
     */
    private static function handtekening(string $manifest): string
    {
        $config = config('wallet.apple');

        $p12 = (string) file_get_contents((string) $config['certificate']);
        $sleutels = [];

        if (! openssl_pkcs12_read($p12, $sleutels, (string) $config['password'])) {
            throw new \RuntimeException('Het pkpass-certificaat kon niet worden gelezen; klopt het wachtwoord?');
        }

        $in  = (string) tempnam(sys_get_temp_dir(), 'vp-manifest-');
        $uit = (string) tempnam(sys_get_temp_dir(), 'vp-signature-');

        try {
            file_put_contents($in, $manifest);

            $gelukt = openssl_pkcs7_sign(
                $in,
                $uit,
                $sleutels['cert'],
                [$sleutels['pkey'], ''],
                [],
                PKCS7_BINARY | PKCS7_DETACHED,
                (string) $config['wwdr'],
            );

            if (! $gelukt) {
                throw new \RuntimeException('Ondertekenen mislukte: ' . openssl_error_string());
            }

            return self::uitSmime((string) file_get_contents($uit));
        } finally {
            @unlink($in);
            @unlink($uit);
        }
    }

    /** Het ondertekende deel uit het s/mime-bericht, als ruwe bytes. */
    private static function uitSmime(string $smime): string
    {
        $start = strpos($smime, 'filename="smime.p7s"');

        if ($start === false) {
            throw new \RuntimeException('De handtekening zag er anders uit dan verwacht.');
        }

        $body = substr($smime, $start);
        $body = substr($body, (int) strpos($body, "\n\n") + 2);
        $einde = strpos($body, "\n\n");

        if ($einde !== false) {
            $body = substr($body, 0, $einde);
        }

        return (string) base64_decode(trim($body), true);
    }

    /**
     * De bestanden als zip, in het geheugen.
     *
     * @param  array<string, string>  $bestanden
     *
     * @throws \RuntimeException
     */
    private static function zip(array $bestanden): string
    {
        $pad = (string) tempnam(sys_get_temp_dir(), 'vp-pkpass-');

        try {
            $zip = new ZipArchive();

            if ($zip->open($pad, ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Kon geen pkpass-bestand aanmaken.');
            }

            foreach ($bestanden as $naam => $inhoud) {
                $zip->addFromString($naam, $inhoud);
            }

            $zip->close();

            return (string) file_get_contents($pad);
        } finally {
            @unlink($pad);
        }
    }
}
