<?php
/**
 * Shared HTTP helpers for integration/E2E tests (P4-M2).
 */

function birkly_test_http_server_start(string $root, int $port, array $env = []): int
{
    $envPrefix = '';
    foreach ($env as $key => $value) {
        if (!is_string($key) || $key === '' || preg_match('/[^A-Za-z0-9_]/', $key)) {
            continue;
        }
        $envPrefix .= $key . '=' . escapeshellarg((string) $value) . ' ';
    }

    $cmd = sprintf(
        '%sphp -S 127.0.0.1:%d -t %s %s/server-router.php',
        $envPrefix,
        $port,
        escapeshellarg($root),
        escapeshellarg($root)
    );
    $spec = [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']];
    $proc = proc_open($cmd, $spec, $pipes);
    if (!is_resource($proc)) {
        fwrite(STDERR, "http_test_helpers: failed to start PHP server\n");
        exit(1);
    }

    $status = proc_get_status($proc);
    $pid = (int) ($status['pid'] ?? 0);
    $readyUrl = "http://127.0.0.1:$port/api/public.php?action=list_collections";

    for ($i = 0; $i < 40; $i++) {
        $code = birkly_test_http_status_only($readyUrl);
        if ($code > 0) {
            return $pid;
        }
        usleep(150000);
    }

    proc_terminate($proc);
    fwrite(STDERR, "http_test_helpers: server did not become ready on port $port\n");
    exit(1);
}

function birkly_test_http_server_stop(int $pid): void
{
    if ($pid > 0 && function_exists('posix_kill')) {
        posix_kill($pid, SIGTERM);
        usleep(200000);
    }
}

function birkly_test_http_status_only(string $url): int
{
    if (!function_exists('curl_init')) {
        return 0;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_NOBODY => false,
    ]);
    curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code;
}

/**
 * @return array{code:int,body:string}
 */
function birkly_test_http_request(string $url, array $options = []): array
{
    if (!function_exists('curl_init')) {
        fwrite(STDERR, "http_test_helpers: curl extension required\n");
        exit(1);
    }

    $method = strtoupper($options['method'] ?? 'GET');
    $body = $options['body'] ?? null;
    $headers = $options['headers'] ?? [];
    $cookieFile = $options['cookie_file'] ?? null;

    $ch = curl_init($url);
    $curlOpts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => false,
    ];

    if ($cookieFile) {
        $curlOpts[CURLOPT_COOKIEJAR] = $cookieFile;
        $curlOpts[CURLOPT_COOKIEFILE] = $cookieFile;
    }

    if ($body !== null) {
        $curlOpts[CURLOPT_POSTFIELDS] = $body;
    }

    curl_setopt_array($ch, $curlOpts);
    $responseBody = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($responseBody, 0, $headerSize);
    $body = substr($responseBody, $headerSize);
    curl_close($ch);

    return ['code' => $code, 'body' => $body, 'headers' => $headers];
}

function birkly_test_backup_directory(string $sourceDir, string $backupDir): void
{
    if (!is_dir($sourceDir)) {
        return;
    }

    if (is_dir($backupDir)) {
        birkly_test_remove_directory($backupDir);
    }
    mkdir($backupDir, 0755, true);

    foreach (glob($sourceDir . '/*.json') ?: [] as $file) {
        copy($file, $backupDir . '/' . basename($file));
    }
}

function birkly_test_restore_directory(string $sourceDir, string $backupDir): void
{
    if (!is_dir($sourceDir)) {
        return;
    }

    foreach (glob($sourceDir . '/*.json') ?: [] as $file) {
        unlink($file);
    }

    if (!is_dir($backupDir)) {
        return;
    }

    foreach (glob($backupDir . '/*.json') ?: [] as $file) {
        copy($file, $sourceDir . '/' . basename($file));
    }
}

function birkly_test_clear_json_files(string $dir): void
{
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        return;
    }

    foreach (glob($dir . '/*.json') ?: [] as $file) {
        unlink($file);
    }
}

function birkly_test_remove_directory(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    foreach (glob($dir . '/*') ?: [] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    rmdir($dir);
}

function birkly_test_json_decode(string $body): ?array
{
    $decoded = json_decode($body, true);
    return is_array($decoded) ? $decoded : null;
}
