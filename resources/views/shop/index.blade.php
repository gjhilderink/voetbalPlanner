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
                $goedkoopste = $activiteit->ticketTypes->min('price_cents');
                $uitverkocht = $activiteit->ticketTypes->every(fn ($s) => $s->isUitverkocht());
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

                <p style="margin:14px 0 16px">
                    @if ($uitverkocht)
                        <strong>Uitverkocht</strong>
                    @elseif ($goedkoopste === 0)
                        <strong>Gratis</strong> — wel even een kaart reserveren
                    @else
                        Vanaf <strong>{{ \App\Support\Geld::euro((int) $goedkoopste) }}</strong>
                    @endif
                </p>

                @if (! $uitverkocht)
                    <a class="knop" href="{{ route('shop.event', [
                        'clubslug' => $club->slug,
                        'event'    => $activiteit->id,
                    ]) . ($embed ? '?embed=1' : '') }}">Kaarten kiezen</a>
                @endif
            </div>
        @endforeach
    @endif
@endsection
