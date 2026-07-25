# Deploy (Shared Hosting, SFTP — analog Jobboard)

## Server-Layout (Produktion)

| Rolle | Pfad |
|---|---|
| SFTP-Deploy-Ziel (`SFTP_REMOTE_PATH`) = Symfony-Projektwurzel | `/public_html/markermap` |
| Web-Docroot (Hoster muss hierhin zeigen) | `/public_html/markermap/public` |

Deploy lädt die App nach `markermap/`; darunter entstehen `public/`, `src/`, `vendor/`, `var/` usw. Zeigt der Hoster nur auf `/public_html/markermap` (ohne `/public`), liegen Secrets und Code im Webroot — das ist falsch.

## Voraussetzungen Hosting

- PHP ≥ 8.2, Extensions: `pdo_sqlite`, `ctype`, `iconv`, `mbstring`, `intl`, **`zip`** (für Vendor-Unpack)
- Domain/Subdomain-Docroot → `/public_html/markermap/public`
- Schreibrechte: `var/`, `public/uploads/`, `vendor/` (Replace beim Unpack)

## Erster Server-Setup (einmalig)

1. Verzeichnis `/public_html/markermap` muss **bereits existieren**. Fehlt es, stimmt `SFTP_REMOTE_PATH` nicht (Hosting ist SFTP-only, kein SSH-Shell). Die Pipeline legt fehlende Child-Ordner per SFTP `-mkdir` an.
2. Hoster-Docroot der Domain auf `/public_html/markermap/public` setzen.
3. Verzeichnisse anlegen und **für den PHP-User beschreibbar** machen:
   - `var/cache`, `var/log`, `var/data`, `var/data/backups`, `var/tmp`
   - `public/uploads/locations`
   - Typisch: Rechte `775` auf diese Ordner (File-Manager / FTP). Fehlt `var/data` oder ist er nicht schreibbar → `SQLSTATE[HY000] [14] unable to open database file`.
4. `.env.local` **nur auf dem Server** (nicht committen), Beispiel:

```dotenv
APP_ENV=prod
APP_SECRET=<langes-zufaelliges-geheimnis>
DATABASE_URL="sqlite:///%kernel.project_dir%/var/data/app.db"
MAP_STYLE_URL=https://tiles.openfreemap.org/styles/liberty
GEOCODER_USER_AGENT="tauschmap/1.0 (https://deine-domain.tld; kontakt@deine-domain.tld)"
ADMIN_USERNAME=markermap-mod
# bcrypt/argon hash — erzeugen: php -r "echo password_hash('DEIN_PASSWORT', PASSWORD_DEFAULT);"
ADMIN_PASSWORD='$2y$...'
MIGRATE_TOKEN=<langes-zufaelliges-token>
MAILER_DSN=null://null
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
```

5. Nach erstem Code-Upload Migration auslösen (Pipeline macht das automatisch, wenn Secrets gesetzt sind).

## GitHub Secrets

| Secret | Bedeutung |
|---|---|
| `SFTP_HOST` | Hostname |
| `SFTP_PORT` | Port (meist `22`) |
| `SFTP_USERNAME` | SFTP-User |
| `SFTP_PASSWORD` | SFTP-Passwort |
| `SFTP_REMOTE_PATH` | `/public_html/markermap` (Projektwurzel) |
| `OPS_BASE_URL` | Öffentliche Origin, z. B. `https://deine-domain.tld` (ohne Slash am Ende) |
| `MIGRATE_TOKEN` | **Gleicher** Wert wie `MIGRATE_TOKEN` in Server-`.env.local` |

Deploy: Push auf `main` oder manuell unter **Actions → Deploy → Run workflow**.

## Vendor-Deploy (wichtig — Geschwindigkeit)

SFTP ist langsam bei Tausenden kleiner Dateien (~1 h), aber akzeptabel bei **einer** großen Datei.

Deshalb:

1. `vendor/` wird **nie** per Dateibaum hochgeladen.
2. Nur wenn sich `composer.lock` seit dem letzten erfolgreichen Deploy geändert hat:
   - CI packt `vendor/` als `var/tmp/vendor-deploy.zip`
   - lädt **eine** Zip per SFTP nach `var/tmp/`
   - ruft `POST /_ops/unpack-vendor.php` auf (Standalone-PHP, braucht kein bestehendes Symfony-`vendor`)
3. Unveränderte `composer.lock` → kein Vendor-Upload (Sekunden statt Stunde).

`public/vendor/` (self-hosted MapLibre) gehört **nicht** zu Composer und wird normal mitdeployt.

Manuell entpacken (falls nötig):

```bash
curl -X POST "https://deine-domain.tld/_ops/unpack-vendor.php" \
  -H "X-Migrate-Token: $MIGRATE_TOKEN"
```

Voraussetzung: `vendor-deploy.zip` liegt bereits unter `var/tmp/` auf dem Server.

## Excludes (Pipeline)

- `vendor/` (Composer — nur als Zip)
- `var/tmp/` (Zip wird separat gelegt)
- `var/data` (SQLite)
- `public/uploads/locations` (Fotos)
- `.env*` (Server-`.env.local` bleibt)
- Tests / `.ddev` / Konzept-Markdown

## Troubleshooting: `unable to open database file`

Ursache fast immer: Ordner `var/data/` fehlt oder PHP darf dort nicht schreiben (`var/data` ist Deploy-Exclude).

Sofort-Fix (FTP / File-Manager):

1. Anlegen: `var/data`, `var/data/backups` (unter der Symfony-Projektwurzel, nicht unter `public/`)
2. Rechte: Ordner beschreibbar (`775` o. ä.)
3. Runtime + Schema:

```bash
curl -X POST "https://deine-domain.tld/_ops/ensure-runtime.php" \
  -H "X-Migrate-Token: $MIGRATE_TOKEN"

curl -X POST "https://deine-domain.tld/_ops/migrate" \
  -H "X-Migrate-Token: $MIGRATE_TOKEN"
```

4. Seite neu laden.

`ensure-runtime.php` meldet im JSON, ob `var/data` writable ist.

## Migrationen ohne SSH

Pipeline ruft nach Deploy automatisch auf (wenn `OPS_BASE_URL` + `MIGRATE_TOKEN` gesetzt):

```bash
curl -X POST "https://deine-domain.tld/_ops/migrate" \
  -H "X-Migrate-Token: $MIGRATE_TOKEN"
```

Der Endpoint:

- prüft Token per `hash_equals`
- legt vor SQLite-Migration ein Backup unter `var/data/backups/` an
- führt `doctrine:migrations:migrate --no-interaction` aus

Kein UI-Link. Token bei Leak rotieren (Server-`.env.local` **und** GitHub Secret).

## AssetMapper

CI führt `asset-map:compile` aus; kompilierte Assets landen unter `public/assets/` und werden mit hochgeladen.

## Erwartete Laufzeiten (Richtwerte)

| Fall | Dauer |
|---|---|
| Nur App-Code (`composer.lock` gleich) | wenige Minuten |
| `composer.lock` geändert (Zip + Unpack) | oft 2–10 Min statt ~1 h |
| Erster Deploy / leerer Cache | wie Lock-Change (Zip nötig) |
