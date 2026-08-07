<?php

/**
 * Ensure shared (+ current release) runtime dirs are writable.
 *
 * POST /_ops/ensure-runtime.php
 * Header: X-Migrate-Token: <same as MIGRATE_TOKEN>
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('X-Robots-Tag: noindex');

require __DIR__.'/_helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ops_json_exit(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

ops_require_migrate_token();

$deployRoot = ops_deploy_root();
$shared = $deployRoot.'/shared';
$created = [];
$existing = [];
$errors = [];

$useShared = is_dir($shared) || is_dir($deployRoot.'/releases');

if ($useShared) {
    $sharedDirs = [
        'shared',
        'shared/var/data',
        'shared/var/data/backups',
        'shared/var/log',
        'shared/var/tmp',
        'shared/public/uploads',
        'shared/public/uploads/locations',
        'shared/vendor',
        'releases',
    ];
    foreach ($sharedDirs as $relative) {
        $path = $deployRoot.'/'.$relative;
        if (is_dir($path)) {
            $existing[] = $relative;
        } elseif (ops_mkdirp($path)) {
            $created[] = $relative;
        } else {
            $errors[] = ['path' => $relative, 'error' => 'mkdir_failed'];
        }
        if (is_dir($path) && !is_writable($path)) {
            $errors[] = ['path' => $relative, 'error' => 'not_writable'];
        }
    }

    $current = $deployRoot.'/current';
    if (is_dir($current) || is_link($current)) {
        foreach (['var/cache', 'var/tmp'] as $rel) {
            $path = $current.'/'.$rel;
            $label = 'current/'.$rel;
            if (is_dir($path)) {
                $existing[] = $label;
            } elseif (ops_mkdirp($path)) {
                $created[] = $label;
            } else {
                $errors[] = ['path' => $label, 'error' => 'mkdir_failed'];
            }
            if (is_dir($path) && !is_writable($path)) {
                $errors[] = ['path' => $label, 'error' => 'not_writable'];
            }
        }
    }

    $dbPath = $shared.'/var/data/app.db';
    $dbWritableDir = is_dir($shared.'/var/data') && is_writable($shared.'/var/data');
} else {
    // Legacy in-place project.
    $projectDir = ops_project_dir();
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
    foreach ($dirs as $relative) {
        $path = $projectDir.'/'.$relative;
        if (is_dir($path)) {
            $existing[] = $relative;
        } elseif (ops_mkdirp($path)) {
            $created[] = $relative;
        } else {
            $errors[] = ['path' => $relative, 'error' => 'mkdir_failed'];
        }
        if (is_dir($path) && !is_writable($path)) {
            $errors[] = ['path' => $relative, 'error' => 'not_writable'];
        }
    }
    $dbPath = $projectDir.'/var/data/app.db';
    $dbWritableDir = is_dir($projectDir.'/var/data') && is_writable($projectDir.'/var/data');
}

$ok = $errors === [] && $dbWritableDir;

ops_json_exit([
    'ok' => $ok,
    'mode' => $useShared ? 'shared' : 'legacy',
    'deploy_root' => $deployRoot,
    'created' => $created,
    'existing' => $existing,
    'errors' => $errors,
    'sqlite' => [
        'path' => $useShared ? 'shared/var/data/app.db' : 'var/data/app.db',
        'exists' => is_file($dbPath),
        'dir_writable' => $dbWritableDir,
        'hint' => $dbWritableDir
            ? 'Directory OK — run /_ops/migrate to create schema if DB missing.'
            : 'PHP cannot write data dir — fix permissions (chmod 775) via FTP/File-Manager.',
    ],
], $ok ? 200 : 500);
