<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>@yield('titel', 'Kaarten') · {{ $club->name }}</title>

    {{-- Eigen CSS in de pagina, en niet de Tailwind-CDN zoals de rest van de
         site. Deze pagina hangt in een iframe op de website van een club, en een
         WordPress-host met een strak beveiligingsbeleid blokkeert externe
         scripts - dan zou de winkel er kaal in staan. Geen lettertype van
         Google om dezelfde reden. --}}
    <style>
        :root {
            --clubkleur: {{ $club->primary_color ?: '#5BA12F' }};
            --tekst: #14283D;
            --grijs: #6B7280;
            --rand: #E5E7EB;
            --vlak: #F4F5F7;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto,
                         Helvetica, Arial, sans-serif;
            color: var(--tekst);
            background: {{ $embed ? '#fff' : 'var(--vlak)' }};
            line-height: 1.55;
            -webkit-font-smoothing: antialiased;
        }

        .omhulsel { max-width: 720px; margin: 0 auto; padding: {{ $embed ? '16px' : '28px 16px 48px' }}; }

        .kop { display: flex; align-items: center; gap: 14px; margin-bottom: 22px; }
        .kop img { height: 46px; width: auto; }
        .kop h1 { font-size: 21px; line-height: 1.25; }
        .kop p { color: var(--grijs); font-size: 14px; }

        .kaart {
            background: #fff;
            border: 1px solid var(--rand);
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 14px;
        }
        .kaart h2 { font-size: 17px; margin-bottom: 2px; }
        .meta { color: var(--grijs); font-size: 14px; }

        .knop {
            display: inline-block;
            background: var(--clubkleur);
            color: #fff;
            border: 0;
            border-radius: 8px;
            padding: 12px 20px;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }
        .knop:hover { filter: brightness(0.93); }
        .knop[disabled] { background: #9CA3AF; cursor: not-allowed; }
        .knop-blok { width: 100%; text-align: center; }

        .terug { display: inline-block; color: var(--grijs); font-size: 14px; text-decoration: none; margin-bottom: 14px; }
        .terug:hover { color: var(--tekst); }

        /* De kaartsoorten onder een activiteit in de winkel: dezelfde rijen als
           op de bestelpagina, met een streep erboven zodat ze niet aan de
           omschrijving vastplakken. */
        .soorten { margin: 12px 0 16px; border-top: 1px solid var(--rand); }

        .rij { display: flex; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--rand); }
        .rij:last-of-type { border-bottom: 0; }
        .rij .naam { flex: 1; }
        .rij .naam small { display: block; color: var(--grijs); font-size: 13px; }
        .rij .prijs { font-weight: 600; white-space: nowrap; }
        .rij .op {
            min-width: 72px;
            padding: 8px;
            font-size: 13px;
            color: var(--grijs);
            text-align: center;
        }

        /* De teller bij een kaartsoort: min, het aantal, plus. Knoppen van 38
           pixels, want dit wordt op een telefoon met een duim bediend. */
        .teller { display: flex; align-items: center; gap: 6px; }
        .teller button {
            width: 38px;
            height: 38px;
            border: 1px solid var(--rand);
            border-radius: 8px;
            background: #fff;
            color: var(--tekst);
            font-size: 20px;
            line-height: 1;
            font-family: inherit;
            cursor: pointer;
        }
        .teller button:hover:not(:disabled) { border-color: var(--clubkleur); color: var(--clubkleur); }
        .teller button:disabled { color: #C7CDD4; cursor: not-allowed; }
        .teller input {
            width: 46px;
            padding: 9px 4px;
            border: 1px solid var(--rand);
            border-radius: 8px;
            font-size: 15px;
            font-family: inherit;
            text-align: center;
            background: #fff;
            /* De eigen pijltjes van de browser eruit: er staat al een plus en
               een min naast, en twee soorten knoppen naast elkaar verwart. */
            -moz-appearance: textfield;
            appearance: textfield;
        }
        .teller input::-webkit-outer-spin-button,
        .teller input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }

        label { display: block; font-size: 14px; font-weight: 600; margin-bottom: 4px; }
        input[type="text"], input[type="email"] {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid var(--rand);
            border-radius: 8px;
            font-size: 15px;
            font-family: inherit;
        }
        input:focus, select:focus { outline: 2px solid var(--clubkleur); outline-offset: 1px; }
        .veld { margin-bottom: 14px; }
        .hulp { color: var(--grijs); font-size: 13px; margin-top: 4px; }

        .fout {
            background: #FDE8E8;
            border: 1px solid #F3C2C2;
            color: #9B1C1C;
            border-radius: 8px;
            padding: 12px 14px;
            font-size: 14px;
            margin-bottom: 16px;
        }
        .fout ul { margin: 6px 0 0 18px; }

        .leeg { text-align: center; color: var(--grijs); padding: 40px 16px; }

        .voet { color: var(--grijs); font-size: 12px; text-align: center; margin-top: 26px; }
        .voet a { color: var(--grijs); }
    </style>
</head>
<body>
    <div class="omhulsel">
        @unless ($embed)
            <div class="kop">
                @if ($club->logo_path)
                    <img src="{{ asset('logos/' . basename($club->logo_path)) }}" alt="{{ $club->name }}">
                @endif
                <div>
                    <h1>@yield('titel', 'Kaarten')</h1>
                    <p>{{ $club->name }}</p>
                </div>
            </div>
        @endunless

        @yield('inhoud')

        @unless ($embed)
            <p class="voet">Kaartverkoop via <a href="{{ url('/') }}">VoetbalPlanner</a></p>
        @endunless
    </div>

    @if ($embed)
        {{-- De hoogte doorgeven aan de omliggende pagina, zodat het iframe
             meegroeit en er geen scrollbalk in een scrollbalk ontstaat. De
             ontvangende kant mag dit negeren; dan blijft de vaste hoogte staan. --}}
        <script>
            (function () {
                function meld() {
                    var h = document.documentElement.scrollHeight;
                    parent.postMessage({ voetbalplannerShopHoogte: h }, '*');
                }
                window.addEventListener('load', meld);
                window.addEventListener('resize', meld);
                if (window.ResizeObserver) {
                    new ResizeObserver(meld).observe(document.body);
                }
            })();
        </script>
    @endif
</body>
</html>
