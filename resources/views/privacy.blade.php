<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Privacyverklaring — VoetbalPlanner</title>
    <meta name="description" content="Privacyverklaring van VoetbalPlanner: welke persoonsgegevens we verwerken, waarom, en welke rechten je hebt.">
    <meta name="robots" content="index, follow">

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
</head>
<body class="bg-white text-gray-900 antialiased font-sans">

{{-- Navigatie --}}
<nav class="bg-white border-b border-gray-100 sticky top-0 z-50 shadow-sm">
    <div class="max-w-6xl mx-auto px-6 py-4 flex items-center justify-between">
        <a href="/" class="flex items-center gap-2">
            <svg class="w-8 h-8 text-green-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2zm0 2c1.29 0 2.516.26 3.633.727L13.5 7H10.5L8.367 4.727A7.963 7.963 0 0112 4zM6.25 5.86L8 8.5l-2 3H3.1A8.007 8.007 0 016.25 5.86zm11.5 0A8.007 8.007 0 0120.9 11.5H18l-2-3 1.75-2.64zM10 9h4l1.5 2.5L14 14h-4l-1.5-2.5L10 9zm-4.6 4H8l1.5 3-1.75 2.64A8.007 8.007 0 015.4 13zm13.2 0a8.007 8.007 0 01-1.85 5.64L15 16l1.5-3h2.6zM10.5 17h3l2.133 2.273A7.963 7.963 0 0112 20a7.963 7.963 0 01-3.633-.727L10.5 17z"/>
            </svg>
            <span class="text-xl font-bold text-gray-900 tracking-tight">VoetbalPlanner</span>
        </a>
        <a href="/" class="text-sm font-medium text-gray-500 hover:text-gray-900 transition-colors">&larr; Terug naar home</a>
    </div>
</nav>

{{-- Header --}}
<section class="bg-gradient-to-br from-green-800 via-green-700 to-emerald-600 text-white py-16 px-6">
    <div class="max-w-3xl mx-auto">
        <h1 class="text-3xl sm:text-4xl font-extrabold mb-3">Privacyverklaring</h1>
        <p class="text-green-100">Laatst bijgewerkt: 24 juni 2026</p>
    </div>
</section>

