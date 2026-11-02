<?php
/**
 * Optional custom HTTP 404 page for Birkly-hosted project sites.
 */

declare(strict_types=1);

require_once __DIR__ . '/project_settings.php';
require_once __DIR__ . '/project_site.php';
require_once __DIR__ . '/hosting_urls.php';

const BIRKLY_PROJECT_CUSTOM_404_DEFAULT_PATH = '404.html';

/**
 * @return array<string, mixed>
 */
function birkly_project_custom_404_settings_slice(?array $settings = null): array
{
    $settings = $settings ?? birkly_load_project_settings();

    return [
        'custom_404_enabled' => !empty($settings['custom_404_enabled']),
        'custom_404_path' => birkly_project_custom_404_normalize_path(
            (string) ($settings['custom_404_path'] ?? BIRKLY_PROJECT_CUSTOM_404_DEFAULT_PATH)
        ),
    ];
}

function birkly_project_custom_404_normalize_path(string $path): string
{
    $path = str_replace('\\', '/', trim($path));
    $path = ltrim($path, '/');
    if ($path === '') {
        return BIRKLY_PROJECT_CUSTOM_404_DEFAULT_PATH;
    }
    if (str_contains($path, '..')) {
        return BIRKLY_PROJECT_CUSTOM_404_DEFAULT_PATH;
    }

    return $path;
}

/**
 * Whether custom 404 can apply on this install (Birkly serves project/ publicly).
 */
function birkly_project_custom_404_hosting_applies(): bool
{
    $hosting = birkly_hosting_load();
    if (($hosting['public_site_mode'] ?? 'none') !== 'project') {
        return false;
    }

    $settings = birkly_load_project_settings();
    if (($settings['hosting_type'] ?? 'birkly') === 'external') {
        return false;
    }

    return true;
}

function birkly_project_custom_404_is_enabled(?array $settings = null): bool
{
    $slice = birkly_project_custom_404_settings_slice($settings);

    return $slice['custom_404_enabled'] === true;
}

function birkly_project_custom_404_is_active(?array $settings = null): bool
{
    return birkly_project_custom_404_hosting_applies() && birkly_project_custom_404_is_enabled($settings);
}

/**
 * Absolute path to configured 404 HTML when it exists on disk.
 */
function birkly_project_custom_404_resolve_file(?array $settings = null): ?string
{
    $slice = birkly_project_custom_404_settings_slice($settings);
    $projectRoot = birkly_public_site_path();
    $candidate = realpath($projectRoot . '/' . $slice['custom_404_path']);
    $rootReal = realpath($projectRoot);
    if ($candidate === false || $rootReal === false || !str_starts_with($candidate, $rootReal) || !is_file($candidate)) {
        return null;
    }

    return $candidate;
}

/**
 * True when the setting has never been explicitly saved (for admin suggestion).
 */
function birkly_project_custom_404_show_enable_suggestion(): bool
{
    $raw = birkly_project_settings_storage()->read('project.json');
    if (!is_array($raw) || array_key_exists('custom_404_enabled', $raw)) {
        return false;
    }

    $defaultPath = birkly_public_site_path() . '/' . BIRKLY_PROJECT_CUSTOM_404_DEFAULT_PATH;

    return is_file($defaultPath);
}

/**
 * @return list<string> Public URL paths to exclude from sitemaps when custom 404 is active.
 */
function birkly_project_custom_404_sitemap_exclude_paths(?array $settings = null): array
{
    if (!birkly_project_custom_404_is_active($settings)) {
        return [];
    }

    $slice = birkly_project_custom_404_settings_slice($settings);
    $rel = '/' . ltrim($slice['custom_404_path'], '/');
    $paths = [$rel];
    if (str_ends_with(strtolower($rel), '.html')) {
        $paths[] = substr($rel, 0, -5);
        $paths[] = substr($rel, 0, -5) . '/';
    }

    return array_values(array_unique($paths));
}

function birkly_project_custom_404_sitemap_should_skip_rel(string $relPath, ?array $settings = null): bool
{
    $relPath = '/' . ltrim(str_replace('\\', '/', $relPath), '/');
    foreach (birkly_project_custom_404_sitemap_exclude_paths($settings) as $exclude) {
        $excludeNorm = '/' . ltrim($exclude, '/');
        if ($relPath === $excludeNorm || $relPath === rtrim($excludeNorm, '/')) {
            return true;
        }
    }

    return false;
}

/**
 * @param array<string, mixed> $input
 * @return array{patch: array<string, mixed>, error: ?string, warning: ?string}
 */
function birkly_project_custom_404_validate_save_input(array $input, ?array $existingSettings = null): array
{
    $patch = [];
    $warning = null;

    if (array_key_exists('custom_404_enabled', $input)) {
        $patch['custom_404_enabled'] = filter_var($input['custom_404_enabled'], FILTER_VALIDATE_BOOLEAN);
    }

    if (array_key_exists('custom_404_path', $input)) {
        $path = birkly_project_custom_404_normalize_path((string) $input['custom_404_path']);
        if (!preg_match('/\.html?$/i', $path)) {
            return ['patch' => [], 'error' => 'Custom 404 page must be an .html or .htm file inside project/.', 'warning' => null];
        }
        $patch['custom_404_path'] = $path;
    }

    if ($patch === []) {
        return ['patch' => [], 'error' => null, 'warning' => null];
    }

    $merged = array_merge(
        birkly_project_custom_404_settings_slice($existingSettings ?? birkly_load_project_settings()),
        $patch
    );
    if (!empty($merged['custom_404_enabled'])) {
        $probe = birkly_project_custom_404_resolve_file(array_merge(
            birkly_load_project_settings(),
            $patch
        ));
        if ($probe === null) {
            $warning = 'The chosen custom 404 page was not found in project/. Visitors will still see the plain Not found response until the file exists.';
        }
    }

    return ['patch' => $patch, 'error' => null, 'warning' => $warning];
}

