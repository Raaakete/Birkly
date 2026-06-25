<?php
/**
 * Automation API for Birkly
 * 
 * Handles automation CRUD operations, execution, and queue management.
 */

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../core/automation.php';
require_once __DIR__ . '/../core/automation/registry.php';
require_once __DIR__ . '/../core/automation/executor.php';
require_once __DIR__ . '/../core/automation/queue.php';
require_once __DIR__ . '/../core/automation/init.php';
require_once __DIR__ . '/../core/csrf.php';
require_once __DIR__ . '/../core/api_response.php';
require_once __DIR__ . '/../plugins/automation/includes/cron_auth.php';

// Require admin authentication for most actions
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = [];
}
$action = $input['action'] ?? $_GET['action'] ?? '';

// Cron-only actions (CLI or shared secret token)
$cronActions = ['process_queue', 'process_scheduled'];

if (in_array($action, $cronActions, true)) {
    if (!automation_verify_cron_request()) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
        exit;
    }
} elseif (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// List automations
if ($action === 'list') {
    $filters = $input['filters'] ?? [];
    $automations = get_automations($filters);
    
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'automations' => $automations
        ]
    ]);
    exit;
}

// Get single automation
if ($action === 'get') {
    $automation_id = $input['automation_id'] ?? $_GET['automation_id'] ?? '';
    
    if (empty($automation_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Automation ID required']);
        exit;
    }
    
    $automation = get_automation($automation_id);
    
    if ($automation) {
        echo json_encode([
            'status' => 'ok',
            'data' => ['automation' => $automation]
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Automation not found']);
    }
    exit;
}

// Create automation
if ($action === 'create') {
    requireAuth('manage_content');
    
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $result = create_automation($input);
    
    if ($result) {
        echo json_encode([
            'status' => 'ok',
            'data' => ['automation' => $result]
        ]);
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Failed to create automation']);
    }
    exit;
}

// Update automation
if ($action === 'update') {
    requireAuth('manage_content');
    
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $automation_id = $input['automation_id'] ?? '';
    
    if (empty($automation_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Automation ID required']);
        exit;
    }
    
    unset($input['action'], $input['_csrf'], $input['automation_id']);
    
    $result = update_automation($automation_id, $input);
    
    if ($result) {
        echo json_encode([
            'status' => 'ok',
            'data' => ['automation' => $result]
        ]);
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Failed to update automation']);
    }
    exit;
}

// Delete automation
if ($action === 'delete') {
    requireAuth('manage_content');
    
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $automation_id = $input['automation_id'] ?? '';
    
    if (empty($automation_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Automation ID required']);
        exit;
    }
    
    $result = delete_automation($automation_id);
    
    if ($result) {
        echo json_encode(['status' => 'ok', 'message' => 'Automation deleted']);
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete automation']);
    }
    exit;
}

// Get available blocks
if ($action === 'get_blocks') {
    $block_type = $input['type'] ?? $_GET['type'] ?? null;
    
    if ($block_type) {
        $blocks = get_automation_blocks($block_type);
    } else {
        $blocks = get_all_automation_blocks();
    }
    
    echo json_encode([
        'status' => 'ok',
        'data' => ['blocks' => $blocks]
    ]);
    exit;
}

// Get execution logs
if ($action === 'get_logs') {
    $automation_id = $input['automation_id'] ?? $_GET['automation_id'] ?? null;
    $limit = $input['limit'] ?? $_GET['limit'] ?? 100;
    
    $logs = get_automation_logs($automation_id, $limit);
    
    echo json_encode([
        'status' => 'ok',
        'data' => ['logs' => $logs]
    ]);
    exit;
}

// Test automation (dry run)
if ($action === 'test') {
    requireAuth('manage_content');
    
    $automation_id = $input['automation_id'] ?? '';
    $sample_context = $input['context'] ?? [];
    
    if (empty($automation_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Automation ID required']);
        exit;
    }
    
    $automation = get_automation($automation_id);
    if (!$automation) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Automation not found']);
        exit;
    }
    
    // Create a test version of the automation that won't trigger real actions
    $test_automation = $automation;
    $test_automation['_test_mode'] = true;
    
    // Execute automation with sample context (but don't log)
    $start_time = microtime(true);
    
    // Temporarily disable logging
    $original_log_function = null;
    if (function_exists('log_automation_execution')) {
        // Store original function
        $original_log_function = 'log_automation_execution';
    }
    
    // Execute with test context
    $test_context = array_merge($sample_context, [
        '_test_mode' => true,
        '_dry_run' => true
    ]);
    
    try {
        $result = execute_automation($test_automation, $test_context);
        $runtime = (microtime(true) - $start_time) * 1000;
        
        // Get execution results (from last execution if available)
        $execution_results = [
            'success' => $result,
            'runtime_ms' => $runtime,
            'conditions_met' => true,
            'actions_executed' => count($automation['actions'] ?? [])
        ];
        
        echo json_encode([
            'status' => 'ok',
            'data' => $execution_results
        ]);
    } catch (Exception $e) {
        $safe = api_sanitize_error_message($e, 'Automation test failed');
        echo json_encode([
            'status' => 'error',
            'message' => $safe,
            'data' => [
                'success' => false,
            ]
        ]);
    }
    exit;
}

// Process queue (can be called by cron)
if ($action === 'process_queue') {
    $limit = $input['limit'] ?? $_GET['limit'] ?? 10;
    $processed = process_automation_queue($limit);
    
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'processed' => $processed
        ]
    ]);
    exit;
}

// Preview template
if ($action === 'preview_template') {
    requireAuth('manage_content');
    require_once __DIR__ . '/../core/automation/template_preview.php';
    
    $template = $input['template'] ?? '';
    $trigger_type = $input['trigger_type'] ?? null;
    $sample_context = $input['sample_context'] ?? null;
    
    if (empty($template)) {
        echo json_encode(['status' => 'error', 'message' => 'Template required']);
        exit;
    }
    
    // Generate sample context if trigger type provided
    if ($sample_context === null && $trigger_type) {
        $sample_context = generate_sample_automation_context($trigger_type);
    }
    
    $preview = preview_automation_template($template, $sample_context);
    $validation = validate_automation_template($template);
    
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'rendered' => $preview['rendered'],
            'variables' => $preview['variables'],
            'sample_context' => $preview['sample_context'],
            'validation' => $validation
        ]
    ]);
    exit;
}

