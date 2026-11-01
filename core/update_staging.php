<?php
/**
 * Deferred CMS update file swaps (Windows — cannot overwrite PHP scripts while in use).
 */

declare(strict_types=1);

function birkly_update_staging_suffix(): string
{
    return '.birkly-staged';
}

/**
 * Files that cannot be replaced during the HTTP request that runs Hub Install.
 *
 * @return list<string>
 */
function birkly_update_deferred_self_replace_paths(): array
{
    return [
        'api/updates.php',
        'api/session.php',
        'core/version.php',
    ];
}

function birkly_update_staging_relative_from_paths(string $stagedPath, string $projectRoot): ?string
{
    $stagedPath = str_replace('\\', '/', $stagedPath);
    $root = rtrim(str_replace('\\', '/', $projectRoot), '/');
    $suffix = birkly_update_staging_suffix();

    if (!str_ends_with($stagedPath, $suffix)) {
        return null;
    }

    $offloadRoot = str_replace('\\', '/', birkly_update_offload_staging_root($projectRoot));
    if (str_starts_with($stagedPath, $offloadRoot . '/')) {
        $relative = substr($stagedPath, strlen($offloadRoot) + 1);

        return substr($relative, 0, -strlen($suffix));
    }

    if (str_starts_with($stagedPath, $root . '/')) {
        $relative = substr($stagedPath, strlen($root) + 1);

        return substr($relative, 0, -strlen($suffix));
    }

    if (str_ends_with($stagedPath, $suffix)) {
        return substr(basename($stagedPath), 0, -strlen($suffix));
    }

    return null;
}

/**
 * @return list<string>
 */
function birkly_list_pending_staged_relative_paths(?string $projectRoot = null): array
{
    if (!function_exists('birkly_project_root')) {
        require_once __DIR__ . '/birkly_paths.php';
    }

    $root = rtrim($projectRoot ?? birkly_project_root(), '/\\');
    $paths = [];
    $suffix = birkly_update_staging_suffix();

    foreach (birkly_update_staging_scan_roots($root) as $scan) {
        if (is_file($scan) && str_ends_with($scan, $suffix)) {
            $relative = birkly_update_staging_relative_from_paths($scan, $root);
            if ($relative !== null) {
                $paths[] = $relative;
            }
            continue;
        }
        if (!is_dir($scan)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($scan, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), $suffix)) {
                continue;
            }
            $relative = birkly_update_staging_relative_from_paths($file->getPathname(), $root);
            if ($relative !== null) {
                $paths[] = $relative;
            }
        }
    }

    return array_values(array_unique($paths));
}

function birkly_count_blocking_pending_staged_update_files(?string $projectRoot = null): int
{
    $deferred = birkly_update_deferred_self_replace_paths();
    $count = 0;
    foreach (birkly_list_pending_staged_relative_paths($projectRoot) as $relative) {
        if (!in_array($relative, $deferred, true)) {
            $count++;
        }
    }

    return $count;
}

/**
 * Remove staged update artifacts after a failed install (prevent silent partial apply on reload).
 */
