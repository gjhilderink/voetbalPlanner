<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>VoetbalPlanner — Slimme clubplanning</title>
    <meta name="description" content="VoetbalPlanner helpt voetbalclubs hun wedstrijden, opstellingen, bardiensten en wisselverzoeken eenvoudig te beheren.">

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
        <div class="flex items-center gap-2">
            <svg class="w-8 h-8 text-green-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2zm0 2c1.29 0 2.516.26 3.633.727L13.5 7H10.5L8.367 4.727A7.963 7.963 0 0112 4zM6.25 5.86L8 8.5l-2 3H3.1A8.007 8.007 0 016.25 5.86zm11.5 0A8.007 8.007 0 0120.9 11.5H18l-2-3 1.75-2.64zM10 9h4l1.5 2.5L14 14h-4l-1.5-2.5L10 9zm-4.6 4H8l1.5 3-1.75 2.64A8.007 8.007 0 015.4 13zm13.2 0a8.007 8.007 0 01-1.85 5.64L15 16l1.5-3h2.6zM10.5 17h3l2.133 2.273A7.963 7.963 0 0112 20a7.963 7.963 0 01-3.633-.727L10.5 17z"/>
            </svg>
            <span class="text-xl font-bold text-gray-900 tracking-tight">VoetbalPlanner</span>
        </div>
        <a href="/admin/login"
           class="bg-green-600 hover:bg-green-700 text-white px-5 py-2 rounded-lg text-sm font-medium transition-colors">
            Inloggen
        </a>
    </div>
</nav>

{{-- Hero --}}
<section class="bg-gradient-to-br from-green-800 via-green-700 to-emerald-600 text-white py-24 px-6">
    <div class="max-w-4xl mx-auto text-center">
        <div class="inline-flex items-center gap-2 bg-white/20 rounded-full px-4 py-1.5 text-sm font-medium mb-6">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
            </svg>
            Klaar voor uw club
        </div>

        <h1 class="text-4xl sm:text-5xl font-extrabold leading-tight mb-6">
            De slimme voetbalplanner<br class="hidden sm:block"> voor jouw club
        </h1>
        <p class="text-lg sm:text-xl text-green-100 max-w-2xl mx-auto mb-10 leading-relaxed">
            Beheer wedstrijden, opstellingen, bardiensten en wisselverzoeken vanuit één overzichtelijk platform. Gekoppeld met Sportlink, bereikbaar via de app.
        </p>

        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="/admin/login"
               class="inline-flex items-center justify-center gap-2 bg-white text-green-700 font-semibold px-8 py-3.5 rounded-xl hover:bg-green-50 transition-colors text-base shadow-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75"/>
                </svg>
                Inloggen
            </a>
            <a href="{{ route('club-request.create') }}"
               class="inline-flex items-center justify-center gap-2 bg-green-900/60 border border-white/30 text-white font-semibold px-8 py-3.5 rounded-xl hover:bg-green-900/80 transition-colors text-base">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                </svg>
                Club aanmelden
            </a>
        </div>
    </div>
</section>

