<?php

/**
 * Activate a release: link shared paths, switch `current`, prune old releases.
 *
 * POST /_ops/activate-release.php
 * Header: X-Migrate-Token: <MIGRATE_TOKEN>
 * Body (form or JSON): release=<id>[&keep=5][&bootstrap_shared=1]
 *
 * Layout:
 *   {deploy}/shared/          .env.local, var/data, var/log, public/uploads, vendor (cache)
 *   {deploy}/releases/{id}/   app code + materialized vendor/ (hardlink/copy from shared)
 *   {deploy}/current -> releases/{id}
 *
 * Hoster docroot must point to {deploy}/current/public
 *
 * Never symlink vendor into the release — Composer $baseDir would become shared/.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('X-Robots-Tag: noindex');

require __DIR__.'/_helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ops_json_exit(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

ops_require_migrate_token();

$input = $_POST;
$raw = file_get_contents('php://input');
if (is_string($raw) && $raw !== '' && str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
    $decoded = json_decode($raw, true);
    if (\is_array($decoded)) {
        $input = $decoded + $input;
    }
}

$releaseId = trim((string) ($input['release'] ?? ''));
$keep = isset($input['keep']) ? (int) $input['keep'] : 5;
if ($keep < 1) {
    $keep = 1;
}
if ($keep > 20) {
    $keep = 20;
}
$bootstrapShared = !empty($input['bootstrap_shared']);

if ($releaseId === '' || !ops_is_valid_release_id($releaseId)) {
    ops_json_exit(['ok' => false, 'error' => 'invalid_release_id'], 400);
}

$deployRoot = ops_deploy_root();
$shared = $deployRoot.'/shared';
$releasesDir = $deployRoot.'/releases';
$releasePath = $releasesDir.'/'.$releaseId;

if (!is_dir($releasePath)) {
    ops_json_exit([
        'ok' => false,
        'error' => 'release_missing',
        'path' => 'releases/'.$releaseId,
        'deploy_root' => $deployRoot,
    ], 400);
}

if (!is_file($releasePath.'/public/index.php')) {
    ops_json_exit(['ok' => false, 'error' => 'release_incomplete', 'hint' => 'public/index.php missing'], 400);
}

try {
    $createdShared = ops_ensure_shared_layout($shared, $deployRoot, $bootstrapShared);

    // Writable dirs that stay inside the release (not shared).
    foreach (['var/cache', 'var/tmp'] as $rel) {
        if (!ops_mkdirp($releasePath.'/'.$rel)) {
            throw new RuntimeException('Cannot create '.$rel.' in release.');
        }
    }

    // Shared links into this release (persistence only — not vendor).
    $links = [
        '.env.local' => $shared.'/.env.local',
        'var/data' => $shared.'/var/data',
        'var/log' => $shared.'/var/log',
        'public/uploads' => $shared.'/public/uploads',
    ];

    if (!is_file($shared.'/.env.local')) {
        throw new RuntimeException('shared/.env.local missing — copy from legacy root or create it.');
    }

    if (!is_file($shared.'/vendor/autoload.php')) {
        $legacyVendor = $deployRoot.'/vendor';
        if (is_file($legacyVendor.'/autoload.php') && is_dir($legacyVendor) && !is_link($legacyVendor)) {
            // First cutover: reuse in-place vendor (same filesystem rename is cheap).
            $sharedVendor = $shared.'/vendor';
            if (is_dir($sharedVendor) && !is_file($sharedVendor.'/autoload.php')) {
                ops_remove_path($sharedVendor);
            }
            if (is_dir($sharedVendor) || is_link($sharedVendor) || is_file($sharedVendor)) {
                throw new RuntimeException('shared/vendor exists but is incomplete — remove it or run unpack-vendor.');
            }
            if (!@rename($legacyVendor, $sharedVendor)) {
                throw new RuntimeException(
                    'shared/vendor/autoload.php missing and cannot move legacy vendor — re-run deploy with force_vendor=true.',
                );
            }
            $createdShared[] = 'vendor (moved from legacy)';
        } else {
            throw new RuntimeException(
                'shared/vendor/autoload.php missing — run unpack-vendor (workflow: force_vendor=true).',
            );
        }
    }

    if (!function_exists('symlink')) {
        throw new RuntimeException('PHP symlink() is disabled on this host — release layout cannot work.');
    }

    @set_time_limit(600);

    // Remove placeholder dirs uploaded by CI before linking / materializing vendor.
    foreach (['var/data', 'var/log', 'public/uploads', 'vendor'] as $rel) {
        $path = $releasePath.'/'.$rel;
        if (is_link($path) || is_file($path)) {
            if (!@unlink($path) && (is_link($path) || is_file($path))) {
                throw new RuntimeException('Cannot remove '.$rel.' before activate.');
            }
            continue;
        }
        if (is_dir($path)) {
            ops_remove_path($path);
        }
    }
    $envLink = $releasePath.'/.env.local';
    if (is_file($envLink) && !is_link($envLink)) {
        @unlink($envLink);
    }

    foreach ($links as $rel => $target) {
        ops_force_symlink($target, $releasePath.'/'.$rel);
    }

    ops_materialize_vendor($shared.'/vendor', $releasePath.'/vendor');

    ops_switch_current($deployRoot, $releasePath);

    $pruned = ops_prune_releases($releasesDir, $releaseId, $keep);

    if (\function_exists('opcache_reset')) {
        @opcache_reset();
    }

    ops_json_exit([
        'ok' => true,
        'release' => $releaseId,
        'current' => 'releases/'.$releaseId,
        'deploy_root' => $deployRoot,
        'shared_created' => $createdShared,
        'pruned' => $pruned,
        'vendor' => 'materialized',
        'docroot_hint' => $deployRoot.'/current/public',
    ]);
} catch (Throwable $e) {
    ops_json_exit([
        'ok' => false,
        'error' => 'activate_failed',
        'message' => $e->getMessage(),
    ], 500);
}

/**
 * @return list<string> created relative paths under shared/
 */
