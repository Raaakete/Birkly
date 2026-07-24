<?php
/**
 * AI agents admin API (P5-M3 / P5.1).
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

require_once __DIR__ . '/session.php';
haru_secure_session_start();

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$currentUser = $_SESSION['user'];
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_GET;
}

$action = $input['action'] ?? $_GET['action'] ?? 'list';

birkly_ai_agents_ensure_personal();

if ($action === 'list') {
    $personal = birkly_ai_agents_find_personal();
    $personalOut = $personal ? birkly_ai_agents_with_endpoint($personal) : null;
    $serviceAgents = array_map(
        'birkly_ai_agents_with_endpoint',
        birkly_ai_agents_list_service_for_user($currentUser)
    );

    echo json_encode([
        'status' => 'ok',
        'data' => [
            'personal_agent' => $personalOut,
            'service_agents' => $serviceAgents,
            'agents' => array_values(array_filter(array_merge(
                $personalOut ? [$personalOut] : [],
                $serviceAgents
            ))),
            'is_admin' => birkly_ai_agents_is_admin($currentUser),
        ],
    ]);
    exit;
}

if ($action === 'roles') {
    echo json_encode(['status' => 'ok', 'data' => ['roles' => birkly_ai_agents_valid_roles()]]);
    exit;
}

if ($action === 'users') {
    if (!birkly_ai_agents_is_admin($currentUser)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
        exit;
    }

    require_once __DIR__ . '/../core/birkly_users.php';
    $userList = [];
    foreach (birkly_list_human_user_records(true) as $user) {
        $userList[] = [
            'id' => $user['id'] ?? '',
            'email' => $user['email'] ?? '',
            'role' => $user['role'] ?? '',
            'name' => $user['name'] ?? $user['email'] ?? '',
        ];
    }

    echo json_encode(['status' => 'ok', 'data' => ['users' => $userList]]);
    exit;
}

$csrf = $input['_csrf'] ?? '';
if (!validate_csrf_token($csrf)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
    exit;
}

$userId = $currentUser['id'] ?? '';

if ($action === 'create') {
    if (!birkly_ai_agents_is_admin($currentUser)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Only admins can create service agents']);
        exit;
    }

    try {
        $agent = birkly_ai_agents_create($input, $userId);
        log_security_event('ai_agent_created', ['agent_id' => $agent['id'], 'created_by' => $userId]);
        echo json_encode([
            'status' => 'ok',
            'message' => 'Service agent created',
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

    $agent = birkly_ai_agents_find($id);
    if ($agent === null) {
        echo json_encode(['status' => 'error', 'message' => 'Agent not found']);
        exit;
    }

    if ($id === BIRKLY_AI_AGENT_PERSONAL_ID) {
        if (!birkly_ai_agents_is_admin($currentUser)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
            exit;
        }
        $status = (string) ($input['status'] ?? '');
        if ($status !== '' && in_array($status, ['active', 'disabled'], true)) {
            $updated = birkly_ai_agents_set_personal_status($status);
            echo json_encode([
                'status' => 'ok',
                'message' => 'Personal connector updated',
                'data' => ['agent' => $updated ? birkly_ai_agents_with_endpoint($updated) : null],
            ]);
            exit;
        }
        if (array_key_exists('require_approval', $input)) {
            $updated = birkly_ai_agents_update_personal_settings([
                'require_approval' => (bool) $input['require_approval'],
            ]);
            echo json_encode([
                'status' => 'ok',
                'message' => 'Personal connector updated',
                'data' => ['agent' => $updated ? birkly_ai_agents_with_endpoint($updated) : null],
            ]);
            exit;
        }
        echo json_encode(['status' => 'error', 'message' => 'Personal connector can only be enabled, disabled, or have approval settings changed']);
        exit;
    }

    if (!birkly_ai_agents_is_admin($currentUser) && !birkly_ai_user_can_see_agent($agent, $currentUser)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
        exit;
    }

    if (!birkly_ai_agents_is_admin($currentUser)) {
        unset($input['visible_to_roles'], $input['allowed_users'], $input['role']);
    }

    try {
        $updated = birkly_ai_agents_update($id, $input, $currentUser);
        if ($updated === null) {
            echo json_encode(['status' => 'error', 'message' => 'Agent not found']);
            exit;
        }
        echo json_encode([
            'status' => 'ok',
            'message' => 'Service agent updated',
            'data' => ['agent' => birkly_ai_agents_with_endpoint($updated)],
        ]);
    } catch (Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'revoke') {
    if (!birkly_ai_agents_is_admin($currentUser)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Only admins can remove service agents']);
        exit;
    }

    $id = trim((string) ($input['id'] ?? ''));
    if ($id === '') {
        echo json_encode(['status' => 'error', 'message' => 'Agent ID required']);
        exit;
    }

    try {
        $ok = birkly_ai_agents_revoke($id);
        if (!$ok) {
            echo json_encode(['status' => 'error', 'message' => 'Agent not found']);
            exit;
        }
        log_security_event('ai_agent_revoked', ['agent_id' => $id, 'revoked_by' => $userId]);
        echo json_encode(['status' => 'ok', 'message' => 'Service agent removed']);
    } catch (Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
