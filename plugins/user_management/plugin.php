<?php
/**
 * User Management Plugin - Bootstrap
 * 
 * Fully self-contained user management plugin for Birkly
 * Phase 1: Core Infrastructure & Storage
 */

// Note: Plugin state is checked by the loader before including this file
// We don't need to check state here - if this file is included, the plugin is active

// Load plugin classes
require_once __DIR__ . '/includes/UserStorage.php';
require_once __DIR__ . '/includes/UserService.php';
require_once __DIR__ . '/includes/UserAuth.php';
require_once __DIR__ . '/includes/UserPermissions.php';
require_once __DIR__ . '/includes/UserTiers.php';
require_once __DIR__ . '/includes/ProfileService.php';

// Register User fieldtype when plugin is active
if (function_exists('register_plugin_fieldtype')) {
    // Load BaseField first
    if (!class_exists('Birkly\FieldTypes\BaseField')) {
        require_once __DIR__ . '/../../fieldtypes/BaseField.php';
    }
    
    // Load User fieldtype class
    require_once __DIR__ . '/fieldtypes/User/User.php';
    
    // Register the fieldtype
    register_plugin_fieldtype('user', 'UserManagement\\Fieldtypes\\User', 'user_management');
    
    // Load Password fieldtype class
    require_once __DIR__ . '/fieldtypes/Password/Password.php';
    
    // Register the fieldtype
    register_plugin_fieldtype('password', 'UserManagement\\Fieldtypes\\Password', 'user_management');
}

// Initialize default tier - always run when plugin loads (idempotent)
// This ensures the "All" tier exists and old tiers are cleaned up
try {
    $tiers = new UserTiers();
    $tiers->initializeDefaultTiers();
    
    // Migrate old default tiers if they exist
    $storage = new UserStorage();
    $oldDefaultTiers = ['free', 'basic', 'premium'];
    foreach ($oldDefaultTiers as $oldTierId) {
        $oldTier = $storage->getTier($oldTierId);
        if ($oldTier) {
            // Delete old default tiers
            $storage->deleteTier($oldTierId);
        }
    }
} catch (Exception $e) {
    // Silently fail - initialization will retry on next load
    error_log('User Management Plugin initialization error: ' . $e->getMessage());
}

// Register hook for future activations (redundant but safe)
if (function_exists('register_hook')) {
    register_hook('plugin_activated', function($plugin_id) {
        if ($plugin_id === 'user_management') {
            try {
                // Initialize default tier (just "All")
                $tiers = new UserTiers();
                $tiers->initializeDefaultTiers();
                
                // Migrate old default tiers if they exist
                $storage = new UserStorage();
                $oldDefaultTiers = ['free', 'basic', 'premium'];
                foreach ($oldDefaultTiers as $oldTierId) {
                    $oldTier = $storage->getTier($oldTierId);
                    if ($oldTier) {
                        // Delete old default tiers
                        $storage->deleteTier($oldTierId);
                    }
                }
                
                // Log activation
                error_log('User Management Plugin activated');
            } catch (Exception $e) {
                error_log('User Management Plugin activation error: ' . $e->getMessage());
            }
        }
    });
}

// Register plugin service
if (function_exists('register_plugin_service')) {
    register_plugin_service('user_service', new UserService(), [
        'version' => '1.0.0',
        'description' => 'User management service for creating, updating, and querying users',
        'capability' => null, // Service itself doesn't require capability, but methods might
        'scope' => 'global'
    ]);
}

// Demo-website scope: on /demo-website/ pages only treat users as "logged in" if they are
// demo users (e.g. *@demo.social). CMS/admin users must not appear as logged in on the demo.
function user_management_is_demo_website_context() {
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    return (strpos($ref, '/demo-website/') !== false);
}

function user_management_is_demo_user($user) {
    if (!$user || !is_array($user)) {
        return false;
    }
    $email = $user['email'] ?? '';
    return (strpos($email, '@demo.social') !== false);
}

