<?php
/**
 * Two-Factor Authentication (2FA) API
 * Implements TOTP (Time-based One-Time Password) using RFC 6238
 */

require_once __DIR__ . '/session.php';
haru_secure_session_start();

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/log.php';

// Only execute API logic if not included internally
if (!isset($GLOBALS['__HARU_INTERNAL_CALL'])) {
    header('Content-Type: application/json');

    // Check authentication
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
        exit;
    }

    $action = $_GET['action'] ?? '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../core/csrf.php';
    $rawInput = (isset($GLOBALS['__birkly_2fa_test_raw_body']) && is_string($GLOBALS['__birkly_2fa_test_raw_body']))
        ? $GLOBALS['__birkly_2fa_test_raw_body']
        : file_get_contents('php://input');
    $input = json_decode(is_string($rawInput) && $rawInput !== '' ? $rawInput : 'null', true);
    if (!is_array($input)) {
        $input = [];
    }
    $csrf = (string) ($input['_csrf'] ?? $_POST['_csrf'] ?? '');
    if (!validate_csrf_token($csrf)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }

    switch ($action) {
        case 'setup':
            $result = setup2FA($input);
            echo json_encode($result);
            exit;
        case 'verify':
            $result = verify2FA($input);
            echo json_encode($result);
            exit;
        case 'enable':
            $result = enable2FA($input);
            echo json_encode($result);
            exit;
        case 'disable':
            $result = disable2FA($input);
            echo json_encode($result);
            exit;
        case 'regenerate_backup_codes':
            $result = regenerateBackupCodes($input);
            echo json_encode($result);
            exit;
        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
            exit;
    }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        switch ($action) {
            case 'status':
                $result = get2FAStatus();
                echo json_encode($result);
                exit;
            case 'qr_code':
                $result = generateQRCode();
                echo json_encode($result);
                exit;
            default:
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
                exit;
        }
    }
}

function birkly_2fa_users_dir(): string
{
    if (isset($GLOBALS['__birkly_2fa_users_dir']) && is_string($GLOBALS['__birkly_2fa_users_dir']) && $GLOBALS['__birkly_2fa_users_dir'] !== '') {
        return $GLOBALS['__birkly_2fa_users_dir'];
    }
    $usersDir = __DIR__ . '/../users';
    if (!is_dir($usersDir)) {
        $usersDir = __DIR__ . '/../data/users';
    }
    return $usersDir;
}

/**
 * Setup 2FA - Generate secret and QR code
 */
function setup2FA($data) {
    $userEmail = $_SESSION['user']['email'] ?? '';
    if (empty($userEmail)) {
        return ['status' => 'error', 'message' => 'User email not found'];
    }
    
    // Generate a random secret (32 bytes = 256 bits)
    $secret = generateSecret();
    
    // Generate backup codes
    $backupCodes = generateBackupCodes();
    
    // Store temporarily in session (until verified and enabled)
    $_SESSION['2fa_setup'] = [
        'secret' => $secret,
        'backup_codes' => $backupCodes,
        'timestamp' => time()
    ];
    
    // Generate QR code URL
    $issuer = 'Birkly';
    $accountName = $userEmail;
    $qrUrl = generateQRCodeURL($secret, $issuer, $accountName);
    
    return [
        'status' => 'ok',
        'data' => [
            'secret' => $secret,
            'qr_url' => $qrUrl,
            'backup_codes' => $backupCodes,
            'manual_entry_key' => formatSecretForDisplay($secret)
        ]
    ];
}

/**
 * Verify 2FA code during setup
 */
function verify2FA($data) {
    $code = $data['code'] ?? '';
    $secret = $_SESSION['2fa_setup']['secret'] ?? '';
    
    if (empty($code) || empty($secret)) {
        return ['status' => 'error', 'message' => 'Code and secret are required'];
    }
    
    if (verifyTOTP($secret, $code)) {
        return ['status' => 'ok', 'message' => 'Code verified successfully'];
    } else {
        return ['status' => 'error', 'message' => 'Invalid code'];
    }
}

/**
 * Enable 2FA for user
 */
