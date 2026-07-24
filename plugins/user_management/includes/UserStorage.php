<?php
/**
 * UserStorage - Isolated data storage for user management plugin
 * 
 * All data stored in data/plugins/user_management/ to ensure safety from system updates
 */

class UserStorage {
    private $baseDir;
    private $encryptionKey;
    
    public function __construct() {
        // Data directory: data/plugins/user_management/
        // __DIR__ is plugins/user_management/includes/
        // Go up to plugins/user_management/, then up to root, then to data/plugins/user_management/
        $pluginDir = dirname(__DIR__);
        $rootDir = dirname(dirname($pluginDir));
        $this->baseDir = $rootDir . '/data/plugins/user_management';
        
        // Ensure data directory exists
        $this->ensureDirectoryStructure();
        
        // Get or create encryption key
        $this->encryptionKey = $this->getEncryptionKey();
    }
    
    /**
     * Ensure all required directories exist
     */
    private function ensureDirectoryStructure() {
        $dirs = [
            $this->baseDir,
            $this->baseDir . '/users',
            $this->baseDir . '/permissions',
            $this->baseDir . '/tiers',
            $this->baseDir . '/groups',
            $this->baseDir . '/metadata',
            $this->baseDir . '/sessions',
            $this->baseDir . '/invites',
            $this->baseDir . '/activity',
            $this->baseDir . '/verification_tokens',
            $this->baseDir . '/2fa',
            $this->baseDir . '/password_reset_tokens'
        ];
        
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }
    
    /**
     * Get or create encryption key for sensitive data
     */
    private function getEncryptionKey() {
        $keyFile = $this->baseDir . '/metadata/encryption_key.txt';
        
        if (file_exists($keyFile)) {
            return trim(file_get_contents($keyFile));
        }
        
        // Generate new key
        $key = bin2hex(random_bytes(32));
        file_put_contents($keyFile, $key);
        chmod($keyFile, 0600); // Read-only for owner
        
        return $key;
    }
    
    /**
     * Simple encryption for sensitive fields
     */
    private function encrypt($data) {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $this->encryptionKey, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Simple decryption for sensitive fields
     */
    private function decrypt($encryptedData) {
        $data = base64_decode($encryptedData);
        $ivLength = openssl_cipher_iv_length('aes-256-cbc');
        $iv = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);
        return openssl_decrypt($encrypted, 'aes-256-cbc', $this->encryptionKey, 0, $iv);
    }
    
    /**
     * Save user data
     */
    public function saveUser($user_id, $userData) {
        $file = $this->baseDir . '/users/' . $user_id . '.json';
        
        // Encrypt sensitive fields
        $encryptedData = $userData;
        if (isset($encryptedData['email'])) {
            $encryptedData['email'] = $this->encrypt($encryptedData['email']);
        }
        if (isset($encryptedData['password_hash'])) {
            $encryptedData['password_hash'] = $this->encrypt($encryptedData['password_hash']);
        }
        if (isset($encryptedData['phone'])) {
            $encryptedData['phone'] = $this->encrypt($encryptedData['phone']);
        }
        
        // Add metadata
        $encryptedData['_encrypted'] = true;
        $encryptedData['_updated_at'] = date('c');
        
        return file_put_contents($file, json_encode($encryptedData, JSON_PRETTY_PRINT)) !== false;
    }
    
    /**
     * Get user data
     */
    public function getUser($user_id) {
        // Defensive: prevent path traversal / unexpected IDs
        if (!is_string($user_id) || !preg_match('/^[a-zA-Z0-9_-]+$/', $user_id)) {
            return null;
        }

        $file = $this->baseDir . '/users/' . $user_id . '.json';
        
        if (!file_exists($file)) {
            return null;
        }

        // Defensive: avoid loading unexpectedly large files into memory
        $size = @filesize($file);
        if ($size !== false && $size > 1024 * 1024) { // 1MB hard cap
            error_log("UserStorage::getUser refused large user file: {$file} ({$size} bytes)");
            return null;
        }
        
        // Read with hard cap to avoid memory exhaustion even if file is unexpectedly large
        $raw = @file_get_contents($file, false, null, 0, 1024 * 1024);
        if ($raw === false) {
            return null;
        }
        $data = json_decode($raw, true);
        if (!$data) {
            return null;
        }
        
        // Decrypt sensitive fields
        if (isset($data['_encrypted']) && $data['_encrypted']) {
            if (isset($data['email'])) {
                $data['email'] = $this->decrypt($data['email']);
            }
            if (isset($data['password_hash'])) {
                $data['password_hash'] = $this->decrypt($data['password_hash']);
            }
            if (isset($data['phone'])) {
                $data['phone'] = $this->decrypt($data['phone']);
            }
            unset($data['_encrypted']);
        }
        
        return $data;
    }
    
