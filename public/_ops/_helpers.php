<?php

/**
 * Shared helpers for public/_ops/*.php (no Symfony).
 * Included by ops scripts — not web-callable alone.
 */

declare(strict_types=1);

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Not found';
    exit;
}

/**
 * Deploy root = directory that contains shared/, releases/, current
 * (or legacy in-place app root before cutover).
 *
 * Called from …/public/_ops/*.php → two levels up is the Symfony project dir
 * (a release, current symlink target, or legacy root).
 */
function ops_project_dir(): string
{
    return dirname(__DIR__, 2);
}

function ops_deploy_root(): string
{
    $project = ops_project_dir();
    $parent = dirname($project);

    // …/releases/{id} (typical after realpath through current)
    if (basename($parent) === 'releases') {
        return dirname($parent);
    }

    // …/current (if cwd path not realpath'd)
    if (basename($project) === 'current' || is_dir($parent.'/releases') || is_dir($parent.'/shared')) {
        return $parent;
    }

    // Legacy in-place: project root == deploy root.
    return $project;
}

function ops_shared_dir(): string
{
    return ops_deploy_root().'/shared';
}

/**
 * @return list<string>
 */
function ops_env_search_dirs(): array
{
    $dirs = [];
    $shared = ops_shared_dir();
    if (is_dir($shared)) {
        $dirs[] = $shared;
    }

    $current = ops_deploy_root().'/current';
    if (is_dir($current) || is_link($current)) {
        $dirs[] = $current;
    }

    $dirs[] = ops_project_dir();

    return array_values(array_unique($dirs));
}

function ops_read_env_value(string $key): string
{
    $value = '';
    foreach (ops_env_search_dirs() as $dir) {
        foreach (['.env', '.env.local'] as $file) {
            $path = $dir.'/'.$file;
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
    }

    return $value;
}

function ops_require_migrate_token(): void
{
    $tokenExpected = ops_read_env_value('MIGRATE_TOKEN');
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
}

function ops_json_exit(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
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

/**
 * @return bool true when path is gone
 */
function ops_remove_tree(string $path): bool
{
    if (!file_exists($path) && !is_link($path)) {
        return true;
    }
    if (is_file($path) || is_link($path)) {
        return @unlink($path);
    }
    if (!is_dir($path)) {
        return false;
    }

    $items = scandir($path);
    if ($items === false) {
        return false;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        if (!ops_remove_tree($path.\DIRECTORY_SEPARATOR.$item)) {
            return false;
        }
    }

    return @rmdir($path);
}

function ops_mkdirp(string $path): bool
{
    return is_dir($path) || @mkdir($path, 0775, true) || is_dir($path);
}

/**
 * Mirror $src → $dest (dirs created, files hardlinked when possible, else copied).
 * Used to materialize shared/vendor into a release so Composer $baseDir is correct.
 */
function ops_mirror_tree(string $src, string $dest): void
{
    if (!is_dir($src)) {
        throw new RuntimeException('mirror source missing: '.$src);
    }

    if (!ops_mkdirp($dest)) {
        throw new RuntimeException('Cannot create mirror dest: '.$dest);
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );

    /** @var SplFileInfo $item */
    foreach ($iterator as $item) {
        $relative = substr($item->getPathname(), \strlen($src) + 1);
        if ($relative === false || $relative === '') {
            continue;
        }
        $target = $dest.\DIRECTORY_SEPARATOR.$relative;

        if ($item->isDir()) {
            if (!ops_mkdirp($target)) {
                throw new RuntimeException('Cannot create dir: '.$target);
            }
            continue;
        }

        if (is_link($target) || is_file($target)) {
            @unlink($target);
        }

        // Same-filesystem hardlink keeps disk cheap and preserves path-based Composer $baseDir.
        if (!@link($item->getPathname(), $target) && !@copy($item->getPathname(), $target)) {
            throw new RuntimeException('Cannot link/copy: '.$relative);
        }
    }
}

/**
 * Place a real vendor/ inside the release (not a symlink to shared/vendor).
 * Composer resolves $baseDir = dirname(vendor); a symlink into shared/ breaks App\Kernel.
 */
function ops_materialize_vendor(string $sharedVendor, string $releaseVendor): void
{
    if (!is_file($sharedVendor.'/autoload.php')) {
        throw new RuntimeException('shared vendor incomplete (autoload.php missing).');
    }

    if (is_link($releaseVendor) || is_file($releaseVendor)) {
        if (!@unlink($releaseVendor)) {
            throw new RuntimeException('Cannot remove vendor symlink/file: '.$releaseVendor);
        }
    } elseif (is_dir($releaseVendor)) {
        ops_remove_path($releaseVendor);
    }

    ops_mirror_tree($sharedVendor, $releaseVendor);

    if (!is_file($releaseVendor.'/autoload.php')) {
        throw new RuntimeException('vendor materialize failed (autoload.php missing in release).');
    }
}

/**
 * Replace $linkPath with a symlink to $target (absolute).
 */
function ops_force_symlink(string $target, string $linkPath): void
{
    if (!function_exists('symlink')) {
        throw new RuntimeException('symlink() disabled');
    }

    if (!file_exists($target) && !is_link($target)) {
        throw new RuntimeException('symlink target missing: '.$target);
    }

    if (is_link($linkPath) || is_file($linkPath)) {
        if (!@unlink($linkPath)) {
            throw new RuntimeException('Cannot remove existing path: '.$linkPath);
        }
    } elseif (is_dir($linkPath)) {
        ops_remove_path($linkPath);
    }

    if (!@symlink($target, $linkPath)) {
        $err = error_get_last();
        $detail = \is_array($err) ? ($err['message'] ?? '') : '';
        throw new RuntimeException('symlink failed: '.$linkPath.' → '.$target.($detail !== '' ? ' ('.$detail.')' : ''));
    }
}

/**
 * Atomic-ish switch of current → $releasePath (same filesystem).
 */
function ops_switch_current(string $deployRoot, string $releasePath): void
{
    $current = $deployRoot.'/current';
    $tmp = $deployRoot.'/current.new';

    if (is_link($tmp) || is_file($tmp)) {
        @unlink($tmp);
    } elseif (is_dir($tmp)) {
        throw new RuntimeException('current.new exists as directory — remove manually.');
    }

    if (!@symlink($releasePath, $tmp)) {
        throw new RuntimeException('Cannot create current.new symlink.');
    }

    // rename() replaces existing symlink atomically on Linux.
    if (!@rename($tmp, $current)) {
        @unlink($tmp);
        // Fallback: unlink + symlink (brief race).
        if (is_link($current) || is_file($current)) {
            @unlink($current);
        }
        if (!@symlink($releasePath, $current)) {
            throw new RuntimeException('Cannot switch current symlink.');
        }
    }
}

function ops_is_valid_release_id(string $id): bool
{
    return (bool) preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]{0,120}$/', $id);
}
