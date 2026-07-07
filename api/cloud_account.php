<?php
/**
 * Cloud account link API (P13-M1) — optional Birkly cloud SSO link.
 */

if (!defined('HARU_API_REQUEST')) {
    define('HARU_API_REQUEST', true);
}

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

if (!headers_sent() && !isset($GLOBALS['__HARU_INTERNAL_CALL'])) {
    header('Content-Type: application/json');
}

require_once 'auth.php';
require_once 'validate.php';
require_once '../core/csrf.php';
require_once '../core/cloud_account.php';
require_once __DIR__ . '/../core/birkly_paths.php';

requireAuth();

$rawInput = file_get_contents('php://input');
$input = [];
if ($rawInput !== '') {
    $decoded = json_decode($rawInput, true);
    if (is_array($decoded)) {
        $input = sanitize_input($decoded);
    }
}

$action = $input['action'] ?? $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'status') {
    $oauth = birkly_cloud_oauth_status();
    echo json_encode([
        'status' => 'ok',
        'data' => array_merge(birkly_cloud_account_status(), [
            'oauth_base_url' => $oauth['base_url'],
            'oauth_reachable' => $oauth['reachable'],
            'oauth_message' => $oauth['message'],
        ]),
    ]);
    exit;
}

if ($action === 'start_link') {
    $csrf = $input['_csrf'] ?? $_POST['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }

    $callbackUrl = birkly_url('/api/cloud_account.php?action=callback');
    $result = birkly_cloud_account_start_link($callbackUrl);
    if (empty($result['ok'])) {
        echo json_encode(['status' => 'error', 'message' => $result['error'] ?? 'Failed to start link']);
        exit;
    }

    echo json_encode([
        'status' => 'ok',
        'data' => [
            'authorization_url' => $result['authorization_url'],
        ],
    ]);
    exit;
}

if ($action === 'callback') {
    $code = trim((string) ($input['code'] ?? $_GET['code'] ?? ''));
    $state = trim((string) ($input['state'] ?? $_GET['state'] ?? ''));

    if ($code === '' || $state === '') {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing authorization code or state']);
        exit;
    }

    $result = birkly_cloud_account_handle_callback($code, $state);
    if (empty($result['ok'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => $result['error'] ?? 'Link failed']);
        exit;
    }

    if (isset($GLOBALS['__HARU_INTERNAL_CALL'])) {
        echo json_encode([
            'status' => 'ok',
            'data' => ['email' => $result['email'] ?? ''],
        ]);
        exit;
    }

    header('Location: ' . birkly_url('/admin/index.php?page=account&cloud_linked=1'));
    exit;
}

if ($action === 'unlink') {
    $csrf = $input['_csrf'] ?? $_POST['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }

    $result = birkly_cloud_account_unlink();
    if (empty($result['ok'])) {
        echo json_encode(['status' => 'error', 'message' => $result['error'] ?? 'Unlink failed']);
        exit;
    }

    echo json_encode(['status' => 'ok', 'data' => birkly_cloud_account_status()]);
    exit;
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
