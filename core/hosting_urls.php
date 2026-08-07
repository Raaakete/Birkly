<?php
/**
 * Configurable hosting URLs — CMS vs public site (P43).
 */

declare(strict_types=1);

require_once __DIR__ . '/birkly_paths.php';
require_once __DIR__ . '/site_domain.php';

/**
 * @return array{cms_base_url: string, public_site_mode: string, public_site_url: string, same_host_landing: string, strict_admin_host: bool}
 */
function birkly_hosting_defaults(): array
{
    return [
        'cms_base_url' => '',
        'public_site_mode' => 'none',
        'public_site_url' => '',
        'same_host_landing' => 'admin',
        'strict_admin_host' => true,
    ];
}

function birkly_hosting_settings_path(): string
{
    return birkly_project_root() . '/settings/hosting.json';
}

function birkly_hosting_reset_cache(): void
{
    unset($GLOBALS['__birkly_hosting_config']);
}

function birkly_hosting_trust_request_host(): bool
{
    $value = strtolower(trim((string) (getenv('BIRKLY_TRUST_REQUEST_HOST') ?: '')));
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

/**
 * Optional Docker/staging overrides (do not set on production unless intentional).
 *
 * @return array{cms_base_url?:string,public_site_url?:string,public_site_mode?:string}
 */
function birkly_hosting_env_overrides(): array
{
    $overrides = [];

    $cms = trim((string) (getenv('BIRKLY_CMS_BASE_URL') ?: ''));
    if ($cms !== '') {
        $overrides['cms_base_url'] = birkly_hosting_normalize_url($cms, false);
    }

    $public = trim((string) (getenv('BIRKLY_PUBLIC_SITE_URL') ?: ''));
    if ($public !== '') {
        $overrides['public_site_url'] = birkly_hosting_normalize_url($public, true);
    }

    $mode = trim((string) (getenv('BIRKLY_PUBLIC_SITE_MODE') ?: ''));
    if ($mode !== '') {
        $overrides['public_site_mode'] = birkly_hosting_normalize_mode($mode);
    }

    return $overrides;
}

/**
 * @param array<string, mixed> $config
 * @return array<string, mixed>
 */
function birkly_hosting_apply_env_overrides(array $config): array
{
    $overrides = birkly_hosting_env_overrides();
    if ($overrides === []) {
        return $config;
    }

    return array_merge($config, $overrides);
}

/**
 * @return array{cms_base_url: string, public_site_mode: string, public_site_url: string, same_host_landing: string, strict_admin_host: bool}
 */
function birkly_hosting_load(): array
{
    if (isset($GLOBALS['__birkly_hosting_config']) && is_array($GLOBALS['__birkly_hosting_config'])) {
        return $GLOBALS['__birkly_hosting_config'];
    }

    $config = birkly_hosting_defaults();
    $path = birkly_hosting_settings_path();
    if (is_readable($path)) {
        $raw = file_get_contents($path);
        if ($raw !== false) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $config = array_merge($config, $decoded);
            }
        }
    }

    if (trim((string) $config['cms_base_url']) === '') {
        $config = birkly_hosting_migrate_legacy($config);
    }

    $config['cms_base_url'] = birkly_hosting_normalize_url((string) $config['cms_base_url'], false);
    $config['public_site_url'] = birkly_hosting_normalize_url((string) $config['public_site_url'], true);
    $config['public_site_mode'] = birkly_hosting_normalize_mode((string) $config['public_site_mode']);
    $config['same_host_landing'] = in_array($config['same_host_landing'], ['admin', 'public'], true)
        ? $config['same_host_landing']
        : 'admin';
    $config['strict_admin_host'] = (bool) $config['strict_admin_host'];

    $config = birkly_hosting_apply_env_overrides($config);

    [$config, $repairedLocal] = birkly_hosting_repair_local_dev_urls($config);

    $GLOBALS['__birkly_hosting_config'] = $config;

    // Persist port fixes so /project → apex redirects stop using http://localhost (no port).
    if (
        $repairedLocal
        && trim((string) $config['cms_base_url']) !== ''
        && empty($GLOBALS['__birkly_hosting_persisting'])
    ) {
        $GLOBALS['__birkly_hosting_persisting'] = true;
        birkly_hosting_persist($config, false);
        unset($GLOBALS['__birkly_hosting_persisting']);
        $GLOBALS['__birkly_hosting_config'] = $config;

        // Existing local installs that already have a project site but never
        // toggled Visit project — enable the header button once.
        require_once __DIR__ . '/project_site.php';
        require_once __DIR__ . '/project_settings.php';
        if (is_file(birkly_public_site_path() . '/index.html')) {
            $rawProject = birkly_project_settings_storage()->read('project.json');
            if (!is_array($rawProject) || !array_key_exists('show_visit_button', $rawProject)) {
                birkly_save_project_settings(['show_visit_button' => true]);
            }
        }
    }

    return $config;
}

