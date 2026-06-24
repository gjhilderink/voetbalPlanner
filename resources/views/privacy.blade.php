<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — VoetbalPlanner</title>
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
        <h1 class="text-3xl sm:text-4xl font-extrabold mb-3">{{ $title }}</h1>
        @if($updatedAt)
            <p class="text-green-100">Laatst bijgewerkt: {{ $updatedAt->format('d-m-Y') }}</p>
        @endif
    </div>
</section>

{{-- Inhoud (beheerd via admin → Documentatie → Juridische pagina's) --}}
<section class="py-14 px-6 bg-white">
    <div class="max-w-3xl mx-auto prose-content">
        <style>
            .prose-content h1 { font-size: 1.6rem; font-weight: 700; color: #111827; margin: 1.5rem 0 .75rem; }
            .prose-content h2 { font-size: 1.35rem; font-weight: 700; color: #111827; margin-top: 2.25rem; margin-bottom: .75rem; }
            .prose-content h3 { font-size: 1.05rem; font-weight: 600; color: #111827; margin-top: 1.5rem; margin-bottom: .5rem; }
            .prose-content p, .prose-content li { color: #4b5563; line-height: 1.7; }
            .prose-content p { margin-bottom: 1rem; }
            .prose-content ul { list-style: disc; padding-left: 1.4rem; margin-bottom: 1rem; }
            .prose-content ol { list-style: decimal; padding-left: 1.4rem; margin-bottom: 1rem; }
            .prose-content li { margin-bottom: .35rem; }
            .prose-content a { color: #15803d; text-decoration: underline; }
            .prose-content strong { color: #111827; }
        </style>

        {!! $body !!}
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
