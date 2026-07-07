<?php
/**
 * Payments admin API — connections CRUD, activity list, stats.
 */

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../../logs/php_errors.log');

require_once __DIR__ . '/../../../api/session.php';
haru_secure_session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../../api/auth.php';
require_once __DIR__ . '/../../../core/csrf.php';
require_once __DIR__ . '/../init.php';

if (!commerce_plugin_active()) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Commerce plugin is not active']);
    exit;
}

$shelf = commerce_bootstrap();
$store = $GLOBALS['commerce_credential_store'] ?? new PaymentsCredentialStore();
$activityLog = $GLOBALS['commerce_activity_log'] ?? new PaymentsActivityLog($store->getDataDir());

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? $_GET['action'] ?? '';

$readActions = ['list_connections', 'get_connection', 'list_activity', 'stats', 'list_providers', 'get_webhook_url', 'get_settings'];
$manageActions = ['save_connection', 'delete_connection', 'test_connection', 'save_settings'];

if (!in_array($action, $readActions, true) && !in_array($action, $manageActions, true)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
    exit;
}

if (in_array($action, ['list_activity', 'stats'], true)) {
    if (!payments_can_view_activity()) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
        exit;
    }
} elseif (in_array($action, $manageActions, true)) {
    if (!payments_can_manage()) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
        exit;
    }
} else {
    if (!payments_can_view_activity()) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
        exit;
    }
}

if (in_array($action, $manageActions, true) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $input['_csrf'] ?? $_POST['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
}

switch ($action) {
    case 'list_providers':
        echo json_encode([
            'status' => 'ok',
            'data' => [
                'providers' => $shelf->listProviders(),
            ],
        ]);
        break;

    case 'list_connections':
        $connections = array_values($store->listConnections());
        foreach ($connections as &$conn) {
            $conn['webhook_url'] = $shelf->getWebhookUrl($conn['provider'] ?? '');
        }
        unset($conn);
        echo json_encode(['status' => 'ok', 'data' => ['connections' => $connections]]);
        break;

    case 'get_connection':
        $id = $input['id'] ?? $_GET['id'] ?? '';
        $conn = $store->getConnection($id, false);
        if (!$conn) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Connection not found']);
            break;
        }
        $conn['webhook_url'] = $shelf->getWebhookUrl($conn['provider'] ?? '');
        echo json_encode(['status' => 'ok', 'data' => ['connection' => $conn]]);
        break;

    case 'save_connection':
        $connection = $input['connection'] ?? [];
        if (empty($connection['provider'])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Provider required']);
            break;
        }
        $saved = $store->saveConnection($connection);
        $saved['webhook_url'] = $shelf->getWebhookUrl($saved['provider'] ?? '');
        echo json_encode(['status' => 'ok', 'data' => ['connection' => $saved]]);
        break;

    case 'delete_connection':
        $id = $input['id'] ?? '';
        $ok = $store->deleteConnection($id);
        echo json_encode(['status' => $ok ? 'ok' : 'error', 'message' => $ok ? 'Deleted' : 'Not found']);
        break;

    case 'test_connection':
        $provider = $input['provider'] ?? '';
        $credentials = $input['credentials'] ?? [];
        if (empty($credentials) && !empty($input['connection_id'])) {
            $credentials = $store->getCredentialsForConnection($input['connection_id']) ?? [];
        }
        $result = $shelf->testConnection($provider, $credentials);
        echo json_encode(['status' => $result['ok'] ? 'ok' : 'error', 'data' => $result]);
        break;

    case 'list_activity':
        $filters = $input['filters'] ?? [];
        $limit = (int) ($input['limit'] ?? 100);
        $offset = (int) ($input['offset'] ?? 0);
        echo json_encode([
            'status' => 'ok',
            'data' => [
                'activity' => $activityLog->list($filters, $limit, $offset),
            ],
        ]);
        break;

    case 'stats':
        if (!payments_can_view_stats()) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
            break;
        }
        echo json_encode(['status' => 'ok', 'data' => ['stats' => $activityLog->stats()]]);
        break;

    case 'get_webhook_url':
        $provider = $input['provider'] ?? $_GET['provider'] ?? '';
        echo json_encode([
            'status' => 'ok',
            'data' => ['webhook_url' => $shelf->getWebhookUrl($provider)],
        ]);
        break;

    case 'get_settings':
        $settings = [];
        foreach ([
            'selection_mode', 'default_provider', 'auto_rules', 'enable_dashboard_widget', 'dashboard_widget_roles',
            'dunning_email_enabled', 'dunning_email_subject', 'dunning_email_body',
        ] as $key) {
            $settings[$key] = commerce_get_setting($key, null);
        }
        if ($settings['enable_dashboard_widget'] === null) {
            $settings['enable_dashboard_widget'] = false;
        }
        if ($settings['dashboard_widget_roles'] === null) {
            $settings['dashboard_widget_roles'] = ['admin'];
        }
        if ($settings['dunning_email_enabled'] === null) {
            $settings['dunning_email_enabled'] = false;
        }
        if ($settings['dunning_email_subject'] === null) {
            $settings['dunning_email_subject'] = 'Action required: subscription payment failed';
        }
        if ($settings['dunning_email_body'] === null) {
            $settings['dunning_email_body'] = "Hello,\n\nWe were unable to process your subscription payment. Please update your payment method to avoid interruption.\n\nSubscription: {subscription_id}\nAmount: {amount}\n\nThank you.";
        }
        echo json_encode(['status' => 'ok', 'data' => ['settings' => $settings]]);
        break;

    case 'save_settings':
        $settings = $input['settings'] ?? [];
        $allowed = [
            'selection_mode', 'default_provider', 'auto_rules', 'enable_dashboard_widget', 'dashboard_widget_roles',
            'dunning_email_enabled', 'dunning_email_subject', 'dunning_email_body',
        ];
        $pluginId = commerce_active_plugin_id();
        foreach ($allowed as $key) {
            if (array_key_exists($key, $settings) && function_exists('set_plugin_setting')) {
                set_plugin_setting($pluginId, $key, $settings[$key]);
            }
        }
        if (function_exists('save_plugin_states')) {
            save_plugin_states();
        }
        echo json_encode(['status' => 'ok', 'message' => 'Settings saved']);
        break;

    default:
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
}
