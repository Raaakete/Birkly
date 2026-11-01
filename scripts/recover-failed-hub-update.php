#!/usr/bin/env php
<?php
/**
 * Recover from a failed Hub CMS update (CLI only).
 *
 * Typical use when admin/public return HTTP 500 after "Installation failed":
 *   php scripts/recover-failed-hub-update.php --purge-staging
 *   php scripts/recover-failed-hub-update.php --list-backups
 *   php scripts/recover-failed-hub-update.php --restore-latest
 *
 * Options:
 *   --list-backups     List pre-update backup folders
 *   --purge-staging    Remove *.birkly-staged and data/cms_update_staging
 *   --restore-latest   Restore newest pre-update-* backup over program tree
 *   --restore=PATH     Restore a specific backup directory
 *   --dry-run          Print actions without writing
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/cli_only.php';
birkly_require_cli();

$root = dirname(__DIR__);
$dryRun = in_array('--dry-run', $argv, true);
$list = in_array('--list-backups', $argv, true);
$purge = in_array('--purge-staging', $argv, true);
$restoreLatest = in_array('--restore-latest', $argv, true);
$restorePath = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--restore=')) {
        $restorePath = substr($arg, strlen('--restore='));
    }
}

if (!$list && !$purge && !$restoreLatest && $restorePath === null) {
    fwrite(STDERR, "Usage: php scripts/recover-failed-hub-update.php [--list-backups] [--purge-staging] [--restore-latest|--restore=PATH] [--dry-run]\n");
    exit(1);
}

/** @return list<string> */
function recover_hub_backup_bases(string $cmsRoot): array
{
    return [
        $cmsRoot . '/backups',
        $cmsRoot . '/data/pre_update_backups',
    ];
}

/** @return list<string> */
function recover_hub_find_backups(string $cmsRoot): array
{
    $found = [];
    foreach (recover_hub_backup_bases($cmsRoot) as $base) {
        if (!is_dir($base)) {
            continue;
        }
        foreach (scandir($base) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (!str_starts_with($entry, 'pre-update-')) {
                continue;
            }
            $path = $base . '/' . $entry;
            if (is_dir($path)) {
                $found[] = $path;
            }
        }
    }
    rsort($found);

    return $found;
}

function recover_hub_purge_staging(string $cmsRoot, bool $dryRun): void
{
    $updateStaging = $cmsRoot . '/core/update_staging.php';
    if (is_file($updateStaging)) {
        require_once $cmsRoot . '/core/birkly_paths.php';
        require_once $updateStaging;
        if (function_exists('birkly_purge_update_staging')) {
            if ($dryRun) {
                fwrite(STDOUT, "[dry-run] would call birkly_purge_update_staging()\n");
            } else {
                birkly_purge_update_staging($cmsRoot);
                fwrite(STDOUT, "Purged staging via birkly_purge_update_staging()\n");
            }
            return;
        }
    }

    $suffix = '.birkly-staged';
    $scanRoots = [
        $cmsRoot . '/data/cms_update_staging',
        $cmsRoot . '/api',
        $cmsRoot . '/core',
        $cmsRoot . '/admin',
        $cmsRoot . '/fieldtypes',
    ];
    $removed = 0;
    foreach ($scanRoots as $scan) {
        if (is_file($scan) && str_ends_with($scan, $suffix)) {
            if ($dryRun) {
                fwrite(STDOUT, "[dry-run] would unlink $scan\n");
            } elseif (@unlink($scan)) {
                $removed++;
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
            $path = $file->getPathname();
            if ($dryRun) {
                fwrite(STDOUT, "[dry-run] would unlink $path\n");
            } elseif (@unlink($path)) {
                $removed++;
            }
        }
    }
    if (!$dryRun) {
        fwrite(STDOUT, "Removed $removed staged artifact(s)\n");
    }
}

function recover_hub_merge_tree(string $sourceDir, string $targetDir, bool $dryRun): bool
{
    if (!is_dir($sourceDir)) {
        fwrite(STDERR, "Backup not found: $sourceDir\n");
        return false;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $relative = str_replace('\\', '/', $iterator->getSubPathName());
        $targetPath = $targetDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

        if ($item->isDir()) {
            if ($dryRun) {
                continue;
            }
            if (!is_dir($targetPath) && !@mkdir($targetPath, 0755, true)) {
                fwrite(STDERR, "Failed to create directory: $targetPath\n");
                return false;
            }
            continue;
        }

        $parent = dirname($targetPath);
        if (!is_dir($parent) && !@mkdir($parent, 0755, true)) {
            fwrite(STDERR, "Failed to create directory: $parent\n");
            return false;
        }

        if ($dryRun) {
            fwrite(STDOUT, "[dry-run] would copy $relative\n");
            continue;
        }

        if (!@copy($item->getPathname(), $targetPath)) {
            fwrite(STDERR, "Failed to copy $relative\n");
            return false;
        }
    }

    return true;
}

function recover_hub_restore(string $backupDir, string $cmsRoot, bool $dryRun): bool
{
    $components = ['admin', 'api', 'core', 'fieldtypes', 'plugins', 'index.php', 'VERSION'];
    foreach ($components as $component) {
        $source = $backupDir . '/' . $component;
        if (!is_dir($source) && !is_file($source)) {
            fwrite(STDERR, "Backup missing component: $component\n");
            return false;
        }
        $target = $cmsRoot . '/' . $component;
        if (is_file($source)) {
            if ($dryRun) {
                fwrite(STDOUT, "[dry-run] would restore file $component\n");
                continue;
            }
            if (!@copy($source, $target)) {
                fwrite(STDERR, "Failed to restore $component\n");
                return false;
            }
            continue;
        }
        if (!recover_hub_merge_tree($source, $target, $dryRun)) {
            return false;
        }
    }

    fwrite(STDOUT, ($dryRun ? '[dry-run] would restore' : 'Restored') . " program tree from $backupDir\n");
    return true;
}

if ($list) {
    $backups = recover_hub_find_backups($root);
    if ($backups === []) {
        fwrite(STDOUT, "No pre-update-* backups found under backups/ or data/pre_update_backups/\n");
        exit(0);
    }
    foreach ($backups as $path) {
        fwrite(STDOUT, $path . "\n");
    }
    exit(0);
}

if ($purge) {
    recover_hub_purge_staging($root, $dryRun);
}

if ($restoreLatest || $restorePath !== null) {
    if ($restorePath === null) {
        $backups = recover_hub_find_backups($root);
        if ($backups === []) {
            fwrite(STDERR, "No backup to restore. Check backups/pre-update-* on the server.\n");
            exit(1);
        }
        $restorePath = $backups[0];
    } elseif (!is_dir($restorePath)) {
        fwrite(STDERR, "Backup path not found: $restorePath\n");
        exit(1);
    }

    if (!$dryRun) {
        recover_hub_purge_staging($root, false);
    }

    if (!recover_hub_restore($restorePath, $root, $dryRun)) {
        exit(1);
    }
}

exit(0);
