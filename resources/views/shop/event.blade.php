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
                    <select name="aantal[{{ $soort->id }}]" aria-label="Aantal {{ $soort->name }}">
                        @for ($i = 0; $i <= $over; $i++)
                            <option value="{{ $i }}" @selected((int) ($ingevuld['aantal'][$soort->id] ?? 0) === $i)>{{ $i }}</option>
                        @endfor
                    </select>
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
@endsection
