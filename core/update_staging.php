<?php
/**
 * Deferred CMS update file swaps (Windows — cannot overwrite PHP scripts while in use).
 */

declare(strict_types=1);

function birkly_apply_staged_update_files(): void
{
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;

    if (!function_exists('birkly_project_root')) {
        require_once __DIR__ . '/birkly_paths.php';
    }

    $root = rtrim(birkly_project_root(), '/\\');
    $scanRoots = ['api', 'core', 'admin', 'fieldtypes', 'index.php'];
    foreach ($scanRoots as $top) {
        $path = $root . DIRECTORY_SEPARATOR . $top;
        if (is_file($path) && str_ends_with($path, '.birkly-staged')) {
            birkly_apply_one_staged_file($path);
            continue;
        }
        if (!is_dir($path)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), '.birkly-staged')) {
                continue;
            }
            birkly_apply_one_staged_file($file->getPathname());
        }
    }
}

function birkly_apply_one_staged_file(string $stagedPath): void
{
    $suffix = '.birkly-staged';
    if (!str_ends_with($stagedPath, $suffix) || !is_file($stagedPath)) {
        return;
    }

    $target = substr($stagedPath, 0, -strlen($suffix));
    if ($target === '') {
        return;
    }

    if (@rename($stagedPath, $target)) {
        return;
    }

    if (@copy($stagedPath, $target)) {
        @unlink($stagedPath);
    }
}
