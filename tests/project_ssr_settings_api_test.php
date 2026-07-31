<?php
/**
 * Project render settings API tests (P51-M3) — V-SET-03/04/05/07.
 *
 * Plain PHP assert script (mirrors tests/project_ssr_test.php style).
 * Run: php tests/project_ssr_settings_api_test.php
 *
 * Covers:
 *   - Validation whitelist for render_mode / hosting_type (rejects bad values)
 *   - ssr_cache_ttl clamp (0..86400)
 *   - V-SET-03 persist TTL to settings/project.json
 *   - V-SET-04 manual invalidate clears the SSR cache
 *   - V-SET-07 non-admin (unauthenticated) rejected by the admin API
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/project_ssr.php';

$failures = 0;
$total = 0;

function assert_true(string $name, bool $ok, string $detail = ''): void
{
    global $failures, $total;
    $total++;
    echo '  ' . $name . ': ' . ($ok ? "PASS\n" : "FAIL\n");
    if (!$ok) {
        $failures++;
        if ($detail !== '') {
            echo '    ' . $detail . "\n";
        }
    }
}

$root = dirname(__DIR__);
$projectSettingsFile = $root . '/settings/project.json';
$projectBackup = is_readable($projectSettingsFile) ? file_get_contents($projectSettingsFile) : null;

try {
    echo "\n[V-SET validation] render_mode / hosting_type whitelist + ttl clamp\n";

    $ok = birkly_project_ssr_validate_render_settings([
        'render_mode' => 'server',
        'hosting_type' => 'external',
        'ssr_cache_ttl' => 120,
    ]);
    assert_true('valid payload has no error', $ok['error'] === null, json_encode($ok));
    assert_true('valid payload marks ssr_affected', $ok['ssr_affected'] === true);
    assert_true('valid render_mode kept', ($ok['patch']['render_mode'] ?? '') === 'server');
    assert_true('valid hosting_type kept', ($ok['patch']['hosting_type'] ?? '') === 'external');
    assert_true('valid ttl kept', ($ok['patch']['ssr_cache_ttl'] ?? -1) === 120);

    $bad = birkly_project_ssr_validate_render_settings(['render_mode' => 'bogus']);
    assert_true('bad render_mode rejected', $bad['error'] === 'Invalid render_mode', json_encode($bad));
    assert_true('bad render_mode yields empty patch', $bad['patch'] === []);

    $badHost = birkly_project_ssr_validate_render_settings(['hosting_type' => 'aws']);
    assert_true('bad hosting_type rejected', $badHost['error'] === 'Invalid hosting_type');

    $clampHigh = birkly_project_ssr_validate_render_settings(['ssr_cache_ttl' => 999999]);
    assert_true('ttl clamped to 86400', ($clampHigh['patch']['ssr_cache_ttl'] ?? -1) === 86400);

    $clampLow = birkly_project_ssr_validate_render_settings(['ssr_cache_ttl' => -50]);
    assert_true('ttl clamped to 0', ($clampLow['patch']['ssr_cache_ttl'] ?? -1) === 0);

    $ttlZero = birkly_project_ssr_validate_render_settings(['ssr_cache_ttl' => 0]);
    assert_true('ttl 0 accepted (no cache)', ($ttlZero['patch']['ssr_cache_ttl'] ?? -1) === 0);

    $badTtl = birkly_project_ssr_validate_render_settings(['ssr_cache_ttl' => 'abc']);
    assert_true('non-numeric ttl rejected', $badTtl['error'] === 'Invalid ssr_cache_ttl');

    $empty = birkly_project_ssr_validate_render_settings(['show_visit_button' => true]);
    assert_true('unrelated keys ignored (empty patch)', $empty['patch'] === [] && $empty['error'] === null);
    assert_true('unrelated keys do not affect ssr', $empty['ssr_affected'] === false);

    echo "\n[V-SET-03] persist ttl + render settings to project.json\n";

    // Simulate the API save: validated patch merged onto settings/project.json.
    $patch = birkly_project_ssr_validate_render_settings([
        'render_mode' => 'auto',
        'hosting_type' => 'birkly',
        'ssr_cache_ttl' => 300,
    ])['patch'];
    if (!is_dir(dirname($projectSettingsFile))) {
        @mkdir(dirname($projectSettingsFile), 0755, true);
    }
    file_put_contents($projectSettingsFile, json_encode($patch, JSON_PRETTY_PRINT));

    $persisted = birkly_project_ssr_settings();
    assert_true('persisted render_mode=auto', $persisted['render_mode'] === 'auto');
    assert_true('persisted hosting_type=birkly', $persisted['hosting_type'] === 'birkly');
    assert_true('persisted ttl=300', $persisted['ssr_cache_ttl'] === 300);

    echo "\n[V-SET-04] manual invalidate clears SSR cache\n";
    birkly_project_ssr_invalidate_all();
    $key = birkly_project_ssr_cache_key('/index.html', [], 'en', 'revset04');
    birkly_project_ssr_cache_put($key, '<html>cached</html>', 300, '/index.html', 'revset04');
    assert_true('cache primed', birkly_project_ssr_cache_get($key, 300) === '<html>cached</html>');
    birkly_project_ssr_invalidate_all();
    assert_true('invalidate clears cached entry', birkly_project_ssr_cache_get($key, 300) === null);

    echo "\n[V-SET-07] non-admin (unauthenticated) rejected\n";
    $httpResult = ssr_settings_http_probe($root);
    if ($httpResult === null) {
        echo "  non-admin rejection: SKIP (could not start php dev server)\n";
    } else {
        assert_true('unauthenticated save_settings → 401', $httpResult['save_status'] === 401, 'got ' . $httpResult['save_status']);
        assert_true('unauthenticated invalidate → 401', $httpResult['invalidate_status'] === 401, 'got ' . $httpResult['invalidate_status']);
        assert_true('rejection body is error status', strpos($httpResult['save_body'], '"status":"error"') !== false, $httpResult['save_body']);
    }
} finally {
    birkly_project_ssr_invalidate_all();
    if ($projectBackup !== null) {
        file_put_contents($projectSettingsFile, $projectBackup);
    } elseif (is_file($projectSettingsFile)) {
        unlink($projectSettingsFile);
    }
}

/**
 * Spin up a short-lived PHP dev server and probe the admin API without a
 * session cookie. Returns null if the server can't be started (test skips).
 *
 * @return array{save_status:int,save_body:string,invalidate_status:int}|null
 */
