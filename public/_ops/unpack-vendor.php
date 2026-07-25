<?php

/**
 * Shared-hosting ops: unpack vendor.zip without booting Symfony
 * (vendor may be missing or mid-replace — chicken/egg).
 *
 * POST /_ops/unpack-vendor.php
 * Header: X-Migrate-Token: <MIGRATE_TOKEN from server .env.local>
 *
 * Expects archive at: {project}/var/tmp/vendor-deploy.zip
 * Archive contents = files of vendor/ (autoload.php at zip root).
 */

declare(strict_types=1);

@set_time_limit(600);
@ini_set('memory_limit', '512M');

header('Content-Type: application/json; charset=UTF-8');
header('X-Robots-Tag: noindex');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

if (!class_exists(ZipArchive::class)) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'zip_extension_missing']);
    exit;
}

$projectDir = dirname(__DIR__, 2);
$tokenExpected = ops_read_env_value($projectDir, 'MIGRATE_TOKEN');
$tokenProvided = $_SERVER['HTTP_X_MIGRATE_TOKEN']
    ?? (is_string($_POST['token'] ?? null) ? $_POST['token'] : '');

if ($tokenExpected === '' || $tokenExpected === 'change-me') {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'MIGRATE_TOKEN not configured']);
    exit;
}

if ($tokenProvided === '' || !hash_equals($tokenExpected, $tokenProvided)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

$zipPath = $projectDir.'/var/tmp/vendor-deploy.zip';
$staging = $projectDir.'/var/tmp/vendor_staging';
$backup = $projectDir.'/var/tmp/vendor_previous';
$vendor = $projectDir.'/vendor';

if (!is_file($zipPath)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'archive_missing', 'path' => 'var/tmp/vendor-deploy.zip']);
    exit;
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

    echo json_encode([
        'ok' => true,
        'vendor' => 'replaced',
        'autoload' => is_file($vendor.'/autoload.php'),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'unpack_failed',
        'message' => $e->getMessage(),
    ]);
}

/**
 * Minimal .env reader (no Symfony). Last matching key wins across .env then .env.local.
 */
function ops_read_env_value(string $projectDir, string $key): string
{
    $value = '';
    foreach (['.env', '.env.local'] as $file) {
        $path = $projectDir.'/'.$file;
        if (!is_file($path) || !is_readable($path)) {
            continue;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            continue;
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!str_starts_with($line, $key.'=')) {
                continue;
            }
            $raw = substr($line, strlen($key) + 1);
            $value = trim($raw);
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }
        }
    }

    return $value;
}

function ops_remove_path(string $path): void
{
    if (is_link($path) || is_file($path)) {
        @unlink($path);

        return;
    }
    if (!is_dir($path)) {
        return;
    }

    $items = scandir($path);
    if ($items === false) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        ops_remove_path($path.\DIRECTORY_SEPARATOR.$item);
    }
    @rmdir($path);
}
