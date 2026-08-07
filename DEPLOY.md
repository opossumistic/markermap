# Deploy (Shared Hosting, SFTP — Release-Layout)

SFTP-only (kein SSH-Exec). Deshalb kein Deployer: Releases + Symlink-Switch per PHP-Ops.

## Server-Layout

```
/public_html/markermap/          ← SFTP_REMOTE_PATH (Deploy-Root)
  shared/
    .env.local                   ← Secrets (nie aus Git)
    var/data/                    ← SQLite + backups
    var/log/
    var/tmp/                     ← vendor-deploy.zip Staging
    public/uploads/              ← UGC-Fotos
    vendor/                      ← Composer-Cache (Zip-Unpack); wird bei Activate ins Release gespiegelt
  releases/
    20260807-211500-a1b2c3d/     ← App-Tree + vendor/ (Hardlink/Copy aus shared, kein Symlink)
    …
  current → releases/20260807-…  ← atomarer Switch
```

| Rolle | Pfad |
|---|---|
| SFTP-Deploy-Ziel | `/public_html/markermap` |
| **Web-Docroot (Hoster)** | `/public_html/markermap/current/public` |

`current/public` ist Pflicht nach dem Cutover. Zeigt der Hoster noch auf `markermap/public` (Legacy), läuft der alte In-Place-Code weiter — neue Releases sind unsichtbar.

**Wichtig:** `vendor/` darf **nicht** als Symlink nach `shared/vendor` zeigen. Composer setzt `$baseDir = dirname(vendor)` — bei Symlink nach `shared/` wird `App\Kernel` unter `shared/src` gesucht. Activate materialisiert deshalb `shared/vendor` → `releases/{id}/vendor` (Hardlink, sonst Copy).

## Voraussetzungen Hosting

- PHP ≥ 8.2, Extensions: `pdo_sqlite`, `ctype`, `iconv`, `mbstring`, `intl`, **`gd`**, **`zip`**
- **Symlinks erlaubt** (Docroot über `current`)
- Schreibrechte: `shared/**`, `releases/*/var/cache`, `releases/*/var/tmp`

## Einmaliger Cutover (Legacy → Releases)

Ist-Stand vorher: App lag direkt unter `markermap/` (`public/`, `src/`, `var/`, …).

1. **Secrets prüfen:** `shared/.env.local` wird beim ersten Activate aus Legacy-`.env.local` kopiert, wenn `bootstrap_shared=1` (Default). Sonst vorher manuell nach `shared/.env.local` legen.
2. **Ersten Release-Deploy** laufen lassen (Actions → Deploy):
   - `force_vendor: true` (wichtig — `shared/vendor` ist noch leer)
   - `bootstrap_shared: true`
3. Activate legt `shared/`, linkt Release, setzt `current`.
4. **Hoster-Docroot** auf `/public_html/markermap/current/public` umstellen.
5. `/` und `/maps/tauschboxen` smoke-testen.
6. Legacy-Reste unter `markermap/src`, `markermap/public` (nicht `current`) später per FTP aufräumen — sie sind tot, sobald Docroot auf `current` zeigt. **Nicht** `shared/` löschen.

Sofort-Fix für den alten Orphan-`HomeController` vor dem Cutover: Datei unter Legacy `src/Controller/HomeController.php` löschen + `var/cache/prod` leeren — oder Cutover durchziehen (neues Release enthält die Datei nicht).

## GitHub Secrets

| Secret | Bedeutung |
|---|---|
| `SFTP_HOST` / `SFTP_PORT` / `SFTP_USERNAME` / `SFTP_PASSWORD` | SFTP |
| `SFTP_REMOTE_PATH` | `/public_html/markermap` (Deploy-Root, nicht `…/current`) |
| `OPS_BASE_URL` | Öffentliche Origin, z. B. `https://markermap.example.tld` |
| `MIGRATE_TOKEN` | Gleicher Wert wie in `shared/.env.local` |

Deploy: Push auf `main` oder **Actions → Deploy → Run workflow**.

Workflow-Inputs:

| Input | Default | Zweck |
|---|---|---|
| `force_vendor` | false | Vendor trotzdem hochladen (erster Cutover / kaputtes shared/vendor) |
| `bootstrap_shared` | true | Legacy `.env.local` / DB / Uploads nach `shared/` übernehmen falls fehlend |
| `keep_releases` | 5 | Ältere Releases löschen |

## Pipeline (Ablauf)

1. `composer install` + `asset-map:compile` in CI  
2. Tree-Sync → `releases/{id}/` (**ohne** `vendor/`, ohne Persistenz-Pfade)  
3. Ops-Scripts zusätzlich nach live `public/_ops/` und ggf. `current/public/_ops/` (Bootstrap vor Docroot-Switch)  
4. Bei Lock-Change: `vendor-deploy.zip` → `shared/var/tmp/` → `POST /_ops/unpack-vendor.php` → `shared/vendor/` (Cache)  
5. `POST /_ops/ensure-runtime.php`  
6. `POST /_ops/activate-release.php` → Persistenz-Symlinks + **Vendor materialisieren** + `current` umlegen + Prune  
7. `POST /_ops/migrate`

