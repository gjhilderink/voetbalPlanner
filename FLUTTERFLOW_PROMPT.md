# VoetbalPlanner — FlutterFlow App Specification

> **Status (bijgewerkt 2026-07-06):** dit is de oorspronkelijke bouwspecificatie. De app is
> daarna doorontwikkeld; enkele punten hieronder zijn achterhaald. Belangrijkste afwijkingen:
> - **Opstelling-tab is verwijderd** uit Wedstrijd-detail (wordt niet gebruikt); tabs zijn nu **Info | Doelpunten**.
> - Login is **magic link** (e-mail) + biometrisch, niet e-mail/wachtwoord voor leden.
> - Er is een **chat/NavBar-architectuur** (ChatsPage-hub, team/direct/groep) bijgekomen.
> - De **"+" snelmenu-FAB** staat op het **Dashboard** (chatten, wissel bardienst/rijden, afmelden), niet op Bardiensten.
> Actuele projectstatus & valkuilen staan in de Claude-memory (o.a. FF-CLI, dedup-fix, magic-link deep link).

## Purpose
Mobile companion app for the VoetbalPlanner platform. Club members, coaches,
and bar-committee members can view schedules, manage match duties, assign bar
duty members, and receive WhatsApp notifications — all scoped to their club.

---

## Tech stack
- **Frontend**: FlutterFlow (Flutter/Dart)
- **Backend**: Laravel 13 REST API (Sanctum token auth)
- **Base URL**: `https://voetbalplanner.nubix.nl/api/v1`
- **Auth header**: `Authorization: Bearer {token}`
- **Content-Type**: `application/json`
- **All responses**: `{ "success": bool, "data": ..., "meta"?: ..., "message": string }`

---

## User roles & access matrix

| Role | View matches | Edit lineup | Bar duties | Manage bar duties | Admin |
|---|---|---|---|---|---|
| `super_admin` | ✓ all clubs | ✓ | ✓ | ✓ | ✓ |
| `club_admin` | ✓ own club | ✓ | ✓ | ✓ | ✓ |
| `bar_commissie` | ✓ own club | — | ✓ | ✓ create/edit/delete | — |
| `coach` | ✓ managed teams | ✓ own teams | ✓ own teams read-only | ✓ assign members only | — |
| `member` | ✓ own teams | — | ✓ own teams read-only | — | — |

Store `roles[]` from `/auth/me` in app state. Check `roles.contains('coach')` etc.

---

## Authentication

### Login
```
POST /auth/login
Body: { "email": "string", "password": "string" }

Response 200:
{
  "success": true,
  "data": {
    "token": "1|abc...",
    "token_type": "Bearer",
    "user": {
      "id": "uuid",
      "name": "string",
      "email": "string",
      "club_id": "uuid",
      "club": { "id", "name", "slug", "logo_path" },
      "roles": ["coach"],
      "managed_teams": [{ "id", "name" }],
      "is_active": true
    }
  }
}
```

### Logout
```
POST /auth/logout   (requires auth header)
```

### Current user
```
GET /auth/me   → same user shape as login
```

Persist token + user object in FlutterFlow's App State or secure storage.
Redirect to login when 401 is received.

---

## API endpoints

### Teams
```
GET /teams
  ?club_id=uuid (optional, admin only)
  Response: [{ id, name, external_id, is_active, club_id, members_count }]

GET /teams/{id}
  Response: team object

GET /teams/{id}/members
  Response: [{ id, name, role, email, phone, is_active }]
```

### Members
```
GET /members
  ?team_id=uuid  ?role=player|coach  ?search=string  ?per_page=25
  Response: paginated [{ id, name, email, phone, role, is_active,
                         external_id, teams[{id,name}] }]

GET /members/{id}
  Response: member + teams
```

### Matches
```
GET /matches
  ?team_id=uuid  ?status=scheduled|played|cancelled|postponed
  ?upcoming=1    ?date_from=YYYY-MM-DD  ?date_to=YYYY-MM-DD
  ?is_home=false ?per_page=25
  Response: paginated [{
    id, match_datetime, arrival_time, opponent, location,
    is_home, status, score_home, score_away, notes,
    team: {id,name}, coach: {id,name}|null,
    fruit_hero: {id,name}|null
  }]

GET /matches/{id}
  → same + drivers[], lineup.players[], goals[]

PATCH /matches/{id}
  Body (all optional):
    arrival_time: "H:i"
    coach_id: uuid|null
    fruit_hero_id: uuid|null
    driver_ids: [uuid, ...]
    notes: string
```

