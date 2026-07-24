<?php
/**
 * TwoFactorAuth - 2FA/TOTP implementation
 * 
 * Handles:
 * - TOTP secret generation
 * - QR code generation for setup
 * - TOTP verification
 * - Backup codes generation and validation
 */

require_once __DIR__ . '/UserStorage.php';

class TwoFactorAuth {
    private $storage;
    private $issuer;
    
    // TOTP settings
    private $period = 30; // 30 seconds
    private $digits = 6; // 6 digits
    private $algorithm = 'sha1';
    
    public function __construct() {
        $this->storage = new UserStorage();
        $this->issuer = $this->getSiteName();
    }
    
    /**
     * Generate TOTP secret for user
     * 
     * @param string $user_id User ID
     * @param string $email User email (for QR code label)
     * @return array Secret and QR code data
     */
    public function generateSecret($user_id, $email) {
        // Generate random secret (base32 encoded, 16 bytes = 32 characters)
        $secret = $this->generateRandomSecret();
        
        // Save secret (not yet enabled)
        $this->storage->save2FASecret($user_id, [
            'secret' => $secret,
            'enabled' => false,
            'created_at' => date('c'),
            'backup_codes' => []
        ]);
        
        // Generate QR code data URL
        $qrCodeUrl = $this->generateQRCodeUrl($email, $secret);
        
        return [
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
            'manual_entry_key' => $this->formatSecretForDisplay($secret)
        ];
    }
    
    /**
     * Verify TOTP code
     * 
     * @param string $user_id User ID
     * @param string $code TOTP code entered by user
     * @return bool Valid
     */
    public function verifyCode($user_id, $code) {
        // Get user's 2FA secret
        $twoFactorData = $this->storage->get2FASecret($user_id);
        if (!$twoFactorData || !$twoFactorData['enabled']) {
            return false;
        }
        
        $secret = $twoFactorData['secret'];
        
        // Verify TOTP code
        return $this->verifyTOTP($secret, $code);
    }
    
