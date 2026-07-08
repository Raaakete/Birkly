<?php
/**
 * CMS health diagnostics (P12-M5) — disk, writable dirs, version, marketplace reachability.
 */

declare(strict_types=1);

require_once __DIR__ . '/birkly_paths.php';
require_once __DIR__ . '/plugins/dependencies.php';

/**
 * Directories the CMS must be able to write at runtime.
 *
 * @return list<string>
 */
function birkly_health_required_writable_dirs(): array
{
    return ['settings', 'content', 'cache', 'logs', 'media', 'temp'];
}

/**
 * Top-level JSON keys production smoke must validate.
 *
 * @return list<string>
 */
function birkly_health_required_response_keys(): array
{
    return ['status', 'service', 'version', 'php', 'time', 'writable', 'disk', 'marketplace', 'checks'];
}

/**
 * Structured checks catalog for monitoring and smoke validation.
 *
 * @return array<string, array{status:string}>
 */
function birkly_health_checks_catalog(array $writable, array $disk, array $marketplace, ?array $studio = null): array
{
    $writableStatus = 'ok';
    foreach (['settings', 'content', 'cache', 'logs'] as $required) {
        if (empty($writable[$required]['writable'])) {
            $writableStatus = 'error';
            break;
        }
    }
    if ($writableStatus === 'ok') {
        foreach (['media', 'temp'] as $optional) {
            if (empty($writable[$optional]['writable'])) {
                $writableStatus = 'degraded';
                break;
            }
        }
    }

    $diskStatus = !empty($disk['low_space']) ? 'degraded' : 'ok';

    $marketplaceStatus = match ((string) ($marketplace['status'] ?? '')) {
        'ok', 'skipped' => 'ok',
        'unreachable' => 'degraded',
        default => 'degraded',
    };

    $checks = [
        'writable_dirs' => ['status' => $writableStatus],
        'disk' => ['status' => $diskStatus],
        'marketplace' => ['status' => $marketplaceStatus],
    ];
    if (is_array($studio)) {
        $checks['studio'] = [
            'status' => !empty($studio['route_ready']) ? 'ok' : (!empty($studio['public_entry']) ? 'degraded' : 'error'),
        ];
    }

    return $checks;
}

/**
 * @return array<string, array{path:string,exists:bool,writable:bool}>
 */
function birkly_health_writable_dirs(): array
{
    if (isset($GLOBALS['birkly_health_writable_override']) && is_array($GLOBALS['birkly_health_writable_override'])) {
        return $GLOBALS['birkly_health_writable_override'];
    }

    $root = birkly_project_root();
    $out = [];

    foreach (birkly_health_required_writable_dirs() as $dir) {
        $path = $root . '/' . $dir;
        if (!is_dir($path)) {
            @mkdir($path, 0755, true);
        }
        $out[$dir] = [
            'path' => $dir,
            'exists' => is_dir($path),
            'writable' => is_dir($path) && is_writable($path),
        ];
    }

    return $out;
}

/**
 * @return array{free_bytes:?int,total_bytes:?int,free_percent:?float,low_space:bool}
 */
function birkly_health_disk(): array
{
    $root = birkly_project_root();
    $free = @disk_free_space($root);
    $total = @disk_total_space($root);

    $freeBytes = is_int($free) || is_float($free) ? (int) $free : null;
    $totalBytes = is_int($total) || is_float($total) ? (int) $total : null;
    $freePercent = null;
    $lowSpace = false;

    if ($freeBytes !== null && $totalBytes !== null && $totalBytes > 0) {
        $freePercent = round(($freeBytes / $totalBytes) * 100, 1);
        $lowSpace = $freeBytes < 100 * 1024 * 1024 || $freePercent < 5.0;
    }

    return [
        'free_bytes' => $freeBytes,
        'total_bytes' => $totalBytes,
        'free_percent' => $freePercent,
        'low_space' => $lowSpace,
    ];
}

/**
 * @return array{status:string,url:string,http_status?:int,error?:string}
 */
