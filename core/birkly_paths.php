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

    $detected = birkly_paths_detect();
    $GLOBALS['__birkly_base_path'] = $detected;
    return $detected;
}

/**
 * Pick the best base-path candidate from script, URI, redirect, and filesystem.
 */
function birkly_paths_detect(): string
{
    $candidates = array_values(array_filter([
        birkly_paths_detect_from_script(),
        birkly_paths_detect_from_request_uri(),
        birkly_paths_detect_from_redirect_url(),
        birkly_paths_detect_from_filesystem(),
    ], static fn(string $path): bool => $path !== ''));

    if ($candidates === []) {
        return '';
    }

    usort($candidates, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));

    return $candidates[0];
}

/**
 * Web path prefix for session cookies (covers admin + api under the install).
 */
function birkly_session_cookie_path(): string
{
    $base = birkly_base_path();
    return $base === '' ? '/' : $base . '/';
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
 * Admin media API URL (subdirectory-safe).
 */
function birkly_media_url(string $query = ''): string
{
    $base = birkly_url('/api/admin_media.php');
    if ($query === '') {
        return $base;
    }
    return $base . (str_starts_with($query, '?') ? $query : '?' . ltrim($query, '?'));
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

/**
 * Fallback when SCRIPT_NAME is rewritten (Apache/LiteSpeed) but REQUEST_URI shows the real path.
 */
function birkly_paths_detect_from_request_uri(): string
{
    $uri = str_replace('\\', '/', $_SERVER['REQUEST_URI'] ?? '');
    if ($uri === '') {
        return '';
    }

    $path = parse_url($uri, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        return '';
    }

    if (preg_match('#^(.*)/(?:admin|api)/#', $path, $m)) {
        return $m[1] === '' ? '' : rtrim($m[1], '/');
    }

    return '';
}

/**
 * Apache/LiteSpeed internal redirect URL (original path before rewrite).
 */
function birkly_paths_detect_from_redirect_url(): string
{
    $redirect = str_replace('\\', '/', $_SERVER['REDIRECT_URL'] ?? '');
    if ($redirect === '') {
        return '';
    }

    if (preg_match('#^(.*)/(?:admin|api)/#', $redirect, $m)) {
        return $m[1] === '' ? '' : rtrim($m[1], '/');
    }

    return '';
}

/**
 * Derive web path from DOCUMENT_ROOT vs Birkly install directory (rewrite-safe).
 */
function birkly_paths_detect_from_filesystem(): string
{
    $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    if ($docRoot === '') {
        return '';
    }

    $docRootReal = realpath($docRoot);
    $projectReal = realpath(birkly_project_root());
    if ($docRootReal === false || $projectReal === false) {
        return '';
    }

    $docRootReal = str_replace('\\', '/', $docRootReal);
    $projectReal = str_replace('\\', '/', $projectReal);

    if ($projectReal === $docRootReal) {
        return '';
    }

    if (!str_starts_with($projectReal, $docRootReal . '/')) {
        return '';
    }

    $relative = substr($projectReal, strlen($docRootReal));
    return rtrim($relative, '/');
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

/**
 * True when the CMS is accessed on a local dev host (localhost, loopback, .local/.test/.dev).
 */
function birkly_is_local_dev_request(): bool
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return false;
    }
    if (strpos($host, ':') !== false) {
        $host = explode(':', $host, 2)[0];
    }
    $localHosts = ['localhost', '127.0.0.1', '::1', '0.0.0.0'];
    if (in_array($host, $localHosts, true)) {
        return true;
    }
    foreach (['.local', '.test', '.dev'] as $suffix) {
        if (strlen($host) > strlen($suffix) && substr($host, -strlen($suffix)) === $suffix) {
            return true;
        }
    }
    return false;
}
