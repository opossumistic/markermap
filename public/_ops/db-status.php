<?php

/**
 * Quick DB health for shared-hosting recovery (no FTP symlink guessing).
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
$projectDir = ops_project_dir();

/**
 * @return array<string, mixed>
 */
function ops_path_probe(string $path): array
{
    $info = [
        'path' => $path,
        'exists' => file_exists($path),
        'is_link' => is_link($path),
        'is_dir' => is_dir($path),
        'is_file' => is_file($path),
        'realpath' => null,
        'link_target' => null,
        'bytes' => null,
        'inode' => null,
    ];

    if ($info['is_link']) {
        $info['link_target'] = readlink($path) ?: null;
    }

    if ($info['exists']) {
        $real = realpath($path);
        $info['realpath'] = $real !== false ? $real : null;
        if (is_file($path) || (is_link($path) && is_file($path))) {
            $info['bytes'] = @filesize($path);
            $info['inode'] = @fileinode($path) ?: null;
        } elseif (is_dir($path)) {
            $info['inode'] = @fileinode($path) ?: null;
        }
    }

    return $info;
}

/**
 * @return array<string, mixed>
 */
function ops_db_probe(string $path): array
{
    $info = ops_path_probe($path);
    $info['readable'] = is_file($path) && is_readable($path);

    if (!$info['readable']) {
        return $info;
    }

    try {
        $pdo = new PDO('sqlite:'.$path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
        $info['tables'] = $tables;

        if (\in_array('locations', $tables, true)) {
            $info['locations'] = (int) $pdo->query('SELECT COUNT(*) FROM locations')->fetchColumn();
            try {
                $info['locations_by_status'] = $pdo
                    ->query('SELECT status, COUNT(*) AS c FROM locations GROUP BY status')
                    ->fetchAll(PDO::FETCH_KEY_PAIR);
            } catch (Throwable) {
                // ignore
            }
        }

        if (\in_array('maps', $tables, true)) {
            $info['maps'] = $pdo->query('SELECT id, slug, status FROM maps')->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $e) {
        $info['error'] = $e->getMessage();
    }

    return $info;
}

$candidates = [
    'shared' => $deployRoot.'/shared/var/data/app.db',
    'legacy' => $deployRoot.'/var/data/app.db',
    'current' => $deployRoot.'/current/var/data/app.db',
    'project' => $projectDir.'/var/data/app.db',
];

$databases = [];
foreach ($candidates as $label => $path) {
    $databases[$label] = ops_db_probe($path);
}

$sharedReal = $databases['shared']['realpath'] ?? null;
$currentReal = $databases['current']['realpath'] ?? null;
$projectReal = $databases['project']['realpath'] ?? null;

ops_json_exit([
    'ok' => true,
    'deploy_root' => $deployRoot,
    'project_dir' => $projectDir,
    'database_url_env' => ops_read_env_value('DATABASE_URL') ?: null,
    'paths' => [
        'current' => ops_path_probe($deployRoot.'/current'),
        'current_var' => ops_path_probe($deployRoot.'/current/var'),
        'current_var_data' => ops_path_probe($deployRoot.'/current/var/data'),
        'shared_var_data' => ops_path_probe($deployRoot.'/shared/var/data'),
        'project_var_data' => ops_path_probe($projectDir.'/var/data'),
    ],
    'same_file' => [
        'shared_vs_current' => $sharedReal !== null && $sharedReal === $currentReal,
        'shared_vs_project' => $sharedReal !== null && $sharedReal === $projectReal,
        'current_vs_project' => $currentReal !== null && $currentReal === $projectReal,
    ],
    'databases' => $databases,
]);
