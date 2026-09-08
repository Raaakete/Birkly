<?php
/**
 * Plugin activity API (P61) — list, clear nav attention, mark read.
 */

declare(strict_types=1);

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0');

if (!headers_sent() && !isset($GLOBALS['__HARU_INTERNAL_CALL'])) {
    header('Content-Type: application/json');
}

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../core/csrf.php';
require_once __DIR__ . '/../core/plugin_activity.php';

if (!isset($_SESSION['user'])) {
    require_once __DIR__ . '/session.php';
    if (function_exists('haru_secure_session_start')) {
        haru_secure_session_start();
    }
}

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$rawInput = file_get_contents('php://input');
$input = [];
if ($rawInput !== '') {
    $decoded = json_decode($rawInput, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $input = $decoded;
    }
}

$action = $_GET['action'] ?? $input['action'] ?? 'list';

if ($action === 'list') {
    $plugin = isset($_GET['plugin']) ? (string) $_GET['plugin'] : null;
    $limit = max(1, min(500, (int) ($_GET['limit'] ?? 100)));
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'items' => birkly_plugin_activity_list($limit, $plugin),
            'nav_attention' => birkly_plugin_activity_nav_attention_counts(),
        ],
    ]);
    exit;
}

if ($action === 'nav_attention') {
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'counts' => birkly_plugin_activity_nav_attention_counts(),
        ],
    ]);
    exit;
}

if ($action === 'clear_nav') {
    $csrf = $input['_csrf'] ?? $_POST['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    $plugin = (string) ($input['plugin'] ?? $_GET['plugin'] ?? '');
    $cleared = birkly_plugin_activity_clear_nav($plugin);
    echo json_encode([
        'status' => 'ok',
        'data' => ['cleared' => $cleared, 'counts' => birkly_plugin_activity_nav_attention_counts()],
    ]);
    exit;
}

if ($action === 'mark_read') {
    $csrf = $input['_csrf'] ?? $_POST['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    $ids = $input['ids'] ?? [];
    if (!is_array($ids)) {
        $ids = [];
    }
    $marked = birkly_plugin_activity_mark_read($ids);
    echo json_encode(['status' => 'ok', 'data' => ['marked' => $marked]]);
    exit;
}

if ($action === 'clear_hub_updates') {
    require_once __DIR__ . '/../core/hub_update_attention.php';
    $cleared = birkly_hub_update_attention_acknowledge_all();
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'cleared' => $cleared,
            'counts' => birkly_plugin_activity_nav_attention_counts(),
        ],
    ]);
    exit;
}

if ($action === 'emit_test') {
    requireAuth('manage_settings');
    $csrf = $input['_csrf'] ?? $_POST['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    $plugin = trim((string) ($input['plugin'] ?? ''));
    if ($plugin === '') {
        echo json_encode(['status' => 'error', 'message' => 'plugin is required']);
        exit;
    }
    $activity = birkly_plugin_activity_emit([
        'plugin' => $plugin,
        'event' => (string) ($input['event'] ?? 'test'),
        'title' => (string) ($input['title'] ?? 'Test plugin activity'),
        'body' => (string) ($input['body'] ?? 'P61 activity bus test'),
        'link' => (string) ($input['link'] ?? ''),
    ]);
    echo json_encode(['status' => 'ok', 'data' => ['activity' => $activity]]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
