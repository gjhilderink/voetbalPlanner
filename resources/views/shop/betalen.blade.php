@extends('shop.layout')

@section('titel', 'Naar de betaling')

@section('inhoud')
    {{-- Deze pagina bestaat alleen in een kader op de site van een club.
         Buiten een kader gaat de winkel gewoon met een 302 naar Pay.nl.

         Pay.nl zet X-Frame-Options op sameorigin, dus safe.pay.nl in ons iframe
         hangen weigert de browser: de bezoeker zou een leeg vlak overhouden. De
         betaling moet dus uit het kader breken, naar het hele venster. Drie
         wegen, van onzichtbaar naar zeker:

         1. De omliggende pagina vragen of zij zelf naar de betaalpagina gaat.
            Een pagina mag zichzelf altijd verplaatsen, dus dit werkt zonder
            meer - maar alleen als daar onze WordPress-plugin luistert.
         2. Zelf het bovenste venster verplaatsen. Mag een kader op een vreemd
            domein alleen vlak na een klik; een browser blokkeert het anders,
            juist om te voorkomen dat een advertentie de pagina kaapt.
         3. De knop hieronder. Die klik is de toestemming die onder 2 ontbrak,
            en target="_top" stuurt hem naar het hele venster.

         Lukt 1 of 2, dan ziet de bezoeker deze pagina nauwelijks. --}}
    <div class="kaart">
        <h2 style="margin-bottom:8px">Je bestelling staat klaar</h2>
        <p>
            Je betaalt bij Pay.nl. Die pagina kan niet binnen de site van
            {{ $club->name }} staan, dus hij opent in het hele venster.
        </p>

        <p style="margin-top:16px">
            <a class="knop knop-blok" href="{{ $betaalUrl }}" target="_top" rel="noopener">
                Verder naar betalen
            </a>
        </p>

        <p class="hulp" style="text-align:center;margin-top:10px">
            Gebeurt er niets? Klik dan op de knop.
        </p>
    </div>

    <script>
        (function () {
            var adres = @json($betaalUrl);

            try {
                parent.postMessage({ voetbalplannerShopBetaling: adres }, '*');
            } catch (e) {}

            // Vervangen en niet openen: anders staat deze tussenpagina in de
            // geschiedenis, en komt wie op terug drukt hier weer uit - waarna
            // hij opnieuw naar de betaling wordt gestuurd.
            try {
                window.top.location.replace(adres);
            } catch (e) {}
        })();
    </script>
@endsection
