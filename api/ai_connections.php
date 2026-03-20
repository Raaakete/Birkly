<?php
/**
 * AI Connections API
 * Manage AI provider connections (OpenAI, Anthropic, etc.)
 */

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

require_once 'session.php';
haru_secure_session_start();

if (!headers_sent() && !isset($GLOBALS['__HARU_INTERNAL_CALL'])) {
    header('Content-Type: application/json');
}

require_once 'storage.php';
require_once 'auth.php';
require_once 'validate.php';
require_once '../core/csrf.php';
require_once 'ai_helpers.php';

// Include log.php but prevent it from executing its API endpoints
$GLOBALS['__HARU_INTERNAL_CALL'] = true;
require_once 'log.php';
unset($GLOBALS['__HARU_INTERNAL_CALL']);

// Start output buffering to catch any stray output
ob_start();

$storage = new Storage('../users');
$input = sanitize_input(json_decode(file_get_contents('php://input'), true));
$action = $input['action'] ?? $_GET['action'] ?? '';

// All actions require authentication (but not a specific permission)
requireAuth();

// List AI connections
if ($action === 'list') {
    // Clear any output buffers to prevent corruption
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    $aiConnections = [];
    $currentUserId = $_SESSION['user']['id'] ?? '';
    
    // Check if storage directory exists
    if (is_dir($storage->basePath)) {
        $users = $storage->list('');
        
        foreach ($users as $userFile) {
            try {
                $user = $storage->read($userFile);
                if ($user && isset($user['type']) && $user['type'] === 'ai') {
                    // Check if user can see this connection
                    $isOwner = isset($user['created_by']) && $user['created_by'] === $currentUserId;
                    // Handle both old format (shared: true/false) and new format (shared_type: 'none'/'all'/'specific')
                    $sharedType = $user['shared_type'] ?? ($user['shared'] === true ? 'all' : ($user['shared'] === 'all' ? 'all' : ($user['shared'] === 'specific' ? 'specific' : 'none')));
                    $canAccess = false;
                    
                    if ($isOwner) {
                        $canAccess = true;
                    } elseif ($sharedType === 'all') {
                        $canAccess = true;
                    } elseif ($sharedType === 'specific') {
                        $allowedUsers = $user['allowed_users'] ?? [];
                        $canAccess = in_array($currentUserId, $allowedUsers);
                    }
                    
                    if ($canAccess) {
                        // Don't send encrypted API key
                        unset($user['api_key_encrypted']);
                        $aiConnections[] = $user;
                    }
                }
            } catch (Exception $e) {
                error_log("Error reading user file $userFile: " . $e->getMessage());
                // Continue with next file
            }
        }
    }
    
    // Ensure clean JSON output - clear any buffers first
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    
    $response = [
        'status' => 'ok',
        'data' => ['connections' => $aiConnections]
    ];
    
    echo json_encode($response);
    exit;
}

