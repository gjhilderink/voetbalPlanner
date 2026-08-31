<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Huisstijl — VoetbalPlanner</title>
    <meta name="description" content="De huisstijl van VoetbalPlanner: kleuren, lettertypes, logo en beeldmateriaal.">
    {{-- Niet in Google: dit is een werkdocument voor onszelf en voor wie iets
         voor ons maakt, geen pagina waarop mensen moeten binnenkomen. --}}
    <meta name="robots" content="noindex, nofollow">

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif

    @include('partials.brand')
</head>
<body class="bg-white text-navy-900 antialiased">

<div class="hoekstreep"></div>

<nav class="bg-navy-900">
    <div class="max-w-5xl mx-auto px-6 py-3.5 flex items-center justify-between">
        <a href="/" class="flex items-center gap-3">
            <img src="{{ asset('brand/app-icon-180.png') }}" alt="" class="w-9 h-9 rounded-lg" width="36" height="36">
            <span class="wordmark text-white text-2xl leading-none">
                Voetbal<span class="groen">planner</span><span class="tld groen">.nl</span>
            </span>
        </a>
        <a href="/" class="text-sm font-medium text-white/70 hover:text-white transition-colors">Terug naar de site</a>
    </div>
</nav>

<header class="tactisch text-white py-16 px-6">
    <div class="max-w-5xl mx-auto">
        <p class="payoff text-xs text-white/45 mb-4">Merkrichtlijn</p>
        <h1 class="display text-5xl sm:text-6xl mb-5">De huisstijl</h1>
        <p class="text-lg text-white/70 max-w-2xl leading-relaxed">
            Groen is de hoofdkleur, rood is een accent. Alles op deze pagina is
            bedoeld om over te nemen: de hexcodes, de lettertypes en de bestanden
            staan er klaar voor.
        </p>
    </div>
</header>

