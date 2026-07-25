<?php

/**
 * Create writable runtime dirs without booting Symfony/Doctrine.
 * Fixes SQLSTATE[HY000][14] when var/data is missing after deploy excludes.
 *
 * POST /_ops/ensure-runtime.php
 * Header: X-Migrate-Token: <same as MIGRATE_TOKEN>
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('X-Robots-Tag: noindex');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
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

$dirs = [
    'var',
    'var/cache',
    'var/log',
    'var/data',
    'var/data/backups',
    'var/tmp',
    'public/uploads',
    'public/uploads/locations',
];

$created = [];
$existing = [];
$errors = [];

foreach ($dirs as $relative) {
    $path = $projectDir.'/'.$relative;
    if (is_dir($path)) {
        $existing[] = $relative;
    } else {
        if (@mkdir($path, 0775, true) || is_dir($path)) {
            $created[] = $relative;
        } else {
            $errors[] = ['path' => $relative, 'error' => 'mkdir_failed'];
        }
    }

    if (is_dir($path) && !is_writable($path)) {
        $errors[] = ['path' => $relative, 'error' => 'not_writable'];
    }
}

$dbRelative = 'var/data/app.db';
$dbPath = $projectDir.'/'.$dbRelative;
$dbWritableDir = is_dir($projectDir.'/var/data') && is_writable($projectDir.'/var/data');

$ok = $errors === [] && $dbWritableDir;

http_response_code($ok ? 200 : 500);
echo json_encode([
    'ok' => $ok,
    'created' => $created,
    'existing' => $existing,
    'errors' => $errors,
    'sqlite' => [
        'path' => $dbRelative,
        'exists' => is_file($dbPath),
        'dir_writable' => $dbWritableDir,
        'hint' => $dbWritableDir
            ? 'Directory OK — run /_ops/migrate to create schema if DB missing.'
            : 'PHP cannot write var/data — fix permissions (chmod 775) via FTP/File-Manager.',
    ],
], JSON_UNESCAPED_SLASHES);

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
