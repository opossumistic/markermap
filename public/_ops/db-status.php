<?php

/**
 * Quick DB health for shared-hosting recovery.
 *
 * POST /_ops/db-status.php
 * Header: X-Migrate-Token
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
$candidates = [
    'shared' => $deployRoot.'/shared/var/data/app.db',
    'legacy' => $deployRoot.'/var/data/app.db',
    'current' => $deployRoot.'/current/var/data/app.db',
];

$out = [
    'ok' => true,
    'deploy_root' => $deployRoot,
    'databases' => [],
];

foreach ($candidates as $label => $path) {
    $info = [
        'path' => $path,
        'exists' => is_file($path),
        'bytes' => is_file($path) ? filesize($path) : null,
        'readable' => is_file($path) && is_readable($path),
    ];

    if ($info['exists'] && $info['readable']) {
        try {
            $pdo = new PDO('sqlite:'.$path, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
            $info['tables'] = $tables;
            if (\in_array('locations', $tables, true)) {
                $info['locations'] = (int) $pdo->query('SELECT COUNT(*) FROM locations')->fetchColumn();
                if (\in_array('status', $pdo->query('PRAGMA table_info(locations)')->fetchAll(PDO::FETCH_COLUMN, 1) ?: [], true)
                    || true) {
                    try {
                        $rows = $pdo->query('SELECT status, COUNT(*) AS c FROM locations GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
                        $info['locations_by_status'] = $rows;
                    } catch (Throwable) {
                        // ignore
                    }
                }
            }
            if (\in_array('maps', $tables, true)) {
                $info['maps'] = $pdo->query('SELECT id, slug, status FROM maps')->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Throwable $e) {
            $info['error'] = $e->getMessage();
        }
    }

    $out['databases'][$label] = $info;
}

ops_json_exit($out);
