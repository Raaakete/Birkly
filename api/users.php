<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

require_once 'session.php';
haru_secure_session_start();

require_once 'storage.php';
require_once __DIR__ . '/../core/birkly_paths.php';
require_once 'auth.php';
require_once 'validate.php';

// Only set headers if this file is being called directly (not included)
// When included via admin/index.php, headers are already sent, so skip
if (!headers_sent() && !isset($GLOBALS['__HARU_INTERNAL_CALL'])) {
    header('Content-Type: application/json');
    // SECURITY FIX: Set security headers for API endpoints
    if (function_exists('set_security_headers')) {
        set_security_headers('api');
    }
}
require_once 'rate_limit.php';
require_once 'log.php';
require_once __DIR__ . '/../core/csrf.php';
require_once __DIR__ . '/../core/birkly_users.php';

// Use a storage instance that points to the users directory
$usersDir = birkly_users_write_dir();
if (!is_dir($usersDir)) {
    @mkdir($usersDir, 0755, true);
}
$storage = new Storage($usersDir);
$rawInput = file_get_contents('php://input');
$input = !empty($rawInput) ? sanitize_input(json_decode($rawInput, true)) : [];
if (json_last_error() !== JSON_ERROR_NONE) {
    if (!headers_sent() && !isset($GLOBALS['__HARU_INTERNAL_CALL'])) {
        header('Content-Type: application/json');
    }
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON input: ' . json_last_error_msg()]);
    exit;
}
$action = $input['action'] ?? $_GET['action'] ?? '';

// Helper function to hash passwords
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Helper function to verify passwords
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Login
if ($action === 'login') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $email = trim(strtolower($input['email'] ?? ''));
    $password = $input['password'] ?? '';

    $security = get_security_settings();
    $maxAttempts = max(1, (int) ($security['max_login_attempts'] ?? 5));
    $lockoutTime = max(60, (int) ($security['lockout_time'] ?? 900));
    $lockoutKey = 'login_' . md5($email . '_' . $ip);
    $rateLimiter = new RateLimiter($lockoutKey, $maxAttempts, $lockoutTime);
    if (!$rateLimiter->check()) {
        log_security_event('rate_limited', ['action' => 'login', 'ip' => $ip, 'email' => $email]);
        http_response_code(429);
        $retryAfter = $rateLimiter->getRetryAfter();
        header('Retry-After: ' . $retryAfter);
        echo json_encode(['status' => 'error', 'message' => 'Too many login attempts. Please try again later.', 'retry_after' => $retryAfter]);
        exit;
    }
    $userFile = md5($email) . '.json';
    
    // SECURITY FIX: Removed password logging - passwords and hashes should never be logged
    // Debug logging removed to prevent sensitive data exposure
    
    // SECURITY FIX: Use generic error message to prevent user enumeration
    // Don't reveal whether user exists or password is wrong
    $user = $storage->read($userFile);
    if (!$user || !verifyPassword($password, $user['password'] ?? '')) {
        // Log the actual reason internally, but return generic message to client
        $reason = !$user ? 'user not found' : 'invalid credentials';
        log_security_event('login_failed', ['email' => $email, 'reason' => $reason]);
        echo json_encode(['status' => 'error', 'message' => 'Invalid email or password']);
        exit;
    }
    
    // Don't send password hash to client
    unset($user['password']);
    
    // Ensure name and avatar are in session
    $user['name'] = $user['name'] ?? $user['email'];
    $user['avatar'] = $user['avatar'] ?? '';
    $user['language'] = $user['language'] ?? null;
    
    // SECURITY FIX: Regenerate session ID after successful login to prevent session fixation
    // This invalidates any session ID that might have been set before authentication
    session_regenerate_id(true);
    
    $_SESSION['user'] = $user;
    
    // Set language from user profile if available
    if (!empty($user['language'])) {
        require_once __DIR__ . '/../core/i18n.php';
        I18n::setLanguage($user['language']);
        $_SESSION['admin_language'] = $user['language'];
    }
    
    log_security_event('login_success', ['email' => $email]);
    echo json_encode([
        'status' => 'ok',
        'message' => 'Login successful',
        'data' => $user
    ]);
    exit;
}

