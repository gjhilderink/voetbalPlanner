<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Een ingetypte tijd naar 'HH:MM'.
 *
 * De app zet bij een tijdveld een cijfertoetsenbord, en daar zit geen dubbele
 * punt op - een tijd was daarmee niet in te tikken. Het toetsenbord vervangen
 * door een volledig toetsenbord zou dat oplossen, maar dan tik je vier cijfers
 * op een bord dat voor woorden is gemaakt.
 *
 * Dus accepteren we allebei: 1345 en 13:45 leveren hetzelfde op, net als 945 en
 * 9:45. Eén plek waar dat gebeurt, zodat het overal gelijk is.
 */
class Tijd
{
    /**
     * Normaliseert naar 'HH:MM', of null als het geen tijd is.
     *
     * Drie cijfers worden gelezen als H:MM (945 = 09:45) en vier als HH:MM.
     * Twee of minder is te weinig om te raden: 45 kan kwart voor of vijfenveertig
     * minuten na betekenen, en gokken is hier erger dan weigeren.
     */
    public static function normaliseer(?string $ruw): ?string
    {
        $ruw = trim((string) $ruw);

        if ($ruw === '') {
            return null;
        }

        // Met scheidingsteken: 13:45, 13.45, 13u45.
        if (preg_match('/^(\d{1,2})[^0-9](\d{2})$/', $ruw, $m)) {
            return self::samen((int) $m[1], (int) $m[2]);
        }

        // Zonder: 1345 of 945.
        if (preg_match('/^(\d{3,4})$/', $ruw)) {
            $uur    = (int) substr($ruw, 0, strlen($ruw) - 2);
            $minuut = (int) substr($ruw, -2);

            return self::samen($uur, $minuut);
        }

        return null;
    }

    private static function samen(int $uur, int $minuut): ?string
    {
        if ($uur > 23 || $minuut > 59) {
            return null;
        }

        return sprintf('%02d:%02d', $uur, $minuut);
    }
}
