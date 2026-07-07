<?php
/**
 * Birkly Marketplace — flat-file JSON storage helper.
 */

/**
 * Resolve a path inside the data directory.
 */
function marketplace_data_path(string $relative = ''): string
{
    $base = (string) marketplace_config('data_dir', MARKETPLACE_ROOT . '/data');
    $relative = ltrim($relative, '/');

    if ($relative === '') {
        return rtrim($base, '/');
    }

    return rtrim($base, '/') . '/' . $relative;
}

/**
 * Read a JSON collection file. Returns an empty array when missing or invalid.
 */
function marketplace_storage_read(string $collection): array
{
    $path = marketplace_data_path($collection . '.json');

    if (!is_file($path)) {
        return [];
    }

    $raw = file_get_contents($path);
    if ($raw === false || $raw === '') {
        return [];
    }

    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * Write a JSON collection file atomically.
 */
function marketplace_storage_write(string $collection, array $data): bool
{
    $dir = marketplace_data_path();
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        return false;
    }

    $path = marketplace_data_path($collection . '.json');
    $tmp = $path . '.tmp.' . getmypid();
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($json === false) {
        return false;
    }

    if (file_put_contents($tmp, $json . "\n", LOCK_EX) === false) {
        @unlink($tmp);
        return false;
    }

    if (!rename($tmp, $path)) {
        @unlink($tmp);
        return false;
    }

    return true;
}

/**
 * Delete a JSON collection file.
 */
function marketplace_storage_delete(string $collection): bool
{
    $path = marketplace_data_path($collection . '.json');

    if (!is_file($path)) {
        return true;
    }

    return unlink($path);
}
