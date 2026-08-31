<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>VoetbalPlanner — Slimme clubplanning</title>
    <meta name="description" content="VoetbalPlanner helpt voetbalclubs hun wedstrijden, opstellingen, bardiensten, rijschema's, clubdocumentatie en communicatie eenvoudig te beheren via één app.">

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif

    @include('partials.brand')
    @include('partials.analytics')
</head>
<body class="bg-white text-gray-900 antialiased font-sans">

@include('partials.nav')

{{-- Hero --}}
<section class="tactisch text-white py-20 px-6">
    <div class="max-w-6xl mx-auto grid lg:grid-cols-[1.15fr,0.85fr] gap-12 items-center">
        <div>
            {{-- Het enige rood op deze pagina, en daarom valt het op. --}}
            <div class="inline-flex items-center gap-2 rounded-full px-3.5 py-1 text-xs font-semibold uppercase tracking-widest mb-7"
                 style="background:rgba(230,48,39,.15); color:#FF8A82; border:1px solid rgba(230,48,39,.35)">
                Klaar voor jouw club
            </div>

            <h1 class="display text-4xl sm:text-6xl lg:text-7xl mb-6">
                De slimme voetbalplanner<br>
                <span style="color:var(--brand)">voor jouw club</span>
            </h1>

            <p class="payoff text-white/60 text-sm mb-7">Plan. Organiseer. Presteer.</p>

            <p class="text-lg text-white/75 max-w-xl mb-9 leading-relaxed">
                Beheer wedstrijden, opstellingen, bardiensten, rijschema's en communicatie vanuit één
                overzichtelijk platform. Gekoppeld met Sportlink, bereikbaar via de app.
            </p>

            <div class="flex flex-col sm:flex-row gap-3">
                <a href="{{ route('club-request.create') }}"
                   class="btn-brand inline-flex items-center justify-center gap-2 font-semibold px-7 py-3.5 rounded-xl text-base">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                    </svg>
                    Club aanmelden
                </a>
                <a href="/admin/login"
                   class="inline-flex items-center justify-center gap-2 border border-white/25 hover:border-white/50 text-white font-semibold px-7 py-3.5 rounded-xl transition-colors text-base">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75"/>
                    </svg>
                    Inloggen
                </a>
            </div>

            {{-- Beschikbaar voor iOS & Android --}}
            <div class="mt-10 flex flex-col items-start gap-3">
                <span class="payoff text-xs text-white/45">Beschikbaar voor</span>
                <div class="flex flex-wrap items-center gap-3">
                    {{-- Allebei de winkels; de apps staan er. --}}
                    <a href="https://apps.apple.com/nl/app/voetbalplanner/id6773810390"
                       target="_blank" rel="noopener"
                       class="inline-flex items-center gap-2 bg-white/10 hover:bg-white/20 border border-white/20 hover:border-white/40 rounded-xl px-4 py-2.5 text-white transition-colors">
                        <svg class="w-5 h-5" viewBox="0 0 384 512" fill="currentColor" aria-hidden="true">
                            <path d="M318.7 268.7c-.2-36.7 16.4-64.4 50-84.8-18.8-26.9-47.2-41.7-84.7-44.6-35.5-2.8-74.3 20.7-88.5 20.7-15 0-49.4-19.7-76.4-19.7C63.3 141.2 4 184.8 4 273.5q0 39.3 14.4 81.2c12.8 36.7 59 126.7 107.2 125.2 25.2-.6 43-17.9 75.8-17.9 31.8 0 48.3 17.9 76.4 17.9 48.6-.7 90.4-82.5 102.6-119.3-65.2-30.7-61.7-90-61.7-91.9zM262.1 104.5c27.3-32.4 24.8-61.9 24-72.5-24.1 1.4-52 16.4-67.9 34.9-17.5 19.8-27.8 44.3-25.6 71.9 26.1 2 49.9-11.4 69.5-34.3z"/>
                        </svg>
                        <span class="font-semibold text-sm">iOS</span>
                    </a>
                    <a href="https://play.google.com/store/apps/details?id=com.mycompany.voetbalplanner"
                       target="_blank" rel="noopener"
                       class="inline-flex items-center gap-2 bg-white/10 hover:bg-white/20 border border-white/20 hover:border-white/40 rounded-xl px-4 py-2.5 text-white transition-colors">
                        <svg class="w-5 h-5" viewBox="0 0 576 512" fill="currentColor" aria-hidden="true">
                            <path d="M420.55 301.93a24 24 0 1 1 24-24 24 24 0 0 1-24 24m-265.1 0a24 24 0 1 1 24-24 24 24 0 0 1-24 24m273.7-144.48 47.94-83a10 10 0 1 0-17.27-10l-48.54 84.07a301.25 301.25 0 0 0-246.56 0L69.34 64.45a10 10 0 1 0-17.27 10l47.94 83C13.72 201.05 1.24 292 1.24 292H575.55s-12.48-90.95-98-134.55"/>
                        </svg>
                        <span class="font-semibold text-sm">Android</span>
                    </a>
                </div>
            </div>
        </div>

        {{-- Het beeldmateriaal uit de huisstijl. Als decoratie: de tekst staat
             er al naast, dus een schermlezer heeft er niets aan. --}}
        <div class="hidden lg:block">
            <img src="{{ asset('brand/poster.jpg') }}" alt=""
                 class="w-full max-w-sm mx-auto rounded-2xl shadow-2xl ring-1 ring-white/10"
                 width="720" height="1279" loading="lazy">
        </div>
    </div>
