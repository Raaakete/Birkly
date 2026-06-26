<?php
/**
 * Base-path helpers for subdirectory installs (e.g. /birkly-test/Birkly-0.1/admin/).
 *
 * Auto-detects the web path prefix from SCRIPT_NAME. Optional override via
 * settings/admin.json "base_path" (leading slash, no trailing slash).
 */

declare(strict_types=1);

/**
 * Cached base path (web), e.g. "" for root or "/birkly-test/Birkly-0.1".
 */
function birkly_base_path(): string
{
    if (array_key_exists('__birkly_base_path', $GLOBALS)) {
        return (string) $GLOBALS['__birkly_base_path'];
    }

    $override = birkly_paths_read_override();
    if ($override !== null) {
        $GLOBALS['__birkly_base_path'] = $override;
        return $override;
    }

    $detected = birkly_paths_detect_from_script();
    $GLOBALS['__birkly_base_path'] = $detected;
    return $detected;
}

/**
 * Join base path with a root-relative URL path.
 */
function birkly_url(string $path): string
{
    if ($path === '' || preg_match('#^https?://#i', $path)) {
        return $path;
    }

    if ($path[0] !== '/') {
        $path = '/' . $path;
    }

    $base = birkly_base_path();
    if ($base === '') {
        return $path;
    }

    return $base . $path;
}

/**
 * Build an admin panel URL for ?page= routing.
 */
function birkly_admin_url(string $page, array $params = []): string
{
    $query = http_build_query(array_merge(['page' => $page], $params));
    return birkly_url('/admin/index.php' . ($query ? ('?' . $query) : ''));
}

/**
 * Filesystem path to the Birkly project root (directory containing admin/, api/, core/).
 */
function birkly_project_root(): string
{
    return dirname(__DIR__);
}

/**
 * Emit a JSON-safe base path string for admin JavaScript bootstrapping.
 */
function birkly_base_path_json(): string
{
    return json_encode(birkly_base_path(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function birkly_paths_detect_from_script(): string
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

    if ($script !== '' && preg_match('#^(.*)/(?:admin|api)/#', $script, $m)) {
        return $m[1] === '' ? '' : rtrim($m[1], '/');
    }

    if ($script !== '' && preg_match('#^(.*)/plugins/#', $script, $m)) {
        return $m[1] === '' ? '' : rtrim($m[1], '/');
    }

    $rootScripts = ['index.php', 'invite.php', 'server-router.php'];
    $basename = basename($script);
    if ($script !== '' && in_array($basename, $rootScripts, true)
        && preg_match('#^(.*)/' . preg_quote($basename, '#') . '$#', $script, $m)) {
        return $m[1] === '' ? '' : rtrim($m[1], '/');
    }

    return '';
}

function birkly_paths_read_override(): ?string
{
    static $cached = false;
    static $value = null;

    if ($cached) {
        return $value;
    }
    $cached = true;

    $settingsFile = birkly_project_root() . '/settings/admin.json';
    if (!is_readable($settingsFile)) {
        return null;
    }

    $raw = @file_get_contents($settingsFile);
    if ($raw === false) {
        return null;
    }

    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['base_path']) || !is_string($data['base_path'])) {
        return null;
    }

    $path = trim($data['base_path']);
    if ($path === '' || $path === '/') {
        $value = '';
        return $value;
    }

    if ($path[0] !== '/') {
        $path = '/' . $path;
    }

    $value = rtrim($path, '/');
    return $value;
}

/**
 * Reset cached base path (tests only).
 */
function birkly_paths_reset_cache(): void
{
    unset($GLOBALS['__birkly_base_path']);
}
