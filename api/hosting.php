<?php
/**
 * Hosting settings API (P43).
 */

declare(strict_types=1);

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

if (!headers_sent() && !isset($GLOBALS['__HARU_INTERNAL_CALL'])) {
    header('Content-Type: application/json');
}

require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../core/csrf.php';
require_once __DIR__ . '/../core/hosting_urls.php';

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
    $config = birkly_hosting_load();
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'hosting' => $config,
            'cms_host' => birkly_hosting_cms_host(),
            'public_host' => birkly_hosting_public_host(),
            'request_host_role' => birkly_request_host_role(),
            'same_host' => birkly_hosting_same_host(),
            'client_snippet' => birkly_hosting_client_snippet(),
            'suggested_cms_url' => birkly_hosting_build_url_from_request(),
        ],
    ]);
    exit;
}

requireAuth('manage_settings');

if ($action === 'save') {
    $csrf = $input['_csrf'] ?? $_POST['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }

    $hosting = $input['hosting'] ?? $input;
    if (!is_array($hosting)) {
        echo json_encode(['status' => 'error', 'message' => 'Hosting settings required']);
        exit;
    }

    $config = array_merge(birkly_hosting_load(), [
        'cms_base_url' => (string) ($hosting['cms_base_url'] ?? ''),
        'public_site_mode' => (string) ($hosting['public_site_mode'] ?? 'none'),
        'public_site_url' => (string) ($hosting['public_site_url'] ?? ''),
        'same_host_landing' => (string) ($hosting['same_host_landing'] ?? 'admin'),
        'strict_admin_host' => (bool) ($hosting['strict_admin_host'] ?? true),
    ]);

    if ($config['public_site_mode'] !== 'none' && $config['public_site_url'] === '') {
        echo json_encode(['status' => 'error', 'message' => 'Public site URL is required when a public site mode is selected']);
        exit;
    }

    if (!birkly_hosting_persist($config, true)) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to save hosting settings']);
        exit;
    }

    echo json_encode([
        'status' => 'ok',
        'message' => 'Hosting settings saved',
        'data' => [
            'hosting' => birkly_hosting_load(),
            'client_snippet' => birkly_hosting_client_snippet(),
        ],
    ]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