</section>
{{-- Features --}}
<section class="py-20 px-6 bg-gray-50">
    <div class="max-w-6xl mx-auto">
        <div class="text-center mb-14">
            <h2 class="display text-4xl sm:text-5xl text-navy-900 mb-4">Alles in <span class="streep">één platform</span></h2>
            <p class="text-gray-500 text-lg max-w-xl mx-auto">VoetbalPlanner neemt het administratieve werk uit handen zodat u zich kunt focussen op het echte voetbal.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            {{-- Feature 1 --}}
            <div class="bg-white rounded-2xl p-7 shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="w-12 h-12 bg-brand-50 rounded-xl flex items-center justify-center mb-5">
                    <svg class="w-6 h-6 text-brand-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 9v7.5"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Wedstrijden &amp; opstellingen</h3>
                <p class="text-gray-500 text-sm leading-relaxed">Overzicht van alle wedstrijden per team. Stel eenvoudig de opstelling samen en registreer doelpunten en uitkomsten.</p>
            </div>

            {{-- Feature 2 --}}
            <div class="bg-white rounded-2xl p-7 shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="w-12 h-12 bg-brand-50 rounded-xl flex items-center justify-center mb-5">
                    <svg class="w-6 h-6 text-brand-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Bardienst beheer</h3>
                <p class="text-gray-500 text-sm leading-relaxed">Plan bardiensten voor al uw teams en ken leden toe aan diensten. Automatische meldingen via WhatsApp.</p>
            </div>

            {{-- Feature 3 --}}
            <div class="bg-white rounded-2xl p-7 shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="w-12 h-12 bg-brand-50 rounded-xl flex items-center justify-center mb-5">
                    <svg class="w-6 h-6 text-brand-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Wisselverzoeken</h3>
                <p class="text-gray-500 text-sm leading-relaxed">Leden kunnen via de app een wisselverzoek indienen voor een bardienst. Beheerders keuren goed of af met één tik.</p>
            </div>

            {{-- Feature 4 --}}
            <div class="bg-white rounded-2xl p-7 shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="w-12 h-12 bg-brand-50 rounded-xl flex items-center justify-center mb-5">
                    <svg class="w-6 h-6 text-brand-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Sportlink koppeling</h3>
                <p class="text-gray-500 text-sm leading-relaxed">Automatisch synchroniseren van teams, leden en wedstrijden vanuit Sportlink. Altijd actuele gegevens zonder handmatige invoer.</p>
            </div>

            {{-- Feature 5 --}}
            <div class="bg-white rounded-2xl p-7 shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="w-12 h-12 bg-brand-50 rounded-xl flex items-center justify-center mb-5">
                    <svg class="w-6 h-6 text-brand-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 8.25h3m-3 3h3m-3 3h3"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">App voor leden</h3>
                <p class="text-gray-500 text-sm leading-relaxed">Leden gebruiken de VoetbalPlanner-app op hun telefoon. Wedstrijden, bardiensten, rijschema, chat, documentatie en wisselverzoeken in één app.</p>
            </div>

            {{-- Feature 6 --}}
            <div class="bg-white rounded-2xl p-7 shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="w-12 h-12 bg-brand-50 rounded-xl flex items-center justify-center mb-5">
                    <svg class="w-6 h-6 text-brand-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Volledig clubbeheer</h3>
                <p class="text-gray-500 text-sm leading-relaxed">Beheer meerdere teams, rollen en gebruikers vanuit één beheerpaneel. Elk team heeft zijn eigen ledenadministratie.</p>
            </div>

            {{-- Feature 7 --}}
            <div class="bg-white rounded-2xl p-7 shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="w-12 h-12 bg-brand-50 rounded-xl flex items-center justify-center mb-5">
                    <svg class="w-6 h-6 text-brand-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">In-app communicatie</h3>
                <p class="text-gray-500 text-sm leading-relaxed">Teamchat, groepsgesprekken, staffgroepen en directe berichten — alles binnen de app, geen externe WhatsApp-groepen meer nodig. Met push-meldingen op de telefoon mist niemand een bericht.</p>
            </div>

            {{-- Feature 8 --}}
            <div class="bg-white rounded-2xl p-7 shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="w-12 h-12 bg-brand-50 rounded-xl flex items-center justify-center mb-5">
                    <svg class="w-6 h-6 text-brand-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Rijschema</h3>
                <p class="text-gray-500 text-sm leading-relaxed">Leden zien direct wie rijdt naar welke wedstrijd. Beheerders stellen het rijschema per wedstrijd in — minder losse appjes, meer overzicht.</p>
            </div>

            {{-- Feature 9 --}}
            <div class="bg-white rounded-2xl p-7 shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="w-12 h-12 bg-brand-50 rounded-xl flex items-center justify-center mb-5">
                    <svg class="w-6 h-6 text-brand-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Clubdocumentatie</h3>
                <p class="text-gray-500 text-sm leading-relaxed">Reglementen, spelershandleidingen en clubdocumenten altijd bij de hand via de app. Geen losse pdf's meer rondsturen.</p>
            </div>

            {{-- Feature 10 --}}
            <div class="bg-white rounded-2xl p-7 shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="w-12 h-12 bg-brand-50 rounded-xl flex items-center justify-center mb-5">
                    <svg class="w-6 h-6 text-brand-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Push-meldingen</h3>
                <p class="text-gray-500 text-sm leading-relaxed">Leden krijgen direct een melding op hun telefoon bij een nieuw chatbericht, ook als de app dicht is. Een badge op het app-icoon toont het aantal ongelezen berichten.</p>
            </div>

            {{-- Feature 11 --}}
            <div class="bg-white rounded-2xl p-7 shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="w-12 h-12 bg-brand-50 rounded-xl flex items-center justify-center mb-5">
                    <svg class="w-6 h-6 text-brand-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Ouder-toegang &amp; meerdere teams</h3>
                <p class="text-gray-500 text-sm leading-relaxed">Ouders volgen de wedstrijden, bardiensten en teamchat van hun kind(eren). Wie bij meerdere teams hoort, wisselt met één tik tussen teams.</p>
            </div>
        </div>
    </div>