// Register template functions (stubs for Phase 1, will be fully implemented in Phase 2)
if (function_exists('register_template_function')) {
    // user_current - Get current logged-in user (demo-website: only demo users)
    register_template_function('user_current', function() {
        try {
            $auth = new UserAuth();
            $user = $auth->getCurrentUser();
            if (user_management_is_demo_website_context() && !user_management_is_demo_user($user)) {
                return null;
            }
            return $user;
        } catch (Exception $e) {
            error_log('Template function user_current error: ' . $e->getMessage());
            return null;
        }
    }, [
        'description' => 'Get current logged-in user object',
        'arguments' => []
    ]);
    
    // user_logged_in - Check if user is logged in (demo-website: only demo users)
    register_template_function('user_logged_in', function() {
        try {
            $auth = new UserAuth();
            if (!$auth->isLoggedIn()) {
                return false;
            }
            if (user_management_is_demo_website_context()) {
                $user = $auth->getCurrentUser();
                return user_management_is_demo_user($user);
            }
            return true;
        } catch (Exception $e) {
            error_log('Template function user_logged_in error: ' . $e->getMessage());
            return false;
        }
    }, [
        'description' => 'Check if a user is currently logged in',
        'arguments' => []
    ]);
    
    // user_by_id - Get user by ID (demo-website: only returns demo users)
    register_template_function('user_by_id', function($user_id) {
        try {
            $service = new UserService();
            $user = $service->getUser($user_id);
            if (!$user) return null;
            if (user_management_is_demo_website_context() && !user_management_is_demo_user($user)) {
                return null;
            }
            return $user;
        } catch (Exception $e) {
            error_log('Template function user_by_id error: ' . $e->getMessage());
            return null;
        }
    }, [
        'description' => 'Get user by ID',
        'arguments' => [
            ['name' => 'user_id', 'type' => 'string', 'required' => true]
        ]
    ]);
    
    // user_can - Check user permissions (stub for Phase 4)
    register_template_function('user_can', function($action, $context = null) {
        try {
            $auth = new UserAuth();
            $user = $auth->getCurrentUser();
            if (!$user) {
                return false;
            }
            
            $permissions = new UserPermissions();
            return $permissions->userCan($user['id'], $action, $context);
        } catch (Exception $e) {
            error_log('Template function user_can error: ' . $e->getMessage());
            return false;
        }
    }, [
        'description' => 'Check if current user can perform an action',
        'arguments' => [
            ['name' => 'action', 'type' => 'string', 'required' => true],
            ['name' => 'context', 'type' => 'string', 'required' => false]
        ]
    ]);
    
    // user_tier - Get user tier (stub for Phase 6)
    register_template_function('user_tier', function() {
        try {
            $auth = new UserAuth();
            $user = $auth->getCurrentUser();
            if (!$user) {
                return null;
            }
            
            $tiers = new UserTiers();
            return $tiers->getUserTier($user['id']);
        } catch (Exception $e) {
            error_log('Template function user_tier error: ' . $e->getMessage());
            return null;
        }
    }, [
        'description' => 'Get current user tier',
        'arguments' => []
    ]);
    
    // user_in_field - Check if current user is in a user fieldtype field
    register_template_function('user_in_field', function($fieldValue) {
        try {
            $auth = new UserAuth();
            $currentUser = $auth->getCurrentUser();
            
            if (!$currentUser) {
                return false;
            }
            
            $userId = $currentUser['id'] ?? null;
            if (!$userId) {
                return false;
            }
            
            // Handle both single and multiple values
            if (is_array($fieldValue)) {
                return in_array($userId, $fieldValue);
            }
            
            return $fieldValue === $userId;
        } catch (Exception $e) {
            error_log('Template function user_in_field error: ' . $e->getMessage());
            return false;
        }
    }, [
        'description' => 'Check if current user is in a user fieldtype field',
        'arguments' => ['field_value']
    ]);
    
    // user_field - Get user data from user fieldtype value(s)
    register_template_function('user_field', function($userIds) {
        try {
            if (empty($userIds)) {
                return [];
            }
            
            $service = new UserService();
            $ids = is_array($userIds) ? $userIds : [$userIds];
            $users = [];
            
            foreach ($ids as $id) {
                if (empty($id)) continue;
                
                $user = $service->getUser($id);
                if ($user) {
                    // Remove sensitive data
                    unset($user['password_hash']);
                    $users[] = $user;
                }
            }
            
            // Return single user if single ID provided, array if multiple
            return is_array($userIds) ? $users : ($users[0] ?? null);
        } catch (Exception $e) {
            error_log('Template function user_field error: ' . $e->getMessage());
            return is_array($userIds) ? [] : null;
        }
    }, [
        'description' => 'Get user data from user fieldtype value(s)',
        'arguments' => ['user_ids']
    ]);

    // user_profile - Get profile entry from a configured profile collection
    // Usage: { user_profile "user_id" } or { user_profile } for current user.
    // On demo-website we never fall back to current user when user_id is empty (avoids showing CMS admin).
    register_template_function('user_profile', function($user_id = null) {
        try {
            $auth = new UserAuth('site');
            $me = $auth->getCurrentUser();
            $uid = $user_id ? (string)$user_id : (string)($me['id'] ?? '');
            if ($uid === '') return null;
            // Demo-website: only return profile for demo users when in demo context
            if (user_management_is_demo_website_context() && !user_management_is_demo_user($me) && $uid === (string)($me['id'] ?? '')) {
                return null;
            }
            // Resolve target user when uid is set (for demo check and for auto-create)
            $targetUser = null;
            if ($uid !== '') {
                $svc = new UserService();
                $targetUser = $svc->getUser($uid);
            }
            // Demo-website: only allow profile for demo users; block when we know target is not demo
            if (user_management_is_demo_website_context() && $targetUser && strpos($targetUser['email'] ?? '', '@demo.social') === false) {
                return null;
            }

            // Respect admin toggle: if collection profiles are disabled, don't expose profile entries.
            $enabled = function_exists('get_plugin_setting')
                ? (bool)get_plugin_setting('user_management', 'profile_collection_enabled', false)
                : false;
            $collection = function_exists('get_plugin_setting')
                ? (string)get_plugin_setting('user_management', 'profile_collection_slug', '')
                : '';
            // Use social_profiles for demo users when profile collection is not configured
            // (works even when Referer is missing, so profile page always loads for Alice/Bob/Carol)
            $isDemoUser = $targetUser && strpos($targetUser['email'] ?? '', '@demo.social') !== false;
            if ($isDemoUser && (!$enabled || $collection === '')) {
                $enabled = true;
                $collection = 'social_profiles';
            }
            if (!$enabled || $collection === '') return null;

            $profiles = new ProfileService();
            $profile = $profiles->getProfileByUserId($collection, $uid);

            // Auto-create for the requested user when enabled.
            // This keeps the site experience smooth and stays generic (collection is configurable).
            if (!$profile) {
                $auto = function_exists('get_plugin_setting')
                    ? (bool)get_plugin_setting('user_management', 'auto_create_profiles', true)
                    : true;
                if ($auto) {
                    // For self, use session snapshot; otherwise use already-loaded target user.
                    if ($me && ($me['id'] ?? '') === $uid) {
                        $profile = $profiles->ensureProfileForUser($collection, $me);
                    } elseif ($targetUser) {
                        $profile = $profiles->ensureProfileForUser($collection, $targetUser);
                    } else {
                        $svc = new UserService();
                        $target = $svc->getUser($uid);
                        if ($target) {
                            $profile = $profiles->ensureProfileForUser($collection, $target);
                        }
                    }
                }
            }

            return $profile;
        } catch (Exception $e) {
            error_log('Template function user_profile error: ' . $e->getMessage());
            return null;
        }
    }, [
        'description' => 'Get profile entry from a configured profile collection',
        'arguments' => [
            ['name' => 'user_id', 'type' => 'string', 'required' => false]
        ]
    ]);

    // user_is_self - Check if a user_id is the current logged-in user
    // Usage: {if user_is_self "user_..." } ... {endif}
    register_template_function('user_is_self', function($user_id) {
        try {
            $auth = new UserAuth('site');
            $me = $auth->getCurrentUser();
            if (!$me || empty($me['id'])) return false;
            return (string)$me['id'] === (string)$user_id;
        } catch (Exception $e) {
            return false;
        }
    }, [
        'description' => 'Check if a user_id matches the current logged-in user',
        'arguments' => [
            ['name' => 'user_id', 'type' => 'string', 'required' => true]
        ]
    ]);

    // user_list - List active users (site scope). On demo-website, only demo users (*@demo.social).
    register_template_function('user_list', function() {
        try {
            $svc = new UserService();
            $users = $svc->listUsers(['status' => 'active']);
            $out = [];
            $demoOnly = user_management_is_demo_website_context();
            foreach ($users as $u) {
                if (!is_array($u)) continue;
                if ($demoOnly && strpos($u['email'] ?? '', '@demo.social') === false) {
                    continue;
                }
                $out[] = [
                    'id' => $u['id'] ?? '',
                    'name' => $u['name'] ?? ($u['username'] ?? ($u['email'] ?? '')),
                    'email' => $u['email'] ?? ''
                ];
            }
            return $out;
        } catch (Exception $e) {
            return [];
        }
    }, [
        'description' => 'List active users (id, name, email)',
        'arguments' => []
    ]);
}

