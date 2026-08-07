# Whitelabel / Multi-Map Plan

Markermap als Multi-Tenant-Plattform: Nutzer legen Maps (Themes + Marker-Sammlungen) an, erreichen sie unter `/maps/{slug}`, moderieren sie selbst. Die bestehende Tauschbox-Map wird Tenant #1.

Bezug Ist-Stand: `tauschbox-hamburg-shared-hosting-konzept.md` (Single-Tenant). Dieses Dokument überschreibt das Produktmodell, sobald die Phasen umgesetzt sind.

## Phase 0 — Getroffene Entscheidungen

| Thema | Entscheidung |
|---|---|
| Produkt | Theme + Marker-Sammlung (kein volles White-Label-Branding/Custom-Domain im MVP) |
| Isolation | Eine App, eine DB, `tenant_id` — **kein** Deploy pro Map |
| Tenant #1 | Bestehende Tauschbox-Map (Migration der vorhandenen Locations/Submissions) |
| Self-Service | Jeder darf Maps anlegen |
| Map anlegen | Nur mit **E-Mail-Verifikation** |
| URL | **`/maps/{slug}`** (kein Root-Slug, keine Subdomain im MVP) |
| Moderationsmodell | **A — immer moderiert** (Submit → pending → Owner-Approve). Hybrid/offen = später |
| Owner-Zugang | **Magic Link** an Owner-E-Mail |
| Auth-Modell | `User` (E-Mail unique) + Relation `Map.owner`; plus **Super-Admin** (Plattform) |
| Sichtbarkeit | Alles öffentlich (kein private/unlisted im MVP) |
| Geografie | Weltweite Pins möglich; **Center/Zoom Pflicht**, Bounds **optional** |
| Kategorien | Optional pro Map-Config (JSON); Tauschbox behält Kategorien — **nicht** in Notizfeld abschieben |
| Kosten | Free (keine Limits/Billing im MVP) |
| Legal | Plattform-Impressum/Datenschutz zentral; Notify-/Owner-E-Mail = Ansprechpartner der Map |

### Bewusst verworfene Alternativen (MVP)

| Alternative | Warum nicht (jetzt) |
|---|---|
| Root-Slugs (`/tischtennis`) | Kollision mit `/admin`, `/api`, `/impressum`, … |
| Subdomains pro Map | DNS/Hosting-Aufwand ohne Nutzen bei Shared Hosting |
| Community-offen (sofort live) | Spam-/UGC-Risiko; Workflow-Branching verdoppelt Komplexität |
| Env-Admin pro Map | Unvereinbar mit Self-Service |
| Owner nur als Token an `Map` | Blockiert „meine Maps“ / Multi-Map-Owner später |
| Kategorien → Notizfeld | Feature-Abbau für Tenant #1; Generik besser über optionale Config |

## Zielbild

1. Öffentliche Map unter `/maps/{slug}` mit Marker, Vorschlägen, Moderationsfreigabe
2. Nutzer legt Map an → E-Mail-Verify → Map live; Owner steuert Inbox per Magic Link
3. Super-Admin sieht/verwaltet alle Maps; Tauschbox bleibt erster Tenant ohne Datenverlust

## Kernarchitektur

```
Request /maps/{slug}/…
    → TenantResolver (Map by slug, 404 if missing/unverified)
    → alle Queries/API/GeoJSON/Admin strikt tenant-scoped
    → LocationWorkflow + SubmissionMailer mit Map-Kontext
```

- **Map (Tenant):** slug, name, centerLat/Lng, defaultZoom, optional bounds, notifyEmail, owner (User), status (pending_verify | active | disabled), timestamps
- **User:** email (unique); Owner mehrerer Maps möglich
- **Location / Submission:** `map_id` (tenant FK) — kein Query ohne diesen Scope
- **Magic Link:** kurze TTL, one-time; führt in `/maps/{slug}/admin`
- **Super-Admin:** global (weiterhin oder parallel zu heutigem Env-Admin), Map-Liste / Disable

## Fallstricke

- **Datenleck:** GeoJSON/API/Admin ohne Tenant-Filter = fremde Marker sichtbar
- **Reserved slugs:** `admin`, `api`, `new`, `login`, `impressum`, `datenschutz`, … blocken
- **Magic-Link-Diebstahl:** kurze TTL, einmalig; E-Mail-Deliverability auf Shared Hosting testen
- **Unverifizierte Creates:** Map erst nach Mail-Confirm öffentlich/erreichbar
- **Hamburg-Triplikation:** Center/Bounds heute in JS + Assert + Geocoder — muss aus Map-Config kommen
- **UGC/Legal:** Plattform haftet als Betreiber; Owner-Kontakt muss auffindbar sein
- **SQLite:** reicht für Free-MVP; bei vielen Tenants/Writes Exit zu MariaDB (wie bisher vorgesehen)

## Umsetzungsphasen

### Phase 1 — Tenant-Kern

- Entity `Map` (+ Migration)
- `map_id` auf `Location` und `Submission`
- Bestehende Daten → Map `tauschboxen` (oder finaler Slug) zuordnen
- Routing-Prefix `/maps/{slug}/…`
- TenantResolver / Request-Attribute
- Repositories, GeoJSON, Geocode/Reverse-Geocode, Form-Writes tenant-scoped
- `/` → Map-Verzeichnis und/oder Redirect-Hinweis auf `/maps/tauschboxen`

### Phase 2 — Self-Service + Auth

- Create-Form: Name, Slug, Center (Karte/Adresse), Notify-E-Mail
- Map `pending_verify` → Verify-Mail → `active`
- Entity `User` + `Map.owner`
- Magic-Link-Login für Owner-Admin
- Super-Admin (globale Übersicht / Disable)
- Rate-Limit + Honeypot auf Map-Create

