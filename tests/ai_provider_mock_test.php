<?php
/**
 * P4.1-M4 — Mock HTTP provider harness for outbound BYOAI validation.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/http_test_helpers.php';
require_once dirname(__DIR__) . '/api/ai_helpers.php';

test_reset_failures();
echo "AI provider mock harness tests\n\n";

$root = dirname(__DIR__);
$mockPort = (int) (getenv('AI_MOCK_PROVIDER_PORT') ?: 18088);
$cmsPort = (int) (getenv('AI_MOCK_CMS_PORT') ?: 18089);
$mockBase = "http://127.0.0.1:$mockPort";
$cmsBase = "http://127.0.0.1:$cmsPort";
$mockRouter = __DIR__ . '/fixtures/mock_ai_provider_router.php';
$settingsFile = $root . '/settings/security.json';
$settingsBackup = sys_get_temp_dir() . '/birkly-security-' . getmypid() . '.json';
$usersDir = $root . '/users';
$backupUsers = sys_get_temp_dir() . '/birkly-ai-mock-users-' . getmypid();
$cookieFile = sys_get_temp_dir() . '/birkly-ai-mock-cookies-' . getmypid() . '.txt';
$testEmail = 'ai-mock-e2e-' . getmypid() . '@birkly.test';
$mockUrl = $mockBase . '/v1/chat/completions';

$mockPid = birkly_test_mock_server_start($mockRouter, $mockPort);
$cmsPid = birkly_test_http_server_start($root, $cmsPort);

try {
    if (is_file($settingsFile)) {
        copy($settingsFile, $settingsBackup);
        unlink($settingsFile);
    }

    $good = validateProviderConnection('custom', 'valid-mock-key', $mockUrl);
    test_assert('mock provider accepts valid key', ($good['valid'] ?? false) === true, $good['message'] ?? '');

    $bad = validateProviderConnection('custom', 'invalid-mock-key', $mockUrl);
    test_assert('mock provider rejects invalid key', ($bad['valid'] ?? true) === false, $bad['message'] ?? '');

    birkly_test_backup_directory($usersDir, $backupUsers);
    birkly_test_clear_json_files($usersDir);

    birkly_test_http_request($cmsBase . '/api/onboarding.php?action=create_first_user', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/json'],
        'body' => json_encode([
            'name' => 'AI Mock E2E',
            'email' => $testEmail,
            'password' => 'SecurePass123!',
        ]),
        'cookie_file' => $cookieFile,
    ]);

    birkly_test_http_request($cmsBase . '/api/onboarding.php?action=skip', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/json'],
        'body' => '{}',
        'cookie_file' => $cookieFile,
    ]);

    $csrf = birkly_test_json_decode(
        birkly_test_http_request($cmsBase . '/api/csrf.php', [
            'cookie_file' => $cookieFile,
            'headers' => ['Referer: ' . $cmsBase . '/admin/'],
        ])['body']
    );
    $csrfToken = $csrf['data']['csrf_token'] ?? '';
    test_assert('csrf available for mock provider flow', $csrfToken !== '');

    birkly_test_http_request($cmsBase . '/api/ai_connections.php?action=providers', [
        'cookie_file' => $cookieFile,
    ]);
    test_assert('encryption bootstrap creates security.json', is_file($settingsFile));
    $security = json_decode((string) file_get_contents($settingsFile), true);
    test_assert('encryption_key is non-empty', !empty($security['encryption_key']));

    $testAction = birkly_test_http_request($cmsBase . '/api/ai_connections.php', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/json'],
        'body' => json_encode([
            'action' => 'test',
            'provider' => 'custom',
            'api_key' => 'valid-mock-key',
            'api_url' => $mockUrl,
            '_csrf' => $csrfToken,
        ]),
        'cookie_file' => $cookieFile,
    ]);
    $testData = birkly_test_json_decode($testAction['body']);
    test_assert('wizard test action passes against mock', ($testData['status'] ?? '') === 'ok', $testAction['body']);

    $create = birkly_test_http_request($cmsBase . '/api/ai_connections.php', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/json'],
        'body' => json_encode([
            'action' => 'create',
            'provider' => 'custom',
            'name' => 'Mock Provider',
            'api_key' => 'valid-mock-key',
            'api_url' => $mockUrl,
            'model' => 'mock-model',
            'role' => 'admin',
            'require_approval' => true,
            '_csrf' => $csrfToken,
        ]),
        'cookie_file' => $cookieFile,
    ]);
    $createData = birkly_test_json_decode($create['body']);
    test_assert('custom mock connection created', ($createData['status'] ?? '') === 'ok', $create['body']);
    $connectionId = $createData['data']['connection']['id'] ?? '';
    test_assert('mock connection id returned', $connectionId !== '');

    $list = birkly_test_json_decode(
        birkly_test_http_request($cmsBase . '/api/ai_connections.php?action=list', [
            'cookie_file' => $cookieFile,
        ])['body']
    );
    $ids = array_column($list['data']['connections'] ?? [], 'id');
    test_assert('mock connection appears in list after create', in_array($connectionId, $ids, true));

    $delete = birkly_test_http_request($cmsBase . '/api/ai_connections.php', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/json'],
        'body' => json_encode([
            'action' => 'delete',
            'id' => $connectionId,
            '_csrf' => $csrfToken,
        ]),
        'cookie_file' => $cookieFile,
    ]);
    $deleteData = birkly_test_json_decode($delete['body']);
    test_assert('mock connection deleted', ($deleteData['status'] ?? '') === 'ok', $delete['body']);

    $listAfter = birkly_test_json_decode(
        birkly_test_http_request($cmsBase . '/api/ai_connections.php?action=list', [
            'cookie_file' => $cookieFile,
        ])['body']
    );
    $idsAfter = array_column($listAfter['data']['connections'] ?? [], 'id');
    test_assert('mock connection absent from list after delete', !in_array($connectionId, $idsAfter, true));
} finally {
    birkly_test_restore_directory($usersDir, $backupUsers);
    birkly_test_remove_directory($backupUsers);
    @unlink($cookieFile);

    if (is_file($settingsBackup)) {
        copy($settingsBackup, $settingsFile);
        unlink($settingsBackup);
    } elseif (is_file($settingsFile)) {
        unlink($settingsFile);
    }

    birkly_test_http_server_stop($cmsPid);
    birkly_test_http_server_stop($mockPid);
}

exit(test_finish('ai_provider_mock_test'));

/**
 * Start a dedicated PHP built-in server using a router script.
 */
function birkly_test_mock_server_start(string $routerScript, int $port): int
{
    $cmd = sprintf(
        'php -S 127.0.0.1:%d %s',
        $port,
        escapeshellarg($routerScript)
    );
    $spec = [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']];
    $proc = proc_open($cmd, $spec, $pipes);
    if (!is_resource($proc)) {
        fwrite(STDERR, "ai_provider_mock_test: failed to start mock server\n");
        exit(1);
    }

    $status = proc_get_status($proc);
    $pid = (int) ($status['pid'] ?? 0);
    $readyUrl = "http://127.0.0.1:$port/v1/chat/completions";

    for ($i = 0; $i < 40; $i++) {
        $probe = birkly_test_http_request($readyUrl, [
            'method' => 'POST',
            'headers' => ['Content-Type: application/json', 'Authorization: Bearer valid-mock-key'],
            'body' => json_encode(['model' => 'mock', 'messages' => [['role' => 'user', 'content' => 'ping']]]),
        ]);
        if ($probe['code'] === 200) {
            return $pid;
        }
        usleep(150000);
    }

    proc_terminate($proc);
    fwrite(STDERR, "ai_provider_mock_test: mock server did not become ready on port $port\n");
    exit(1);
}
