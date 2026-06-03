# VoetbalPlanner — Platform

Een voetbalclub-beheersysteem bestaande uit een **Laravel 13 backend**, een **Filament 4 admin-panel** en een **FlutterFlow mobiele app**. Clubs beheren hun leden, teams, wedstrijden en bardiensten via het platform. Leden gebruiken de app voor het programma, de chat en de bardiensten.

---

## Stack

| Laag | Technologie |
|------|-------------|
| Backend | Laravel 13, PHP 8.3 |
| Admin | Filament 4 (multi-tenant via Club) |
| Auth | Laravel Sanctum (Bearer Token) + Magic Links |
| Mobiele app | FlutterFlow (Dart/Flutter) |
| Chat | Firebase Firestore (real-time) |
| Push | Firebase Cloud Messaging (FCM) |
| Externe sync | Sportlink MCP (teams, leden, wedstrijden) |
| Notificaties | WhatsApp via MCP-brug |
| PDF export | barryvdh/laravel-dompdf |

---

## Functies

### App (leden)
- Inloggen via magic link (e-mail) of biometrisch (Face ID / vingerafdruk)
- Wedstrijdprogramma per elftal (aankomende of volledig seizoen)
- Wedstrijddetail: datum, locatie, verzameltijd, opstelling, doelpunten, notities
- Bardiensten: overzicht en details per dienst
- Teamchat (real-time via Firebase)
- Direct berichten tussen teamleden
- Profielpagina met uitloggen
- In-app handleiding (deze documentatie)

### Platform (beheerders)
- Clubbeheer: naam, logo, adres, contactgegevens
- Huisstijl: primaire, secundaire en accentkleur (doorgezet naar de app)
- Teams en leden beheren (import via Excel)
- Wedstrijden beheren: datum, tegenstander, opstelling, doelpunten, notities
- Bardiensten plannen en leden toewijzen
- Gebruikers en rollen (super_admin, club_admin, bar_commissie, coach, member)
- Impersoneren van gebruikers (super_admin)
- Synchronisatie met Sportlink MCP (teams, leden, wedstrijden)
- Documentatie beheren + PDF exporteren

---

## Installatie

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --force
php artisan db:seed
php artisan filament:assets
```

### Vereiste `.env` variabelen

```dotenv
APP_NAME="VoetbalPlanner"
APP_URL=https://jouwclub.voetbalplanner.nl

DB_CONNECTION=mysql
DB_HOST=...
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

MAIL_MAILER=smtp          # of resend / mailgun
MAIL_FROM_ADDRESS=noreply@jouwclub.nl

FIREBASE_CREDENTIALS=...  # pad naar service-account JSON
FIREBASE_PROJECT_ID=...

# Optioneel — Sportlink MCP
MCP_BASE_URL=https://mcp.nubixhosting.nl/mcp/sportlink/mcp
MCP_API_KEY=...

# Optioneel — WhatsApp via MCP
WHATSAPP_ENABLED=false
WHATSAPP_API_KEY=...
```

---

## Deployen (cPanel shared hosting)

```bash
bash deploy.sh
```

Het script voert uit: `composer install --no-dev`, migraties, Filament assets, cache-warm-up en seeding.

---

## API

Alle endpoints leven onder `/api/v1/`. Authenticatie via `Authorization: Bearer <token>`.

| Methode | Endpoint | Omschrijving |
|---------|----------|-------------|
| POST | `/auth/login` | Inloggen met e-mail + wachtwoord |
| POST | `/auth/magic-link` | Magic link aanvragen |
| POST | `/auth/verify-magic-link` | Magic link inwisselen voor token |
| POST | `/auth/logout` | Uitloggen (token intrekken) |
| GET | `/auth/me` | Eigen gebruikersprofiel |
| GET | `/teams` | Teams van de club |
| GET | `/teams/{team}/members` | Leden van een team |
| GET | `/members` | Alle leden |
| GET | `/matches` | Wedstrijden (`?upcoming=1` voor aankomende) |
| GET | `/matches/{match}` | Wedstrijddetail |
| PATCH | `/matches/{match}` | Wedstrijd bijwerken (coach) |
| GET | `/matches/{match}/lineup` | Opstelling |
| POST | `/matches/{match}/lineup` | Opstelling opslaan |
| GET | `/matches/{match}/goals` | Doelpunten |
| POST | `/matches/{match}/goals` | Doelpunt toevoegen |
| DELETE | `/matches/{match}/goals/{goal}` | Doelpunt verwijderen |
| GET | `/bar-duties` | Bardiensten |
| POST | `/bar-duties` | Bardienst aanmaken |
| PATCH | `/bar-duties/{id}/members` | Leden toewijzen |
| GET | `/documentation` | Handleiding-secties (voor de app) |
| POST | `/sync/all` | Volledige sync (admin) |

---

## Rollen

| Rol | Toegang |
|-----|---------|
| `super_admin` | Alles, inclusief alle clubs |
| `club_admin` | Eigen club volledig beheren |
| `bar_commissie` | Bardiensten beheren |
| `coach` | Wedstrijden en opstelling bijwerken |
| `member` | Alleen via de app (geen platform-toegang) |

---

## Synchronisatie (Sportlink MCP)

Teams, leden en wedstrijden worden gesynchroniseerd vanuit Sportlink via een MCP-service. Stel de `mcp_base_url` en `mcp_api_key` in via **Instellingen** in het platform (per club opgeslagen in de `settings` tabel).

Handmatig synchroniseren: **Sync** in het admin-menu → kies het gewenste onderdeel of gebruik "Alles synchroniseren".

---

## Documentatie in de app

De handleiding die leden in de app zien, wordt beheerd via **Documentatie** in het admin-panel. Secties zijn ingedeeld in drie categorieën: *De App*, *Het Platform* en *Koppelingen*. Ze kunnen worden bewerkt en geëxporteerd als PDF via de knop "PDF exporteren".
