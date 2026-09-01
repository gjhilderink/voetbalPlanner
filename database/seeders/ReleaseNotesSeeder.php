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
                'title'       => 'Zie wie zich heeft afgemeld',
                'description' => 'Op het informatieblad van een wedstrijd staat nu wie zich heeft afgemeld en '
                    . 'om welke reden. Heeft niemand zich afgemeld, dan staat dat er ook — zo weet je zeker dat '
                    . 'je niets mist. Voorheen was een afmelding nergens terug te zien, ook je eigen niet, '
                    . 'waardoor het leek alsof er niets gebeurde.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Wedstrijd delen met je team',
                'description' => 'Met de deelknop bovenaan een wedstrijd stuur je in één tik een nette '
                    . 'samenvatting door: tegenstander, datum, aanwezigtijd, locatie, coach, vlagger, fruitheld '
                    . 'en de notitie. Je kiest zelf waarheen — WhatsApp, Signal, mail of iets anders. Onderaan '
                    . 'het bericht staat een link waarmee de ontvanger de wedstrijd direct in de app opent.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Fruitheld kiezen',
                'description' => 'Coaches en leiders kunnen nu ook de fruitheld aanwijzen, via "Fruitheld '
                    . 'kiezen" in het coach-menu. Verwijderen kan met het prullenbakje achter de naam op het '
                    . 'informatieblad, net als bij de vlagger.',
            ],
            [
                'type'        => 'improvement',
                'title'       => 'Vlagger en gastspelers beheren waar ze staan',
                'description' => 'Het verwijderen van de vlagger en van gastspelers zat in een apart blok '
                    . 'onderaan de wedstrijd, waar elke naam een tweede keer stond. Het prullenbakje staat nu '
                    . 'gewoon achter de naam zelf. Gastspelers zijn daardoor ook voor de coach meteen zichtbaar '
                    . 'op de plek waar je ze verwacht.',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'Wijzigingen van de coach meteen zichtbaar',
                'description' => 'Koos je een vlagger, fruitheld of aanwezigtijd, dan bleef het scherm de oude '
                    . 'gegevens tonen tot je de wedstrijd opnieuw opende. Het leek daardoor alsof de wijziging '
                    . 'niet was opgeslagen, terwijl dat wel zo was. Alles wat je in het coach-menu aanpast staat '
                    . 'nu direct op het scherm.',
            ],
            [
                'type'        => 'improvement',
                'title'       => 'Tijden zonder seconden',
                'description' => 'De aanwezigtijd bij een wedstrijd en de tijden van een bardienst werden soms '
                    . 'getoond als 14:30:00. Overal staat nu gewoon 14:30.',
            ],
            [
                'type'        => 'improvement',
                'title'       => 'Wachtwoord opslaan op de iPhone',
                'description' => 'De iPhone herkent het inlogscherm nu, zodat je je inloggegevens kunt opslaan '
                    . 'in je sleutelhanger en de volgende keer met Face ID of Touch ID kunt invullen.',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'Verlopen sessie loopt niet meer vast',
                'description' => 'Was je te lang weg, dan kon de app blijven hangen of lege schermen tonen. '
                    . 'Nu word je netjes uitgelogd en naar het inlogscherm gebracht, zodat je gewoon opnieuw '
                    . 'kunt inloggen.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Notitie bij een wedstrijd',
                'description' => 'Coaches en leiders kunnen vanuit het coach-menu een notitie bij een wedstrijd '
                    . 'zetten of aanpassen — bijvoorbeeld over verzamelen, tenue of vervoer. De notitie staat '
                    . 'meteen bij de wedstrijd en is zichtbaar voor iedereen die erbij hoort. Bestaat er al een '
                    . 'notitie, dan staat die vast ingevuld zodat je hem kunt bijwerken in plaats van overtypen.',
            ],
            [
                'type'        => 'improvement',
                'title'       => 'Activiteiten in je eigen agenda-app',
                'description' => '"Toevoegen aan mijn agenda" zet een activiteit nu rechtstreeks in de agenda van '
                    . 'je telefoon, net als bij een wedstrijd. Voorheen opende dit een pagina in de browser.',
            ],
            [
                'type'        => 'improvement',
                'title'       => 'Overzichtelijker dashboard',
                'description' => 'Je start na het inloggen voortaan op het dashboard. Daar staan nu de '
                    . 'eerstvolgende twee wedstrijden in plaats van de hele lijst — de rest vind je op de '
                    . 'wedstrijdenpagina. Ook zie je er de eerstvolgende verenigingsactiviteiten, met een knop '
                    . 'naar de volledige agenda.',
            ],
            [
                'type'        => 'improvement',
                'title'       => 'Nieuwe opmaak voor wedstrijd en training',
                'description' => 'De wedstrijdinformatie en het af- en aanmelden voor trainingen zijn opnieuw '
                    . 'vormgegeven: rustige kaarten met een duidelijk icoon per onderdeel, het clublogo bij de '
                    . 'tegenstander en een knop om direct naar de locatie te navigeren.',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'Coaches zagen wedstrijden van andere teams',
                'description' => 'Een coach die aan één elftal gekoppeld is, kreeg in sommige gevallen alle '
                    . 'wedstrijden te zien in plaats van alleen die van het eigen team. Je ziet nu weer '
                    . 'uitsluitend de wedstrijden, leden en teams van je eigen club en elftallen.',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'Trainingen en teamkeuze blijven staan',
                'description' => 'Na het opstarten van de app kon het gebeuren dat je trainingen leeg bleven en '
                    . 'de teamkeuze bovenaan het dashboard verdween, tot je opnieuw inlogde. De app onthoudt je '
                    . 'teams nu, en valt bij het ophalen van trainingen terug op je eigen team.',
            ],
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
            [
                'type'        => 'feature',
                'title'       => 'Vernieuwd dashboard',
                'description' => 'Het dashboard is opnieuw opgebouwd. Bovenaan een begroeting met je foto en de teamkeuze, '
                    . 'daaronder je rol, de volgende wedstrijd met beide clublogo\'s, de eerstvolgende training en je '
                    . 'persoonlijke taken. Alles staat nu in overzichtelijke blokken bij elkaar.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Nieuwe navigatiebalk met Trainingen en Meer',
                'description' => 'Onderin de app staan nu zes knoppen met tekst erbij: Dashboard, Wedstrijden, Trainingen, '
                    . 'Agenda, Berichten en Meer. Trainingen is een nieuw overzicht van alle geplande trainingen. Onder Meer '
                    . 'vind je het rijschema, de bardiensten, je profiel en de rest.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Komende activiteiten bij elkaar',
                'description' => 'Wedstrijden, trainingen en de verenigingsagenda staan op het dashboard in één lijst op '
                    . 'volgorde van datum, met een datumblokje en een icoon per soort. Tikken opent meteen het juiste scherm.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Teamkeuze als uitklapmenu',
                'description' => 'Heb je meerdere teams, dan kies je bovenin het dashboard met één tik welk team je bekijkt. '
                    . 'De wedstrijden en trainingen schakelen meteen mee.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Statistieken van je team en jezelf',
                'description' => 'Nieuw op het dashboard: de cijfers van je team dit seizoen (gespeeld, winst-gelijk-verlies, '
                    . 'punten en doelsaldo) en je eigen cijfers: aanwezigheid, wedstrijden, trainingen, doelpunten en assists.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Teamsfeer',
                'description' => 'Geef met één tik op een smiley aan hoe de sfeer in het team is. Op het dashboard zie je het '
                    . 'gemiddelde van het team en hoeveel mensen hebben gereageerd. Wie wat heeft gestemd blijft geheim.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Zie wie er op de training komt',
                'description' => 'Bij een training staat nu wie er komt en wie zich heeft afgemeld, met het aantal erbij en de '
                    . 'reden van afmelding. Handig voor trainers die hun oefening willen voorbereiden.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Live wedstrijdverslag',
                'description' => 'De coach start bij de aftrap een live verslag en tikt tijdens de wedstrijd doelpunten, '
                    . 'wissels en kaarten aan. Teamleden volgen de stand, de speelklok en het verslag live mee in de app.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Meekijken zonder app',
                'description' => 'Bij een live wedstrijd deelt de coach een link, bijvoorbeeld via WhatsApp. Wie de app niet '
                    . 'heeft, opent die link en volgt de wedstrijd gewoon in de browser. De link werkt zolang de wedstrijd bezig is.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Kaarten en fair play',
                'description' => 'Gele en rode kaarten worden tijdens het live verslag vastgelegd en tellen mee in je '
                    . 'seizoenscijfers op het dashboard.',
            ],
            [
                'type'        => 'improvement',
                'title'       => 'Uitnodigingen in dezelfde opmaak',
                'description' => 'Het blok met gastspeler-uitnodigingen op het dashboard heeft dezelfde kaartopmaak gekregen '
                    . 'als de rest, met het logo van de tegenstander erbij.',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'Terugknop op onderliggende schermen',
                'description' => 'Op het rijschema, de bardiensten en je profiel stond een menuknop waar de terugpijl hoort. '
                    . 'Je kon daardoor niet terug naar het vorige scherm. Dat is opgelost.',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'Rijschema-blok bleef over het dashboard hangen',
                'description' => 'Het oude rijschema-blok bleef bovenin het scherm over de rest van het dashboard heen staan. '
                    . 'Het is verwijderd; je rijbeurten staan nu onder "Mijn taken".',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Wedstrijd achteraf terugkijken',
                'description' => 'Bij een wedstrijd staat een tabblad "Verslag" met het volledige verloop: aftrap, '
                    . 'doelpunten, wissels, kaarten, rust en eindsignaal, elk met de minuut erbij. Zo kun je later nog '
                    . 'nalezen wat er gebeurd is.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Opstelling bij de live wedstrijd',
                'description' => 'De coach deelt spelers met één tik in bij de basis of de bank. Wie meekijkt ziet die '
                    . 'opstelling op de livepagina staan.',
            ],
            [
                'type'        => 'improvement',
                'title'       => 'Doelpunten uit het live verslag meteen zichtbaar',
                'description' => 'Een doelpunt dat de coach tijdens de wedstrijd aantikt, staat nu direct op het tabblad '
                    . '"Doelpunten" en telt mee in de seizoenscijfers. Voorheen zag je het pas na het opnieuw openen van '
                    . 'de wedstrijd.',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'Account verwijderen negeerde "Annuleren"',
                'description' => 'De knop "Account verwijderen" vroeg om een bevestiging, maar verwijderde het account ook '
                    . 'als je op Annuleren tikte. Dat is opgelost: de bevestiging staat nu als blok op de pagina en alleen '
                    . '"Ja, alles verwijderen" doet iets. Ben je hierdoor je account kwijtgeraakt, meld het bij de club — '
                    . 'het is te herstellen.',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'Trainingdetail kon niet scrollen',
                'description' => 'Bij een training met een volledig team vielen de onderste spelers buiten beeld en kon je '
                    . 'niet naar beneden scrollen. De pagina scrollt nu.',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'Filters in de rapportages',
                'description' => 'In het wedstrijdrooster liep de periodefilter vast zodra je een periode koos. Dat is '
                    . 'verholpen; ook het rijschema-rapport is nagelopen.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Bardienst voor twee personen tegelijk',
                'description' => 'Ouders komen vaak samen en melden zich aan via het kind. Bij het aanmelden voor een '
                    . 'bardienst kies je nu of je één of twee plekken vult. In het overzicht staat er dan "(2 personen)" '
                    . 'achter je naam en telt de dienst ook echt voor twee.',
            ],
            [
                'type'        => 'improvement',
                'title'       => 'Zien bij wélk elftal een bericht staat',
                'description' => 'Hoor je bij meerdere teams, dan zag je wel dát er een ongelezen bericht was, maar niet '
                    . 'waar. Op de chatpagina staat het aantal ongelezen berichten nu per elftal, en je schakelt met één '
                    . 'tik naar dat team.',
            ],
            [
                'type'        => 'improvement',
                'title'       => 'Meldingen voor al je elftallen',
                'description' => 'Pushberichten kwamen alleen binnen voor het elftal dat op dat moment actief stond. Je '
                    . 'toestel luistert nu mee met álle teams waar je bij hoort, dus je mist geen bericht meer van het '
                    . 'andere elftal van je kind.',
            ],
            [
                'type'        => 'improvement',
                'title'       => 'Rol-tabs horen bij het elftal',
                'description' => 'Op het dashboard zag je alle functies die je ergens in de club hebt. Ben je coach van '
                    . 'het ene en speler in het andere elftal, dan kreeg je overal een coach-tab met een stafblok dat bij '
                    . 'een ander team hoorde. De tabs volgen nu het elftal dat je hebt gekozen: speel je daar alleen, dan '
                    . 'staat er alleen "Speler".',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'Aanmelden als ouder op een nieuw toestel',
                'description' => 'Op een net geïnstalleerde app werkte het aanmelden als ouder niet: je bleef op het '
                    . 'inlogscherm staan, en pas nadat er ooit iemand op dat toestel had ingelogd lukte het wél. Bij de '
                    . 'eerste inlog bleef het scherm daarna soms grijs, tot je de app afsloot en opnieuw opende. Allebei '
                    . 'opgelost.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Toegangsverzoek van een ouder op het dashboard',
                'description' => 'Vraagt een ouder toegang tot jouw gegevens, dan stond dat verzoek alleen op je '
                    . 'profielpagina. Het staat nu bovenaan het dashboard, waar je het meteen ziet. Na je antwoord '
                    . 'verdwijnt het blok en krijg je een bevestiging.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Bericht zodra je kind akkoord geeft',
                'description' => 'Als ouder krijg je nu een melding op je toestel op het moment dat je kind je verzoek '
                    . 'goedkeurt. Voorheen moest je zelf blijven kijken of er al iets veranderd was.',
            ],
            [
                'type'        => 'improvement',
                'title'       => 'Uitleg zolang je nog op akkoord wacht',
                'description' => 'Een ouder die net een account had aangemaakt zag een leeg dashboard: geen team, geen '
                    . 'wedstrijden, geen trainingen. Dat klopte — zonder akkoord van het kind hoort er niets te staan — '
                    . 'maar het zei niet waarom. Er staat nu uitleg met de naam van het kind op wie je wacht.',
            ],
            [
                'type'        => 'improvement',
                'title'       => 'Ouderaccounts herkenbaar in het clubbeheer',
                'description' => 'Ouderaccounts kwamen als gewone gebruiker in de lijst te staan. Ze krijgen nu de rol '
                    . '"Ouder/verzorger", en een kolom laat zien bij welk kind ze horen.',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'Excel-import werkt weer',
                'description' => 'Het importeren van leden, wedstrijden en bardiensten liep vast op "er is een onverwachte '
                    . 'fout opgetreden": het geüploade bestand werd op de verkeerde plek gezocht. Daarnaast werden '
                    . 'geboortedatums uit een als datum opgemaakte Excel-cel niet herkend, waardoor élke rij werd '
                    . 'afgekeurd. Beide zijn verholpen.',
            ],
            [
                'type'        => 'improvement',
                'title'       => 'Ledenimport legt de relatiecode vast',
                'description' => 'De relatiecode werd bij het importeren alleen gelezen, nooit opgeslagen. Een nieuw lid '
                    . 'kwam daardoor zonder code binnen, en een tweede import van hetzelfde bestand zette iedereen er nog '
                    . 'een keer bij. De code wordt nu vastgelegd bij leden die er nog geen hebben.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Verwijderde leden terugzetten',
                'description' => 'In de ledenlijst staat een filter "Verwijderd" en een knop "Herstellen", zodat je kunt '
                    . 'zien wie er weg is en iemand kunt terughalen. Verwijst een importbestand naar een verwijderd lid, '
                    . 'dan wordt dat lid teruggezet en staat dat er met naam en toenaam bij in de melding.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Rijschema in Excel indelen',
                'description' => 'Het rijschema kon alleen als PDF naar buiten. Je kunt het nu exporteren naar Excel, de '
                    . 'rijders voor een heel seizoen in één keer invullen en het bestand terugzetten. De export bevat óók '
                    . 'de uitwedstrijden waar nog niemand rijdt — dat is meteen je overzicht van wat er nog open staat.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Verslag verwijderen',
                'description' => 'Een verslag dat per ongeluk gestart is of niet klopt, kon je nergens meer kwijt: '
                    . '"ongedaan maken" haalt alleen de laatste gebeurtenis weg, en na het eindsignaal ging dat ook niet '
                    . 'meer. Op het tabblad "Verslag" staat nu een knop om het hele verslag te verwijderen, met een '
                    . 'bevestiging die precies vertelt wat er weggaat. Doelpunten die tijdens dat verslag zijn vastgelegd '
                    . 'gaan mee; de uitslag van de wedstrijd blijft staan. Alleen de coach of leider ziet de knop.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Coach meldt spelers af en aan',
                'description' => 'Sta je langs de lijn en is iemand er niet, dan zet je dat nu zelf om. Achter elke '
                    . 'speler in het opkomstlijstje van een training of wedstrijd staat een knop "Afmelden" of '
                    . '"Aanmelden". Er staat dan bij dat de trainer of coach het heeft gedaan. Alleen wie het elftal '
                    . 'beheert ziet de knoppen.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Zie bij een wedstrijd wie er komt',
                'description' => 'Bij een wedstrijd stond alleen wie zich had afgemeld — wie er nog wél stond zag je '
                    . 'nergens. Nu staat de hele selectie er, verdeeld over "Aanwezig" en "Afgemeld", met de tellingen '
                    . 'erbij. Net zoals dat bij een training al werkte.',
            ],
            [
                'type'        => 'improvement',
                'title'       => 'Live verslag ziet er moderner uit',
                'description' => 'De publieke livepagina heeft een donkere kop gekregen met beide clubemblemen om de '
                    . 'stand heen en de speelminuut ertussen, en drie tabbladen: tijdlijn, opstelling en statistieken. '
                    . 'De tijdlijn is een lijn met ronde iconen; doelpunten, kaarten en het eindsignaal springen eruit. '
                    . 'In de app hebben de livepagina en het tabblad "Verslag" dezelfde opmaak gekregen.',
            ],

            // ── Ronde van eind augustus ──────────────────────────────────────
            [
                'type'        => 'feature',
                'title'       => 'Pasfoto bij je teamgenoten',
                'description' => 'De ledenlijst van je elftal toont nu de pasfoto uit Sportlink, zodat je bij een '
                    . 'naam meteen een gezicht hebt. Heb je zelf een foto in de app gezet, dan blijft die staan — '
                    . 'die wordt niet overschreven door de clubfoto. Wie geen foto heeft, houdt gewoon zijn '
                    . 'rugnummer of rolafkorting.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'De stand van je poule',
                'description' => 'Onder "Meer" staat nu "Stand": de volledige poulestand van je elftal, met je eigen '
                    . 'ploeg dikgedrukt. De cijfers op het dashboard komen daar ook vandaan, dus positie, punten en '
                    . 'doelsaldo kloppen met de officiële stand en niet alleen met de wedstrijden die de app kent. '
                    . 'Tik op het blok en je gaat er direct naartoe.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Wedstrijdverslagen teruglezen',
                'description' => 'Een live verslag stond alleen bij die ene wedstrijd, en was een maand later niet '
                    . 'meer terug te vinden. Onder "Meer" staat nu "Wedstrijdverslagen": alle bijgehouden verslagen '
                    . 'onder elkaar, nieuwste eerst, met de uitslag in kleur. Zoeken kan op tegenstander of elftal.',
            ],
            [
                'type'        => 'feature',
                'title'       => "Foto's bij een wedstrijd",
                'description' => 'Bij elke wedstrijd staat een tabblad "Media". Iedereen uit het elftal kan er foto\'s '
                    . 'bij zetten — maximaal vijf per persoon — en onder elke foto staat wie hem heeft geplaatst. Je '
                    . 'eigen foto\'s kun je weer weghalen; de coach kan alles weghalen.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Statistiek in plaats van doelpunten',
                'description' => 'Het tabblad "Doelpunten" bij een wedstrijd is "Statistiek" geworden. Je ziet nu de '
                    . 'eindstand, doelpunten voor en tegen, wie er scoorden en wie de assists gaven, de kaarten met '
                    . 'namen erbij en het aantal wissels — allemaal uit het live verslag. Is er geen verslag '
                    . 'bijgehouden, dan staat dat er, in plaats van een scherm vol nullen.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Documenten bij je elftal',
                'description' => 'Spelregels, formulieren en draaiboeken gingen rond als bijlage in een appgroep en '
                    . 'waren later niet meer te vinden. Onder "Meer" staat nu "Documenten", met de documenten van je '
                    . 'eigen elftal én die van de hele club. De club plaatst ze in de portal en kan er een link van '
                    . 'delen die het ook in een appbericht of een mail doet.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Opstelling maken op je eigen helft',
                'description' => 'Het opstellingsbord toont nu alleen je eigen helft, zodat elf spelers er ruim op '
                    . 'passen in plaats van op elkaar te kruipen. Bij de nog niet ingedeelde spelers staan de knoppen '
                    . '"Wissel" en "Opstellen", zodat je ze zonder slepen kunt indelen. Tik je op een speler in het '
                    . 'veld, dan kies je tussen verslepen, naar de bank, of helemaal uit de opstelling.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Opstelling houdt de telling bij',
                'description' => 'Bij de instellingen hoort de opstelling nu bij het aantal spelers: kies je negen, '
                    . 'dan zie je alleen opstellingen die op negen uitkomen. Staan er meer spelers in het veld dan is '
                    . 'ingesteld, dan verschijnt daar een waarschuwing over — je wordt niet geblokkeerd, want tijdens '
                    . 'het schuiven klopt het even niet. Afgemelde spelers kun je niet meer indelen.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Rijders aanwijzen in de app',
                'description' => 'In het plusmenu bij een wedstrijd staat nu "Rijders toevoegen". Je tikt een naam '
                    . 'aan, bevestigt, en wie al rijdt is in de lijst gemarkeerd zodat je hem er ook weer uit kunt '
                    . 'halen. De rijders staan daarna bij de wedstrijdgegevens. "Doelpunt toevoegen" is uit dit menu '
                    . 'verdwenen; dat kan nog via het live verslag.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Zie met wie je gekoppeld bent',
                'description' => 'Op je profiel staat nu een blok "Koppelingen": met wie je verbonden bent, beide '
                    . 'kanten op. Een ouder ziet zijn kinderen, en een speler ziet eindelijk ook wie er meekijkt. '
                    . 'Intrekken kan met de knop ernaast, met een bevestiging ervoor.',
            ],
            [
                'type'        => 'improvement',
                'title'       => 'Chatten met je teamgenoten',
                'description' => 'De lijst om iemand direct te appen ziet er nu uit als de teamledenpagina: foto, '
                    . 'rugnummer en functie. Bij wie de app nog niet heeft geactiveerd staat "Nog niet online" en '
                    . 'ontbreekt de chatknop — een gesprek beginnen dat nooit gelezen wordt heeft geen zin.',
            ],
            [
                'type'        => 'improvement',
                'title'       => 'Sneller bij je taken en je profiel',
                'description' => 'De taken op het dashboard — rijden, vlaggen, fruit, bardienst — zijn nu '
                    . 'aanklikbaar en brengen je naar de wedstrijd of de dienst waar het om gaat. Een tik op je foto '
                    . 'in de kop opent je profiel.',
            ],
            [
                'type'        => 'improvement',
                'title'       => 'Alleen de bardiensten die jou aangaan',
                'description' => 'De bardienstenlijst toonde voor sommige rollen alle diensten van de hele club. Je '
                    . 'ziet nu alleen de diensten van de elftallen waar je bij hoort — en dan wel van al je '
                    . 'elftallen, ook die van je kinderen.',
            ],
            [
                'type'        => 'improvement',
                'title'       => 'Knopteksten weer leesbaar',
                'description' => 'De tekst op knoppen was overal kleiner dan de rest van de app, waardoor inloggen '
                    . 'en uitloggen slecht te lezen waren. Alle knoppen hebben nu een vaste, grotere lettergrootte.',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'Teamledenlijst was niet te scrollen',
                'description' => 'Bij een elftal dat niet op één scherm paste, kon je niet verder naar beneden: de '
                    . 'lijst liep gewoon van het scherm af. Nu scrollt de pagina zoals overal elders.',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'Cijfers wisselden niet mee met je elftal',
                'description' => 'Schakelde je op het dashboard naar een ander elftal, dan bleven de teamcijfers die '
                    . 'van het vorige team tonen tot je het dashboard sloot en opnieuw opende. Wedstrijden en '
                    . 'trainingen wisselden wel al mee; de cijfers doen dat nu ook.',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'Stand bleef leeg',
                'description' => 'Bij sommige elftallen bleef de standpagina leeg of toonde hij een tabel zonder '
                    . 'cijfers. De koppeling gaf in dat geval de poules terug in plaats van de stand, en dat werd '
                    . 'klakkeloos aangenomen. Is er nog geen stand — bijvoorbeeld omdat de competitie nog moet '
                    . 'beginnen — dan staat dat er nu, in plaats van lege vakjes.',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'Bardiensten konden helemaal wegvallen',
                'description' => 'Stond er op een bardienst iemand die geen ledenprofiel heeft, dan gaf de hele '
                    . 'bardienstenlijst een foutmelding — niet alleen die ene dienst. Dat is verholpen.',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'Registreren als ouder gaf een serverfout',
                'description' => 'Was er ooit een account met hetzelfde e-mailadres aangemaakt en later verwijderd, '
                    . 'dan liep het registreren vast op "Server Error" zonder verdere uitleg. Nu krijg je te horen '
                    . 'wat er aan de hand is, en blijft er niets halfs achter als er iets misgaat.',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'Koppeling intrekken werkte niet',
                'description' => 'De knop om een ouder/verzorger-koppeling in te trekken deed niets: het verzoek '
                    . 'werd verstuurd op een manier die de server weigert. Dat is rechtgezet, en na het intrekken '
                    . 'verdwijnt de regel meteen uit de lijst.',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'Geen foutmeldingen meer als je niet bent ingelogd',
                'description' => 'Opende je de app zonder actieve sessie, dan probeerde het scherm eerst van alles '
                    . 'op te halen — wat allemaal mislukte — en kreeg je een rij foutmeldingen te zien, gevolgd door '
                    . '"je sessie is verlopen". Nu wordt eerst gekeken of je bent ingelogd, en zo niet, dan kom je '
                    . 'gewoon op het inlogscherm. Ook wie via een inloglink binnenkomt krijgt die melding niet meer, '
                    . 'want die had nog helemaal geen sessie.',
            ],
            // ── Live verslag ────────────────────────────────────────────────
            [
                'type'        => 'feature',
                'title'       => 'Schot op doel vastleggen',
                'description' => 'In het live verslag staan twee knoppen erbij: schot en schot tegen. Eén tik, '
                    . 'zonder eerst een speler te kiezen — er vallen er te veel om er telkens een naam bij te '
                    . 'zoeken. De aantallen komen terug op de publieke pagina, in het tabblad Statistiek en in '
                    . 'de tijdlijn.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Zie hoeveel mensen er meekijken',
                'description' => 'Terwijl je het live verslag bijhoudt zie je in de coachbalk hoeveel mensen er '
                    . 'op dat moment meelezen — via de app en via de gedeelde link samen. Het getal is van dit '
                    . 'moment en reageert binnen ongeveer een halve minuut.',
            ],
            [
                'type'        => 'improvement',
                'title'       => 'Het verslag begint pas als jij op start drukt',
                'description' => '"Start live verslag" op de wedstrijdpagina begon meteen met tellen, ook als je '
                    . 'alleen even wilde kijken of alles klaarstond. Die knop brengt je nu naar de livepagina, '
                    . 'waar je zelf op start drukt. Loopt er al een verslag, dan staat er "Open live verslag" — '
                    . 'zo kunnen twee coaches samen één wedstrijd bijhouden zonder opnieuw te beginnen.',
            ],
            [
                'type'        => 'improvement',
                'title'       => 'De opstelling staat onderaan de livepagina',
                'description' => 'Tijdens de wedstrijd kijk je naar de klok en het verslag; spelers indelen doe '
                    . 'je vooraf. De opstelling stond daar tussenin en moest elke keer voorbij gescrold worden.',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'De opstelling was te vroeg zichtbaar',
                'description' => 'Wie op "live volgen" tikte zag de opstelling, ook als de coach hem nog niet had '
                    . 'vrijgegeven. Datzelfde gold voor de gedeelde link, waar iedereen bij kan. Niet vrijgegeven '
                    . 'is nu niet zichtbaar, en voor wie meekijkt verschijnt de opstelling pas na de aftrap.',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'Coaches stonden tussen de spelers',
                'description' => 'Bij het kiezen van een doelpuntenmaker of het indelen van de opstelling stonden '
                    . 'de coaches en leiders in de lijst, en door de sortering op naam vaak bovenaan.',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'Statistiek werd niet bijgewerkt',
                'description' => 'Het tabblad Statistiek laadde alleen bij het openen van de wedstrijd. Wie een '
                    . 'verslag weggooide of een doelpunt noteerde en daarna terugbladerde, keek naar de oude '
                    . 'cijfers.',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'Verslag verwijderen liet de uitslag staan',
                'description' => 'Na het weggooien van een verslag bleef de eindstand op de wedstrijdkaart staan, '
                    . 'terwijl de doelpunten en de statistiek weg waren. Bij een wedstrijd uit Sportlink komt de '
                    . 'officiële uitslag bij de volgende synchronisatie vanzelf terug.',
            ],

            // ── Opstelling ──────────────────────────────────────────────────
            [
                'type'        => 'feature',
                'title'       => 'Standaardopstelling per elftal',
                'description' => 'Stel één keer een vaste opstelling samen voor je team en laad die bij elke '
                    . 'wedstrijd in met één knop. Je past hem daarna nog aan zonder de standaard te wijzigen.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Notitie bij de opstelling',
                'description' => 'Een veld voor tactische afspraken bij de opstelling, boven de opslaanknop. '
                    . 'Alleen zichtbaar voor wie de opstelling beheert.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Wisselspeler met één knop terug het veld in',
                'description' => 'Zet je iemand op de bank, dan staat er nu een knop "Opstellen" bij. Slepen kan '
                    . 'nog steeds, maar op een telefoon is dat gepriegel.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Zoeken bij het uitnodigen van een gastspeler',
                'description' => 'In het venster waarin je een gastspeler kiest zit een zoekveld. Bij een club '
                    . 'met honderden leden scheelt dat een halve minuut scrollen.',
            ],
            [
                'type'        => 'improvement',
                'title'       => 'Volledige naam op het opstellingsbord',
                'description' => 'Op het veld stond alleen de voornaam. Met twee keer een Sem of een Daan in de '
                    . 'selectie wist je niet wie waar stond. Er staat nu de hele naam, en de namenlijst ernaast '
                    . 'is breder zodat een tussenvoegsel er ook bij past.',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'Gastspelers stonden niet in de opstelling',
                'description' => 'Een uitgenodigde gastspeler kwam nergens in de opstelling voor, ook niet nadat '
                    . 'hij had toegezegd. Hij staat er nu meteen bij, onderaan de selectie met een label "Gast".',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'Afgemelde spelers blijven niet in de opstelling staan',
                'description' => 'Wie zich afmeldde werd rood in de opstelling maar bleef staan, en een afgemelde '
                    . 'wisselspeler was niet meer te verplaatsen. Afmelden zet iemand nu bij de niet ingedeelde '
                    . 'spelers, met "Afgemeld" erbij.',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'Opstelling met twee helften kwam verkeerd terug',
                'description' => 'Een opstelling met perioden las elke speler als periode 1: je zag dubbele '
                    . 'pionnen in de eerste helft en een lege tweede.',
            ],

            // ── Trainingen en afgelasten ────────────────────────────────────
            [
                'type'        => 'feature',
                'title'       => 'Trainingen twee weken vooruit',
                'description' => 'De trainingenpagina toonde er twee. Nu staat alles van de komende twee weken '
                    . 'erin.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Wedstrijd of training afgelasten',
                'description' => 'Coach en trainer kunnen een wedstrijd of een losse training afgelasten, met een '
                    . 'reden, en hem weer vrijgeven als het toch doorgaat. Bij een training blijft het schema '
                    . 'gewoon staan: volgende week is er weer training.',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'Afgelasting was niet te zien op het dashboard',
                'description' => 'Een afgelaste wedstrijd stond gewoon tussen de komende activiteiten, met "Thuis" '
                    . 'erachter alsof er niets aan de hand was. Nu staat de reden erbij, ook op de kaart met de '
                    . 'volgende wedstrijd.',
            ],

            // ── Cijfers ─────────────────────────────────────────────────────
            [
                'type'        => 'feature',
                'title'       => 'Teamstatistieken voor de coach',
                'description' => 'Tik op de kaart Statistieken op het dashboard en je ziet de cijfers van het hele '
                    . 'elftal: resultaten, topscorers, assists, kaarten per speler, schoten op doel en de opkomst '
                    . 'per speler. Alleen voor wie het elftal beheert.',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'Seizoenscijfers klopten niet',
                'description' => 'Een wedstrijd telde alleen mee als Sportlink hem als uitgespeeld aanleverde. '
                    . 'Oefenwedstrijden, handmatig ingevoerde wedstrijden en wedstrijden waarvan je de uitslag '
                    . 'zelf invulde vielen buiten alle cijfers. Ook doelpunten uit een verslag waarbij nooit op '
                    . '"Einde" was gedrukt telden niet mee.',
            ],

            // ── Kleding ─────────────────────────────────────────────────────
            [
                'type'        => 'feature',
                'title'       => 'Kledingmaten op je profiel',
                'description' => 'Geef je maten op voor shirt, broek, sokken en de rest; ouders doen dat voor hun '
                    . 'kinderen. Per persoon een uitklapblok, zodat een gezin met drie kinderen geen lijst van '
                    . 'twintig regels krijgt. De kledingcommissie ziet in de portal per elftal wie wat heeft '
                    . 'opgegeven en wie nog niets.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Nummer per kledingstuk',
                'description' => 'Naast de maat kun je nu ook het nummer op een kledingstuk invullen — het '
                    . 'rugnummer op een shirt bijvoorbeeld. Kies eerst een maat, daarna verschijnt het '
                    . 'nummerveld.',
            ],

            // ── Ouders, af- en aanmelden ────────────────────────────────────
            [
                'type'        => 'feature',
                'title'       => 'Ouders melden hun kind af en aan',
                'description' => 'Ben je gekoppeld aan je kind, dan meld je het af of aan voor een training of '
                    . 'wedstrijd zonder in te loggen als je kind. Bij meerdere kinderen in hetzelfde elftal kies '
                    . 'je om wie het gaat.',
            ],
            [
                'type'        => 'improvement',
                'title'       => 'Af- en aanmeldlijst beter leesbaar',
                'description' => 'De lijst met wie er komt en wie niet is opnieuw opgemaakt: hele namen, ronde '
                    . 'badges en een teller bovenaan.',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'Koppelingen stonden niet op je profiel',
                'description' => 'Het blok met wie je gekoppeld bent bleef leeg door een serverfout, waardoor het '
                    . 'leek alsof je aan niemand gekoppeld was. Je ziet nu beide kanten: een kind ziet ook zijn '
                    . 'gekoppelde ouders.',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'Kind koppelen gaf "Too Many Attempts"',
                'description' => 'Wie een tweede kind wilde koppelen liep tegen een Engelse foutmelding aan. De '
                    . 'grens is verruimd en de melding is voortaan Nederlands en legt uit hoe lang je moet '
                    . 'wachten.',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'Af- en aanmelden alleen voor je eigen elftal',
                'description' => 'Toegang tot een elftal en er lid van zijn liepen door elkaar. Daardoor kon je je '
                    . 'af- en aanmelden bij een team waar je alleen meekeek.',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'Teamsfeer: ouders kijken mee, stemmen niet mee',
                'description' => 'Een ouder of verzorger kon de sfeer van het elftal kiezen. Dat is aan de spelers '
                    . 'zelf; ouders zien de uitslag wel.',
            ],

            // ── Wedstrijden en taken ────────────────────────────────────────
            [
                'type'        => 'bugfix',
                'title'       => '"Toon alle wedstrijden" liet alle elftallen zien',
                'description' => 'De schakelaar bij Wedstrijden gaf de wedstrijden van álle elftallen waar je bij '
                    . 'hoort door elkaar. Hij blijft nu bij het gekozen elftal en toont het hele seizoen, '
                    . 'gespeeld en nog te spelen.',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'Rijschema toonde ritten uit het verleden',
                'description' => 'De taak "Rijden" op het dashboard wees een wedstrijd van maanden geleden aan, en '
                    . 'op de rijschemapagina stond diezelfde geschiedenis bovenaan.',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'Bardienst bleef "open" staan',
                'description' => 'Een bardienst waarvoor genoeg mensen waren ingedeeld bleef als open staan.',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'Foto van een wedstrijd verwijderen gaf een foutmelding',
                'description' => 'Het prullenbakje bij een foto leverde een 404 op en de foto bleef staan.',
            ],

            // ── Uiterlijk van de app ────────────────────────────────────────
            [
                'type'        => 'improvement',
                'title'       => 'De app in een nieuwe huisstijl',
                'description' => 'Groen als hoofdkleur, een nieuw lettertype en een rustiger beeld. Heeft je club '
                    . 'eigen kleuren ingesteld, dan blijven die natuurlijk voorgaan.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'De app in de stijl van je club',
                'description' => 'Bij de eerste keer inloggen vraagt de app of je hem in de kleuren en het logo '
                    . 'van je club wilt. Het inlogscherm toont voortaan het logo van je club in plaats van een '
                    . 'algemeen icoon. Je kunt de keuze altijd wijzigen in je profiel.',
            ],
            [
                'type'        => 'improvement',
                'title'       => 'Profielpagina opgeruimd',
                'description' => 'Je naam stond er twee keer, met alle inhoud ertussen. Nu één kop bovenaan met je '
                    . 'foto, en je elftallen in dezelfde vorm als je koppelingen. De pagina laat zich scrollen — '
                    . 'de knoppen onderaan vielen buiten beeld — en die knoppen hebben iconen gekregen.',
            ],
            [
                'type'        => 'improvement',
                'title'       => 'Uitloggen ook onderaan Meer',
                'description' => 'Je hoefde er niet meer voor naar je profiel.',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'Knoppen waren zwart voordat je inlogde',
                'description' => 'Op een verse installatie was het inlogscherm zwart in plaats van in de kleuren '
                    . 'van de app.',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'Na uitloggen bleef de vorige club staan',
                'description' => 'Logde iemand anders in op hetzelfde toestel, dan zag die op het inlogscherm nog '
                    . 'het logo en de kleuren van zijn voorganger.',
            ],

            // ── Voor de club: koppeling en beheer ───────────────────────────
            [
                'type'        => 'feature',
                'title'       => 'Sportlink synchroniseert twee keer per dag',
                'description' => 'De koppeling met Sportlink draait automatisch \'s ochtends en \'s avonds, met '
                    . 'een statusmail naar de club. In de portal staat een monitor die laat zien of hij loopt.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Coaches worden automatisch aan wedstrijden gekoppeld',
                'description' => 'Bij het importeren van wedstrijden krijgen ze meteen de coaches en leiders van '
                    . 'het elftal. Er is ook een opschoonactie voor wedstrijden die er al stonden.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Kledingcommissie in de portal',
                'description' => 'Een nieuwe rol die per elftal ziet wie welke maat heeft, met een export naar '
                    . 'Excel. De commissie beheert zelf welke kledingstukken er zijn en welke maten daarbij '
                    . 'horen.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Tarievenpagina en demo aanvragen',
                'description' => 'Op voetbalplanner.nl staat nu een tarievenpagina met rekenhulp, en kun je '
                    . 'vrijblijvend een demo aanvragen. De aanvragen komen in de portal binnen.',
            ],
            [
                'type'        => 'improvement',
                'title'       => 'Teamfilter bij gebruikers',
                'description' => 'In de portal filter je de gebruikerslijst nu op elftal.',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'Oefenwedstrijden kwamen niet mee uit Sportlink',
                'description' => 'Sportlink levert oefenwedstrijden onder een ander teamnummer aan, waardoor ze '
                    . 'werden overgeslagen.',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'Synchronisatie viel om over een verwijderde wedstrijd',
                'description' => 'Stond er een handmatig toegevoegde wedstrijd die later was verwijderd, dan '
                    . 'stopte de hele synchronisatie met een foutmelding.',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'Uitgezette handleidingonderwerpen kwamen terug',
                'description' => 'Na een update stonden onderwerpen die de club had uitgezet weer aan.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Toon mij dit: uitleg in de app zelf',
                'description' => 'Bij een onderwerp in de handleiding kan een knop "Toon mij dit" staan. '
                    . 'Die opent het echte scherm en zet er een uitleg overheen: de rest van het scherm '
                    . 'wordt donker, het onderdeel waar het om gaat blijft zichtbaar en daarnaast staat in '
                    . 'een paar zinnen wat het doet. Met Volgende loop je er stap voor stap doorheen, en met '
                    . 'Sluiten stop je wanneer je wilt. Er wordt onderweg niets gewijzigd — het is meekijken, '
                    . 'geen doe-het-zelf. Op dit moment zijn er twee: een wedstrijd afgelasten en een '
                    . 'gastspeler uitnodigen.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Handleiding per doelgroep',
                'description' => 'Elk onderwerp in de handleiding heeft nu een doelgroep: iedereen, of alleen '
                    . 'coaches en leiders. Uitleg over knoppen die een speler niet heeft verdwijnt daarmee uit '
                    . 'zijn handleiding. De onderwerpen over het beheerportaal en de koppelingen staan meteen '
                    . 'op "alleen coaches en leiders"; de club kan dat per onderwerp aanpassen.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Strafschop: benut of gemist',
                'description' => 'Tik je in het live verslag op Strafschop, dan kies je eerst wie hem nam en '
                    . 'daarna of hij erin ging. Een gemiste strafschop komt met de naam van de nemer in de '
                    . 'tijdlijn te staan. Hij telt niet mee als doelpunt en ook niet als schot op doel, dus de '
                    . 'stand en de schotenteller kloppen. De vraag naar een assist is bij een strafschop '
                    . 'vervallen; die stond er alleen omdat een strafschop dezelfde route volgde als een gewoon '
                    . 'doelpunt.',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'Wedstrijd bleef op de oude stand staan na het live verslag',
                'description' => 'Sloot je het live verslag af, dan stonden de tabbladen van de wedstrijd nog '
                    . 'op de stand van daarvoor: geen eindstand, geen verslag. Pas als je de wedstrijd sloot en '
                    . 'opnieuw opende klopte het. Na Einde kom je nu op een verse wedstrijd uit, met alles er '
                    . 'meteen op.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Toegangscontrole bij activiteiten',
                'description' => 'Bij een activiteit in de agenda kun je nu toegang regelen. In de portal maak '
                    . 'of importeer je toegangscodes, die je als QR uitdeelt — per stuk of als PDF-vel met drie '
                    . 'kaartjes per rij om te knippen. Per code stel je in hoe vaak hij gebruikt mag worden, en '
                    . 'je ziet meelopen hoe vaak dat al gebeurd is. Bij de ingang scant iemand ze met de app: '
                    . 'groen scherm met een vink als de code geldig is, rood met een kruis als hij op is of niet '
                    . 'klopt. Onder Binnenkomsten staat wie er binnen is.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Je eigen toegangscode in de app',
                'description' => 'In het menu linksboven staat Mijn toegangscode: jouw lidnummer als QR-code. '
                    . 'Zet de club bij een activiteit "gratis voor leden" aan, dan kom je daarmee binnen — één '
                    . 'keer per activiteit. De code wordt op je eigen toestel getekend en werkt dus ook zonder '
                    . 'internet, wat in een zaal of kantine nogal eens uitmaakt. Lukt het scannen niet, dan staat '
                    . 'je lidnummer er in cijfers onder.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Nieuwe rol: Toegangscontrole',
                'description' => 'Een gebruiker kan de rol Toegangscontrole krijgen. Daarmee komt in de app onder '
                    . 'Meer de scanpagina beschikbaar, en in de portal het beheer van toegangscodes. Verder geeft '
                    . 'de rol nergens toegang toe — handig voor de vrijwilliger die alleen bij de deur staat.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Banners op trainingen en agenda',
                'description' => 'Een banner kan nu ook boven de trainingen- en agendapagina staan. In de portal '
                    . 'staan die twee gewoon tussen de plekken waar je een banner kunt tonen.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Ticketshop: kaarten online verkopen',
                'description' => 'Clubs kunnen nu kaarten verkopen voor een activiteit. Per activiteit stel je '
                    . 'kaartsoorten in met een eigen prijs en voorraad — volwassene, kind, vrijwilliger — en de '
                    . 'winkel staat op voetbalplanner.nl met de naam van je club in het adres. Betalen gaat via '
                    . 'Pay.nl, met je eigen account, dus het geld komt rechtstreeks bij de club binnen. De koper '
                    . 'krijgt zijn QR-codes per mail en die werken meteen bij de ingang: het zijn gewone '
                    . 'toegangscodes, met de naam van de koper erop. Alle bestellingen staan in de portal onder '
                    . 'Beheer, waar je ook de kaarten kunt bekijken en de mail opnieuw kunt sturen.',
            ],
            [
                'type'        => 'feature',
                'title'       => 'Ticketshop in je eigen website',
                'description' => 'De winkel is met één regel HTML in een WordPress-pagina te zetten. Bezoekers '
                    . 'kopen dan hun kaarten zonder je site te verlaten; het venster groeit vanzelf mee met de '
                    . 'inhoud. Het knipsel staat kant-en-klaar bij Instellingen → Ticketshop.',
            ],
            [
                'type'        => 'bugfix',
                'title'       => 'Kledingnummer kon niet worden opgeslagen',
                'description' => 'Een nummer invullen bij een kledingstuk gaf de melding dat het niet kon worden '
                    . 'opgeslagen. Het nummer hing aan de maat: stond er nog geen maat, dan was er ook niets om '
                    . 'het nummer aan te hangen. Maat en nummer staan nu los van elkaar, dus je kunt alleen een '
                    . 'nummer invullen. En de maat weghalen laat een ingevuld nummer voortaan staan.',
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
