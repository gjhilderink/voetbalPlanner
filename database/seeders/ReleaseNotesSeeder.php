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
                'title'       => 'Verenigingsagenda',
                'description' => 'Alle verenigingsactiviteiten staan nu bij elkaar in de agenda: het toernooi, '
                    . 'de bazaar, clubavonden, vrijwilligersactiviteiten en de oliebollentraining. Je ziet per '
                    . 'activiteit de datum, tijd, locatie en waar het over gaat, en je kunt je in één tik '
                    . 'aanmelden als dat nodig is. De eerstvolgende activiteiten staan ook op je dashboard, '
                    . 'en met "Toevoegen aan mijn agenda" zet je een activiteit in je eigen kalender.',
            ],
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
            [
                'type'        => 'feature',
                'title'       => 'Vlagger bij een wedstrijd',
                'description' => 'Per wedstrijd kan nu een vlagger (grensrechter) worden gekozen uit het team. '
                    . 'De coach stelt de vlagger in via de app of de beheerder in het admin-paneel, en de naam '
                    . 'is voor iedereen zichtbaar op het info-tabblad van de wedstrijd, direct onder de coach.',
            ],
            [
                'type'        => 'improvement',
                'title'       => 'Coach standaard gekoppeld aan een wedstrijd',
                'description' => 'De coach die aan het team hangt, wordt voortaan automatisch als coach bij een '
                    . 'wedstrijd ingevuld. Je hoeft dit niet meer per wedstrijd handmatig te doen; een eigen keuze '
                    . 'blijft uiteraard behouden.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Rondleiding bij de eerste keer inloggen',
                'description' => 'Nieuw in de app? Bij de eerste keer inloggen zie je nu een korte rondleiding met '
                    . 'uitleg over wedstrijden, rijschema & bardienst, chat en de coach-functies. Wil je \'m later '
                    . 'nog eens bekijken? Via je profiel kun je de rondleiding met één tik opnieuw starten.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Rondleiding aanpasbaar per club',
                'description' => 'Beheerders kunnen de onboarding-rondleiding nu volledig zelf samenstellen: slides '
                    . 'toevoegen, herordenen en per slide een titel, tekst, een strak icoon en zelfs een eigen '
                    . 'achtergrondafbeelding kiezen. Zo laat je nieuwe leden precies zien wat jouw club belangrijk vindt.',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'Doelpunt toevoegen werkt vlot (coach)',
                'description' => 'Na het plaatsen van een doelpunt sluit het venster nu netjes en wordt het tabblad '
                    . '"Doelpunten" direct bijgewerkt — geen vastlopende pop-up meer. Bij het invullen van de minuut '
                    . 'verschijnt bovendien een cijfertoetsenbord.',
            ],
            [
                'type'        => 'improvement',
                'title'       => 'Aantal doelpunten op het tabblad',
                'description' => 'Op het tabblad "Doelpunten" zie je nu in een rood telbolletje meteen hoeveel '
                    . 'doelpunten er zijn geregistreerd; dit werkt direct bij zodra je er een toevoegt of verwijdert.',
            ],
            [
                'type'        => 'improvement',
                'title'       => 'Vlagger & gastspeler makkelijker beheren (coach)',
                'description' => 'Je selecteert eerst een naam en bevestigt daarna met een knop — meer controle, minder '
                    . 'misklikken. De gekozen vlagger en gastspelers zie je op het info-tabblad van de wedstrijd, en als '
                    . 'coach kun je ze daar ook weer verwijderen.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Mijn bardiensten op het dashboard',
                'description' => 'Op het dashboard zie je onder "Mijn bardiensten" nu alleen de bardiensten waarvoor je zelf '
                    . 'bent aangemeld, in plaats van alle bardiensten van je team(s).',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Handmatige bardiensten op elke dag en tijd',
                'description' => 'Naast de vaste weekend-dagdelen kan de club nu ook op andere dagen en tijden een bardienst '
                    . 'inplannen met een eigen omschrijving, begin- en eindtijd en aantal personen, en daar een elftal of leden '
                    . 'aan toewijzen.',
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