function enable2FA($data) {
    $userEmail = $_SESSION['user']['email'] ?? '';
    if (empty($userEmail)) {
        return ['status' => 'error', 'message' => 'User email not found'];
    }
    
    $setup = $_SESSION['2fa_setup'] ?? null;
    if (!$setup) {
        return ['status' => 'error', 'message' => '2FA setup not found. Please start setup again.'];
    }
    
    $code = $data['code'] ?? '';
    if (empty($code)) {
        return ['status' => 'error', 'message' => 'Verification code is required'];
    }
    
    // Verify the code before enabling
    if (!verifyTOTP($setup['secret'], $code)) {
        return ['status' => 'error', 'message' => 'Invalid verification code'];
    }
    
    // Save 2FA data to user file
    // Check if users directory exists at root level, otherwise try data/users
    // Use md5() to match the filename format used in users.php
    $usersDir = birkly_2fa_users_dir();
    $userId = md5(strtolower(trim($userEmail)));
    $userFile = $usersDir . '/' . $userId . '.json';
    
    if (file_exists($userFile)) {
        $userData = json_decode(file_get_contents($userFile), true);
        $userData['2fa'] = [
            'enabled' => true,
            'secret' => encryptSecret($setup['secret']),
            'backup_codes' => array_map('encryptSecret', $setup['backup_codes']),
            'enabled_at' => date('c'),
            'last_used' => null
        ];
        
        if (file_put_contents($userFile, json_encode($userData, JSON_PRETTY_PRINT))) {
            // Clear setup data
            unset($_SESSION['2fa_setup']);
            
            // Log event
            log_security_event('2fa_enabled', ['user' => $userEmail]);
            
            return [
                'status' => 'ok',
                'message' => '2FA enabled successfully',
                'data' => [
                    'backup_codes' => $setup['backup_codes']
                ]
            ];
        }
    }
    
    return ['status' => 'error', 'message' => 'Failed to enable 2FA'];
}

/**
 * Disable 2FA for user
 */
function disable2FA($data) {
    $userEmail = $_SESSION['user']['email'] ?? '';
    if (empty($userEmail)) {
        return ['status' => 'error', 'message' => 'User email not found'];
    }
    
    $code = $data['code'] ?? '';
    $password = $data['password'] ?? '';
    
    // Verify password first
    // Use md5() to match the filename format used in users.php
    $usersDir = birkly_2fa_users_dir();
    $userId = md5(strtolower(trim($userEmail)));
    $userFile = $usersDir . '/' . $userId . '.json';
    
    if (!file_exists($userFile)) {
        return ['status' => 'error', 'message' => 'User not found'];
    }
    
    $userData = json_decode(file_get_contents($userFile), true);
    
    // Verify password
    if (!password_verify($password, $userData['password_hash'] ?? '')) {
        return ['status' => 'error', 'message' => 'Invalid password'];
    }
    
    // Verify 2FA code if provided
    if (!empty($code) && isset($userData['2fa']['secret'])) {
        $secret = decryptSecret($userData['2fa']['secret']);
        if (!verifyTOTP($secret, $code)) {
            return ['status' => 'error', 'message' => 'Invalid 2FA code'];
        }
    }
    
    // Disable 2FA
    unset($userData['2fa']);
    
    if (file_put_contents($userFile, json_encode($userData, JSON_PRETTY_PRINT))) {
        // Log event
        log_security_event('2fa_disabled', ['user' => $userEmail]);
        
        return ['status' => 'ok', 'message' => '2FA disabled successfully'];
    }
    
    return ['status' => 'error', 'message' => 'Failed to disable 2FA'];
}

/**
 * Get 2FA status for current user
 */
function get2FAStatus() {
    $userEmail = $_SESSION['user']['email'] ?? '';
    if (empty($userEmail)) {
        return ['status' => 'error', 'message' => 'User email not found'];
    }
    
    // Use md5() to match the filename format used in users.php
    $usersDir = birkly_2fa_users_dir();
    $userId = md5(strtolower(trim($userEmail)));
    $userFile = $usersDir . '/' . $userId . '.json';
    
    if (!file_exists($userFile)) {
        return ['status' => 'ok', 'enabled' => false];
    }
    
    $userData = json_decode(file_get_contents($userFile), true);
    $enabled = isset($userData['2fa']['enabled']) && $userData['2fa']['enabled'];
    
    return [
        'status' => 'ok',
        'enabled' => $enabled,
        'enabled_at' => $userData['2fa']['enabled_at'] ?? null,
        'backup_codes_count' => count($userData['2fa']['backup_codes'] ?? [])
    ];
}

/**
 * Generate QR code URL for setup
 */
