@extends('shop.layout')

@section('titel', 'Kaarten kopen')

@section('inhoud')
    @if ($activiteiten->isEmpty())
        <div class="kaart leeg">
            <p><strong>Er zijn op dit moment geen kaarten te koop.</strong></p>
            <p class="meta">Kom later nog eens terug.</p>
        </div>
    @else
        @foreach ($activiteiten as $activiteit)
            @php
                $uitverkocht = $activiteit->ticketTypes->every(fn ($s) => $s->isUitverkocht());
                $gratis      = $activiteit->ticketTypes->every(fn ($s) => $s->price_cents === 0);
            @endphp

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

                {{-- De kaartsoorten meteen hier en niet pas op de volgende
                     pagina: "vanaf € 5" laat een bezoeker raden of er ook een
                     kindkaart is, en of die ene soort die hij zoekt nog te koop
                     is. Dezelfde rijen als op de bestelpagina, alleen zonder
                     de keuzevakjes. --}}
                <div class="soorten">
                    @foreach ($activiteit->ticketTypes as $soort)
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
                            @endif
                        </div>
                    @endforeach
                </div>

                @if ($uitverkocht)
                    <p style="margin-bottom:2px"><strong>Uitverkocht</strong></p>
                @else
                    @if ($gratis)
                        <p class="hulp" style="margin:0 0 12px">
                            Gratis — wel even een kaart reserveren.
                        </p>
                    @endif
                    <a class="knop" href="{{ route('shop.event', [
                        'clubslug' => $club->slug,
                        'event'    => $activiteit->id,
                    ]) . ($embed ? '?embed=1' : '') }}">Kaarten kiezen</a>
                @endif
            </div>
        @endforeach
    @endif
@endsection
