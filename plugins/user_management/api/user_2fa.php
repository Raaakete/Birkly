<?php
/**
 * User 2FA API
 * 
 * Handles 2FA setup, verification, and management
 */

require_once __DIR__ . '/../includes/TwoFactorAuth.php';
require_once __DIR__ . '/../includes/UserAuth.php';
require_once __DIR__ . '/../includes/UserService.php';
require_once __DIR__ . '/../../../core/csrf.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

$twoFactor = new TwoFactorAuth();
$auth = new UserAuth();
$service = new UserService();

try {
    // Get current user
    $currentUser = $auth->getCurrentUser();
    if (!$currentUser) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'Not authenticated'
        ]);
        exit;
    }
    
    $user_id = $currentUser['id'];
    
    switch ($action) {
        case 'generate_secret':
            // Generate new 2FA secret
            $csrf = $input['_csrf'] ?? '';
            if (!validate_csrf_token($csrf)) {
                http_response_code(403);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Invalid CSRF token'
                ]);
                exit;
            }
            
            $user = $service->getUser($user_id);
            $email = $user['email'] ?? '';
            
            $result = $twoFactor->generateSecret($user_id, $email);
            
            echo json_encode([
                'status' => 'ok',
                'data' => $result
            ]);
            break;
            
        case 'enable':
            // Enable 2FA (after verifying code)
            $csrf = $input['_csrf'] ?? '';
            if (!validate_csrf_token($csrf)) {
                http_response_code(403);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Invalid CSRF token'
                ]);
                exit;
            }
            
            $verification_code = $input['code'] ?? '';
            if (empty($verification_code)) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Verification code required'
                ]);
                exit;
            }
            
            $result = $twoFactor->enable2FA($user_id, $verification_code);
            
            if ($result['success']) {
                echo json_encode([
                    'status' => 'ok',
                    'message' => $result['message'],
                    'backup_codes' => $result['backup_codes']
                ]);
            } else {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => $result['message']
                ]);
            }
            break;
            
        case 'disable':
            // Disable 2FA
            $csrf = $input['_csrf'] ?? '';
            if (!validate_csrf_token($csrf)) {
                http_response_code(403);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Invalid CSRF token'
                ]);
                exit;
            }
            
            $success = $twoFactor->disable2FA($user_id);
            
            if ($success) {
                echo json_encode([
                    'status' => 'ok',
                    'message' => '2FA disabled successfully'
                ]);
            } else {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Failed to disable 2FA'
                ]);
            }
            break;
            
        case 'verify':
            // Verify 2FA code (for login)
            $code = $input['code'] ?? '';
            $backup_code = $input['backup_code'] ?? '';
            
            if (empty($code) && empty($backup_code)) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Code or backup code required'
                ]);
                exit;
            }
            
            $valid = false;
            if (!empty($code)) {
                $valid = $twoFactor->verifyCode($user_id, $code);
            } elseif (!empty($backup_code)) {
                $valid = $twoFactor->verifyBackupCode($user_id, $backup_code);
            }
            
            if ($valid) {
                echo json_encode([
                    'status' => 'ok',
                    'message' => '2FA verified successfully'
                ]);
            } else {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Invalid code'
                ]);
            }
            break;
            
        case 'regenerate_backup_codes':
            // Regenerate backup codes
            $csrf = $input['_csrf'] ?? '';
            if (!validate_csrf_token($csrf)) {
                http_response_code(403);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Invalid CSRF token'
                ]);
                exit;
            }
            
            $result = $twoFactor->regenerateBackupCodes($user_id);
            
            if ($result['success']) {
                echo json_encode([
                    'status' => 'ok',
                    'backup_codes' => $result['backup_codes']
                ]);
            } else {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => $result['message']
                ]);
            }
            break;
            
        case 'status':
            // Get 2FA status
            $enabled = $twoFactor->is2FAEnabled($user_id);
            $user = $service->getUser($user_id);
            $required = $twoFactor->is2FARequired($user['role'] ?? 'member');
            
            echo json_encode([
                'status' => 'ok',
                'enabled' => $enabled,
                'required' => $required,
                'has_secret' => !empty($twoFactor->get2FASecret($user_id))
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
    error_log('User 2FA API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Internal server error'
    ]);
}
