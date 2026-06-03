<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Documentation;
use Illuminate\Database\Seeder;

class DocumentationSeeder extends Seeder
{
    public function run(): void
    {
        if (Documentation::exists()) {
            return;
        }

        $sections = [
            // ── De App ──────────────────────────────────────────────────────
            [
                'category'   => 'app',
                'sort_order' => 1,
                'title'      => 'Welkom bij VoetbalPlanner',
                'body'       => <<<TEXT
VoetbalPlanner is een mobiele app voor leden en staf van jouw voetbalclub. Via de app heb je altijd en overal toegang tot het wedstrijdprogramma, de bardiensten, het rijschema en de teamchat.

Na het inloggen zie je automatisch de informatie die hoort bij jouw elftal. De app past de kleuren aan op de huisstijl van jouw club.
TEXT,
            ],
            [
                'category'   => 'app',
                'sort_order' => 2,
                'title'      => 'Inloggen',
                'body'       => <<<TEXT
Leden loggen in via een magic link die per e-mail wordt verstuurd:
1. Vul je e-mailadres in op het inlogscherm.
2. Tik op "Stuur inloglink".
3. Controleer je inbox en tik op de link in de e-mail.
4. Je wordt automatisch ingelogd in de app.

De link is 15 minuten geldig. Heb je geen e-mail ontvangen? Controleer dan ook je spam-map of vraag een nieuwe link aan.

Beheerders kunnen ook inloggen met e-mailadres en wachtwoord via de knop "Inloggen beheerders".

Na de eerste keer inloggen kun je bij vervolgbezoeken gebruikmaken van Face ID of vingerafdruk (indien beschikbaar op jouw toestel).
TEXT,
            ],
            [
                'category'   => 'app',
                'sort_order' => 3,
                'title'      => 'Wedstrijden',
                'body'       => <<<TEXT
Op de wedstrijdenpagina zie je het programma van jouw elftal.

Standaard worden alleen de aankomende wedstrijden getoond. Wil je het volledige seizoenprogramma zien, schakel dan de toggle "Toon alle wedstrijden" in.

Tik op een wedstrijd om de detailpagina te openen. Hier vind je:
- Datum, tijd en locatie
- Verzameltijd
- Coach en Fruitheld
- Opstelling (indien ingevoerd)
- Doelpunten en eindstand
- Notities van de coach
TEXT,
            ],
            [
                'category'   => 'app',
                'sort_order' => 4,
                'title'      => 'Rijschema',
                'body'       => <<<TEXT
Het rijschema toont de vervoersafspraken rondom wedstrijden. Per wedstrijd zie je welke leden beschikbaar zijn als chauffeur en hoeveel plaatsen er nog vrij zijn.

Het rijschema wordt beheerd door de teamcoach of clubbeheerder via het platform.
TEXT,
            ],
            [
                'category'   => 'app',
                'sort_order' => 5,
                'title'      => 'Bardiensten',
                'body'       => <<<TEXT
Op de bardienstenpagina zie je de bardiensten van jouw elftal. Per dienst staat vermeld:
- Datum en dienst (ochtend, middag of avond)
- Toegewezen leden
- Status: Gepland, Bevestigd of Vervuld

De bardiensten worden gepland door de bardienst-commissie of clubbeheerder. Als jij of een teamgenoot is toegewezen aan een dienst, zie je dat hier terug.
TEXT,
            ],
            [
                'category'   => 'app',
                'sort_order' => 6,
                'title'      => 'Teamchat',
                'body'       => <<<TEXT
Via de teamchat wissel je berichten uit met alle leden van jouw elftal. De chat werkt in real-time.

Jouw eigen berichten verschijnen aan de rechterkant in de primaire clubkleur. Berichten van anderen verschijnen links, met de naam van de afzender erboven.

Stuur een bericht door een tekst te typen en op het verstuur-icoon te tikken. Je kunt ook directe berichten sturen naar individuele teamleden via de drie puntjes rechtsboven.
TEXT,
            ],
            [
                'category'   => 'app',
                'sort_order' => 7,
                'title'      => 'Profiel',
                'body'       => <<<TEXT
Op de profielpagina zie je jouw persoonlijke gegevens: naam, e-mailadres en club.

Via de uitlogknop kun je uitloggen. Je bent dan afgemeld totdat je opnieuw een magic link aanvraagt.

Op de profielpagina vind je ook de link naar deze handleiding.
TEXT,
            ],

            // ── Het Platform ─────────────────────────────────────────────────
            [
                'category'   => 'platform',
                'sort_order' => 10,
                'title'      => 'Het Platform',
                'body'       => <<<TEXT
Het VoetbalPlanner platform is het beheersysteem achter de app. Beheerders, coaches en commissieleden loggen in via de browser op het admin-adres van de club (bijvoorbeeld: jouwclub.voetbalplanner.nl/admin).

Via het platform beheer je de volledige cluborganisatie: leden, teams, wedstrijden, bardiensten en instellingen. Wijzigingen zijn direct zichtbaar in de app.
TEXT,
            ],
            [
                'category'   => 'platform',
                'sort_order' => 11,
                'title'      => 'Club Instellingen',
                'body'       => <<<TEXT
Via Instellingen stel je de identiteit van de club in.

Huisstijl:
- Primaire kleur: hoofdkleur voor knoppen, appbalk en e-mailkop
- Secundaire kleur: ondersteunende kleur voor kaarten en achtergronden
- Accentkleur: kleur voor highlights, badges en call-to-action elementen

De drie kleuren worden automatisch overgenomen in de app na het inloggen van leden.

E-mail sjabloon:
- Koptekst: de aanhef bovenin de verificatie-e-mail
- Introductietekst: aanvullende uitleg in de e-mail
- Voettekst: tekst onderaan de e-mail (bijv. contactgegevens)

Sla de instellingen op via de knop "Opslaan" rechtsonder.
TEXT,
            ],
            [
                'category'   => 'platform',
                'sort_order' => 12,
                'title'      => 'Teams & Leden',
                'body'       => <<<TEXT
Teams zijn de elftallen van de club. Per team leg je vast:
- Naam en eventuele externe club-ID (voor synchronisatie)
- Coaches (koppeling aan gebruikers met de rol Coach)

Leden zijn de spelers en stafleden. Per lid sla je op:
- Naam, e-mailadres en telefoonnummer
- Geboortedatum
- Koppeling aan een of meerdere teams

Leden importeren: via de knop "Importeren" op de ledenpagina kun je een Excel-bestand uploaden om leden in bulk toe te voegen. Gebruik het geëxporteerde bestand als sjabloon.

Een lid dat ook toegang nodig heeft tot het platform, koppel je via Gebruikers aan de juiste rol.
TEXT,
            ],
            [
                'category'   => 'platform',
                'sort_order' => 13,
                'title'      => 'Wedstrijdbeheer',
                'body'       => <<<TEXT
Via de wedstrijdenmodule beheer je alle wedstrijden van het seizoen.

Per wedstrijd stel je in:
- Datum, tijd en locatie
- Tegenstander en thuis/uit
- Status: Gepland, Gespeeld, Geannuleerd of Uitgesteld
- Verzameltijd, coach en fruitheld
- Opstelling en doelpunten (na de wedstrijd)
- Notities voor het team

Wedstrijden zijn na opslaan direct zichtbaar in de app voor de leden van het betreffende elftal. Coaches kunnen de opstelling en wedstrijdgegevens ook vanuit de app bijwerken.
TEXT,
            ],
            [
                'category'   => 'platform',
                'sort_order' => 14,
                'title'      => 'Bardiensten Beheer',
                'body'       => <<<TEXT
Via de bardiensten-module plan je de kantinediensten voor het seizoen.

Een nieuwe bardienst aanmaken:
1. Kies datum, dienst (ochtend / middag / avond) en eventueel een elftal.
2. Wijs optioneel direct leden toe (maximaal 2 per dienst).
3. Sla op.

Leden toewijzen: open een bardienst en gebruik "Leden koppelen" om maximaal 2 leden toe te wijzen.

Status verloop:
- Open: geen leden toegewezen
- Bevestigd: één of twee leden zijn toegewezen
- Vervuld: de dienst is uitgevoerd

Importeren en exporteren: gebruik de Excel-import om bardiensten in bulk aan te maken. Het geëxporteerde bestand dient als sjabloon.
TEXT,
            ],
            [
                'category'   => 'platform',
                'sort_order' => 15,
                'title'      => 'Gebruikers & Rollen',
                'body'       => <<<TEXT
VoetbalPlanner werkt met de volgende rollen:

Super Admin — volledige toegang tot alle clubs en instellingen.
Club Admin — beheert de eigen club volledig (teams, leden, wedstrijden, bardiensten, instellingen).
Bar Commissie — beheert bardiensten en kan leden toewijzen.
Coach — beheert wedstrijden en rijschema van het eigen team.
Member — alleen leestoegang via de mobiele app (geen toegang tot het platform).

Gebruikers aanmaken: via Gebruikers > Nieuw koppel je een e-mailadres aan de juiste rol. De gebruiker ontvangt een uitnodigingsmail.

Imiteren (impersoneren): als Super Admin kun je via het gebruikersmenu inloggen als een andere gebruiker om hun omgeving te controleren. Een oranje balk bovenaan het scherm geeft aan dat je imitatiemodus actief is. Klik op "Terug naar eigen account" om te stoppen.
TEXT,
            ],

            // ── Koppelingen ───────────────────────────────────────────────────
            [
                'category'   => 'koppelingen',
                'sort_order' => 20,
                'title'      => 'App ↔ Platform Koppeling',
                'body'       => <<<TEXT
De mobiele app communiceert via een beveiligde REST API met het Laravel-platform.

Authenticatie: na het inloggen ontvangt de app een persoonlijk toegangstoken (Sanctum Bearer Token). Dit token wordt meegestuurd bij elk verzoek aan de API via de Authorization-header.

Gegevensuitwisseling: wedstrijden, bardiensten en profieldata worden via API-endpoints opgehaald. De app slaat het token en basisgegevens (naam, club, kleuren) lokaal op zodat de app ook opstartbaar blijft zonder directe internetverbinding.

Beveiliging: het token is uniek per apparaat en per sessie. Bij uitloggen wordt het token ongeldig gemaakt op de server.
TEXT,
            ],
            [
                'category'   => 'koppelingen',
                'sort_order' => 21,
                'title'      => 'E-mail & Magic Link',
                'body'       => <<<TEXT
Leden loggen in via een magic link die per e-mail wordt verstuurd.

Hoe het werkt:
1. Het lid vult zijn/haar e-mailadres in de app in.
2. De app stuurt een verzoek naar het platform.
3. Het platform genereert een tijdelijk token (geldig 15 minuten) en verstuurt een e-mail met de inloglink.
4. Het lid klikt op de link; de app verifieert het token en is direct ingelogd.

E-mail opmaak: de stijl van de e-mail (kleuren, koptekst, introductie, voettekst) is per club instelbaar onder Instellingen in het platform.

E-mail verzending: de e-mails worden verstuurd via de geconfigureerde mailprovider van het platform (SMTP of dienst als Mailgun / Resend).
TEXT,
            ],
            [
                'category'   => 'koppelingen',
                'sort_order' => 22,
                'title'      => 'Chat (Firebase)',
                'body'       => <<<TEXT
De teamchat werkt via Firebase Firestore, een real-time database van Google.

Berichten worden opgeslagen in de Firestore-collectie "teamChats" met de velden: tekst, afzender-ID, afzendernaam, team-ID en tijdstempel.

Real-time updates: nieuwe berichten worden automatisch zichtbaar zonder dat de pagina hoeft te worden ververst.

Directe berichten tussen leden worden opgeslagen in de collectie "directMessages".

Vereiste configuratie: voor de chat is een actief Firebase-project nodig met de juiste Firestore-beveiligingsregels en de app-configuratie (google-services.json / GoogleService-Info.plist) in de FlutterFlow-projectinstellingen.
TEXT,
            ],
            [
                'category'   => 'koppelingen',
                'sort_order' => 23,
                'title'      => 'Push Notificaties',
                'body'       => <<<TEXT
De app ondersteunt push notificaties via Firebase Cloud Messaging (FCM).

Teamberichten: bij het openen van de teamchat abonneert de app zich op het FCM-topic van het team (team-{teamId}). Alle teamleden ontvangen zo notificaties bij nieuwe berichten.

Technische werking: het platform kan berichten sturen naar een FCM-topic of naar individuele apparaat-tokens. Alle apparaten die op het topic zijn geabonneerd ontvangen de notificatie, ook als de app op de achtergrond of gesloten is.

Toestemming: bij de eerste installatie vraagt de app toestemming voor het ontvangen van notificaties. Zonder toestemming worden geen pushberichten ontvangen.
TEXT,
            ],
        ];

        foreach ($sections as $section) {
            Documentation::create($section);
        }
    }
}
