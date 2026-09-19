<?php
/**
 * Admin API — desktop MCP pairing (local Claude Desktop / Cursor).
 */

declare(strict_types=1);

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

header('Content-Type: application/json');

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../core/csrf.php';
require_once __DIR__ . '/../core/mcp_desktop.php';
require_once __DIR__ . '/../core/ai_agents.php';

haru_secure_session_start();

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$currentUser = $_SESSION['user'];
$userId = (string) ($currentUser['id'] ?? '');

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_GET;
}

$action = (string) ($input['action'] ?? $_GET['action'] ?? 'status');

if ($action === 'status') {
    $data = birkly_mcp_desktop_status_for_user($userId);
    $data['install_targets'] = array_combine(
        birkly_mcp_desktop_valid_targets(),
        array_map(
            static fn (string $t) => birkly_mcp_desktop_install_target_meta($t),
            birkly_mcp_desktop_valid_targets()
        )
    );
    echo json_encode([
        'status' => 'ok',
        'data' => $data,
    ]);
    exit;
}

if ($action === 'download_helper') {
    $status = birkly_mcp_desktop_status_for_user($userId);
    if (!($status['paired'] ?? false)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Pair this computer first']);
        exit;
    }
    $target = (string) ($input['target'] ?? $_GET['target'] ?? 'claude');
    if (!birkly_mcp_desktop_is_valid_target($target)) {
        $target = 'claude';
    }
    $os = (string) ($input['os'] ?? $_GET['os'] ?? 'mac');
    $credPath = (string) ($status['credentials_path'] ?? '');
    if ($credPath === '' || !is_file($credPath)) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Credentials unavailable']);
        exit;
    }
    $downloads = $status['helper_downloads'] ?? birkly_mcp_desktop_helper_download_names();
    $osKey = ($os === 'win' || $os === 'windows') ? 'win' : 'mac';
    $filename = (string) ($downloads[$target . '_' . $osKey] ?? 'Connect-Birkly.bat');
    try {
        if ($os === 'win' || $os === 'windows') {
            $body = birkly_mcp_desktop_windows_bat_script($credPath, $target);
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            echo $body;
            exit;
        }
        $body = birkly_mcp_desktop_mac_command_script($credPath, $target);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $body;
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

if (!validate_csrf_token((string) ($input['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
    exit;
}

if ($action === 'pair') {
    try {
        $agentId = (string) ($input['agent_id'] ?? BIRKLY_AI_AGENT_PERSONAL_ID);
        $result = birkly_mcp_desktop_pair_user($currentUser, $agentId);
        echo json_encode(['status' => 'ok', 'data' => $result]);
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'revoke') {
    birkly_mcp_desktop_revoke_credentials($userId);
    echo json_encode(['status' => 'ok', 'data' => ['revoked' => true]]);
    exit;
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
