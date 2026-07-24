<?php
/**
 * User Authentication API
 * 
 * Phase 2 implementation
 */

header('Content-Type: application/json');

function haru_is_html_form_request(): bool {
    $accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
    // If browser navigation or classic form post, it usually accepts HTML
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
require_once __DIR__ . '/../includes/UserAuth.php';
require_once __DIR__ . '/../includes/UserService.php';

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? 'check';

// CSRF protection for state-changing operations
// NOTE: We do NOT require CSRF for login. CSRF-on-login is not meaningful protection and it breaks
// pure-HTML form posts (and some browser setups that block JS event interception).
if (in_array($action, ['logout']) && $method === 'POST') {
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
    $auth = new UserAuth();
    
    switch ($action) {
        case 'login':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                break;
            }
            
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $remember = isset($_POST['remember']) && $_POST['remember'] === 'true';
            
            if (empty($email) || empty($password)) {
                if (haru_is_html_form_request()) {
                    haru_redirect_back_with('error', 'Email and password are required');
                }
                http_response_code(400);
                echo json_encode(['error' => 'Email and password are required']);
                break;
            }
            
            try {
                $user = $auth->login($email, $password, $remember);
                
                // Check if 2FA is required
                if (!empty($user['requires_2fa'])) {
                    if (haru_is_html_form_request()) {
                        haru_redirect_back_with('error', '2FA required (not supported by demo form yet)');
                    }
                    echo json_encode([
                        'success' => true,
                        'requires_2fa' => true,
                        'message' => '2FA code required',
                        'temp_login_key' => $user['temp_login_key'] ?? null,
                        'user' => $user
                    ]);
                } else {
                    if (haru_is_html_form_request()) {
                        haru_redirect_back_with('ok', 'Login successful');
                    }
                    echo json_encode([
                        'success' => true,
                        'message' => 'Login successful',
                        'user' => $user
                    ]);
                }
            } catch (Exception $e) {
                if (haru_is_html_form_request()) {
                    haru_redirect_back_with('error', $e->getMessage());
                }
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
            }
            break;
            
        case 'verify_2fa':
            // Verify 2FA code and complete login
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                break;
            }
            
            $tempLoginKey = $input['temp_login_key'] ?? $_POST['temp_login_key'] ?? '';
            $code = $input['code'] ?? $_POST['code'] ?? '';
            
            if (empty($tempLoginKey) || empty($code)) {
                http_response_code(400);
                echo json_encode(['error' => 'Temporary login key and code are required']);
                break;
            }
            
            try {
                $user = $auth->completeLoginWith2FA($tempLoginKey, $code);
                echo json_encode([
                    'success' => true,
                    'message' => 'Login successful',
                    'user' => $user
                ]);
            } catch (Exception $e) {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
            }
            break;
            
        case 'logout':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                break;
            }
            
            $auth->logout();
            if (haru_is_html_form_request()) {
                haru_redirect_back_with('ok', 'Logout successful');
            }
            echo json_encode([
                'success' => true,
                'message' => 'Logout successful'
            ]);
            break;
            
        case 'check':
            // Check current session
            $user = $auth->getCurrentUser();
            if ($user) {
                echo json_encode([
                    'logged_in' => true,
                    'user' => $user
                ]);
            } else {
                echo json_encode(['logged_in' => false]);
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
