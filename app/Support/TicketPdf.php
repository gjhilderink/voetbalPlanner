<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AccessCode;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Kaarten als afdrukbare pdf: één kaart per A4, in de kleuren van de club.
 *
 * Eén plek voor drie gebruikers: de bijlagen bij de mail aan de koper (daar
 * krijgt elke kaart een eigen bestand, zodat je er één kunt doorsturen naar wie
 * meegaat), de bestelling in de portal, en het afdrukvel waarmee de club de
 * kaarten van een hele activiteit in één keer uitprint.
 *
 * Alles wat de opmaak nodig heeft wordt hier klaargezet en niet in de Blade.
 * De QR's zijn data-URI's: dompdf hoeft dan geen bestand van schijf te halen,
 * en het werkt hetzelfde op elke hosting.
 */
class TicketPdf
{
    /**
     * Zoveel kaarten passen er in één bestand.
     *
     * Elke pagina draagt een eigen QR-plaatje mee; bij een paar honderd
     * pagina's loopt dompdf tegen het geheugen van de php-worker aan en krijgt
     * de beheerder een lege pagina in plaats van een pdf. Liever een duidelijke
     * melding en in twee delen afdrukken.
     */
    public const MAX_KAARTEN = 250;

    /** Eén kaart op één A4. */
    public static function kaart(AccessCode $code): string
    {
        return self::kaarten(new Collection([$code]));
    }

    /**
     * Een stapel kaarten in één bestand: één A4 per kaart.
     *
     * @param  Collection<int, AccessCode>  $codes
     */
    public static function kaarten(Collection $codes): string
    {
        // In één keer bijladen, want anders haalt elke pagina zijn eigen club
        // en activiteit op. Wat al geladen is blijft staan.
        $codes->loadMissing(['club', 'agendaItem', 'order.accessCodes']);

        $paginas = $codes->map(fn (AccessCode $code): array => self::pagina($code))->all();

        return Pdf::loadView('pdf.ticket', ['kaarten' => $paginas])
            ->setPaper('a4', 'portrait')
            ->output();
    }

    /** De pdf als download terugsturen vanuit een portal-actie. */
    public static function download(string $pdf, string $bestandsnaam): StreamedResponse
    {
        return response()->streamDownload(
            fn () => print ($pdf),
            $bestandsnaam,
            ['Content-Type' => 'application/pdf'],
        );
    }

    /**
     * Alles wat er op één kaart komt te staan.
     *
     * @return array<string, mixed>
     */
    private static function pagina(AccessCode $code): array
    {
        $club  = $code->club;
        $item  = $code->agendaItem;
        $order = $code->order;

        [$houder, $soort] = Kaart::houderEnSoort($code->label, $order?->buyer_name);

        $kleur = Kaart::hex($club?->primary_color);

        return [
            'kleur'      => $kleur,
            'bandTekst'  => Kaart::tekstOp($kleur),
            'accent'     => Kaart::hex($club?->secondary_color, $kleur),
            'clubNaam'   => $club?->name ?? config('app.name'),
            'logo'       => Kaart::logoPad($club?->logo_path),

            'titel'    => $item?->title ?? 'Toegangsbewijs',
            'datum'    => $item?->starts_at?->translatedFormat('l j F Y'),
            'tijd'     => Kaart::tijd($item),
            'locatie'  => $item?->location,

            'code' => $code->code,
            'qr'   => Qr::pngDataUri($code->code, 480),

            'houder'       => $houder,
            'soort'        => $soort,
            'bestelnummer' => $order?->order_number,
            'volgnummer'   => Kaart::volgnummer($code),
            'meermalig'    => $code->max_uses > 1 ? $code->max_uses : null,
        ];
    }
}
