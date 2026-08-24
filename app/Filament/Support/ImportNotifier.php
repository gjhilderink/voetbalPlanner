<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\Notifications\Notification;

/**
 * Eén melding met de uitkomst van een Excel-import, gedeeld door de leden- en
 * wedstrijdimport. Zijn er regelfouten, dan blijft de melding staan met de
 * eerste vijf regels erin — zo weet je meteen welke rijen aandacht vragen.
 */
class ImportNotifier
{
    /** Aantal foutregels dat in de melding past voordat het onleesbaar wordt. */
    private const MAX_ERRORS = 5;

    /**
     * @param string        $label   Meervoud van wat er geïmporteerd is ('leden').
     * @param array<string> $errors
     * @param array<string> $notices Wél gelukt, maar het vermelden waard.
     */
    public static function report(
        int $imported,
        int $created,
        int $skipped,
        array $errors,
        string $label,
        array $notices = [],
    ): void {
        // Ook zónder fouten blijft de melding staan zodra er iets te vertellen is:
        // een teruggezet lid mag niet wegvallen in een groen vinkje.
        $regels = array_merge($errors, $notices);

        if ($regels) {
            $titel = $errors
                ? "{$imported} {$label} verwerkt, {$skipped} overgeslagen"
                : "{$imported} {$label} verwerkt";

            Notification::make()
                ->warning()
                ->title($titel)
                ->body(implode("\n", array_slice($regels, 0, self::MAX_ERRORS))
                    . (count($regels) > self::MAX_ERRORS ? "\n…en meer" : ''))
                ->persistent()
                ->send();

            return;
        }

        $body = "Bijgewerkt of toegevoegd: {$imported}";
        if ($created) {
            $body .= " (waarvan {$created} nieuw)";
        }
        if ($skipped) {
            $body .= " · Overgeslagen: {$skipped}";
        }

        Notification::make()
            ->success()
            ->title('Import geslaagd')
            ->body($body)
            ->send();
    }
}
