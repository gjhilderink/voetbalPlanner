<?php

declare(strict_types=1);

namespace App\Imports\Concerns;

use Carbon\Carbon;

/**
 * Celwaarden uit Excel omzetten naar bruikbare types. Gedeeld door de leden- en
 * wedstrijdimport, omdat gebruikers in beide bestanden dezelfde vrijheden nemen
 * met datums, tijden en ja/nee-kolommen.
 */
trait ParsesCells
{
    /**
     * Accepteert d-m-Y, Y-m-d, d/m/Y en losse datumteksten. Carbon gooit bij een
     * mismatch een exception (afhankelijk van de versie), vandaar per formaat een
     * try/catch in plaats van een false-check.
     */
    public static function parseDate(string $value): ?Carbon
    {
        foreach (['d-m-Y', 'Y-m-d', 'd/m/Y'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
                if ($date instanceof Carbon) {
                    return $date;
                }
            } catch (\Throwable) {
                // Volgend formaat proberen.
            }
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Accepteert H:i en H:i:s, ook als Excel er een datum-tijd van maakt. */
    public static function parseTime(string $value): ?string
    {
        if (preg_match('/(\d{1,2}):(\d{2})/', $value, $m)) {
            return sprintf('%02d:%02d:00', (int) $m[1], (int) $m[2]);
        }

        return null;
    }

    /** Alles wat een gebruiker als 'ja' bedoelt; de rest telt als nee. */
    public static function parseBool(string $value): bool
    {
        return in_array(mb_strtolower($value), ['ja', 'j', 'yes', 'y', '1', 'true', 'waar'], true);
    }
}
