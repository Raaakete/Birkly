<?php
/**
 * AI agents admin API (P5-M3).
 */

declare(strict_types=1);

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

header('Content-Type: application/json');

require_once 'auth.php';
require_once 'validate.php';
require_once __DIR__ . '/../core/csrf.php';
require_once 'log.php';
require_once __DIR__ . '/../core/ai_agents.php';

requireAuth('manage_settings');

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_GET;
}

$action = $input['action'] ?? $_GET['action'] ?? 'list';

if ($action === 'list') {
    $agents = array_map('birkly_ai_agents_with_endpoint', birkly_ai_agents_list());
    echo json_encode(['status' => 'ok', 'data' => ['agents' => $agents]]);
    exit;
}

if ($action === 'roles') {
    echo json_encode(['status' => 'ok', 'data' => ['roles' => birkly_ai_agents_valid_roles()]]);
    exit;
}

$csrf = $input['_csrf'] ?? '';
if (!validate_csrf_token($csrf)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
    exit;
}

$userId = $_SESSION['user']['id'] ?? '';

if ($action === 'create') {
    try {
        $agent = birkly_ai_agents_create($input, $userId);
        log_security_event('ai_agent_created', ['agent_id' => $agent['id'], 'created_by' => $userId]);
        echo json_encode([
            'status' => 'ok',
            'message' => 'AI agent created',
            'data' => ['agent' => birkly_ai_agents_with_endpoint($agent)],
        ]);
    } catch (Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'update') {
    $id = trim((string) ($input['id'] ?? ''));
    if ($id === '') {
        echo json_encode(['status' => 'error', 'message' => 'Agent ID required']);
        exit;
    }
    try {
        $agent = birkly_ai_agents_update($id, $input);
        if ($agent === null) {
            echo json_encode(['status' => 'error', 'message' => 'Agent not found']);
            exit;
        }
        echo json_encode([
            'status' => 'ok',
            'message' => 'AI agent updated',
            'data' => ['agent' => birkly_ai_agents_with_endpoint($agent)],
        ]);
    } catch (Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'revoke') {
    $id = trim((string) ($input['id'] ?? ''));
    if ($id === '') {
        echo json_encode(['status' => 'error', 'message' => 'Agent ID required']);
        exit;
    }
    $ok = birkly_ai_agents_revoke($id);
    if (!$ok) {
        echo json_encode(['status' => 'error', 'message' => 'Agent not found']);
        exit;
    }
    log_security_event('ai_agent_revoked', ['agent_id' => $id, 'revoked_by' => $userId]);
    echo json_encode(['status' => 'ok', 'message' => 'AI agent revoked']);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