    /**
     * Get user by email (requires scanning - use sparingly)
     */
    public function getUserByEmail($email) {
        $usersDir = $this->baseDir . '/users';
        if (!is_dir($usersDir)) {
            return null;
        }
        
        $files = glob($usersDir . '/*.json');
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if (!$data) continue;
            
            // Decrypt email for comparison
            if (isset($data['_encrypted']) && $data['_encrypted'] && isset($data['email'])) {
                $decryptedEmail = $this->decrypt($data['email']);
                if ($decryptedEmail === $email) {
                    // Return full decrypted user (but ignore soft-deleted users)
                    $user = $this->getUser(basename($file, '.json'));
                    if ($user && ($user['deleted'] ?? false)) {
                        continue;
                    }
                    return $user;
                }
            } elseif (isset($data['email']) && $data['email'] === $email) {
                // For unencrypted legacy users, still honor soft delete if present
                if (($data['deleted'] ?? false)) {
                    continue;
                }
                return $data;
            }
        }
        
        return null;
    }
    
    /**
     * Delete user (soft delete - mark as deleted)
     */
    public function deleteUser($user_id) {
        $user = $this->getUser($user_id);
        if (!$user) {
            return false;
        }
        
        $user['deleted'] = true;
        $user['deleted_at'] = date('c');
        
        return $this->saveUser($user_id, $user);
    }

    /**
     * Restore a soft-deleted user (deleted=false).
     */
    public function restoreUser($user_id) {
        $user = $this->getUser($user_id);
        if (!$user) {
            return false;
        }

        $user['deleted'] = false;
        unset($user['deleted_at']);

        return $this->saveUser($user_id, $user);
    }

    /**
     * Permanently delete a single user (hard delete) and related files.
     */
    public function purgeUser($user_id) {
        $file = $this->baseDir . '/users/' . $user_id . '.json';
        if (file_exists($file)) {
            @unlink($file);
        } else {
            return false;
        }

        // Remove related data (best-effort)
        $related = [
            $this->baseDir . '/permissions/' . $user_id . '.json',
            $this->baseDir . '/activity/' . $user_id . '.json',
            $this->baseDir . '/verification_tokens/' . $user_id . '.json',
            $this->baseDir . '/2fa/' . $user_id . '.json',
            $this->baseDir . '/sessions/' . $user_id . '.json',
            $this->baseDir . '/password_reset_tokens/' . $user_id . '.json',
        ];
        foreach ($related as $path) {
            if (file_exists($path)) {
                @unlink($path);
            }
        }

        return true;
    }

    /**
     * Permanently delete ALL soft-deleted users and related files.
     *
     * Delete in this plugin is a soft-delete (deleted=true). This method lets admins
     * purge those records to keep storage clean.
     *
     * @return array{purged:int, user_ids:string[]}
     */
    public function purgeDeletedUsers() {
        $usersDir = $this->baseDir . '/users';
        if (!is_dir($usersDir)) {
            return ['purged' => 0, 'user_ids' => []];
        }

        $purgedIds = [];
        $files = glob($usersDir . '/*.json') ?: [];

        foreach ($files as $file) {
            $id = basename($file, '.json');

            // Read raw file (deleted flag is not encrypted)
            $raw = json_decode(@file_get_contents($file), true);
            if (!$raw) {
                continue;
            }

            if (!($raw['deleted'] ?? false)) {
                continue;
            }

            // Remove primary user record
            @unlink($file);

            // Remove related data (best-effort)
            $related = [
                $this->baseDir . '/permissions/' . $id . '.json',
                $this->baseDir . '/activity/' . $id . '.json',
                $this->baseDir . '/verification_tokens/' . $id . '.json',
                $this->baseDir . '/2fa/' . $id . '.json',
                $this->baseDir . '/sessions/' . $id . '.json',
                $this->baseDir . '/password_reset_tokens/' . $id . '.json',
            ];
            foreach ($related as $path) {
                if (file_exists($path)) {
                    @unlink($path);
                }
            }

            $purgedIds[] = $id;
        }

        return ['purged' => count($purgedIds), 'user_ids' => $purgedIds];
    }
    
    /**
     * List all users (with optional filters)
     */
    public function listUsers($filters = []) {
        $usersDir = $this->baseDir . '/users';
        if (!is_dir($usersDir)) {
            return [];
        }
        
        $files = glob($usersDir . '/*.json');
        $users = [];
        
        foreach ($files as $file) {
            $user = $this->getUser(basename($file, '.json'));
            if (!$user) continue;
            
            // Apply filters
            if (isset($filters['deleted']) && ($user['deleted'] ?? false) !== $filters['deleted']) {
                continue;
            }
            if (isset($filters['role']) && ($user['role'] ?? '') !== $filters['role']) {
                continue;
            }
            if (isset($filters['status']) && ($user['status'] ?? '') !== $filters['status']) {
                continue;
            }
            
            $users[] = $user;
        }
        
        return $users;
    }
    
    /**
     * Save permissions
     */
    public function savePermissions($user_id, $permissions) {
        $file = $this->baseDir . '/permissions/' . $user_id . '.json';
        $data = [
            'user_id' => $user_id,
            'permissions' => $permissions,
            'updated_at' => date('c')
        ];
        
        return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT)) !== false;
    }
    
    /**
     * Get permissions
     */
    public function getPermissions($user_id) {
        $file = $this->baseDir . '/permissions/' . $user_id . '.json';
        
        if (!file_exists($file)) {
            return null;
        }
        
        return json_decode(file_get_contents($file), true);
    }
    
    /**
     * Save tier definition
     */
    public function saveTier($tier_id, $tierData) {
        $file = $this->baseDir . '/tiers/' . $tier_id . '.json';
        $tierData['tier_id'] = $tier_id;
        $tierData['updated_at'] = date('c');
        
        return file_put_contents($file, json_encode($tierData, JSON_PRETTY_PRINT)) !== false;
    }
    
    /**
     * Get tier definition
     */
    public function getTier($tier_id) {
        $file = $this->baseDir . '/tiers/' . $tier_id . '.json';
        
        if (!file_exists($file)) {
            return null;
        }
        
        return json_decode(file_get_contents($file), true);
    }
    
    /**
     * List all tiers
     */
    public function listTiers() {
        $tiersDir = $this->baseDir . '/tiers';
        if (!is_dir($tiersDir)) {
            return [];
        }
        
        $files = glob($tiersDir . '/*.json');
        $tiers = [];
        
        foreach ($files as $file) {
            $tierId = basename($file, '.json');
            $tier = json_decode(file_get_contents($file), true);
            if ($tier) {
                // Ensure tier has an 'id' field
                if (!isset($tier['id'])) {
                    $tier['id'] = $tierId;
                }
                $tiers[] = $tier;
            }
        }
        
        return $tiers;
    }
    
    /**
     * Delete tier
     */
    public function deleteTier($tier_id) {
        $file = $this->baseDir . '/tiers/' . $tier_id . '.json';
        if (file_exists($file)) {
            return unlink($file);
        }
        return false;
    }
    
    /**
     * Save session data
     */
    public function saveSession($session_id, $sessionData) {
        $file = $this->baseDir . '/sessions/' . $session_id . '.json';
        $sessionData['session_id'] = $session_id;
        $sessionData['updated_at'] = date('c');
        
        return file_put_contents($file, json_encode($sessionData, JSON_PRETTY_PRINT)) !== false;
    }
    
    /**
     * Get session data
     */
    public function getSession($session_id) {
        $file = $this->baseDir . '/sessions/' . $session_id . '.json';
        
        if (!file_exists($file)) {
            return null;
        }
        
        $data = json_decode(file_get_contents($file), true);
        
        // Check expiration
        if (isset($data['expires_at'])) {
            $expires = new DateTime($data['expires_at']);
            $now = new DateTime();
            if ($now > $expires) {
                // Session expired, delete it
                unlink($file);
                return null;
            }
        }
        
        return $data;
    }
    
    /**
     * Delete session
     */
    public function deleteSession($session_id) {
        $file = $this->baseDir . '/sessions/' . $session_id . '.json';
        if (file_exists($file)) {
            return unlink($file);
        }
        return false;
    }
    
    /**
     * Clean expired sessions
     */
    public function cleanExpiredSessions() {
        $sessionsDir = $this->baseDir . '/sessions';
        if (!is_dir($sessionsDir)) {
            return 0;
        }
        
        $files = glob($sessionsDir . '/*.json');
        $cleaned = 0;
        $now = new DateTime();
        
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && isset($data['expires_at'])) {
                $expires = new DateTime($data['expires_at']);
                if ($now > $expires) {
                    unlink($file);
                    $cleaned++;
                }
            }
        }
        
        return $cleaned;
    }
    
    /**
     * Save invite
     */
    public function saveInvite($invite_code, $inviteData) {
        $file = $this->baseDir . '/invites/' . $invite_code . '.json';
        $inviteData['invite_code'] = $invite_code;
        $inviteData['created_at'] = $inviteData['created_at'] ?? date('c');
        
        return file_put_contents($file, json_encode($inviteData, JSON_PRETTY_PRINT)) !== false;
    }
    
    /**
     * Get invite
     */
    public function getInvite($invite_code) {
        $file = $this->baseDir . '/invites/' . $invite_code . '.json';
        
        if (!file_exists($file)) {
            return null;
        }
        
        $data = json_decode(file_get_contents($file), true);
        
        // Check expiration
        if (isset($data['expires_at'])) {
            $expires = new DateTime($data['expires_at']);
            $now = new DateTime();
            if ($now > $expires) {
                return null;
            }
        }
        
        return $data;
    }
    
    /**
     * Save activity log entry
     */
    public function saveActivity($user_id, $activity) {
        $file = $this->baseDir . '/activity/' . $user_id . '.json';
        
        $activities = [];
        if (file_exists($file)) {
            $activities = json_decode(file_get_contents($file), true) ?: [];
        }
        
        $activities[] = array_merge($activity, [
            'timestamp' => date('c'),
            'user_id' => $user_id
        ]);
        
        // Keep only last 1000 activities per user
        if (count($activities) > 1000) {
            $activities = array_slice($activities, -1000);
        }
        
        return file_put_contents($file, json_encode($activities, JSON_PRETTY_PRINT)) !== false;
    }
    
    /**
     * Get activity log for user
     */
    public function getActivity($user_id, $limit = 100) {
        $file = $this->baseDir . '/activity/' . $user_id . '.json';
        
        if (!file_exists($file)) {
            return [];
        }
        
        $activities = json_decode(file_get_contents($file), true) ?: [];
        
        // Return most recent activities
        return array_slice(array_reverse($activities), 0, $limit);
    }
    
    /**
     * Get plugin metadata
     */
    public function getMetadata($key, $default = null) {
        $file = $this->baseDir . '/metadata/' . $key . '.json';
        
        if (!file_exists($file)) {
            return $default;
        }
        
        return json_decode(file_get_contents($file), true);
    }
    
    /**
     * Save plugin metadata
     */
    public function saveMetadata($key, $value) {
        $file = $this->baseDir . '/metadata/' . $key . '.json';
        $this->ensureDirectoryStructure();
        return file_put_contents($file, json_encode($value, JSON_PRETTY_PRINT)) !== false;
    }
    
    /**
     * Read plugin metadata
     */
    public function readMetadata($key) {
        $file = $this->baseDir . '/metadata/' . $key . '.json';
        
        if (!file_exists($file)) {
            return null;
        }
        
        $content = file_get_contents($file);
        return json_decode($content, true);
    }
    
    /**
     * Save group data
     */
    public function saveGroup($group_id, $groupData) {
        $file = $this->baseDir . '/groups/' . $group_id . '.json';
        $this->ensureDirectoryStructure();
        return file_put_contents($file, json_encode($groupData, JSON_PRETTY_PRINT)) !== false;
    }
    
    /**
     * Get group data
     */
    public function getGroup($group_id) {
        $file = $this->baseDir . '/groups/' . $group_id . '.json';
        
        if (!file_exists($file)) {
            return null;
        }
        
        return json_decode(file_get_contents($file), true);
    }
    
    /**
     * Delete group
     */
    public function deleteGroup($group_id) {
        $file = $this->baseDir . '/groups/' . $group_id . '.json';
        if (file_exists($file)) {
            return unlink($file);
        }
        return false;
    }
    
    /**
     * List all groups
     */
    public function listGroups($filters = []) {
        $groupsDir = $this->baseDir . '/groups';
        if (!is_dir($groupsDir)) {
            return [];
        }
        
        $files = glob($groupsDir . '/*.json');
        $groups = [];
        
        foreach ($files as $file) {
            $group = json_decode(file_get_contents($file), true);
            if (!$group) continue;
            
            // Apply filters
            if (isset($filters['owner_id']) && ($group['owner_id'] ?? null) !== $filters['owner_id']) {
                continue;
            }
            if (isset($filters['type']) && ($group['type'] ?? '') !== $filters['type']) {
                continue;
            }
            
            $groups[] = $group;
        }
        
        return $groups;
    }
    
    /**
     * Save 2FA secret
     */
    public function save2FASecret($user_id, $twoFactorData) {
        $file = $this->baseDir . '/2fa/' . $user_id . '.json';
        $this->ensureDirectoryStructure();
        $twoFactorData['user_id'] = $user_id;
        $twoFactorData['updated_at'] = date('c');
        return file_put_contents($file, json_encode($twoFactorData, JSON_PRETTY_PRINT)) !== false;
    }
    
    /**
     * Get 2FA secret
     */
    public function get2FASecret($user_id) {
        $file = $this->baseDir . '/2fa/' . $user_id . '.json';
        
        if (!file_exists($file)) {
            return null;
        }
        
        return json_decode(file_get_contents($file), true);
    }
    
    /**
     * Delete 2FA secret
     */
    public function delete2FASecret($user_id) {
        $file = $this->baseDir . '/2fa/' . $user_id . '.json';
        if (file_exists($file)) {
            return unlink($file);
        }
        return false;
    }
    
    /**
     * Save verification token
     */
    public function saveVerificationToken($user_id, $tokenData) {
        $file = $this->baseDir . '/verification_tokens/' . $user_id . '.json';
        return file_put_contents($file, json_encode($tokenData, JSON_PRETTY_PRINT)) !== false;
    }
    
    /**
     * Get verification token
     */
    public function getVerificationToken($user_id) {
        $file = $this->baseDir . '/verification_tokens/' . $user_id . '.json';
        
        if (!file_exists($file)) {
            return null;
        }
        
        return json_decode(file_get_contents($file), true);
    }
    
    /**
     * Delete verification token
     */
    public function deleteVerificationToken($user_id) {
        $file = $this->baseDir . '/verification_tokens/' . $user_id . '.json';
        if (file_exists($file)) {
            return unlink($file);
        }
        return false;
    }
    
    /**
     * Save password reset token
     */
    public function savePasswordResetToken($user_id, $tokenData) {
        $file = $this->baseDir . '/password_reset_tokens/' . $user_id . '.json';
        $this->ensureDirectoryStructure();
        return file_put_contents($file, json_encode($tokenData, JSON_PRETTY_PRINT)) !== false;
    }
    
    /**
     * Get password reset token
     */
    public function getPasswordResetToken($user_id) {
        $file = $this->baseDir . '/password_reset_tokens/' . $user_id . '.json';
        
        if (!file_exists($file)) {
            return null;
        }
        
        return json_decode(file_get_contents($file), true);
    }
    
    /**
     * Delete password reset token
     */
    public function deletePasswordResetToken($user_id) {
        $file = $this->baseDir . '/password_reset_tokens/' . $user_id . '.json';
        if (file_exists($file)) {
            return unlink($file);
        }
        return false;
    }
    
}
