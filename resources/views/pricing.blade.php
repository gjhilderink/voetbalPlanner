@php
    /** @var \App\Services\PricingContent $prijzen */
    $prijzen = \App\Services\PricingContent::class;

    $perLid     = $prijzen::naarGetal($inhoud['pricing_per_member']);
    $opstart    = $prijzen::naarGetal($inhoud['pricing_setup_fee']);
    $minimum    = $prijzen::naarGetal($inhoud['pricing_minimum']);
    $onderdelen = $prijzen::regels($inhoud['pricing_includes']);

    // Vooraf opgemaakt, zodat er verderop geen klassenaam meer in de opmaak staat.
    $perLidTekst  = $prijzen::bedrag($perLid);
    $opstartTekst = $prijzen::bedrag($opstart);
    $minimumTekst = $prijzen::bedrag($minimum);

    // Het aantal leden waarboven de minimumbijdrage niet meer knelt. Dat getal
    // maakt de drempel navolgbaar: een kleine club ziet dan meteen waaróm er
    // € 595 staat en niet leden x tarief.
    $drempel = $perLid > 0 ? (int) ceil($minimum / $perLid) : 0;

    // Als losse variabele en niet als array rechtstreeks in @json: die
    // directive knipt zijn expressie op komma's om de optionele flags- en
    // depth-argumenten te vinden, en snijdt een array met meer dan twee
    // sleutels dus middendoor.
    $rekenwaarden = [
        'perLid'  => $perLid,
        'opstart' => $opstart,
        'minimum' => $minimum,
        'drempel' => $drempel,
    ];
@endphp
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tarieven — VoetbalPlanner</title>
    <meta name="description" content="Wat kost VoetbalPlanner? {{ $perLidTekst }} per clublid per jaar, ouders en verzorgers gratis. Geen pakketten en geen verborgen kosten. Reken direct uit wat het uw club kost.">
    <meta name="robots" content="index, follow">

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif

    @include('partials.analytics')
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
        <div class="flex items-center gap-5">
            <a href="/" class="text-sm font-medium text-gray-500 hover:text-gray-900 transition-colors hidden sm:inline">Home</a>
            <a href="/admin/login"
               class="bg-green-600 hover:bg-green-700 text-white px-5 py-2 rounded-lg text-sm font-medium transition-colors">
                Inloggen
            </a>
        </div>
    </div>
</nav>

{{-- Hero met de drie bedragen --}}
<section class="bg-gradient-to-br from-green-800 via-green-700 to-emerald-600 text-white py-20 px-6">
    <div class="max-w-5xl mx-auto">
        <div class="text-center">
            <div class="inline-flex items-center gap-2 bg-white/20 rounded-full px-4 py-1.5 text-sm font-medium mb-6">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                </svg>
                Geen pakketten, geen keuzestress
            </div>

            <h1 class="text-4xl sm:text-5xl font-extrabold leading-tight mb-5">{{ $inhoud['pricing_title'] }}</h1>
            <p class="text-green-50 text-lg leading-relaxed max-w-2xl mx-auto">{{ $inhoud['pricing_intro'] }}</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mt-12">
            <div class="bg-white rounded-2xl p-7 text-center text-gray-900 shadow-lg">
                <span class="block text-xs font-semibold uppercase tracking-widest text-green-700 mb-3">Per clublid</span>
                <span class="block text-4xl font-extrabold">{{ $perLidTekst }}</span>
                <span class="block text-gray-500 text-sm mt-2">per jaar</span>
                <p class="text-gray-500 text-sm mt-4 leading-relaxed">Ouders en verzorgers gebruiken de app gratis en tellen niet mee.</p>
            </div>

            <div class="bg-white rounded-2xl p-7 text-center text-gray-900 shadow-lg">
                <span class="block text-xs font-semibold uppercase tracking-widest text-green-700 mb-3">Opstartkosten</span>
                <span class="block text-4xl font-extrabold">{{ $opstartTekst }}</span>
                <span class="block text-gray-500 text-sm mt-2">eenmalig</span>
                <p class="text-gray-500 text-sm mt-4 leading-relaxed">Voor de intake en het inrichten van uw club. Daarna nooit meer.</p>
            </div>

            <div class="bg-white rounded-2xl p-7 text-center text-gray-900 shadow-lg">
                <span class="block text-xs font-semibold uppercase tracking-widest text-green-700 mb-3">Minimum</span>
                <span class="block text-4xl font-extrabold">{{ $minimumTekst }}</span>
                <span class="block text-gray-500 text-sm mt-2">per jaar</span>
                <p class="text-gray-500 text-sm mt-4 leading-relaxed">
                    @if ($drempel > 0)
                        Geldt tot {{ $drempel }} leden; daarboven rekent u gewoon per lid.
                    @else
                        De laagste jaarbijdrage, ongeacht het aantal leden.
                    @endif
                </p>
            </div>
        </div>
    </div>
