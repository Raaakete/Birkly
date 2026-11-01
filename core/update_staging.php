<?php
/**
 * Deferred CMS update file swaps (Windows — cannot overwrite PHP scripts while in use).
 */

declare(strict_types=1);

function birkly_update_staging_suffix(): string
{
    return '.birkly-staged';
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
