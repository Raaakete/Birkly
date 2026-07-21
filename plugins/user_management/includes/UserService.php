<?php
/**
 * UserService - Main service interface for user management
 */

require_once __DIR__ . '/UserStorage.php';

class UserService {
    private $storage;
    
    public function __construct() {
        $this->storage = new UserStorage();
    }
    
    /**
     * Generate unique user ID
     */
    private function generateUserId() {
        return 'user_' . bin2hex(random_bytes(16));
    }
    
    /**
     * Validate user data
     */
    private function validateUserData($data, $isUpdate = false) {
        $errors = [];
        
        if (!$isUpdate || isset($data['email'])) {
            // Trim email before validation
            $email = isset($data['email']) ? trim($data['email']) : '';
            if (empty($email)) {
                $errors[] = 'Email is required';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid email format';
            } else {
                // Check email uniqueness (only for new users)
                if (!$isUpdate) {
                    $existing = $this->storage->getUserByEmail($email);
                    if ($existing) {
                        $errors[] = 'Email already registered';
                    }
                }
                // Update data with trimmed email
                $data['email'] = $email;
            }
        }
        
        if (!$isUpdate || isset($data['password'])) {
            if (empty($data['password'])) {
                if (!$isUpdate) {
                    $errors[] = 'Password is required';
                }
            } elseif (strlen($data['password']) < 8) {
                $errors[] = 'Password must be at least 8 characters';
            }
        }
        
        if (isset($data['role'])) {
            $validRoles = ['member', 'subscriber', 'premium', 'vip'];
            if (!in_array($data['role'], $validRoles)) {
                $errors[] = 'Invalid role';
            }
        }
        
        return $errors;
    }
    
