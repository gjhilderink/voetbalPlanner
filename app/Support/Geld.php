<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Bedragen in hele centen, en hoe je ze opschrijft.
 *
 * Centen en geen floats: een optelling van bedragen hoort geen halve cent te
 * verliezen. Dit is de enige plek waar de omrekening staat, zodat de portal,
 * de winkel en de mail hetzelfde laten zien.
 */
class Geld
{
    /** Centen naar een bedrag zoals je het in Nederland opschrijft: € 7,50. */
    public static function euro(int $centen): string
    {
        return '€ ' . number_format($centen / 100, 2, ',', '.');
    }

    /**
     * Een ingetypt bedrag naar hele centen.
     *
     * Accepteert komma én punt, met of zonder euroteken. Afronden op de cent en
     * niet afkappen: 7,505 hoort 751 te worden en geen 750.
     */
    public static function naarCenten(?string $ruw): int
    {
        $schoon = str_replace(',', '.', preg_replace('/[^0-9,.]/', '', (string) $ruw) ?? '');

        return (int) round(((float) $schoon) * 100);
    }
}
