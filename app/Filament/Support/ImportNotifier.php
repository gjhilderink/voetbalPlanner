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
     * @param string        $label Meervoud van wat er geïmporteerd is ('leden').
     * @param array<string> $errors
     */
    public static function report(int $imported, int $created, int $skipped, array $errors, string $label): void
    {
        if ($errors) {
            Notification::make()
                ->warning()
                ->title("{$imported} {$label} verwerkt, {$skipped} overgeslagen")
                ->body(implode("\n", array_slice($errors, 0, self::MAX_ERRORS))
                    . (count($errors) > self::MAX_ERRORS ? "\n…en meer" : ''))
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
