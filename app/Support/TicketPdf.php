<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AccessCode;
use App\Models\Order;
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

    /** De standaardkleur als de club er geen heeft ingesteld. */
    private const KLEUR_TERUGVAL = '#5BA12F';

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

        [$houder, $soort] = self::houderEnSoort($code->label, $order?->buyer_name);

        $kleur = self::hex($club?->primary_color, self::KLEUR_TERUGVAL);

        return [
            'kleur'      => $kleur,
            'bandTekst'  => self::tekstOp($kleur),
            'accent'     => self::hex($club?->secondary_color, $kleur),
            'clubNaam'   => $club?->name ?? config('app.name'),
            'logo'       => self::logo($club?->logo_path),

            'titel'    => $item?->title ?? 'Toegangsbewijs',
            'datum'    => $item?->starts_at?->translatedFormat('l j F Y'),
            'tijd'     => self::tijd($item),
            'locatie'  => $item?->location,

            'code' => $code->code,
            'qr'   => Qr::pngDataUri($code->code, 480),

            'houder'       => $houder,
            'soort'        => $soort,
            'bestelnummer' => $order?->order_number,
            'volgnummer'   => self::volgnummer($code, $order),
            'meermalig'    => $code->max_uses > 1 ? $code->max_uses : null,
        ];
    }

    /**
     * De koper en de kaartsoort uit het label halen.
     *
     * De ticketshop zet ze samen als "Naam — Soort". Bij een code die de club
     * zelf heeft aangemaakt staat er iets anders, of niets; dan is het hele
     * label de naam en blijft de soort leeg.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private static function houderEnSoort(?string $label, ?string $koper): array
    {
        $label = trim((string) $label);

        if ($label === '') {
            return [$koper, null];
        }

        // Op de laatste scheiding splitsen: een naam met een streepje erin
        // hoort niet halverwege te breken.
        $streep = mb_strrpos($label, ' — ');

        if ($streep === false) {
            return [$label, null];
        }

        return [
            trim(mb_substr($label, 0, $streep)),
            trim(mb_substr($label, $streep + 3)),
        ];
    }

    /** "Kaart 2 van 4", zodat een koper ziet dat hij ze allemaal heeft. */
    private static function volgnummer(AccessCode $code, ?Order $order): ?string
    {
        $broers = $order?->accessCodes;

        if (! $broers || $broers->count() < 2) {
            return null;
        }

        $op = $broers->sortBy('code')->values();
        $ik = $op->search(fn (AccessCode $c): bool => $c->id === $code->id);

        return $ik === false
            ? null
            : 'Kaart ' . ($ik + 1) . ' van ' . $op->count();
    }

    /** De aanvangstijd, en de eindtijd als die er is. Leeg bij een hele dag. */
    private static function tijd(?\App\Models\AgendaItem $item): ?string
    {
        if (! $item?->starts_at || $item->is_all_day) {
            return null;
        }

        $tijd = $item->starts_at->format('H:i');

        if ($item->ends_at && $item->ends_at->isSameDay($item->starts_at)) {
            $tijd .= ' – ' . $item->ends_at->format('H:i');
        }

        return $tijd;
    }

    /**
     * Het pad naar het clublogo op schijf, of niets.
     *
     * dompdf leest het bestand zelf; staat het er niet, dan valt de opmaak om
     * over een ontbrekend plaatje. Vandaar de controle vooraf.
     */
    private static function logo(?string $pad): ?string
    {
        if (! $pad) {
            return null;
        }

        $bestand = public_path('logos/' . basename($pad));

        return is_file($bestand) ? $bestand : null;
    }

    /** Een ingestelde kleur, of de terugval als hij onbruikbaar is. */
    private static function hex(?string $ruw, string $terugval): string
    {
        $kleur = trim((string) $ruw);

        if (preg_match('/^#[0-9a-f]{6}$/i', $kleur)) {
            return $kleur;
        }

        // Ook drie tekens accepteren: #F00 komt voor in handmatig ingevulde
        // instellingen.
        if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/i', $kleur, $m)) {
            return '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3];
        }

        return $terugval;
    }

    /**
     * Zwart of wit op de clubkleur.
     *
     * Een clubkleur is net zo vaak geel als donkerblauw. Witte letters op geel
     * zijn niet te lezen, dus de tekstkleur volgt de helderheid van de
     * achtergrond in plaats van altijd wit te zijn.
     */
    private static function tekstOp(string $hex): string
    {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x') ?: [0, 0, 0];

        // Gewogen helderheid: het oog ziet groen veel lichter dan blauw.
        $helderheid = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        return $helderheid > 0.6 ? '#14283D' : '#FFFFFF';
    }
}
