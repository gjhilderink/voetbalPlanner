<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Club aanmelden — VoetbalPlanner</title>

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif

    @if (($recaptchaEnabled ?? false) && ($recaptchaSiteKey ?? ''))
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    @endif
</head>
<body class="bg-gray-50 text-gray-900 antialiased font-sans min-h-screen">

{{-- Navigatie --}}
<nav class="bg-white border-b border-gray-100 shadow-sm">
    <div class="max-w-3xl mx-auto px-6 py-4 flex items-center justify-between">
        <a href="{{ url('/') }}" class="flex items-center gap-2 hover:opacity-80 transition-opacity">
            <svg class="w-7 h-7 text-green-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2zm0 2c1.29 0 2.516.26 3.633.727L13.5 7H10.5L8.367 4.727A7.963 7.963 0 0112 4zM6.25 5.86L8 8.5l-2 3H3.1A8.007 8.007 0 016.25 5.86zm11.5 0A8.007 8.007 0 0120.9 11.5H18l-2-3 1.75-2.64zM10 9h4l1.5 2.5L14 14h-4l-1.5-2.5L10 9zm-4.6 4H8l1.5 3-1.75 2.64A8.007 8.007 0 015.4 13zm13.2 0a8.007 8.007 0 01-1.85 5.64L15 16l1.5-3h2.6zM10.5 17h3l2.133 2.273A7.963 7.963 0 0112 20a7.963 7.963 0 01-3.633-.727L10.5 17z"/>
            </svg>
            <span class="font-bold text-gray-900">VoetbalPlanner</span>
        </a>
        <a href="/admin/login" class="text-sm text-gray-500 hover:text-gray-900 transition-colors">Inloggen</a>
    </div>
</nav>

<div class="max-w-3xl mx-auto px-6 py-12">

    {{-- Header --}}
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Club aanmelden</h1>
        <p class="text-gray-500 text-lg leading-relaxed">
            Vul onderstaand formulier in. Wij nemen binnen twee werkdagen contact met u op om de inrichting te bespreken.
        </p>
    </div>

    @if ($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 mb-6">
            <p class="font-semibold mb-1">Er zijn fouten opgetreden:</p>
            <ul class="list-disc list-inside text-sm space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('club-request.store') }}" class="space-y-8">
        @csrf

        {{-- Clubgegevens --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="bg-gray-50 border-b border-gray-200 px-6 py-4">
                <h2 class="font-semibold text-gray-900">Clubgegevens</h2>
                <p class="text-sm text-gray-500 mt-0.5">Informatie over de club en contactpersoon.</p>
            </div>
            <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-5">

                <div class="sm:col-span-2">
                    <label for="club_name" class="block text-sm font-medium text-gray-700 mb-1.5">Clubnaam <span class="text-red-500">*</span></label>
                    <input type="text" id="club_name" name="club_name" value="{{ old('club_name') }}" required
                           placeholder="v.v. Voorbeeld"
                           class="w-full rounded-lg border @error('club_name') border-red-400 bg-red-50 @else border-gray-300 @enderror px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                    @error('club_name')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="contact_name" class="block text-sm font-medium text-gray-700 mb-1.5">Naam contactpersoon <span class="text-red-500">*</span></label>
                    <input type="text" id="contact_name" name="contact_name" value="{{ old('contact_name') }}" required
                           placeholder="Jan de Vries"
                           class="w-full rounded-lg border @error('contact_name') border-red-400 bg-red-50 @else border-gray-300 @enderror px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                    @error('contact_name')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">E-mailadres <span class="text-red-500">*</span></label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required
                           placeholder="jan@voetbalclub.nl"
                           class="w-full rounded-lg border @error('email') border-red-400 bg-red-50 @else border-gray-300 @enderror px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                    @error('email')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-1.5">Telefoonnummer</label>
                    <input type="tel" id="phone" name="phone" value="{{ old('phone') }}"
                           placeholder="06-12345678"
                           class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                </div>

            </div>
        </div>

        {{-- Sportlink --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="bg-gray-50 border-b border-gray-200 px-6 py-4">
                <h2 class="font-semibold text-gray-900">Sportlink-inloggegevens</h2>
                <p class="text-sm text-gray-500 mt-0.5">
                    Benodigd voor de automatische koppeling met Sportlink. De gegevens worden versleuteld opgeslagen en uitsluitend gebruikt voor de synchronisatie.
                </p>
            </div>
            <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-5">

                <div>
                    <label for="sportlink_username" class="block text-sm font-medium text-gray-700 mb-1.5">Sportlink gebruikersnaam <span class="text-red-500">*</span></label>
                    <input type="text" id="sportlink_username" name="sportlink_username" value="{{ old('sportlink_username') }}" required
                           autocomplete="off"
                           placeholder="clubadmin@voetbalclub.nl"
                           class="w-full rounded-lg border @error('sportlink_username') border-red-400 bg-red-50 @else border-gray-300 @enderror px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                    @error('sportlink_username')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="sportlink_password" class="block text-sm font-medium text-gray-700 mb-1.5">Sportlink wachtwoord <span class="text-red-500">*</span></label>
                    <input type="password" id="sportlink_password" name="sportlink_password" required
                           autocomplete="new-password"
                           class="w-full rounded-lg border @error('sportlink_password') border-red-400 bg-red-50 @else border-gray-300 @enderror px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                    @error('sportlink_password')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="sm:col-span-2">
                    <div class="flex items-start gap-2 bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 text-sm text-blue-700">
                        <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd"/>
                        </svg>
                        Uw Sportlink-inloggegevens worden AES-256 versleuteld opgeslagen en worden nooit gedeeld met derden.
                    </div>
                </div>

            </div>
        </div>

        {{-- Opmerkingen --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="bg-gray-50 border-b border-gray-200 px-6 py-4">
                <h2 class="font-semibold text-gray-900">Opmerkingen</h2>
                <p class="text-sm text-gray-500 mt-0.5">Optioneel — vermeld hier eventuele bijzonderheden of vragen.</p>
            </div>
            <div class="p-6">
                <textarea id="notes" name="notes" rows="4"
                          placeholder="Bijv. wij hebben 12 teams, waarvan 4 jeugdteams..."
                          class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition resize-y">{{ old('notes') }}</textarea>
            </div>
        </div>

        @if (($recaptchaEnabled ?? false) && ($recaptchaSiteKey ?? ''))
            {{-- Anti-spam --}}
            <div>
                <div class="g-recaptcha" data-sitekey="{{ $recaptchaSiteKey }}"></div>
                @error('captcha')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        @endif

        {{-- Verzenden --}}
        <div class="flex items-center justify-between">
            <a href="{{ url('/') }}" class="text-sm text-gray-500 hover:text-gray-700 transition-colors">&larr; Terug</a>
            <button type="submit"
                    class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white font-semibold px-8 py-3 rounded-xl transition-colors text-sm shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/>
                </svg>
                Aanvraag versturen
            </button>
        </div>

    </form>

</div>

<footer class="mt-12 border-t border-gray-200 py-6 text-center text-sm text-gray-400">
    &copy; {{ date('Y') }} VoetbalPlanner
</footer>

</body>
</html>
