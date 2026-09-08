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

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw ?: '{}', true);
if (!is_array($input)) {
    $input = [];
}
$input = sanitize_input($input);
$action = $input['action'] ?? $_GET['action'] ?? 'get';

/**
 * Attach the catalog metadata so the admin UI can render the tool list without
 * hard-coding it in JS.
 *
 * @return array<int, array<string, mixed>>
 */
function birkly_webmcp_api_catalog(array $config): array
{
    $out = [];
    foreach (birkly_webmcp_tool_catalog() as $tool) {
        $id = $tool['id'];
        $entry = [
            'id' => $id,
            'pack' => $tool['pack'],
            'kind' => $tool['kind'],
            'mutating' => $tool['mutating'],
            'consent' => $tool['consent'],
            'default' => $tool['default'],
            'enabled' => (bool) ($config['tools'][$id] ?? $tool['default']),
        ];
        if (!empty($tool['plugin_id'])) {
            $entry['plugin_id'] = $tool['plugin_id'];
        }
        if (!empty($tool['label'])) {
            $entry['label'] = $tool['label'];
        }
        if (!empty($tool['description'])) {
            $entry['description'] = $tool['description'];
        }
        $out[] = $entry;
    }
    return $out;
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
