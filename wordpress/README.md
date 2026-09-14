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
