<?php
/**
 * User Registration API
 * 
 * Phase 3 implementation
 */

header('Content-Type: application/json');

function haru_is_html_form_request(): bool {
    $accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
    return strpos($accept, 'text/html') !== false;
}

function haru_redirect_back_with(string $param, string $message): void {
    $redirect = $_POST['_haru_redirect'] ?? $_GET['_haru_redirect'] ?? ($_SERVER['HTTP_REFERER'] ?? '/');
    $base = $redirect ?: '/';
    $sep = (strpos($base, '?') !== false) ? '&' : '?';
    header('Location: ' . $base . $sep . rawurlencode($param) . '=' . rawurlencode($message));
    exit;
}

// Check if plugin is active
if (!function_exists('get_plugin_state') || !get_plugin_state('user_management')) {
    http_response_code(503);
    echo json_encode(['error' => 'User Management plugin is not active']);
    exit;
}

// Load plugin classes
require_once __DIR__ . '/../includes/UserService.php';
require_once __DIR__ . '/../includes/UserStorage.php';
require_once __DIR__ . '/../includes/ProfileService.php';

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? 'register';

// CSRF protection for state-changing operations
// NOTE: We do NOT require CSRF for registration flows. These are public, unauthenticated endpoints and
// CSRF does not provide meaningful protection here; requiring it breaks pure-HTML form posts.
if (in_array($action, ['verify', 'invite']) && $method === 'POST') {
    if (function_exists('verify_csrf_token')) {
        $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
        if (!verify_csrf_token($token)) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF token']);
            exit;
        }
    }
}

