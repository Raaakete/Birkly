<?php
/**
 * Entry counting helpers for admin collection cards and API metadata.
 */

declare(strict_types=1);

/**
 * Count entry JSON files in a collection entries directory.
 * By default excludes soft-deleted (trashed) entries so card counts match the active list.
 */
function birkly_count_entry_files(string $entriesDir, bool $excludeTrashed = true): int
{
    if (!is_dir($entriesDir)) {
        return 0;
    }

    $files = glob(rtrim($entriesDir, '/') . '/*.json');
    if (!$files) {
        return 0;
    }

    $count = 0;
    foreach ($files as $file) {
        if (!is_file($file)) {
            continue;
        }
        if ($excludeTrashed) {
            $raw = @file_get_contents($file);
            if ($raw === false) {
                continue;
            }
            $data = json_decode($raw, true);
            if (is_array($data) && (($data['status'] ?? '') === 'trashed')) {
                continue;
            }
        }
        $count++;
    }

    return $count;
}
