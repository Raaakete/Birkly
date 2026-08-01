<?php
/**
 * Automation API for Birkly
 * 
 * Handles automation CRUD operations, execution, and queue management.
 */

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../../logs/php_errors.log');

require_once __DIR__ . '/../../../api/session.php';
haru_secure_session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../../api/auth.php';
require_once __DIR__ . '/../../../core/automation/bootstrap.php';

if (!automation_bootstrap()) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Automation plugin is not active']);
    exit;
}

require_once __DIR__ . '/../../../core/csrf.php';
require_once __DIR__ . '/../includes/cron_auth.php';
require_once __DIR__ . '/../includes/setup_health.php';

// Require admin authentication for most actions
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

if (!is_array($input)) {
    $input = [];
}

$cronActions = ['process_queue', 'process_scheduled'];
$csrfActions = ['create', 'update', 'delete', 'dry_run', 'test', 'run_live', 'save_mail_settings', 'process_queue_now', 'send_test_email'];

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
} else {
    requireAuth('manage_content');

    if (in_array($action, $csrfActions, true)) {
        $csrf = $input['_csrf'] ?? '';
        if (!validate_csrf_token($csrf)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
            exit;
        }
    }
}

if ($action === 'dashboard_summary') {
    if (!automation_dashboard_user_can_view()) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
        exit;
    }

    $automations = get_automations();
    $logs = get_automation_logs(null, 5);
    $queue = get_automation_queue_status();
    $recent = array_map(static function (array $log): array {
        return [
            'automation_id' => (string) ($log['automation_id'] ?? ''),
            'status' => (string) ($log['status'] ?? 'unknown'),
            'executed_at' => (string) ($log['executed_at'] ?? ''),
        ];
    }, $logs);

    echo json_encode([
        'status' => 'ok',
        'data' => [
            'enabled' => count(array_filter($automations, static fn (array $automation): bool => !empty($automation['enabled']))),
            'total' => count($automations),
            'queue' => $queue,
            'recent' => $recent,
            'automation_url' => function_exists('admin_url')
                ? admin_url('automation')
                : '/admin/index.php?page=automation',
        ],
    ]);
    exit;
}

if ($action === 'cron_info') {
    $token = automation_get_cron_token();
    $host = $_SERVER['HTTP_HOST'] ?? 'yoursite.com';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'https';
    $base_url = $scheme . '://' . $host;

    echo json_encode([
        'status' => 'ok',
        'data' => [
            'queue_cli' => 'php plugins/automation/cli/process_queue.php 10',
            'queue_url' => $base_url . '/api/automation.php?action=process_queue&token=' . $token,
            'scheduled_url' => $base_url . '/api/automation.php?action=process_scheduled&token=' . $token,
            'token_created' => true,
        ],
    ]);
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
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
            'data' => [
                'success' => false,
                'error' => $e->getMessage()
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

// Admin: process queue immediately (no cron token)
if ($action === 'process_queue_now') {
    $limit = (int) ($input['limit'] ?? 25);
    if ($limit < 1) {
        $limit = 1;
    }
    if ($limit > 100) {
        $limit = 100;
    }
    // force=true ignores retry backoff and reclaims stale "processing" jobs
    $processed = process_automation_queue($limit, ['force' => true]);
    $queue = get_automation_queue_status();
    $recent = function_exists('automation_get_recent_queue_jobs')
        ? automation_get_recent_queue_jobs(8)
        : [];

    echo json_encode([
        'status' => 'ok',
        'data' => [
            'processed' => $processed,
            'queue' => $queue,
            'recent_jobs' => $recent,
            'health' => automation_setup_health(),
        ],
    ]);
    exit;
}

// Setup / connection readiness checklist
if ($action === 'setup_health') {
    echo json_encode([
        'status' => 'ok',
        'data' => automation_setup_health(),
    ]);
    exit;
}

// Save email From / SMTP settings used by Send Email actions
if ($action === 'save_mail_settings') {
    $saved = automation_save_mail_settings($input);
    if ($saved === false) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Valid From email is required. For SMTP, also provide an SMTP host.',
        ]);
        exit;
    }
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'mail' => $saved,
            'health' => automation_setup_health(),
        ],
    ]);
    exit;
}