// Check authentication
if ($action === 'check_auth') {
    if (!isset($_SESSION['user'])) {
        echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
        exit;
    }
    
    // Check session timeout
    $timeout = getSessionTimeout();
    $now = time();
    if (isset($_SESSION['last_activity']) && ($now - $_SESSION['last_activity']) > $timeout) {
        session_unset();
        session_destroy();
        echo json_encode(['status' => 'error', 'message' => 'Session expired']);
        exit;
    }
    
    $_SESSION['last_activity'] = $now;
    
    echo json_encode([
        'status' => 'ok',
        'message' => 'Authenticated',
        'data' => [
            'user' => $_SESSION['user']
        ]
    ]);
    exit;
}

// Logout
if ($action === 'logout') {
    log_security_event('logout', ['user' => $_SESSION['user']['email'] ?? 'guest']);
    session_destroy();
    echo json_encode([
        'status' => 'ok',
        'message' => 'Logged out'
    ]);
    exit;
}

// Create user (admin only)
if ($action === 'create_user') {
    if (!isset($_SESSION['user']) || !checkPermission($_SESSION['user'], 'create_user')) {
        log_security_event('permission_denied', ['action' => 'create_user', 'user' => $_SESSION['user']['email'] ?? 'guest']);
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $email = $input['email'] ?? '';
    $password = $input['password'] ?? '';
    $role = $input['role'] ?? 'viewer';
    
    if (empty($email) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'Email and password required']);
        exit;
    }
    
    // SECURITY FIX: Validate password strength using helper function
    $passwordValidation = validate_password_strength($password);
    if (!$passwordValidation['valid']) {
        echo json_encode(['status' => 'error', 'message' => $passwordValidation['message']]);
        exit;
    }
    
    $userId = md5($email);
    if ($storage->read($userId . '.json')) {
        echo json_encode(['status' => 'error', 'message' => 'User already exists']);
        exit;
    }
    
    $user = [
        'id' => $userId,
        'email' => $email,
        'password' => hashPassword($password),
        'role' => $role,
        'created_at' => date('c'),
        'created_by' => $_SESSION['user']['id']
    ];
    
    try {
        $result = $storage->write($userId . '.json', $user);
        
        if (!$result) {
            error_log('Failed to write user file: ' . $userId . '.json');
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to create user: Could not save user data'
            ]);
            exit;
        }
        
        // Remove password from response
        unset($user['password']);
        
        echo json_encode([
            'status' => 'ok',
            'message' => 'User created successfully',
            'data' => $user
        ]);
        exit;
    } catch (Exception $e) {
        error_log('Error creating user: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to create user: ' . $e->getMessage()
        ]);
        exit;
    }
}

// List users (admin only)
if ($action === 'list_users') {
    if (!isset($_SESSION['user']) || !checkPermission($_SESSION['user'], 'list_users')) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    
    $users = $storage->list('');  // List root of users directory
    $userData = [];
    foreach ($users as $user) {
        $data = $storage->read($user);
        if ($data) {
            unset($data['password']); // Don't send password hashes
            $userData[] = $data;
        }
    }
    
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'users' => $userData
        ]
    ]);
    exit;
}

// Update user (admin only)
if ($action === 'update_user') {
    if (!isset($_SESSION['user']) || !checkPermission($_SESSION['user'], 'update_user')) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $userId = $input['id'] ?? '';
    $updates = $input['updates'] ?? [];
    
    if (empty($userId) || empty($updates)) {
        echo json_encode(['status' => 'error', 'message' => 'User ID and updates required']);
        exit;
    }
    
    $user = $storage->read($userId . '.json');
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
        exit;
    }
    
    // Update allowed fields
    $allowedFields = ['role', 'email'];
    foreach ($allowedFields as $field) {
        if (isset($updates[$field])) {
            $user[$field] = $updates[$field];
        }
    }
    
    // Update password if provided
    if (!empty($updates['password'])) {
        $user['password'] = hashPassword($updates['password']);
    }
    
    $user['updated_at'] = date('c');
    $user['updated_by'] = $_SESSION['user']['id'];
    
    $result = $storage->write($userId . '.json', $user);
    
    echo json_encode([
        'status' => $result ? 'ok' : 'error',
        'message' => $result ? 'User updated' : 'Failed to update user'
    ]);
    exit;
}