// Register API endpoints (stubs for Phase 1, will be fully implemented in Phase 2-3)
if (function_exists('register_api_endpoint')) {
    // Authentication API (Phase 2)
    register_api_endpoint('user_auth', __DIR__ . '/api/user_auth.php');
    
    // Registration API (Phase 3)
    register_api_endpoint('user_registration', __DIR__ . '/api/user_registration.php');

    // Social graph / relationships API (optional)
    register_api_endpoint('user_relations', __DIR__ . '/api/user_relations.php');
    
    // Admin API (Phase 5)
    register_api_endpoint('user_admin', __DIR__ . '/api/user_admin.php');
    
    // Tiers API (Phase 6)
    register_api_endpoint('user_tiers', __DIR__ . '/api/user_tiers.php');
    
    // Groups API (Phase 7)
    register_api_endpoint('user_groups', __DIR__ . '/api/user_groups.php');
    
    // Settings API
    register_api_endpoint('user_settings', __DIR__ . '/api/user_settings.php');
    
    // Collection Permissions API
    register_api_endpoint('collection_permissions', __DIR__ . '/api/collection_permissions.php');
    
    // User Verification API
    register_api_endpoint('user_verification', __DIR__ . '/api/user_verification.php');
    
    // User 2FA API
    register_api_endpoint('user_2fa', __DIR__ . '/api/user_2fa.php');
    
    // Password Reset API
    register_api_endpoint('password_reset', __DIR__ . '/api/password_reset.php');
}