function ssr_settings_http_probe(string $root): ?array
{
    if (!function_exists('proc_open')) {
        return null;
    }

    $phpBin = PHP_BINARY ?: 'php';
    $host = '127.0.0.1';
    $port = 8100 + random_int(0, 400);
    $cmd = escapeshellarg($phpBin) . ' -S ' . $host . ':' . $port . ' ' . escapeshellarg('server-router.php');

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $proc = @proc_open($cmd, $descriptors, $pipes, $root);
    if (!is_resource($proc)) {
        return null;
    }

    $base = 'http://' . $host . ':' . $port;
    $up = false;
    for ($i = 0; $i < 40; $i++) {
        usleep(100000); // 100ms
        $conn = @fsockopen($host, $port, $errno, $errstr, 0.2);
        if ($conn) {
            fclose($conn);
            $up = true;
            break;
        }
    }

    $result = null;
    if ($up) {
        $save = ssr_settings_http_post($base . '/api/project_site.php?action=save_settings', [
            'action' => 'save_settings',
            'render_mode' => 'server',
        ]);
        $invalidate = ssr_settings_http_post($base . '/api/project_site.php?action=invalidate_ssr_cache', [
            'action' => 'invalidate_ssr_cache',
        ]);
        if ($save !== null && $invalidate !== null) {
            $result = [
                'save_status' => $save['status'],
                'save_body' => $save['body'],
                'invalidate_status' => $invalidate['status'],
            ];
        }
    }

    foreach ($pipes as $pipe) {
        if (is_resource($pipe)) {
            fclose($pipe);
        }
    }
    proc_terminate($proc);
    proc_close($proc);

    return $result;
}

/**
 * @param array<string,mixed> $body
 * @return array{status:int,body:string}|null
 */
function ssr_settings_http_post(string $url, array $body): ?array
{
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => json_encode($body),
            'ignore_errors' => true,
            'timeout' => 5,
        ],
    ]);
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        return null;
    }
    $status = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $status = (int) $m[1];
    }
    return ['status' => $status, 'body' => $response];
}

echo "\nproject_ssr_settings_api_test: " . ($failures === 0 ? "PASS\n" : "FAIL ($failures/$total)\n");
exit($failures === 0 ? 0 : 1);