function birkly_hosting_normalize_mode(string $mode): string
{
    return in_array($mode, ['none', 'external', 'project'], true) ? $mode : 'none';
}

function birkly_hosting_normalize_url(string $url, bool $allowEmpty = true): string
{
    $url = trim($url);
    if ($url === '') {
        return $allowEmpty ? '' : '';
    }

    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . ltrim($url, '/');
    }

    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['host'])) {
        return '';
    }

    $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
    $host = birkly_normalize_host((string) $parts['host']);
    $port = isset($parts['port']) ? (int) $parts['port'] : null;
    $path = isset($parts['path']) ? rtrim((string) $parts['path'], '/') : '';

    $normalized = $scheme . '://' . $host;
    if ($port !== null && !in_array($port, [80, 443], true)) {
        $normalized .= ':' . $port;
    }
    $normalized .= $path;

    return rtrim($normalized, '/');
}

function birkly_hosting_url_host(string $url): string
{
    if ($url === '') {
        return '';
    }
    return birkly_normalize_host((string) parse_url($url, PHP_URL_HOST));
}

function birkly_hosting_request_scheme(): string
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $proto = strtolower(trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]));
        if (in_array($proto, ['http', 'https'], true)) {
            return $proto;
        }
    }
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return 'https';
    }
    return 'http';
}

/**
 * Current request origin including non-default port (e.g. http://localhost:8080).
 * Uses HTTP_HOST as sent by the browser — do not strip the port first.
 */
function birkly_hosting_request_origin_url(): string
{
    $httpHost = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($httpHost === '') {
        return '';
    }

    return birkly_hosting_normalize_url(birkly_hosting_request_scheme() . '://' . $httpHost, false);
}

function birkly_hosting_build_url_from_request(): string
{
    return birkly_hosting_request_origin_url();
}

/**
 * Local php -S / reverse-proxy installs often persist public_site_url as
 * http://localhost (port dropped). Repair when the live request has a port.
 *
 * @param array<string, mixed> $config
 * @return array{0: array<string, mixed>, 1: bool} [config, changed]
 */
function birkly_hosting_repair_local_dev_urls(array $config): array
{
    require_once __DIR__ . '/birkly_paths.php';
    require_once __DIR__ . '/site_domain.php';

    if (!birkly_is_local_dev_request()) {
        return [$config, false];
    }

    $origin = birkly_hosting_request_origin_url();
    if ($origin === '') {
        return [$config, false];
    }

    $originParts = parse_url($origin);
    $originHost = birkly_normalize_host((string) ($originParts['host'] ?? ''));
    $originPort = isset($originParts['port']) ? (int) $originParts['port'] : null;
    if ($originHost === '' || $originPort === null) {
        return [$config, false];
    }

    $changed = false;
    foreach (['cms_base_url', 'public_site_url'] as $key) {
        $url = trim((string) ($config[$key] ?? ''));
        if ($url === '') {
            continue;
        }
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            continue;
        }
        $host = birkly_normalize_host((string) $parts['host']);
        $port = isset($parts['port']) ? (int) $parts['port'] : null;
        if ($host !== $originHost) {
            continue;
        }
        if ($port === $originPort) {
            continue;
        }
        // Same local host, wrong/missing port → rewrite to the live origin (+ path).
        $path = isset($parts['path']) ? rtrim((string) $parts['path'], '/') : '';
        if ($path === '/') {
            $path = '';
        }
        $config[$key] = rtrim($origin, '/') . $path;
        $changed = true;
    }

    return [$config, $changed];
}

