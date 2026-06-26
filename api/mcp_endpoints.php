<?php
/**
 * MCP endpoint profiles API (P4-M3) — inbound MCP only.
 */

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

require_once __DIR__ . '/session.php';
haru_secure_session_start();

header('Content-Type: application/json');

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/validate.php';
require_once __DIR__ . '/../core/csrf.php';
require_once __DIR__ . '/log.php';
require_once __DIR__ . '/../core/mcp_endpoints.php';

$input = sanitize_input(json_decode(file_get_contents('php://input'), true) ?? []);
$action = $input['action'] ?? $_GET['action'] ?? '';

requireAuth('manage_settings');

if ($action === 'list') {
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'endpoints' => birkly_mcp_list_endpoints(),
            'server_url' => birkly_ai_mcp_server_url(),
            'legacy_dual_resolve_until' => birkly_mcp_load_store()['legacy_dual_resolve_until'] ?? null,
        ],
    ]);
    exit;
}

if ($action === 'get') {
    $id = $_GET['id'] ?? $input['id'] ?? '';
    $endpoint = birkly_mcp_find_endpoint($id);
    if (!$endpoint) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Endpoint not found']);
        exit;
    }
    echo json_encode(['status' => 'ok', 'data' => ['endpoint' => birkly_mcp_scrub_endpoint($endpoint)]]);
    exit;
}

if ($action === 'get_key') {
    $id = $_GET['id'] ?? $input['id'] ?? '';
    $endpoint = birkly_mcp_find_endpoint($id);
    if (!$endpoint) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Endpoint not found']);
        exit;
    }
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'mcp_api_key' => $endpoint['mcp_api_key'] ?? '',
            'server_url' => birkly_ai_mcp_server_url(),
        ],
    ]);
    exit;
}

$csrf = $input['_csrf'] ?? '';
if (!validate_csrf_token($csrf)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
    exit;
}

try {
    if ($action === 'create') {
        $endpoint = birkly_mcp_create_endpoint($input, $_SESSION['user']['id'] ?? 'unknown');
        log_security_event('mcp_endpoint_created', ['endpoint_id' => $endpoint['id']]);
        echo json_encode([
            'status' => 'ok',
            'message' => 'MCP endpoint created',
            'data' => [
                'endpoint' => birkly_mcp_scrub_endpoint($endpoint),
                'mcp_api_key' => $endpoint['mcp_api_key'],
                'server_url' => birkly_ai_mcp_server_url(),
            ],
        ]);
        exit;
    }

    if ($action === 'update') {
        $id = $input['id'] ?? '';
        $endpoint = birkly_mcp_update_endpoint($id, $input);
        if (!$endpoint) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Endpoint not found']);
            exit;
        }
        echo json_encode(['status' => 'ok', 'data' => ['endpoint' => birkly_mcp_scrub_endpoint($endpoint)]]);
        exit;
    }

    if ($action === 'rotate') {
        $id = $input['id'] ?? '';
        $endpoint = birkly_mcp_rotate_endpoint_key($id);
        if (!$endpoint) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Endpoint not found']);
            exit;
        }
        log_security_event('mcp_endpoint_rotated', ['endpoint_id' => $id]);
        echo json_encode([
            'status' => 'ok',
            'data' => [
                'endpoint' => birkly_mcp_scrub_endpoint($endpoint),
                'mcp_api_key' => $endpoint['mcp_api_key'],
                'previous_key_expires_at' => $endpoint['previous_key_expires_at'],
            ],
        ]);
        exit;
    }

    if ($action === 'revoke') {
        $id = $input['id'] ?? '';
        if (!birkly_mcp_revoke_endpoint($id)) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Endpoint not found']);
            exit;
        }
        log_security_event('mcp_endpoint_revoked', ['endpoint_id' => $id]);
        echo json_encode(['status' => 'ok', 'message' => 'Endpoint revoked']);
        exit;
    }
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