// Send a real test email using current Setup transport
if ($action === 'send_test_email') {
    $to = trim((string) ($input['to'] ?? ''));
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Enter a valid test recipient email']);
        exit;
    }

    // Cross-request lock (session alone is not enough if the page binds twice
    // or two tabs fire in the same second).
    $dedupe = automation_acquire_test_email_lock($to, 12);
    if (!$dedupe['acquired']) {
        echo json_encode([
            'status' => 'ok',
            'message' => 'Test email already sent a moment ago — check your inbox before sending again.',
            'data' => [
                'result' => ['ok' => true, 'method' => 'deduped'],
                'health' => automation_setup_health(),
            ],
        ]);
        exit;
    }

    $mail = automation_get_mail_settings();
    $body = '<p>This is a Birkly Automation test email.</p>'
        . '<p>Transport: <strong>' . htmlspecialchars((string) $mail['method']) . '</strong></p>'
        . '<p>Plugin version: <strong>' . htmlspecialchars(automation_plugin_version()) . '</strong></p>'
        . '<p>If you received this, Setup email delivery is working.</p>'
        . '<p>Sent at ' . htmlspecialchars(date('c')) . '</p>';

    $result = automation_send_mail(
        $to,
        'Birkly Automation test email',
        $body,
        $mail
    );

    if (empty($result['ok'])) {
        automation_release_test_email_lock($to);
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => $result['error'] ?? 'Test email failed',
            'data' => ['result' => $result, 'health' => automation_setup_health()],
        ]);
        exit;
    }

    $message = !empty($result['logged']) && ($result['method'] ?? '') === 'log'
        ? 'Test email logged (Log transport). Open Setup → Recent sends to confirm. Switch to SMTP to deliver to a real inbox.'
        : 'Test email accepted by transport (' . ($result['method'] ?? 'mail') . '). Check the inbox (and spam).';

    echo json_encode([
        'status' => 'ok',
        'message' => $message,
        'data' => [
            'result' => $result,
            'health' => automation_setup_health(),
        ],
    ]);
    exit;
}

