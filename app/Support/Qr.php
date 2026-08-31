<?php

declare(strict_types=1);

namespace App\Support;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

/**
 * QR-plaatjes maken. Eén plek, drie gebruikers: de modal in de portal, de losse
 * download en het afdrukvel.
 *
 * PNG en geen SVG: het plaatje moet zowel in de browser als in dompdf werken,
 * en de SVG-ondersteuning van dompdf is te wisselend om een heel afdrukvel op
 * te bouwen. PNG via GD is saai en werkt overal.
 *
 * De aanroep gebruikt de constructor plus setters in plaats van de fluent
 * Builder: die vorm werkt in zowel versie 4 als 5 van endroid/qr-code, en
 * QrCode::create() is in 5 verdwenen.
 */
class Qr
{
    /**
     * Een QR als data-URI, direct bruikbaar in een <img src="...">.
     *
     * @param string $tekst   Wat er in de code komt te staan.
     * @param int    $grootte Zijde in pixels.
     */
    public static function pngDataUri(string $tekst, int $grootte = 320): string
    {
        return self::maak($tekst, $grootte)->getDataUri();
    }

    /** De ruwe PNG-bytes, voor een download. */
    public static function pngBytes(string $tekst, int $grootte = 640): string
    {
        return self::maak($tekst, $grootte)->getString();
    }

    private static function maak(string $tekst, int $grootte): \Endroid\QrCode\Writer\Result\ResultInterface
    {
        $qr = new QrCode($tekst);
        $qr->setSize($grootte);
        // Marge rondom: zonder witrand lezen veel scanners een code niet die
        // tegen een rand of een andere code aan staat. Op een vel met tachtig
        // codes is dat het verschil tussen werkt en werkt niet.
        $qr->setMargin(12);

        return (new PngWriter())->write($qr);
    }
}