function birkly_purge_update_staging(?string $projectRoot = null): void
{
    if (!function_exists('birkly_project_root')) {
        require_once __DIR__ . '/birkly_paths.php';
    }

    $root = rtrim($projectRoot ?? birkly_project_root(), '/\\');
    $suffix = birkly_update_staging_suffix();

    foreach (birkly_list_pending_staged_relative_paths($root) as $relative) {
        $target = $root . '/' . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $coLocated = $target . $suffix;
        if (is_file($coLocated)) {
            @unlink($coLocated);
        }
        $offload = birkly_update_offload_staging_path($relative, $root);
        if (is_file($offload)) {
            @unlink($offload);
        }
    }

    $offloadRoot = birkly_update_offload_staging_root($root);
    if (is_dir($offloadRoot)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($offloadRoot, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
    }
}

function birkly_update_offload_staging_root(?string $projectRoot = null): string
{
    if (!function_exists('birkly_project_root')) {
        require_once __DIR__ . '/birkly_paths.php';
    }

    $root = rtrim($projectRoot ?? birkly_project_root(), '/\\');

    return $root . '/data/cms_update_staging';
}

function birkly_update_offload_staging_path(string $relativePath, ?string $projectRoot = null): string
{
    $relativePath = str_replace('\\', '/', $relativePath);

    return birkly_update_offload_staging_root($projectRoot) . '/' . $relativePath . birkly_update_staging_suffix();
}

/**
 * @return list<string>
 */
function birkly_update_staging_scan_roots(?string $projectRoot = null): array
{
    if (!function_exists('birkly_project_root')) {
        require_once __DIR__ . '/birkly_paths.php';
    }

    $root = rtrim($projectRoot ?? birkly_project_root(), '/\\');

    return [
        $root . '/data/cms_update_staging',
        $root . '/api',
        $root . '/core',
        $root . '/admin',
        $root . '/fieldtypes',
        $root . '/index.php',
    ];
}

function birkly_apply_staged_update_files(?string $projectRoot = null): void
{
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;

    if (!function_exists('birkly_project_root')) {
        require_once __DIR__ . '/birkly_paths.php';
    }

    $root = rtrim($projectRoot ?? birkly_project_root(), '/\\');
    $offloadRoot = birkly_update_offload_staging_root($root);

    foreach (birkly_update_staging_scan_roots($root) as $scan) {
        if (is_file($scan) && str_ends_with($scan, birkly_update_staging_suffix())) {
            birkly_apply_one_staged_file($scan);
            continue;
        }
        if (!is_dir($scan)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($scan, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), birkly_update_staging_suffix())) {
                continue;
            }

            $stagedPath = $file->getPathname();
            if (str_starts_with(str_replace('\\', '/', $stagedPath), str_replace('\\', '/', $offloadRoot . '/'))) {
                $relative = ltrim(str_replace('\\', '/', substr($stagedPath, strlen($offloadRoot))), '/');
                $relative = substr($relative, 0, -strlen(birkly_update_staging_suffix()));
                $target = $root . '/' . str_replace('/', DIRECTORY_SEPARATOR, $relative);
                birkly_promote_staged_file($stagedPath, $target);
                continue;
            }

            birkly_apply_one_staged_file($stagedPath);
        }
    }
}

function birkly_apply_one_staged_file(string $stagedPath): void
{
    $suffix = birkly_update_staging_suffix();
    if (!str_ends_with($stagedPath, $suffix) || !is_file($stagedPath)) {
        return;
    }

    $target = substr($stagedPath, 0, -strlen($suffix));
    if ($target === '') {
        return;
    }

    birkly_promote_staged_file($stagedPath, $target);
}

function birkly_promote_staged_file(string $stagingPath, string $target): bool
{
    if (!is_file($stagingPath)) {
        return false;
    }

    $targetDir = dirname($target);
    if (!is_dir($targetDir) && !@mkdir($targetDir, 0755, true)) {
        return false;
    }

    if (@rename($stagingPath, $target)) {
        return true;
    }

    if (@copy($stagingPath, $target)) {
        @unlink($stagingPath);
        return true;
    }

    if (is_file($target)) {
        @chmod($target, 0644);
        if (@unlink($target)) {
            if (@rename($stagingPath, $target)) {
                return true;
            }
            if (@copy($stagingPath, $target)) {
                @unlink($stagingPath);
                return true;
            }
        }
    }

    return is_file($target) && !is_file($stagingPath);
}

/**
 * Count remaining staged update files (install verification).
 */
function birkly_count_pending_staged_update_files(?string $projectRoot = null): int
{
    if (!function_exists('birkly_project_root')) {
        require_once __DIR__ . '/birkly_paths.php';
    }

    $root = rtrim($projectRoot ?? birkly_project_root(), '/\\');
    $count = 0;
    $suffix = birkly_update_staging_suffix();

    foreach (birkly_update_staging_scan_roots($root) as $scan) {
        if (is_file($scan) && str_ends_with($scan, $suffix)) {
            $count++;
            continue;
        }
        if (!is_dir($scan)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($scan, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), $suffix)) {
                $count++;
            }
        }
    }

    return $count;
}