</section>

{{-- Hoe het werkt --}}
<section class="py-20 px-6 bg-white">
    <div class="max-w-4xl mx-auto">
        <div class="text-center mb-14">
            <h2 class="display text-4xl sm:text-5xl text-navy-900 mb-4">In <span class="streep">drie stappen</span> van start</h2>
            <p class="text-gray-500 text-lg">Wij regelen de koppeling en inrichting — uw club is binnen no-time operationeel.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-8">
            <div class="text-center">
                <div class="w-14 h-14 bg-brand-500 text-white rounded-2xl flex items-center justify-center text-2xl font-bold mx-auto mb-4 display">1</div>
                <h3 class="font-semibold text-gray-900 mb-2">Club aanmelden</h3>
                <p class="text-gray-500 text-sm leading-relaxed">Vul het aanmeldformulier in met uw clubgegevens en Sportlink-inloggegevens.</p>
            </div>
            <div class="text-center">
                <div class="w-14 h-14 bg-brand-500 text-white rounded-2xl flex items-center justify-center text-2xl font-bold mx-auto mb-4 display">2</div>
                <h3 class="font-semibold text-gray-900 mb-2">Wij richten in</h3>
                <p class="text-gray-500 text-sm leading-relaxed">We koppelen Sportlink, importeren uw teams en leden en stellen het platform in.</p>
            </div>
            <div class="text-center">
                <div class="w-14 h-14 bg-brand-500 text-white rounded-2xl flex items-center justify-center text-2xl font-bold mx-auto mb-4 display">3</div>
                <h3 class="font-semibold text-gray-900 mb-2">Aan de slag!</h3>
                <p class="text-gray-500 text-sm leading-relaxed">Uw beheerders ontvangen inloggegevens en leden kunnen de app downloaden.</p>
            </div>
        </div>
    </div>