</section>

{{-- Calculator --}}
<section class="py-20 px-6 bg-gray-50">
    <div class="max-w-4xl mx-auto">
        <div class="text-center mb-10">
            <h2 class="text-3xl font-bold text-gray-900 mb-3">Wat kost het uw club?</h2>
            <p class="text-gray-500 text-lg">Vul het aantal leden in en u ziet het meteen. Geen offerte nodig.</p>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-7 sm:p-9">
            <label for="leden" class="block text-sm font-semibold text-gray-900 mb-2">Aantal clubleden</label>
            <p class="text-gray-500 text-sm mb-4">Spelende en niet-spelende leden. Ouders en verzorgers tellen niet mee — zij gebruiken de app gratis.</p>

            <div class="flex items-center gap-4">
                <input type="number" id="leden" min="0" max="10000" step="1" value="300" inputmode="numeric"
                       class="w-32 shrink-0 rounded-xl border border-gray-300 px-4 py-3 text-lg font-semibold text-gray-900 focus:border-green-600 focus:ring-2 focus:ring-green-600/30 focus:outline-none">
                <input type="range" id="leden-schuif" min="0" max="1500" step="10" value="300"
                       class="w-full accent-green-600" aria-label="Aantal clubleden">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-8">
                <div class="rounded-xl bg-gray-50 border border-gray-100 p-5">
                    <span class="block text-xs font-semibold uppercase tracking-widest text-gray-500 mb-2">Per jaar</span>
                    <span id="uitkomst-jaar" class="block text-2xl font-extrabold text-gray-900">—</span>
                    <span id="uitkomst-maand" class="block text-gray-500 text-sm mt-1"></span>
                </div>
                <div class="rounded-xl bg-gray-50 border border-gray-100 p-5">
                    <span class="block text-xs font-semibold uppercase tracking-widest text-gray-500 mb-2">Eenmalig</span>
                    <span id="uitkomst-opstart" class="block text-2xl font-extrabold text-gray-900">—</span>
                    <span class="block text-gray-500 text-sm mt-1">intake en inrichting</span>
                </div>
                <div class="rounded-xl bg-green-600 p-5 text-white">
                    <span class="block text-xs font-semibold uppercase tracking-widest text-green-100 mb-2">Eerste jaar</span>
                    <span id="uitkomst-totaal" class="block text-2xl font-extrabold">—</span>
                    <span id="uitkomst-daarna" class="block text-green-100 text-sm mt-1"></span>
                </div>
            </div>

            <p id="uitkomst-melding" class="hidden mt-6 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-sm leading-relaxed px-4 py-3"></p>

            <p id="uitkomst-perlid" class="text-gray-500 text-sm mt-6 leading-relaxed"></p>
        </div>
    </div>
</section>

{{-- Wat er in zit --}}
<section class="py-20 px-6 bg-white">
    <div class="max-w-4xl mx-auto">
        <div class="text-center mb-12">
            <h2 class="text-3xl font-bold text-gray-900 mb-3">Alles zit erin</h2>
            <p class="text-gray-500 text-lg max-w-xl mx-auto">Er is geen instap- of pluspakket. Elke club krijgt hetzelfde platform, compleet.</p>
        </div>

        <ul class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3">
            @foreach ($onderdelen as $onderdeel)
                <li class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-green-600 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                    </svg>
                    <span class="text-gray-600 leading-relaxed">{{ $onderdeel }}</span>
                </li>
            @endforeach
        </ul>
    </div>
</section>

{{-- Geen verborgen kosten en de dataservice --}}
<section class="py-20 px-6 bg-gray-50">
    <div class="max-w-5xl mx-auto grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white rounded-2xl p-8 shadow-sm border border-gray-100">
            <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center mb-5">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <h3 class="text-xl font-semibold text-gray-900 mb-3">{{ $inhoud['pricing_no_hidden_title'] }}</h3>
            <p class="text-gray-500 leading-relaxed">{{ $inhoud['pricing_no_hidden'] }}</p>
        </div>

        <div class="bg-white rounded-2xl p-8 shadow-sm border border-gray-100">
            <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center mb-5">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244"/>
                </svg>
            </div>
            <h3 class="text-xl font-semibold text-gray-900 mb-3">{{ $inhoud['pricing_data_title'] }}</h3>
            <p class="text-gray-500 leading-relaxed">{{ $inhoud['pricing_data_note'] }}</p>
        </div>
    </div>

    @if (trim($inhoud['pricing_fine_print']) !== '')
        <p class="max-w-5xl mx-auto text-gray-400 text-sm leading-relaxed mt-6">{{ $inhoud['pricing_fine_print'] }}</p>
    @endif