/**
 * Infer hosting settings when hosting.json is empty (P43-D9).
 *
 * @param array<string, mixed> $partial
 * @return array<string, mixed>
 */
function birkly_hosting_migrate_legacy(array $partial = []): array
{
    $config = array_merge(birkly_hosting_defaults(), $partial);
    $requestHost = birkly_cms_request_host();

    $adminFile = birkly_project_root() . '/settings/admin.json';
    if (is_readable($adminFile)) {
        $admin = json_decode((string) file_get_contents($adminFile), true);
        if (is_array($admin) && !empty($admin['cms_url'])) {
            $config['cms_base_url'] = birkly_hosting_normalize_url((string) $admin['cms_url'], false);
        }
    }

    if ($config['cms_base_url'] === '' && $requestHost !== '') {
        if (str_starts_with($requestHost, 'cms.')) {
            $config['cms_base_url'] = birkly_hosting_build_url_from_request();
        }
    }

    $projectIndex = birkly_project_root() . '/project/index.html';
    $hasProject = is_file($projectIndex) || is_dir(birkly_project_root() . '/project');
    $cmsHost = birkly_hosting_url_host((string) $config['cms_base_url']);

    if ($config['cms_base_url'] === '' && $requestHost !== '') {
        $config['cms_base_url'] = birkly_hosting_build_url_from_request();
        $cmsHost = $requestHost;
    }

    if ($hasProject && $requestHost !== '' && $cmsHost !== '' && $requestHost !== $cmsHost) {
        $config['public_site_mode'] = 'project';
        $config['public_site_url'] = birkly_hosting_build_url_from_request();
    } elseif ($hasProject && $requestHost !== '' && $cmsHost === $requestHost && $config['public_site_mode'] === 'none') {
        // Legacy single-host + project folder — keep admin landing unless configured later.
        $config['public_site_mode'] = 'project';
        $config['public_site_url'] = $config['cms_base_url'];
        $config['same_host_landing'] = 'admin';
    }

    if ($config['cms_base_url'] !== '') {
        birkly_hosting_persist($config, false);
        birkly_hosting_sync_admin_cms_url((string) $config['cms_base_url']);
    }

    return $config;
}

/**
 * @param array<string, mixed> $config
 */
function birkly_hosting_persist(array $config, bool $registerDomains = true): bool
{
    $merged = array_merge(birkly_hosting_defaults(), $config);
    $merged['cms_base_url'] = birkly_hosting_normalize_url((string) $merged['cms_base_url'], false);
    $merged['public_site_url'] = birkly_hosting_normalize_url((string) $merged['public_site_url'], true);
    $merged['public_site_mode'] = birkly_hosting_normalize_mode((string) $merged['public_site_mode']);
    $merged['same_host_landing'] = in_array($merged['same_host_landing'], ['admin', 'public'], true)
        ? $merged['same_host_landing']
        : 'admin';
    $merged['strict_admin_host'] = (bool) $merged['strict_admin_host'];

    if ($merged['public_site_mode'] === 'none') {
        $merged['public_site_url'] = '';
    } elseif ($merged['public_site_url'] === '') {
        return false;
    }

    if ($merged['cms_base_url'] === '') {
        return false;
    }

    $dir = dirname(birkly_hosting_settings_path());
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        return false;
    }

    $merged['_updated_at'] = date('c');
    $ok = file_put_contents(
        birkly_hosting_settings_path(),
        json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
    ) !== false;

    if ($ok) {
        birkly_hosting_reset_cache();
        birkly_hosting_sync_admin_cms_url((string) $merged['cms_base_url']);
        if ($registerDomains) {
            birkly_hosting_register_domains($merged);
        }
    }

    return $ok;
}

