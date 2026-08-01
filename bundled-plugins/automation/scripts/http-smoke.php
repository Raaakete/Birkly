#!/usr/bin/env php
<?php
declare(strict_types=1);

$repoRoot = dirname(__DIR__);
$cmsRoot = getenv('BIRKLY_ROOT') ?: dirname($repoRoot) . '/Birkly';
$cmsRoot = rtrim($cmsRoot, '/');
$baseUrl = rtrim(getenv('BIRKLY_SMOKE_BASE_URL') ?: 'http://127.0.0.1:8080', '/');
$failures = 0;

function smoke_pass(string $label): void
{
    echo "PASS {$label}\n";
}

function smoke_fail(string $label, string $detail): void
{
    global $failures;
    $failures++;
    echo "FAIL {$label}: {$detail}\n";
}

function smoke_assert(bool $condition, string $label, string $detail = ''): void
{
    if ($condition) {
        smoke_pass($label);
        return;
    }
    smoke_fail($label, $detail !== '' ? $detail : 'assertion failed');
}

function smoke_request(string $method, string $url, ?array $body = null, array $headers = []): array
{
    $headers[] = 'Accept: application/json';
    $content = null;
    if ($body !== null) {
        $content = json_encode($body, JSON_UNESCAPED_SLASHES);
        $headers[] = 'Content-Type: application/json';
    }

    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'content' => $content,
            'ignore_errors' => true,
            'timeout' => 10,
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);
    $status = 0;
    foreach ($http_response_header ?? [] as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $m)) {
            $status = (int) $m[1];
            break;
        }
    }

    return [
        'status' => $status,
        'raw' => $raw === false ? '' : $raw,
        'json' => is_string($raw) && $raw !== '' ? json_decode($raw, true) : null,
    ];
}

function smoke_create_admin_session(string $csrfToken): string
{
    if (session_status() !== PHP_SESSION_NONE) {
        session_write_close();
    }

    $sessionId = 'autosmoke' . bin2hex(random_bytes(12));
    session_name('birkly_cms_sid');
    session_id($sessionId);
    session_start();
    $_SESSION['user'] = [
        'id' => 'automation-smoke-admin',
        'email' => 'automation-smoke@example.test',
        'role' => 'admin',
        'permissions' => ['*'],
    ];
    $_SESSION['csrf_token'] = $csrfToken;
    $_SESSION['last_activity'] = time();
    session_write_close();

    return $sessionId;
}

if (!is_dir($cmsRoot)) {
    smoke_fail('CMS root exists', "BIRKLY_ROOT not found: {$cmsRoot}");
    exit(1);
}

$sessionId = smoke_create_admin_session('automation-smoke-csrf-token');
$pluginDir = $cmsRoot . '/plugins/automation';
smoke_assert(is_file($pluginDir . '/plugin.php'), 'automation plugin is installed under CMS plugins/automation', $pluginDir);

$bootstrap = $cmsRoot . '/core/automation/bootstrap.php';
if (is_file($bootstrap)) {
    $cwd = getcwd();
    chdir($cmsRoot . '/api');
    require_once $bootstrap;
    $loaded = function_exists('automation_bootstrap') && automation_bootstrap();
    smoke_assert($loaded, 'CMS automation bootstrap loads active plugin', 'automation_bootstrap() returned false');
    smoke_assert(function_exists('get_automations'), 'automation runtime functions are available', 'get_automations() missing after bootstrap');
    require_once $pluginDir . '/includes/cron_auth.php';
    $cronToken = automation_get_cron_token();
    chdir($cwd ?: $repoRoot);
} else {
    smoke_fail('CMS automation bootstrap exists', $bootstrap);
    $cronToken = '';
}

$health = smoke_request('GET', $baseUrl . '/api/public.php?action=list_collections');
smoke_assert($health['status'] > 0, 'CMS dev server responds over HTTP', "no response from {$baseUrl}; start: php -S localhost:8080 -t {$cmsRoot} {$cmsRoot}/server-router.php");

$noCron = smoke_request('GET', $baseUrl . '/api/automation.php?action=process_queue');
smoke_assert($noCron['status'] === 403, 'process_queue rejects missing cron token', 'HTTP ' . $noCron['status'] . ' body=' . $noCron['raw']);

if ($cronToken !== '') {
    $withCron = smoke_request('GET', $baseUrl . '/api/automation.php?action=process_queue&token=' . rawurlencode($cronToken));
    smoke_assert($withCron['status'] === 200 && (($withCron['json']['status'] ?? '') === 'ok'), 'process_queue accepts valid cron token', 'HTTP ' . $withCron['status'] . ' body=' . $withCron['raw']);
}

$csrfProbe = smoke_request(
    'POST',
    $baseUrl . '/api/automation.php',
    ['action' => 'create', 'name' => 'Automation smoke CSRF probe'],
    ['Cookie: birkly_cms_sid=' . $sessionId]
);
smoke_assert(
    $csrfProbe['status'] === 403 && str_contains($csrfProbe['raw'], 'Invalid CSRF token'),
    'mutating automation API rejects authenticated request without CSRF',
    'HTTP ' . $csrfProbe['status'] . ' body=' . $csrfProbe['raw']
);

if ($failures > 0) {
    echo "Automation HTTP smoke: FAIL ({$failures})\n";
    exit(1);
}

echo "Automation HTTP smoke: PASS\n";
