# Google Play Store — invulblad VoetbalPlanner

Alles wat Google Play Console vraagt voordat je naar productie kunt publiceren.
Wat al bekend is uit het project heb ik ingevuld. Regels met **[INVULLEN]** moet
je zelf aanvullen; die kan ik niet uit de code halen.

De volgorde hieronder volgt de Play Console, zodat je van boven naar beneden kunt
werken.

---

## 0. Eerst dit: publiceren faalt nu op een API

De laatste build slaagde, maar het uploaden naar Play faalde:

> Google Play Android Developer API has not been used in project 858079644390 or it is disabled.

**Actie:** zet de API aan op
`https://console.developers.google.com/apis/api/androidpublisher.googleapis.com/overview?project=858079644390`,
wacht enkele minuten en draai alleen de publicatiestap opnieuw.

Controleer daarna twee dingen die hierna vaak alsnog misgaan:

- De API staat aan in **hetzelfde** project als waar het service-account uit komt
  dat in `GOOGLE_PLAY_SERVICE_ACCOUNT_CREDENTIALS` zit.
- Het service-account is in Play Console uitgenodigd met minimaal
  **Releases beheren** voor deze app.

---

## 0b. Vóór élke release: versie ophogen

De meest voorkomende publicatiefout in dit project. Beide winkels weigeren een
upload die een al gebruikt nummer hergebruikt:

> Version code 5 has already been used.

**Doe dit als eerste stap van elke release**, vóór de build — niet erna, want een
geslaagde build met een verkeerd nummer moet je volledig overdoen.

In FlutterFlow: **Settings → App Details → App Versioning**.

| Veld | Waarvoor | Regel |
|---|---|---|
| Version Code / Build Number | Android (Play) | Altijd +1. Nooit hergebruiken, ook niet na een mislukte publicatie |
| Version Name | iOS (App Store) | Ophogen zodra een versie is ingediend; een "trein" sluit na indiening en accepteert geen tweede build |

Beide tegelijk ophogen is het veiligst. Hoog je alleen de code op, dan slaagt
Android en faalt iOS op de gesloten trein; alleen de naam ophogen doet het
omgekeerde.

Bijhouden welke nummers op zijn:

| Versie | Code | Uitkomst |
|---|---|---|
| 4.2.2 | 5 | Verbruikt — Android geweigerd (code al gebruikt), iOS-trein gesloten |

Vul deze tabel aan bij elke release, dan hoef je het niet in de console op te
zoeken.

---

## 1. App-gegevens

| Veld | Waarde |
|---|---|
| App-naam (max 30 tekens) | `Voetbalplanner` |
| Package-naam | `com.mycompany.voetbalplanner` |
| Huidige versie | `4.2.2+5` |
| Standaardtaal | Nederlands (nl-NL) |
| App of game | App |
| Gratis of betaald | **[INVULLEN]** — waarschijnlijk Gratis |

> **Let op de package-naam.** `com.mycompany.voetbalplanner` is de FlutterFlow-standaard
> en niet meer te wijzigen na de eerste publicatie. Wil je `nl.nubix.voetbalplanner`
> of iets met je eigen domein, dan moet dat nú — daarna zit je eraan vast en is een
> nieuwe app-vermelding de enige uitweg.

---

## 2. Korte beschrijving (max 80 tekens)

Voorstel — kies er één of pas aan:

```
Alles voor jouw team: wedstrijden, trainingen, rijschema en bardienst.
```

```
De clubapp voor wedstrijden, trainingen, bardiensten en verenigingsnieuws.
```

---

## 3. Volledige beschrijving (max 4000 tekens)

Concept op basis van wat de app daadwerkelijk doet. Pas de clubnaam aan of maak
het algemener als de app voor meerdere clubs bedoeld is.

```
Voetbalplanner brengt alles rond jouw team samen in één app. Geen losse
appjes en spreadsheets meer: je ziet in één oogopslag wat er speelt.

WEDSTRIJDEN
• Je eerstvolgende wedstrijden met datum, tijd, locatie en tegenstander
• Aanwezigheid: meld je met één tik af of aan
• Rijschema: zie wanneer jij aan de beurt bent om te rijden
• Coaches stellen de opstelling op, houden de score bij en voegen een
  notitie toe bij de wedstrijd

TRAININGEN
• Het trainingsschema van jouw team
• Af- en aanmelden per training, met een reden als je dat wilt
• Coaches zien in één overzicht wie er is

BARDIENSTEN
• Je eigen bardiensten op je dashboard
• Ruilen met een teamgenoot via een wisselverzoek

VERENIGINGSAGENDA
• Alle clubactiviteiten bij elkaar: toernooien, de bazaar, clubavonden,
  vrijwilligersactiviteiten en meer
• Aanmelden voor een activiteit en toevoegen aan je eigen agenda

VERDER
• Chat met je team, in groepen of één-op-één
• Nieuws en meldingen vanuit de club
• Ouders kunnen meekijken met hun kind
• Inloggen zonder wachtwoord via een e-maillink

Voetbalplanner is bedoeld voor leden, ouders, coaches en vrijwilligers van
aangesloten verenigingen. Je hebt een account van je club nodig om in te loggen.
```

