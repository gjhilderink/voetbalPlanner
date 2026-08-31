{{--
    "Download de app"-balk voor wie de site op een telefoon bezoekt.

    De herkenning gebeurt in de browser en niet in Laravel. Twee redenen: een
    reverse proxy bij de hosting zou een server-side variant kunnen cachen en de
    iOS-balk aan een Android-bezoeker serveren, en een iPad meldt zich in de
    user-agent als desktop-Mac — dat verschil is alleen hier te zien, via
    navigator.maxTouchPoints.

    De balk staat ná de menubalk in de pagina, dus hij scrollt weg. Bewust niet
    ín partials/nav: die is sticky, en een melding die altijd in beeld blijft is
    een melding die je gaat wegklikken.
--}}

<div id="app-banner" style="display:none"
     data-ios="{{ config('app_stores.ios') }}"
     data-android="{{ config('app_stores.android') }}"
     class="bg-navy-800 border-b border-white/10">
    <div class="max-w-6xl mx-auto px-4 py-2.5 flex items-center gap-3">
        <img src="{{ asset('brand/app-icon-180.png') }}" alt=""
             class="w-10 h-10 rounded-xl shrink-0" width="40" height="40">

        <div class="min-w-0 flex-1">
            <p class="text-white text-sm font-semibold leading-tight truncate">VoetbalPlanner-app</p>
            {{-- Wordt door het script aangevuld met de juiste winkel. --}}
            <p id="app-banner-store" class="text-white/55 text-xs leading-tight truncate">Gratis te downloaden</p>
        </div>

        <a id="app-banner-link" href="#" target="_blank" rel="noopener"
           class="btn-brand shrink-0 px-4 py-2 rounded-lg text-sm font-semibold">Bekijk</a>

        <button type="button" id="app-banner-close"
                class="shrink-0 p-1.5 -mr-1 text-white/40 hover:text-white/80 transition-colors">
            <span class="sr-only">Melding sluiten</span>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
</div>

<script>
    (function () {
        try {
            var KEY = 'vp_app_banner_ok';
            var balk = document.getElementById('app-banner');
            if (!balk) return;

            var ua = navigator.userAgent || '';

            // Een moderne iPad meldt zich als "Macintosh"; alleen het aantal
            // aanraakpunten verraadt het verschil met een echte Mac.
            var iPad = /iPad/.test(ua)
                || (/Macintosh/.test(ua) && (navigator.maxTouchPoints || 0) > 1);

            var iPhone = /iPhone|iPod/.test(ua) && !iPad;

            // Een Android-telefoon heeft "Mobile" in de user-agent, een
            // Android-tablet niet. Dat is de enige manier om ze uit elkaar te
            // houden zonder aan schermbreedte te gaan meten.
            var android = /Android/.test(ua) && /Mobile/.test(ua);

            if (!iPhone && !android) return;

            var link = document.getElementById('app-banner-link');
            var winkel = document.getElementById('app-banner-store');
            link.href = balk.getAttribute(iPhone ? 'data-ios' : 'data-android');
            winkel.textContent = iPhone
                ? 'Gratis in de App Store'
                : 'Gratis in Google Play';

            if (!localStorage.getItem(KEY)) balk.style.display = '';

            var sluit = document.getElementById('app-banner-close');
            if (sluit) sluit.addEventListener('click', function () {
                try { localStorage.setItem(KEY, '1'); } catch (e) {}
                balk.style.display = 'none';
            });
        } catch (e) {
            // Privémodus kan op localStorage gooien. Dan gewoon geen balk; de
            // storeknoppen staan verderop in de pagina toch al.
        }
    })();
</script>
