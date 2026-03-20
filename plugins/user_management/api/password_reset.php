<?php
/**
 * Password Reset API
 * 
 * Handles password reset requests and password updates
 */

require_once __DIR__ . '/../includes/PasswordReset.php';
require_once __DIR__ . '/../includes/UserService.php';
require_once __DIR__ . '/../../../core/csrf.php';
require_once __DIR__ . '/../../../api/session.php';
require_once __DIR__ . '/../../../api/auth.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

$passwordReset = new PasswordReset();

try {
    switch ($action) {
        case 'request':
            // Request password reset
            $email = trim($input['email'] ?? $_POST['email'] ?? '');
            
            if (empty($email)) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Email is required'
                ]);
                exit;
            }
            
            $result = $passwordReset->sendResetEmail($email);
            
            if ($result['success']) {
                echo json_encode([
                    'status' => 'ok',
                    'message' => $result['message']
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'status' => 'error',
                    'message' => $result['message']
                ]);
            }
            break;
            
        case 'verify':
            // Verify reset token
            $token = $_GET['token'] ?? $input['token'] ?? '';
            $user_id = $_GET['user'] ?? $input['user_id'] ?? '';
            
            if (empty($token) || empty($user_id)) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Token and user ID required'
                ]);
                exit;
            }
            
            $result = $passwordReset->verifyToken($token, $user_id);
            
            if ($result['success']) {
                echo json_encode([
                    'status' => 'ok',
                    'message' => $result['message']
                ]);
            } else {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => $result['message']
                ]);
            }
            break;
            
        case 'reset':
            // Reset password with token
            $token = $input['token'] ?? '';
            $user_id = $input['user_id'] ?? '';
            $password = $input['password'] ?? '';
            
            if (empty($token) || empty($user_id) || empty($password)) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Token, user ID, and password are required'
                ]);
                exit;
            }
            
            $result = $passwordReset->resetPassword($token, $user_id, $password);
            
            if ($result['success']) {
                echo json_encode([
                    'status' => 'ok',
                    'message' => $result['message']
                ]);
            } else {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => $result['message']
                ]);
            }
            break;
            
        case 'send_admin':
            // Send password reset email from admin panel (requires authentication)
            birkly_admin_session_start();
            requireAuth('manage_users');
            
            $csrf = $input['_csrf'] ?? '';
            if (!validate_csrf_token($csrf)) {
                http_response_code(403);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Invalid CSRF token'
                ]);
                exit;
            }
            
            $user_id = $input['user_id'] ?? '';
            if (empty($user_id)) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'User ID is required'
                ]);
                exit;
            }
            
            // Get user by ID
            $userService = new UserService();
            $user = $userService->getUser($user_id);
            
            if (!$user) {
                http_response_code(404);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'User not found'
                ]);
                exit;
            }
            
            $email = $user['email'] ?? '';
            if (empty($email)) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'User email not found'
                ]);
                exit;
            }
            
            // Send password reset email
            $result = $passwordReset->sendResetEmail($email);
            
            if ($result['success']) {
                echo json_encode([
                    'status' => 'ok',
                    'message' => 'Password reset email sent successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'status' => 'error',
                    'message' => $result['message']
                ]);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid action'
            ]);
            break;
    }
} catch (Exception $e) {
    error_log('Password reset API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Internal server error'
    ]);
}