**[INVULLEN]** — controleer of alle genoemde functies kloppen voor de versie die
je publiceert, en schrap wat er niet in zit. Google keurt af op beloftes die de
app niet waarmaakt.

---

## 4. Afbeeldingen

Alles moet PNG of JPEG zijn, zonder transparantie voor de feature graphic.

| Wat | Eis | Status |
|---|---|---|
| App-icoon | 512 × 512 px, 32-bit PNG | **[INVULLEN]** |
| Feature graphic | 1024 × 500 px | **[INVULLEN]** |
| Telefoon-screenshots | min. 2, max. 8 — 16:9 of 9:16, kortste zijde min. 320 px | **[INVULLEN]** |
| Tablet 7" (optioneel) | min. 2 als je tablets ondersteunt | **[INVULLEN]** |
| Tablet 10" (optioneel) | min. 2 als je tablets ondersteunt | **[INVULLEN]** |

**Tip voor de screenshots** — deze schermen laten de app het best zien:
dashboard, wedstrijddetail (de nieuwe kaartopmaak), trainingen af-/aanmelden,
de verenigingsagenda en de teamchat.

> Zet geen persoonsgegevens van echte leden op de screenshots. Gebruik een
> testteam met verzonnen namen; anders is het een AVG-probleem zodra de
> vermelding openbaar is.

---

## 5. Categorisering

| Veld | Waarde |
|---|---|
| Applicatietype | App |
| Categorie | Sport |
| Tags | **[INVULLEN]** — max. 5, bv. voetbal, team, club, agenda |

---

## 6. Contactgegevens

| Veld | Waarde |
|---|---|
| E-mailadres | **[INVULLEN]** — wordt openbaar getoond in de Play Store |
| Telefoonnummer | Optioneel |
| Website | `https://voetbalplanner.nubix.nl` |
| Privacybeleid (URL) | `https://voetbalplanner.nubix.nl/privacy` |

> De privacypagina bestaat al in de app (`routes/web.php` → `/privacy`, gevuld
> vanuit het `LegalPage`-model). **Controleer vóór publicatie of die pagina
> daadwerkelijk gevuld is**: Google opent hem en keurt af bij een lege of
> onbereikbare pagina.

---

## 7. App-toegang — belangrijk

Deze app werkt niet zonder account, dus je **moet** Google inloggegevens geven.
Zonder dit wordt de review afgekeurd omdat de reviewer niet verder komt dan het
inlogscherm.

Kies in Play Console: *Alle of sommige functies zijn beperkt*.

| Veld | Waarde |
|---|---|
| Gebruikersnaam / e-mail | **[INVULLEN]** — maak een testaccount aan |
| Wachtwoord | **[INVULLEN]** |
| Extra instructies | zie hieronder |

Voorstel voor de instructies:

```
Log in met de knop "Inloggen beheerders" en de bovenstaande gegevens.
Het account is gekoppeld aan een testteam met voorbeeldgegevens.

De inlogoptie voor leden verstuurt een e-maillink; die is voor de review niet
nodig — gebruik het wachtwoord-account hierboven.
```

> Maak dit testaccount aan in een **testclub** met verzonnen leden, niet in een
> echte club. De reviewer ziet alles waar het account toegang toe heeft.

---

## 8. Data safety (Gegevensbeveiliging)

Dit formulier is het meeste werk en Google controleert het streng. Vul in wat de
app werkelijk doet.

De app verzamelt in elk geval, op basis van de code:

