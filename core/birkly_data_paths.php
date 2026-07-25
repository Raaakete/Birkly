<?php
/**
 * Unified data root for Docker / VPS installs (BIRKLY_DATA).
 *
 * When set (e.g. /app/data), the entrypoint symlinks legacy top-level folders
 * (content/, settings/, users/, media/, …) into that directory so existing
 * birkly_project_root() paths keep working. Runtime API dirs (webhooks, AI, …)
 * live directly under the same root.
 */

declare(strict_types=1);

require_once __DIR__ . '/birkly_paths.php';

/**
 * Absolute path to the unified data root, or null for legacy flat layout.
 */
function birkly_data_root(): ?string
{
    if (array_key_exists('__birkly_data_root', $GLOBALS)) {
        $cached = $GLOBALS['__birkly_data_root'];
        return $cached === null ? null : (string) $cached;
    }

    $raw = getenv('BIRKLY_DATA');
    if ($raw === false || $raw === '') {
        $raw = $_ENV['BIRKLY_DATA'] ?? $_SERVER['BIRKLY_DATA'] ?? '';
    }

    $raw = trim((string) $raw);
    if ($raw === '') {
        $GLOBALS['__birkly_data_root'] = null;
        return null;
    }

    $real = realpath($raw);
    $resolved = $real !== false ? $real : $raw;
    $GLOBALS['__birkly_data_root'] = $resolved;

    return $resolved;
}

/**
 * Path for disk-space and persistence checks (data root when configured).
 */
function birkly_data_disk_path(): string
{
    $dataRoot = birkly_data_root();
    if ($dataRoot !== null) {
        return $dataRoot;
    }

    return birkly_project_root();
}

/**
 * Top-level persistent directory names placed under BIRKLY_DATA when configured.
 *
 * @return list<string>
 */
function birkly_data_persistent_dir_names(): array
{
    return ['content', 'settings', 'users', 'media', 'cache', 'logs', 'temp', 'plugins', 'themes'];
}

/**
 * Reset cached data root (tests only).
 */
function birkly_data_paths_reset_cache(): void
{
    unset($GLOBALS['__birkly_data_root']);
}
