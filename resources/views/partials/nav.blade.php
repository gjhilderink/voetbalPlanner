{{--
    De navigatiebalk van de publieke site, op één plek.

    Op een telefoon past het woordmerk niet naast twee menu-items: "Tarieven"
    liep dwars over de letters heen. Vandaar een hamburger onder de 640 px, en
    het volle menu daarboven.

    Gebruik: @include('partials.nav') — de partial kijkt zelf welke pagina actief
    is, zodat het huidige item niet naar zichzelf linkt.
--}}

@php
    $huidig = request()->path();
    $items = [
        ['label' => 'Home',     'url' => url('/'),        'actief' => $huidig === '/'],
        ['label' => 'Tarieven', 'url' => route('pricing'), 'actief' => $huidig === 'tarieven'],
    ];
@endphp

<div class="hoekstreep"></div>
<nav class="bg-navy-900 sticky top-0 z-50">
    <div class="max-w-6xl mx-auto px-5 sm:px-6 py-3.5 flex items-center justify-between gap-3">
        {{-- shrink-0: zonder dit knijpt de flexbox het woordmerk samen en
             schuiven de letters over het menu heen. --}}
        <a href="{{ url('/') }}" class="flex items-center gap-2.5 shrink-0">
            <img src="{{ asset('brand/app-icon-180.png') }}" alt=""
                 class="w-8 h-8 sm:w-9 sm:h-9 rounded-lg" width="36" height="36">
            <span class="wordmark text-white text-xl sm:text-2xl leading-none">
                Voetbal<span class="groen">planner</span><span class="tld groen">.nl</span>
            </span>
        </a>

        {{-- Vanaf sm: gewoon naast elkaar. --}}
        <div class="hidden sm:flex items-center gap-5">
            @foreach ($items as $item)
                @if (! $item['actief'])
                    <a href="{{ $item['url'] }}"
                       class="text-sm font-medium text-white/70 hover:text-white transition-colors">{{ $item['label'] }}</a>
                @endif
            @endforeach
            <a href="/admin/login" class="btn-brand px-5 py-2 rounded-lg text-sm font-semibold">Inloggen</a>
        </div>

        {{-- Daaronder: één knop. --}}
        <button type="button" id="nav-toggle" aria-controls="nav-menu" aria-expanded="false"
                class="sm:hidden shrink-0 p-2 -mr-2 text-white/80 hover:text-white transition-colors">
            <span class="sr-only">Menu</span>
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5"/>
            </svg>
        </button>
    </div>

    <div id="nav-menu" class="sm:hidden hidden border-t border-white/10">
        <div class="px-5 py-3 flex flex-col">
            @foreach ($items as $item)
                @if (! $item['actief'])
                    <a href="{{ $item['url'] }}"
                       class="py-3 text-white/80 hover:text-white transition-colors border-b border-white/5">{{ $item['label'] }}</a>
                @endif
            @endforeach
            <a href="{{ route('club-request.create') }}"
               class="py-3 text-white/80 hover:text-white transition-colors border-b border-white/5">Club aanmelden</a>
            <a href="/admin/login" class="btn-brand mt-3 mb-1 px-5 py-2.5 rounded-lg text-sm font-semibold text-center">Inloggen</a>
        </div>
    </div>
</nav>

<script>
    (function () {
        var knop = document.getElementById('nav-toggle');
        var menu = document.getElementById('nav-menu');
        if (!knop || !menu) return;
        knop.addEventListener('click', function () {
            var open = menu.classList.toggle('hidden') === false;
            knop.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    })();
</script>
