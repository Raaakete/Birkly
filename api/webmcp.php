<?php
/**
 * WebMCP admin API (P60-M3 / M6).
 * -------------------------------
 * Authenticated get/save for the WebMCP enablement config. Backs the Agent
 * Guide → Tools zone. Public runtime reads the secret-free projection from
 * api/public/webmcp.php instead.
 */

declare(strict_types=1);

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

header('Content-Type: application/json');

require_once 'session.php';
haru_secure_session_start();

require_once 'auth.php';
require_once 'validate.php';
require_once __DIR__ . '/../core/csrf.php';
require_once __DIR__ . '/../core/webmcp.php';

// Same permission as the Agent Guide and Discovery screens (admin/index.php).
requireAuth('read');

$raw = $GLOBALS['__birkly_webmcp_test_raw_body'] ?? file_get_contents('php://input');
if (!is_string($raw)) {
    $raw = '';
}
$input = json_decode($raw ?: '{}', true);
if (!is_array($input)) {
    $input = [];
}
$input = sanitize_input($input);
$action = $input['action'] ?? $_GET['action'] ?? 'get';

/**
 * @deprecated Use birkly_webmcp_admin_catalog() in core/webmcp.php
 */
function birkly_webmcp_api_catalog(array $config): array
{
    return birkly_webmcp_admin_catalog($config);
}

if ($action === 'get') {
    $config = birkly_webmcp_load();
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'config' => $config,
            'catalog' => birkly_webmcp_api_catalog($config),
            'runtime_packs' => birkly_webmcp_api_runtime_packs(),
            'public_forms' => birkly_webmcp_public_form_collections(),
        ],
    ]);
    exit;
}

// Write actions require a valid CSRF token.
$csrf = $input['_csrf'] ?? '';
if (!validate_csrf_token($csrf)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
    exit;
}

if ($action === 'save') {
    $payload = $input['config'] ?? $input;
    if (!is_array($payload)) {
        $payload = [];
    }
    unset($payload['action'], $payload['_csrf']);

    $result = birkly_webmcp_save($payload);
    if (!$result['ok']) {
        echo json_encode(['status' => 'error', 'message' => $result['error'] ?? 'Could not save']);
        exit;
    }

    $config = $result['config'];
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'config' => $config,
            'catalog' => birkly_webmcp_api_catalog($config),
            'runtime_packs' => birkly_webmcp_api_runtime_packs(),
            'public_forms' => birkly_webmcp_public_form_collections(),
        ],
    ]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
