<?php
/**
 * Deploy / health endpoint helpers (P88).
 *
 * Public callers receive a minimal fingerprint only. Diagnostics (install metadata,
 * plugin probes, hosting env) require BIRKLY_DEPLOY_DIAG_SECRET via request header.
 */
declare(strict_types=1);

require_once __DIR__ . '/birkly_data_paths.php';

/**
 * Shared secret for deploy diagnostics (empty = diagnostics disabled except local dev).
 */
function birkly_deploy_diag_secret(): string
{
    return trim((string) (getenv('BIRKLY_DEPLOY_DIAG_SECRET') ?: ''));
}

/**
 * Whether diagnostic actions may be enabled at all on this request.
 */
function birkly_deploy_diag_configured(): bool
{
    if (birkly_deploy_diag_secret() !== '') {
        return true;
    }

    return function_exists('birkly_is_local_dev_request') && birkly_is_local_dev_request();
}

/**
 * True when the caller presented the deploy diagnostic secret (or local dev with no secret set).
 */
function birkly_deploy_diag_request_authorized(): bool
{
    if (!birkly_deploy_diag_configured()) {
        return false;
    }

    $secret = birkly_deploy_diag_secret();
    if ($secret === '') {
        return function_exists('birkly_is_local_dev_request') && birkly_is_local_dev_request();
    }

    $header = trim((string) ($_SERVER['HTTP_X_BIRKLY_DEPLOY_SECRET'] ?? ''));
    if ($header === '') {
        $header = trim((string) ($_SERVER['HTTP_X_BIRKLY_DEPLOY_DIAG_SECRET'] ?? ''));
    }

    return $header !== '' && hash_equals($secret, $header);
}

function birkly_require_deploy_diag_auth(): void
{
    if (birkly_deploy_diag_request_authorized()) {
        return;
    }

    if (!headers_sent()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
    }

    echo json_encode([
        'status' => 'error',
        'message' => 'Deploy diagnostics require authorization',
    ], JSON_UNESCAPED_UNICODE);
    exit;
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