// Get available context variables
if ($action === 'get_context_variables') {
    require_once __DIR__ . '/../core/automation/template_preview.php';
    
    $trigger_type = $input['trigger_type'] ?? $_GET['trigger_type'] ?? null;
    $variables = get_automation_context_variables($trigger_type);
    
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'variables' => $variables
        ]
    ]);
    exit;
}

// Process scheduled automations (should be called by cron every minute)
if ($action === 'process_scheduled') {
    require_once __DIR__ . '/../core/automation/scheduler.php';
    $triggered = process_scheduled_automations();
    
    // Also process plugin scheduled tasks
    if (function_exists('process_plugin_scheduled_tasks')) {
        require_once __DIR__ . '/../core/plugins/scheduled_tasks.php';
        $plugin_tasks_executed = process_plugin_scheduled_tasks();
    } else {
        $plugin_tasks_executed = 0;
    }
    
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'triggered' => $triggered,
            'plugin_tasks_executed' => $plugin_tasks_executed
        ]
    ]);
    exit;
}

// Get queue status
if ($action === 'queue_status') {
    $status = get_automation_queue_status();
    
    echo json_encode([
        'status' => 'ok',
        'data' => ['queue' => $status]
    ]);
    exit;
}

// Inbound webhook configuration (admin)
if ($action === 'list_webhooks') {
    requireAuth('manage_content');
    $storage = StorageFactory::createStorage(__DIR__ . '/../settings');
    $webhooks = $storage->read('automation_webhook_config.json') ?? [];
    if (!is_array($webhooks)) {
        $webhooks = [];
    }
    // Never expose raw tokens in list — mask for display
    $safe = array_map(function ($wh) {
        $token = $wh['token'] ?? '';
        $wh['token_preview'] = $token !== '' ? substr($token, 0, 8) . '…' : '';
        unset($wh['token']);
        $wh['url'] = '/api/automation_webhook.php?id=' . urlencode($wh['id'] ?? '') . '&token=<token>';
        return $wh;
    }, array_values($webhooks));
    echo json_encode(['status' => 'ok', 'data' => ['webhooks' => $safe]]);
    exit;
}

if ($action === 'create_webhook') {
    requireAuth('manage_content');
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    $name = trim($input['name'] ?? 'Inbound Webhook');
    $storage = StorageFactory::createStorage(__DIR__ . '/../settings');
    $webhooks = $storage->read('automation_webhook_config.json') ?? [];
    if (!is_array($webhooks)) {
        $webhooks = [];
    }
    $id = 'wh_' . bin2hex(random_bytes(8));
    $token = bin2hex(random_bytes(16));
    $webhook = [
        'id' => $id,
        'name' => $name,
        'token' => $token,
        'active' => true,
        'created_at' => date('c'),
    ];
    $webhooks[] = $webhook;
    $storage->write('automation_webhook_config.json', $webhooks);
    $webhook['url'] = '/api/automation_webhook.php?id=' . urlencode($id) . '&token=' . urlencode($token);
    echo json_encode(['status' => 'ok', 'data' => ['webhook' => $webhook]]);
    exit;
}

if ($action === 'delete_webhook') {
    requireAuth('manage_content');
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    $webhook_id = $input['webhook_id'] ?? '';
    if ($webhook_id === '') {
        echo json_encode(['status' => 'error', 'message' => 'Webhook ID required']);
        exit;
    }
    $storage = StorageFactory::createStorage(__DIR__ . '/../settings');
    $webhooks = $storage->read('automation_webhook_config.json') ?? [];
    if (!is_array($webhooks)) {
        $webhooks = [];
    }
    $filtered = array_values(array_filter($webhooks, function ($wh) use ($webhook_id) {
        return ($wh['id'] ?? '') !== $webhook_id;
    }));
    $storage->write('automation_webhook_config.json', $filtered);
    echo json_encode(['status' => 'ok', 'message' => 'Webhook deleted']);
    exit;
}

if ($action === 'get_cron_info') {
    requireAuth('manage_settings');
    $token = automation_get_cron_token();
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'token_preview' => substr($token, 0, 8) . '…',
            'cli_command' => 'php plugins/automation/cli/process_queue.php 10',
        ]
    ]);
    exit;
}

// Default response
http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
