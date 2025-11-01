<?php
/**
 * Webhooks API
 * Handles webhook configuration and triggering
 */

require_once __DIR__ . '/session.php';
haru_secure_session_start();

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/log.php';

// CRITICAL: Only execute switch if NOT being included by another file
if (isset($GLOBALS['__HARU_INTERNAL_CALL']) && $GLOBALS['__HARU_INTERNAL_CALL'] === true) {
    // Being included - don't execute switch statement, just provide functions
} else {
    // File is being called directly (not included) - handle API requests
    header('Content-Type: application/json');

    // Check authentication for webhook management
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
        exit;
    }

    $action = $_GET['action'] ?? '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);
        
        switch ($action) {
            case 'create':
                $result = createWebhook($input);
                echo json_encode($result);
                exit;
            case 'update':
                $result = updateWebhook($input);
                echo json_encode($result);
                exit;
            case 'delete':
                $result = deleteWebhook($input);
                echo json_encode($result);
                exit;
            case 'test':
                $result = testWebhook($input);
                echo json_encode($result);
                exit;
            default:
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
                exit;
        }
    } else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        switch ($action) {
            case 'list':
                $result = listWebhooks();
                echo json_encode($result);
                exit;
            case 'get':
                $result = getWebhook($_GET['id'] ?? '');
                echo json_encode($result);
                exit;
            default:
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
                exit;
        }
    }
    exit; // Exit after handling direct API call
}
// End of direct API call handling - functions below are available for includes

/**
 * Trigger webhook
 * This is called from other parts of the system when content changes
 */
function triggerWebhook($event, $data = []) {
    $webhooksDir = __DIR__ . '/../data/webhooks';
    if (!is_dir($webhooksDir)) {
        return []; // No webhooks configured
    }
    $storage = new Storage($webhooksDir);
    $webhookFiles = $storage->list('');
    
    if (empty($webhookFiles)) {
        return [];
    }
    
    $triggered = [];
    
    foreach ($webhooks as $webhook) {
        if (!$webhook || !isset($webhook['active']) || !$webhook['active']) {
            continue;
        }
        
        // Check if webhook should fire for this event
        $events = $webhook['events'] ?? [];
        if (!empty($events) && !in_array($event, $events) && !in_array('*', $events)) {
            continue;
        }
        
        // Check collection filter if specified
        if (isset($webhook['collections']) && !empty($webhook['collections'])) {
            $collection = $data['collection'] ?? '';
            if (!in_array($collection, $webhook['collections']) && !in_array('*', $webhook['collections'])) {
                continue;
            }
        }
        
        // Prepare payload
        $payload = [
            'event' => $event,
            'timestamp' => date('c'),
            'data' => $data
        ];
        
        // Trigger webhook
        $ch = curl_init($webhook['url']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'User-Agent: HaruCMS/1.0',
                'X-HaruCMS-Event: ' . $event,
                'X-HaruCMS-Signature: ' . generateWebhookSignature($webhook['secret'] ?? '', $payload)
            ],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        $triggered[] = [
            'id' => $webhook['id'],
            'url' => $webhook['url'],
            'success' => $httpCode >= 200 && $httpCode < 300 && !$error,
            'http_code' => $httpCode,
            'error' => $error,
            'response' => $response
        ];
        
        // Log webhook trigger
        log_security_event('webhook_triggered', [
            'webhook_id' => $webhook['id'],
            'event' => $event,
            'success' => $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode
        ]);
    }
    
    return $triggered;
}

function generateWebhookSignature($secret, $payload) {
    return hash_hmac('sha256', json_encode($payload), $secret);
}

function createWebhook($data) {
    $webhooksDir = __DIR__ . '/../data/webhooks';
    if (!is_dir($webhooksDir)) {
        mkdir($webhooksDir, 0755, true);
    }
    $storage = new Storage($webhooksDir);
    
    $url = trim($data['url'] ?? '');
    $events = $data['events'] ?? ['*'];
    $collections = $data['collections'] ?? ['*'];
    $secret = $data['secret'] ?? bin2hex(random_bytes(16));
    $active = $data['active'] ?? true;
    
    if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
        return ['status' => 'error', 'message' => 'Valid URL is required'];
    }
    
    $webhookId = uniqid('wh_');
    $webhook = [
        'id' => $webhookId,
        'url' => $url,
        'events' => $events,
        'collections' => $collections,
        'secret' => $secret,
        'active' => $active,
        'created_at' => date('c'),
        'created_by' => $_SESSION['user']['email'] ?? 'unknown'
    ];
    
    if ($storage->write($webhookId . '.json', $webhook)) {
        log_security_event('webhook_created', ['webhook_id' => $webhookId, 'url' => $url]);
        return ['status' => 'ok', 'data' => $webhook];
    } else {
        return ['status' => 'error', 'message' => 'Failed to create webhook'];
    }
}

