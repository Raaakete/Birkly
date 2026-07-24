<?php
/**
 * User Management Settings API
 * Handles plugin settings operations
 */

// Include core files
require_once __DIR__ . '/../../../api/auth.php';
require_once __DIR__ . '/../../../core/plugins.php';
require_once __DIR__ . '/../../../core/csrf.php';
require_once __DIR__ . '/../includes/UserStorage.php';

// Admin authentication check
if (function_exists('requireAuth')) {
    requireAuth('manage_users');
} elseif (function_exists('requireAdminAuth')) {
    requireAdminAuth();
}

// Get request method and input
$method = $_SERVER['REQUEST_METHOD'];
$input = [];
if ($method === 'POST' && !empty($_POST)) {
    $input = $_POST;
} elseif ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true) ?? [];
}

$action = $input['action'] ?? $_GET['action'] ?? 'get';

// CSRF protection for POST requests
if ($method === 'POST') {
    $token = $input['csrf_token'] ?? $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    if (function_exists('validate_csrf_token')) {
        if (!validate_csrf_token($token)) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF token']);
            exit;
        }
    }
}

header('Content-Type: application/json');

try {
    $storage = new UserStorage();
    
    switch ($action) {
        case 'get':
            // Get plugin settings
            global $birkly_plugins;
            $plugin = $birkly_plugins['user_management'] ?? [];
            $settings = $plugin['settings'] ?? [];
            
            // Merge with defaults
            // If a site has a configured profile collection (e.g. demo uses "social_profiles"),
            // we default-enable collection profiles only when that collection exists.
            $hasSocialProfiles = is_dir(__DIR__ . '/../../../content/collections/social_profiles');
            $defaultProfileSlug = $hasSocialProfiles ? 'social_profiles' : 'profiles';
            $defaultProfileEnabled = $hasSocialProfiles ? true : false;

            $defaultSettings = [
                'allow_public_registration' => false,
                'require_email_verification' => false,
                'require_admin_approval' => false,
                'auto_approve' => false,
                'default_role' => 'member',
                'default_tier' => 'all',
                'max_users' => 0,
                'session_timeout' => 3600,
                'max_login_attempts' => 5,
                'lockout_duration' => 30,
                'profile_collection_enabled' => $defaultProfileEnabled,
                'profile_collection_slug' => $defaultProfileSlug,
                'auto_create_profiles' => true
            ];
            $settings = array_merge($defaultSettings, $settings);
            
            // Get profile schema
            $profileSchema = $storage->readMetadata('profile_schema') ?? [
                'fields' => []
            ];
            
            echo json_encode([
                'success' => true,
                'settings' => $settings,
                'profile_schema' => $profileSchema
            ]);
            break;
            
        case 'save_settings':
            // Save general settings
            $settings = [
                'allow_public_registration' => isset($input['allow_public_registration']) ? (bool)$input['allow_public_registration'] : false,
                'require_email_verification' => isset($input['require_email_verification']) ? (bool)$input['require_email_verification'] : false,
                'require_admin_approval' => isset($input['require_admin_approval']) ? (bool)$input['require_admin_approval'] : false,
                'auto_approve' => isset($input['auto_approve']) ? (bool)$input['auto_approve'] : false,
                'default_role' => $input['default_role'] ?? 'member',
                'default_tier' => $input['default_tier'] ?? 'all',
                'max_users' => isset($input['max_users']) ? (int)$input['max_users'] : 0,
                'session_timeout' => isset($input['session_timeout']) ? (int)$input['session_timeout'] : 3600,
                'max_login_attempts' => isset($input['max_login_attempts']) ? (int)$input['max_login_attempts'] : 5,
                'lockout_duration' => isset($input['lockout_duration']) ? (int)$input['lockout_duration'] : 30,
                'profile_collection_enabled' => isset($input['profile_collection_enabled']) ? (bool)$input['profile_collection_enabled'] : false,
                'profile_collection_slug' => $input['profile_collection_slug'] ?? 'profiles',
                'auto_create_profiles' => isset($input['auto_create_profiles']) ? (bool)$input['auto_create_profiles'] : true
            ];
            
            // Save via plugin settings system
            if (function_exists('set_plugin_setting')) {
                foreach ($settings as $key => $value) {
                    set_plugin_setting('user_management', $key, $value);
                }

                // -----------------------------------------------------------------
                // Back-compat mapping:
                // The admin UI historically used:
                // - allow_public_registration
                // - auto_approve
                // But the public registration endpoint uses:
                // - enable_registration
                // - registration_method
                // - auto_approve_users
                //
                // Keep both sets in sync so the admin toggle actually controls behavior.
                // -----------------------------------------------------------------
                set_plugin_setting('user_management', 'enable_registration', (bool)$settings['allow_public_registration']);
                // If registration is disabled, force admin_only to avoid "invite/public" confusion.
                // If enabled, default to "public" (unless a site later introduces a UI for registration_method).
                set_plugin_setting('user_management', 'registration_method', $settings['allow_public_registration'] ? 'public' : 'admin_only');
                set_plugin_setting('user_management', 'auto_approve_users', (bool)$settings['auto_approve']);
            } else {
                // Fallback: save to metadata
                $storage->saveMetadata('settings', $settings);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Settings saved successfully',
                'settings' => $settings
            ]);
            break;
            
        case 'save_profile_schema':
            // Save profile schema
            $schema = [
                'fields' => $input['fields'] ?? []
            ];
            
            if (!$storage->saveMetadata('profile_schema', $schema)) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to save profile schema']);
                break;
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Profile schema saved successfully',
                'schema' => $schema
            ]);
            break;
            
        case 'get_profile_schema':
            $schema = $storage->readMetadata('profile_schema') ?? ['fields' => []];
            
            echo json_encode([
                'success' => true,
                'schema' => $schema
            ]);
            break;
            
        case 'get_collections':
            // Get list of collections for profile collection selection
            require_once __DIR__ . '/../../../core/storage/StorageFactory.php';
            $contentStorage = \StorageFactory::getStorage(__DIR__ . '/../../../content');
            
            $collections = [];
            try {
                $collectionDirs = $contentStorage->list('collections');
                foreach ($collectionDirs as $dir) {
                    $collectionMeta = $contentStorage->read("collections/{$dir}/collection.json");
                    if ($collectionMeta) {
                        $collections[] = [
                            'slug' => $collectionMeta['slug'] ?? $dir,
                            'name' => $collectionMeta['name'] ?? $collectionMeta['title'] ?? $dir,
                            'id' => $collectionMeta['id'] ?? $dir
                        ];
                    }
                }
            } catch (Exception $e) {
                error_log('Error loading collections: ' . $e->getMessage());
            }
            
            echo json_encode([
                'success' => true,
                'collections' => $collections
            ]);
            break;
            
        case 'migrate_profiles':
            // Migrate existing users to collection profiles
            //
            // IMPORTANT:
            // The demo/social stack uses ProfileService-style entries (queried by user_id),
            // not the older ProfileManager hybrid linkage via profile_entry_id.
            // So migration must create/normalize ProfileService-compatible entries.
            require_once __DIR__ . '/../includes/UserService.php';
            require_once __DIR__ . '/../includes/ProfileService.php';

            $profileEnabled = function_exists('get_plugin_setting')
                ? (bool)get_plugin_setting('user_management', 'profile_collection_enabled', false)
                : false;
            $profileCollection = function_exists('get_plugin_setting')
                ? (string)get_plugin_setting('user_management', 'profile_collection_slug', '')
                : '';

            if (!$profileEnabled) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Collection profiles are not enabled']);
                break;
            }
            if ($profileCollection === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Profile collection is not configured']);
                break;
            }

            $usersService = new UserService();
            $profiles = new ProfileService();

            if (!$profiles->collectionExists($profileCollection)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Profile collection does not exist']);
                break;
            }

            $user_ids = $input['user_ids'] ?? null; // Optional: specific users, or null for all
            if ($user_ids !== null && !is_array($user_ids)) {
                $user_ids = [$user_ids];
            }

            // Slugify helper (avoid relying on private method inside ProfileService)
            $slugify = function(string $s): string {
                $s = trim(mb_strtolower($s));
                $s = preg_replace('/[^a-z0-9]+/u', '-', $s);
                $s = trim($s, '-');
                if ($s === '') $s = 'profile';
                return substr($s, 0, 80);
            };

            // Gather users
            if ($user_ids === null) {
                // Only migrate non-deleted users
                $users = $usersService->listUsers(['deleted' => false], 1000000, 0);
            } else {
                $users = [];
                foreach ($user_ids as $uid) {
                    $u = $usersService->getUser((string)$uid);
                    if ($u && !($u['deleted'] ?? false)) {
                        $users[] = $u;
                    }
                }
            }

            $created = 0;
            $normalized = 0;
            $errors = [];

            foreach ($users as $u) {
                $uid = (string)($u['id'] ?? '');
                if ($uid === '') continue;

                try {
                    $existing = $profiles->getProfileByUserId($profileCollection, $uid);
                    if (!$existing) {
                        $newProfile = $profiles->ensureProfileForUser($profileCollection, $u);
                        if ($newProfile) {
                            $created++;
                        } else {
                            $errors[] = "Failed to create profile for user {$uid}";
                        }
                        continue;
                    }

                    // Normalize existing profile entry to expected shape without overwriting custom fields
                    $changed = false;
                    if (!isset($existing['id']) || (string)$existing['id'] === '') { $existing['id'] = 'profile_' . substr(sha1($uid), 0, 12); $changed = true; }
                    if (!isset($existing['user_id']) || (string)$existing['user_id'] === '') { $existing['user_id'] = $uid; $changed = true; }
                    if (!isset($existing['name']) || (string)$existing['name'] === '') { $existing['name'] = (string)($u['name'] ?? $u['username'] ?? $u['email'] ?? $uid); $changed = true; }
                    if (!isset($existing['slug']) || (string)$existing['slug'] === '') { $existing['slug'] = $slugify((string)$existing['name']); $changed = true; }
                    if (!array_key_exists('bio', $existing)) { $existing['bio'] = ''; $changed = true; }
                    if (!array_key_exists('avatar', $existing)) { $existing['avatar'] = null; $changed = true; }
                    if (!isset($existing['connections']) || !is_array($existing['connections'])) { $existing['connections'] = []; $changed = true; }
                    if (!isset($existing['following']) || !is_array($existing['following'])) { $existing['following'] = []; $changed = true; }
                    if (!isset($existing['followers']) || !is_array($existing['followers'])) { $existing['followers'] = []; $changed = true; }
                    if (!isset($existing['status']) || (string)$existing['status'] === '') { $existing['status'] = 'published'; $changed = true; }
                    if (!isset($existing['created_at']) || (string)$existing['created_at'] === '') { $existing['created_at'] = date('c'); $changed = true; }

                    if ($changed) {
                        // Ensure we save to the same file id key used by storage
                        if ($profiles->saveProfile($profileCollection, $existing)) {
                            $normalized++;
                        } else {
                            $errors[] = "Failed to normalize profile for user {$uid}";
                        }
                    }
                } catch (Exception $e) {
                    $errors[] = "Error migrating user {$uid}: " . $e->getMessage();
                }
            }

            echo json_encode([
                'success' => true,
                'message' => "Created {$created} profiles; normalized {$normalized}.",
                'migrated' => $created,
                'normalized' => $normalized,
                'total' => count($users),
                'errors' => $errors
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