// Delete user (admin only)
if ($action === 'delete_user') {
    if (!isset($_SESSION['user']) || !checkPermission($_SESSION['user'], 'delete_user')) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $userId = $input['id'] ?? '';
    if (empty($userId)) {
        echo json_encode(['status' => 'error', 'message' => 'User ID required']);
        exit;
    }
    
    // Prevent self-deletion
    if ($userId === $_SESSION['user']['id']) {
        echo json_encode(['status' => 'error', 'message' => 'Cannot delete your own account']);
        exit;
    }
    
    $result = $storage->delete($userId . '.json');
    
    echo json_encode([
        'status' => $result ? 'ok' : 'error',
        'message' => $result ? 'User deleted' : 'Failed to delete user'
    ]);
    exit;
}

// Count users
if ($action === 'count') {
    $users = $storage->list('');  // List root of users directory
    $count = count($users);
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'count' => $count
        ]
    ]);
    exit;
}

// Update profile (current user only)
if ($action === 'update_profile') {
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $userId = $_SESSION['user']['id'];
    $user = $storage->read($userId . '.json');
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
        exit;
    }
    
    // Update allowed fields
    if (isset($input['name'])) {
        $user['name'] = $input['name'];
    }
    if (isset($input['avatar'])) {
        $user['avatar'] = $input['avatar'];
    }
    
    $user['updated_at'] = date('c');
    $result = $storage->write($userId . '.json', $user);
    
    // Update session
    $_SESSION['user'] = array_merge($_SESSION['user'], [
        'name' => $user['name'] ?? $_SESSION['user']['email'],
        'avatar' => $user['avatar'] ?? ''
    ]);
    
    echo json_encode([
        'status' => $result ? 'ok' : 'error',
        'message' => $result ? 'Profile updated' : 'Failed to update profile'
    ]);
    exit;
}

// Upload avatar
if ($action === 'upload_avatar') {
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    
    $csrf = $_POST['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['status' => 'error', 'message' => 'No file uploaded or upload error']);
        exit;
    }
    
    $file = $_FILES['file'];
    
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid file type. Only images are allowed.']);
        exit;
    }
    
    // Validate file size (max 2MB for avatar)
    if ($file['size'] > 2 * 1024 * 1024) {
        echo json_encode(['status' => 'error', 'message' => 'File too large. Maximum size is 2MB.']);
        exit;
    }
    
    // Create admin/assets/avatars directory if it doesn't exist
    $avatarsDir = __DIR__ . '/../admin/assets/avatars';
    if (!is_dir($avatarsDir)) {
        mkdir($avatarsDir, 0755, true);
    }
    
    // Generate unique filename
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'avatar-' . $_SESSION['user']['id'] . '-' . uniqid() . '.' . $extension;
    $filepath = $avatarsDir . '/' . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        $avatarPath = birkly_url('/admin/assets/avatars/' . $filename);
        
        echo json_encode([
            'status' => 'ok',
            'message' => 'Avatar uploaded successfully',
            'data' => ['avatarPath' => $avatarPath]
        ]);
        exit;
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to save file']);
        exit;
    }
}

