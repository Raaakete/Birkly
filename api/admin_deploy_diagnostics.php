<?php
/**
 * Admin deploy / install diagnostics (P88.1).
 *
 * Read: GET + read_settings. Mutations: POST + manage_settings + CSRF.
 * Public health remains api/deploy_revision.php only.
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../core/cors.php';
require_once __DIR__ . '/../core/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/log.php';
require_once __DIR__ . '/../core/deploy_diagnostics.php';

set_cors_headers('admin');
haru_secure_session_start();

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput ?: '[]', true);
if (!is_array($input)) {
    $input = [];
}

$action = trim((string) ($_GET['action'] ?? $input['action'] ?? $_POST['action'] ?? 'status'));
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

/** @var list<string> */
$readActions = ['status', 'plugins', 'probe_plugin'];
/** @var list<string> */
$writeActions = ['sync_bundled_plugin', 'repair_plugins', 'plugin_dev_sync'];

function birkly_admin_deploy_json(array $payload, int $httpCode = 200): void
{
    if ($httpCode !== 200) {
        http_response_code($httpCode);
    }
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === '' || !in_array($action, array_merge($readActions, $writeActions), true)) {
    birkly_admin_deploy_json(['status' => 'error', 'message' => 'Unknown action'], 400);
}

if (in_array($action, $writeActions, true)) {
    if ($method !== 'POST') {
        birkly_admin_deploy_json(['status' => 'error', 'message' => 'POST required'], 405);
    }
    requireAuth('manage_settings');
    $csrf = (string) ($input['_csrf'] ?? $_POST['_csrf'] ?? '');
    if (!validate_csrf_token($csrf)) {
        log_security_event('permission_denied', ['action' => 'admin_deploy_' . $action, 'reason' => 'invalid CSRF']);
        birkly_admin_deploy_json(['status' => 'error', 'message' => 'Invalid CSRF token'], 403);
    }
} else {
    requireAuth('read_settings');
}

if ($action === 'status') {
    birkly_admin_deploy_json(['status' => 'ok', 'data' => birkly_deploy_verbose_payload()]);
}

if ($action === 'plugins') {
    $payload = birkly_deploy_verbose_payload();
    $payload['plugins'] = birkly_deploy_plugin_diagnostics()['plugins'];
    birkly_admin_deploy_json(['status' => 'ok', 'data' => $payload]);
}

if ($action === 'probe_plugin') {
    $pluginId = trim((string) ($_GET['plugin_id'] ?? $input['plugin_id'] ?? 'automation'));
    $result = birkly_deploy_probe_plugin($pluginId);
    $code = ($result['message'] ?? '') === 'invalid plugin_id' ? 400 : 200;
    birkly_admin_deploy_json($result, $code);
}

if ($action === 'sync_bundled_plugin') {
    $pluginId = trim((string) ($input['plugin_id'] ?? 'automation'));
    $result = birkly_deploy_sync_bundled_plugin($pluginId);
    if ($result['status'] === 'error' && ($result['message'] ?? '') === 'bundled plugin not found') {
        birkly_admin_deploy_json($result, 404);
    }
    if ($result['status'] === 'error') {
        birkly_admin_deploy_json($result, 400);
    }
    birkly_admin_deploy_json($result);
}

if ($action === 'repair_plugins') {
    $mode = trim((string) ($input['mode'] ?? 'deactivate_broken'));
    birkly_admin_deploy_json(birkly_deploy_repair_plugins($mode));
}

if ($action === 'plugin_dev_sync') {
    $result = birkly_deploy_plugin_dev_sync();
    if ($result['status'] === 'error') {
        birkly_admin_deploy_json($result, 404);
    }
    birkly_admin_deploy_json($result);
}

birkly_admin_deploy_json(['status' => 'error', 'message' => 'Unhandled action'], 500);