    /**
     * Verify backup code
     * 
     * @param string $user_id User ID
     * @param string $code Backup code
     * @return bool Valid
     */
    public function verifyBackupCode($user_id, $code) {
        $twoFactorData = $this->storage->get2FASecret($user_id);
        if (!$twoFactorData) {
            return false;
        }
        
        $backupCodes = $twoFactorData['backup_codes'] ?? [];
        
        // Check if code exists and hasn't been used
        foreach ($backupCodes as $index => $backupCode) {
            if (isset($backupCode['code']) && $backupCode['code'] === $code && empty($backupCode['used'])) {
                // Mark as used
                $backupCodes[$index]['used'] = true;
                $backupCodes[$index]['used_at'] = date('c');
                
                // Update storage
                $twoFactorData['backup_codes'] = $backupCodes;
                $this->storage->save2FASecret($user_id, $twoFactorData);
                
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Enable 2FA for user (after verification)
     * 
     * @param string $user_id User ID
     * @param string $verification_code TOTP code to verify before enabling
     * @return array Result
     */
    public function enable2FA($user_id, $verification_code) {
        $twoFactorData = $this->storage->get2FASecret($user_id);
        if (!$twoFactorData) {
            return [
                'success' => false,
                'message' => '2FA secret not found. Please generate a new secret first.'
            ];
        }
        
        // Verify the code before enabling
        $secret = $twoFactorData['secret'];
        if (!$this->verifyTOTP($secret, $verification_code)) {
            return [
                'success' => false,
                'message' => 'Invalid verification code'
            ];
        }
        
        // Generate backup codes
        $backupCodes = $this->generateBackupCodes();
        
        // Enable 2FA
        $twoFactorData['enabled'] = true;
        $twoFactorData['enabled_at'] = date('c');
        $twoFactorData['backup_codes'] = $backupCodes;
        $this->storage->save2FASecret($user_id, $twoFactorData);
        
        return [
            'success' => true,
            'message' => '2FA enabled successfully',
            'backup_codes' => array_column($backupCodes, 'code')
        ];
    }
    
    /**
     * Disable 2FA for user
     * 
     * @param string $user_id User ID
     * @return bool Success
     */
    public function disable2FA($user_id) {
        $twoFactorData = $this->storage->get2FASecret($user_id);
        if (!$twoFactorData) {
            return false;
        }
        
        // Clear 2FA data
        $this->storage->delete2FASecret($user_id);
        
        return true;
    }
    
    /**
     * Generate new backup codes
     * 
     * @param string $user_id User ID
     * @return array New backup codes
     */
    public function regenerateBackupCodes($user_id) {
        $twoFactorData = $this->storage->get2FASecret($user_id);
        if (!$twoFactorData || !$twoFactorData['enabled']) {
            return [
                'success' => false,
                'message' => '2FA is not enabled'
            ];
        }
        
        // Generate new backup codes
        $backupCodes = $this->generateBackupCodes();
        $twoFactorData['backup_codes'] = $backupCodes;
        $this->storage->save2FASecret($user_id, $twoFactorData);
        
        return [
            'success' => true,
            'backup_codes' => array_column($backupCodes, 'code')
        ];
    }
    
    /**
     * Check if 2FA is enabled for user
     * 
     * @param string $user_id User ID
     * @return bool
     */
    public function is2FAEnabled($user_id) {
        $twoFactorData = $this->storage->get2FASecret($user_id);
        return !empty($twoFactorData['enabled']);
    }
    
    /**
     * Check if 2FA is required for user's role
     * 
     * @param string $role User role
     * @return bool
     */
    public function is2FARequired($role) {
        // Get plugin settings
        $settings = $this->storage->getMetadata('plugin_settings', []);
        $requiredRoles = $settings['2fa_required_roles'] ?? [];
        
        return in_array($role, $requiredRoles);
    }
    
    /**
     * Generate random secret (base32)
     */
    private function generateRandomSecret($length = 16) {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; // Base32 alphabet
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $secret;
    }
    
    /**
     * Generate QR code URL for authenticator app
     */
    private function generateQRCodeUrl($email, $secret) {
        // Format: otpauth://totp/Issuer:Email?secret=SECRET&issuer=Issuer
        $label = urlencode($this->issuer . ':' . $email);
        $secretParam = urlencode($secret);
        $issuerParam = urlencode($this->issuer);
        
        $otpauthUrl = "otpauth://totp/{$label}?secret={$secretParam}&issuer={$issuerParam}&algorithm={$this->algorithm}&digits={$this->digits}&period={$this->period}";
        
        // Generate QR code using online service or local library
        // For now, return the otpauth URL (frontend can generate QR code)
        // Or use a QR code library like phpqrcode
        return $this->generateQRCodeDataUrl($otpauthUrl);
    }
    
    /**
     * Generate QR code data URL
     * Uses simple approach - can be enhanced with QR code library
     */
    private function generateQRCodeDataUrl($data) {
        // For now, return the otpauth URL
        // Frontend can use a QR code library to generate the image
        // Or we can use a service like: https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=
        $qrServiceUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($data);
        return $qrServiceUrl;
    }
    
    /**
     * Format secret for manual entry (add spaces every 4 characters)
     */
    private function formatSecretForDisplay($secret) {
        return chunk_split($secret, 4, ' ');
    }
    
    /**
     * Verify TOTP code
     */
    private function verifyTOTP($secret, $code, $timeWindow = 1) {
        $time = floor(time() / $this->period);
        
        // Check current time and adjacent time windows (for clock skew)
        for ($i = -$timeWindow; $i <= $timeWindow; $i++) {
            $expectedCode = $this->generateTOTP($secret, $time + $i);
            if (hash_equals($expectedCode, $code)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Generate TOTP code for given time
     */
    private function generateTOTP($secret, $time) {
        // Decode base32 secret
        $secretBytes = $this->base32Decode($secret);
        
        // Pack time into 8-byte big-endian
        $timeBytes = pack('N*', 0) . pack('N*', $time);
        
        // Generate HMAC
        $hmac = hash_hmac($this->algorithm, $timeBytes, $secretBytes, true);
        
        // Dynamic truncation
        $offset = ord($hmac[19]) & 0x0F;
        $code = (
            ((ord($hmac[$offset + 0]) & 0x7F) << 24) |
            ((ord($hmac[$offset + 1]) & 0xFF) << 16) |
            ((ord($hmac[$offset + 2]) & 0xFF) << 8) |
            (ord($hmac[$offset + 3]) & 0xFF)
        ) % pow(10, $this->digits);
        
        // Pad with zeros
        return str_pad($code, $this->digits, '0', STR_PAD_LEFT);
    }
    
    /**
     * Base32 decode
     */
    private function base32Decode($data) {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $data = strtoupper($data);
        $data = rtrim($data, '=');
        
        $binary = '';
        for ($i = 0; $i < strlen($data); $i++) {
            $char = $data[$i];
            $value = strpos($chars, $char);
            if ($value === false) {
                continue;
            }
            $binary .= str_pad(decbin($value), 5, '0', STR_PAD_LEFT);
        }
        
        // Convert binary to bytes
        $bytes = '';
        for ($i = 0; $i < strlen($binary); $i += 8) {
            $byte = substr($binary, $i, 8);
            if (strlen($byte) === 8) {
                $bytes .= chr(bindec($byte));
            }
        }
        
        return $bytes;
    }
    
    /**
     * Generate backup codes
     */
    private function generateBackupCodes($count = 10) {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            // Generate 8-character alphanumeric code
            $code = strtoupper(bin2hex(random_bytes(4)));
            $codes[] = [
                'code' => $code,
                'used' => false,
                'created_at' => date('c')
            ];
        }
        return $codes;
    }
    
    /**
     * Get site name
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
}