// Set language (user-level)
if ($action === 'set_language') {
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        log_security_event('permission_denied', ['action' => 'set_language', 'reason' => 'invalid CSRF']);
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $language = $input['language'] ?? '';
    if (empty($language)) {
        echo json_encode(['status' => 'error', 'message' => 'Language required']);
        exit;
    }
    
    // Validate language exists
    require_once __DIR__ . '/../core/i18n.php';
    $availableLangs = I18n::getAvailableLanguages();
    if (!isset($availableLangs[$language])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid language']);
        exit;
    }
    
    // Set language in session
    $_SESSION['admin_language'] = $language;
    
    // Save to user profile
    $userId = $_SESSION['user']['id'];
    $user = $storage->read($userId . '.json');
    if ($user) {
        $user['language'] = $language;
        $user['updated_at'] = date('c');
        $storage->write($userId . '.json', $user);
        
        // Update session
        $_SESSION['user']['language'] = $language;
    }
    
    // Set language in i18n system
    I18n::setLanguage($language);
    
    echo json_encode(['status' => 'ok', 'message' => 'Language changed successfully']);
    exit;
}

// Update notification preferences (current user only)
if ($action === 'update_notification_preferences') {
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $preferences = $input['preferences'] ?? [];
    if (!is_array($preferences)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid preferences format']);
        exit;
    }
    
    // Validate and sanitize preferences
    $validPreferences = [
        'entry_published' => isset($preferences['entry_published']) ? (bool)$preferences['entry_published'] : true,
        'entry_review' => isset($preferences['entry_review']) ? (bool)$preferences['entry_review'] : true,
        'entry_commented' => isset($preferences['entry_commented']) ? (bool)$preferences['entry_commented'] : true,
        'invitation_accepted' => isset($preferences['invitation_accepted']) ? (bool)$preferences['invitation_accepted'] : true,
        'role_changed' => isset($preferences['role_changed']) ? (bool)$preferences['role_changed'] : true,
        'system_updates' => isset($preferences['system_updates']) ? (bool)$preferences['system_updates'] : true,
        'security_events' => isset($preferences['security_events']) ? (bool)$preferences['security_events'] : false
    ];
    
    // Save to user profile
    $userId = $_SESSION['user']['id'];
    $user = $storage->read($userId . '.json');
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
        exit;
    }
    
    $user['notification_preferences'] = $validPreferences;
    $user['updated_at'] = date('c');
    $result = $storage->write($userId . '.json', $user);
    
    // Update session
    $_SESSION['user']['notification_preferences'] = $validPreferences;
    
    echo json_encode([
        'status' => $result ? 'ok' : 'error',
        'message' => $result ? 'Notification preferences saved' : 'Failed to save preferences'
    ]);
    exit;
}

// Change password (current user only)
if ($action === 'change_password') {
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $currentPassword = $input['current_password'] ?? '';
    $newPassword = $input['new_password'] ?? '';
    
    if (empty($currentPassword) || empty($newPassword)) {
        echo json_encode(['status' => 'error', 'message' => 'Current password and new password required']);
        exit;
    }
    
    // SECURITY FIX: Validate password strength using helper function
    $passwordValidation = validate_password_strength($newPassword);
    if (!$passwordValidation['valid']) {
        echo json_encode(['status' => 'error', 'message' => $passwordValidation['message']]);
        exit;
    }
    
    $userId = $_SESSION['user']['id'];
    $user = $storage->read($userId . '.json');
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
        exit;
    }
    
    // Verify current password
    if (!verifyPassword($currentPassword, $user['password'])) {
        // SECURITY FIX: Use generic error message
        echo json_encode(['status' => 'error', 'message' => 'Invalid current password']);
        exit;
    }
    
    // Update password
    $user['password'] = hashPassword($newPassword);
    $user['updated_at'] = date('c');
    $result = $storage->write($userId . '.json', $user);
    
    log_security_event('password_changed', ['user' => $_SESSION['user']['email'] ?? 'unknown']);
    
    echo json_encode([
        'status' => $result ? 'ok' : 'error',
        'message' => $result ? 'Password changed successfully' : 'Failed to change password'
    ]);
    exit;
}

// Default response - only if this file is called directly
if (basename($_SERVER['SCRIPT_NAME']) === 'users.php') {
    $users = $storage->list('');  // List root of users directory
    $count = count($users);
    echo json_encode([
        'status' => 'ok',
        'message' => 'Users API is working',
        'data' => [
            'count' => $count
        ]
    ]);
} 