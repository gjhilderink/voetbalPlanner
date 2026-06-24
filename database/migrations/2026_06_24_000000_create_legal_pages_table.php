<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_pages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();   // bv. 'privacy'
            $table->string('title');
            $table->longText('body');           // HTML (RichEditor)
            $table->timestamps();
        });

        // Standaard-privacyverklaring seeden zodat /privacy meteen werkt.
        DB::table('legal_pages')->insert([
            'id'         => (string) Str::uuid(),
            'slug'       => 'privacy',
            'title'      => 'Privacyverklaring',
            'body'       => $this->defaultPrivacyHtml(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_pages');
    }

    private function defaultPrivacyHtml(): string
    {
        return <<<'HTML'
<p>VoetbalPlanner respecteert jouw privacy en gaat zorgvuldig om met je persoonsgegevens. In deze privacyverklaring leggen we uit welke gegevens we verwerken via het VoetbalPlanner-platform en de bijbehorende app, met welk doel, en welke rechten je hebt. We verwerken persoonsgegevens in overeenstemming met de Algemene Verordening Gegevensbescherming (AVG/GDPR).</p>
<p><em>Let op: dit is een basis-/standaardverklaring. Vul de [tussen haken] aangegeven gegevens aan met die van jouw organisatie en laat de tekst juridisch controleren voordat je 'm definitief publiceert.</em></p>
<h2>1. Wie zijn wij?</h2>
<p>VoetbalPlanner wordt aangeboden door <strong>[organisatienaam / B.V.]</strong>, gevestigd te [adres, plaats] (KvK [nummer]). Voor vragen over deze verklaring of je gegevens kun je contact opnemen via <a href="mailto:[privacy@voetbalplanner.nl]">[privacy@voetbalplanner.nl]</a>.</p>
<p>Voor de gegevens van leden treedt jouw <strong>voetbalclub</strong> op als verwerkingsverantwoordelijke; VoetbalPlanner verwerkt die gegevens als verwerker namens de club.</p>
<h2>2. Welke gegevens verwerken we?</h2>
<ul>
<li><strong>Accountgegevens:</strong> naam, e-mailadres, telefoonnummer en (indien van toepassing) lidnummer.</li>
<li><strong>Profielgegevens:</strong> geboortedatum, team(s), rol binnen de club en een optionele profielfoto.</li>
<li><strong>Sportgegevens:</strong> wedstrijden, opstellingen, bardiensten, rijschema's en wisselverzoeken.</li>
<li><strong>Chatberichten:</strong> de inhoud van team-, groeps- en directe berichten, met afzender en tijdstip.</li>
<li><strong>Meldingen:</strong> een apparaat-token voor push-notificaties.</li>
<li><strong>Ouder/verzorger-koppelingen:</strong> de relatie tussen een ouder/verzorger en het gekoppelde kind.</li>
<li><strong>Technische gegevens:</strong> bij een bugmelding sturen we app-versie, platform en apparaatinformatie mee; daarnaast verwerken we inlog- en sessiegegevens.</li>
</ul>
<p>Een deel van deze gegevens kan automatisch worden gesynchroniseerd vanuit <strong>Sportlink</strong>, het ledensysteem van jouw club.</p>
<h2>3. Waarvoor gebruiken we je gegevens?</h2>
<ul>
<li>Het aanbieden en laten functioneren van de app en het platform (wedstrijden, bardiensten, rijschema, chat, documentatie).</li>
<li>Het inloggen via een magic link (eenmalige, tijdelijke inloglink per e-mail).</li>
<li>Het versturen van push-notificaties bij nieuwe chatberichten.</li>
<li>Communicatie binnen de club via de chat.</li>
<li>Het afhandelen van bugmeldingen en support.</li>
<li>Beveiliging, misbruikpreventie en het nakomen van wettelijke verplichtingen.</li>
</ul>
<h2>4. Grondslag</h2>
<p>We verwerken je gegevens op basis van de <strong>uitvoering van de overeenkomst</strong> (het lidmaatschap en het gebruik van de app) en op basis van een <strong>gerechtvaardigd belang</strong> (een goed werkende, veilige clubadministratie en communicatie). Waar de wet dit vereist, vragen we je <strong>toestemming</strong> — bijvoorbeeld voor push-notificaties.</p>
<h2>5. Met wie delen we gegevens?</h2>
<p>We verkopen je gegevens nooit. We delen ze alleen met partijen die nodig zijn om de dienst te leveren:</p>
<ul>
<li><strong>Jouw voetbalclub</strong> en haar beheerders/coaches/commissieleden.</li>
<li><strong>Google Firebase</strong> (Firebase Cloud Messaging &amp; Firestore) voor chat en push-notificaties.</li>
<li><strong>Onze hostingpartij</strong> waarop het platform draait.</li>
<li>Partijen waar we wettelijk toe verplicht zijn (bijv. op grond van een gerechtelijk bevel).</li>
</ul>
<p>Met deze partijen sluiten we waar nodig verwerkersovereenkomsten. Gegevens kunnen worden verwerkt buiten de EU; in dat geval zorgen we voor passende waarborgen.</p>
<h2>6. Hoe lang bewaren we je gegevens?</h2>
<p>We bewaren je gegevens niet langer dan nodig is voor de hierboven genoemde doelen, of zolang je lid bent en de club gebruikmaakt van VoetbalPlanner. Daarna verwijderen of anonimiseren we je gegevens, tenzij we wettelijk verplicht zijn ze langer te bewaren.</p>
<h2>7. Beveiliging</h2>
<p>We nemen passende technische en organisatorische maatregelen om je gegevens te beschermen, waaronder versleutelde verbindingen (HTTPS), tijdelijke en per apparaat unieke toegangstokens, en toegangsbeperking op basis van rollen.</p>
<h2>8. Kinderen en ouder/verzorger-toegang</h2>
<p>Voor minderjarige leden kan een ouder of verzorger toegang aanvragen tot de gegevens van het kind. Een koppeling komt alleen tot stand na bevestiging door het kind/lid en met verificatie van lidnummer, achternaam én geboortedatum. Zowel het kind als de ouder kan de koppeling op elk moment intrekken.</p>
<h2>9. Cookies</h2>
<p>De publieke website gebruikt alleen functionele cookies die nodig zijn voor het inloggen en de beveiliging van sessies. We gebruiken geen tracking- of advertentiecookies.</p>
<h2>10. Jouw rechten</h2>
<p>Je hebt op grond van de AVG het recht om je gegevens in te zien, te corrigeren of te laten verwijderen; de verwerking te beperken of er bezwaar tegen te maken; je gegevens over te laten dragen (dataportabiliteit); en een gegeven toestemming weer in te trekken.</p>
<p>Neem hiervoor contact op via <a href="mailto:[privacy@voetbalplanner.nl]">[privacy@voetbalplanner.nl]</a> of via je club. Je hebt daarnaast het recht een klacht in te dienen bij de <a href="https://www.autoriteitpersoonsgegevens.nl" target="_blank" rel="noopener">Autoriteit Persoonsgegevens</a>.</p>
<h2>11. Wijzigingen</h2>
<p>We kunnen deze privacyverklaring van tijd tot tijd aanpassen. De meest actuele versie vind je altijd op deze pagina, met bovenaan de datum van de laatste wijziging.</p>
<h2>12. Contact</h2>
<p>Vragen over je privacy of deze verklaring? Mail naar <a href="mailto:[privacy@voetbalplanner.nl]">[privacy@voetbalplanner.nl]</a>.</p>
HTML;
    }
};
