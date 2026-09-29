<?php
/**
 * Backup zip create and restore.
 * Restore writes only under content, users, media, and settings.
 */

declare(strict_types=1);

/**
 * @return list<string>
 */
function birkly_backup_trees(): array
{
    return ['content', 'users', 'media', 'settings'];
}

/**
 * Relative path inside the CMS root, or null when the entry must not be extracted.
 */
function birkly_backup_entry_relative(string $name): ?string
{
    $name = str_replace('\\', '/', $name);
    if ($name === '' || str_contains($name, "\0")) {
        return null;
    }
    if (str_starts_with($name, '/') || preg_match('#^[A-Za-z]:#', $name) === 1) {
        return null;
    }
    while (str_starts_with($name, './')) {
        $name = substr($name, 2);
    }
    $name = ltrim($name, '/');
    $name = rtrim($name, '/');
    if ($name === '') {
        return null;
    }

    $parts = explode('/', $name);
    $clean = [];
    foreach ($parts as $part) {
        if ($part === '' || $part === '.' || $part === '..') {
            return null;
        }
        $clean[] = $part;
    }
    if (!in_array($clean[0], birkly_backup_trees(), true)) {
        return null;
    }

    return implode('/', $clean);
}

function birkly_backup_zip_entry_is_link(ZipArchive $zip, int $index): bool
{
    $opsys = 0;
    $attr = 0;
    if (!$zip->getExternalAttributesIndex($index, $opsys, $attr)) {
        return false;
    }
    $mode = ($attr >> 16) & 0xFFFF;

    return ($mode & 0xF000) === 0xA000;
}

function birkly_backup_path_has_link(string $cmsRoot, string $relative): bool
{
    $cursor = rtrim($cmsRoot, '/\\');
    foreach (explode('/', $relative) as $part) {
        $cursor .= '/' . $part;
        if (is_link($cursor)) {
            return true;
        }
    }

    return false;
}

/**
 * @return array{ok:bool,path?:string,error?:string}
 */
function birkly_backup_create_zip(string $cmsRoot, string $zipPath): array
{
    if (!class_exists('ZipArchive')) {
        return ['ok' => false, 'error' => 'ZipArchive extension is required'];
    }

    $cmsRoot = rtrim($cmsRoot, '/\\');
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return ['ok' => false, 'error' => 'Could not create backup file'];
    }

    try {
        foreach (birkly_backup_trees() as $tree) {
            $dir = $cmsRoot . '/' . $tree;
            if (!is_dir($dir) || is_link($dir)) {
                continue;
            }
            $rootReal = realpath($dir);
            if ($rootReal === false) {
                continue;
            }
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($files as $file) {
                if (!$file instanceof SplFileInfo || $file->isLink()) {
                    continue;
                }
                $filePath = $file->getRealPath();
                if ($filePath === false || !str_starts_with($filePath, $rootReal . DIRECTORY_SEPARATOR)) {
                    continue;
                }
                $relative = str_replace('\\', '/', substr($filePath, strlen($rootReal) + 1));
                $localPath = $tree . '/' . $relative;
                if ($file->isDir()) {
                    $zip->addEmptyDir($localPath);
                } else {
                    $zip->addFile($filePath, $localPath);
                }
            }
        }
    } catch (UnexpectedValueException $e) {
        $zip->close();
        @unlink($zipPath);

        return ['ok' => false, 'error' => 'Could not read backup directories'];
    }

    $zip->close();

    return ['ok' => true, 'path' => $zipPath];
}

/**
 * @return array{ok:bool,restored?:int,error?:string}
 */
function birkly_backup_restore_zip(string $zipPath, string $cmsRoot): array
{
    if (!class_exists('ZipArchive')) {
        return ['ok' => false, 'error' => 'ZipArchive extension is required'];
    }
    if (!is_file($zipPath)) {
        return ['ok' => false, 'error' => 'Invalid backup file'];
    }

    $cmsRoot = rtrim($cmsRoot, '/\\');
    $rootReal = realpath($cmsRoot);
    if ($rootReal === false || !is_dir($rootReal)) {
        return ['ok' => false, 'error' => 'Backup destination is not available'];
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        return ['ok' => false, 'error' => 'Invalid backup file'];
    }

    $entries = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        $name = $zip->getNameIndex($i);
        if (!is_array($stat) || !is_string($name)) {
            $zip->close();

            return ['ok' => false, 'error' => 'Invalid backup file'];
        }
        if (birkly_backup_zip_entry_is_link($zip, $i)) {
            $zip->close();

            return ['ok' => false, 'error' => 'Backup contains a link'];
        }
        $relative = birkly_backup_entry_relative($name);
        if ($relative === null) {
            $zip->close();

            return ['ok' => false, 'error' => 'Backup path is not allowed'];
        }
        $isDir = str_ends_with($name, '/');
        $entries[] = ['index' => $i, 'relative' => $relative, 'dir' => $isDir];
    }

    $restored = 0;
    foreach ($entries as $entry) {
        $relative = $entry['relative'];
        if (birkly_backup_path_has_link($rootReal, $relative)) {
            $zip->close();

            return ['ok' => false, 'error' => 'Backup destination contains a link'];
        }
        $dest = $rootReal . '/' . $relative;
        if ($entry['dir']) {
            if (!is_dir($dest) && !mkdir($dest, 0755, true) && !is_dir($dest)) {
                $zip->close();

                return ['ok' => false, 'error' => 'Could not create backup directory'];
            }
            continue;
        }

        $parent = dirname($dest);
        if (!is_dir($parent) && !mkdir($parent, 0755, true) && !is_dir($parent)) {
            $zip->close();

            return ['ok' => false, 'error' => 'Could not create backup directory'];
        }
        if (birkly_backup_path_has_link($rootReal, $relative)) {
            $zip->close();

            return ['ok' => false, 'error' => 'Backup destination contains a link'];
        }

        $bytes = $zip->getFromIndex($entry['index']);
        if (!is_string($bytes)) {
            $zip->close();

            return ['ok' => false, 'error' => 'Invalid backup file'];
        }
        if (file_put_contents($dest, $bytes) === false) {
            $zip->close();

            return ['ok' => false, 'error' => 'Could not write backup file'];
        }
        $written = realpath($dest);
        $tree = explode('/', $relative)[0];
        $treeReal = realpath($rootReal . '/' . $tree);
        if ($written === false || $treeReal === false || !str_starts_with($written, $treeReal . DIRECTORY_SEPARATOR)) {
            @unlink($dest);
            $zip->close();

            return ['ok' => false, 'error' => 'Backup path is not allowed'];
        }
        $restored++;
    }

    $zip->close();

    return ['ok' => true, 'restored' => $restored];
}
