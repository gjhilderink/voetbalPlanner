=== VoetbalPlanner Ticketshop ===
Contributors: voetbalplanner
Tags: tickets, kaartverkoop, voetbal, vereniging, embed
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Zet de kaartverkoop van je club uit VoetbalPlanner op je eigen website.

== Description ==

De plugin zet de ticketshop van je club in een kader op een pagina van je
eigen website. Bezoekers kopen hun kaarten dus zonder de site te verlaten;
het bestellen, betalen en versturen van de kaarten blijft bij VoetbalPlanner.

Het kader groeit vanzelf mee met de winkel, dus je krijgt geen scrollbalk in
een scrollbalk.

== Installation ==

1. Plugins - Nieuwe plugin - Plugin uploaden, kies het zip-bestand en
   activeer de plugin.
2. Ga naar Instellingen - Ticketshop en vul de clubnaam in zoals die in het
   adres staat, bijvoorbeeld `bon-boys` voor
   https://voetbalplanner.nl/bon-boys/ticketshop.
3. Zet op de pagina waar de kaartverkoop moet komen de shortcode
   `[voetbalplanner_ticketshop]`. In de blokeditor doe je dat met het blok
   "Shortcode".

== Shortcode ==

`[voetbalplanner_ticketshop]`

Attributen, allemaal optioneel:

* `club` - de clubnaam in het adres. Standaard die van de instellingen.
* `hoogte` - de starthoogte in pixels, standaard 900. Daarna past het kader
  zich zelf aan.
* `titel` - de naam van het kader voor schermlezers.
* `basis` - het adres van VoetbalPlanner, alleen nodig als je club op een
  eigen adres draait.

Voorbeeld:

`[voetbalplanner_ticketshop club="bon-boys" hoogte="1200"]`

== Frequently Asked Questions ==

= De winkel is leeg =

Er staan pas kaarten in de winkel als de club de ticketshop heeft aangezet en
er een activiteit met kaartsoorten klaarstaat. Controleer dat eerst op
https://voetbalplanner.nl/{clubnaam}/ticketshop zelf.

= Het kader blijft even hoog =

Het meegroeien gebeurt met JavaScript. Als een cache- of optimalisatieplugin
het script van deze plugin samenvoegt of uitstelt, zet die er dan buiten. Tot
die tijd blijft de starthoogte staan en werkt de winkel gewoon.

== Changelog ==

= 1.0.1 =
* Het kader groeide zichzelf de pagina uit: het meldde de hoogte terug die het
  net zelf had gekregen, en daar kwamen elke ronde een paar pixels bij. Het
  negeert nu verschillen van een paar pixels, en de winkel meet voortaan de
  inhoud in plaats van het venster.

= 1.0.0 =
* Eerste versie: shortcode, instellingenscherm en een kader dat meegroeit.
