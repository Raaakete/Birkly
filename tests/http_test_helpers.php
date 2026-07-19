<?php
/**
 * Shared HTTP helpers for integration/E2E tests (P4-M2).
 */

function birkly_test_is_port_in_use(int $port): bool
{
    $fp = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.05);
    if ($fp !== false) {
        fclose($fp);
        return true;
    }

    return false;
}

/**
 * Pick a free localhost port for ephemeral php -S servers (avoids pid-modulo collisions).
 */
function birkly_test_allocate_http_port(int $preferred = 0): int
{
    static $used = [];

    if ($preferred > 0 && !isset($used[$preferred]) && !birkly_test_is_port_in_use($preferred)) {
        $used[$preferred] = true;
        return $preferred;
    }

    $seed = getmypid() + (int) (microtime(true) * 1000);
    for ($attempt = 0; $attempt < 60; $attempt++) {
        $port = 18000 + (($seed + $attempt * 37 + random_int(0, 999)) % 4000);
        if (isset($used[$port]) || birkly_test_is_port_in_use($port)) {
            continue;
        }
        $used[$port] = true;
        return $port;
    }

    fwrite(STDERR, "http_test_helpers: could not allocate free port\n");
    exit(1);
}

/**
 * @param array<string, string> $extra
 * @return array<string, string>
 */
function birkly_test_proc_environment(array $extra = []): array
{
    $env = [];
    foreach ($_ENV as $key => $value) {
        if (is_string($key) && is_string($value)) {
            $env[$key] = $value;
        }
    }
    foreach ($_SERVER as $key => $value) {
        if (is_string($key) && is_string($value) && !isset($env[$key])) {
            $env[$key] = $value;
        }
    }
    foreach ($extra as $key => $value) {
        $env[$key] = (string) $value;
    }

    return $env;
}

/**
 * @param array{env?:array<string,string>} $options
 */
function birkly_test_http_server_start(string $root, int $port, array $options = []): int
{
    $port = birkly_test_allocate_http_port($port);
    $GLOBALS['__birkly_test_http_server_port'] = $port;

    $cmd = sprintf(
        'php -S 127.0.0.1:%d -t %s %s/server-router.php',
        $port,
        escapeshellarg($root),
        escapeshellarg($root)
    );
    $spec = [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']];
    $env = birkly_test_proc_environment($options['env'] ?? []);
    $proc = proc_open($cmd, $spec, $pipes, $root, $env);
    if (!is_resource($proc)) {
        fwrite(STDERR, "http_test_helpers: failed to start PHP server\n");
        exit(1);
    }

    if (is_resource($pipes[0] ?? null)) {
        fclose($pipes[0]);
    }

    $status = proc_get_status($proc);
    $pid = (int) ($status['pid'] ?? 0);
    $readyUrl = "http://127.0.0.1:$port/api/public.php?action=list_collections";

    for ($i = 0; $i < 40; $i++) {
        $status = proc_get_status($proc);
        if (!$status['running']) {
            break;
        }
        $code = birkly_test_http_status_only($readyUrl);
        if ($code > 0) {
            $status = proc_get_status($proc);
            if ($status['running']) {
                return $pid;
            }
            fwrite(STDERR, "http_test_helpers: server process died after binding port $port\n");
            exit(1);
        }
        usleep(150000);
    }

    proc_terminate($proc);
    fwrite(STDERR, "http_test_helpers: server did not become ready on port $port\n");
    exit(1);
}

function birkly_test_http_server_stop(int $pid): void
{
    if ($pid <= 0 || !function_exists('posix_kill')) {
        return;
    }

    @posix_kill($pid, SIGTERM);
    usleep(150000);
    if (@posix_kill($pid, 0)) {
        @posix_kill($pid, SIGKILL);
        usleep(100000);
    }
}

function birkly_test_http_server_port(): int
{
    return (int) ($GLOBALS['__birkly_test_http_server_port'] ?? 0);
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
