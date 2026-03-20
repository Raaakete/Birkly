<?php
/**
 * PasswordReset - Password reset system
 * 
 * Handles:
 * - Reset token generation
 * - Email sending
 * - Token validation
 * - Password reset
 */

require_once __DIR__ . '/UserStorage.php';
require_once __DIR__ . '/UserService.php';
require_once __DIR__ . '/EmailVerification.php';

class PasswordReset {
    private $storage;
    private $service;
    private $emailVerification;
    
    public function __construct() {
        $this->storage = new UserStorage();
        $this->service = new UserService();
        $this->emailVerification = new EmailVerification();
    }
    
    /**
     * Generate password reset token
     * 
     * @param string $user_id User ID
     * @return string Token
     */
    public function generateToken($user_id) {
        // Generate secure random token
        $token = bin2hex(random_bytes(32));
        
        // Store token with expiration (1 hour)
        $tokenData = [
            'user_id' => $user_id,
            'token' => $token,
            'created_at' => date('c'),
            'expires_at' => date('c', strtotime('+1 hour')),
            'used' => false
        ];
        
        $this->storage->savePasswordResetToken($user_id, $tokenData);
        
        return $token;
    }
    
    /**
     * Send password reset email
     * 
     * @param string $email User email
     * @return array Result with status and message
     */
    public function sendResetEmail($email) {
        // Get user by email
        $user = $this->storage->getUserByEmail($email);
        if (!$user) {
            // Don't reveal if user exists (security best practice)
            return [
                'success' => true,
                'message' => 'If an account with that email exists, a password reset link has been sent.'
            ];
        }
        
        // Check if user is active
        if (($user['status'] ?? 'active') !== 'active') {
            return [
                'success' => true,
                'message' => 'If an account with that email exists, a password reset link has been sent.'
            ];
        }
        
        // Generate token
        $token = $this->generateToken($user['id']);
        
        // Get site URL
        $siteUrl = $this->getSiteUrl();
        $resetUrl = $siteUrl . '/reset-password?token=' . urlencode($token) . '&user=' . urlencode($user['id']);
        
        // Send email
        $sent = $this->emailVerification->sendPasswordResetEmail($user['id'], $email, $token, $resetUrl);
        
        if ($sent) {
            return [
                'success' => true,
                'message' => 'If an account with that email exists, a password reset link has been sent.'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to send password reset email'
            ];
        }
    }
    
    /**
     * Verify reset token
     * 
     * @param string $token Reset token
     * @param string $user_id User ID
     * @return array Result with status and message
     */
    public function verifyToken($token, $user_id) {
        // Get token data
        $tokenData = $this->storage->getPasswordResetToken($user_id);
        
        if (!$tokenData) {
            return [
                'success' => false,
                'message' => 'Invalid or expired reset token'
            ];
        }
        
        // Check if token matches
        if ($tokenData['token'] !== $token) {
            return [
                'success' => false,
                'message' => 'Invalid or expired reset token'
            ];
        }
        
        // Check if token is expired
        $expiresAt = strtotime($tokenData['expires_at']);
        if (time() > $expiresAt) {
            return [
                'success' => false,
                'message' => 'Reset token has expired. Please request a new one.'
            ];
        }
        
        // Check if token already used
        if ($tokenData['used']) {
            return [
                'success' => false,
                'message' => 'This reset link has already been used'
            ];
        }
        
        return [
            'success' => true,
            'message' => 'Token is valid'
        ];
    }
    
    /**
     * Reset password with token
     * 
     * @param string $token Reset token
     * @param string $user_id User ID
     * @param string $newPassword New password
     * @return array Result with status and message
     */
    public function resetPassword($token, $user_id, $newPassword) {
        // Verify token first
        $verifyResult = $this->verifyToken($token, $user_id);
        if (!$verifyResult['success']) {
            return $verifyResult;
        }
        
        // Validate password
        if (empty($newPassword)) {
            return [
                'success' => false,
                'message' => 'Password is required'
            ];
        }
        
        if (strlen($newPassword) < 8) {
            return [
                'success' => false,
                'message' => 'Password must be at least 8 characters long'
            ];
        }
        
        // Get user
        $user = $this->service->getUser($user_id);
        if (!$user) {
            return [
                'success' => false,
                'message' => 'User not found'
            ];
        }
        
        // Update password
        $user['password_hash'] = $this->service->hashPassword($newPassword);
        $this->service->updateUser($user_id, $user);
        
        // Mark token as used
        $tokenData = $this->storage->getPasswordResetToken($user_id);
        $tokenData['used'] = true;
        $tokenData['used_at'] = date('c');
        $this->storage->savePasswordResetToken($user_id, $tokenData);
        
        // Log activity
        $this->storage->saveActivity($user_id, [
            'action' => 'password_reset',
            'description' => 'Password reset via email',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        return [
            'success' => true,
            'message' => 'Password has been reset successfully'
        ];
    }
    
    /**
     * Get site URL
     * 
     * @return string Site URL
     */
    private function getSiteUrl() {
        // Try to get from settings
        $settingsPath = __DIR__ . '/../../../settings/site.json';
        if (file_exists($settingsPath)) {
            $settings = json_decode(file_get_contents($settingsPath), true);
            if (!empty($settings['url'])) {
                return rtrim($settings['url'], '/');
            }
        }
        
        // Fallback to current request URL
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host;
    }
}