function generateQRCode() {
    $setup = $_SESSION['2fa_setup'] ?? null;
    if (!$setup) {
        return ['status' => 'error', 'message' => '2FA setup not found'];
    }
    
    $userEmail = $_SESSION['user']['email'] ?? '';
    $issuer = 'Birkly';
    $qrUrl = generateQRCodeURL($setup['secret'], $issuer, $userEmail);
    
    return [
        'status' => 'ok',
        'qr_url' => $qrUrl
    ];
}

/**
 * Regenerate backup codes
 */
function regenerateBackupCodes($data) {
    $userEmail = $_SESSION['user']['email'] ?? '';
    if (empty($userEmail)) {
        return ['status' => 'error', 'message' => 'User email not found'];
    }
    
    $code = $data['code'] ?? '';
    $password = $data['password'] ?? '';
    
    // Use md5() to match the filename format used in users.php
    $usersDir = birkly_2fa_users_dir();
    $userId = md5(strtolower(trim($userEmail)));
    $userFile = $usersDir . '/' . $userId . '.json';
    
    if (!file_exists($userFile)) {
        return ['status' => 'error', 'message' => 'User not found'];
    }
    
    $userData = json_decode(file_get_contents($userFile), true);
    
    if (!isset($userData['2fa']['enabled']) || !$userData['2fa']['enabled']) {
        return ['status' => 'error', 'message' => '2FA is not enabled'];
    }
    
    // Verify password
    if (!password_verify($password, $userData['password_hash'] ?? '')) {
        return ['status' => 'error', 'message' => 'Invalid password'];
    }
    
    // Verify 2FA code
    if (!empty($code)) {
        $secret = decryptSecret($userData['2fa']['secret']);
        if (!verifyTOTP($secret, $code)) {
            return ['status' => 'error', 'message' => 'Invalid 2FA code'];
        }
    }
    
    // Generate new backup codes
    $newBackupCodes = generateBackupCodes();
    $userData['2fa']['backup_codes'] = array_map('encryptSecret', $newBackupCodes);
    
    if (file_put_contents($userFile, json_encode($userData, JSON_PRETTY_PRINT))) {
        log_security_event('2fa_backup_codes_regenerated', ['user' => $userEmail]);
        
        return [
            'status' => 'ok',
            'message' => 'Backup codes regenerated',
            'data' => [
                'backup_codes' => $newBackupCodes
            ]
        ];
    }
    
    return ['status' => 'error', 'message' => 'Failed to regenerate backup codes'];
}

/**
 * Verify TOTP code during login
 */
function verify2FALogin($userEmail, $code, $isBackupCode = false) {
    // Use md5() to match the filename format used in users.php
    $usersDir = birkly_2fa_users_dir();
    $userId = md5(strtolower(trim($userEmail)));
    $userFile = $usersDir . '/' . $userId . '.json';
    
    if (!file_exists($userFile)) {
        return false;
    }
    
    $userData = json_decode(file_get_contents($userFile), true);
    
    if (!isset($userData['2fa']['enabled']) || !$userData['2fa']['enabled']) {
        return false; // 2FA not enabled
    }
    
    if ($isBackupCode) {
        // Check backup codes
        $backupCodes = array_map('decryptSecret', $userData['2fa']['backup_codes'] ?? []);
        $codeIndex = array_search($code, $backupCodes);
        
        if ($codeIndex !== false) {
            // Remove used backup code
            unset($backupCodes[$codeIndex]);
            $userData['2fa']['backup_codes'] = array_map('encryptSecret', array_values($backupCodes));
            $userData['2fa']['last_used'] = date('c');
            file_put_contents($userFile, json_encode($userData, JSON_PRETTY_PRINT));
            
            log_security_event('2fa_backup_code_used', ['user' => $userEmail]);
            return true;
        }
        
        return false;
    } else {
        // Verify TOTP code
        $secret = decryptSecret($userData['2fa']['secret']);
        if (verifyTOTP($secret, $code)) {
            $userData['2fa']['last_used'] = date('c');
            file_put_contents($userFile, json_encode($userData, JSON_PRETTY_PRINT));
            
            log_security_event('2fa_verified', ['user' => $userEmail]);
            return true;
        }
        
        return false;
    }
}

/**
 * Generate TOTP secret
 */
function generateSecret($length = 32) {
    return base32_encode(random_bytes($length));
}

/**
 * Generate backup codes
 */
