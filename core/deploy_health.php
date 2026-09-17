<?php
/**
 * Deploy / health endpoint helpers (P88).
 *
 * Public callers receive a minimal fingerprint only. Diagnostics require a
 * normal admin session (read_settings / manage_settings) — no server env secrets.
 */
declare(strict_types=1);

require_once __DIR__ . '/birkly_data_paths.php';

/**
 * Admin must be logged in to read deploy diagnostics (hosting, plugins, etc.).
 */
function birkly_require_deploy_admin_read(): void
{
    require_once dirname(__DIR__) . '/api/auth.php';
    requireAuth('read_settings');
}

/**
 * Destructive or mutating deploy actions (plugin repair, bundled sync, …).
 */
function birkly_require_deploy_admin_write(): void
{
    require_once dirname(__DIR__) . '/api/auth.php';
    requireAuth('manage_settings');
}

/**
 * Safe JSON for unauthenticated health checks (Dokploy, load balancers).
 *
 * @return array<string, mixed>
 */
function birkly_public_deploy_health_payload(): array
{
    return [
        'status' => 'ok',
        'service' => 'birkly-cms',
        'build_revision' => birkly_deploy_revision(),
        'time' => gmdate('c'),
    ];
}

/**
 * @return array{ok: bool, detail: ?string}
 */
function birkly_php_lint_file(string $file): array
{
    if (!is_file($file)) {
        return ['ok' => false, 'detail' => 'file missing'];
    }

    $php = defined('PHP_BINARY') ? PHP_BINARY : 'php';
    $cmd = [$php, '-l', $file];
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = @proc_open($cmd, $descriptors, $pipes);
    if (!is_resource($process)) {
        return ['ok' => false, 'detail' => 'lint unavailable'];
    }
    fclose($pipes[0]);
    $stdout = (string) stream_get_contents($pipes[1]);
    $stderr = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);

    $output = trim($stdout . "\n" . $stderr);
    $ok = str_contains($output, 'No syntax errors detected');

    return ['ok' => $ok, 'detail' => $ok ? null : $output];
}

/**
 * In-process plugin load probe (no shell).
 *
 * @return array{ok: bool, error: ?string}
 */
function birkly_probe_plugin_load(string $pluginId): array
{
    if ($pluginId === '' || !preg_match('/^[a-z0-9_-]+$/i', $pluginId)) {
        return ['ok' => false, 'error' => 'invalid plugin_id'];
    }

    $root = birkly_project_root();
    require_once $root . '/core/plugins.php';
    require_once $root . '/core/plugins/health.php';

    if (function_exists('load_plugins')) {
        load_plugins();
    }

    if (!function_exists('birkly_plugin_smoke_load')) {
        return ['ok' => false, 'error' => 'smoke loader missing'];
    }

    $smoke = birkly_plugin_smoke_load($pluginId);

    return [
        'ok' => !empty($smoke['ok']),
        'error' => !empty($smoke['ok']) ? null : (string) ($smoke['error'] ?? 'load failed'),
    ];
}
