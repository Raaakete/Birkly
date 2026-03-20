<?php
/**
 * EmailVerification - Email verification system
 * 
 * Handles:
 * - Verification token generation
 * - Email sending
 * - Token validation
 * - Admin approval workflow
 */

require_once __DIR__ . '/UserStorage.php';
require_once __DIR__ . '/UserService.php';

class EmailVerification {
    private $storage;
    private $service;
    
    public function __construct() {
        $this->storage = new UserStorage();
        $this->service = new UserService();
    }
    
    /**
     * Generate verification token
     * 
     * @param string $user_id User ID
     * @return string Token
     */
    public function generateToken($user_id) {
        // Generate secure random token
        $token = bin2hex(random_bytes(32));
        
        // Store token with expiration (24 hours)
        $tokenData = [
            'user_id' => $user_id,
            'token' => $token,
            'created_at' => date('c'),
            'expires_at' => date('c', strtotime('+24 hours')),
            'used' => false
        ];
        
        $this->storage->saveVerificationToken($user_id, $tokenData);
        
        return $token;
    }
    
    /**
     * Send verification email
     * 
     * @param string $user_id User ID
     * @param string $email User email
     * @param string $token Verification token
     * @return bool Success
     */
    public function sendVerificationEmail($user_id, $email, $token) {
        // Get site URL from settings or use default
        $siteUrl = $this->getSiteUrl();
        $verificationUrl = $siteUrl . '/verify-email?token=' . urlencode($token) . '&user=' . urlencode($user_id);
        
        // Get email template
        $subject = $this->getEmailSubject('verification');
        $body = $this->getEmailTemplate('verification', [
            'verification_url' => $verificationUrl,
            'token' => $token,
            'user_id' => $user_id
        ]);
        
        // Send email
        return $this->sendEmail($email, $subject, $body);
    }
    
    /**
     * Verify token and activate user
     * 
     * @param string $token Verification token
     * @param string $user_id User ID
     * @return array Result with status and message
     */
    public function verifyToken($token, $user_id) {
        // Get token data
        $tokenData = $this->storage->getVerificationToken($user_id);
        
        if (!$tokenData) {
            return [
                'success' => false,
                'message' => 'Invalid verification token'
            ];
        }
        
        // Check if token matches
        if ($tokenData['token'] !== $token) {
            return [
                'success' => false,
                'message' => 'Invalid verification token'
            ];
        }
        
        // Check if token is expired
        $expiresAt = strtotime($tokenData['expires_at']);
        if (time() > $expiresAt) {
            return [
                'success' => false,
                'message' => 'Verification token has expired. Please request a new one.'
            ];
        }
        
        // Check if token already used
        if ($tokenData['used']) {
            return [
                'success' => false,
                'message' => 'This verification link has already been used'
            ];
        }
        
        // Mark token as used
        $tokenData['used'] = true;
        $tokenData['verified_at'] = date('c');
        $this->storage->saveVerificationToken($user_id, $tokenData);
        
        // Update user as verified
        $user = $this->service->getUser($user_id);
        if ($user) {
            $user['verified'] = true;
            $user['verified_at'] = date('c');
            $this->service->updateUser($user_id, $user);
        }
        
        return [
            'success' => true,
            'message' => 'Email verified successfully'
        ];
    }
    