/**
 * @param array<string, mixed> $config
 */
function birkly_hosting_register_domains(array $config): void
{
    require_once __DIR__ . '/site_domain.php';

    $cmsHost = birkly_hosting_url_host((string) ($config['cms_base_url'] ?? ''));
    if ($cmsHost !== '') {
        birkly_ensure_site_domain_registered($cmsHost);
    }

    if (($config['public_site_mode'] ?? 'none') !== 'none') {
        $publicHost = birkly_hosting_url_host((string) ($config['public_site_url'] ?? ''));
        if ($publicHost !== '' && $publicHost !== $cmsHost) {
            birkly_ensure_site_domain_registered($publicHost);
        }
    }
}

function birkly_hosting_sync_admin_cms_url(string $cmsBaseUrl): void
{
    $adminFile = birkly_project_root() . '/settings/admin.json';
    $admin = [];
    if (is_readable($adminFile)) {
        $decoded = json_decode((string) file_get_contents($adminFile), true);
        if (is_array($decoded)) {
            $admin = $decoded;
        }
    }
    $admin['cms_url'] = $cmsBaseUrl;
    $admin['_updated_at'] = date('c');
    file_put_contents($adminFile, json_encode($admin, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
}

function birkly_hosting_cms_host(): string
{
    $config = birkly_hosting_load();
    return birkly_hosting_url_host((string) $config['cms_base_url']);
}

function birkly_hosting_public_host(): string
{
    $config = birkly_hosting_load();
    if ($config['public_site_mode'] === 'none') {
        return '';
    }
    return birkly_hosting_url_host((string) $config['public_site_url']);
}

function birkly_hosting_same_host(): bool
{
    $cms = birkly_hosting_cms_host();
    $public = birkly_hosting_public_host();
    return $cms !== '' && $public !== '' && $cms === $public;
}

/**
 * @return 'cms'|'public'|'local'|'unknown'
 */
function birkly_request_host_role(): string
{
    $host = birkly_cms_request_host();
    if ($host === '') {
        return 'unknown';
    }
    if (birkly_is_local_dev_host($host)) {
        return 'local';
    }

    $cmsHost = birkly_hosting_cms_host();
    $publicHost = birkly_hosting_public_host();

    if ($cmsHost !== '' && $host === $cmsHost) {
        return 'cms';
    }
    if ($publicHost !== '' && $host === $publicHost) {
        return 'public';
    }

    if (birkly_hosting_trust_request_host() && $host !== '') {
        return 'cms';
    }

    return 'unknown';
}

function birkly_cms_base_url(): string
{
    $config = birkly_hosting_load();
    if ($config['cms_base_url'] !== '') {
        return $config['cms_base_url'];
    }
    return birkly_hosting_build_url_from_request();
}

function birkly_hosting_public_base_url(): string
{
    $config = birkly_hosting_load();
    if ($config['public_site_mode'] === 'none') {
        return '';
    }
    return (string) $config['public_site_url'];
}

function birkly_hosting_admin_url(string $page = 'dashboard', array $params = []): string
{
    $query = http_build_query(array_merge(['page' => $page], $params));
    $path = '/admin/index.php' . ($query !== '' ? '?' . $query : '');
    return birkly_cms_base_url() . birkly_url($path);
}

function birkly_hosting_should_redirect_admin(): bool
{
    $config = birkly_hosting_load();
    if (!$config['strict_admin_host']) {
        return false;
    }
    $role = birkly_request_host_role();
    return $role === 'public' || ($role === 'unknown' && birkly_hosting_cms_host() !== '');
}

function birkly_hosting_root_redirect_url(): ?string
{
    $config = birkly_hosting_load();
    $role = birkly_request_host_role();

    if ($role === 'local') {
        return birkly_hosting_admin_url('dashboard');
    }

    if ($config['public_site_mode'] === 'none') {
        return birkly_hosting_admin_url('dashboard');
    }

    if ($role === 'cms') {
        if (birkly_hosting_same_host() && $config['same_host_landing'] === 'public' && $config['public_site_mode'] === 'project') {
            return rtrim(birkly_hosting_public_base_url(), '/') . '/';
        }
        return birkly_hosting_admin_url('dashboard');
    }

    if ($role === 'public') {
        if ($config['public_site_mode'] === 'project') {
            return rtrim(birkly_hosting_public_base_url(), '/') . '/';
        }
        if ($config['public_site_mode'] === 'external') {
            return birkly_hosting_public_base_url() !== '' ? birkly_hosting_public_base_url() . '/' : null;
        }
    }

    if ($role === 'unknown' && $config['cms_base_url'] !== '') {
        return birkly_hosting_admin_url('dashboard');
    }

    return birkly_hosting_admin_url('dashboard');
}

function birkly_hosting_public_project_path(string $uri): string
{
    $uri = $uri === '' ? '/' : $uri;
    if ($uri[0] !== '/') {
        $uri = '/' . $uri;
    }
    if ($uri === '/') {
        return '/index.html';
    }
    return $uri;
}

function birkly_hosting_resolve_public_file(string $uri): ?string
{
    $config = birkly_hosting_load();
    if ($config['public_site_mode'] !== 'project') {
        return null;
    }

    require_once __DIR__ . '/project_site.php';
    if (birkly_is_git_metadata_path($uri)) {
        return null;
    }

    $role = birkly_request_host_role();
    if ($role !== 'public' && !($role === 'cms' && str_starts_with($uri, '/project/'))) {
        if ($role === 'local') {
            // Dev convenience: allow clean paths on localhost when mode=project.
        } elseif ($role !== 'public') {
            return null;
        }
    }

    $projectRoot = birkly_project_root() . '/project';
    if (!is_dir($projectRoot)) {
        return null;
    }

    if (str_starts_with($uri, '/project/')) {
        $relative = substr($uri, strlen('/project'));
    } elseif (str_starts_with($uri, '/project')) {
        $relative = substr($uri, strlen('/project'));
    } else {
        $relative = birkly_hosting_public_project_path($uri);
    }

    if ($relative === '' || $relative === '/') {
        $relative = '/index.html';
    }

    $candidate = realpath($projectRoot . $relative);
    if ($candidate === false) {
        if (!str_contains(basename($relative), '.') && is_file($projectRoot . rtrim($relative, '/') . '/index.html')) {
            $candidate = realpath($projectRoot . rtrim($relative, '/') . '/index.html');
        }
    }

    $projectReal = realpath($projectRoot);
    if ($candidate === false || $projectReal === false || !str_starts_with($candidate, $projectReal) || !is_file($candidate)) {
        return null;
    }

    return $candidate;
}

function birkly_hosting_clean_public_url(string $uri): string
{
    if (str_starts_with($uri, '/project/')) {
        $clean = substr($uri, strlen('/project'));
        return $clean === '' ? '/' : $clean;
    }
    if ($uri === '/project') {
        return '/';
    }
    return $uri;
}

function birkly_hosting_client_snippet(): string
{
    $cms = birkly_cms_base_url();
    if ($cms === '') {
        return '';
    }
    $script = rtrim($cms, '/') . birkly_url('/birkly-client.js');
    return '<script src="' . htmlspecialchars($script, ENT_QUOTES, 'UTF-8') . '" data-cms-url="' . htmlspecialchars($cms, ENT_QUOTES, 'UTF-8') . '"></script>';
}
