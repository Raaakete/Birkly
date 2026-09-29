<?php
/**
 * Community-user entry paths. Ids are uniqid() (letters, digits, underscore, hyphen).
 */

declare(strict_types=1);

/**
 * Returns null when the id could leave the entries directory.
 */
function community_users_entry_file(string $entriesDir, string $id): ?string
{
    if ($id === '' || preg_match('/^[A-Za-z0-9_-]+$/', $id) !== 1) {
        return null;
    }
    if (!is_dir($entriesDir)) {
        return rtrim($entriesDir, '/\\') . DIRECTORY_SEPARATOR . $id . '.json';
    }
    $root = realpath($entriesDir);
    if ($root === false) {
        return null;
    }
    $path = $root . DIRECTORY_SEPARATOR . $id . '.json';
    if (is_file($path)) {
        $real = realpath($path);
        if ($real === false || dirname($real) !== $root) {
            return null;
        }
        return $real;
    }

    return $path;
}
