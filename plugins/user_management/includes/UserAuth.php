<?php
/**
 * UserAuth - Authentication and session management
 * 
 * Phase 2 implementation
 */

require_once __DIR__ . '/UserService.php';
require_once __DIR__ . '/UserStorage.php';

class UserAuth {
    private $service;
    private $storage;
    private $maxLoginAttempts;
    private $lockoutDuration;
    private $sessionTimeout;
    private $sessionScope;
    
    /**
     * @param string $sessionScope  'site' (default) or 'admin'
     */
    public function __construct($sessionScope = 'site') {
        $this->service = new UserService();
        $this->storage = new UserStorage();
        $this->sessionScope = ($sessionScope === 'admin') ? 'admin' : 'site';
        
        // Load settings
        $this->maxLoginAttempts = get_plugin_setting('user_management', 'max_login_attempts', 5);
        $this->lockoutDuration = get_plugin_setting('user_management', 'lockout_duration', 900);
        $this->sessionTimeout = get_plugin_setting('user_management', 'session_timeout', 3600);
    }
    
    /**
     * Start secure session if not already started
     */
    private function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            // Try to use Birkly session helper if available (ensures admin/site are decoupled)
            $sessionHelper = __DIR__ . '/../../../api/session.php';
            if (file_exists($sessionHelper)) {
                require_once $sessionHelper;
            }

            if (function_exists('birkly_session_start')) {
                birkly_session_start($this->sessionScope);
                return;
            }

            if ($this->sessionScope === 'site' && function_exists('birkly_site_session_start')) {
                birkly_site_session_start();
                return;
            }

            if ($this->sessionScope === 'admin' && function_exists('birkly_admin_session_start')) {
                birkly_admin_session_start();
                return;
            }