function updateWebhook($data) {
    $webhooksDir = __DIR__ . '/../data/webhooks';
    if (!is_dir($webhooksDir)) {
        mkdir($webhooksDir, 0755, true);
    }
    $storage = new Storage($webhooksDir);
    
    $webhookId = $data['id'] ?? '';
    if (empty($webhookId)) {
        return ['status' => 'error', 'message' => 'Webhook ID is required'];
    }
    
    $webhook = $storage->read($webhookId . '.json');
    if (!$webhook) {
        return ['status' => 'error', 'message' => 'Webhook not found'];
    }
    
    // Update fields
    if (isset($data['url'])) {
        $url = trim($data['url']);
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            $webhook['url'] = $url;
        }
    }
    
    if (isset($data['events'])) {
        $webhook['events'] = $data['events'];
    }
    
    if (isset($data['collections'])) {
        $webhook['collections'] = $data['collections'];
    }
    
    if (isset($data['secret'])) {
        $webhook['secret'] = trim($data['secret']);
    }
    
    if (isset($data['active'])) {
        $webhook['active'] = (bool)$data['active'];
    }
    
    $webhook['updated_at'] = date('c');
    $webhook['updated_by'] = $_SESSION['user']['email'] ?? 'unknown';
    
    if ($storage->write($webhookId . '.json', $webhook)) {
        log_security_event('webhook_updated', ['webhook_id' => $webhookId]);
        return ['status' => 'ok', 'data' => $webhook];
    } else {
        return ['status' => 'error', 'message' => 'Failed to update webhook'];
    }
}

function deleteWebhook($data) {
    $webhooksDir = __DIR__ . '/../data/webhooks';
    if (!is_dir($webhooksDir)) {
        mkdir($webhooksDir, 0755, true);
    }
    $storage = new Storage($webhooksDir);
    
    $webhookId = $data['id'] ?? '';
    if (empty($webhookId)) {
        return ['status' => 'error', 'message' => 'Webhook ID is required'];
    }
    
    if ($storage->delete($webhookId . '.json')) {
        log_security_event('webhook_deleted', ['webhook_id' => $webhookId]);
        return ['status' => 'ok', 'message' => 'Webhook deleted'];
    } else {
        return ['status' => 'error', 'message' => 'Failed to delete webhook'];
    }
}

function listWebhooks() {
    $webhooksDir = __DIR__ . '/../data/webhooks';
    if (!is_dir($webhooksDir)) {
        mkdir($webhooksDir, 0755, true);
    }
    $storage = new Storage($webhooksDir);
    $webhookFiles = $storage->list('');
    
    $webhooks = [];
    foreach ($webhookFiles as $file) {
        $webhook = $storage->read($file);
        if ($webhook) {
            // Don't expose secret
            unset($webhook['secret']);
            $webhooks[] = $webhook;
        }
    }
    
    return ['status' => 'ok', 'webhooks' => $webhooks];
}

function getWebhook($webhookId) {
    if (empty($webhookId)) {
        return ['status' => 'error', 'message' => 'Webhook ID is required'];
    }
    
    $webhooksDir = __DIR__ . '/../data/webhooks';
    if (!is_dir($webhooksDir)) {
        mkdir($webhooksDir, 0755, true);
    }
    $storage = new Storage($webhooksDir);
    $webhook = $storage->read($webhookId . '.json');
    
    if (!$webhook) {
        return ['status' => 'error', 'message' => 'Webhook not found'];
    }
    
    // Don't expose secret
    unset($webhook['secret']);
    
    return ['status' => 'ok', 'webhook' => $webhook];
}

function testWebhook($data) {
    $webhookId = $data['id'] ?? '';
    if (empty($webhookId)) {
        return ['status' => 'error', 'message' => 'Webhook ID is required'];
    }
    
    $storage = new Storage(__DIR__ . '/../data/webhooks');
    $webhook = $storage->read($webhookId . '.json');
    
    if (!$webhook) {
        return ['status' => 'error', 'message' => 'Webhook not found'];
    }
    
    // Send test payload
    $payload = [
        'event' => 'test',
        'timestamp' => date('c'),
        'data' => [
            'message' => 'This is a test webhook from HaruCMS',
            'webhook_id' => $webhookId
        ]
    ];
    
    $ch = curl_init($webhook['url']);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'User-Agent: HaruCMS/1.0',
            'X-HaruCMS-Event: test',
            'X-HaruCMS-Signature: ' . generateWebhookSignature($webhook['secret'] ?? '', $payload)
        ],
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'status' => 'ok',
        'success' => $httpCode >= 200 && $httpCode < 300 && !$error,
        'http_code' => $httpCode,
        'error' => $error,
        'response' => $response
    ];
}

