<?php
/**
 * User Verification API
 * 
 * Handles email verification endpoints
 */

require_once __DIR__ . '/../includes/EmailVerification.php';
require_once __DIR__ . '/../includes/UserAuth.php';
require_once __DIR__ . '/../includes/UserService.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

$verification = new EmailVerification();
$auth = new UserAuth();
$service = new UserService();

try {
    switch ($action) {
        case 'verify':
            // Verify email with token
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
            
            $result = $verification->verifyToken($token, $user_id);
            
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
            
        case 'resend':
            // Resend verification email
            $currentUser = $auth->getCurrentUser();
            if (!$currentUser) {
                http_response_code(401);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Not authenticated'
                ]);
                exit;
            }
            
            $result = $verification->resendVerificationEmail($currentUser['id']);
            
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
            
        case 'check_status':
            // Check verification status
            $currentUser = $auth->getCurrentUser();
            if (!$currentUser) {
                http_response_code(401);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Not authenticated'
                ]);
                exit;
            }
            
            $user = $service->getUser($currentUser['id']);
            $verified = !empty($user['verified']);
            
            echo json_encode([
                'status' => 'ok',
                'verified' => $verified,
                'verified_at' => $user['verified_at'] ?? null
            ]);
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
    error_log('User verification API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Internal server error'
    ]);
}
