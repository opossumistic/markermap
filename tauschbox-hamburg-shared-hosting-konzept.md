# Tauschbox Hamburg auf Shared Hosting

Schlanke Open-Source-Webanwendung: Karte öffentlicher Tauschboxen in Hamburg, Vorschläge aus der Community, Moderationsfreigabe. Kein BaaS, keine Google-Abhängigkeit, Betrieb auf Shared Hosting (nur FTP).

## Getroffene Entscheidungen

| Thema | Entscheidung |
|---|---|
| Framework | Symfony mit Flex (Web-App), Twig + Form + Security + Doctrine |
| Datenbank | SQLite (bewusst zum Ausprobieren; Exit zu MariaDB nur bei Bedarf) |
| Admin | Zuerst eigenes Mini-Admin (Inbox + Location-Edit); EasyAdmin optional nachrüsten |
| Write-Path | Alles Community-Schreiben als Submission → Review → Live |
| Titel | Optional; Anzeige via `displayLabel` (Titel oder Kategorien · Stadtteil) |
| Kategorien | 1–n als JSON-Array von Enum-Werten (`categories`) |
| Löschen | Soft-Delete über Status (`removed` + ggf. `deleted_at`, eine Wahrheit) |
| Karte | MapLibre GL JS; Basemap-Style-URL austauschbar per Env |
| Basemap (MVP) | OpenFreeMap Liberty (`https://tiles.openfreemap.org/styles/liberty`) |
| `disputed` auf Karte | Ja, visuell anders (grau/Badge); weg nur via `removed` nach Review |
| Migrations ohne SSH | Geschützter One-Shot-Endpoint mit Secret |
| Lokal | DDEV (`tauschmap.ddev.site`), PHP 8.2, ohne DB-Container (`omit_containers: [db]`) |
| Hosting | Shared Hosting, **nur FTP** (kein SSH, kein Docker) |
| Deploy | GitHub Actions baut Artefakt und lädt per FTP hoch |
| Auth öffentlich | Kein Muss; Moderation-first. Admin: einfacher Login |
| EasyAdmin | Nicht im MVP |

## Zielbild

Drei Dinge gut können:

1. Tauschboxen auf einer Karte anzeigen
2. Neue Orte vorschlagen lassen
3. Bestehende Orte durch die Community melden/bestätigen lassen (moderiert)

## Warum Shared Hosting den Stack bestimmt

Grenzen: kein Root, kein Docker, kein SSH, nur FTP, klassisches PHP, Cron falls vorhanden. Dadurch fallen Appwrite, Container-Stacks, Worker-Pflicht und Realtime aus.

Architektur-Mittel:

- Symfony-Anwendung (Build in CI, Upload per FTP)
- SQLite-Datei außerhalb des Docroots
- Bild-Uploads im Dateisystem (ebenfalls deploy-geschützt)
- Keine Queues, keine Microservices; Cron nur für Backup/Cleanup falls verfügbar

## Empfohlene Minimal-Architektur

### Backend

Symfony mit Flex, Web-App-Skeleton (oder gleichwertig schlank per Rezepten):

- Twig für öffentliche Seiten und Admin
- Doctrine ORM + Migrations
- Symfony Form + Validator
- Security für Admin-Bereich
- AssetMapper statt Webpack-Encore (kein Node auf dem Server nötig)

Domain-Services statt „Logik im Controller“, z. B. `SubmitLocation`, `ApproveSubmission`, `RejectSubmission`, `ReportLocation`.

### Datenbank (SQLite)

DSN z. B. `sqlite:///%kernel.project_dir%/var/data/app.db`.

Betriebsregeln:

- DB-Datei **außerhalb** von `public/`
- WAL-Mode (`PRAGMA journal_mode=WAL`)
- Backup per Cron oder manuell (`sqlite3 … .backup`)
- FTP-Deploy darf `var/data/` **nie** überschreiben/löschen

Migration Exit: bei nachgewiesenem Schmerz (Locks, Backup-Tooling) → MariaDB. Nicht parallel pflegen.

### Karte

- MapLibre als feste Bibliothek
- Default-Style: OpenFreeMap Liberty — kein API-Key, MapLibre-kompatibel
- `MAP_STYLE_URL` (+ Attribution) per Env; Provider jederzeit austauschbar
- Keine harte Bindung an maptoolkit.org oder einen Paid-Vendor
- Marker/Fachdaten nur aus der eigenen DB
- OSM-Standard-Tileserver (`tile.openstreetmap.org`) nicht als Produktiv-Default (Usage Policy)
- Fallback später bei Bedarf: MapTiler/Stadia Free-Tier oder Protomaps — nicht MVP
- Hinweis: OpenFreeMap Public Instance ohne SLA; für Community-MVP akzeptabel