function birkly_health_marketplace(): array
{
    require_once __DIR__ . '/marketplace.php';

    $url = birkly_marketplace_url();
    if (trim((string) (getenv('BIRKLY_HEALTH_SKIP_MARKETPLACE') ?: '')) === '1') {
        return [
            'status' => 'skipped',
            'url' => $url,
        ];
    }

    $response = birkly_marketplace_request('GET', '/health');
    if (!empty($response['ok'])) {
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        if (($data['status'] ?? '') === 'ok') {
            return [
                'status' => 'ok',
                'url' => $url,
                'http_status' => (int) ($response['status'] ?? 200),
            ];
        }
    }

    return [
        'status' => 'unreachable',
        'url' => $url,
        'http_status' => (int) ($response['status'] ?? 0),
        'error' => (string) ($response['error'] ?? 'marketplace health check failed'),
    ];
}

/**
 * Studio SSR / public route readiness (P23).
 *
 * @return array{public_entry:bool,htaccess_rule:bool,index_route:bool,plugin_active:bool,route_ready:bool,hints:list<string>}
 */
function birkly_health_studio(): array
{
    $root = birkly_project_root();
    $publicEntry = is_file($root . '/api/studio_public.php');
    $htaccessRule = false;
    $htaccess = $root . '/.htaccess';
    if (is_readable($htaccess)) {
        $body = (string) file_get_contents($htaccess);
        $htaccessRule = str_contains($body, 'studio_public.php');
    }
    $indexRoute = false;
    $index = $root . '/index.php';
    if (is_readable($index)) {
        $body = (string) file_get_contents($index);
        $indexRoute = str_contains($body, '/site') && str_contains($body, 'studio_public.php');
    }
    $pluginActive = false;
    if (function_exists('birkly_is_plugin_active')) {
        require_once __DIR__ . '/plugins.php';
        load_plugins();
        $pluginActive = birkly_is_plugin_active('studio');
    }
    $routeReady = $publicEntry && $pluginActive && ($htaccessRule || $indexRoute);
    $hints = [];
    if (!$publicEntry) {
        $hints[] = 'Deploy api/studio_public.php (missing on this server).';
    }
    if (!$htaccessRule && !$indexRoute) {
        $hints[] = 'Route /site/* to api/studio_public.php (.htaccess on Apache, or nginx — see plugins/studio/README.md).';
    }
    if (!$pluginActive) {
        $hints[] = 'Activate the Studio plugin in Admin → Settings → Plugins.';
    }

    return [
        'public_entry' => $publicEntry,
        'htaccess_rule' => $htaccessRule,
        'index_route' => $indexRoute,
        'plugin_active' => $pluginActive,
        'route_ready' => $routeReady,
        'hints' => $hints,
    ];
}

/**
 * @return array<string, mixed>
 */
function birkly_health_report(): array
{
    $writable = birkly_health_writable_dirs();
    $disk = birkly_health_disk();
    $marketplace = birkly_health_marketplace();
    $studio = birkly_health_studio();

    $criticalFail = false;
    foreach (['settings', 'content', 'cache', 'logs'] as $required) {
        if (empty($writable[$required]['writable'])) {
            $criticalFail = true;
            break;
        }
    }

    $degraded = false;
    if (($marketplace['status'] ?? '') === 'unreachable') {
        $degraded = true;
    }
    if (!empty($disk['low_space'])) {
        $degraded = true;
    }
    foreach (['media', 'temp'] as $optional) {
        if (empty($writable[$optional]['writable'])) {
            $degraded = true;
        }
    }

    $status = $criticalFail ? 'error' : ($degraded ? 'degraded' : 'ok');
    $checks = birkly_health_checks_catalog($writable, $disk, $marketplace, $studio);

    return [
        'status' => $status,
        'service' => 'birkly-cms',
        'version' => get_birkly_version(),
        'php' => PHP_VERSION,
        'time' => gmdate('c'),
        'writable' => $writable,
        'disk' => $disk,
        'marketplace' => $marketplace,
        'studio' => $studio,
        'checks' => $checks,
    ];
}
