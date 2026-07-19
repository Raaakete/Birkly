<?php
/**
 * P4-M2-T3 — AI connection list/delete regression (stale connection after delete).
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/http_test_helpers.php';

test_reset_failures();
echo "AI chat connection regression tests\n\n";

$root = dirname(__DIR__);
$port = (int) (getenv('AI_CONNECTION_TEST_PORT') ?: 18084);
$usersDir = $root . '/users';
$backupUsers = sys_get_temp_dir() . '/birkly-ai-users-' . getmypid();
$cookieFile = sys_get_temp_dir() . '/birkly-ai-cookies-' . getmypid() . '.txt';
$testEmail = 'ai-conn-e2e-' . getmypid() . '@birkly.test';

$serverPid = birkly_test_http_server_start($root, $port);
$port = birkly_test_http_server_port();
$base = "http://127.0.0.1:$port";

try {
    birkly_test_backup_directory($usersDir, $backupUsers);
    birkly_test_clear_json_files($usersDir);

    birkly_test_http_request($base . '/api/onboarding.php?action=create_first_user', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/json'],
        'body' => json_encode([
            'name' => 'AI Conn E2E',
            'email' => $testEmail,
            'password' => 'SecurePass123!',
        ]),
        'cookie_file' => $cookieFile,
    ]);

    birkly_test_http_request($base . '/api/onboarding.php?action=skip', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/json'],
        'body' => '{}',
        'cookie_file' => $cookieFile,
    ]);

    $csrf = birkly_test_json_decode(
        birkly_test_http_request($base . '/api/csrf.php', [
            'cookie_file' => $cookieFile,
            'headers' => ['Referer: ' . $base . '/admin/'],
        ])['body']
    );
    $csrfToken = $csrf['data']['csrf_token'] ?? '';
    test_assert('csrf available for AI connection CRUD', $csrfToken !== '');

    $create = birkly_test_http_request($base . '/api/ai_connections.php', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/json'],
        'body' => json_encode([
            'action' => 'create',
            'provider' => 'ollama',
            'name' => 'Ollama Stale Test',
            'role' => 'admin',
            'model' => 'llama3',
            'require_approval' => true,
            '_csrf' => $csrfToken,
        ]),
        'cookie_file' => $cookieFile,
    ]);
    $createData = birkly_test_json_decode($create['body']);
    test_assert('ollama connection created', ($createData['status'] ?? '') === 'ok', $create['body']);
    $connectionId = $createData['data']['connection']['id'] ?? '';
    test_assert('connection id returned', $connectionId !== '');

    $list = birkly_test_json_decode(
        birkly_test_http_request($base . '/api/ai_connections.php?action=list', [
            'cookie_file' => $cookieFile,
        ])['body']
    );
    $ids = array_column($list['data']['connections'] ?? [], 'id');
    test_assert('connection appears in list', in_array($connectionId, $ids, true));

    $delete = birkly_test_http_request($base . '/api/ai_connections.php', [
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
    test_assert('connection deleted', ($deleteData['status'] ?? '') === 'ok', $delete['body']);

    $listAfter = birkly_test_json_decode(
        birkly_test_http_request($base . '/api/ai_connections.php?action=list', [
            'cookie_file' => $cookieFile,
        ])['body']
    );
    $idsAfter = array_column($listAfter['data']['connections'] ?? [], 'id');
    test_assert('deleted connection absent from list', !in_array($connectionId, $idsAfter, true));

    $getDeleted = birkly_test_http_request(
        $base . '/api/ai_connections.php?action=get&id=' . urlencode($connectionId),
        ['cookie_file' => $cookieFile]
    );
    $getDeletedData = birkly_test_json_decode($getDeleted['body']);
    test_assert(
        'get deleted connection fails',
        ($getDeletedData['status'] ?? '') !== 'ok' || $getDeleted['code'] >= 400
    );

    $chat = birkly_test_http_request($base . '/api/ai_chat.php', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/json'],
        'body' => json_encode([
            'action' => 'chat',
            'ai_connection_id' => $connectionId,
            'message' => 'hello',
            'conversation_id' => 'conv_test_' . getmypid(),
            '_csrf' => $csrfToken,
        ]),
        'cookie_file' => $cookieFile,
    ]);
    $chatData = birkly_test_json_decode($chat['body']);
    test_assert(
        'chat with deleted connection rejected',
        ($chatData['status'] ?? '') !== 'ok' || $chat['code'] >= 400,
        $chat['body']
    );
} finally {
    birkly_test_restore_directory($usersDir, $backupUsers);
    birkly_test_remove_directory($backupUsers);
    @unlink($cookieFile);
    birkly_test_http_server_stop($serverPid);
}

exit(test_finish('ai_chat_connection_test'));