### Bilder

- Ein Bild pro Ort im MVP
- Upload-Ordner außerhalb oder unter geschütztem Pfad; nicht durch Deploy wischen
- Größenlimit, MIME-Prüfung, serverseitige Verkleinerung soweit praktikabel

### Authentifizierung

- Öffentlich: Vorschläge ohne Voll-Konto, erscheinen erst nach Freigabe
- Admin: ein oder wenige User (Security + Login-Form)
- Magic-Link/Community-Login: Nice-to-have, nicht MVP

## Status-Modell (Soft-Delete)

Eine klare State-Machine für `Location`:

```text
pending → active ⇄ disputed → removed
```

| Status | Bedeutung | Auf der Karte |
|---|---|---|
| `pending` | Noch nicht freigegeben (falls Location schon angelegt) | nein |
| `active` | Sichtbar | ja, normaler Marker |
| `disputed` | „stimmt nicht mehr“ gemeldet, Review offen | ja, visuell anders (grau/gestrichelt + Badge „ungeprüft“) |
| `removed` | Soft-Delete nach Moderationsentscheid | nein |

Regel: `deleted_at` nur setzen wenn `status = removed` (eine Wahrheit, nicht redundant ohne Regel).

Karten-Query default: `status IN (active, disputed)`. `pending` und `removed` nie.

Verhalten bei Meldungen:

- Eine einzelne „stimmt nicht mehr“-Meldung → Location wird `disputed`, bleibt sichtbar (kein sofortiges Ausblenden)
- `confirmation` („existiert noch“) → nur `confirmed_at` aktualisieren; Status bleibt `active` (bzw. Admin kann `disputed` → `active` zurücksetzen)
- `removed` nur nach Admin-Approve einer Statusmeldung (oder manuellem Soft-Remove) — kein Auto-Delete im MVP
- Optional später: Threshold (z. B. 3 Reports) eskaliert nur die Inbox-Priorität, löscht nicht automatisch

Submissions ändern Live-Daten **nur** über Approve im Admin.

## Funktionen für den MVP

- Karte mit `active` + `disputed` Locations (`disputed` optisch unterscheidbar)
- Detail: Titel, Bild, kurze Beschreibung, Status
- Formular „neue Box vorschlagen“
- Meldung „existiert noch“ / „stimmt nicht mehr“ → Submission
- Admin-Inbox: Submissions freigeben/ablehnen
- Admin: Location editieren, soft-remooven (`removed`)
- Mobile-tauglich; „in meiner Nähe“ und Filter-Chips: zweite Priorität

Bewusst später:

- EasyAdmin
- Community-Login
- Mehrere Bilder
- Realtime, Gamification, komplexes Rollenmodell
- Eigener Tileserver

## Suche und Geocoding

1. Suche in eigenen Daten (Titel, Straße, Stadtteil) — kostenlos
2. Geocoding nur beim Anlegen/Bearbeiten, Ergebnis in `lat`/`lng` persistieren, sparsam cachen

Normale Kartennutzung hängt nicht am Geocoder.

## Datenmodell

### `locations`

- `id`
- `title`
- `street`
- `postal_code`
- `district`
- `lat`, `lng`
- `description`
- `category` (feste Liste/Enum im MVP, keine freien Tags)
- `image_path`
- `status` (`pending` \| `active` \| `disputed` \| `removed`)
- `deleted_at` nullable
- `confirmed_at` nullable
- `created_at`, `updated_at`

### `submissions`

- `id`
- `location_id` nullable
- `type` (`new` \| `correction` \| `status_report` \| `confirmation`)
- `payload_json`
- `email` nullable
- `created_at`
- `reviewed_at` nullable
- `review_status` (`open` \| `approved` \| `rejected`)

## Admin ohne EasyAdmin

Must:

- Login
- Inbox offener Submissions
- Approve/Reject (Domain-Service)
- Location-Liste + Edit + Soft-Remove

EasyAdmin nachrüsten, wenn nach realer Moderationsarbeit klar ist: viel manuelles Location-CRUD/Filtern, wenig Inbox — und eigene Admin-Templates wuchern.

## Moderation und Missbrauchsschutz

- Honeypot am Vorschlagsformular
- Rate Limiting soweit mit Symfony möglich
- Alles erst nach Review live
- Bild: Größe + Typ
- Keine direkten Live-Edits durch anonyme Nutzer