// Create AI connection
if ($action === 'create') {
    // Clear any output buffers to prevent corruption
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $provider = $input['provider'] ?? '';
    $apiKey = $input['api_key'] ?? '';
    $role = $input['role'] ?? 'author';
    $name = $input['name'] ?? '';
    $model = $input['model'] ?? '';
    $apiUrl = $input['api_url'] ?? '';
    $shared = isset($input['shared']) && $input['shared'] === true;
    $sharedType = $input['shared_type'] ?? 'none'; // 'none', 'all', 'specific'
    $allowedUsers = $input['allowed_users'] ?? []; // Array of user IDs
    $requireApproval = isset($input['require_approval']) ? (bool)$input['require_approval'] : true;
    
    if (empty($provider)) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode(['status' => 'error', 'message' => 'Provider is required']);
        exit;
    }
    
    $providers = getSupportedAiProviders();
    if (!isset($providers[$provider])) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode(['status' => 'error', 'message' => 'Unsupported provider']);
        exit;
    }
    
    // For custom providers, API URL is required
    if ($provider === 'custom' && empty($apiUrl)) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode(['status' => 'error', 'message' => 'API URL is required for custom providers']);
        exit;
    }
    
    // API key not required for MCP or Ollama
    if ($provider !== 'mcp' && $provider !== 'ollama' && empty($apiKey)) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode(['status' => 'error', 'message' => 'API key is required']);
        exit;
    }
    
    // Generate unique ID for AI user
    $aiId = 'ai_' . bin2hex(random_bytes(8));
    $email = $provider . '@ai.birkly';
    
    // Check if connection already exists (skip for MCP as multiple MCP connections are allowed)
    if ($provider !== 'mcp') {
        $existingUsers = $storage->list('');
        foreach ($existingUsers as $userFile) {
            $user = $storage->read($userFile);
            if ($user && isset($user['type']) && $user['type'] === 'ai' && 
                isset($user['provider']) && $user['provider'] === $provider &&
                isset($user['created_by']) && $user['created_by'] === $_SESSION['user']['id']) {
                if (!headers_sent()) {
                    header('Content-Type: application/json');
                }
                echo json_encode(['status' => 'error', 'message' => 'Connection for this provider already exists']);
                exit;
            }
        }
    }
    
    // Create AI user
    $aiUser = [
        'id' => $aiId,
        'email' => $email,
        'type' => 'ai',
        'name' => $name ?: $providers[$provider]['name'],
        'role' => $role,
        'provider' => $provider,
        'api_key_encrypted' => $provider !== 'mcp' ? encryptApiKey($apiKey) : '',
        'api_url' => $apiUrl ?: ($providers[$provider]['api_url'] ?? ''),
        'model' => $model ?: ($providers[$provider]['models'][0] ?? ''),
        'shared' => $sharedType !== 'none',
        'shared_type' => $sharedType,
        'allowed_users' => $sharedType === 'specific' ? $allowedUsers : [],
        'require_approval' => $requireApproval,
        'created_at' => date('c'),
        'created_by' => $_SESSION['user']['id'],
        'status' => 'active'
    ];
    
    // For MCP, generate MCP API key and URL
    if ($provider === 'mcp') {
        $mcpApiKey = bin2hex(random_bytes(32));
        $aiUser['mcp_api_key'] = $mcpApiKey;
        $aiUser['mcp_url'] = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
                             '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/api/mcp_server.php';
    }
    
    // Clear output buffers before writing
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    $result = $storage->write($aiId . '.json', $aiUser);
    
    log_security_event('ai_connection_created', [
        'provider' => $provider,
        'ai_user_id' => $aiId,
        'created_by' => $_SESSION['user']['id']
    ]);
    
    // Prepare response data (don't send sensitive info)
    $responseData = $aiUser;
    unset($responseData['api_key_encrypted']);
    
    // Ensure clean JSON output - clear any buffers first
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    
    $response = [
        'status' => $result ? 'ok' : 'error',
        'message' => $result ? 'AI connection created' : 'Failed to create connection',
        'data' => ['connection' => $responseData]
    ];
    
    echo json_encode($response);
    exit;
}

// Update AI connection
if ($action === 'update') {
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $aiId = $input['id'] ?? '';
    $updates = $input['updates'] ?? [];
    
    error_log("AI Connection Update: ID=$aiId, Updates=" . json_encode($updates));
    
    if (empty($aiId)) {
        echo json_encode(['status' => 'error', 'message' => 'AI connection ID required']);
        exit;
    }
    
    $aiUser = $storage->read($aiId . '.json');
    if (!$aiUser || !isset($aiUser['type']) || $aiUser['type'] !== 'ai') {
        echo json_encode(['status' => 'error', 'message' => 'AI connection not found']);
        exit;
    }
    
    error_log("AI Connection Update: Before update, require_approval=" . var_export($aiUser['require_approval'] ?? 'not set', true));
    
    // Update allowed fields
    $allowedFields = ['name', 'role', 'model', 'status', 'shared', 'shared_type', 'allowed_users', 'require_approval', 'api_url'];
    foreach ($allowedFields as $field) {
        if (array_key_exists($field, $updates)) {
            // Ensure require_approval is stored as boolean
            if ($field === 'require_approval') {
                // Handle various input types: boolean, string 'true'/'false', '1'/'0', etc.
                $value = $updates[$field];
                error_log("AI Connection Update: require_approval raw value: " . var_export($value, true) . " (type: " . gettype($value) . ")");
                
                if (is_bool($value)) {
                    $aiUser[$field] = $value;
                } elseif (is_string($value)) {
                    // Empty string should be treated as false
                    if ($value === '') {
                        $aiUser[$field] = false;
                    } else {
                        $aiUser[$field] = ($value === '1' || $value === 'true' || $value === 'yes' || $value === 'on');
                    }
                } elseif (is_null($value)) {
                    $aiUser[$field] = false;
                } else {
                    $aiUser[$field] = (bool)$value;
                }
                error_log("AI Connection Update: Setting require_approval to " . var_export($aiUser[$field], true) . " (from " . var_export($value, true) . ")");
            } else {
                $aiUser[$field] = $updates[$field];
                error_log("AI Connection Update: Setting $field to " . var_export($updates[$field], true));
            }
        } else {
            error_log("AI Connection Update: Field $field not in updates array");
        }
    }
    
    // Always ensure require_approval is set (default to true if not provided)
    if (!array_key_exists('require_approval', $updates)) {
        // If not in updates, keep existing value or default to true
        if (!isset($aiUser['require_approval'])) {
            $aiUser['require_approval'] = true;
            error_log("AI Connection Update: require_approval not in updates, defaulting to true");
        } else {
            error_log("AI Connection Update: require_approval not in updates, keeping existing value: " . var_export($aiUser['require_approval'], true));
        }
    }
    
    // Update shared flag based on shared_type
    if (isset($updates['shared_type'])) {
        $aiUser['shared'] = $updates['shared_type'] !== 'none';
    }
    
    // Update API key if provided
    if (!empty($updates['api_key'])) {
        $aiUser['api_key_encrypted'] = encryptApiKey($updates['api_key']);
    }
    
    $aiUser['updated_at'] = date('c');
    $aiUser['updated_by'] = $_SESSION['user']['id'];
    
    error_log("AI Connection Update: After update, require_approval=" . var_export($aiUser['require_approval'] ?? 'not set', true));
    
    $result = $storage->write($aiId . '.json', $aiUser);
    
    error_log("AI Connection Update: Write result=" . ($result ? 'success' : 'failed'));
    
    log_security_event('ai_connection_updated', [
        'ai_user_id' => $aiId,
        'updated_by' => $_SESSION['user']['id']
    ]);
    
    unset($aiUser['api_key_encrypted']);
    echo json_encode([
        'status' => $result ? 'ok' : 'error',
        'message' => $result ? 'AI connection updated' : 'Failed to update connection',
        'data' => ['connection' => $aiUser]
    ]);
    exit;
}

