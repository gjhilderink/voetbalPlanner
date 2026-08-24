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