function ops_ensure_shared_layout(string $shared, string $deployRoot, bool $bootstrapFromLegacy): array
{
    $created = [];
    $dirs = [
        'var/data/backups',
        'var/log',
        'var/tmp',
        'public/uploads/locations',
        'vendor',
    ];

    if (!ops_mkdirp($shared)) {
        throw new RuntimeException('Cannot create shared/.');
    }

    foreach ($dirs as $rel) {
        $path = $shared.'/'.$rel;
        if (!is_dir($path)) {
            if (!ops_mkdirp($path)) {
                throw new RuntimeException('Cannot create shared/'.$rel);
            }
            $created[] = $rel;
        }
    }

    if ($bootstrapFromLegacy) {
        $legacyEnv = $deployRoot.'/.env.local';
        $sharedEnv = $shared.'/.env.local';
        if (!is_file($sharedEnv) && is_file($legacyEnv)) {
            if (!@copy($legacyEnv, $sharedEnv)) {
                throw new RuntimeException('Cannot copy legacy .env.local into shared/.');
            }
            $created[] = '.env.local (from legacy)';
        }

        $legacyData = $deployRoot.'/var/data';
        $sharedData = $shared.'/var/data';
        if (is_dir($legacyData) && !is_link($legacyData)) {
            // Prefer moving DB into shared if shared has no app.db yet.
            if (!is_file($sharedData.'/app.db') && is_file($legacyData.'/app.db')) {
                if (!@rename($legacyData.'/app.db', $sharedData.'/app.db')) {
                    if (!@copy($legacyData.'/app.db', $sharedData.'/app.db')) {
                        throw new RuntimeException('Cannot move/copy var/data/app.db into shared.');
                    }
                }
                $created[] = 'var/data/app.db (from legacy)';
            }
            if (is_dir($legacyData.'/backups')) {
                foreach (glob($legacyData.'/backups/*') ?: [] as $file) {
                    $dest = $sharedData.'/backups/'.basename($file);
                    if (!is_file($dest)) {
                        @rename($file, $dest) || @copy($file, $dest);
                    }
                }
            }
        }

        $legacyUploads = $deployRoot.'/public/uploads';
        $sharedUploads = $shared.'/public/uploads';
        if (is_dir($legacyUploads.'/locations') && !is_link($legacyUploads)) {
            $destLoc = $sharedUploads.'/locations';
            ops_mkdirp($destLoc);
            foreach (glob($legacyUploads.'/locations/*') ?: [] as $file) {
                if (!is_file($file)) {
                    continue;
                }
                $dest = $destLoc.'/'.basename($file);
                if (!is_file($dest)) {
                    @rename($file, $dest) || @copy($file, $dest);
                }
            }
            $created[] = 'public/uploads/locations (from legacy)';
        }

        // Seed shared/vendor from legacy vendor if needed.
        if (!is_file($shared.'/vendor/autoload.php') && is_file($deployRoot.'/vendor/autoload.php')) {
            // Too large to copy synchronously often — instruct caller to unpack instead.
            // Soft hint only; activate will fail on missing vendor with clear error.
        }
    }

    return $created;
}

/**
 * @return list<string> pruned release ids
 */
function ops_prune_releases(string $releasesDir, string $keepId, int $keep): array
{
    if (!is_dir($releasesDir)) {
        return [];
    }

    $entries = [];
    foreach (scandir($releasesDir) ?: [] as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        $path = $releasesDir.'/'.$name;
        if (!is_dir($path) && !is_link($path)) {
            continue;
        }
        $entries[] = [
            'id' => $name,
            'mtime' => (int) @filemtime($path),
        ];
    }

    usort($entries, static fn (array $a, array $b): int => $b['mtime'] <=> $a['mtime']);
    $ordered = array_column($entries, 'id');

    $keepIds = array_slice($ordered, 0, $keep);
    if (!\in_array($keepId, $keepIds, true)) {
        $keepIds[] = $keepId;
    }

    $pruned = [];
    foreach ($ordered as $id) {
        if (\in_array($id, $keepIds, true)) {
            continue;
        }
        ops_remove_path($releasesDir.'/'.$id);
        $pruned[] = $id;
    }

    return $pruned;
}
