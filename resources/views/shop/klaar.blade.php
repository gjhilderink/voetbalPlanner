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
                Niets ontvangen? Kijk in je ongewenste post, of haal ze hieronder op —
                deze pagina blijft werken.
            </p>

            {{-- Een knop en niet de codes zelf. Uitgeschreven codes op het scherm
                 zijn bij de ingang lastig werken: iemand moet dan per persoon
                 scrollen naar de juiste QR. In de pdf staat elke kaart op een
                 eigen A4, met de naam erbij.

                 Alleen als de kaarten er ook echt zijn: een knop die op een 404
                 uitkomt is erger dan geen knop. --}}
            @if ($order->accessCodes->isNotEmpty())
                <p style="margin-top:16px">
                    <a class="knop" target="_blank" rel="noopener"
                       href="{{ route('shop.kaarten', ['clubslug' => $club->slug, 'token' => $order->public_token]) }}">
                        Kaarten downloaden (pdf)
                    </a>
                </p>

                <p class="hulp">
                    {{ $order->accessCodes->count() }}
                    {{ $order->accessCodes->count() === 1 ? 'kaart' : 'kaarten' }},
                    elk op een eigen A4 met de QR erop. Laat er bij de ingang één per
                    persoon scannen; elke kaart werkt één keer.
                </p>
            @else
                <p class="hulp" style="margin-top:16px">
                    Je kaarten worden klaargezet. Ververs deze pagina zo nog eens.
                </p>
            @endif
        </div>

        {{-- In de wallet van de telefoon. Per kaart en niet per bestelling: in
             een wallet is één kaart één pas, en zo kan wie met z'n vieren komt
             er één doorsturen.

             De knoppen zijn tekst en geen officiële badge van Apple of Google.
             Die plaatjes komen uit hun eigen merkpakket en horen in public/ te
             staan; tot die er zijn is een knop die het gewoon doet beter dan een
             plaatje dat er niet is. --}}
        @if (! empty($walletKaarten))
            <div class="kaart">
                <h2 style="margin-bottom:4px">Op je telefoon</h2>
                <p class="hulp" style="margin-bottom:6px">
                    Zet je kaart in je wallet, dan heb je hem bij de ingang meteen
                    bij de hand — ook zonder bereik.
                </p>

                @foreach ($walletKaarten as $kaart)
                    <div class="rij">
                        <div class="naam">
                            {{ $kaart['titel'] }}
                            <small>{{ $kaart['onder'] }}</small>
                        </div>

                        <div class="wallet">
                            @if ($kaart['apple'])
                                <a class="walletknop" href="{{ $kaart['apple'] }}">Apple Wallet</a>
                            @endif
                            @if ($kaart['google'])
                                <a class="walletknop" href="{{ $kaart['google'] }}"
                                   target="_blank" rel="noopener">Google Wallet</a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

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
