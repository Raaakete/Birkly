<?php
/**
 * M5-T1: Auth boundary regression tests (fixture-driven).
 * Run: php tests/auth_boundary_test.php
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();

$fixturePath = __DIR__ . '/fixtures/auth_boundary.json';
$fixture = json_decode(file_get_contents($fixturePath), true);
if (!$fixture || empty($fixture['probes']) || !is_array($fixture['probes'])) {
    fwrite(STDERR, "auth_boundary_test: invalid fixture at $fixturePath\n");
    exit(1);
}

$port = (int) (getenv('AUTH_PROBE_PORT') ?: 18080);
$base = "http://127.0.0.1:$port";
$root = dirname(__DIR__);

function auth_http_status(string $url, string $method, ?string $body, ?string $contentType): int
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $contentType ? ["Content-Type: $contentType"] : [],
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 5,
    ]);
    curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code;
}

function auth_start_server(string $root, int $port): int
{
    $cmd = sprintf(
        'php -S 127.0.0.1:%d -t %s %s/server-router.php',
        $port,
        escapeshellarg($root),
        escapeshellarg($root)
    );
    $spec = [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']];
    $proc = proc_open($cmd, $spec, $pipes);
    if (!is_resource($proc)) {
        fwrite(STDERR, "auth_boundary_test: failed to start PHP server\n");
        exit(1);
    }
    $status = proc_get_status($proc);
    $pid = (int) ($status['pid'] ?? 0);
    $readyUrl = "http://127.0.0.1:$port/api/public.php?action=list_collections";

    for ($i = 0; $i < 30; $i++) {
        $code = auth_http_status($readyUrl, 'GET', null, null);
        if ($code > 0) {
            return $pid;
        }
        usleep(200000);
    }

    proc_terminate($proc);
    fwrite(STDERR, "auth_boundary_test: server did not become ready on port $port\n");
    exit(1);
}

function auth_stop_server(int $pid): void
{
    if ($pid > 0 && function_exists('posix_kill')) {
        posix_kill($pid, SIGTERM);
        usleep(200000);
    }
}

echo "Auth boundary tests (fixture-driven)\n\n";

if (!function_exists('curl_init')) {
    fwrite(STDERR, "auth_boundary_test: curl extension required\n");
    exit(1);
}

$serverPid = auth_start_server($root, $port);

try {
    foreach ($fixture['probes'] as $probe) {
        $label = $probe['label'] ?? 'unknown';
        $path = $probe['path'] ?? '';
        $method = strtoupper($probe['method'] ?? 'GET');
        $expect = $probe['expect'] ?? [];
        $body = $probe['body'] ?? null;
        $contentType = $probe['content_type'] ?? null;

        $url = $base . $path;
        $code = auth_http_status($url, $method, $body, $contentType);
        $ok = in_array($code, $expect, true);
        $detail = $ok ? '' : "got $code, expected " . implode('|', $expect);
        test_assert($label, $ok, $detail);
    }
} finally {
    auth_stop_server($serverPid);
}

exit(test_finish('auth_boundary_test'));
