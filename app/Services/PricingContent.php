<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;

/**
 * De inhoud van de tarievenpagina.
 *
 * Bedragen en teksten staan in `settings` met club_id = null, zodat de
 * super-admin ze in de portal kan aanpassen zonder dat er iemand aan de code
 * hoeft te komen. De standaardwaarden hieronder zijn de tarieven zoals ze nu
 * gelden: staat er nog niets in de database, dan klopt de pagina toch.
 *
 * Eén bron voor zowel de publieke pagina als het beheerscherm. Zou de calculator
 * zijn eigen bedragen kennen, dan rekent hij vroeg of laat met andere getallen
 * dan er in de kaartjes ernaast staan — precies het soort fout dat niemand
 * opmerkt tot een club erover valt.
 */
class PricingContent
{
    /** De groep waarin deze instellingen vallen, voor herkenbaarheid in de tabel. */
    public const GROUP = 'pricing';

    /** @var array<string, string> */
    public const DEFAULTS = [
        'pricing_title' => 'Eén tarief, alles inbegrepen',

        'pricing_intro' => 'Geen pakketten en geen modules die u er los bij koopt. Elke club krijgt '
            . 'het hele platform: de portal, de app en alles wat daarin zit. U betaalt per clublid, '
            . 'en verder niets.',

        'pricing_per_member' => '3.50',
        'pricing_setup_fee'  => '250',
        'pricing_minimum'    => '595',

        'pricing_includes' => "Wedstrijden, uitslagen en het live wedstrijdverslag\n"
            . "Opstellingen maken op een digitaal veld\n"
            . "Bardiensten inplannen, toewijzen en onderling ruilen\n"
            . "Rijders, vlaggers en fruitouders per wedstrijd\n"
            . "Trainingsschema's met af- en aanmelden\n"
            . "Team- en groepschat met pushberichten\n"
            . "Nieuws, agenda en clubdocumenten\n"
            . "Ticketshop met iDEAL-betaling via uw eigen Pay.nl-account\n"
            . "Toegangscontrole met QR-codes bij de ingang\n"
            . "Koppeling van ouders en verzorgers aan hun kind\n"
            . "De app voor iOS en Android\n"
            . "Onbeperkt aantal teams, leden en beheerders\n"
            . "Updates en ondersteuning",

        'pricing_no_hidden_title' => 'Geen verborgen kosten',

        'pricing_no_hidden' => 'Geen aankopen in de app, geen kosten per bericht en geen toeslag voor '
            . 'extra teams of beheerders. Ouders en verzorgers gebruiken de app gratis: zij tellen niet '
            . 'mee in het aantal leden. Wat hier staat, is wat u betaalt.',

        'pricing_data_title' => 'Over de koppeling met Sportlink',

        'pricing_data_note' => 'De genoemde bedragen zijn exclusief de kosten van Sportlink of een '
            . 'andere dataservice; die rekent uw club rechtstreeks met die partij af. VoetbalPlanner '
            . 'werkt ook zonder zo\'n koppeling — u voert teams, leden en wedstrijden dan zelf in. Met '
            . 'een koppeling loopt het onderhoud een stuk prettiger: leden, teams en het '
            . 'wedstrijdprogramma komen dan vanzelf binnen en blijven vanzelf bijgewerkt.',

        // Bewust leeg. Hier hoort bijvoorbeeld de btw-vermelding, en die verzint
        // een ontwikkelaar niet voor een ander: het is een uitspraak over de
        // eigen administratie.
        'pricing_fine_print' => '',
    ];

    /**
     * Alle waarden, met de standaard waar de database nog niets heeft.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        $waarden = [];

        foreach (self::DEFAULTS as $sleutel => $standaard) {
            $waarden[$sleutel] = (string) Setting::get($sleutel, $standaard, null);
        }

        return $waarden;
    }

    /** @param array<string, mixed> $data */
    public static function save(array $data): void
    {
        foreach (array_keys(self::DEFAULTS) as $sleutel) {
            if (! array_key_exists($sleutel, $data)) {
                continue;
            }

            Setting::set($sleutel, (string) ($data[$sleutel] ?? ''), self::GROUP, false, null);
        }
    }

    /**
     * Zet een ingetypt bedrag om naar een getal.
     *
     * Een beheerder typt "3,50" of "€ 3,50" en niet "3.50"; dat mag geen nul
     * opleveren op een pagina die over geld gaat.
     */
    public static function naarGetal(?string $ruw): float
    {
        $schoon = str_replace(',', '.', preg_replace('/[^0-9,.]/', '', (string) $ruw) ?? '');

        // Bij "1.234.56" is alleen de laatste punt de decimale scheiding.
        if (substr_count($schoon, '.') > 1) {
            $laatste = strrpos($schoon, '.');
            $schoon  = str_replace('.', '', substr($schoon, 0, (int) $laatste)) . substr($schoon, (int) $laatste);
        }

        return round((float) $schoon, 2);
    }

    /** Een bedrag zoals je het in Nederland opschrijft: € 3,50 en € 250,-. */
    public static function bedrag(float $waarde): string
    {
        return fmod($waarde, 1.0) === 0.0
            ? '€ ' . number_format($waarde, 0, ',', '.') . ',-'
            : '€ ' . number_format($waarde, 2, ',', '.');
    }

    /**
     * De opsomming als losse regels; lege regels vallen weg.
     *
     * @return array<int, string>
     */
    public static function regels(?string $ruw): array
    {
        return array_values(array_filter(
            array_map('trim', preg_split('/\r\n|\r|\n/', (string) $ruw) ?: []),
            fn (string $r) => $r !== '',
        ));
    }
}