    /**
     * Resend verification email
     * 
     * @param string $user_id User ID
     * @return array Result with status and message
     */
    public function resendVerificationEmail($user_id) {
        $user = $this->service->getUser($user_id);
        if (!$user) {
            return [
                'success' => false,
                'message' => 'User not found'
            ];
        }
        
        // Check if already verified
        if (!empty($user['verified'])) {
            return [
                'success' => false,
                'message' => 'Email is already verified'
            ];
        }
        
        // Generate new token
        $token = $this->generateToken($user_id);
        
        // Send email
        $email = $user['email'] ?? '';
        if (empty($email)) {
            return [
                'success' => false,
                'message' => 'User email not found'
            ];
        }
        
        $sent = $this->sendVerificationEmail($user_id, $email, $token);
        
        if ($sent) {
            return [
                'success' => true,
                'message' => 'Verification email sent'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to send verification email'
            ];
        }
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
    
    /**
     * Get email subject
     * 
     * @param string $type Email type
     * @return string Subject
     */
    private function getEmailSubject($type) {
        $subjects = [
            'verification' => 'Verify your email address',
            'password_reset' => 'Reset your password',
            'welcome' => 'Welcome!'
        ];
        
        return $subjects[$type] ?? 'Email from ' . $this->getSiteName();
    }
    
    /**
     * Send password reset email
     * 
     * @param string $user_id User ID
     * @param string $email User email
     * @param string $token Reset token
     * @param string $resetUrl Reset URL
     * @return bool Success
     */
    public function sendPasswordResetEmail($user_id, $email, $token, $resetUrl) {
        $subject = $this->getEmailSubject('password_reset');
        $body = $this->getEmailTemplate('password_reset', [
            'reset_url' => $resetUrl,
            'token' => $token,
            'user_id' => $user_id
        ]);
        
        return $this->sendEmail($email, $subject, $body);
    }
    
    /**
     * Get email template
     * 
     * @param string $type Email type
     * @param array $data Template data
     * @return string Email body
     */
    private function getEmailTemplate($type, $data = []) {
        // Check for custom template
        $templatePath = __DIR__ . '/../templates/emails/' . $type . '.html';
        if (file_exists($templatePath)) {
            $template = file_get_contents($templatePath);
            // Replace placeholders
            foreach ($data as $key => $value) {
                $template = str_replace('{' . $key . '}', htmlspecialchars($value), $template);
            }
            return $template;
        }
        
        // Default templates
        switch ($type) {
            case 'verification':
                return $this->getDefaultVerificationTemplate($data);
            case 'password_reset':
                return $this->getDefaultPasswordResetTemplate($data);
            case 'welcome':
                return $this->getDefaultWelcomeTemplate($data);
            default:
                return 'Email from ' . $this->getSiteName();
        }
    }
    
    /**
     * Get default verification email template
     */
    private function getDefaultVerificationTemplate($data) {
        $url = $data['verification_url'] ?? '#';
        $siteName = $this->getSiteName();
        
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Verify your email</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h1 style="color: #2c3e50;">Verify your email address</h1>
        <p>Thank you for signing up for {$siteName}!</p>
        <p>Please click the button below to verify your email address:</p>
        <p style="text-align: center; margin: 30px 0;">
            <a href="{$url}" style="display: inline-block; padding: 12px 24px; background-color: #3498db; color: white; text-decoration: none; border-radius: 5px;">Verify Email</a>
        </p>
        <p>Or copy and paste this link into your browser:</p>
        <p style="word-break: break-all; color: #7f8c8d;">{$url}</p>
        <p style="color: #7f8c8d; font-size: 12px; margin-top: 30px;">
            This link will expire in 24 hours. If you didn't create an account, you can safely ignore this email.
        </p>
    </div>
</body>
</html>
HTML;
    }
    
    /**
     * Get default password reset email template
     */
    private function getDefaultPasswordResetTemplate($data) {
        $url = $data['reset_url'] ?? '#';
        $siteName = $this->getSiteName();
        
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reset your password</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h1 style="color: #2c3e50;">Reset your password</h1>
        <p>You requested to reset your password for {$siteName}.</p>
        <p>Click the button below to reset your password:</p>
        <p style="text-align: center; margin: 30px 0;">
            <a href="{$url}" style="display: inline-block; padding: 12px 24px; background-color: #e74c3c; color: white; text-decoration: none; border-radius: 5px;">Reset Password</a>
        </p>
        <p>Or copy and paste this link into your browser:</p>
        <p style="word-break: break-all; color: #7f8c8d;">{$url}</p>
        <p style="color: #7f8c8d; font-size: 12px; margin-top: 30px;">
            This link will expire in 1 hour. If you didn't request a password reset, you can safely ignore this email.
        </p>
    </div>
</body>
</html>
HTML;
    }
    
    /**
     * Get default welcome email template
     */
    private function getDefaultWelcomeTemplate($data) {
        $siteName = $this->getSiteName();
        
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Welcome</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h1 style="color: #2c3e50;">Welcome to {$siteName}!</h1>
        <p>Thank you for joining us. We're excited to have you on board!</p>
        <p>If you have any questions, feel free to reach out to us.</p>
        <p style="color: #7f8c8d; font-size: 12px; margin-top: 30px;">
            Best regards,<br>
            The {$siteName} Team
        </p>
    </div>
</body>
</html>
HTML;
    }
    
    /**
     * Get site name
     * 
     * @return string Site name
     */
    private function getSiteName() {
        $settingsPath = __DIR__ . '/../../../settings/site.json';
        if (file_exists($settingsPath)) {
            $settings = json_decode(file_get_contents($settingsPath), true);
            if (!empty($settings['name'])) {
                return $settings['name'];
            }
        }
        return 'Birkly';
    }
    
    /**
     * Send email
     * 
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $body Email body (HTML)
     * @return bool Success
     */
    private function sendEmail($to, $subject, $body) {
        // Get email settings
        $emailSettings = $this->getEmailSettings();
        
        // Use configured email method
        $method = $emailSettings['method'] ?? 'mail';
        
        switch ($method) {
            case 'smtp':
                return $this->sendViaSMTP($to, $subject, $body, $emailSettings);
            case 'sendmail':
                return $this->sendViaSendmail($to, $subject, $body, $emailSettings);
            case 'mail':
            default:
                return $this->sendViaMail($to, $subject, $body, $emailSettings);
        }
    }
    
    /**
     * Get email settings
     * 
     * @return array Email settings
     */
    private function getEmailSettings() {
        // Get from plugin settings
        $pluginSettings = $this->storage->getMetadata('email_settings', []);
        
        // Default settings
        return array_merge([
            'method' => 'mail',
            'from_email' => 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'),
            'from_name' => $this->getSiteName(),
            'smtp_host' => '',
            'smtp_port' => 587,
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_encryption' => 'tls'
        ], $pluginSettings);
    }
    
    /**
     * Send email via PHP mail()
     */
    private function sendViaMail($to, $subject, $body, $settings) {
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $settings['from_name'] . ' <' . $settings['from_email'] . '>',
            'Reply-To: ' . $settings['from_email']
        ];
        
        return mail($to, $subject, $body, implode("\r\n", $headers));
    }
    
    /**
     * Send email via Sendmail
     */
    private function sendViaSendmail($to, $subject, $body, $settings) {
        // Similar to mail() but uses sendmail
        return $this->sendViaMail($to, $subject, $body, $settings);
    }
    
    /**
     * Send email via SMTP
     */
    private function sendViaSMTP($to, $subject, $body, $settings) {
        // Check if PHPMailer or similar is available
        // For now, fall back to mail()
        // TODO: Implement proper SMTP sending
        error_log('SMTP email sending not yet implemented, falling back to mail()');
        return $this->sendViaMail($to, $subject, $body, $settings);
    }
}
