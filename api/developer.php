<?php
/**
 * Developer mode API (P61) — gated DX tools.
 */

declare(strict_types=1);

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0');

if (!headers_sent() && !isset($GLOBALS['__HARU_INTERNAL_CALL'])) {
    header('Content-Type: application/json');
}

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../core/csrf.php';
require_once __DIR__ . '/../core/developer_mode.php';

requireAuth('manage_settings');

$rawInput = file_get_contents('php://input');
$input = [];
if ($rawInput !== '') {
    $decoded = json_decode($rawInput, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $input = $decoded;
    }
}

$action = $_GET['action'] ?? $input['action'] ?? '';

if ($action === 'get') {
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'enabled' => birkly_developer_mode_enabled(),
            'settings' => birkly_developer_mode_load(),
        ],
    ]);
    exit;
}

if ($action === 'save') {
    $csrf = $input['_csrf'] ?? $_POST['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    $enabled = !empty($input['enabled']);
    if (!birkly_developer_mode_save($enabled)) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to save developer mode']);
        exit;
    }
    echo json_encode([
        'status' => 'ok',
        'message' => $enabled ? 'Developer mode on' : 'Developer mode off',
        'data' => ['enabled' => birkly_developer_mode_enabled()],
    ]);
    exit;
}

if ($action === 'system_info') {
    if (!birkly_developer_mode_enabled()) {
        echo json_encode(['status' => 'error', 'message' => 'Developer mode is off', 'code' => 'dev_mode_off']);
        exit;
    }
    if (!function_exists('get_all_plugins')) {
        require_once __DIR__ . '/../core/plugins.php';
    }
    echo json_encode(['status' => 'ok', 'data' => birkly_developer_system_info()]);
    exit;
}

if ($action === 'export_template') {
    if (!birkly_developer_mode_enabled()) {
        echo json_encode(['status' => 'error', 'message' => 'Developer mode is off', 'code' => 'dev_mode_off']);
        exit;
    }
    $csrf = $input['_csrf'] ?? $_POST['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    $includeContent = !empty($input['include_content']);
    $result = birkly_developer_export_template_pack($includeContent);
    if (empty($result['ok'])) {
        echo json_encode(['status' => 'error', 'message' => $result['error'] ?? 'Export failed']);
        exit;
    }

    $path = (string) $result['path'];
    $filename = basename($path);
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . (string) filesize($path));
    readfile($path);
    @unlink($path);
    exit;
}

if ($action === 'smoke') {
    if (!birkly_developer_mode_enabled()) {
        echo json_encode(['status' => 'error', 'message' => 'Developer mode is off', 'code' => 'dev_mode_off']);
        exit;
    }
    require_once __DIR__ . '/../core/hosting_urls.php';
    $cms = function_exists('birkly_cms_base_url') ? birkly_cms_base_url() : '';
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'cms_base_url' => $cms,
            'mcp_path' => '/mcp',
            'public_api' => '/api/index.php',
            'php_ok' => true,
            'zip_ok' => class_exists('ZipArchive'),
        ],
    ]);
    exit;
}

if ($action === 'ops_relay_status') {
    if (!birkly_developer_mode_enabled()) {
        echo json_encode(['status' => 'error', 'message' => 'Developer mode is off', 'code' => 'dev_mode_off']);
        exit;
    }
    require_once __DIR__ . '/../core/platform_ops.php';
    $force = !empty($input['force_register']);
    echo json_encode([
        'status' => 'ok',
        'data' => birkly_ops_relay_status($force),
    ]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