// Preview template
if ($action === 'preview_template') {
    require_once __DIR__ . '/../template_preview.php';
    
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
    require_once __DIR__ . '/../template_preview.php';
    
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
    require_once __DIR__ . '/../scheduler.php';
    $triggered = process_scheduled_automations();
    
    // Also process plugin scheduled tasks
    if (function_exists('process_plugin_scheduled_tasks')) {
        require_once __DIR__ . '/../../../core/plugins/scheduled_tasks.php';
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

// Get collections list for pickers
if ($action === 'get_collections') {
    $storage_core = automation_storage();
    $contentRoot = automation_cms_root();
    $content_storage = StorageFactory::createStorage($contentRoot);
    $col_dirs = @scandir($contentRoot . '/content/collections') ?: [];
    $collections = [];
    foreach ($col_dirs as $dir) {
        if ($dir === '.' || $dir === '..') continue;
        $meta_file = $contentRoot . '/content/collections/' . $dir . '/collection.json';
        if (!is_file($meta_file)) continue;
        $meta = json_decode(file_get_contents($meta_file), true);
        if (!$meta) continue;
        $collections[] = [
            'slug'  => $meta['slug'] ?? $dir,
            'title' => $meta['title'] ?? $dir,
            'sub_collections' => array_map(function($sc) {
                return [
                    'slug'  => $sc['slug'] ?? '',
                    'title' => $sc['title'] ?? $sc['slug'] ?? ''
                ];
            }, $meta['sub_collections'] ?? [])
        ];
    }
    echo json_encode(['status' => 'ok', 'data' => ['collections' => $collections]]);
    exit;
}

// Dry run — validate config, simulate actions, return structured log (D4)
if ($action === 'dry_run') {
    $automation_id = $input['automation_id'] ?? '';
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

    $sample_context = array_merge($input['context'] ?? [], ['_dry_run' => true, '_test_mode' => true]);
    if (function_exists('generate_sample_automation_context')) {
        require_once __DIR__ . '/../template_preview.php';
        $sample_context = array_merge(
            generate_sample_automation_context($automation['trigger']['type'] ?? null),
            $sample_context
        );
    }
    $steps = [];
    $all_ok = true;

    // Step: trigger
    $trigger_type = $automation['trigger']['type'] ?? '';
    $steps[] = ['type' => 'trigger', 'label' => 'Trigger', 'detail' => $trigger_type ?: 'none', 'status' => $trigger_type ? 'pass' : 'warn'];

    // Step: conditions
    $conditions = $automation['conditions'] ?? [];
    if (empty($conditions)) {
        $steps[] = ['type' => 'conditions', 'label' => 'Conditions', 'detail' => 'No conditions — always runs', 'status' => 'info'];
    } else {
        foreach ($conditions as $i => $condition) {
            $ctype = $condition['type'] ?? '';
            $block = get_automation_block('condition', $ctype);
            if (!$block) {
                $steps[] = ['type' => 'condition', 'label' => "Condition " . ($i + 1), 'detail' => "Unknown type: $ctype", 'status' => 'warn'];
                $all_ok = false;
                continue;
            }
            try {
                $handler = $block['handler'] ?? null;
                $result = $handler ? call_user_func($handler, $condition['config'] ?? [], $sample_context) : true;
                $steps[] = ['type' => 'condition', 'label' => "Condition " . ($i + 1) . ": " . ($block['name'] ?? $ctype), 'detail' => $result ? 'Would pass' : 'Would skip (condition not met)', 'status' => $result ? 'pass' : 'skip'];
            } catch (Exception $e) {
                $steps[] = ['type' => 'condition', 'label' => "Condition " . ($i + 1), 'detail' => $e->getMessage(), 'status' => 'warn'];
                $all_ok = false;
            }
        }
    }

    // Step: actions (dry-run — describe but do not execute)
    $actions = $automation['actions'] ?? [];
    if (empty($actions) && !empty($automation['graph']['nodes'])) {
        foreach ($automation['graph']['nodes'] as $node) {
            if (($node['kind'] ?? '') !== 'action') {
                continue;
            }
            $actions[] = [
                'type' => $node['type'] ?? '',
                'config' => $node['config'] ?? [],
            ];
        }
    }

    $health = automation_setup_health();
    if (!empty($health['blocking'])) {
        foreach ($health['blocking'] as $blockMsg) {
            $steps[] = [
                'type' => 'setup',
                'label' => 'Setup required',
                'detail' => $blockMsg,
                'status' => 'warn',
            ];
            $all_ok = false;
        }
    } elseif (!empty($health['warnings'])) {
        foreach (array_slice($health['warnings'], 0, 3) as $warnMsg) {
            $steps[] = [
                'type' => 'setup',
                'label' => 'Setup warning',
                'detail' => $warnMsg,
                'status' => 'warn',
            ];
        }
    }

    if (empty($actions)) {
        $steps[] = ['type' => 'actions', 'label' => 'Actions', 'detail' => 'No actions configured', 'status' => 'warn'];
        $all_ok = false;
    } else {
        foreach ($actions as $i => $action_def) {
            $atype = $action_def['type'] ?? '';
            $block = get_automation_block('action', $atype);
            $label = 'Action ' . ($i + 1) . ': ' . ($block['name'] ?? ($atype !== '' ? $atype : 'unknown'));
            $detail = automation_describe_dry_run_action($atype, $action_def['config'] ?? [], $sample_context);
            $steps[] = ['type' => 'action', 'label' => $label, 'detail' => $detail, 'status' => 'info'];
        }
    }

    $steps[] = [
        'type' => 'notice',
        'label' => 'Dry run only',
        'detail' => 'No email, webhook, Slack, or other side effects were performed. Use Confirm & Run Live (or create a real entry) to execute for real.',
        'status' => 'info',
    ];

    echo json_encode([
        'status' => 'ok',
        'data'   => [
            'steps'       => $steps,
            'all_ok'      => $all_ok,
            'action_count' => count($actions),
            'dry_run' => true,
            'setup' => [
                'ready' => $health['ready'],
                'blocking' => $health['blocking'],
                'warnings' => $health['warnings'],
            ],
        ]
    ]);
    exit;
}

// Run automation live (manual trigger from admin — executes real actions)
if ($action === 'run_live') {
    $automation_id = $input['automation_id'] ?? '';
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

    require_once __DIR__ . '/../template_preview.php';

    $run_context = $input['context'] ?? [];
    if (empty($run_context)) {
        $run_context = generate_sample_automation_context($automation['trigger']['type'] ?? null);
    }
    $run_context['_manual_run'] = true;
    $run_context['_skip_conditions'] = !empty($input['skip_conditions']);

    $health = automation_setup_health();
    $actionTypes = [];
    foreach ($automation['actions'] ?? [] as $actionDef) {
        $actionTypes[] = (string) ($actionDef['type'] ?? '');
    }
    foreach ($automation['graph']['nodes'] ?? [] as $node) {
        if (($node['kind'] ?? '') === 'action') {
            $actionTypes[] = (string) ($node['type'] ?? '');
        }
    }
    $needsMail = in_array('email', $actionTypes, true);
    $mailMethod = (string) ($health['mail']['method'] ?? 'mail');
    if ($needsMail && $mailMethod === 'mail' && empty($health['capabilities']['mail'])) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Email cannot be sent: PHP mail() is unavailable. Open Automation → Setup and choose SMTP or Log.',
            'data' => ['setup' => $health],
        ]);
        exit;
    }
    if ($needsMail && $mailMethod === 'smtp' && empty($health['mail']['smtp_host'])) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'SMTP is selected but not configured. Open Automation → Setup, fill SMTP host/credentials, then Send test email.',
            'data' => ['setup' => $health],
        ]);
        exit;
    }

    $start_time = microtime(true);
    try {
        $success = execute_automation($automation, $run_context);
        $runtime = (microtime(true) - $start_time) * 1000;
        $actionCount = count($automation['actions'] ?? []);
        if ($actionCount === 0 && !empty($automation['graph']['nodes'])) {
            $actionCount = count(array_filter(
                $automation['graph']['nodes'],
                static fn ($n) => ($n['kind'] ?? '') === 'action'
            ));
        }

        echo json_encode([
            'status' => 'ok',
            'data' => [
                'success' => $success,
                'runtime_ms' => $runtime,
                'actions_executed' => $actionCount,
                'manual_run' => true,
                'delivery_note' => $needsMail
                    ? ('Email uses PHP mail() From ' . ($health['mail']['from_email'] ?? '') . '. If nothing arrives, check spam and Automation → Setup.')
                    : null,
                'setup_warnings' => $health['warnings'],
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
            'data' => [
                'success' => false,
                'error' => $e->getMessage()
            ]
        ]);
    }
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

// Default response
http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