## `.env.local` (nur auf dem Server unter `shared/`)

```dotenv
APP_ENV=prod
APP_SECRET=<langes-zufaelliges-geheimnis>
DATABASE_URL="sqlite:///%kernel.project_dir%/var/data/app.db"
MAP_STYLE_URL=https://tiles.openfreemap.org/styles/liberty
GEOCODER_USER_AGENT="tauschmap/1.0 (https://deine-domain.tld; kontakt@deine-domain.tld)"
ADMIN_USERNAME=markermap-mod
ADMIN_PASSWORD='$2y$...'
MIGRATE_TOKEN=<langes-zufaelliges-token>
MAILER_DSN=null://null
MAILER_FROM="Markermap <noreply@deine-domain.tld>"
ADMIN_NOTIFY_EMAIL=du@deine-domain.tld
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
DEFAULT_URI=https://deine-domain.tld
```

`%kernel.project_dir%` zeigt auf das aktive Release (`current`); `var/data` ist Symlink nach `shared/var/data`.

`.env` liegt **im Release** (wird deployed; CI schließt nur `.env.local` / `.env.*.local` aus). `.env.local` liegt nur in `shared/` und wird per Symlink eingehängt. `public/index.php` setzt defensiv `APP_RUNTIME_OPTIONS[project_dir]` auf das Release.

## Vendor

- Nie per Dateibaum. Zip → `shared/var/tmp/` → Unpack → `shared/vendor/` (Cache).  
- Unveränderte `composer.lock` → Skip Unpack; Activate spiegelt weiterhin `shared/vendor` ins Release.  
- Pro Release: **echte** `vendor/`-Tree (Hardlinks wo möglich) — **kein** Symlink nach `shared/`.  
- `public/vendor/` (MapLibre) gehört zum Release-Tree.

```bash
curl -X POST "https://deine-domain.tld/_ops/unpack-vendor.php" \
  -H "X-Migrate-Token: $MIGRATE_TOKEN"
```

## Activate / Rollback

```bash
# Neues Release (macht die Pipeline)
curl -X POST "https://deine-domain.tld/_ops/activate-release.php" \
  -H "X-Migrate-Token: $MIGRATE_TOKEN" \
  -d "release=20260807-211500-a1b2c3d&keep=5&bootstrap_shared=1"

# Rollback: älteres Release erneut aktivieren (muss noch unter releases/ liegen)
curl -X POST "https://deine-domain.tld/_ops/activate-release.php" \
  -H "X-Migrate-Token: $MIGRATE_TOKEN" \
  -d "release=20260807-200000-oldsha&keep=5"
```

## Migrationen

```bash
curl -X POST "https://deine-domain.tld/_ops/migrate" \
  -H "X-Migrate-Token: $MIGRATE_TOKEN"
```

Backup unter `shared/var/data/backups/` vor SQLite-Migration.

## Troubleshooting

### `Variable "map" does not exist` auf `/`

Legacy-`HomeController.php` noch unter altem Docroot. Cutover (Docroot → `current/public`) oder Orphan löschen + Cache leeren.

### `shared/vendor/autoload.php missing`

Deploy mit `force_vendor: true`.

### `Unable to read …/releases/…/.env`

Die SFTP-Action lädt per `put -r …/*` hoch — **Dotfiles** (`.env`) fallen durch. Pipeline hat deshalb einen expliziten `put .env`-Step. Sofort: Repo-`.env` nach `releases/{id}/.env` (bzw. `current/../` = Release-Root) hochladen.

### `shared/.env.local missing`

Datei nach `shared/.env.local` kopieren oder Activate mit `bootstrap_shared=1`, solange Legacy-`.env.local` am Deploy-Root liegt.

### `unable to open database file`

`shared/var/data` fehlt oder nicht beschreibbar (`775`). Dann:

```bash
curl -X POST "https://deine-domain.tld/_ops/ensure-runtime.php" \
  -H "X-Migrate-Token: $MIGRATE_TOKEN"
```

### Symlink-Fehler

Hoster blockiert Symlinks → Release-Modell nicht nutzbar; dann Hosting mit Symlink-Support oder SSH/Deployer.

## AssetMapper

CI: `asset-map:compile` → `public/assets/` im Release.

## Laufzeiten (Richtwerte)

| Fall | Dauer |
|---|---|
| App-Code, Lock gleich | wenige Minuten |
| Lock geändert (Vendor-Zip) | oft 2–10 Min |
| Erster Cutover (`force_vendor`) | wie Lock-Change + Docroot-Umstellung |