</section>

{{-- CTA --}}
<section class="bg-green-700 py-16 px-6 text-white text-center">
    <div class="max-w-2xl mx-auto">
        <h2 class="text-3xl font-bold mb-4">Klaar om te starten?</h2>
        <p class="text-green-100 text-lg mb-8">Meld uw club aan en wij nemen binnen twee werkdagen contact op.</p>
        <a href="{{ route('club-request.create') }}"
           class="inline-flex items-center gap-2 bg-white text-green-700 font-semibold px-8 py-3.5 rounded-xl hover:bg-green-50 transition-colors text-base shadow-lg">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
            </svg>
            Club aanmelden
        </a>
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
        <div class="flex items-center gap-5">
            <a href="/" class="hover:text-white transition-colors">Home</a>
            <a href="{{ route('privacy') }}" class="hover:text-white transition-colors">Privacyverklaring</a>
            <a href="/admin/login" class="hover:text-white transition-colors">Inloggen</a>
        </div>
    </div>
</footer>

<script>
    (function () {
        // De bedragen komen uit dezelfde instellingen als de kaartjes hierboven,
        // zodat de calculator er niet zijn eigen waarheid op na kan houden.
        var T = @json($rekenwaarden);

        var euro = new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' });

        var veld    = document.getElementById('leden');
        var schuif  = document.getElementById('leden-schuif');
        var jaar    = document.getElementById('uitkomst-jaar');
        var maand   = document.getElementById('uitkomst-maand');
        var opstart = document.getElementById('uitkomst-opstart');
        var totaal  = document.getElementById('uitkomst-totaal');
        var daarna  = document.getElementById('uitkomst-daarna');
        var melding = document.getElementById('uitkomst-melding');
        var perLid  = document.getElementById('uitkomst-perlid');

        if (!veld || !schuif || !jaar) return;

        function reken() {
            var leden = parseInt(veld.value, 10);
            if (isNaN(leden) || leden < 0) leden = 0;

            var naarLeden = leden * T.perLid;
            var perJaar   = Math.max(naarLeden, T.minimum);
            var onderMin  = naarLeden < T.minimum;

            jaar.textContent    = euro.format(perJaar);
            maand.textContent   = 'ongeveer ' + euro.format(perJaar / 12) + ' per maand';
            opstart.textContent = euro.format(T.opstart);
            totaal.textContent  = euro.format(perJaar + T.opstart);
            daarna.textContent  = 'daarna ' + euro.format(perJaar) + ' per jaar';

            // Waaróm er meer staat dan leden x tarief hoort erbij; anders lijkt
            // het alsof de calculator zich vergist.
            if (onderMin) {
                melding.textContent = leden === 0
                    ? 'Vul het aantal leden in. Tot ' + T.drempel + ' leden geldt hoe dan ook de minimumbijdrage van ' + euro.format(T.minimum) + ' per jaar.'
                    : leden + ' leden x ' + euro.format(T.perLid) + ' is ' + euro.format(naarLeden) + '. Dat is minder dan de minimumbijdrage, dus rekenen we ' + euro.format(T.minimum) + '. Vanaf ' + T.drempel + ' leden betaalt u gewoon per lid.';
                melding.classList.remove('hidden');
            } else {
                melding.classList.add('hidden');
            }

            perLid.textContent = leden > 0
                ? 'Dat komt neer op ' + euro.format(perJaar / leden / 12) + ' per lid per maand. Ouders en verzorgers zitten daar gratis bij in.'
                : '';
        }

        veld.addEventListener('input', function () {
            var leden = parseInt(veld.value, 10);
            // De schuif loopt tot 1500; een grotere club typt het getal gewoon in
            // en dan blijft de schuif rechts staan in plaats van te springen.
            if (!isNaN(leden)) schuif.value = Math.min(Math.max(leden, 0), parseInt(schuif.max, 10));
            reken();
        });

        schuif.addEventListener('input', function () {
            veld.value = schuif.value;
            reken();
        });

        reken();
    })();
</script>

</body>
</html>