            // Fallback: still keep admin/site decoupled by using different session names
            $sessionName = ($this->sessionScope === 'admin') ? 'birkly_admin_sid' : 'birkly_site_sid';
            session_name($sessionName);

            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? null) == 443;
            $params = session_get_cookie_params();
            session_set_cookie_params([
                'lifetime' => $params['lifetime'],
                'path' => '/',
                'domain' => $params['domain'],
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
            session_start();
        }
    }
    
    /**
     * Get current logged-in user
     */
    public function getCurrentUser() {
        $this->startSession();
        
        // Check PHP session first (for compatibility with existing system)
        if (isset($_SESSION['user']) && isset($_SESSION['user']['id'])) {
            // Check session timeout
            if (isset($_SESSION['last_activity'])) {
                $now = time();
                if (($now - $_SESSION['last_activity']) > $this->sessionTimeout) {
                    // Session expired
                    $this->logout();
                    return null;
                }
                // Update last activity
                $_SESSION['last_activity'] = $now;
            }

            // Return the session-stored snapshot (avoid disk reads on every request)
            $user = $_SESSION['user'];
            if (is_array($user)) {
                unset($user['password_hash']);
            }
            return $user;
        }
        
        // Check plugin session storage
        $session_id = session_id();
        $sessionData = $this->storage->getSession($session_id);
        
        if (!$sessionData || !isset($sessionData['user_id'])) {
            return null;
        }
        
        // Check expiration
        if (isset($sessionData['expires_at'])) {
            $expires = new DateTime($sessionData['expires_at']);
            $now = new DateTime();
            if ($now > $expires) {
                // Session expired
                $this->storage->deleteSession($session_id);
                return null;
            }
        }
        
        // Return minimal user snapshot from session storage (avoid disk reads)
        $sessionData['last_activity'] = date('c');
        $this->storage->saveSession($session_id, $sessionData);

        return [
            'id' => $sessionData['user_id'],
            'email' => $sessionData['email'] ?? null,
            'name' => $sessionData['name'] ?? null
        ];
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        $this->startSession();

        // Fast path: if PHP session indicates a user, treat as logged in (and enforce timeout)
        if (isset($_SESSION['user']) && isset($_SESSION['user']['id'])) {
            if (isset($_SESSION['last_activity'])) {
                $now = time();
                if (($now - $_SESSION['last_activity']) > $this->sessionTimeout) {
                    $this->logout();
                    return false;
                }
                $_SESSION['last_activity'] = $now;
            }
            return true;
        }

        // Plugin session storage check (do NOT load the full user object here)
        $session_id = session_id();
        $sessionData = $this->storage->getSession($session_id);
        if (!$sessionData || !isset($sessionData['user_id'])) {
            return false;
        }

        // Check expiration
        if (isset($sessionData['expires_at'])) {
            $expires = new DateTime($sessionData['expires_at']);
            $now = new DateTime();
            if ($now > $expires) {
                $this->storage->deleteSession($session_id);
                return false;
            }
        }

        // Update last activity (best effort)
        $sessionData['last_activity'] = date('c');
        $this->storage->saveSession($session_id, $sessionData);
        return true;
    }
    
    /**
     * Check login attempts and lockout
     */
    private function checkLoginAttempts($email) {
        $attemptsKey = 'login_attempts_' . md5($email);
        $lockoutKey = 'lockout_' . md5($email);
        
        // Check if locked out
        $lockoutData = $this->storage->getMetadata($lockoutKey);
        if ($lockoutData) {
            $lockoutUntil = new DateTime($lockoutData['until']);
            $now = new DateTime();
            if ($now < $lockoutUntil) {
                $remaining = $lockoutUntil->getTimestamp() - $now->getTimestamp();
                throw new Exception('Account locked. Please try again in ' . ceil($remaining / 60) . ' minutes.');
            } else {
                // Lockout expired, clear it
                $this->storage->saveMetadata($lockoutKey, null);
            }
        }
        
        // Get attempt count
        $attempts = $this->storage->getMetadata($attemptsKey);
        $attemptCount = $attempts ? ($attempts['count'] ?? 0) : 0;
        
        return $attemptCount;
    }
    
    /**
     * Record failed login attempt
     */
    private function recordFailedAttempt($email) {
        $attemptsKey = 'login_attempts_' . md5($email);
        $lockoutKey = 'lockout_' . md5($email);
        
        $attempts = $this->storage->getMetadata($attemptsKey);
        $attemptCount = $attempts ? ($attempts['count'] ?? 0) : 0;
        $attemptCount++;
        
        // Save attempt count
        $this->storage->saveMetadata($attemptsKey, [
            'count' => $attemptCount,
            'last_attempt' => date('c')
        ]);
        
        // Lock account if max attempts reached
        if ($attemptCount >= $this->maxLoginAttempts) {
            $lockoutUntil = new DateTime();
            $lockoutUntil->modify('+' . $this->lockoutDuration . ' seconds');
            
            $this->storage->saveMetadata($lockoutKey, [
                'until' => $lockoutUntil->format('c'),
                'reason' => 'max_login_attempts'
            ]);
            
            // Log security event
            $this->storage->saveActivity('system', [
                'action' => 'account_locked',
                'description' => 'Account locked due to too many failed login attempts',
                'email' => $email
            ]);
        }
    }
    
    /**
     * Clear login attempts on successful login
     */
    private function clearLoginAttempts($email) {
        $attemptsKey = 'login_attempts_' . md5($email);
        $lockoutKey = 'lockout_' . md5($email);
        
        $this->storage->saveMetadata($attemptsKey, null);
        $this->storage->saveMetadata($lockoutKey, null);
    }
    
    /**
     * Login user (returns user or throws exception)
     * If 2FA is enabled, returns user with 'requires_2fa' flag
     */
    public function login($email, $password, $remember = false) {
        $this->startSession();
        
        try {
            // Check login attempts
            $this->checkLoginAttempts($email);
            
            // Get user by email
            $user = $this->service->getUserByEmail($email);
            if (!$user) {
                $this->recordFailedAttempt($email);
                throw new Exception('Invalid email or password');
            }
            
            // Check if user is active
            if (($user['status'] ?? 'active') !== 'active') {
                throw new Exception('Account is not active');
            }
            
            // Check if user is deleted
            if ($user['deleted'] ?? false) {
                throw new Exception('Account not found');
            }
            
            // Get user with password hash for verification
            $userWithPassword = $this->storage->getUser($user['id']);
            if (!$userWithPassword || !isset($userWithPassword['password_hash'])) {
                throw new Exception('Invalid email or password');
            }
            
            // Verify password
            if (!$this->service->verifyPassword($password, $userWithPassword['password_hash'])) {
                $this->recordFailedAttempt($email);
                throw new Exception('Invalid email or password');
            }
            
            // Check if 2FA is enabled
            require_once __DIR__ . '/TwoFactorAuth.php';
            $twoFactor = new TwoFactorAuth();
            $twoFactorEnabled = $twoFactor->is2FAEnabled($user['id']);
            
            // If 2FA is enabled, don't create session yet - require 2FA code
            if ($twoFactorEnabled) {
                // Clear failed attempts (password was correct)
                $this->clearLoginAttempts($email);
                
                // Store temporary login state (password verified, waiting for 2FA)
                $tempLoginKey = 'temp_login_' . bin2hex(random_bytes(16));
                $this->storage->saveMetadata($tempLoginKey, [
                    'user_id' => $user['id'],
                    'email' => $email,
                    'remember' => $remember,
                    'created_at' => date('c'),
                    'expires_at' => date('c', strtotime('+5 minutes')) // 5 minute window for 2FA
                ]);
                
                // Return user with 2FA requirement flag
                $returnUser = $user;
                unset($returnUser['password_hash']);
                $returnUser['requires_2fa'] = true;
                $returnUser['temp_login_key'] = $tempLoginKey;
                
                return $returnUser;
            }
            
            // Clear failed attempts
            $this->clearLoginAttempts($email);
            
            // Create session
            $session_id = session_id();
            $expiresAt = new DateTime();
            if ($remember) {
                // Remember me: 30 days
                $expiresAt->modify('+30 days');
            } else {
                // Normal session: use timeout setting
                $expiresAt->modify('+' . $this->sessionTimeout . ' seconds');
            }
            
            $sessionData = [
                'user_id' => $user['id'],
                'email' => $user['email'],
                'name' => $user['name'] ?? ($user['username'] ?? null),
                'created_at' => date('c'),
                'last_activity' => date('c'),
                'expires_at' => $expiresAt->format('c'),
                'remember' => $remember,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ];
            
            $this->storage->saveSession($session_id, $sessionData);
            
            // Also set PHP session for compatibility
            $_SESSION['user'] = $user;
            $_SESSION['last_activity'] = time();
            $_SESSION['user_management_session'] = true;
            
            // Log activity
            $this->storage->saveActivity($user['id'], [
                'action' => 'user_login',
                'description' => 'User logged in',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            
            // Fire hook
            if (function_exists('do_action')) {
                do_action('user_logged_in', $user);
            }
            
            // Return user (without password hash)
            $returnUser = $user;
            unset($returnUser['password_hash']);
            
            return $returnUser;
            
        } catch (Exception $e) {
            // Log failed login attempt
            if (function_exists('log_security_event')) {
                log_security_event('login_failed', [
                    'email' => $email,
                    'reason' => $e->getMessage(),
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
            }
            throw $e;
        }
    }
    
    /**
     * Complete login with 2FA verification
     * 
     * @param string $tempLoginKey Temporary login key from initial login
     * @param string $code 2FA code or backup code
     * @return array User data
     */
    public function completeLoginWith2FA($tempLoginKey, $code) {
        // Get temporary login data
        $tempLogin = $this->storage->getMetadata($tempLoginKey);
        if (!$tempLogin) {
            throw new Exception('Invalid or expired login session');
        }
        
        // Check expiration
        $expires = new DateTime($tempLogin['expires_at']);
        $now = new DateTime();
        if ($now > $expires) {
            $this->storage->saveMetadata($tempLoginKey, null);
            throw new Exception('Login session expired. Please login again.');
        }
        
        // Verify 2FA code
        require_once __DIR__ . '/TwoFactorAuth.php';
        $twoFactor = new TwoFactorAuth();
        
        $user_id = $tempLogin['user_id'];
        $valid = false;
        
        // Try TOTP code first
        if (strlen($code) === 6 && ctype_digit($code)) {
            $valid = $twoFactor->verifyCode($user_id, $code);
        }
        
        // If TOTP failed, try backup code
        if (!$valid) {
            $valid = $twoFactor->verifyBackupCode($user_id, $code);
        }
        
        if (!$valid) {
            throw new Exception('Invalid 2FA code');
        }
        
        // Get user
        $user = $this->service->getUser($user_id);
        if (!$user) {
            throw new Exception('User not found');
        }
        
        // Clear temporary login
        $this->storage->saveMetadata($tempLoginKey, null);
        
        // Create session
        $remember = $tempLogin['remember'] ?? false;
        $this->createSession($user_id, $remember);
        
        // Log activity
        $this->storage->saveActivity($user_id, [
            'action' => 'user_login',
            'description' => 'User logged in with 2FA',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        // Return user (without password hash)
        $returnUser = $user;
        unset($returnUser['password_hash']);
        
        return $returnUser;
    }
    
    /**
     * Logout user
     */
    public function logout() {
        $this->startSession();
        
        $user = $this->getCurrentUser();
        $session_id = session_id();
        
        // Delete plugin session
        if ($session_id) {
            $this->storage->deleteSession($session_id);
        }
        
        // Clear PHP session
        if (isset($_SESSION['user_management_session'])) {
            unset($_SESSION['user']);
            unset($_SESSION['user_management_session']);
            unset($_SESSION['last_activity']);
        }
        
        // Log activity
        if ($user) {
            $this->storage->saveActivity($user['id'], [
                'action' => 'user_logout',
                'description' => 'User logged out'
            ]);
            
            // Fire hook
            if (function_exists('do_action')) {
                do_action('user_logged_out', $user);
            }
        }
        
        // Destroy session if it was created by this plugin
        if (isset($_SESSION['user_management_session'])) {
            session_destroy();
        }
        
        return true;
    }
    
    /**
     * Check session validity
     */
    public function checkSession() {
        $user = $this->getCurrentUser();
        return $user !== null;
    }
}
