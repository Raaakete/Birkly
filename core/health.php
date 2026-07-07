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
 * @return array<string, array{path:string,exists:bool,writable:bool}>
 */
function birkly_health_writable_dirs(): array
{
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
 * @return array<string, mixed>
 */
function birkly_health_report(): array
{
    $writable = birkly_health_writable_dirs();
    $disk = birkly_health_disk();
    $marketplace = birkly_health_marketplace();

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

    return [
        'status' => $status,
        'service' => 'birkly-cms',
        'version' => get_birkly_version(),
        'php' => PHP_VERSION,
        'time' => gmdate('c'),
        'writable' => $writable,
        'disk' => $disk,
        'marketplace' => $marketplace,
    ];
}
