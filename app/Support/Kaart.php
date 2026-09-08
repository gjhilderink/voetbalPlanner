<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AccessCode;
use App\Models\AgendaItem;
use Carbon\CarbonInterface;

/**
 * Wat er op een kaartje staat, los van de vorm waarin het uitkomt.
 *
 * De pdf, de Apple-pas en de Google-pas laten alle drie dezelfde dingen zien:
 * wie de kaart is, welke soort, wanneer en waar. Die regels staan hier één keer,
 * zodat een wijziging - een ander scheidingsteken in het label bijvoorbeeld -
 * niet in twee van de drie vormen wordt vergeten.
 */
class Kaart
{
    /** De kleur van een club die er zelf geen heeft ingesteld. */
    public const KLEUR_TERUGVAL = '#5BA12F';

    /**
     * De koper en de kaartsoort uit het label halen.
     *
     * De ticketshop zet ze samen als "Naam — Soort". Bij een code die de club
     * zelf heeft aangemaakt staat er iets anders, of niets; dan is het hele
     * label de naam en blijft de soort leeg.
     *
     * @return array{0: ?string, 1: ?string}
     */
    public static function houderEnSoort(?string $label, ?string $koper): array
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
    public static function volgnummer(AccessCode $code): ?string
    {
        $broers = $code->order?->accessCodes;

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
    public static function tijd(?AgendaItem $item): ?string
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
     * Een tijdstip zoals de telefoon het moet lezen, met de juiste tijdzone.
     *
     * De applicatie draait op UTC en de agenda bewaart de tijd zoals hij op de
     * klok van de club staat. Wie dat rechtstreeks als ISO-tijd doorgeeft
     * beweert dat de wedstrijd om 14:00 UTC begint, en dan zet een telefoon in
     * Nederland de kaart twee uur te laat op het toegangsscherm. Vandaar de
     * klokstand opnieuw lezen in de tijdzone waar de club staat.
     */
    public static function momentIso(?CarbonInterface $moment, string $tijdzone = 'Europe/Amsterdam'): ?string
    {
        if (! $moment) {
            return null;
        }

        return \Carbon\Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $moment->format('Y-m-d H:i:s'),
            $tijdzone,
        )->toIso8601String();
    }

    /** Een ingestelde kleur, of de terugval als hij onbruikbaar is. */
    public static function hex(?string $ruw, string $terugval = self::KLEUR_TERUGVAL): string
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
    public static function tekstOp(string $hex): string
    {
        [$r, $g, $b] = self::rgb($hex);

        // Gewogen helderheid: het oog ziet groen veel lichter dan blauw.
        $helderheid = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        return $helderheid > 0.6 ? '#14283D' : '#FFFFFF';
    }

    /**
     * Een hexkleur als drie getallen.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    public static function rgb(string $hex): array
    {
        $delen = sscanf($hex, '#%02x%02x%02x');

        return [(int) ($delen[0] ?? 0), (int) ($delen[1] ?? 0), (int) ($delen[2] ?? 0)];
    }

    /** Dezelfde kleur zoals Apple hem in een pas wil hebben. */
    public static function rgbCss(string $hex): string
    {
        [$r, $g, $b] = self::rgb($hex);

        return "rgb({$r},{$g},{$b})";
    }

    /**
     * Het clublogo op schijf, of niets.
     *
     * Zowel dompdf als de pasbouwer lezen het bestand zelf; staat het er niet,
     * dan valt de opmaak om over een ontbrekend plaatje.
     */
    public static function logoPad(?string $pad): ?string
    {
        if (! $pad) {
            return null;
        }

        $bestand = public_path('logos/' . basename($pad));

        return is_file($bestand) ? $bestand : null;
    }
}
