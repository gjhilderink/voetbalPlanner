@extends('shop.layout')

@section('titel', $activiteit->title)

@section('inhoud')
    <a class="terug" href="{{ route('shop.show', ['clubslug' => $club->slug]) . ($embed ? '?embed=1' : '') }}">
        ← Alle activiteiten
    </a>

    {{-- Fouten komen als gewone variabele mee en niet uit de sessie: zonder
         sessie doet withErrors() niets, en in een iframe op een ander domein is
         er geen sessie. --}}
    @if (! empty($fouten))
        <div class="fout">
            <strong>Er ging iets mis:</strong>
            <ul>
                @foreach ($fouten as $fout)
                    <li>{{ $fout }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="kaart">
        <h2>{{ $activiteit->title }}</h2>
        <p class="meta">
            {{ $activiteit->starts_at?->translatedFormat('l j F Y') }}
            @if (! $activiteit->is_all_day && $activiteit->starts_at)
                · {{ $activiteit->starts_at->format('H:i') }}
            @endif
            @if ($activiteit->location)
                · {{ $activiteit->location }}
            @endif
        </p>
        @if ($activiteit->summary)
            <p class="meta" style="margin-top:8px">{{ $activiteit->summary }}</p>
        @endif
    </div>

    {{-- Geen @csrf: deze pagina draait zonder sessie, zodat hij ook in een
         iframe op een ander domein werkt. De afrekenroute staat daarom in de
         CSRF-uitzondering en leunt op throttling en reCAPTCHA. --}}
    <form class="kaart" method="POST"
          action="{{ route('shop.checkout', ['clubslug' => $club->slug, 'event' => $activiteit->id]) }}">
        <input type="hidden" name="embed" value="{{ $embed ? '1' : '0' }}">

        <h2 style="margin-bottom:6px">Kies je kaarten</h2>

        @foreach ($soorten as $soort)
            @php $over = $soort->maximumNu(); @endphp

            <div class="rij">
                <div class="naam">
                    {{ $soort->name }}
                    @if ($soort->description)
                        <small>{{ $soort->description }}</small>
                    @endif
                    @if ($soort->stock !== null && ! $soort->isUitverkocht() && $soort->beschikbaar() <= 10)
                        <small>Nog {{ $soort->beschikbaar() }} beschikbaar</small>
                    @endif
                </div>

                <div class="prijs">
                    {{ $soort->price_cents === 0 ? 'Gratis' : \App\Support\Geld::euro($soort->price_cents) }}
                </div>

                @if ($soort->isUitverkocht())
                    <span class="op">Uitverkocht</span>
                @else
                    {{-- Een invoerveld met een min en een plus ernaast. Het veld
                         is een gewone number-input en geen keuzelijst, zodat het
                         formulier ook klopt als het script niet draait: dan typ
                         je het aantal en klemt de server het alsnog af op wat er
                         te koop is. --}}
                    <div class="teller">
                        <button type="button" data-stap="-1"
                                aria-label="Eén {{ $soort->name }} minder">−</button>

                        <input type="number"
                               name="aantal[{{ $soort->id }}]"
                               value="{{ (int) ($ingevuld['aantal'][$soort->id] ?? 0) }}"
                               min="0" max="{{ $over }}" step="1"
                               inputmode="numeric"
                               aria-label="Aantal {{ $soort->name }}">

                        <button type="button" data-stap="1"
                                aria-label="Eén {{ $soort->name }} meer">+</button>
                    </div>
                @endif
            </div>
        @endforeach

        <h2 style="margin:22px 0 12px">Je gegevens</h2>

        <div class="veld">
            <label for="buyer_name">Naam</label>
            <input type="text" id="buyer_name" name="buyer_name" value="{{ $ingevuld['buyer_name'] ?? '' }}"
                   maxlength="150" required autocomplete="name">
            <p class="hulp">Deze naam komt op je kaarten te staan.</p>
        </div>

        <div class="veld">
            <label for="buyer_email">E-mailadres</label>
            <input type="email" id="buyer_email" name="buyer_email" value="{{ $ingevuld['buyer_email'] ?? '' }}"
                   maxlength="190" required autocomplete="email">
            <p class="hulp">Hier sturen we je kaarten naartoe. Kijk hem goed na.</p>
        </div>

        @if ($recaptchaEnabled ?? false)
            <div class="veld">
                <div class="g-recaptcha" data-sitekey="{{ $recaptchaSiteKey }}"></div>
            </div>
        @endif

        <button type="submit" class="knop knop-blok">Naar betalen</button>

        <p class="hulp" style="text-align:center;margin-top:10px">
            Je betaalt bij {{ $club->name }} via Pay.nl. De kaarten komen daarna per mail.
        </p>
    </form>

    @if ($recaptchaEnabled ?? false)
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    @endif

    {{-- De plus en de min. Eigen script en geen bibliotheek: deze pagina hangt
         in een iframe op de website van een club, en een host met een strak
         beveiligingsbeleid blokkeert alles wat van buiten komt. Het klemt het
         aantal ook af op wat er nog te koop is, zodat je niet pas bij het
         afrekenen hoort dat er nog maar drie kaarten waren. --}}
    <script>
        (function () {
            function klem(veld) {
                var min = parseInt(veld.getAttribute('min'), 10) || 0;
                var max = parseInt(veld.getAttribute('max'), 10);
                var nu  = parseInt(veld.value, 10);

                if (isNaN(nu) || nu < min) { nu = min; }
                if (!isNaN(max) && nu > max) { nu = max; }

                veld.value = nu;

                var knoppen = veld.parentNode.getElementsByTagName('button');
                knoppen[0].disabled = nu <= min;
                knoppen[1].disabled = !isNaN(max) && nu >= max;
            }

            var tellers = document.querySelectorAll('.teller');

            for (var i = 0; i < tellers.length; i++) {
                (function (teller) {
                    var veld = teller.getElementsByTagName('input')[0];

                    teller.addEventListener('click', function (e) {
                        var knop = e.target.closest('button[data-stap]');
                        if (!knop) { return; }

                        veld.value = (parseInt(veld.value, 10) || 0)
                            + parseInt(knop.getAttribute('data-stap'), 10);
                        klem(veld);
                    });

                    veld.addEventListener('change', function () { klem(veld); });
                    klem(veld);
                })(tellers[i]);
            }
        })();
    </script>
@endsection
