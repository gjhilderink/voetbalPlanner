# WordPress-plugin voor de ticketshop

`voetbalplanner-ticketshop/` is een losse WordPress-plugin die de publieke
ticketshop van een club in een pagina zet, bijvoorbeeld
<https://voetbalplanner.nl/bon-boys/ticketshop> op bonboys.nl.

De plugin hangt aan twee dingen die de winkel zelf al doet:

- `?embed=1` laat de kop, de voettekst en de buitenmarge weg
  (`resources/views/shop/layout.blade.php`);
- in die stand stuurt de winkel bij elke wijziging zijn hoogte naar de
  omliggende pagina, als `{ voetbalplannerShopHoogte: <px> }`. Het script van
  de plugin luistert daarnaar en zet het iframe op die hoogte.

De winkelroutes draaien zonder sessie en zonder `X-Frame-Options`, dus een
iframe op een vreemd domein werkt.

## Betalen kan niet in het kader

Pay.nl zet op `safe.pay.nl` wél `X-Frame-Options: sameorigin`, dus de browser
weigert de betaalpagina in ons iframe te tonen — de bezoeker houdt een leeg
vlak over. Een `302` lost dat niet op: die blijft binnen het kader waar hij
begon.

Het afrekenen geeft in de embed-stand daarom `resources/views/shop/betalen.blade.php`
terug in plaats van een doorverwijzing. Die pagina breekt langs drie wegen uit
het kader, van onzichtbaar naar zeker:

1. `{ voetbalplannerShopBetaling: "<url>" }` naar de omliggende pagina. Het
   script van de plugin controleert afzender, herkomst en `https:` en gaat er
   dan zelf naartoe — een pagina mag zichzelf altijd verplaatsen.
2. `window.top.location.replace()`. Een browser blokkeert dat voor een kader op
   een vreemd domein zonder klik, maar het kost niets om te proberen.
3. Een knop met `target="_top"`. Die klik is de toestemming die onder 2
   ontbreekt, dus dit werkt altijd.

Omdat de bezoeker daarmee de clubsite verlaat, geeft de plugin het adres van de
pagina mee als `terug=`. Dat rijdt mee tot het afrekenen, komt in het adres waar
Pay.nl de bezoeker afzet, en wordt op de bedankpagina de knop terug.

## Zip maken

```bash
bash wordpress/bouw.sh
```

Dat levert `wordpress/dist/voetbalplanner-ticketshop-<versie>.zip`, klaar om
te uploaden via Plugins → Nieuwe plugin → Plugin uploaden.

## Installeren op bonboys.nl

1. Zip uploaden en activeren.
2. Instellingen → Ticketshop → clubnaam `bon-boys` invullen en opslaan.
3. Op de pagina waar de kaartverkoop moet komen het blok "Shortcode"
   toevoegen met `[voetbalplanner_ticketshop]`.