    /**
     * Hash password
     */
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
    }
    
    /**
     * Verify password
     */
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Create new user
     */
    public function createUser($data) {
        // Validate
        $errors = $this->validateUserData($data, false);
        if (!empty($errors)) {
            throw new Exception('Validation failed: ' . implode(', ', $errors));
        }
        
        // Generate user ID
        $user_id = $this->generateUserId();
        
        // Hash password
        $password_hash = $this->hashPassword($data['password']);
        
        // Get default role and tier from settings
        $defaultRole = 'member';
        $defaultTier = 'all';
        if (function_exists('get_plugin_setting')) {
            $defaultRole = get_plugin_setting('user_management', 'default_role', 'member');
            $defaultTier = get_plugin_setting('user_management', 'default_tier', 'all');
        }
        
        // Prepare user data
        $userData = [
            'id' => $user_id,
            'email' => $data['email'],
            'password_hash' => $password_hash,
            'role' => $data['role'] ?? $defaultRole,
            'tier' => $data['tier'] ?? $defaultTier,
            'status' => $data['status'] ?? 'active',
            'created_at' => date('c'),
            'updated_at' => date('c'),
            'deleted' => false
        ];
        
        // Add optional fields
        if (isset($data['username'])) {
            $userData['username'] = $data['username'];
        } elseif (isset($data['name'])) {
            // Support 'name' as alias for 'username'
            $userData['username'] = $data['name'];
        }
        if (isset($data['name'])) {
            $userData['name'] = $data['name'];
        }
        if (isset($data['phone'])) {
            $userData['phone'] = $data['phone'];
        }
        if (isset($data['verified'])) {
            $userData['verified'] = $data['verified'];
        }
        if (isset($data['approved'])) {
            $userData['approved'] = $data['approved'];
        }
        if (isset($data['tier'])) {
            $userData['tier'] = $data['tier'];
        }
        
        // Check if we should skip profile creation BEFORE saving (to prevent circular dependency)
        // The flag comes from $data, not $userData (since $userData is built from $data)
        $skipProfileCreation = $data['_skip_profile_creation'] ?? false;
        // Don't save the flag to the user record
        unset($userData['_skip_profile_creation']);
        
        // Fire before hook
        if (function_exists('do_action')) {
            do_action('before_user_register', $userData);
            // Note: do_action doesn't return a value, it just executes hooks
            // If hooks need to modify data, they should use filters instead
        }
        
        // Save user (without the _skip_profile_creation flag)
        if (!$this->storage->saveUser($user_id, $userData)) {
            throw new Exception('Failed to save user');
        }
        
        if (!$skipProfileCreation && function_exists('get_plugin_setting')) {
            $profileCollectionEnabled = get_plugin_setting('user_management', 'profile_collection_enabled', false);
            $autoCreateProfiles = get_plugin_setting('user_management', 'auto_create_profiles', true);
            
            if ($profileCollectionEnabled && $autoCreateProfiles) {
                try {
                    // Check if profile entry already exists for this user (prevent duplicates)
                    require_once __DIR__ . '/ProfileService.php';
                    $profileService = new ProfileService();
                    $profileCollectionSlug = get_plugin_setting('user_management', 'profile_collection_slug', '');
                    
                    if ($profileCollectionSlug) {
                        $existingProfile = $profileService->getProfileByUserId($profileCollectionSlug, $user_id);
                        if (!$existingProfile) {
                            require_once __DIR__ . '/ProfileManager.php';
                            $profileManager = new ProfileManager();
                            $profileManager->createProfileEntry($user_id);
                        } else {
                            error_log("Profile entry already exists for user {$user_id}, skipping auto-creation");
                        }
                    }
                } catch (Exception $e) {
                    error_log('Failed to create profile entry: ' . $e->getMessage());
                    // Don't fail user creation if profile entry creation fails
                }
            }
        }
        
        // Fire after hook
        if (function_exists('do_action')) {
            do_action('after_user_register', $userData);
        }
        
        // Send verification email if email verification is enabled
        $emailVerificationEnabled = function_exists('get_plugin_setting')
            ? get_plugin_setting('user_management', 'email_verification', true)
            : true;
        if ($emailVerificationEnabled && !empty($userData['email'])) {
            try {
                require_once __DIR__ . '/EmailVerification.php';
                $emailVerification = new EmailVerification();
                $token = $emailVerification->generateToken($user_id);
                $emailVerification->sendVerificationEmail($user_id, $userData['email'], $token);
            } catch (Exception $e) {
                error_log('Failed to send verification email: ' . $e->getMessage());
                // Don't fail user creation if email fails
            }
        }
        
        // Log activity
        $this->storage->saveActivity($user_id, [
            'action' => 'user_created',
            'description' => 'User account created'
        ]);
        
        // Return user (without password hash)
        $returnUser = $userData;
        unset($returnUser['password_hash']);
        
        return $returnUser;
    }
    
    /**
     * Get user by ID
     */
    public function getUser($user_id) {
        $user = $this->storage->getUser($user_id);
        
        if (!$user || ($user['deleted'] ?? false)) {
            return null;
        }
        
        // Remove sensitive data
        unset($user['password_hash']);
        
        return $user;
    }
    
    /**
     * Get user by email
     */
    public function getUserByEmail($email) {
        $user = $this->storage->getUserByEmail($email);
        
        if (!$user || ($user['deleted'] ?? false)) {
            return null;
        }
        
        // Remove sensitive data
        unset($user['password_hash']);
        
        return $user;
    }
    
    /**
     * Update user
     */
    public function updateUser($user_id, $data) {
        $user = $this->storage->getUser($user_id);
        
        if (!$user || ($user['deleted'] ?? false)) {
            throw new Exception('User not found');
        }
        
        // Validate
        $errors = $this->validateUserData($data, true);
        if (!empty($errors)) {
            throw new Exception('Validation failed: ' . implode(', ', $errors));
        }
        
        // Update fields
        if (isset($data['email'])) {
            // Check email uniqueness (if changed)
            if ($data['email'] !== $user['email']) {
                $existing = $this->storage->getUserByEmail($data['email']);
                if ($existing && $existing['id'] !== $user_id) {
                    throw new Exception('Email already registered');
                }
            }
            $user['email'] = $data['email'];
        }
        
        if (isset($data['password'])) {
            $user['password_hash'] = $this->hashPassword($data['password']);
        }
        
        if (isset($data['name'])) {
            $user['name'] = $data['name'];
        }
        
        if (isset($data['phone'])) {
            $user['phone'] = $data['phone'];
        }
        
        if (isset($data['role'])) {
            $user['role'] = $data['role'];
        }
        
        if (isset($data['status'])) {
            $user['status'] = $data['status'];
        }
        
        if (isset($data['verified'])) {
            $user['verified'] = $data['verified'];
        }
        
        if (isset($data['approved'])) {
            $user['approved'] = $data['approved'];
        }
        
        if (isset($data['tier'])) {
            $user['tier'] = $data['tier'];
        }
        
        $user['updated_at'] = date('c');
        
        // Fire before hook
        if (function_exists('do_action')) {
            do_action('before_user_update', $user);
            // Note: do_action doesn't return a value, it just executes hooks
        }
        
        // Save
        if (!$this->storage->saveUser($user_id, $user)) {
            throw new Exception('Failed to update user');
        }
        
        // Fire after hook with updated user (reload to ensure we have latest data)
        if (function_exists('do_action')) {
            $updatedUser = $this->getUser($user_id);
            if ($updatedUser) {
                do_action('after_user_update', $updatedUser);
            }
        }
        
        // Log activity
        $this->storage->saveActivity($user_id, [
            'action' => 'user_updated',
            'description' => 'User account updated'
        ]);
        
        // Return user (without password hash)
        $returnUser = $user;
        unset($returnUser['password_hash']);
        
        return $returnUser;
    }
    
    /**
     * Delete user (soft delete)
     */
    public function deleteUser($user_id) {
        $user = $this->storage->getUser($user_id);
        
        if (!$user) {
            throw new Exception('User not found');
        }
        
        // Fire before hook
        if (function_exists('do_action')) {
            do_action('before_user_delete', $user);
        }
        
        // Soft delete
        if (!$this->storage->deleteUser($user_id)) {
            throw new Exception('Failed to delete user');
        }
        
        // Fire after hook
        if (function_exists('do_action')) {
            do_action('after_user_delete', $user);
        }
        
        // Log activity
        $this->storage->saveActivity($user_id, [
            'action' => 'user_deleted',
            'description' => 'User account deleted'
        ]);
        
        return true;
    }
    
    /**
     * List users with filters
     */
    public function listUsers($filters = [], $limit = 100, $offset = 0) {
        $users = $this->storage->listUsers($filters);
        
        // Apply pagination
        $users = array_slice($users, $offset, $limit);
        
        // Remove sensitive data
        foreach ($users as &$user) {
            unset($user['password_hash']);
        }
        
        return $users;
    }
    
    /**
     * Search users
     */
    public function searchUsers($query, $limit = 50) {
        $allUsers = $this->storage->listUsers(['deleted' => false]);
        $results = [];
        $query = strtolower($query);
        
        foreach ($allUsers as $user) {
            $match = false;
            
            // Search in email
            if (isset($user['email']) && strpos(strtolower($user['email']), $query) !== false) {
                $match = true;
            }
            
            // Search in name
            if (isset($user['name']) && strpos(strtolower($user['name']), $query) !== false) {
                $match = true;
            }
            
            // Search in ID
            if (isset($user['id']) && strpos(strtolower($user['id']), $query) !== false) {
                $match = true;
            }
            
            if ($match) {
                unset($user['password_hash']);
                $results[] = $user;
                
                if (count($results) >= $limit) {
                    break;
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Count users
     */
    public function countUsers($filters = []) {
        return count($this->storage->listUsers($filters));
    }
    
    /**
     * Create invite code
     */
    public function createInvite($options = []) {
        $inviteCode = bin2hex(random_bytes(16));
        
        $inviteData = [
            'invite_code' => $inviteCode,
            'created_at' => date('c'),
            'max_uses' => $options['max_uses'] ?? 1,
            'uses' => 0,
            'used' => false
        ];
        
        // Optional: specific email
        if (isset($options['email'])) {
            $inviteData['email'] = $options['email'];
        }
        
        // Optional: expiration
        if (isset($options['expires_in'])) {
            $expires = new DateTime();
            $expires->modify('+' . $options['expires_in'] . ' seconds');
            $inviteData['expires_at'] = $expires->format('c');
        }
        
        // Optional: role assignment
        if (isset($options['role'])) {
            $inviteData['role'] = $options['role'];
        }
        
        $this->storage->saveInvite($inviteCode, $inviteData);
        
        return $inviteCode;
    }
    
    /**
     * Create user by admin
     */
    public function createUserByAdmin($data) {
        // Admin can bypass some restrictions
        $userData = [
            'email' => $data['email'],
            'password' => $data['password'] ?? bin2hex(random_bytes(8)), // Generate temp password if not provided
            'name' => $data['name'] ?? $data['email'],
            'role' => $data['role'] ?? get_plugin_setting('user_management', 'default_role', 'member'),
            'status' => $data['status'] ?? 'active',
            'verified' => $data['verified'] ?? true,
            'approved' => $data['approved'] ?? true
        ];
        
        // Add optional fields
        if (isset($data['phone'])) {
            $userData['phone'] = $data['phone'];
        }
        if (isset($data['tier'])) {
            $userData['tier'] = $data['tier'];
        }
        
        $user = $this->createUser($userData);
        
        // If temp password was generated, return it
        if (!isset($data['password'])) {
            $user['temp_password'] = $userData['password'];
        }
        
        return $user;
    }
}