| Gegevenstype | Verzameld | Gedeeld | Doel | Verplicht |
|---|---|---|---|---|
| Naam | Ja | Nee | App-functionaliteit, accountbeheer | Ja |
| E-mailadres | Ja | Nee | App-functionaliteit, accountbeheer | Ja |
| Telefoonnummer | Ja (optioneel veld) | Nee | App-functionaliteit | Nee |
| Geboortedatum | Ja | Nee | App-functionaliteit | Nee |
| Foto's | Ja (profielfoto, bijlagen bij bugmeldingen) | Nee | App-functionaliteit | Nee |
| Berichten in de app | Ja (teamchat) | Nee | App-functionaliteit | Nee |
| App-activiteit / crashlogs | **[INVULLEN]** — afhankelijk van Firebase-instellingen | Nee | Analyse | Nee |
| Push-token | Ja | Nee | Meldingen | Nee |

Verder in te vullen:

- **Worden gegevens versleuteld verzonden?** Ja — alles gaat via HTTPS.
- **Kunnen gebruikers hun gegevens laten verwijderen?** Ja — via Profiel →
  account verwijderen, en dat verwijdert ook de chatgeschiedenis.
- **Gegevens gedeeld met derden?** **[INVULLEN]** — denk aan Firebase (Google)
  voor meldingen en chat. Google rekent zijn eigen diensten meestal niet als
  "delen", maar controleer dit.

> Loop dit na met iemand die de Firebase-instellingen kent. Een onjuist ingevuld
> data-safety-formulier is een van de meest voorkomende redenen voor afkeuring en
> kan later tot verwijdering leiden.

---

## 9. Inhoudsclassificatie

Vragenlijst invullen in de console. Verwachte uitkomst voor deze app: **PEGI 3 /
Iedereen**.

Let op deze vragen, want ze gelden hier wél:

- **Bevat de app door gebruikers gegenereerde inhoud?** → **Ja** (teamchat).
  Google vraagt dan of er een meldfunctie en moderatie is.
  **[INVULLEN]** — is er een manier om ongepaste berichten te melden of te
  laten verwijderen? Zo niet, dan is dat waarschijnlijk het eerste wat je moet
  toevoegen.
- **Deelt de app locatie?** → Nee.
- **Bevat de app advertenties?** → Nee.
- **Aankopen in de app?** → Nee.

---

## 10. Doelgroep

| Veld | Waarde |
|---|---|
| Doelgroep-leeftijd | **[INVULLEN]** |
| Richt de app zich op kinderen? | **[INVULLEN]** |

> Dit vraagt aandacht. De app wordt gebruikt door jeugdleden onder de 13, en er
> is een ouder/verzorger-koppeling. Vink je "ook gericht op kinderen" aan, dan
> geldt het strengere **Families-beleid**: extra eisen aan advertenties,
> gegevensverzameling en een verplichte privacyverklaring voor kinderen.
>
> Veel clubapps kiezen voor doelgroep **13+** en stellen dat een ouder het
> account beheert. Bespreek dit met iemand die de AVG-kant kent voordat je hier
> iets aanvinkt — het is achteraf lastig te wijzigen.

---

## 11. Advertenties

| Veld | Waarde |
|---|---|
| Bevat de app advertenties? | Nee |

> De app toont wel **banners van de club zelf** (het `Banner`-model). Dat zijn
> geen advertenties van een advertentienetwerk; Google bedoelt hier third-party
> ads. Bij twijfel: nee is hier het juiste antwoord.

---

## 12. Wat is er nieuw (release notes, max 500 tekens)

De app houdt zijn eigen release notes bij in
`database/seeders/ReleaseNotesSeeder.php`. Voor de Play Store een korte versie:

```
• Je ziet nu wie zich voor een wedstrijd heeft afgemeld, en waarom
• Deel een wedstrijd in één tik: samenvatting naar WhatsApp of een andere
  app, met een link om de wedstrijd direct te openen
• Coaches kunnen de fruitheld kiezen; vlagger en gastspelers beheer je
  voortaan gewoon bij de naam zelf
• Opgelost: wijzigingen van de coach werden pas zichtbaar na het opnieuw
  openen van de wedstrijd
• Opgelost: de app liep niet meer vast bij een verlopen sessie
• Je iPhone kan je inloggegevens nu opslaan
```

---

## 13. Laatste controle vóór publicatie

- [ ] Google Play Android Developer API aangezet (zie punt 0)
- [ ] Service-account heeft "Releases beheren" in Play Console
- [ ] Package-naam definitief — na publicatie niet meer te wijzigen
- [ ] Privacypagina bereikbaar en gevuld
- [ ] Testaccount aangemaakt in een testclub en werkend
- [ ] Screenshots zonder gegevens van echte leden
- [ ] Data safety ingevuld en gecontroleerd tegen de Firebase-instellingen
- [ ] Doelgroep-vraag bewust beantwoord (zie punt 10)