<main class="max-w-5xl mx-auto px-6 py-16 space-y-20">

    {{-- ── Kleuren ─────────────────────────────────────────────────────── --}}
    <section>
        <h2 class="display text-3xl sm:text-4xl mb-3">Kleur</h2>
        <p class="text-gray-600 mb-8 max-w-2xl leading-relaxed">
            Eén hoofdkleur en één accent. Het accent is er voor precies één ding
            per scherm — een badge, een streep, een waarschuwing. Wie rood als
            tweede hoofdkleur gebruikt, houdt geen accent meer over.
        </p>

        @php
            $kleuren = [
                ['Groen', '#5BA12F', 'Hoofdkleur. Knoppen, links, actieve staat, de tweede helft van het woordmerk.', true],
                ['Groen donker', '#4A8526', 'Alleen voor hover en ingedrukte knoppen.', true],
                ['Rood', '#E63027', 'Accent. Spaarzaam: één element per scherm. Ook de kleur van waarschuwingen.', true],
                ['Navy', '#0B1D31', 'Donkere vlakken: kop, afsluitblok, voettekst.', true],
                ['Navy zacht', '#203449', 'Verloop en randen binnen een donker vlak.', true],
                ['Wit', '#FFFFFF', 'Tekst op donker, en de achtergrond van de inhoud.', false],
            ];
        @endphp

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach ($kleuren as $kleur)
                <div class="rounded-2xl border border-gray-200 overflow-hidden">
                    <div class="h-24 flex items-end p-4"
                         style="background: {{ $kleur[1] }}; {{ $kleur[3] ? '' : 'border-bottom:1px solid #E5E7EB' }}">
                        <code class="text-sm font-semibold {{ $kleur[3] ? 'text-white' : 'text-navy-900' }}">{{ $kleur[1] }}</code>
                    </div>
                    <div class="p-4">
                        <p class="font-semibold mb-1">{{ $kleur[0] }}</p>
                        <p class="text-sm text-gray-500 leading-relaxed">{{ $kleur[2] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ── Typografie ──────────────────────────────────────────────────── --}}
    <section>
        <h2 class="display text-3xl sm:text-4xl mb-3">Letter</h2>
        <p class="text-gray-600 mb-8 max-w-2xl leading-relaxed">
            Twee sneden van dezelfde familie, allebei gratis via Google Fonts.
            Koppen smal en schuin, zoals het woordmerk; lopende tekst gewoon
            rechtop, want een hele alinea cursief leest niemand.
        </p>

        <div class="space-y-6">
            <div class="rounded-2xl border border-gray-200 p-6">
                <p class="payoff text-xs text-gray-400 mb-3">Koppen — Barlow Condensed 800 italic</p>
                <p class="display text-5xl mb-3">Plan. Organiseer. Presteer.</p>
                <p class="text-sm text-gray-500">
                    Altijd in hoofdletters, regelafstand krap (0.95). Gebruik de klasse
                    <code class="bg-gray-100 px-1.5 py-0.5 rounded text-xs">.display</code>.
                </p>
            </div>

            <div class="rounded-2xl border border-gray-200 p-6">
                <p class="payoff text-xs text-gray-400 mb-3">Payoff — Barlow Condensed 600, gespatieerd</p>
                <p class="payoff text-lg mb-3">Plan. Organiseer. Presteer.</p>
                <p class="text-sm text-gray-500">
                    Klein, in hoofdletters, letterafstand 0.18em. Klasse
                    <code class="bg-gray-100 px-1.5 py-0.5 rounded text-xs">.payoff</code>.
                </p>
            </div>

            <div class="rounded-2xl border border-gray-200 p-6">
                <p class="payoff text-xs text-gray-400 mb-3">Lopende tekst — Barlow 400/600</p>
                <p class="text-lg leading-relaxed mb-2">
                    Beheer wedstrijden, opstellingen, bardiensten en rijschema's vanuit
                    één overzichtelijk platform. Gekoppeld met Sportlink, bereikbaar via de app.
                </p>
                <p class="text-sm text-gray-500">Regelafstand ruim; grijs (#6B7280) voor bijzin en uitleg.</p>
            </div>
        </div>
    </section>

    {{-- ── Woordmerk ───────────────────────────────────────────────────── --}}
    <section>
        <h2 class="display text-3xl sm:text-4xl mb-3">Woordmerk</h2>
        <p class="text-gray-600 mb-8 max-w-2xl leading-relaxed">
            "Voetbal" in wit of navy, "planner.nl" in groen. Op de site is het
            gewone tekst en geen plaatje: dan blijft het scherp op elk scherm en
            kan een schermlezer het voorlezen.
        </p>

        <div class="grid sm:grid-cols-2 gap-5">
            <div class="rounded-2xl bg-navy-900 p-8 flex items-center justify-center">
                <span class="wordmark text-white text-4xl leading-none">
                    Voetbal<span class="groen">planner</span><span class="tld groen">.nl</span>
                </span>
            </div>
            <div class="rounded-2xl border border-gray-200 p-8 flex items-center justify-center">
                <span class="wordmark text-navy-900 text-4xl leading-none">
                    Voetbal<span class="groen">planner</span><span class="tld groen">.nl</span>
                </span>
            </div>
        </div>
    </section>

    {{-- ── Beeldmerk ───────────────────────────────────────────────────── --}}
    <section>
        <h2 class="display text-3xl sm:text-4xl mb-3">Beeldmerk</h2>
        <p class="text-gray-600 mb-8 max-w-2xl leading-relaxed">
            De bal op een diagonaal van rood en groen. Vierkant, zonder witruimte
            eromheen — een telefoon snijdt het icoon zelf rond of met afgeronde
            hoeken uit.
        </p>

        <div class="flex flex-wrap items-end gap-8">
            <div class="text-center">
                <img src="{{ asset('brand/app-icon-1024.png') }}" alt="App-icoon van VoetbalPlanner"
                     class="w-32 h-32 rounded-3xl shadow-lg" width="128" height="128">
                <p class="text-xs text-gray-500 mt-3">1024 × 1024</p>
                <a href="{{ asset('brand/app-icon-1024.png') }}" download
                   class="text-xs font-semibold text-brand-600 hover:underline">Downloaden</a>
            </div>
            <div class="text-center">
                <img src="{{ asset('brand/app-icon-180.png') }}" alt=""
                     class="w-16 h-16 rounded-2xl shadow" width="64" height="64">
                <p class="text-xs text-gray-500 mt-3">180 × 180</p>
                <a href="{{ asset('brand/app-icon-180.png') }}" download
                   class="text-xs font-semibold text-brand-600 hover:underline">Downloaden</a>
            </div>
        </div>

        <div class="mt-8 rounded-2xl border border-gray-200 overflow-hidden">
            <div class="hoekstreep"></div>
            <div class="p-6">
                <p class="font-semibold mb-1">De diagonale streep</p>
                <p class="text-sm text-gray-500 leading-relaxed">
                    Rood, wit, groen — dezelfde volgorde als in het icoon. Eén keer per
                    pagina, bovenaan. Klasse <code class="bg-gray-100 px-1.5 py-0.5 rounded text-xs">.hoekstreep</code>.
                </p>
            </div>
        </div>
    </section>

    {{-- ── Beeldmateriaal ──────────────────────────────────────────────── --}}
    <section>
        <h2 class="display text-3xl sm:text-4xl mb-3">Beeld</h2>
        <p class="text-gray-600 mb-8 max-w-2xl leading-relaxed">
            Stadion bij avond, gras met belijning, een bal in beeld. Donker en
            rustig, zodat witte tekst erop leesbaar blijft. Geen juichende
            stockfoto's met een blauwe lucht — dat is een andere club.
        </p>

        <div class="grid sm:grid-cols-[auto,1fr] gap-8 items-start">
            <img src="{{ asset('brand/poster.jpg') }}" alt="Voorbeeld van het beeldmateriaal"
                 class="w-56 rounded-2xl shadow-lg" width="224" height="398" loading="lazy">
            <div>
                <p class="font-semibold mb-2">Tactiekbord als patroon</p>
                <p class="text-sm text-gray-500 leading-relaxed mb-4">
                    De stippellijnen en kruisjes van de poster zitten als CSS-patroon in
                    de klasse <code class="bg-gray-100 px-1.5 py-0.5 rounded text-xs">.tactisch</code>,
                    niet als foto. Dat scheelt ruim een megabyte per pagina en het schaalt
                    naar elk formaat.
                </p>
                <div class="tactisch rounded-2xl h-40 flex items-center justify-center">
                    <span class="payoff text-white/70 text-sm">Plan. Organiseer. Presteer.</span>
                </div>
            </div>
        </div>
    </section>

    {{-- ── Wel en niet ─────────────────────────────────────────────────── --}}
    <section>
        <h2 class="display text-3xl sm:text-4xl mb-8">Wel en niet</h2>
        <div class="grid sm:grid-cols-2 gap-5">
            <div class="rounded-2xl border-2 p-6" style="border-color: var(--brand)">
                <p class="payoff text-xs mb-4" style="color: var(--brand)">Wel</p>
                <ul class="space-y-2.5 text-sm text-gray-700 leading-relaxed">
                    <li>Groen voor alles wat aanklikbaar is.</li>
                    <li>Eén rood element per scherm, en niet meer.</li>
                    <li>Koppen in de smalle cursieve snede.</li>
                    <li>Donkere vlakken voor kop en afsluiting, wit ertussen.</li>
                    <li>Ruime witruimte; de kaarten mogen ademen.</li>
                </ul>
            </div>
            <div class="rounded-2xl border-2 p-6" style="border-color: var(--accent)">
                <p class="payoff text-xs mb-4" style="color: var(--accent)">Niet</p>
                <ul class="space-y-2.5 text-sm text-gray-700 leading-relaxed">
                    <li>Een waaier aan icoonkleuren. Dat maakt het groen betekenisloos.</li>
                    <li>Rood naast groen als tweede hoofdkleur.</li>
                    <li>Hele alinea's in de cursieve kopletter.</li>
                    <li>Het woordmerk als plaatje waar tekst kan.</li>
                    <li>Het icoon met witruimte eromheen; het wordt dan een postzegel.</li>
                </ul>
            </div>
        </div>
    </section>
</main>

<footer class="bg-navy-900 text-white/50 py-10 px-6">
    <div class="max-w-5xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4 text-sm">
        <span class="wordmark text-white text-xl leading-none">
            Voetbal<span class="groen">planner</span><span class="tld groen">.nl</span>
        </span>
        <span>&copy; {{ date('Y') }} VoetbalPlanner</span>
        <a href="/" class="hover:text-white transition-colors">Terug naar de site</a>
    </div>
</footer>

</body>
</html>