// Register admin pages (Phase 5)
if (function_exists('register_admin_page')) {
    register_admin_page('site_users', __DIR__ . '/admin/site_users.html');
    register_admin_page('user_tiers', __DIR__ . '/admin/tiers.html');
    register_admin_page('user_groups', __DIR__ . '/admin/groups.html');
}

if (function_exists('register_plugin_admin_meta')) {
    register_plugin_admin_meta('user_management', [
        'title' => 'Site Users',
        'root_page' => 'site_users',
        'permission' => 'manage_users',
        'page_slugs' => ['site_users', 'user_tiers', 'user_groups'],
        'sub_pages' => [
            'user_tiers' => ['title' => 'Tiers', 'permission' => 'manage_users'],
            'user_groups' => ['title' => 'Groups', 'permission' => 'manage_users'],
        ],
        'sidebar' => [
            'show' => true,
            'label' => 'Site Users',
            'order' => 35,
            'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>',
        ],
    ]);
}

// Hook into collection system for permissions (Phase 4)
if (function_exists('register_hook')) {
    require_once __DIR__ . '/includes/UserAuth.php';
    require_once __DIR__ . '/includes/UserPermissions.php';
    require_once __DIR__ . '/includes/EntryPermissions.php';

    if (!function_exists('user_management_is_cms_admin_request')) {
        function user_management_is_cms_admin_request() {
            if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
                return false;
            }
            if (function_exists('checkPermission')) {
                $user = $_SESSION['user'];
                return checkPermission($user, 'write')
                    || checkPermission($user, 'delete')
                    || checkPermission($user, 'manage_content');
            }
            $role = $_SESSION['user']['role'] ?? '';
            return in_array($role, ['admin', 'editor', 'author'], true);
        }
    }

    if (!function_exists('user_management_guard_entry_create')) {
        function user_management_guard_entry_create($collection, $entry) {
            if (user_management_is_cms_admin_request()) {
                return $entry;
            }

            $auth = new UserAuth();
            $user = $auth->getCurrentUser();
            if (!$user) {
                throw new Exception('Authentication required to create entries');
            }

            $permissions = new UserPermissions();
            if (!$permissions->userCan($user['id'], 'create', $collection)) {
                throw new Exception('Permission denied: Cannot create entries in this collection');
            }

            if (!isset($entry['author_id']) && !isset($entry['author_user_id'])) {
                $entry['author_id'] = $user['id'];
            }

            return $entry;
        }
    }

    if (!function_exists('user_management_guard_entry_delete')) {
        function user_management_guard_entry_delete($collection, $entry_id, $entry = null) {
            if (user_management_is_cms_admin_request()) {
                return true;
            }

            $auth = new UserAuth();
            $user = $auth->getCurrentUser();
            if (!$user) {
                throw new Exception('Authentication required to delete entries');
            }

            if ($entry === null && function_exists('get_entry')) {
                $entry = get_entry($collection, $entry_id);
            }
            if (!is_array($entry) && is_string($entry_id)) {
                $entryPath = dirname(dirname(__DIR__)) . "/content/collections/$collection/entries/$entry_id.json";
                if (file_exists($entryPath)) {
                    $entry = json_decode(file_get_contents($entryPath), true);
                }
            }

            $entryPermissions = new EntryPermissions();
            if (!is_array($entry) || !$entryPermissions->canDeleteEntry($user['id'], $entry, $collection)) {
                throw new Exception('Permission denied: Cannot delete this entry');
            }

            return true;
        }
    }

    register_hook('before_entry_create', function($collection, $entry) {
        return user_management_guard_entry_create($collection, $entry);
    });

    if (function_exists('add_filter')) {
        add_filter('before_entry_create', function($entry, $collection) {
            return user_management_guard_entry_create($collection, $entry);
        }, 10);
    }
    
    register_hook('before_entry_save', function($collection, $entry) {
        $auth = new UserAuth();
        $user = $auth->getCurrentUser();
        
        if (!$user) {
            throw new Exception('Authentication required to save entries');
        }
        
        $permissions = new UserPermissions();
        
        // Check if this is a new entry or update
        $isNew = !isset($entry['id']);
        $action = $isNew ? 'create' : 'edit';
        
        // Build context for permission check
        $context = [
            'collection' => $collection,
            'entry' => $entry['id'] ?? null,
            'author_id' => $entry['author_id'] ?? null
        ];
        
        $canSave = $permissions->userCan($user['id'], $action, $context);
        
        if (!$canSave) {
            throw new Exception('Permission denied: Cannot ' . ($isNew ? 'create' : 'edit') . ' this entry');
        }
        
        return $entry;
    });
    
    register_hook('before_entry_delete', function($collection, $entry_id) {
        return user_management_guard_entry_delete($collection, $entry_id);
    });
    
    // Auto-create user from profile entry when entry is saved
    if (function_exists('add_filter')) {
        add_filter('before_entry_save', function($entry, $collection) {
            // Only process if profile collection is enabled and this is the profile collection
            if (!function_exists('get_plugin_setting')) {
                return $entry;
            }
            
            $profileCollectionEnabled = get_plugin_setting('user_management', 'profile_collection_enabled', false);
            $profileCollectionSlug = get_plugin_setting('user_management', 'profile_collection_slug', '');
            
            if (!$profileCollectionEnabled || $profileCollectionSlug !== $collection) {
                return $entry;
            }
            
            // If entry already has user_id, sync title with user name
            if (!empty($entry['user_id'])) {
                try {
                    require_once __DIR__ . '/includes/UserService.php';
                    $userService = new UserService();
                    $user = $userService->getUser($entry['user_id']);
                    
                    if ($user) {
                        $userName = $user['name'] ?? $user['username'] ?? '';
                        if (!empty($userName)) {
                            $entry['title'] = $userName;
                        }
                    }
                } catch (Exception $e) {
                    error_log("Failed to sync title from user: " . $e->getMessage());
                }
                
                return $entry;
            }
            
            // Check if a user with this email already exists (prevent duplicates)
            $email = trim($entry['email'] ?? '');
            if (empty($email)) {
                error_log("Profile entry missing email (required field), cannot auto-create user");
                return $entry;
            }
            
            try {
                require_once __DIR__ . '/includes/UserService.php';
                require_once __DIR__ . '/includes/ProfileService.php';
                $userService = new UserService();
                $profileService = new ProfileService();
                
                // Check if email already exists
                $existingUser = $userService->getUserByEmail($email);
                if ($existingUser) {
                    // User already exists - link the profile entry to existing user
                    $entry['user_id'] = $existingUser['id'];
                    error_log("Linked profile entry to existing user {$existingUser['id']} ({$email})");
                    return $entry;
                }
                
                // Also check if another profile entry with this email already exists (without user_id)
                // This prevents creating duplicate users when the same entry is saved multiple times
                $entryId = $entry['id'] ?? '';
                // Use Storage class directly (ProfileService uses it internally)
                require_once __DIR__ . '/../../api/storage.php';
                $rootDir = dirname(dirname(__DIR__)); // Go from plugins/user_management/ to root
                $storage = new Storage($rootDir . '/content');
                $allProfiles = $storage->query($profileCollectionSlug, [], []);
                foreach ($allProfiles as $profile) {
                    // Skip the current entry
                    if (isset($profile['id']) && $profile['id'] === $entryId) {
                        continue;
                    }
                    // If another profile has this email and no user_id, don't create a duplicate user
                    if (isset($profile['email']) && $profile['email'] === $email && empty($profile['user_id'])) {
                        error_log("Another profile entry with email {$email} already exists, skipping user creation to prevent duplicate");
                        return $entry;
                    }
                }
                
                // Extract user data from entry
                $name = trim($entry['name'] ?? $entry['title'] ?? '');
                if (empty($name)) {
                    error_log("Profile entry missing name/title, cannot auto-create user");
                    return $entry;
                }
                
                // Get password from entry (required field)
                $password = trim($entry['password'] ?? '');
                if (empty($password)) {
                    error_log("Profile entry missing password (required field), cannot auto-create user");
                    return $entry;
                }
                
                // Determine user status based on entry status and auto-approve setting
                $entryStatus = $entry['status'] ?? 'pending';
                $autoApprove = get_plugin_setting('user_management', 'auto_approve_users', false);
                
                // Map entry status to user status/approved
                // published = approved + active, unpublished = not approved + inactive, pending = not approved + pending
                $userStatus = 'pending';
                $userApproved = false;
                if ($entryStatus === 'published') {
                    $userStatus = 'active';
                    $userApproved = true;
                } elseif ($entryStatus === 'unpublished') {
                    $userStatus = 'inactive';
                    $userApproved = false;
                } else { // pending or other
                    $userStatus = 'pending';
                    $userApproved = false;
                }
                
                // If auto-approve is enabled, override to approved + active
                if ($autoApprove) {
                    $userStatus = 'active';
                    $userApproved = true;
                    // Also set entry status to published if it was pending
                    if ($entryStatus === 'pending') {
                        $entry['status'] = 'published';
                    }
                }
                
                // Create user account
                // Set flag to skip profile creation (since profile entry already exists)
                $userData = [
                    'email' => $email,
                    'password' => $password,
                    'name' => $name,
                    'username' => $name,
                    'status' => $userStatus,
                    'verified' => true,
                    'approved' => $userApproved,
                    'tier' => get_plugin_setting('user_management', 'default_tier', 'all'),
                    '_skip_profile_creation' => true // Prevent circular dependency
                ];
                
                $newUser = $userService->createUser($userData);
                
                // Link profile entry to the created user
                $entry['user_id'] = $newUser['id'];
                
                // Sync title with username/name
                $entry['title'] = $name;
                
                // Remove password from entry (it's only used for user creation, not stored in profile)
                unset($entry['password']);
                
                error_log("Auto-created user {$newUser['id']} ({$email}) from profile entry in collection {$collection} with status: {$userStatus}, approved: " . ($userApproved ? 'yes' : 'no'));
            } catch (Exception $e) {
                error_log("Failed to auto-create user from profile entry: " . $e->getMessage());
                // Don't fail entry save if user creation fails - just log it
                // The entry will be saved without user_id, which can be fixed later
            }
            
            return $entry;
        }, 5); // Priority 5 (higher priority, runs early)
        
        // Sync entry status to user status when entry is saved (after save)
        if (function_exists('register_hook')) {
            register_hook('after_entry_save', function($entry, $collection, $oldEntry = null) {
            // Only process if profile collection is enabled and this is the profile collection
            if (!function_exists('get_plugin_setting')) {
                return;
            }
            
            $profileCollectionEnabled = get_plugin_setting('user_management', 'profile_collection_enabled', false);
            $profileCollectionSlug = get_plugin_setting('user_management', 'profile_collection_slug', '');
            
            if (!$profileCollectionEnabled || $profileCollectionSlug !== $collection) {
                return;
            }
            
            // Only sync if entry has a user_id
            $userId = $entry['user_id'] ?? '';
            if (empty($userId)) {
                return;
            }
            
            try {
                require_once __DIR__ . '/includes/UserService.php';
                $userService = new UserService();
                $user = $userService->getUser($userId);
                
                if (!$user) {
                    return; // User doesn't exist
                }
                
                // Sync title with username/name
                $userName = $user['name'] ?? $user['username'] ?? '';
                if (!empty($userName) && ($entry['title'] ?? '') !== $userName) {
                    // Update entry title to match user name
                    require_once __DIR__ . '/../../api/storage.php';
                    $rootDir = dirname(dirname(__DIR__));
                    $storage = new Storage($rootDir . '/content');
                    $entryId = $entry['id'] ?? '';
                    if (!empty($entryId)) {
                        $entryPath = "collections/{$profileCollectionSlug}/entries/{$entryId}.json";
                        $currentEntry = $storage->read($entryPath);
                        if ($currentEntry) {
                            $currentEntry['title'] = $userName;
                            $storage->write($entryPath, $currentEntry);
                        }
                    }
                }
                
                // Map entry status to user status/approved
                $entryStatus = $entry['status'] ?? 'pending';
                $userStatus = 'pending';
                $userApproved = false;
                
                if ($entryStatus === 'published') {
                    $userStatus = 'active';
                    $userApproved = true;
                } elseif ($entryStatus === 'unpublished') {
                    $userStatus = 'inactive';
                    $userApproved = false;
                } else { // pending or other
                    $userStatus = 'pending';
                    $userApproved = false;
                }
                
                // Only update if status changed
                $statusChanged = ($user['status'] ?? '') !== $userStatus;
                $approvedChanged = ($user['approved'] ?? false) !== $userApproved;
                
                if ($statusChanged || $approvedChanged) {
                    $updateData = [];
                    if ($statusChanged) {
                        $updateData['status'] = $userStatus;
                    }
                    if ($approvedChanged) {
                        $updateData['approved'] = $userApproved;
                    }
                    
                    $userService->updateUser($userId, $updateData);
                    error_log("Synced entry status '{$entryStatus}' to user {$userId}: status={$userStatus}, approved=" . ($userApproved ? 'yes' : 'no'));
                }
                
                // Also sync name if changed
                $entryName = trim($entry['name'] ?? '');
                $userName = trim($user['name'] ?? '');
                if (!empty($entryName) && $entryName !== $userName) {
                    $userService->updateUser($userId, ['name' => $entryName, 'username' => $entryName]);
                    error_log("Synced entry name '{$entryName}' to user {$userId}");
                }
            } catch (Exception $e) {
                error_log("Failed to sync entry status to user: " . $e->getMessage());
            }
            }, 10);
        }
        
        // Sync user status to entry status when user is updated
        if (function_exists('register_hook')) {
            register_hook('after_user_update', function($user) {
            // Only process if profile collection is enabled
            if (!function_exists('get_plugin_setting')) {
                return;
            }
            
            $profileCollectionEnabled = get_plugin_setting('user_management', 'profile_collection_enabled', false);
            $profileCollectionSlug = get_plugin_setting('user_management', 'profile_collection_slug', '');
            
            if (!$profileCollectionEnabled || empty($profileCollectionSlug)) {
                return;
            }
            
            $userId = $user['id'] ?? '';
            if (empty($userId)) {
                return;
            }
            
            try {
                require_once __DIR__ . '/includes/ProfileService.php';
                $profileService = new ProfileService();
                $profile = $profileService->getProfileByUserId($profileCollectionSlug, $userId);
                
                if (!$profile) {
                    return; // Profile entry doesn't exist
                }
                
                // Map user status/approved to entry status
                $userStatus = $user['status'] ?? 'pending';
                $userApproved = $user['approved'] ?? false;
                $entryStatus = 'pending';
                
                if ($userApproved && $userStatus === 'active') {
                    $entryStatus = 'published';
                } elseif ($userStatus === 'inactive') {
                    $entryStatus = 'unpublished';
                } else {
                    $entryStatus = 'pending';
                }
                
                // Only update if status changed
                $currentStatus = $profile['status'] ?? 'pending';
                if ($currentStatus !== $entryStatus) {
                    $profile['status'] = $entryStatus;
                    $profileService->saveProfile($profileCollectionSlug, $profile);
                    error_log("Synced user status '{$userStatus}' (approved: " . ($userApproved ? 'yes' : 'no') . ") to entry {$profile['id']}: status={$entryStatus}");
                }
                
                // Also sync name if changed
                $userName = trim($user['name'] ?? '');
                $profileName = trim($profile['name'] ?? '');
                if (!empty($userName) && $userName !== $profileName) {
                    $profile['name'] = $userName;
                    $profileService->saveProfile($profileCollectionSlug, $profile);
                    error_log("Synced user name '{$userName}' to entry {$profile['id']}");
                }
            } catch (Exception $e) {
                error_log("Failed to sync user status to entry: " . $e->getMessage());
            }
            }, 10);
        }
    }
}

require __DIR__ . '/docs/register_docs.php';