### Lineup
```
GET  /matches/{id}/lineup
  Response: {
    match_id, players: [{
      id, position, jersey_number, is_starter, is_captain,
      member: {id, name}
    }]
  }

POST /matches/{id}/lineup
  Body: {
    players: [{
      member_id: uuid,
      position: string,       // e.g. "GK","CB","CM","CF"
      jersey_number: int|null,
      is_starter: bool,
      is_captain: bool
    }]
  }
```

### Goals
```
GET    /matches/{id}/goals
  Response: [{ id, minute, type, scorer:{id,name}, assist:{id,name}|null }]

POST   /matches/{id}/goals
  Body: { minute: int, type: "normal|penalty|own_goal",
          scorer_id: uuid, assist_id: uuid|null }

DELETE /matches/{id}/goals/{goal_id}
```

### Bar duties  ⚠️ ADD TO BACKEND (see section below)
```
GET /bar-duties
  ?team_id=uuid  ?status=open|bevestigd|vervuld
  ?date_from=YYYY-MM-DD  ?date_to=YYYY-MM-DD
  Response: paginated [{
    id, date, shift, status, notes, club_id,
    team: {id,name}|null,
    members: [{id,name,phone}]
  }]

GET /bar-duties/{id}

PATCH /bar-duties/{id}/members
  Body: { member_ids: [uuid, uuid] }   // max 2
  → auto-sets status to 'bevestigd' when 2 assigned
```

### Drive schedule (derived from matches)
```
GET /matches?is_home=false&has_drivers=1
  (Use the regular matches endpoint with these filters)
  Shows away matches that have drivers assigned.
```

---

## Backend endpoints still needed

Add these to `routes/api.php` and create controllers:

```php
// Bar duties
Route::apiResource('bar-duties', BarDutyController::class)
    ->only(['index','show','store','update','destroy']);
Route::patch('bar-duties/{barDuty}/members', [BarDutyController::class, 'assignMembers']);
```