function birkly_hosting_uri_excluded_from_custom_404(string $uri): bool
{
    $path = '/' . ltrim(parse_url($uri, PHP_URL_PATH) ?: $uri, '/');

    if (birkly_hosting_is_platform_endpoint_path($path)) {
        return true;
    }

    $prefixes = [
        '/api/',
        '/admin',
        '/media/',
        '/content/',
        '/site/',
        '/plugins/',
        '/core/',
        '/fieldtypes/',
    ];
    foreach ($prefixes as $prefix) {
        if ($path === rtrim($prefix, '/') || str_starts_with($path, $prefix)) {
            return true;
        }
    }

    if (str_starts_with($path, '/.well-known/')) {
        return true;
    }

    return false;
}

function birkly_hosting_request_prefers_custom_404_page(string $uri): bool
{
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method !== 'GET' && $method !== 'HEAD') {
        return false;
    }

    if (birkly_hosting_uri_excluded_from_custom_404($uri)) {
        return false;
    }

    $path = parse_url($uri, PHP_URL_PATH) ?: $uri;
    $basename = basename((string) $path);
    if ($basename !== '' && str_contains($basename, '.')) {
        $ext = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
        if ($ext !== 'html' && $ext !== 'htm') {
            return false;
        }
    }

    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    if ($accept !== '' && !str_contains($accept, 'text/html') && !str_contains($accept, '*/*')
        && !str_contains($accept, 'application/xhtml+xml')) {
        return false;
    }

    return true;
}

function birkly_hosting_send_plain_not_found(): void
{
    if (!headers_sent()) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo 'Not found';
}

/**
 * True when a resolved project file should be served as HTTP 404 (direct /404.html).
 */
function birkly_project_custom_404_is_configured_public_file(string $absoluteFilePath, ?array $settings = null): bool
{
    if (!birkly_project_custom_404_is_active($settings)) {
        return false;
    }

    $configured = birkly_project_custom_404_resolve_file($settings);
    if ($configured === null) {
        return false;
    }

    $resolved = realpath($absoluteFilePath);

    return $resolved !== false && $resolved === realpath($configured);
}

function birkly_project_custom_404_inject_not_found_meta(string $html, string $requestedPath): string
{
    $requestedPath = '/' . ltrim(parse_url($requestedPath, PHP_URL_PATH) ?: $requestedPath, '/');
    if ($requestedPath === '//') {
        $requestedPath = '/';
    }
    $query = $_SERVER['QUERY_STRING'] ?? '';
    if (is_string($query) && $query !== '') {
        $requestedPath .= '?' . $query;
    }

    $meta = '<meta name="birkly-not-found" content="' . htmlspecialchars($requestedPath, ENT_QUOTES, 'UTF-8') . '">';
    if (stripos($html, 'name="birkly-not-found"') !== false || stripos($html, "name='birkly-not-found'") !== false) {
        return $html;
    }
    if (preg_match('/<head[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
        $pos = $m[0][1] + strlen($m[0][0]);

        return substr($html, 0, $pos) . "\n    " . $meta . substr($html, $pos);
    }

    return $meta . "\n" . $html;
}

/**
 * Emit configured custom 404 page (HTTP 404, SSR when enabled).
 */
function birkly_hosting_serve_custom_not_found_page(string $filePath, string $requestedUri): void
{
    require_once __DIR__ . '/project_ssr.php';

    if (!headers_sent()) {
        http_response_code(404);
        header('X-Robots-Tag: noindex');
        header('Cache-Control: no-store');
    }

    $query = isset($_GET) && is_array($_GET) ? $_GET : [];

    if (birkly_project_ssr_should_render($filePath)) {
        if (birkly_project_ssr_serve($filePath, $query, [
            'http_status' => 404,
            'skip_cache' => true,
            'not_found_uri' => $requestedUri,
        ])) {
            return;
        }
    }

    if (!headers_sent()) {
        header('Content-Type: text/html; charset=UTF-8');
        header('X-Birkly-Render: static');
    }

    $html = (string) @file_get_contents($filePath);
    $html = birkly_project_custom_404_inject_not_found_meta($html, $requestedUri);
    echo birkly_project_prepare_html_for_serve($html);
}

/**
 * Respond to an unmatched public request (plain or custom 404).
 * Returns true when a response was sent.
 */
function birkly_hosting_try_respond_not_found(string $uri): bool
{
    if (!birkly_project_custom_404_hosting_applies()) {
        return false;
    }

    if (!birkly_hosting_request_prefers_custom_404_page($uri)) {
        birkly_hosting_send_plain_not_found();

        return true;
    }

    if (!birkly_project_custom_404_is_enabled()) {
        birkly_hosting_send_plain_not_found();

        return true;
    }

    $file = birkly_project_custom_404_resolve_file();
    if ($file === null) {
        birkly_hosting_send_plain_not_found();

        return true;
    }

    birkly_hosting_serve_custom_not_found_page($file, $uri);

    return true;
}
