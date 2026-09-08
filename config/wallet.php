<?php

declare(strict_types=1);

/**
 * De kaarten in de wallet van een telefoon.
 *
 * Beide kanten hebben iets nodig dat niet in dit project kan staan: Apple wil
 * een certificaat van een Pass Type ID, Google een sleutelbestand van een
 * service-account plus een issuer-nummer. Zolang die er niet zijn, zijn de
 * knoppen niet zichtbaar - zie de beschikbaar()-controles in App\Support\Wallet.
 *
 * Bestanden buiten de webmap zetten. Een certificaat in public/ is met één
 * verzoek van het internet te downloaden, en daarmee kan iemand kaarten maken
 * die op naam van de club staan.
 */
return [

    'apple' => [
        // Het .p12-bestand van het Pass Type ID-certificaat, plus het
        // wachtwoord dat je bij het exporteren hebt gekozen.
        'certificate' => env('APPLE_WALLET_CERT'),
        'password'    => env('APPLE_WALLET_CERT_PASSWORD', ''),

        // Het tussencertificaat van Apple (Worldwide Developer Relations) als
        // .pem. Zonder dat weigert een telefoon de pas zonder uitleg.
        'wwdr' => env('APPLE_WALLET_WWDR'),

        // Zoals ze in het ontwikkelaarsportaal staan.
        'pass_type_id' => env('APPLE_WALLET_PASS_TYPE_ID'),
        'team_id'      => env('APPLE_WALLET_TEAM_ID'),
    ],

    'google' => [
        // Het json-sleutelbestand van het service-account, en het
        // issuer-nummer uit de Google Wallet-console.
        'key_file'  => env('GOOGLE_WALLET_KEY_FILE'),
        'issuer_id' => env('GOOGLE_WALLET_ISSUER_ID'),
    ],

];