</section>

{{-- CTA --}}
<section class="tactisch py-20 px-6 text-white text-center">
    <div class="max-w-2xl mx-auto">
        <h2 class="display text-4xl sm:text-5xl mb-5">Klaar om te starten?</h2>
        <p class="text-white/70 text-lg mb-9">
            Meld je club aan, of kijk eerst vrijblijvend mee in een demo.
            We nemen binnen twee werkdagen contact op.
        </p>
        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <a href="{{ route('club-request.create') }}"
               class="btn-brand inline-flex items-center justify-center gap-2 font-semibold px-8 py-3.5 rounded-xl text-base">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                </svg>
                Club aanmelden
            </a>
            <a href="{{ route('demo-request.create') }}"
               class="inline-flex items-center justify-center gap-2 border border-white/25 hover:border-white/50 text-white font-semibold px-8 py-3.5 rounded-xl transition-colors text-base">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.91 11.672a.375.375 0 010 .656l-5.603 3.113a.375.375 0 01-.557-.328V8.887c0-.286.307-.466.557-.327l5.603 3.112z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Vrijblijvend demo aanvragen
            </a>
        </div>
    </div>
</section>

{{-- Footer --}}
<footer class="bg-navy-900 text-white/50 py-10 px-6 border-t border-white/10">
    <div class="max-w-6xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4 text-sm">
        <div class="flex items-center gap-3">
            <img src="{{ asset('brand/app-icon-180.png') }}" alt="" class="w-7 h-7 rounded-md" width="28" height="28">
            <span class="wordmark text-white text-xl leading-none">
                Voetbal<span class="groen">planner</span><span class="tld groen">.nl</span>
            </span>
        </div>
        <span>&copy; {{ date('Y') }} VoetbalPlanner. Alle rechten voorbehouden.</span>
        <div class="flex flex-wrap items-center justify-center gap-5">
            <a href="{{ route('pricing') }}" class="hover:text-white transition-colors">Tarieven</a>
            <a href="{{ route('privacy') }}" class="hover:text-white transition-colors">Privacyverklaring</a>
            <a href="{{ route('brandguide') }}" class="hover:text-white transition-colors">Huisstijl</a>
            <a href="/admin/login" class="hover:text-white transition-colors">Inloggen</a>
        </div>
    </div>
</footer>
{{-- Cookiemelding --}}
<div id="cookie-notice" style="display:none" class="fixed bottom-0 inset-x-0 z-50 p-4">
    <div class="max-w-4xl mx-auto bg-gray-900 text-gray-100 rounded-xl shadow-lg p-5 flex flex-col sm:flex-row items-start sm:items-center gap-4">
        <p class="text-sm leading-relaxed flex-1">
            We gebruiken alleen functionele cookies om deze website goed te laten werken. Door de site te blijven gebruiken ga je hiermee akkoord.
            <a href="{{ route('privacy') }}" class="underline hover:text-white">Meer info</a>.
        </p>
        <button id="cookie-accept" type="button"
                class="btn-brand shrink-0 px-5 py-2 rounded-lg text-sm font-semibold">
            Akkoord
        </button>
    </div>
</div>
<script>
    (function () {
        try {
            var KEY = 'vp_cookie_ok';
            var el = document.getElementById('cookie-notice');
            if (!el) return;
            if (!localStorage.getItem(KEY)) el.style.display = '';
            var btn = document.getElementById('cookie-accept');
            if (btn) btn.addEventListener('click', function () {
                try { localStorage.setItem(KEY, '1'); } catch (e) {}
                el.style.display = 'none';
            });
        } catch (e) {}
    })();
</script>

</body>
</html>