{{-- Inhoud --}}
<section class="py-14 px-6 bg-white">
    <div class="max-w-3xl mx-auto prose-content">
        <style>
            .prose-content h2 { font-size: 1.35rem; font-weight: 700; color: #111827; margin-top: 2.25rem; margin-bottom: .75rem; }
            .prose-content h3 { font-size: 1.05rem; font-weight: 600; color: #111827; margin-top: 1.5rem; margin-bottom: .5rem; }
            .prose-content p, .prose-content li { color: #4b5563; line-height: 1.7; }
            .prose-content p { margin-bottom: 1rem; }
            .prose-content ul { list-style: disc; padding-left: 1.4rem; margin-bottom: 1rem; }
            .prose-content li { margin-bottom: .35rem; }
            .prose-content a { color: #15803d; text-decoration: underline; }
            .prose-content .note { background:#f0fdf4; border:1px solid #bbf7d0; border-radius:.75rem; padding:1rem 1.25rem; color:#166534; }
        </style>

        <p>VoetbalPlanner respecteert jouw privacy en gaat zorgvuldig om met je persoonsgegevens. In deze privacyverklaring leggen we uit welke gegevens we verwerken via het VoetbalPlanner-platform en de bijbehorende app, met welk doel, en welke rechten je hebt. We verwerken persoonsgegevens in overeenstemming met de Algemene Verordening Gegevensbescherming (AVG/GDPR).</p>

        <div class="note">
            <strong>Let op:</strong> dit is een basis-/standaardverklaring. Vul de [tussen haken] aangegeven gegevens aan met die van jouw organisatie en laat de tekst juridisch controleren voordat je 'm definitief publiceert.
        </div>

        <h2>1. Wie zijn wij?</h2>
        <p>
            VoetbalPlanner wordt aangeboden door <strong>[organisatienaam / B.V.]</strong>, gevestigd te [adres, plaats]
            (KvK [nummer]). Voor vragen over deze verklaring of je gegevens kun je contact opnemen via
            <a href="mailto:[privacy@voetbalplanner.nl]">[privacy@voetbalplanner.nl]</a>.
        </p>
        <p>
            Voor de gegevens van leden treedt jouw <strong>voetbalclub</strong> op als verwerkingsverantwoordelijke;
            VoetbalPlanner verwerkt die gegevens als verwerker namens de club.
        </p>

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
        <p>
            We verwerken je gegevens op basis van de <strong>uitvoering van de overeenkomst</strong> (het lidmaatschap en het
            gebruik van de app) en op basis van een <strong>gerechtvaardigd belang</strong> (een goed werkende, veilige
            clubadministratie en communicatie). Waar de wet dit vereist, vragen we je <strong>toestemming</strong> —
            bijvoorbeeld voor push-notificaties.
        </p>

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
        <p>
            We bewaren je gegevens niet langer dan nodig is voor de hierboven genoemde doelen, of zolang je lid bent en de
            club gebruikmaakt van VoetbalPlanner. Daarna verwijderen of anonimiseren we je gegevens, tenzij we wettelijk
            verplicht zijn ze langer te bewaren.
        </p>

        <h2>7. Beveiliging</h2>
        <p>
            We nemen passende technische en organisatorische maatregelen om je gegevens te beschermen, waaronder versleutelde
            verbindingen (HTTPS), tijdelijke en per apparaat unieke toegangstokens, en toegangsbeperking op basis van rollen.
        </p>

        <h2>8. Kinderen en ouder/verzorger-toegang</h2>
        <p>
            Voor minderjarige leden kan een ouder of verzorger toegang aanvragen tot de gegevens van het kind. Een koppeling
            komt alleen tot stand na bevestiging door het kind/lid en met verificatie van lidnummer, achternaam én
            geboortedatum. Zowel het kind als de ouder kan de koppeling op elk moment intrekken.
        </p>

        <h2>9. Cookies</h2>
        <p>
            De publieke website gebruikt alleen functionele cookies die nodig zijn voor het inloggen en de beveiliging van
            sessies. We gebruiken geen tracking- of advertentiecookies.
        </p>

        <h2>10. Jouw rechten</h2>
        <p>Je hebt op grond van de AVG het recht om:</p>
        <ul>
            <li>je gegevens in te zien, te corrigeren of te laten verwijderen;</li>
            <li>de verwerking te beperken of er bezwaar tegen te maken;</li>
            <li>je gegevens over te laten dragen (dataportabiliteit);</li>
            <li>een gegeven toestemming weer in te trekken.</li>
        </ul>
        <p>
            Neem hiervoor contact op via <a href="mailto:[privacy@voetbalplanner.nl]">[privacy@voetbalplanner.nl]</a> of via
            je club. Je hebt daarnaast het recht een klacht in te dienen bij de
            <a href="https://www.autoriteitpersoonsgegevens.nl" target="_blank" rel="noopener">Autoriteit Persoonsgegevens</a>.
        </p>

        <h2>11. Wijzigingen</h2>
        <p>
            We kunnen deze privacyverklaring van tijd tot tijd aanpassen. De meest actuele versie vind je altijd op deze
            pagina, met bovenaan de datum van de laatste wijziging.
        </p>

        <h2>12. Contact</h2>
        <p>
            Vragen over je privacy of deze verklaring? Mail naar
            <a href="mailto:[privacy@voetbalplanner.nl]">[privacy@voetbalplanner.nl]</a>.
        </p>
    </div>
</section>

{{-- Footer --}}
<footer class="bg-gray-900 text-gray-400 py-10 px-6">
    <div class="max-w-6xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4 text-sm">
        <div class="flex items-center gap-2">
            <svg class="w-6 h-6 text-green-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2zm0 2c1.29 0 2.516.26 3.633.727L13.5 7H10.5L8.367 4.727A7.963 7.963 0 0112 4zM6.25 5.86L8 8.5l-2 3H3.1A8.007 8.007 0 016.25 5.86zm11.5 0A8.007 8.007 0 0120.9 11.5H18l-2-3 1.75-2.64zM10 9h4l1.5 2.5L14 14h-4l-1.5-2.5L10 9zm-4.6 4H8l1.5 3-1.75 2.64A8.007 8.007 0 015.4 13zm13.2 0a8.007 8.007 0 01-1.85 5.64L15 16l1.5-3h2.6zM10.5 17h3l2.133 2.273A7.963 7.963 0 0112 20a7.963 7.963 0 01-3.633-.727L10.5 17z"/>
            </svg>
            <span class="font-semibold text-white">VoetbalPlanner</span>
        </div>
        <span>&copy; {{ date('Y') }} VoetbalPlanner. Alle rechten voorbehouden.</span>
        <a href="/" class="hover:text-white transition-colors">Home</a>
    </div>
</footer>

</body>
</html>
