@extends('shop.layout')

@section('titel', 'Je bestelling')

@section('inhoud')
    @if ($order->isBetaald())
        <div class="kaart">
            <h2 style="margin-bottom:8px">Gelukt — je kaarten staan klaar</h2>
            <p class="meta">
                Bestelling {{ $order->order_number }} ·
                {{ $order->agendaItem?->title }}
                @if ($order->agendaItem?->starts_at)
                    · {{ $order->agendaItem->starts_at->translatedFormat('l j F Y') }}
                @endif
            </p>
            <p style="margin-top:12px">
                We hebben ze ook naar <strong>{{ $order->buyer_email }}</strong> gestuurd.
                Niets ontvangen? Kijk in je ongewenste post, of bewaar deze pagina —
                de codes hieronder blijven werken.
            </p>
        </div>

        @foreach ($order->accessCodes as $code)
            <div class="kaart" style="text-align:center">
                <img src="{{ \App\Support\Qr::pngDataUri($code->code, 260) }}"
                     alt="QR-code {{ $code->code }}"
                     style="width:220px;height:220px;background:#fff;border-radius:8px">
                <p style="font-family:ui-monospace,Menlo,Consolas,monospace;font-size:19px;font-weight:700;letter-spacing:2px;margin-top:10px">
                    {{ $code->code }}
                </p>
                @if ($code->label)
                    <p class="meta">{{ $code->label }}</p>
                @endif
            </div>
        @endforeach

        <p class="hulp" style="text-align:center">
            Laat bij de ingang één code per persoon scannen. Elke code werkt één keer.
        </p>

    @elseif ($order->status === \App\Models\Order::STATUS_PENDING)
        <div class="kaart">
            <h2 style="margin-bottom:8px">We wachten nog op je betaling</h2>
            <p class="meta">Bestelling {{ $order->order_number }}</p>
            <p style="margin-top:12px">
                Soms duurt het een minuutje voordat de betaling bij ons binnen is.
                Ververs deze pagina zo nog eens.
            </p>
            <p style="margin-top:14px">
                <a class="knop" href="">Opnieuw kijken</a>
            </p>
        </div>

    @else
        <div class="kaart">
            <h2 style="margin-bottom:8px">De betaling is niet doorgegaan</h2>
            <p class="meta">Bestelling {{ $order->order_number }}</p>
            <p style="margin-top:12px">
                Er is niets afgeschreven en je kaarten zijn weer vrijgegeven.
                Je kunt het gewoon opnieuw proberen.
            </p>
            <p style="margin-top:14px">
                <a class="knop" href="{{ route('shop.show', ['clubslug' => $club->slug]) . ($embed ? '?embed=1' : '') }}">
                    Terug naar de kaarten
                </a>
            </p>
        </div>
    @endif
@endsection
