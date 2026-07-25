# Deploy (Shared Hosting, FTP only)

## Server-Layout (Produktion)

| Rolle | Pfad |
|---|---|
| FTP-Deploy-Ziel (`FTP_SERVER_DIR`) = Symfony-Projektwurzel | `/public_html/markermap` |
| Web-Docroot (Hoster muss hierhin zeigen) | `/public_html/markermap/public` |

Deploy lädt die App nach `markermap/`; darunter entstehen `public/`, `src/`, `vendor/`, `var/` usw. Zeigt der Hoster nur auf `/public_html/markermap` (ohne `/public`), liegen Secrets und Code im Webroot — das ist falsch.

## Voraussetzungen Hosting

- PHP ≥ 8.2, Extensions: `pdo_sqlite`, `ctype`, `iconv`, `mbstring`, `intl`
- Domain/Subdomain-Docroot → `/public_html/markermap/public`
- Schreibrechte: `var/`, `public/uploads/`

## Erster Server-Setup (einmalig)

1. Verzeichnis `/public_html/markermap` anlegen (falls leer).
2. Hoster-Docroot der Domain auf `/public_html/markermap/public` setzen.
3. Verzeichnisse anlegen und beschreibbar machen:
   - `var/cache`, `var/log`, `var/data`, `var/data/backups`
   - `public/uploads/locations`
4. `.env.local` **nur auf dem Server** (nicht committen), Beispiel:

```dotenv
APP_ENV=prod
APP_SECRET=<langes-zufaelliges-geheimnis>
DATABASE_URL="sqlite:///%kernel.project_dir%/var/data/app.db"
MAP_STYLE_URL=https://tiles.openfreemap.org/styles/liberty
GEOCODER_USER_AGENT="tauschmap/1.0 (https://deine-domain.tld; kontakt@deine-domain.tld)"
# bcrypt/argon hash — erzeugen: php -r "echo password_hash('DEIN_PASSWORT', PASSWORD_DEFAULT);"
ADMIN_PASSWORD='$2y$...'
MIGRATE_TOKEN=<langes-zufaelliges-token>
MAILER_DSN=null://null
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
```

5. Nach erstem Code-Upload Migration auslösen (siehe unten).

## GitHub Secrets

| Secret | Bedeutung |
|---|---|
| `FTP_SERVER` | Hostname |
| `FTP_USERNAME` | FTP-User |
| `FTP_PASSWORD` | FTP-Passwort |
| `FTP_SERVER_DIR` | `/public_html/markermap/` (Projektwurzel, trailing slash wie vom Hoster erwartet) |

Deploy: Push auf `main` oder manuell unter **Actions → Deploy FTP → Run workflow**.

## Excludes (wichtig)

Die Pipeline **überschreibt/löscht nicht**:

- `var/data/**` (SQLite)
- `public/uploads/locations/**` (Fotos)
- `.env.local`

`dangerous-clean-slate` ist bewusst `false`.

## Migrationen ohne SSH

Nach Deploy:

```bash
curl -X POST "https://deine-domain.tld/_ops/migrate" \
  -H "X-Migrate-Token: $MIGRATE_TOKEN"
```

Der Endpoint:

- prüft Token per `hash_equals`
- legt vor SQLite-Migration ein Backup unter `var/data/backups/` an
- führt `doctrine:migrations:migrate --no-interaction` aus

Kein UI-Link. Token bei Leak rotieren.

## AssetMapper

CI führt `asset-map:compile` aus; kompilierte Assets landen unter `public/assets/` und werden mit hochgeladen.