// Delete AI connection
if ($action === 'delete') {
    // Clear output buffers
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $aiId = $input['id'] ?? '';
    if (empty($aiId)) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode(['status' => 'error', 'message' => 'AI connection ID required']);
        exit;
    }
    
    $aiUser = $storage->read($aiId . '.json');
    if (!$aiUser || !isset($aiUser['type']) || $aiUser['type'] !== 'ai') {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode(['status' => 'error', 'message' => 'AI connection not found']);
        exit;
    }
    
    $result = $storage->delete($aiId . '.json');
    
    log_security_event('ai_connection_deleted', [
        'ai_user_id' => $aiId,
        'deleted_by' => $_SESSION['user']['id']
    ]);
    
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode([
        'status' => $result ? 'ok' : 'error',
        'message' => $result ? 'AI connection deleted' : 'Failed to delete connection'
    ]);
    exit;
}

// Get AI connection details
if ($action === 'get') {
    // Clear output buffers
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    $aiId = $_GET['id'] ?? '';
    if (empty($aiId)) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode(['status' => 'error', 'message' => 'AI connection ID required']);
        exit;
    }
    
    $aiUser = $storage->read($aiId . '.json');
    if (!$aiUser || !isset($aiUser['type']) || $aiUser['type'] !== 'ai') {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode(['status' => 'error', 'message' => 'AI connection not found']);
        exit;
    }
    
    unset($aiUser['api_key_encrypted']);
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode([
        'status' => 'ok',
        'data' => ['connection' => $aiUser]
    ]);
    exit;
}

// Get supported providers
if ($action === 'providers') {
    // Clear output buffers
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode([
        'status' => 'ok',
        'data' => ['providers' => getSupportedAiProviders()]
    ]);
    exit;
}

// Get users list (for user selection)
if ($action === 'get_users') {
    // Clear output buffers
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    $userList = [];
    
    if (is_dir($storage->basePath)) {
        $users = $storage->list('');
        
        foreach ($users as $userFile) {
            $user = $storage->read($userFile);
            if ($user && (!isset($user['type']) || $user['type'] !== 'ai')) {
                // Only return human users, not AI users
                unset($user['password']);
                $userList[] = [
                    'id' => $user['id'] ?? md5($user['email'] ?? ''),
                    'email' => $user['email'] ?? '',
                    'name' => $user['name'] ?? $user['email'] ?? '',
                    'role' => $user['role'] ?? 'viewer'
                ];
            }
        }
    }
    
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode([
        'status' => 'ok',
        'data' => ['users' => $userList]
    ]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Unknown action']);