try {
    $service = new UserService();
    $storage = new UserStorage();
    
    switch ($action) {
        case 'register':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                break;
            }
            
            // Check if registration is enabled
            // Canonical keys (plugin schema): enable_registration, registration_method, auto_approve_users
            // Back-compat keys (admin UI): allow_public_registration, auto_approve
            $enableRegistration = get_plugin_setting('user_management', 'enable_registration', null);
            if ($enableRegistration === null) {
                $enableRegistration = get_plugin_setting('user_management', 'allow_public_registration', true);
            }
            $registrationMethod = get_plugin_setting('user_management', 'registration_method', 'public');
            if (!$enableRegistration) {
                $registrationMethod = 'admin_only';
            }
            
            if (!$enableRegistration) {
                if (haru_is_html_form_request()) {
                    haru_redirect_back_with('error', 'User registration is disabled');
                }
                http_response_code(403);
                echo json_encode(['error' => 'User registration is disabled']);
                break;
            }
            
            if ($registrationMethod === 'admin_only') {
                if (haru_is_html_form_request()) {
                    haru_redirect_back_with('error', 'Registration is only available to administrators');
                }
                http_response_code(403);
                echo json_encode(['error' => 'Registration is only available to administrators']);
                break;
            }
            
            // Get registration data
            $email = trim(strtolower($_POST['email'] ?? ''));
            $password = $_POST['password'] ?? '';
            $name = trim($_POST['name'] ?? '');
            $inviteCode = trim($_POST['invite_code'] ?? '');
            
            // Validate required fields
            if (empty($email) || empty($password)) {
                if (haru_is_html_form_request()) {
                    haru_redirect_back_with('error', 'Email and password are required');
                }
                http_response_code(400);
                echo json_encode(['error' => 'Email and password are required']);
                break;
            }
            
            // Check invite code if invite-only
            if ($registrationMethod === 'invite_only') {
                if (empty($inviteCode)) {
                    if (haru_is_html_form_request()) {
                        haru_redirect_back_with('error', 'Invite code is required');
                    }
                    http_response_code(400);
                    echo json_encode(['error' => 'Invite code is required']);
                    break;
                }
                
                $invite = $storage->getInvite($inviteCode);
                if (!$invite) {
                    if (haru_is_html_form_request()) {
                        haru_redirect_back_with('error', 'Invalid or expired invite code');
                    }
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid or expired invite code']);
                    break;
                }
                
                // Check if invite already used
                if (isset($invite['used']) && $invite['used']) {
                    if (haru_is_html_form_request()) {
                        haru_redirect_back_with('error', 'Invite code has already been used');
                    }
                    http_response_code(400);
                    echo json_encode(['error' => 'Invite code has already been used']);
                    break;
                }
            }
            
            // Prepare user data
            $userData = [
                'email' => $email,
                'password' => $password,
                'name' => $name ?: $email,
                'role' => get_plugin_setting('user_management', 'default_role', 'member'),
                'status' => 'active'
            ];
            
            // Handle email verification requirement
            $requireEmailVerification = get_plugin_setting('user_management', 'require_email_verification', false);
            if ($requireEmailVerification) {
                $userData['verified'] = false;
                $userData['verification_token'] = bin2hex(random_bytes(32));
            } else {
                $userData['verified'] = true;
            }
            
            // Handle auto-approval
            $autoApprove = get_plugin_setting('user_management', 'auto_approve_users', null);
            if ($autoApprove === null) {
                $autoApprove = get_plugin_setting('user_management', 'auto_approve', true);
            }
            $userData['approved'] = $autoApprove;
            
            // Create user
            try {
                $user = $service->createUser($userData);

                // Optional: auto-create profile entry if configured + enabled
                $profileEnabled = function_exists('get_plugin_setting')
                    ? (bool)get_plugin_setting('user_management', 'profile_collection_enabled', false)
                    : false;
                $profileCollection = function_exists('get_plugin_setting')
                    ? (string)get_plugin_setting('user_management', 'profile_collection_slug', '')
                    : '';
                $autoCreateProfiles = function_exists('get_plugin_setting')
                    ? (bool)get_plugin_setting('user_management', 'auto_create_profiles', true)
                    : true;
                if ($profileEnabled && $autoCreateProfiles && $profileCollection !== '') {
                    try {
                        $profiles = new ProfileService();
                        $profiles->ensureProfileForUser($profileCollection, $user);
                    } catch (Exception $e) {
                        // Non-fatal
                        error_log('Profile auto-create failed: ' . $e->getMessage());
                    }
                }
                
                // Mark invite as used if invite-only
                if ($registrationMethod === 'invite_only' && $inviteCode) {
                    $invite['used'] = true;
                    $invite['used_by'] = $user['id'];
                    $invite['used_at'] = date('c');
                    $storage->saveInvite($inviteCode, $invite);
                }
                
                // Send verification email if required (stub for now)
                if ($requireEmailVerification && isset($userData['verification_token'])) {
                    // TODO: Send verification email
                    // For now, just log it
                    error_log('Verification email should be sent to: ' . $email . ' with token: ' . $userData['verification_token']);
                }
                
                if (haru_is_html_form_request()) {
                    haru_redirect_back_with('ok', 'Registration successful');
                }
                echo json_encode([
                    'success' => true,
                    'message' => 'Registration successful',
                    'user' => $user,
                    'requires_verification' => $requireEmailVerification
                ]);
            } catch (Exception $e) {
                if (haru_is_html_form_request()) {
                    haru_redirect_back_with('error', $e->getMessage());
                }
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
            }
            break;
            
        case 'verify':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                break;
            }
            
            $token = trim($_POST['token'] ?? '');
            if (empty($token)) {
                http_response_code(400);
                echo json_encode(['error' => 'Verification token is required']);
                break;
            }
            
            // Find user by verification token
            $users = $storage->listUsers(['deleted' => false]);
            $userToVerify = null;
            
            foreach ($users as $user) {
                $fullUser = $storage->getUser($user['id']);
                if (isset($fullUser['verification_token']) && $fullUser['verification_token'] === $token) {
                    $userToVerify = $fullUser;
                    break;
                }
            }
            
            if (!$userToVerify) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid or expired verification token']);
                break;
            }
            
            // Verify user
            $userToVerify['verified'] = true;
            unset($userToVerify['verification_token']);
            
            $service->updateUser($userToVerify['id'], [
                'verified' => true
            ]);
            
            // Fire hook
            if (function_exists('do_action')) {
                do_action('user_email_verified', $userToVerify);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Email verified successfully'
            ]);
            break;
            
        case 'invite':
            if ($method === 'GET') {
                // Validate invite code
                $inviteCode = $_GET['code'] ?? '';
                if (empty($inviteCode)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invite code is required']);
                    break;
                }
                
                $invite = $storage->getInvite($inviteCode);
                if (!$invite) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid or expired invite code']);
                    break;
                }
                
                // Check if already used
                if (isset($invite['used']) && $invite['used']) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invite code has already been used']);
                    break;
                }
                
                echo json_encode([
                    'valid' => true,
                    'invite' => [
                        'code' => $invite['invite_code'],
                        'email' => $invite['email'] ?? null,
                        'max_uses' => $invite['max_uses'] ?? 1,
                        'uses' => $invite['uses'] ?? 0
                    ]
                ]);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