### Phase 3 — Admin scoped

- `/maps/{slug}/admin/*` (Inbox, Locations, Approve/Reject)
- Mails: Map-Name in Subject/Body; Notify an Map-Owner/`notifyEmail`
- Kein Zugriff auf fremde Tenant-Daten (auch nicht per ID-Guessing)

### Phase 4 — Generik (Tauschbox-Hardcodes raus)

**Ziel:** Geocode/Validierung/Copy sind map-getrieben; Hamburg-Logik nur noch als Config von Tenant `tauschboxen`, nicht als Plattform-Default.

#### Bereits erledigt (nicht nochmal bauen)

- Map center/zoom/bounds → Stimulus `map-shell` via Twig (`hasBounds`-Flag)
- Kategorien nur bei `Map.usesCategories()` (`categoriesConfig` non-empty); neue Maps `[]`
- Lat/Lng-Form-Validation weltweit; Server prüft `map.containsCoordinates` wenn Bounds gesetzt
- `search` + Platform-Geocode (ein Pfad; Bounds optional aus Map)
- Generisches Fallback-Label `Ort` in `Location::getDisplayLabel` und `map_shell_controller.js`
- `ReverseGeocoder`: Viewbox/`bounded` aus Map-Bounds, sonst DE-weit (`countrycodes=de`); District = rohes Nominatim-Label
- Legal-Copy plattform-generisch (Impressum/Datenschutz)

#### Noch offen (Phase-4-Rest / Nice)

1. **Nice:** Bounds beim Map-Create optional setzen (Viewport → Bounds ableiten oder Checkbox „auf Ausschnitt begrenzen“)
2. HH-Whitelist (`HamburgDistricts`) bleibt als Referenzdaten; bewusst **nicht** im Geocoder — ggf. später tenant-UI

#### Nicht Phase 4

- Custom Domains, Themes, Multi-Mod, Billing  
- Ein-Login (Owner + Plattform) — bewusst später  
- Phase 5 Vertiefung (Owner-Kontakt auf Map-Seite)

### Phase 5 — Plattform-Oberfläche (dünn)

- `/` = Verzeichnis öffentlicher Maps + CTA „Map anlegen“ (Basis erledigt)
- Legal zentral (Plattform); Map-Kontext zeigt Owner-/Notify-Kontakt kurz (offen)

## Must vs. Nice

### Must (MVP)

- `Map` + `/maps/{slug}` + scoped data
- E-Mail-Verifikation beim Anlegen
- Magic-Link Owner + Super-Admin
- Moderations-Workflow wie heute (Modell A)
- Tauschbox-Migration ohne Datenverlust
- Center/Zoom pro Map
- Reserved-Slug-Schutz, Rate-Limit Create

### Nice (später)

- Optionale Bounds-UI
- Kategorien-Config-UI (über Seed/Config hinaus)
- Moderation abschaltbar (Hybrid/offen)
- Farbschema / Logo / Custom Domain
- Mehrere Moderatoren pro Map
- Private/unlisted Maps
- Map-Verzeichnis mit Suche/Ranking
- Billing / Soft-Limits
- „Meine Maps“-Übersicht für Owner (nach User-Modell triviale Erweiterung)

## Komplexität klein halten

1. Tenant-Context **einmal** zentral (Resolver + Repository-Disziplin), nicht in jedem Controller copy-pasten
2. `LocationWorkflow` bleibt die Schreib-Nahtstelle — nur Map-Kontext injizieren
3. Keine Rollenmatrix: Owner + `ROLE_SUPER_ADMIN` reichen
4. Kein globales Category-Enum als Plattform-Pflicht — Config oder nichts
5. Hybrid-Moderation und Branding bewusst nach hinten schieben

## Offene Punkte (nicht blockierend für Start)

| Punkt | Default-Annahme |
|---|---|
| Finaler Slug Tenant #1 | `tauschboxen` |
| Super-Admin: Env-User vs. DB-User | Env zunächst beibehalten, parallel zu Owner-Magic-Link |
| Verify-Mail vs. Magic-Link-Templates | Eigene schlanke Twig-Mails, Map-Name parametrisiert |
| Verzeichnis auf `/` vs. Soft-Redirect nur Tauschbox | Verzeichnis + prominenter Link Tenant #1 |

## Bezug Deploy / Hosting

Unverändert Shared Hosting (siehe `DEPLOY.md`). Multi-Tenant erhöht nur Daten- und Auth-Komplexität in einer Codebase — kein zweites Deploy-Ziel pro Map.

## Umsetzungsstand

| Phase | Status |
|---|---|
| 0 Entscheidungen | festgehalten |
| 1 Tenant-Kern | erledigt |
| 2 Self-Service + Auth | erledigt |
| 3 Admin scoped | Owner-Admin erledigt; Super-Admin `/admin` global; UX-Labels Owner vs. Plattform |
| 4 Generik | **Kern erledigt** — offen nur Nice: Bounds-UI beim Create; HH-Districts-Whitelist bewusst unverkabelt |
| 5 Plattform-Oberfläche | Basis (`/`, Create-CTA) da; Owner-Kontakt auf Map optional |

Branch: `feature/whitelabel-multi-map`.

### Handoff-Prompt (neuer Chat)

```
Branch feature/whitelabel-multi-map. Lies docs/WHITELABEL.md Phase 4/5.
Phase-4-Kern erledigt (ReverseGeocoder map-bounds, generic districts, Ort-Defaults, Legal).
Optional: Bounds-UI beim Map-Create; Phase 5 Owner-Kontakt auf Map-Seite.
Nicht anfassen: Ein-Login, Custom Domains. ddev nutzen.
```