## Deploy: GitHub Actions → FTP

### Pipeline (Skizze)

1. Checkout
2. `composer install --no-dev --optimize-autoloader`
3. Assets (AssetMapper / `importmap:install` o. ä.)
4. Optional: `cache:warmup` mit prod-Env (soweit ohne Server-Secrets möglich)
5. FTP-Upload des Artefakts
6. **Excludes / Skip löschen:** `var/data/`, Uploads, `.env.local`, idealerweise `var/cache` server-seitig neu aufbauen-Strategie festlegen

### Server-Layout

- Docroot → `public/`
- `.env.local` nur auf dem Server (`APP_SECRET`, SQLite-Pfad, `MAP_STYLE_URL=https://tiles.openfreemap.org/styles/liberty`, `MIGRATE_TOKEN`, Admin-Secrets)
- Schreibbar: `var/data/`, `var/cache/`, `var/log/`, Upload-Verzeichnis

### Migrations ohne SSH

Kein `bin/console` per SSH.

**Gewählt:** geschützter Endpoint (z. B. `/_ops/migrate`), der `doctrine:migrations:migrate --no-interaction` ausführt.

Absicherung (Must):

- Starkes Secret in `.env.local` (`MIGRATE_TOKEN` o. ä.), Vergleich per `hash_equals`
- Nur in `APP_ENV=prod` sinnvoll nutzbar; nach Deploy bewusst aufrufen
- Kein Link in der UI; Aufruf nur manuell / aus CI-Doku
- Token nach Leak rotieren; optional IP-Allowlist wenn Hosting das hergibt
- Vor jedem Migrate: SQLite-Backup (`var/data/`)

Notnagel (nicht Default): DB-Datei ersetzen — nur mit Backup, quasi nie.

## UI (Richtung, nicht Scope-Explosion)

- Mobil zuerst, Karte als Start
- Detail als Bottom-Sheet später ok
- CTA „Box eintragen“ sichtbar
- Freude durch Schnelligkeit und klare Bestätigung, nicht durch Gamification

## Was bewusst weggelassen wird

- Firebase / proprietäre BaaS
- Appwrite & Co. auf Shared Hosting
- maptoolkit.org als feste Kernabhängigkeit
- EasyAdmin im MVP
- WebSockets / Live-Feed
- Komplexe Rollen
- Eigener Tileserver

## Lokale Entwicklung (DDEV)

```bash
ddev start
ddev launch                 # https://tauschmap.ddev.site
ddev exec php bin/console   # Symfony-CLI im Container
ddev composer require …     # Composer immer über ddev
```

- Projekttyp: `symfony`, Docroot `public`, PHP 8.2
- Kein MariaDB-Container — SQLite unter `var/data/app.db` (Parity mit Shared Hosting)
- Uploads: `public/uploads` (DDEV `upload_dirs`)
- `MAP_STYLE_URL` in `.env` / `.env.local`
- Kein Symfony-`compose.yaml` — DDEV ist die lokale Runtime

## Umsetzungsreihenfolge (aktuell)

1. Domänenkern (Entities, Workflow, Admin-Inbox) — erledigt
2. Vorschlagsformular (mit lat/lng-Übergang) — erledigt
3. **Öffentliche Karte** (MapLibre via CDN + GeoJSON + Marker active/disputed) — erledigt
4. **Kartenpicker** — erledigt, integriert in One-Page
5. Domäne: optionaler Titel, Kategorien 1–n — erledigt
6. **One-Page Map-Shell** (Browse / Add / Detail-Sheet) — erledigt
7. Adress-Geocoding (Reverse + Forward-Suche) + optionales Foto — erledigt
8. Deploy CI→FTP, Migrate-Endpoint, Admin-Hash, Legal-Stubs — erledigt (siehe `DEPLOY.md`)

Hinweis: MapLibre **nicht** über AssetMapper vendorn — Worker/MIME brechen (Firefox `NS_ERROR_CORRUPTED_CONTENT`). UMD+CSS von unpkg/jsDelivr. Shared Loader: `assets/maplibre.js`.


## Nächste Schritte (nach Go-Live)

1. Hosting-Secrets setzen + erster FTP-Deploy + `/_ops/migrate`
2. Impressum/Datenschutz-Platzhalter mit echten Daten füllen
3. `GEOCODER_USER_AGENT` mit realer Kontakt-URL
4. Feinschliff: „in meiner Nähe“, Filter, Rate-Limit — EasyAdmin nur bei Bedarf
