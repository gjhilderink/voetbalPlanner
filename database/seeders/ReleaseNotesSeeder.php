<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Feature;
use App\Models\ReleaseNote;
use Illuminate\Database\Seeder;

/**
 * Maakt Feature-records (status "uitgebracht") voor de recente updates aan.
 * Elke uitgebrachte feature genereert via de Feature-model-hook automatisch een
 * release note. Idempotent: firstOrCreate op titel voorkomt duplicaten bij een
 * herhaalde deploy/seed.
 */
class ReleaseNotesSeeder extends Seeder
{
    public function run(): void
    {
        $features = [
            [
                'type'        => 'feature',
                'title'       => 'Account verwijderen vanuit de app',
                'description' => 'Je kunt nu vanaf je profiel je account volledig zelf verwijderen. '
                    . 'Na een duidelijke bevestiging worden al je gegevens én je chatgeschiedenis '
                    . 'permanent verwijderd en word je uitgelogd.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Functie per team',
                'description' => 'Een lid kan nu per team een functie hebben — bijvoorbeeld speler in '
                    . 'het ene team en coach of leider van een ander team. Coaches en leiders krijgen '
                    . 'automatisch beheerrechten (opstelling & score) voor hun eigen team.',
            ],
            [
                'type'        => 'improvement',
                'title'       => 'Automatische teamwissel',
                'description' => 'Ben je naar een ander team gegaan of aan een extra team gekoppeld? '
                    . 'De app pakt dit nu automatisch op — je hoeft niet meer uit en weer in te loggen '
                    . 'om je juiste team te zien.',
            ],
            [
                'type'        => 'improvement',
                'title'       => 'Teamkoppelingen automatisch opgeschoond',
                'description' => 'Bij een teamwissel via Sportlink blijft je oude team niet meer hangen; '
                    . 'je ziet voortaan alleen je actuele team(s). Handmatig toegevoegde teams blijven '
                    . 'wél behouden.',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'Inloggen via e-maillink verbeterd',
                'description' => 'De inloglink uit de e-mail werkt weer betrouwbaar: geen vastlopen op '
                    . '"Inloglink verifiëren" meer, en de onterechte melding "ongeldig of verlopen" is '
                    . 'opgelost.',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'Kind koppelen aan account werkt weer',
                'description' => 'Een ouder/verzorger kan een kind nu weer eenvoudig koppelen op basis van '
                    . 'lidnummer en achternaam. De onterechte melding "Geen lid gevonden met deze gegevens" '
                    . 'bij correcte gegevens is opgelost.',
            ],
            [
                'type'        => 'improvement',
                'title'       => 'Duidelijker wedstrijdoverzicht op het dashboard',
                'description' => 'Bij "Mijn wedstrijden" zie je nu in één oogopslag of het een thuis- of '
                    . 'uitwedstrijd is, met de tegenstander, datum en tijd en (indien bekend) de locatie — '
                    . 'zonder de wedstrijd eerst te hoeven openen.',
            ],
            [
                'type'        => 'improvement',
                'title'       => 'Clublogo van de tegenstander bij wedstrijden',
                'description' => 'Bij de wedstrijden op het dashboard zie je nu ook het clublogo van de '
                    . 'tegenstander, voor een duidelijker en herkenbaarder overzicht.',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'Snelmenu volledig zichtbaar op Android',
                'description' => 'Het snelmenu (de + op het dashboard) viel op Android deels achter de '
                    . 'navigatiebalk. De onderste knop is nu volledig zichtbaar en bruikbaar.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Team-switcher op het dashboard',
                'description' => 'Ben je aan meerdere teams gekoppeld (of als ouder aan meerdere kinderen)? '
                    . 'Bovenaan het dashboard kun je nu wisselen van team; je wedstrijden en trainingen '
                    . 'passen zich direct aan. Bardiensten en rijschema blijven van al je teams zichtbaar, '
                    . 'want die zijn persoonlijk. Bij één team verschijnt de keuze niet.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Kleedkamer bij trainingen',
                'description' => 'Bij een training zie je nu (indien bekend) de kleedkamer, zodat je '
                    . 'meteen weet waar je moet zijn.',
            ],
            [
                'type'        => 'improvement',
                'title'       => 'Clublogo in de app',
                'description' => 'Het clublogo staat nu rechtsboven in de app, op elke pagina.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Je functie per team op je profiel',
                'description' => 'Op je profiel zie je nu bij elk team ook je functie (bijvoorbeeld speler, '
                    . 'coach of leider).',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'Aantal dagen bij nieuws klopt weer',
                'description' => 'Bij nieuwsberichten werd soms een lange reeks cijfers getoond in plaats van '
                    . 'het aantal dagen. Er staat nu weer netjes "Vandaag" of "X dagen geleden".',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'Kind/lid koppelen werkt volledig',
                'description' => 'Een koppelverzoek op basis van lidnummer en achternaam komt nu correct aan, '
                    . 'en je ziet je ingediende verzoeken terug in het overzicht "Mijn aanvragen" met hun '
                    . 'status. Ook krijg je bij een fout een duidelijke uitleg in plaats van steeds '
                    . '"geen lid gevonden".',
            ],
            [
                'type'        => 'improvement',
                'title'       => 'Clublogo netter in beeld',
                'description' => 'Het clublogo wordt nu als nette rechthoek getoond (niet meer rond bijgesneden) '
                    . 'en staat ook onderaan in het uitklapmenu.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Push-meldingen',
                'description' => 'Je ontvangt nu push-meldingen op je telefoon: bij een nieuw nieuwsbericht en '
                    . 'wanneer een ouder/verzorger jouw account wil koppelen. Zet notificaties aan om ze te '
                    . 'ontvangen.',
            ],
            [
                'type'        => 'improvement',
                'title'       => 'Clublogo en thuis/uit bij wedstrijden',
                'description' => 'In de wedstrijdenlijst zie je nu bij elke wedstrijd het clublogo van de '
                    . 'tegenstander en een duidelijke "Thuis"- of "Uit"-badge.',
            ],
            [
                'type'        => 'improvement',
                'title'       => 'Doelpunten beheren verbeterd (coach)',
                'description' => 'Voor coaches en leiders: een doelpunt toevoegen gaat nu via het kiezen van de '
                    . 'speler uit de lijst (met minuut), je kunt een keuze annuleren, en op de Doelpunten-tab '
                    . 'kun je losse doelpunten verwijderen. De lijst werkt meteen bij.',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'Wedstrijddetail volledig zichtbaar',
                'description' => 'Onderaan de wedstrijddetails viel soms informatie (zoals af-/aanmelden) buiten '
                    . 'beeld. De pagina scrollt nu volledig.',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'Team wisselen werkt bij meerdere teams',
                'description' => 'Ben je aan meerdere teams gekoppeld (bijvoorbeeld als coach van twee teams)? '
                    . 'De teamkiezer verschijnt nu goed op het dashboard en je wedstrijden laden voor het '
                    . 'juiste team — zonder opnieuw in te loggen.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Gastspeler uitnodigen',
                'description' => 'Coaches en leiders kunnen nu een gastspeler uit een ander team uitnodigen voor '
                    . 'een wedstrijd: open de wedstrijd, kies het team en de speler. De gastspeler krijgt een '
                    . 'push-melding, ziet de uitnodiging op zijn dashboard en heeft toegang tot die '
                    . 'wedstrijdinformatie tot kort na de wedstrijd.',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'Datum bij koppelverzoeken netjes',
                'description' => 'Bij de ouder/verzorger-verzoeken werd de datum/tijd als lange technische reeks '
                    . 'getoond. Die staat nu netjes als dag-maand-jaar met tijd.',
            ],
            [
                'type'        => 'improvement',
                'title'       => 'Snelknop op de wedstrijd (coach)',
                'description' => 'Voor coaches en leiders staat er nu een + knop rechtsonder op een wedstrijd. '
                    . 'Daarmee open je een handig menu om snel een doelpunt toe te voegen of een gastspeler uit '
                    . 'te nodigen, overzichtelijk in een pop-up.',
            ],
            [
                'type'        => 'improvement',
                'title'       => 'Mijn uitnodigingen op het dashboard',
                'description' => 'Ben je als gastspeler uitgenodigd? Onder aan je dashboard vind je nu het kopje '
                    . '"Mijn uitnodigingen" met de wedstrijden waarvoor je bent gevraagd; tik erop om de details '
                    . 'te bekijken.',
            ],
        ];

        $sort = 0;
        foreach ($features as $feature) {
            $model = Feature::firstOrCreate(
                ['title' => $feature['title']],
                [
                    'description' => $feature['description'],
                    'type'        => $feature['type'],
                    'status'      => Feature::STATUS_RELEASED,
                    'sort_order'  => $sort++,
                ],
            );

            // Zorg dat de feature echt op "uitgebracht" staat met datum + type,
            // ook als hij al bestond met een andere status/zonder type.
            $model->status      = Feature::STATUS_RELEASED;
            $model->released_at = $model->released_at ?? now();
            $model->type        = $model->type ?: $feature['type'];
            $model->save();

            // Release note expliciet garanderen (niet alleen via de model-hook),
            // zodat een reeds bestaande feature zonder note alsnog een note krijgt.
            ReleaseNote::firstOrCreate(
                ['feature_id' => $model->id],
                [
                    'type'        => $feature['type'],
                    'title'       => $feature['title'],
                    'body'        => $feature['description'],
                    'released_at' => $model->released_at ?? now(),
                ],
            );
        }
    }
}
