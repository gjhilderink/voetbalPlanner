# VoetbalPlanner — Mobiele App

FlutterFlow-workspace voor de **VoetbalPlanner** mobiele app (`voetbalplanner-76ly9n`). De app stelt voetbalclubleden in staat om het wedstrijdprogramma, bardiensten en teamchat te bekijken.

---

## Functies

| Pagina | Omschrijving |
|--------|-------------|
| **LoginPage** | Inloggen via magic link (e-mail) of e-mail + wachtwoord (beheerders) |
| **MagicLinkVerifyPage** | Verwerken van de magic link uit de e-mail |
| **WedstrijdenPage** | Aankomende of volledige seizoen-wedstrijden van het eigen elftal |
| **WedstrijdDetailPage** | Datum, locatie, opstelling, doelpunten, notities |
| **BardienPage** | Bardiensten van het elftal |
| **TeamChatPage** | Real-time teamchat (Firebase Firestore) |
| **DirectChatPage** | 1-op-1 berichten met een teamlid |
| **ProfielPage** | Persoonlijke gegevens, uitloggen, link naar handleiding |
| **DocumentatiePage** | In-app handleiding (opgehaald via API) |

---

## Verbinding met het backend

De app communiceert via de REST API op `/api/v1/`. Na het inloggen ontvangt de app een **Sanctum Bearer Token** dat opgeslagen wordt in de app state (`authToken`). Elk API-verzoek stuurt dit token mee via de `Authorization`-header.

API-groep in FlutterFlow: **VoetbalPlannerAPI**  
Base URL: geconfigureerd in de API-groep-instellingen van het FlutterFlow-project.

### Gebruikte app state velden

| Veld | Type | Omschrijving |
|------|------|-------------|
| `authToken` | String | Sanctum Bearer Token |
| `userName` | String | Naam van de ingelogde gebruiker |
| `userEmail` | String | E-mailadres |
| `currentTeamId` | String | ID van het actieve elftal |
| `currentTeamName` | String | Naam van het actieve elftal |
| `clubName` | String | Naam van de club |
| `primaryColor` | Color | Primaire huisstijlkleur |
| `secondaryColor` | Color | Secundaire huisstijlkleur |
| `accentColor` | Color | Accentkleur |

---

## Chat (Firebase)

De teamchat en directe berichten werken via **Firebase Firestore**:

- Teamberichten: collectie `teamChats`, gefilterd op `teamId`
- Directe berichten: collectie `directMessages`, gefilterd op afzender + ontvanger
- Push notificaties via FCM-topic `team-{teamId}`

Vereiste configuratie in FlutterFlow: Firebase-project koppelen en de `google-services.json` / `GoogleService-Info.plist` uploaden onder **Project Settings → Firebase**.

---

## DSL-workspace

Wijzigingen worden aangebracht via de **FlutterFlow AI DSL** in `dsl/edit.dart`:

```bash
# Controleer op SDK updates (één keer per sessie)
flutterflow ai upgrade --check

# Compileer en valideer
dart compile exe dsl/edit.dart -o /tmp/edit_check.exe

# Push naar FlutterFlow
dart run dsl/edit.dart \
  --api-key <api-key> \
  --project-id voetbalplanner-76ly9n \
  --commit-message "Omschrijving van de wijziging"
```

### Belangrijke DSL-patronen

- **Grouped API calls**: gebruik `app.raw()` + `Actions.apiCallNode(project, endpointName, groupName: 'VoetbalPlannerAPI')` — niet de DSL `ApiCall()` helper (die zoekt standalone endpoints).
- **Nieuwe struct + endpoint**: declareer de struct via `app.struct()` vóór het `app.raw()` blok dat het endpoint aanmaakt.
- **Page state update vanuit API**: `Actions.updatePageState()` + `StateFieldUpdate.setFromVariable()` met `nodeKey: wc.node.key`.
- **Bearer token**: wordt automatisch meegestuurd via de groeps-variabele `bearerToken` (gebonden aan `authToken` app state). Geen per-endpoint `token`-variabele nodig.

### Projectstructuur

```
dsl/
  edit.dart          # Alle wijzigingen aan het bestaande project
  create.dart        # Initieel aanmaken (niet meer gebruiken)
references/          # Werkende DSL-voorbeelden (lees-only)
context/             # Gegenereerde projectcontext (lees-only)
generated_code/      # Flutter-snapshot van het FlutterFlow-project (lees-only)
```

---

## Lokale ontwikkeling

```bash
dart pub get
dart compile exe dsl/edit.dart -o /tmp/edit_check.exe  # compileer-check
```

Gebruik `flutterflow ai inspect voetbalplanner-76ly9n --page <PaginaNaam>` om de huidige pagina-structuur op te halen voor je een wijziging maakt.
