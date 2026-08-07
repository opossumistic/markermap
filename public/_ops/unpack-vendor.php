<?php

/**
 * Unpack vendor.zip into shared/vendor (release-aware deploy).
 *
 * POST /_ops/unpack-vendor.php
 * Header: X-Migrate-Token: <MIGRATE_TOKEN>
 *
 * Expects: {deploy}/shared/var/tmp/vendor-deploy.zip
 * Archive contents = files of vendor/ (autoload.php at zip root).
 *
 * Legacy fallback: {project}/var/tmp/vendor-deploy.zip → {project}/vendor
 * when shared/ does not exist yet.
 */

declare(strict_types=1);

@set_time_limit(600);
@ini_set('memory_limit', '512M');

header('Content-Type: application/json; charset=UTF-8');
header('X-Robots-Tag: noindex');

require __DIR__.'/_helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ops_json_exit(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

if (!class_exists(ZipArchive::class)) {
    ops_json_exit(['ok' => false, 'error' => 'zip_extension_missing'], 503);
}

ops_require_migrate_token();

$deployRoot = ops_deploy_root();
$shared = $deployRoot.'/shared';
$useShared = is_dir($shared) || ops_mkdirp($shared);

if ($useShared) {
    ops_mkdirp($shared.'/var/tmp');
    ops_mkdirp($shared.'/vendor');
    $zipPath = $shared.'/var/tmp/vendor-deploy.zip';
    $staging = $shared.'/var/tmp/vendor_staging';
    $backup = $shared.'/var/tmp/vendor_previous';
    $vendor = $shared.'/vendor';
    $mode = 'shared';
} else {
    $projectDir = ops_project_dir();
    $zipPath = $projectDir.'/var/tmp/vendor-deploy.zip';
    $staging = $projectDir.'/var/tmp/vendor_staging';
    $backup = $projectDir.'/var/tmp/vendor_previous';
    $vendor = $projectDir.'/vendor';
    $mode = 'legacy';
}

if (!is_file($zipPath)) {
    ops_json_exit([
        'ok' => false,
        'error' => 'archive_missing',
        'path' => $mode === 'shared' ? 'shared/var/tmp/vendor-deploy.zip' : 'var/tmp/vendor-deploy.zip',
        'mode' => $mode,
    ], 400);
}

try {
    ops_remove_path($staging);
    if (!mkdir($staging, 0775, true) && !is_dir($staging)) {
        throw new RuntimeException('Cannot create staging directory.');
    }

    $zip = new ZipArchive();
    $opened = $zip->open($zipPath);
    if ($opened !== true) {
        throw new RuntimeException('Cannot open zip (code '.$opened.').');
    }
    if (!$zip->extractTo($staging)) {
        $zip->close();
        throw new RuntimeException('Zip extract failed.');
    }
    $zip->close();

    if (!is_file($staging.'/autoload.php')) {
        throw new RuntimeException('Invalid archive: vendor/autoload.php missing at zip root.');
    }

    ops_remove_path($backup);
    if (is_dir($vendor) || is_link($vendor)) {
        // shared/vendor is a real dir we replace; never symlink the shared vendor root away oddly.
        if (is_link($vendor)) {
            throw new RuntimeException('vendor path is a symlink — refuse to replace: '.$vendor);
        }
        if (!rename($vendor, $backup)) {
            throw new RuntimeException('Cannot move current vendor aside.');
        }
    }

    if (!rename($staging, $vendor)) {
        if (is_dir($backup)) {
            @rename($backup, $vendor);
        }
        throw new RuntimeException('Cannot move staging into vendor/.');
    }

    ops_remove_path($backup);
    @unlink($zipPath);

    if (\function_exists('opcache_reset')) {
        @opcache_reset();
    }

    ops_json_exit([
        'ok' => true,
        'mode' => $mode,
        'vendor' => 'replaced',
        'autoload' => is_file($vendor.'/autoload.php'),
    ]);
} catch (Throwable $e) {
    ops_json_exit([
        'ok' => false,
        'error' => 'unpack_failed',
        'message' => $e->getMessage(),
        'mode' => $mode,
    ], 500);
}