`BarDutyController::index` must scope by club (from authenticated user's `club_id`), and filter coaches to only see their own teams' duties.

---

## Screens & navigation

### Bottom navigation (all roles)
```
[ Wedstrijden ]  [ Rijschema ]  [ Bardiensten ]  [ Profiel ]
```
Admins / bar_commissie also see a floating "+" button on the Bardiensten tab.

---

### 1. Login screen
- Logo centered, email + password fields, "Inloggen" button
- Show error toast on 401/403
- On success: save token + user to App State, navigate to Home

---

### 2. Wedstrijden (Match list)
- Filter bar: team selector (from user's managed teams), status chips
  (`Gepland` `Gespeeld` `Uitgesteld` `Geannuleerd`), date range
- List card per match:
  ```
  [Datum]           [Elftal naam]
  [Tegenstander]    [Thuis 🏠 / Uit 🚗]
  [Locatie]         [Status badge]  [Score]
  ```
- Tap → Match detail screen
- Infinite scroll pagination

---

### 3. Wedstrijd detail
Tabs: **Info** | **Opstelling** | **Doelpunten**

**Info tab**
- Date/time, opponent, location, arrival time
- Coach, fruitheld, kleedkamer
- Drivers list (with phone tap-to-call)
- Notes
- Edit button (coach/admin): opens edit sheet for arrival_time, notes

**Opstelling tab**
- Pitch visualisation or simple list grouped by: Keeper / Verdediging / Middenveld / Aanval / Wisselspelers
- Each player card: jersey number, name, captain badge
- Edit button (coach/admin): opens player picker with position/jersey/captain toggles
- Positions: GK, LB, CB, RB, LM, CM, DM, RM, CAM, LW, ST, RW

**Doelpunten tab**
- Timeline list: minute · type icon · scorer name · (assist)
- "+ Doelpunt" button (coach/admin): picker for minute, type, scorer, assist
- Swipe-to-delete (coach/admin)

---

### 4. Rijschema (Drive schedule)
- Filter: team dropdown, date range
- List grouped by elftal:
  ```
  [dag DD-MM-YYYY HH:mm]  [Tegenstander]
  Verzamelen: HH:mm
  Rijders: Naam1 | Naam2
  Coach: Naam
  ```
- Read-only for all roles
- Export button: share/open PDF (call `GET /matches?is_home=false&has_drivers=1`, render locally or open PDF URL)

---

### 5. Bardiensten (Bar duties)
- Grouped by week, sorted by date
- Card per duty:
  ```
  [Datum + Dienst badge]   [Elftal]
  Ingepland: Naam1, Naam2  [Status badge]
  ```
- Badges: Ochtend=blauw · Middag=geel · Avond=paars
- Status: Open=oranje · Bevestigd=blauw · Vervuld=groen

**Coach actions (own teams only):**
- Tap card → "Leden toewijzen" bottom sheet
  - Shows members from the duty's team
  - Multi-select max 2
  - Save → PATCH /bar-duties/{id}/members

**Bar commissie / admin actions:**
- "+" FAB → Create duty sheet (date picker, shift, team, members, notes)
- Long-press / swipe → edit & delete
- Status chip → quick-update status

---

### 6. Profiel
- Avatar initials or profile photo
- Name, email, phone, role label, club name + logo
- "Uitloggen" button → POST /auth/logout → clear App State → Login screen
- "Wachtwoord wijzigen" → form (current + new password)

---

## Data models (App State / Custom Data Types)

```dart
AppUser {
  String id, name, email;
  String? phone, clubId;
  Club? club;
  List<String> roles;
  List<Team> managedTeams;
}

Club {
  String id, name, slug;
  String? logoPath;
}

Team {
  String id, name;
  bool isActive;
}

FootballMatch {
  String id, opponent, location;
  DateTime matchDatetime;
  String? arrivalTime, status, notes;
  bool isHome;
  int? scoreHome, scoreAway;
  Team? team;
  Member? coach, fruitHero;
  List<Member> drivers;
}

Member {
  String id, name, role;
  String? email, phone;
  bool isActive;
}

LineupPlayer {
  String id, position;
  int? jerseyNumber;
  bool isStarter, isCaptain;
  Member member;
}

Goal {
  String id, type;
  int minute;
  Member scorer;
  Member? assist;
}

BarDuty {
  String id, date, shift, status;
  String? notes;
  Team? team;
  List<Member> members;
}
```

---

## UI / Design system

| Token | Value |
|---|---|
| Primary green | `#16a34a` |
| Dark green | `#15803d` |
| Light green bg | `#dcfce7` |
| Text primary | `#1a1a1a` |
| Text muted | `#6b7280` |
| Border | `#e5e7eb` |
| Background | `#f9fafb` |
| White | `#ffffff` |
| Danger | `#b91c1c` |
| Warning | `#a16207` |

- **Font**: Inter or Roboto, 14sp body, 16sp title, 12sp caption
- **Border radius**: 8px cards, 6px chips, 12px bottom sheets
- **Icons**: Material Icons or Heroicons equivalent
- **Status badges**: rounded pill, colored background + dark text
- **Loading**: CircularProgressIndicator in primary green
- **Error states**: red snackbar with message from `response.message`
- **Empty states**: centered icon + subtitle text

---

## FlutterFlow-specific notes

1. **API Group**: Create one API group `VoetbalPlannerAPI` with base URL and
   `Authorization` header using App State variable `authToken`.

2. **App State variables**:
   - `authToken` (String, persisted)
   - `currentUser` (AppUser JSON, persisted)
   - `selectedClubId` (String, persisted) — for super_admin switching clubs

3. **Authentication flow**:
   - On app launch: if `authToken` is empty → Login page
   - Call `GET /auth/me` on launch to refresh user data; on 401 → Login page

4. **Role checks**: Use Conditional Visibility on widgets checking
   `FFAppState().currentUser.roles.contains('coach')` etc.

5. **Pagination**: Use FlutterFlow's infinite scroll with `page` query param.
   Increment page on scroll-to-end, append to list.

6. **Date formatting**: Display all dates in Dutch locale `dd-MM-yyyy HH:mm`.
   Use `DateFormat('EEEE d MMMM yyyy', 'nl_NL')` for full day names.

7. **Offline**: Cache last-loaded match list and bar duties in local state so
   the app is usable without network on read.

8. **Deep links**: Not required for v1.

9. **Push notifications**: Out of scope for v1 (use WhatsApp via backend).

10. **Multi-tenancy**: The `club_id` is embedded in the auth token via Sanctum.
    The backend scopes all queries automatically. No need to pass `club_id`
    in API calls — the token identifies the club.

---

## Missing API endpoints to build first

Before building the FlutterFlow app, add these to the Laravel backend:

```
GET  /api/v1/bar-duties           list (scoped by club+role)
POST /api/v1/bar-duties           create (bar_commissie/admin)
GET  /api/v1/bar-duties/{id}      show
PATCH /api/v1/bar-duties/{id}     update (bar_commissie/admin)
DELETE /api/v1/bar-duties/{id}    delete (bar_commissie/admin)
PATCH /api/v1/bar-duties/{id}/members   assign members (coach + above)

GET /api/v1/matches?is_home=false&has_drivers=1  (already exists, use as drive schedule)
```

---

## Build order (recommended)

1. API group + auth flow (Login → Me → persist token)
2. Profiel screen (verify auth works)
3. Wedstrijden list + detail (Info tab)
4. Opstelling tab + Doelpunten tab
5. Rijschema screen
6. Bardiensten list
7. Bardiensten — coach assign members
8. Bardiensten — bar commissie create/edit/delete
9. Role-based visibility polish
10. Empty states, error handling, loading skeletons