{{-- Features --}}
<section class="py-20 px-6 bg-gray-50">
    <div class="max-w-6xl mx-auto">
        <div class="text-center mb-14">
            <h2 class="text-3xl font-bold text-gray-900 mb-3">Alles in één platform</h2>
            <p class="text-gray-500 text-lg max-w-xl mx-auto">VoetbalPlanner neemt het administratieve werk uit handen zodat u zich kunt focussen op het echte voetbal.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            {{-- Feature 1 --}}
            <div class="bg-white rounded-2xl p-7 shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center mb-5">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 9v7.5"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Wedstrijden &amp; opstellingen</h3>
                <p class="text-gray-500 text-sm leading-relaxed">Overzicht van alle wedstrijden per team. Stel eenvoudig de opstelling samen en registreer doelpunten en uitkomsten.</p>
            </div>

            {{-- Feature 2 --}}
            <div class="bg-white rounded-2xl p-7 shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center mb-5">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Bardienst beheer</h3>
                <p class="text-gray-500 text-sm leading-relaxed">Plan bardiensten voor al uw teams en ken leden toe aan diensten. Automatische meldingen via WhatsApp.</p>
            </div>

            {{-- Feature 3 --}}
            <div class="bg-white rounded-2xl p-7 shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center mb-5">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Wisselverzoeken</h3>
                <p class="text-gray-500 text-sm leading-relaxed">Leden kunnen via de app een wisselverzoek indienen voor een bardienst. Beheerders keuren goed of af met één tik.</p>
            </div>

            {{-- Feature 4 --}}
            <div class="bg-white rounded-2xl p-7 shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center mb-5">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Sportlink koppeling</h3>
                <p class="text-gray-500 text-sm leading-relaxed">Automatisch synchroniseren van teams, leden en wedstrijden vanuit Sportlink. Altijd actuele gegevens zonder handmatige invoer.</p>
            </div>

            {{-- Feature 5 --}}
            <div class="bg-white rounded-2xl p-7 shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center mb-5">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 8.25h3m-3 3h3m-3 3h3"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">App voor leden</h3>
                <p class="text-gray-500 text-sm leading-relaxed">Leden gebruiken de VoetbalPlanner-app op hun telefoon. Wedstrijden bekijken, bardiensten inzien en wisselverzoeken indienen.</p>
            </div>

            {{-- Feature 6 --}}
            <div class="bg-white rounded-2xl p-7 shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="w-12 h-12 bg-orange-100 rounded-xl flex items-center justify-center mb-5">
                    <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Volledig clubbeheer</h3>
                <p class="text-gray-500 text-sm leading-relaxed">Beheer meerdere teams, rollen en gebruikers vanuit één beheerpaneel. Elk team heeft zijn eigen ledenadministratie.</p>
            </div>
        </div>
    </div>
</section>

{{-- Hoe het werkt --}}
<section class="py-20 px-6 bg-white">
    <div class="max-w-4xl mx-auto">
        <div class="text-center mb-14">
            <h2 class="text-3xl font-bold text-gray-900 mb-3">In drie stappen van start</h2>
            <p class="text-gray-500 text-lg">Wij regelen de koppeling en inrichting — uw club is binnen no-time operationeel.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-8">
            <div class="text-center">
                <div class="w-14 h-14 bg-green-600 text-white rounded-2xl flex items-center justify-center text-2xl font-bold mx-auto mb-4">1</div>
                <h3 class="font-semibold text-gray-900 mb-2">Club aanmelden</h3>
                <p class="text-gray-500 text-sm leading-relaxed">Vul het aanmeldformulier in met uw clubgegevens en Sportlink-inloggegevens.</p>
            </div>
            <div class="text-center">
                <div class="w-14 h-14 bg-green-600 text-white rounded-2xl flex items-center justify-center text-2xl font-bold mx-auto mb-4">2</div>
                <h3 class="font-semibold text-gray-900 mb-2">Wij richten in</h3>
                <p class="text-gray-500 text-sm leading-relaxed">We koppelen Sportlink, importeren uw teams en leden en stellen het platform in.</p>
            </div>
            <div class="text-center">
                <div class="w-14 h-14 bg-green-600 text-white rounded-2xl flex items-center justify-center text-2xl font-bold mx-auto mb-4">3</div>
                <h3 class="font-semibold text-gray-900 mb-2">Aan de slag!</h3>
                <p class="text-gray-500 text-sm leading-relaxed">Uw beheerders ontvangen inloggegevens en leden kunnen de app downloaden.</p>
            </div>
        </div>
    </div>
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
        <a href="/admin/login" class="hover:text-white transition-colors">Inloggen</a>
    </div>
</footer>

</body>
</html>
