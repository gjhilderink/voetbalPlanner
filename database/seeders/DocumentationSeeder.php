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
                'body'       => "Via de teamchat wissel je berichten uit met alle leden van jouw elftal. De chat werkt in real-time.\n\nJouw eigen berichten verschijnen aan de rechterkant in de primaire clubkleur. Berichten van anderen verschijnen links, met de naam van de afzender erboven.\n\nStuur een bericht door een tekst te typen en op het verstuur-icoon te tikken. Je kunt ook directe berichten sturen naar individuele teamleden via de drie puntjes rechtsboven.",
            ],
            [
                'category'   => 'app',
                'sort_order' => 6,
                'title'      => 'Profiel',
                'body'       => "Op de profielpagina zie je jouw persoonlijke gegevens: naam, e-mailadres en club.\n\nVia de uitlogknop kun je uitloggen. Je bent dan afgemeld totdat je opnieuw een magic link aanvraagt.\n\nOp de profielpagina vind je ook de link naar deze handleiding.",
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
                'body'       => "Via Instellingen stel je de identiteit van de club in.\n\nHuisstijl:\n- Primaire kleur: hoofdkleur voor knoppen, appbalk en e-mailkop\n- Secundaire kleur: ondersteunende kleur voor kaarten en achtergronden\n- Accentkleur: kleur voor highlights, badges en call-to-action elementen\n\nDe drie kleuren worden automatisch overgenomen in de app na het inloggen van leden.\n\nE-mail sjabloon:\n- Koptekst: de aanhef bovenin de verificatie-e-mail\n- Introductietekst: aanvullende uitleg in de e-mail\n- Voettekst: tekst onderaan de e-mail (bijv. contactgegevens)\n\nSla de instellingen op via de knop \"Opslaan\" rechtsonder.",
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
                'sort_order' => 16,
                'title'      => 'Documentatie Beheren',
                'body'       => "Via Documentatie in het platform beheer je de handleiding die leden in de app zien.\n\nSecties zijn ingedeeld in drie categorieën:\n- De App: uitleg over het gebruik van de app\n- Het Platform: uitleg voor beheerders\n- Koppelingen: technische uitleg over de integraties\n\nEen sectie aanpassen: open de sectie en bewerk de titel of inhoud. Gebruik de volgorde om de weergavevolgorde te bepalen.\n\nPDF exporteren: tik op \"PDF exporteren\" boven de lijst om een opgemaakt PDF-document te downloaden met alle actieve secties.",
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
                'sort_order' => 24,
                'title'      => 'Push Notificaties',
                'body'       => "De app ondersteunt push notificaties via Firebase Cloud Messaging (FCM).\n\nTeamberichten: bij het openen van de teamchat abonneert de app zich op het FCM-topic van het team (team-{teamId}). Alle teamleden ontvangen zo notificaties bij nieuwe berichten.\n\nTechnische werking: het platform stuurt berichten naar een FCM-topic of naar individuele apparaat-tokens. Alle apparaten die op het topic zijn geabonneerd ontvangen de notificatie, ook als de app op de achtergrond is.\n\nToestemming: bij de eerste installatie vraagt de app toestemming voor het ontvangen van notificaties.",
            ],
        ];

        foreach ($sections as $section) {
            Documentation::updateOrCreate(
                ['category' => $section['category'], 'title' => $section['title']],
                [
                    'body'       => $section['body'],
                    'sort_order' => $section['sort_order'],
                    'is_active'  => true,
                ]
            );
        }
    }
}