function generateBackupCodes($count = 10) {
    $codes = [];
    for ($i = 0; $i < $count; $i++) {
        $codes[] = strtoupper(bin2hex(random_bytes(4))); // 8-character codes
    }
    return $codes;
}

/**
 * Verify TOTP code
 */
function verifyTOTP($secret, $code, $window = 1) {
    $code = trim($code);
    if (strlen($code) !== 6 || !ctype_digit($code)) {
        return false;
    }
    
    $currentTime = time();
    $timeStep = 30; // 30-second windows
    
    // Check current time step and surrounding windows
    for ($i = -$window; $i <= $window; $i++) {
        $time = $currentTime + ($i * $timeStep);
        $expectedCode = generateTOTP($secret, $time);
        
        if (hash_equals($expectedCode, $code)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Generate TOTP code for a given time
 */
function generateTOTP($secret, $time) {
    $timeStep = 30;
    $timeCounter = floor($time / $timeStep);
    
    $secretBytes = base32_decode($secret);
    $counter = pack('N*', 0) . pack('N*', $timeCounter);
    
    $hash = hash_hmac('sha1', $counter, $secretBytes, true);
    $offset = ord($hash[19]) & 0x0F;
    $code = (
        ((ord($hash[$offset]) & 0x7F) << 24) |
        ((ord($hash[$offset + 1]) & 0xFF) << 16) |
        ((ord($hash[$offset + 2]) & 0xFF) << 8) |
        (ord($hash[$offset + 3]) & 0xFF)
    ) % 1000000;
    
    return str_pad((string)$code, 6, '0', STR_PAD_LEFT);
}

/**
 * Generate QR code URL
 */
function generateQRCodeURL($secret, $issuer, $accountName) {
    $encodedIssuer = rawurlencode($issuer);
    $encodedAccount = rawurlencode($accountName);
    return "otpauth://totp/{$encodedIssuer}:{$encodedAccount}?secret={$secret}&issuer={$encodedIssuer}&algorithm=SHA1&digits=6&period=30";
}

/**
 * Format secret for manual entry
 */
function formatSecretForDisplay($secret) {
    // Add spaces every 4 characters for readability
    return chunk_split($secret, 4, ' ');
}

/**
 * Base32 encoding
 */
function base32_encode($data) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $encoded = '';
    $bits = 0;
    $value = 0;
    
    for ($i = 0; $i < strlen($data); $i++) {
        $value = ($value << 8) | ord($data[$i]);
        $bits += 8;
        
        while ($bits >= 5) {
            $encoded .= $chars[($value >> ($bits - 5)) & 0x1F];
            $bits -= 5;
        }
    }
    
    if ($bits > 0) {
        $encoded .= $chars[($value << (5 - $bits)) & 0x1F];
    }
    
    return $encoded;
}

/**
 * Base32 decoding
 */
function base32_decode($data) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $data = strtoupper($data);
    $data = preg_replace('/[^A-Z2-7]/', '', $data);
    
    $decoded = '';
    $bits = 0;
    $value = 0;
    
    for ($i = 0; $i < strlen($data); $i++) {
        $charValue = strpos($chars, $data[$i]);
        if ($charValue === false) continue;
        
        $value = ($value << 5) | $charValue;
        $bits += 5;
        
        while ($bits >= 8) {
            $decoded .= chr(($value >> ($bits - 8)) & 0xFF);
            $bits -= 8;
        }
    }
    
    return $decoded;
}

/**
 * Encrypt secret (simple obfuscation - in production, use proper encryption)
 */
function encryptSecret($secret) {
    // Simple XOR encryption with a key derived from system
    $key = hash('sha256', __DIR__ . '2fa_secret_key');
    $encrypted = '';
    for ($i = 0; $i < strlen($secret); $i++) {
        $encrypted .= chr(ord($secret[$i]) ^ ord($key[$i % strlen($key)]));
    }
    return base64_encode($encrypted);
}

/**
 * Decrypt secret
 */
function decryptSecret($encrypted) {
    $key = hash('sha256', __DIR__ . '2fa_secret_key');
    $data = base64_decode($encrypted);
    $decrypted = '';
    for ($i = 0; $i < strlen($data); $i++) {
        $decrypted .= chr(ord($data[$i]) ^ ord($key[$i % strlen($key)]));
    }
    return $decrypted;
}

/**
 * Sanitize filename
 */
function sanitizeFilename($filename) {
    return preg_replace('/[^a-z0-9._-]/i', '_', $filename);
}

