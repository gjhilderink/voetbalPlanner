<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Aanvraag ontvangen — VoetbalPlanner</title>
    <meta name="robots" content="noindex">

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif

    @include('partials.analytics')
</head>
<body class="bg-gray-50 text-gray-900 antialiased font-sans min-h-screen flex flex-col">

<nav class="bg-white border-b border-gray-100 shadow-sm">
    <div class="max-w-3xl mx-auto px-6 py-4 flex items-center justify-between">
        <a href="{{ url('/') }}" class="flex items-center gap-2 hover:opacity-80 transition-opacity">
            <svg class="w-7 h-7 text-green-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2zm0 2c1.29 0 2.516.26 3.633.727L13.5 7H10.5L8.367 4.727A7.963 7.963 0 0112 4zM6.25 5.86L8 8.5l-2 3H3.1A8.007 8.007 0 016.25 5.86zm11.5 0A8.007 8.007 0 0120.9 11.5H18l-2-3 1.75-2.64zM10 9h4l1.5 2.5L14 14h-4l-1.5-2.5L10 9zm-4.6 4H8l1.5 3-1.75 2.64A8.007 8.007 0 015.4 13zm13.2 0a8.007 8.007 0 01-1.85 5.64L15 16l1.5-3h2.6zM10.5 17h3l2.133 2.273A7.963 7.963 0 0112 20a7.963 7.963 0 01-3.633-.727L10.5 17z"/>
            </svg>
            <span class="font-bold text-gray-900">VoetbalPlanner</span>
        </a>
    </div>
</nav>

<div class="flex-1 flex items-center justify-center px-6 py-16">
    <div class="max-w-md w-full text-center">
        <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
            <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>

        <h1 class="text-2xl font-bold text-gray-900 mb-3">Aanvraag ontvangen</h1>
        <p class="text-gray-500 leading-relaxed mb-8">
            Bedankt voor uw interesse. We nemen binnen <strong class="text-gray-700">twee werkdagen</strong> contact op
            via het opgegeven e-mailadres om een moment af te spreken. U zit nergens aan vast.
        </p>

        <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
            <a href="{{ url('/') }}"
               class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white font-semibold px-6 py-3 rounded-xl transition-colors text-sm">
                Terug naar home
            </a>
            <a href="{{ route('pricing') }}"
               class="inline-flex items-center gap-2 text-gray-500 hover:text-gray-900 font-medium px-6 py-3 text-sm transition-colors">
                Bekijk de tarieven
            </a>
        </div>
    </div>
</div>

<footer class="border-t border-gray-200 py-6 text-center text-sm text-gray-400">
    &copy; {{ date('Y') }} VoetbalPlanner
</footer>

</body>
</html>
