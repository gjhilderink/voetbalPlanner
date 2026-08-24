<?php

declare(strict_types=1);

namespace App\Imports\Concerns;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Celwaarden uit Excel omzetten naar bruikbare types. Gedeeld door de leden- en
 * wedstrijdimport, omdat gebruikers in beide bestanden dezelfde vrijheden nemen
 * met datums, tijden en ja/nee-kolommen.
 */
trait ParsesCells
{
    /**
     * Accepteert Excel-serienummers, d-m-Y, Y-m-d, d/m/Y en losse datumteksten.
     * Carbon gooit bij een mismatch een exception (afhankelijk van de versie),
     * vandaar per formaat een try/catch in plaats van een false-check.
     */
    public static function parseDate(string $value): ?Carbon
    {
        // Een als datum opgemaakte cel levert Excel aan als serienummer (27-4-2014
        // wordt 41756). PhpSpreadsheet rekent dat terug. Zonder deze stap sneuvelt
        // elke rij uit een bestand dat in Excel zelf is getypt, want geen van de
        // tekstformaten hieronder herkent zo'n getal.
        if (is_numeric($value)) {
            $serial = (float) $value;
            // Ondergrens 1 = 1-1-1900, bovengrens 2958465 = 31-12-9999.
            if ($serial >= 1 && $serial <= 2958465) {
                try {
                    return Carbon::instance(ExcelDate::excelToDateTimeObject($serial));
                } catch (\Throwable) {
                    return null;
                }
            }

            return null;
        }

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

        // Een als tijd opgemaakte cel komt binnen als deel van een etmaal
        // (0,75 = 18:00). Zonder deze stap leest de bardienstimport zo'n cel
        // als "geen tijd". Hele getallen zijn datums, geen tijden.
        if (is_numeric($value)) {
            $fraction = fmod((float) $value, 1.0);
            if ($fraction > 0) {
                $minutes = (int) round($fraction * 24 * 60);

                return sprintf('%02d:%02d:00', intdiv($minutes, 60) % 24, $minutes % 60);
            }
        }

        return null;
    }

    /** Alles wat een gebruiker als 'ja' bedoelt; de rest telt als nee. */
    public static function parseBool(string $value): bool
    {
        return in_array(mb_strtolower($value), ['ja', 'j', 'yes', 'y', '1', 'true', 'waar'], true);
    }
}
