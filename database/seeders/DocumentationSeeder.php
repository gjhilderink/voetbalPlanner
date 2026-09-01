<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Documentation;
use Illuminate\Database\Seeder;

class DocumentationSeeder extends Seeder
{
    public function run(): void
    {
        $sections = [
            // ── De App ──────────────────────────────────────────────────────
            [
                'category'   => 'app',
                'sort_order' => 1,
                'title'      => 'Welkom bij VoetbalPlanner',
                'body'       => "VoetbalPlanner is een mobiele app voor leden en staf van jouw voetbalclub. Via de app heb je altijd en overal toegang tot het wedstrijdprogramma, de bardiensten en de teamchat.\n\nNa het inloggen zie je automatisch de informatie die hoort bij jouw elftal. De app past de kleuren aan op de huisstijl van jouw club.",
            ],
            [
                'category'   => 'app',
                'sort_order' => 2,
                'title'      => 'Inloggen',
                'body'       => "Leden loggen in via een magic link die per e-mail wordt verstuurd:\n1. Vul je e-mailadres in op het inlogscherm.\n2. Tik op \"Stuur inloglink\".\n3. Controleer je inbox en tik op de link in de e-mail.\n4. Je wordt automatisch ingelogd in de app.\n\nDe link is 15 minuten geldig. Heb je geen e-mail ontvangen? Controleer dan ook je spam-map of vraag een nieuwe link aan.\n\nBeheerders kunnen ook inloggen met e-mailadres en wachtwoord via de knop \"Inloggen beheerders\".\n\nNa de eerste keer inloggen kun je bij vervolgbezoeken gebruikmaken van Face ID of vingerafdruk (indien beschikbaar op jouw toestel).",
            ],
            [
                'category'   => 'app',
                'sort_order' => 3,
                'title'      => 'Wedstrijden',
                'body'       => "Op de wedstrijdenpagina zie je het programma van jouw elftal.\n\nStandaard worden alleen de aankomende wedstrijden getoond. Wil je het volledige seizoenprogramma zien, schakel dan de toggle \"Toon alle wedstrijden\" in.\n\nTik op een wedstrijd om de detailpagina te openen. Hier vind je:\n- Datum, tijd en locatie\n- Verzameltijd\n- Coach en Fruitheld\n- Opstelling (indien ingevoerd)\n- Doelpunten en eindstand\n- Notities van de coach",
            ],
            [
                'category'   => 'app',
                'sort_order' => 4,
                'title'      => 'Bardiensten',
                'body'       => "Op de bardienstenpagina zie je de bardiensten van jouw elftal. Per dienst staat vermeld:\n- Datum en dienst (ochtend, middag of avond)\n- Toegewezen leden\n- Status: Gepland, Bevestigd of Vervuld\n\nDe bardiensten worden gepland door de bardienst-commissie of clubbeheerder. Als jij of een teamgenoot is toegewezen aan een dienst, zie je dat hier terug.",
            ],
            [
                'category'   => 'app',
                'sort_order' => 5,
                'title'      => 'Teamchat',
                'body'       => "Via de teamchat wissel je berichten uit met alle leden van jouw elftal. De chat werkt in real-time.\n\nJouw eigen berichten verschijnen aan de rechterkant in de primaire clubkleur. Berichten van anderen verschijnen links, met de naam van de afzender erboven.\n\nStuur een bericht door een tekst te typen en op het verstuur-icoon te tikken. Je kunt ook directe berichten sturen naar individuele teamleden en in staffgroepen chatten via de Chats-pagina.\n\nJe ontvangt een push-melding op je telefoon bij nieuwe berichten (zie \"Meldingen op je telefoon\"). Hoor je bij meerdere teams, dan kies je op de Chats-pagina welk team je wilt openen (zie \"Meerdere teams & teamkeuze\").",
            ],
            [
                'category'   => 'app',
                'sort_order' => 6,
                'title'      => 'Profiel',
                'body'       => "Op de profielpagina zie je jouw persoonlijke gegevens: naam, e-mailadres en club.\n\nVia de uitlogknop kun je uitloggen. Je bent dan afgemeld totdat je opnieuw een magic link aanvraagt.\n\nOp de profielpagina vind je ook de link naar deze handleiding.",
            ],
            [
                'category'   => 'app',
                'sort_order' => 7,
                'title'      => 'Ouder / Verzorger Toegang',
                'body'       => "Via ouder/verzorger-toegang kunnen ouders of verzorgers de teamgegevens van hun kind(eren) inzien in de app.\n\n**Koppeling aanvragen (ouder/verzorger)**\n1. Ga naar Ouder / Verzorger via het menu of profielpagina.\n2. Tik op het + icoontje bij \"Mijn kinderen\".\n3. Vul in het formulier het lidnummer, de achternaam en de geboortedatum van het kind in.\n4. Tik op \"Koppeling aanvragen\".\n\nHet kind ontvangt een melding in de app en moet de koppeling bevestigen. Het verzoek verloopt automatisch na 14 dagen als het kind niet reageert.\n\n**Verzoek bevestigen of weigeren (kind/lid)**\nAls er een openstaand verzoek is, zie je dit direct na het inloggen:\n- Tik op \"Accepteren\" om de ouder/verzorger toegang te geven.\n- Tik op \"Weigeren\" om het verzoek af te wijzen.\n\nNa acceptatie kan de ouder/verzorger via de app de wedstrijden, bardiensten en teamchat van het kind bekijken.\n\n**Koppeling intrekken**\nBoth het kind én de ouder/verzorger kunnen de koppeling zelf intrekken via de Ouder / Verzorger pagina. Een beheerder kan dit ook namens hen doen.\n\n**Beveiliging**\nVoor het aanvragen van een koppeling zijn drie gegevens vereist: lidnummer, achternaam én geboortedatum. Dit voorkomt dat willekeurige leden gevonden kunnen worden.",
            ],
            [
                'category'   => 'app',
                'sort_order' => 30,
                'title'      => 'Hamburger menu',
                'body'       => "Linksboven in elke pagina vind je het hamburger-icoon (drie streepjes). Tik erop om het hoofdmenu te openen.\n\nBovenaan zie je je eigen profielinformatie: profielfoto, naam en e-mailadres. Daaronder staan de volgende navigatie-items:\n- Home — terug naar het dashboard\n- Nieuws — laatste nieuwsberichten van de club\n- Handleiding — deze pagina\n- Profiel — je persoonlijke gegevens en instellingen\n- Bug melden — een probleem of suggestie doorgeven\n\nTik op een item om er direct naartoe te navigeren. Het menu sluit automatisch.\n\nDe onderbalk met iconen blijft ook altijd zichtbaar voor snel wisselen tussen de hoofdpagina's: Dashboard, Wedstrijden, Bardiensten, Rijschema, Chats en Profiel.",
            ],
            [
                'category'   => 'app',
                'sort_order' => 31,
                'title'      => 'Nieuwsfeed',
                'body'       => "Via het menu → Nieuws zie je alle gepubliceerde clubnieuws. Per artikel vind je:\n- Titel van het bericht\n- Hoeveel dagen oud het bericht is (Vandaag / N dag(en) geleden)\n- Categorie-label: Jeugd, Senioren of Algemeen\n- Eventuele afbeelding\n- Volledige inhoud van het bericht\n\nDe meest recente artikelen staan bovenaan. Nieuwsberichten worden beheerd door clubbeheerders via het platform.",
            ],
            [
                'category'   => 'app',
                'sort_order' => 32,
                'title'      => 'Ongelezen chats — rood bolletje',
                'body'       => "Boven het chat-icoon in de onderbalk verschijnt een rood bolletje met een getal zodra je nieuwe ongelezen berichten hebt. Het getal toont het totaal aantal ongelezen chats — over alle gesprekken heen (team, direct én groepen).\n\nDe teller wordt live bijgewerkt: zodra iemand een bericht stuurt verschijnt het rondje vanzelf. Zodra je een gesprek opent en de berichten gelezen markeert verdwijnt het bolletje weer (of telt het lager als er nog ongelezen gesprekken zijn).\n\nDe teller blijft ook actief op andere pagina's — je hoeft de chat-pagina niet zelf eerst te openen om de melding te ontvangen.",
            ],
            [
                'category'   => 'app',
                'sort_order' => 33,
                'title'      => 'Profielfoto uploaden',
                'body'       => "Op de profielpagina kun je een eigen foto instellen. Tik op de cirkel met je initialen en kies een afbeelding uit de galerij.\n\nDe foto wordt automatisch verkleind en opgeslagen op de server. Je profielfoto verschijnt daarna op meerdere plekken in de app, waaronder in het hamburger-menu en op je profielpagina.\n\nDe upload werkt voor zowel gewone leden als beheerders/coaches. Maximumgrootte: 5 MB. Toegestane formaten: JPG, PNG, WEBP.",
            ],
            [
                'category'   => 'app',
                'sort_order' => 34,
                'title'      => 'Bardienst — zelf aanmelden',
                'body'       => "Op de detailpagina van een bardienst zie je onderaan een knop **Aanmelden** zolang er nog plek vrij is. Tik erop om jezelf toe te voegen aan de dienst.\n\nVoorwaarden:\n- Je bent lid van het team van de bardienst (of de dienst is club-breed gepland)\n- Er zijn nog plekken vrij (maximaal 2 leden per dienst)\n- Je bent nog niet aangemeld\n\nNa succesvol aanmelden:\n- Je naam verschijnt direct in de leden-lijst van die dienst (groen gemarkeerd, zie hieronder)\n- De status springt van \"Open\" naar \"Bevestigd\" zodra het maximum bereikt is\n- De Aanmelden-knop verdwijnt\n\nVoor uitschrijven of het toewijzen van anderen schakel je de bardienst-commissie of clubbeheerder in.",
            ],
            [
                'category'   => 'app',
                'sort_order' => 35,
                'title'      => 'Eigen naam herkennen in lijsten',
                'body'       => "In de leden-rij op de bardienst-detail én in de rijders-rij op de rijschema-detail wordt jouw eigen naam visueel gemarkeerd met een groen pill-label en een persoon-icoontje.\n\nHandig om in één oogopslag te zien of jij ingedeeld bent voor een dienst of rit. Andere namen blijven in normale tekst.",
            ],
            [
                'category'   => 'app',
                'sort_order' => 36,
                'title'      => 'Bug melden',
                'body'       => "Via menu → Bug melden geef je problemen, vragen of suggesties door aan de ontwikkelaars.\n\nVul in:\n- Een korte titel (waar gaat het over?)\n- Een omschrijving (wat ging er mis of wat verwacht je?)\n- Voeg optioneel schermafbeeldingen toe (max 5 stuks, max 5 MB per stuk)\n\nDe app stuurt automatisch je app-versie, platform (Android/iOS/web) en apparaatinfo mee — zo kunnen wij het probleem sneller reproduceren. Je krijgt een bevestiging zodra de melding succesvol is verstuurd.",
            ],

            [
                'category'   => 'app',
                'sort_order' => 37,
                'title'      => 'Meldingen op je telefoon (push)',
                'body'       => "Je ontvangt een push-melding op je telefoon zodra iemand je een chatbericht stuurt — in de teamchat, een direct bericht of een staffgroep. Zo mis je niets, ook als de app dicht is.\n\nDe eerste keer dat je een chat opent vraagt de app toestemming voor meldingen. Geef die toestemming om push-notificaties te ontvangen.\n\nTik op een melding om direct naar het juiste gesprek te gaan.\n\nMeldingen werken op Android en iOS (niet in de browser-/webversie).",
            ],
            [
                'category'   => 'app',
                'sort_order' => 38,
                'title'      => 'Meerdere teams & teamkeuze',
                'body'       => "Hoor je bij meerdere teams — bijvoorbeeld als ouder met kinderen in verschillende elftallen, of als coach/staflid van meer dan één team — dan wissel je eenvoudig in de app.\n\nOp de Chats-pagina zie je onder \"Teamchat\" een keuze met al jouw teams. Tik op een team om de teamchat van dat team te openen; de app schakelt dan ook de rest (wedstrijden, bardiensten) naar dat team.\n\nHoor je maar bij één team, dan zie je gewoon dat ene team — er verandert niets. Heb je (nog) geen team gekoppeld, dan wordt de teamchat-ingang niet getoond.",
            ],
            [
                'category'   => 'app',
                'sort_order' => 39,
                'title'      => 'Badge op het app-icoon',
                'body'       => "Naast het rode bolletje in de app toont ook het app-icoon op je startscherm een badge met het aantal ongelezen chatberichten. Zo zie je in één oogopslag of er nieuwe berichten zijn, zonder de app te openen.\n\nDe badge telt alle ongelezen chats samen (team, direct én groepen) en verdwijnt zodra je alles gelezen hebt.\n\nDit werkt op iOS en op Android-toestellen waarvan de launcher app-badges ondersteunt.",
            ],

            [
                'category'   => 'app',
                'sort_order' => 40,
                'title'      => 'Snelmenu (+ knop op het dashboard)',
                'body'       => "Op het dashboard (Home) staat rechtsonder een blauwe **+** knop. Tik erop voor een menu met snelle acties dat van onderen omhoog schuift (bottom sheet):\n- **Chatten** — opent direct de Chats-pagina.\n- **Wissel bardienst** — opent de bardienstenlijst; kies daar de dienst waarvoor je wilt ruilen en vraag de wissel aan.\n- **Wissel rijden** — opent het rijschema; kies de rit waarvoor je wilt ruilen.\n- **Afmelden wedstrijd** — opent de wedstrijdenlijst; open de betreffende wedstrijd om je af te melden.\n\nTik naast het menu of veeg het omlaag om het te sluiten. De concrete wissel- of afmeldstap doorloop je op de pagina waar je terechtkomt; de + knop is enkel de snelkoppeling erheen.",
            ],
            [
                'category'   => 'app',
                'sort_order' => 41,
                'title'      => 'Toon mij dit — uitleg in de app zelf',
                'body'       => "Bij sommige onderwerpen in deze handleiding staat onderaan een knop **Toon mij dit**. Die laat het niet zien op een plaatje, maar in de app zelf.\n\nWat er gebeurt: de app opent het scherm waar het over gaat en legt er een uitleg overheen. De rest van het scherm wordt donker, het onderdeel waar het om draait blijft zichtbaar, en daarnaast staat in een paar zinnen wat het doet. Met **Volgende** loop je er stap voor stap doorheen, met **Vorige** ga je terug en met **Sluiten** stop je wanneer je wilt. Een tik naast de uitleg werkt ook als Volgende.\n\nEr wordt onderweg niets gewijzigd. Je kijkt mee; de knoppen die worden aangewezen doen niets zolang de uitleg loopt. Zo kun je rustig een keer zien hoe iets werkt zonder dat er per ongeluk een wedstrijd wordt afgelast.\n\nTwee dingen om te weten:\n- Niet elk onderwerp heeft zo'n knop. Hij staat er alleen als er een rondleiding aan gekoppeld is.\n- De uitleg draait op een echte wedstrijd van je eigen elftal. Staat er op dit moment geen wedstrijd gepland, of gaat de uitleg over knoppen die jij niet hebt, dan krijg je daar een melding over en gebeurt er verder niets.",
            ],
            [
                'category'   => 'app',
                'audience'   => 'staff',
                'sort_order' => 42,
                'title'      => 'Een wedstrijd afgelasten',
                'tour_id'    => 'wedstrijd_afgelasten',
                'body'       => "Gaat een wedstrijd niet door, dan kun je hem als coach of leider afgelasten. Open de wedstrijd; onderaan het Info-tabblad staan de knoppen.\n\n1. Vul eerst een **reden** in. Dat is hetzelfde veld als bij afmelden, met het label \"Reden (bij afmelden)\". Zonder reden kun je niet afgelasten, en iedereen krijgt die tekst te zien.\n2. Tik op **Wedstrijd afgelasten**. Het gaat meteen door; er komt geen extra bevestiging.\n\nDaarna staat bovenaan de wedstrijd een rode melding met jouw reden, en ook op het dashboard is bij de komende activiteiten te zien dat de wedstrijd niet doorgaat.\n\nGaat hij toch door? Met **Toch laten doorgaan** draai je het terug. De melding verdwijnt en de wedstrijd staat er weer gewoon bij.\n\nDezelfde knoppen zitten op een training, via de Trainingen-pagina of via de training op het dashboard.",
            ],
            [
                'category'   => 'app',
                'audience'   => 'staff',
                'sort_order' => 43,
                'title'      => 'Een gastspeler uitnodigen',
                'tour_id'    => 'gastspeler_uitnodigen',
                'body'       => "Kom je spelers tekort, dan nodig je een speler uit een ander elftal van de club uit.\n\n1. Open de wedstrijd en tik rechtsonder op de **plusknop**. Daar staan alle acties voor deze wedstrijd bij elkaar.\n2. Kies **Gastspeler uitnodigen**.\n3. Kies eerst het **elftal** waar de speler uit komt. Je ziet alle elftallen van de club.\n4. Zoek de speler op naam — bij een grote club scheelt dat een hoop scrollen — en tik hem aan.\n5. Tik op **Gastspeler toevoegen**.\n\nDe gast staat daarna meteen onderaan je selectie met het label **Gast**, dus je kunt hem direct indelen in de opstelling. Er is geen accepteerstap: hij hoeft niets te doen. Hij krijgt wel een melding op zijn telefoon en ziet de wedstrijd in zijn eigen app.\n\nAlleen leden van de eigen club zijn uit te nodigen; iemand op e-mailadres uitnodigen kan niet.",
            ],
            [
                'category'   => 'app',
                'audience'   => 'staff',
                'sort_order' => 44,
                'title'      => 'Live verslag bijhouden',
                'body'       => "Tijdens de wedstrijd kun je een live verslag bijhouden. Iedereen die de wedstrijd volgt ziet de stand en de gebeurtenissen meelopen, ook familie zonder app via een deelbare link.\n\nOp de wedstrijd staat **Start live verslag**. Is er al een verslag gestart, dan staat er **Open live verslag** — zo kunnen twee coaches het samen bijhouden. Boven in beeld zie je hoeveel mensen meekijken.\n\nWat je kunt vastleggen: doelpunt (met of zonder assist), tegendoelpunt, strafschop, schot op doel, gele en rode kaart, wissel, rust en tweede helft.\n\n**Strafschop.** Je kiest eerst wie hem nam en daarna of hij erin ging: **Benut** of **Gemist**. Een gemiste strafschop komt met naam in de tijdlijn te staan, maar telt niet mee als doelpunt en ook niet als schot op doel.\n\n**Vergist?** Met **Ongedaan** haal je de laatste gebeurtenis weg. Heb je halverwege een verkeerde keuze gemaakt, dan staat er in elk keuzescherm een **Annuleren**-knop.\n\nAan het eind tik je op **Einde**. Je komt dan terug op de wedstrijd, met de eindstand en het verslag er meteen op.",
            ],

            [
                'category'   => 'app',
                'sort_order' => 45,
                'title'      => 'Mijn toegangscode',
                'body'       => "In het menu linksboven staat **Mijn toegangscode**. Daar vind je jouw persoonlijke QR-code: dat is je lidnummer, in een vierkantje dat bij de ingang gescand kan worden.\n\nDe code staat altijd klaar, ook zonder internet — hij wordt op je eigen toestel getekend. Handig, want in een zaal of kantine is de ontvangst nogal eens matig.\n\n**Waar werkt hij?**\nBij activiteiten waarvan de club heeft aangezet dat leden gratis naar binnen mogen. Staat dat niet aan, dan heb je een gewone toegangscode van de club nodig; die krijg je dan apart.\n\n**Eén keer per activiteit.** Je code wordt bij de ingang afgestreept. Laat je hem daarna nog een keer scannen, dan komt hij als \"al binnen\" naar voren. Geef hem dus niet door aan iemand anders — dan sta jij zelf voor een dichte deur.\n\nLukt het scannen niet, bijvoorbeeld door een gebarsten scherm? Onder de code staat je lidnummer ook in cijfers; dat kan degene bij de ingang overnemen.\n\nZie je geen code maar een melding dat er nog geen lidnummer aan je account hangt, neem dan even contact op met de club.",
            ],
            [
                'category'   => 'app',
                'audience'   => 'staff',
                'sort_order' => 46,
                'title'      => 'Toegangscontrole bij de ingang',
                'body'       => "Heb je van de club de rol **Toegangscontrole** gekregen, dan staat er onder **Meer** een knop Toegangscontrole. Daarmee scan je bij de ingang.\n\n**Zo werkt het**\n1. Open Toegangscontrole. Je ziet welke activiteiten er te controleren zijn, met per activiteit hoeveel mensen er al binnen zijn.\n2. Tik de juiste activiteit aan. Dat is belangrijk: een code van gisteravond hoort vandaag niet meer te werken, en die controle gebeurt op de activiteit die jij hier kiest.\n3. Houd de camera voor de code.\n\n**Groen met een vink**: laat maar door. **Rood met een kruis**: niet doorlaten, met de reden erbij — al gebruikt, ingetrokken, of geen geldige code.\n\nNa anderhalve seconde is het scherm weer klaar voor de volgende. Dezelfde code twee keer achter elkaar voor de lens houden doet niets; dat is met opzet, anders zou een code die vaker mag meteen opgebruikt zijn.\n\nZit je bij de verkeerde activiteit? Tik onderin op **Wisselen**.\n\n**Twee soorten codes** komen langs en zien er voor jou hetzelfde uit: de codes die de club heeft uitgedeeld, en het lidnummer uit de app van een lid. Dat laatste werkt alleen als de club voor die activiteit heeft aangezet dat leden gratis naar binnen mogen. Bij een lid zie je zijn naam in het groene scherm staan.\n\n**Vergissing?** Een verkeerd afgestreepte code zet je in de portal terug: bij Toegangscodes staat per code een knop om de teller terug te zetten. Ging het om een lid, dan haal je de regel weg bij Binnenkomsten.",
            ],
            // ── Het Platform ─────────────────────────────────────────────────
            [
                'category'   => 'platform',
                'sort_order' => 10,
                'title'      => 'Het Platform',
                'body'       => "Het VoetbalPlanner platform is het beheersysteem achter de app. Beheerders, coaches en commissieleden loggen in via de browser op het admin-adres van de club (bijv. jouwclub.voetbalplanner.nl/admin).\n\nVia het platform beheer je de volledige cluborganisatie: leden, teams, wedstrijden, bardiensten, instellingen en documentatie. Wijzigingen zijn direct zichtbaar in de app.",
            ],
            [
                'category'   => 'platform',
                'sort_order' => 11,
                'title'      => 'Club Instellingen',
                'body'       => "Via Instellingen stel je de identiteit van de club in.\n\nHuisstijl:\n- Primaire kleur: hoofdkleur voor knoppen, appbalk en e-mailkop\n- Secundaire kleur: ondersteunende kleur voor kaarten en achtergronden\n- Accentkleur: kleur voor highlights, badges en call-to-action elementen\n\nDe drie kleuren worden automatisch overgenomen in de app na het inloggen van leden.\n\nE-mail sjabloon:\n- Onderwerp: onderwerpregel van de inlogmail (leeg = standaard)\n- Koptekst: de aanhef bovenin de verificatie-e-mail\n- Introductietekst: aanvullende uitleg in de e-mail\n- Voettekst: tekst onderaan de e-mail (bijv. contactgegevens)\n\nVariabelen in het onderwerp:\nIn het veld E-mail onderwerp kun je variabelen gebruiken die automatisch worden vervangen. Schrijf de variabele tussen accolades.\n- {club_naam} — de naam van de club\n- {ontvanger_naam} — de naam van het lid dat de mail ontvangt\n- {app_naam} — de naam van de app (VoetbalPlanner)\n\nVoorbeelden:\n- \"Inloggen bij {club_naam}\" → \"Inloggen bij VV De Kanjers\"\n- \"Hoi {ontvanger_naam}, je inloglink\" → \"Hoi Jan de Vries, je inloglink\"\n- \"{club_naam} — inloggen\" → \"VV De Kanjers — inloggen\"\n\nSla de instellingen op via de knop \"Opslaan\" rechtsonder.",
            ],
            [
                'category'   => 'platform',
                'sort_order' => 12,
                'title'      => 'Teams & Leden',
                'body'       => "Teams zijn de elftallen van de club. Per team leg je vast:\n- Naam en eventuele externe club-ID (voor synchronisatie)\n- Coaches (koppeling aan gebruikers met de rol Coach)\n\nLeden zijn de spelers en stafleden. Per lid sla je op:\n- Naam, e-mailadres en telefoonnummer\n- Geboortedatum\n- Koppeling aan een of meerdere teams\n\nLeden importeren: via de knop \"Importeren\" op de ledenpagina kun je een Excel-bestand uploaden om leden in bulk toe te voegen.\n\nEen lid dat ook toegang nodig heeft tot het platform, koppel je via Gebruikers aan de juiste rol.",
            ],
            [
                'category'   => 'platform',
                'sort_order' => 13,
                'title'      => 'Wedstrijdbeheer',
                'body'       => "Via de wedstrijdenmodule beheer je alle wedstrijden van het seizoen.\n\nPer wedstrijd stel je in:\n- Datum, tijd en locatie\n- Tegenstander en thuis/uit\n- Status: Gepland, Gespeeld, Geannuleerd of Uitgesteld\n- Verzameltijd, coach en fruitheld\n- Opstelling en doelpunten (na de wedstrijd)\n- Notities voor het team\n\nWedstrijden zijn na opslaan direct zichtbaar in de app voor de leden van het betreffende elftal. Coaches kunnen de opstelling en wedstrijdgegevens ook vanuit de app bijwerken.",
            ],
            [
                'category'   => 'platform',
                'sort_order' => 14,
                'title'      => 'Bardiensten Beheer',
                'body'       => "Via de bardiensten-module plan je de kantinediensten voor het seizoen.\n\nEen nieuwe bardienst aanmaken:\n1. Kies datum, dienst (ochtend / middag / avond) en eventueel een elftal.\n2. Wijs optioneel direct leden toe.\n3. Sla op.\n\nLeden toewijzen: open een bardienst en gebruik \"Leden koppelen\" om leden toe te wijzen.\n\nStatus verloop:\n- Open: geen leden toegewezen\n- Bevestigd: leden zijn toegewezen\n- Vervuld: de dienst is uitgevoerd",
            ],
            [
                'category'   => 'platform',
                'sort_order' => 15,
                'title'      => 'Gebruikers & Rollen',
                'body'       => "VoetbalPlanner werkt met de volgende rollen:\n\nSuper Admin — volledige toegang tot alle clubs en instellingen.\nClub Admin — beheert de eigen club volledig (teams, leden, wedstrijden, bardiensten, instellingen).\nBar Commissie — beheert bardiensten en kan leden toewijzen.\nCoach — beheert wedstrijden en opstelling van het eigen team.\nMember — alleen leestoegang via de mobiele app (geen toegang tot het platform).\n\nGebruikers aanmaken: via Gebruikers > Nieuw koppel je een e-mailadres aan de juiste rol.\n\nImiteren (impersoneren): als Super Admin kun je via het gebruikersmenu inloggen als een andere gebruiker om hun omgeving te controleren.",
            ],
            [
                'category'   => 'platform',
                'sort_order' => 15,
                'title'      => 'Ouder / Verzorger Koppelingen Beheren',
                'body'       => "Beheerders kunnen ouder/verzorger-koppelingen inzien en beheren.\n\nKoppelingen verlopen automatisch:\n- Een verzoek dat niet binnen 14 dagen door het kind is bevestigd, krijgt automatisch de status \"Geweigerd\" (via dagelijkse cron: php artisan guardian:expire).\n\nStatussen:\n- In afwachting: verzoek is verzonden, kind heeft nog niet gereageerd\n- Goedgekeurd: kind heeft de koppeling bevestigd; ouder heeft toegang\n- Geweigerd: kind heeft geweigerd of verzoek is verlopen\n- Ingetrokken: koppeling is beëindigd door kind, ouder of beheerder\n\nKoppeling intrekken als beheerder:\nGebruik de API-route DELETE /api/v1/guardian/{id}/revoke met een geldig admin-token. Beheerders met de rol super_admin of club_admin mogen elke koppeling binnen hun club intrekken.\n\nBeveiliging:\n- Drie-veld verificatie voorkomt dat willekeurige leden gevonden kunnen worden\n- Rate limiting: maximaal 5 verzoeken per minuut per account\n- Club-scoping: een ouder kan alleen leden zoeken binnen de eigen club",
            ],
            [
                'category'   => 'platform',
                'sort_order' => 16,
                'title'      => 'Nieuws Beheren',
                'body'       => "Via Communicatie → Nieuws beheer je alle nieuwsberichten die in de app worden getoond.\n\nNieuw bericht aanmaken:\n1. Tik op \"New nieuwsitem\" rechtsboven.\n2. Vul de titel in (verplicht, max 200 tekens).\n3. Kies een categorie: Jeugd, Senioren of Algemeen.\n4. Stel een publicatiedatum in — berichten met een toekomstige datum verschijnen pas vanaf dat moment.\n5. Upload optioneel een afbeelding (max 5 MB, jpg/png/webp).\n6. Schrijf de inhoud van het bericht.\n7. Toggle \"Gepubliceerd\" aan/uit naar wens (uit = bewerkbaar maar niet zichtbaar in de app).\n\nIn de app verschijnt het bericht direct bij iedereen van de club. De \"hoeveel dagen oud\"-tekst wordt automatisch berekend op basis van de publicatiedatum.",
            ],
            [
                'category'   => 'platform',
                'sort_order' => 17,
                'title'      => 'Bug Meldingen',
                'body'       => "Via Support → Bug meldingen zie je alle problemen die door gebruikers via de \"Bug melden\"-knop in de app zijn doorgegeven.\n\nPer melding zie je:\n- Titel en uitgebreide omschrijving\n- Schermafbeeldingen (klik om uit te vergroten)\n- App-versie, platform en apparaat-info — handig voor reproduceren\n- Wie de melding heeft ingestuurd\n\nWerkproces:\n- Status zet je op \"Open\" → \"In behandeling\" → \"Opgelost\" of \"Gesloten\"\n- Interne notities zijn alleen zichtbaar voor admins\n- Meldingen worden niet automatisch verwijderd; archiveer ze via Gesloten\n\nDe Bug-meldingen-resource is beschikbaar voor super_admin en club_admin rollen.",
            ],
            [
                'category'   => 'platform',
                'sort_order' => 18,
                'title'      => 'Documentatie Beheren',
                'body'       => "Via Documentatie in het platform beheer je de handleiding die leden in de app zien.\n\nSecties zijn ingedeeld in drie categorieën:\n- De App: uitleg over het gebruik van de app\n- Het Platform: uitleg voor beheerders\n- Koppelingen: technische uitleg over de integraties\n\nEen sectie aanpassen: open de sectie en bewerk de titel of inhoud. Gebruik de volgorde om de weergavevolgorde in de app te bepalen.\n\n**Per sectie aan/uit zetten in de app**\nElke sectie heeft een toggle \"Tonen in app\". Zet 'm uit als je een sectie tijdelijk niet wilt tonen — de inhoud blijft hier wel bewerkbaar en je kunt 'm later weer aanzetten. In het overzicht zie je per regel een groene/grijze indicator in de kolom \"In app\".\n\n**Voor wie is de sectie?**\nBij Voor wie kies je tussen \"Iedereen\" en \"Alleen coaches en leiders\". Kies je het tweede, dan krijgen spelers en ouders de sectie helemaal niet te zien — ook niet als ze de app doorspitten. Bedoeld voor uitleg over knoppen die zij toch niet hebben; dat is voor hen alleen maar verwarrend. Het platform en de koppelingen staan hier standaard op.\n\n**Rondleiding koppelen**\nBij Rondleiding koppel je een sectie aan een uitleg in de app zelf. Staat er een gekozen, dan verschijnt onder die sectie de knop \"Toon mij dit\", die het echte scherm opent met de uitleg eroverheen. Met Beginnen bij stap laat je een sectie halverwege instappen — handig als twee secties dezelfde rondleiding delen maar over een ander stuk gaan. Geen rondleiding gekozen betekent geen knop.\n\n**Alleen super_admins** kunnen secties aanmaken, bewerken of verwijderen. Club_admins zien de handleiding in alleen-lezen.",
            ],

            [
                'category'   => 'platform',
                'sort_order' => 19,
                'title'      => 'Toegangscodes Beheren',
                'body'       => "Bij een activiteit in de agenda kun je toegangscontrole regelen. Twee dingen los van elkaar: uitgedeelde codes, en de persoonlijke code van leden.\n\n**Gratis voor leden**\nOpen het agenda-item en zet onder Toegangscontrole de schakelaar **Gratis voor leden** aan. Vanaf dat moment werkt het lidnummer van elk clublid bij de ingang, één keer per activiteit. Leden vinden hun eigen QR in de app onder Mijn toegangscode; jij hoeft ze niets te sturen.\n\n**Codes uitdelen**\nGa naar **Toegangscodes** en kies de activiteit. Je hebt drie manieren om aan codes te komen — vier als de ticketshop aanstaat, want dan komen verkochte kaarten er vanzelf bij, met de naam van de koper erop:\n- **Codes genereren** — zeg hoeveel je er wilt en hoe vaak elke code mag worden gebruikt. De app verzint leesbare codes zonder 0, O, 1 en I, want die haalt niemand foutloos over.\n- **Importeren** — een Excel-bestand met één kolom `code`. Een kolom `omschrijving` mag erbij, bijvoorbeeld een naam. Codes die al bij deze activiteit staan worden overgeslagen, dus je kunt dezelfde lijst gerust nog eens inlezen om er wat aan toe te voegen.\n- **Code toevoegen** — één losse code met de hand.\n\n**Uitdelen**\nMet **PDF-vel** krijg je alle codes van een activiteit als QR's op A4, drie per rij, met stippellijnen om te knippen. Wil je er één vervangen, dan open je die code en gebruik je de downloadknop bij de QR.\n\n**Tijdens en na afloop**\nIn de kolom Gebruikt zie je per code hoe vaak hij is gescand; hij kleurt rood zodra hij op is. Onder **Binnenkomsten** staat wie er binnen is, met naam bij een lid en de code of omschrijving bij een uitgedeelde code.\n\n**Iets rechtzetten?** Een code die per ongeluk is afgestreept zet je terug met de knop Teller terugzetten. Bij een lid haal je de regel weg bij Binnenkomsten; daarna kan hij weer naar binnen. Een code die helemaal niet meer mag werken zet je op niet-actief — dan blijft hij wel in het overzicht staan.\n\n**Wie mag dit?** Superbeheerders, clubbeheerders, en gebruikers met de rol **Toegangscontrole**. Die rol ken je toe bij Gebruikers; hij geeft alleen toegang tot toegangscodes en het scannen in de app, verder tot niets.",
            ],
            [
                'category'   => 'platform',
                'sort_order' => 20,
                'title'      => 'Ticketshop',
                'body'       => "Met de ticketshop verkoop je online kaarten voor een activiteit. De koper betaalt met iDEAL en krijgt zijn QR-codes per mail; die werken meteen bij de ingang, want het zijn gewone toegangscodes.\n\n**Aanzetten**\nGa naar Instellingen → Modules. Toegangscontrole moet aanstaan — een verkocht kaartje ís een toegangscode, dus zonder dat is er niets te verkopen. Zet daarna Ticketshop aan.\n\n**Pay.nl koppelen**\nOnder Instellingen → Ticketshop vul je de gegevens van je eigen Pay.nl-account in: het service-ID (SL-…), de tokencode (AT-…) en het API-token. Het geld komt rechtstreeks op de rekening van de club binnen; VoetbalPlanner zit er niet tussen.\n\nLaat **Testmodus** aan zolang je aan het proberen bent. Dan wordt er niets echt afgeschreven. Zet hem uit op het moment dat je echt gaat verkopen — dat is één vinkje en het is zo vergeten.\n\nHet API-token wordt versleuteld bewaard en nooit teruggetoond. Wil je het later niet wijzigen, laat het veld dan leeg; opslaan met een leeg veld laat het staan zoals het was.\n\n**Kaarten klaarzetten**\nOpen het agenda-item en ga naar het tabblad Kaartsoorten. Per soort geef je een naam, een prijs, hoeveel er zijn en hoeveel iemand er in één bestelling mag kopen. Bijvoorbeeld: Volwassene € 7,50 (120 stuks), Kind t/m 12 € 3,50 (40 stuks).\n\nVoorraad leeglaten betekent onbeperkt; nul betekent uitverkocht. Zolang er geen kaartsoort is, staat de activiteit niet in de winkel. Een soort waarvan al kaarten verkocht zijn kun je niet verwijderen — zet hem op niet te koop.\n\n**Je winkel**\nHet adres staat bij Instellingen → Ticketshop en ziet eruit als voetbalplanner.nl/jouw-club/ticketshop. Die link kun je overal delen.\n\nWil je de winkel in je eigen website? Daar staat ook een regel HTML die je in een WordPress-pagina plakt. De winkel verschijnt dan in je eigen site en groeit vanzelf mee in hoogte.\n\n**Bestellingen**\nOnder Beheer → Bestellingen zie je alles: wie, wat, hoeveel, en of er betaald is. Per bestelling kun je de kaarten met QR bekijken — handig als iemand belt omdat zijn mail niet is aangekomen — en de mail opnieuw sturen.\n\nEen bestelling intrekken zet de kaarten op niet-actief; ze worden dan bij de ingang geweigerd. **Het geld terugstorten doe je bij Pay.nl zelf**; dat gebeurt hier niet.\n\n**Wat gebeurt er bij een afgebroken betaling?**\nDe kaarten blijven een half uur gereserveerd en komen daarna vanzelf weer vrij. De bestelling blijft zichtbaar met de status Verlopen, zodat je kunt terugkijken als iemand belt.",
            ],
            // ── Koppelingen ───────────────────────────────────────────────────
            [
                'category'   => 'koppelingen',
                'sort_order' => 20,
                'title'      => 'App ↔ Platform Koppeling',
                'body'       => "De mobiele app communiceert via een beveiligde REST API met het Laravel-platform.\n\nAuthenticatie: na het inloggen ontvangt de app een persoonlijk toegangstoken (Sanctum Bearer Token). Dit token wordt meegestuurd bij elk verzoek aan de API via de Authorization-header.\n\nGegevensuitwisseling: wedstrijden, bardiensten en profieldata worden via API-endpoints opgehaald. De app slaat het token en basisgegevens (naam, club, kleuren) lokaal op.\n\nBeveiliging: het token is uniek per apparaat en per sessie. Bij uitloggen wordt het token ongeldig gemaakt op de server.",
            ],
            [
                'category'   => 'koppelingen',
                'sort_order' => 21,
                'title'      => 'E-mail & Magic Link',
                'body'       => "Leden loggen in via een magic link die per e-mail wordt verstuurd.\n\nHoe het werkt:\n1. Het lid vult zijn/haar e-mailadres in de app in.\n2. De app stuurt een verzoek naar het platform.\n3. Het platform genereert een tijdelijk token (geldig 15 minuten) en verstuurt een e-mail met de inloglink.\n4. Het lid klikt op de link; de app verifieert het token en is direct ingelogd.\n\nE-mail opmaak: de stijl van de e-mail (kleuren, koptekst, introductie, voettekst) is per club instelbaar onder Instellingen in het platform.",
            ],
            [
                'category'   => 'koppelingen',
                'sort_order' => 22,
                'title'      => 'Sportlink Synchronisatie',
                'body'       => "Teams, leden en wedstrijden kunnen automatisch worden gesynchroniseerd vanuit Sportlink via de MCP-koppeling.\n\nInstellen: ga naar Instellingen in het platform en vul de MCP URL en API-sleutel in. Deze zijn per club opgeslagen.\n\nHandmatig synchroniseren: ga naar Sync in het admin-menu en kies het gewenste onderdeel (teams, leden, wedstrijden) of gebruik \"Alles synchroniseren\".\n\nSynchistorie: via Sync-status zie je wanneer de laatste synchronisatie is uitgevoerd en of er fouten zijn opgetreden.",
            ],
            [
                'category'   => 'koppelingen',
                'sort_order' => 23,
                'title'      => 'Chat (Firebase)',
                'body'       => "De teamchat werkt via Firebase Firestore, een real-time database van Google.\n\nBerichten worden opgeslagen in de Firestore-collectie \"teamChats\" met de velden: tekst, afzender-ID, afzendernaam, team-ID en tijdstempel.\n\nReal-time updates: nieuwe berichten worden automatisch zichtbaar zonder dat de pagina hoeft te worden ververst.\n\nDirecte berichten tussen leden worden opgeslagen in de collectie \"directMessages\".\n\nVereiste configuratie: een actief Firebase-project met de juiste Firestore-beveiligingsregels en de app-configuratie in de FlutterFlow-projectinstellingen.",
            ],
            [
                'category'   => 'koppelingen',
                'sort_order' => 25,
                'title'      => 'Ouder / Verzorger API',
                'body'       => "De ouder/verzorger-koppeling verloopt volledig via de Laravel REST API. De relaties worden centraal opgeslagen in de tabel guardian_links.\n\nEndpoints (allemaal beveiligd met Bearer Token):\n- POST   /api/v1/guardian/request           — Verzoek indienen (ouder)\n- GET    /api/v1/guardian/pending            — Openstaande verzoeken ophalen (kind)\n- POST   /api/v1/guardian/{id}/respond       — Accepteren of weigeren (kind)\n- DELETE /api/v1/guardian/{id}/revoke        — Koppeling intrekken\n- GET    /api/v1/guardian/children           — Gekoppelde kinderen ophalen (ouder)\n- GET    /api/v1/guardian/my-requests        — Verzoekhistorie (ouder)\n- GET    /api/v1/guardian/members/{id}/data  — Kindgegevens ophalen (ouder)\n\nVerzoek indienen (POST /api/v1/guardian/request):\nBody: { \"lidnummer\": \"LID-00123\", \"achternaam\": \"Jansen\", \"geboortedatum\": \"2010-05-14\" }\n\nReageren (POST /api/v1/guardian/{id}/respond):\nBody: { \"action\": \"approve\" } of { \"action\": \"reject\" }\n\nBeveiliging:\n- Drie-veld verificatie (lidnummer + achternaam + geboortedatum)\n- Identieke foutmelding ongeacht de reden — voorkomt gebruikersenumeratie\n- Rate limiting: 5 verzoeken per minuut per account\n- Club-scoping: zoekt alleen binnen leden van dezelfde club",
            ],
            [
                'category'   => 'koppelingen',
                'sort_order' => 24,
                'title'      => 'Push Notificaties',
                'body'       => "Chat push-notificaties werken via Firebase Cloud Messaging (FCM), topic-based.\n\nAbonneren: na het inloggen abonneert elk toestel zich op twee topics — een persoonlijk topic (user_<gesanitiseerde-email>) voor directe en staffgroep-berichten, en het teamtopic (team_<teamId>) voor de teamchat. De app vraagt bij het eerste gebruik van de chat toestemming voor meldingen.\n\nVersturen: gebeurt via Firebase Cloud Functions (project voetbalplanner-b4062). Twee triggers:\n- notifyOnChatMessage — vuurt op nieuwe documenten in chatMessages (direct/staffgroep), zoekt de deelnemers op in chatConversations en pusht naar elk persoonlijk topic, behalve dat van de afzender.\n- notifyOnTeamChat — vuurt op nieuwe documenten in teamChats en pusht naar het teamtopic team_<teamId>.\n\nApparaten die op het topic zijn geabonneerd ontvangen de melding ook als de app op de achtergrond of gesloten is. Tikken op de melding opent het juiste gesprek. Push-topics werken op Android en iOS, niet op web.\n\nLet op: de Firestore-beveiligingsregels voor de chat-collecties moeten gepubliceerd blijven (request.auth != null) — anders falen chat-reads/writes met permission-denied.",
            ],
            [
                'category'   => 'app',
                'sort_order' => 8,
                'title'      => 'Gastspeler uitnodigen',
                'body'       => "Coaches en leiders kunnen een gastspeler uit een ander team uitnodigen voor een wedstrijd. De gastspeler krijgt dan tot kort na de wedstrijd toegang tot de informatie van die wedstrijd.\n\n**Een gastspeler uitnodigen (coach/leider)**\n1. Open de wedstrijd waarvoor je een gastspeler wilt uitnodigen.\n2. Ga naar de sectie \"Gastspeler uitnodigen\" (alleen zichtbaar als je de wedstrijd mag beheren).\n3. Kies eerst het team waar de gastspeler in zit — je kunt uit alle teams van de club kiezen.\n4. Kies vervolgens de speler uit dat team.\n5. De speler is meteen uitgenodigd; je ziet een bevestiging.\n\n**Als je bent uitgenodigd (gastspeler)**\n- Je ontvangt een push-melding.\n- Op je dashboard verschijnt de sectie \"Uitnodigingen\" met de wedstrijd(en) waarvoor je bent uitgenodigd.\n- Tik op de uitnodiging om de wedstrijddetails te bekijken (datum, tijd, locatie, tegenstander).\n\nDe toegang verloopt automatisch kort na de wedstrijd; daarna verdwijnt de uitnodiging vanzelf uit je overzicht. Een coach of beheerder kan een uitnodiging ook eerder intrekken.",
            ],
            [
                'category'   => 'koppelingen',
                'sort_order' => 26,
                'title'      => 'Gastspeler-uitnodigingen API',
                'body'       => "Gastspeler-uitnodigingen verlopen via de Laravel REST API en worden opgeslagen in de tabel match_guest_invitations. Informatief (geen accepteren/weigeren): de gast krijgt toegang zolang de uitnodiging actief en niet-verlopen is.\n\nEndpoints (beveiligd met Bearer Token):\n- POST   /api/v1/matches/{match}/guest-invite?teamId=..&memberId=..  — Gastspeler uitnodigen (coach van het wedstrijd-team)\n- GET    /api/v1/guest-invite/teams                                  — Club-teams voor de teamkeuze\n- GET    /api/v1/guest-invitations                                   — Mijn actieve uitnodigingen (gast)\n- DELETE /api/v1/guest-invitations/{invitation}/revoke              — Intrekken (coach of beheerder)\n\nRechten: alleen een coach/leider van het team van de wedstrijd (of een beheerder) mag uitnodigen — dezelfde controle als voor opstelling/score (canManageLineup).\n\nToegangsperiode: expires_at wordt gezet op de wedstrijddatum + 1 dag. Daarna valt de uitnodiging automatisch uit het overzicht.\n\nPush: bij het uitnodigen stuurt de backend een melding naar het persoonlijke topic van de gast (user_<gesanitiseerde-email>) via Firebase Cloud Messaging; tikken opent de wedstrijddetailpagina.\n\nBeheer: in het platform staat onder Leden → Gastspelers een read-only overzicht met een intrekken-actie.",
            ],
        ];

        foreach ($sections as $section) {
            $onderwerp = Documentation::firstOrNew([
                'category' => $section['category'],
                'title'    => $section['title'],
            ]);

            // De tekst en de volgorde komen uit deze seeder, dus die worden bij
            // elke deploy bijgewerkt: verbeteringen aan de handleiding horen
            // vanzelf op de server te komen.
            $onderwerp->body       = $section['body'];
            $onderwerp->sort_order = $section['sort_order'];

            // De zichtbaarheid niet. Heeft een beheerder een onderwerp
            // uitgezet, dan is dat een keuze - en die stond na elke deploy weer
            // aan, zonder dat iemand doorhad waar het vandaan kwam.
            //
            // Voor wie de sectie bedoeld is werkt hetzelfde. Nieuwe secties
            // krijgen een startpunt op basis van de categorie: het platform en
            // de koppelingen gaan over het beheerportaal en zijn dus voor de
            // staf. Daarna is het aan de beheerder.
            if (! $onderwerp->exists) {
                $onderwerp->is_active = true;
                // Staat er een doelgroep bij het onderwerp, dan wint die. Anders
                // de categorie: het platform en de koppelingen gaan over het
                // beheerportaal en zijn dus voor de staf.
                $onderwerp->audience = $section['audience']
                    ?? (in_array($section['category'], ['platform', 'koppelingen'], true)
                        ? Documentation::AUDIENCE_STAFF
                        : Documentation::AUDIENCE_ALL);
                // Een gekoppelde rondleiding alleen bij het aanmaken. Haalt een
                // beheerder hem later weg, dan is dat een keuze.
                if (isset($section['tour_id'])) {
                    $onderwerp->tour_id = $section['tour_id'];
                }
            }

            $onderwerp->save();
        }
    }
}
