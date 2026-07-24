<?php
/**
 * Onboarding API
 * Handles onboarding status, progress tracking, and setup completion
 */

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/../core/birkly_users.php';

// Helper functions - always available (even when included)
// Helper to check if there are any users
function hasUsers() {
    return birkly_has_users();
}

// Helper to normalize user key for onboarding file storage
function normalizeOnboardingUserKey($userId) {
    if ($userId === null || $userId === '') {
        return md5('unknown');
    }
    $userId = (string) $userId;
    if (filter_var($userId, FILTER_VALIDATE_EMAIL)) {
        return md5(strtolower(trim($userId)));
    }
    // User id is already md5(email) in Birkly user records
    if (preg_match('/^[a-f0-9]{32}$/i', $userId)) {
        return strtolower($userId);
    }
    return md5(strtolower(trim($userId)));
}

// Whether the setup wizard should be shown (independent of pre-installed demo content)
function shouldRedirectToOnboarding($userId = null) {
    if (!hasUsers()) {
        return true;
    }
    if ($userId === null) {
        return false;
    }
    $status = getOnboardingStatus($userId);
    return !($status['setup_wizard_completed'] ?? false);
}

// Helper to check if this is a first-time setup
function isFirstTimeSetup() {
    // First check if there are any users - if not, it's definitely first-time
    if (!hasUsers()) {
        return true;
    }
    
    $storage = new Storage('../content');
    $collections = $storage->list('collections');
    
    // Filter out empty collections
    $hasContent = false;
    foreach ($collections as $dir) {
        $entriesDir = __DIR__ . "/../content/collections/$dir/entries";
        if (is_dir($entriesDir)) {
            $files = array_filter(scandir($entriesDir), function($f) {
                return $f !== '.' && $f !== '..' && pathinfo($f, PATHINFO_EXTENSION) === 'json';
            });
            if (count($files) > 0) {
                $hasContent = true;
                break;
            }
        }
    }
    
    return !$hasContent;
}

// Helper to get site-wide onboarding settings (install path, etc.)
function getSiteOnboardingSettings() {
    $settingsStorage = new Storage('../settings');
    return $settingsStorage->read('onboarding.json') ?? [];
}

function saveSiteOnboardingSettings(array $data) {
    $settingsStorage = new Storage('../settings');
    $existing = $settingsStorage->read('onboarding.json') ?? [];
    return $settingsStorage->write('onboarding.json', array_merge($existing, $data));
}

// Helper to get onboarding status
function getOnboardingStatus($userId = null) {
    $siteOnboarding = getSiteOnboardingSettings();
    
    $needsFirstUser = !hasUsers();
    $isFirstTime = isFirstTimeSetup();
    
    // If no userId provided (not logged in yet), return basic status
    if ($userId === null) {
        $projectRoot = dirname(__DIR__);
        require_once __DIR__ . '/../core/demo_starter_templates.php';
        require_once __DIR__ . '/../core/birkly_paths.php';
        return [
            'needs_first_user' => $needsFirstUser,
            'is_first_time' => $isFirstTime,
            'completed' => false,
            'install_path' => $siteOnboarding['install_path'] ?? null,
            'starter_availability' => birkly_demo_starter_availability($projectRoot),
            'is_local_dev' => birkly_is_local_dev_request(),
        ];
    }
    
    // Check user-specific onboarding
    $userStorage = new Storage('../users');
    $userOnboardingFile = normalizeOnboardingUserKey($userId) . '_onboarding.json';
    $userOnboarding = $userStorage->read($userOnboardingFile) ?? [];
    
    $progress = $userOnboarding['progress'] ?? [];
    $projectRoot = dirname(__DIR__);
    require_once __DIR__ . '/../core/demo_starter_templates.php';
    require_once __DIR__ . '/../core/birkly_paths.php';
    
    return [
        'needs_first_user' => $needsFirstUser,
        'is_first_time' => $isFirstTime,
        'completed' => $userOnboarding['completed'] ?? false,
        'skipped' => $userOnboarding['skipped'] ?? false,
        'setup_wizard_completed' => $userOnboarding['setup_wizard_completed'] ?? false,
        'demo_content_installed' => $userOnboarding['demo_content_installed'] ?? false,
        'demo_template_installed' => $userOnboarding['demo_template_installed'] ?? false,
        'progress' => $progress,
        'last_step' => $userOnboarding['last_step'] ?? null,
        'use_case' => $progress['use_case'] ?? ($userOnboarding['use_case'] ?? null),
        'ai_skipped' => !empty($progress['ai_skipped']) || !empty($progress['ai_skipped_local']) || !empty($userOnboarding['ai_skipped']),
        'ai_connected' => !empty($progress['ai_connected']) || !empty($userOnboarding['ai_connected']),
        'install_path' => $siteOnboarding['install_path'] ?? null,
        'checklist_dismissed' => $userOnboarding['checklist_dismissed'] ?? [],
        'checklist_hidden' => $userOnboarding['checklist_hidden'] ?? false,
        'started_at' => $userOnboarding['started_at'] ?? null,
        'completed_at' => $userOnboarding['completed_at'] ?? null,
        'starter_availability' => birkly_demo_starter_availability($projectRoot),
        'is_local_dev' => birkly_is_local_dev_request(),
    ];
}

// Helper to save onboarding progress
function saveOnboardingProgress($userId, $data) {
    $userStorage = new Storage('../users');
    $userOnboardingFile = normalizeOnboardingUserKey($userId) . '_onboarding.json';
    
    $existing = $userStorage->read($userOnboardingFile) ?? [];
    $updated = array_merge($existing, $data);
    
    if (!isset($updated['started_at']) && isset($data['started_at'])) {
        $updated['started_at'] = date('c');
    }
    
    if (isset($data['completed']) && $data['completed'] === true && !isset($updated['completed_at'])) {
        $updated['completed_at'] = date('c');
    }
    
    return $userStorage->write($userOnboardingFile, $updated);
}

// Only execute API logic if this file is called directly (not included)
if (!isset($GLOBALS['__HARU_INTERNAL_CALL'])) {
    haru_secure_session_start();
    
    header('Content-Type: application/json');
    
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    
    // Allow 'status' and 'create_first_user' actions without authentication
    $allowedWithoutAuth = ['status', 'create_first_user'];
    
    // Check authentication (unless action is allowed without auth)
    if (!in_array($action, $allowedWithoutAuth) && !isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }

    switch ($action) {
    case 'status':
        // Get onboarding status - can work without authentication for first user check
        $userId = isset($_SESSION['user']) ? ($_SESSION['user']['email'] ?? $_SESSION['user']['id'] ?? 'unknown') : null;
        $status = getOnboardingStatus($userId);
        
        // Persist install path from query param when wizard loads
        $installParam = $_GET['install'] ?? '';
        if (in_array($installParam, ['local', 'cloud'], true)) {
            saveSiteOnboardingSettings(['install_path' => $installParam]);
            $status['install_path'] = $installParam;
        }
        
        echo json_encode([
            'status' => 'ok',
            'data' => $status
        ]);
        exit;
        
    case 'create_first_user':
        // Create first admin user - no authentication required (this is how we get the first user)
        $name = trim($input['name'] ?? '');
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';
        
        if (empty($name) || empty($email) || empty($password)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Name, email and password required']);
            exit;
        }
        
        // Validate name
        if (strlen($name) < 2) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Name must be at least 2 characters']);
            exit;
        }
        
        // Check if users already exist
        if (hasUsers()) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Users already exist. Cannot create first user.']);
            exit;
        }
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid email format']);
            exit;
        }
        
        // SECURITY FIX: Validate password strength
        require_once __DIR__ . '/validate.php';
        $passwordValidation = validate_password_strength($password);
        if (!$passwordValidation['valid']) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => $passwordValidation['message']]);
            exit;
        }
        
        // Create users directory if it doesn't exist
        $usersDir = birkly_users_write_dir();
        
        // Create first admin user
        require_once __DIR__ . '/storage.php';
        require_once __DIR__ . '/users.php'; // For hashPassword function
        
        $userId = md5(strtolower(trim($email)));
        $userFile = $userId . '.json';
        
        if (file_exists($usersDir . '/' . $userFile)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'User already exists']);
            exit;
        }
        
        $user = [
            'id' => $userId,
            'name' => $name,
            'email' => strtolower(trim($email)),
            'password' => hashPassword($password),
            'role' => 'admin',
            'created_at' => date('c'),
            'is_first_user' => true
        ];
        
        $result = file_put_contents($usersDir . '/' . $userFile, json_encode($user, JSON_PRETTY_PRINT));
        
        if ($result === false) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to create user']);
            exit;
        }
        
        // Auto-login the user
        unset($user['password']);
        haru_secure_session_start();
        $_SESSION['user'] = $user;
        $_SESSION['last_activity'] = time();
        require_once __DIR__ . '/../core/csrf.php';
        generate_csrf_token();
        
        echo json_encode([
            'status' => 'ok',
            'message' => 'First admin user created and logged in',
            'data' => [
                'user' => $user,
                'onboarding' => getOnboardingStatus($user['email'])
            ]
        ]);
        exit;
        
    case 'start':
        // Start onboarding
        $userId = $_SESSION['user']['email'] ?? $_SESSION['user']['id'] ?? 'unknown';
        
        saveOnboardingProgress($userId, [
            'started_at' => date('c'),
            'completed' => false,
            'current_step' => 'welcome'
        ]);
        
        echo json_encode([
            'status' => 'ok',
            'message' => 'Onboarding started',
            'data' => getOnboardingStatus($userId)
        ]);
        exit;
        
    case 'update_progress':
        // Update onboarding progress
        $userId = $_SESSION['user']['email'] ?? $_SESSION['user']['id'] ?? 'unknown';
        
        $updates = [];
        if (isset($input['step'])) {
            $updates['last_step'] = $input['step'];
            $updates['current_step'] = $input['step'];
        }
        if (isset($input['setup_wizard_completed'])) {
            $updates['setup_wizard_completed'] = (bool)$input['setup_wizard_completed'];
        }
        if (isset($input['demo_content_installed'])) {
            $updates['demo_content_installed'] = (bool)$input['demo_content_installed'];
        }
        if (isset($input['demo_template_installed'])) {
            $updates['demo_template_installed'] = (bool)$input['demo_template_installed'];
        }
        if (isset($input['progress'])) {
            $updates['progress'] = is_array($input['progress']) ? $input['progress'] : [];
        }

        $userStorage = new Storage('../users');
        $existing = $userStorage->read(normalizeOnboardingUserKey($userId) . '_onboarding.json') ?? [];

        // Store ad-hoc progress flags (e.g. demo_content_kept)
        $reserved = ['step', 'setup_wizard_completed', 'demo_content_installed', 'demo_template_installed', 'progress'];
        foreach ($input as $key => $value) {
            if (in_array($key, $reserved, true)) {
                continue;
            }
            if (!isset($updates['progress']) || !is_array($updates['progress'])) {
                $updates['progress'] = $existing['progress'] ?? [];
            }
            $updates['progress'][$key] = $value;
        }
        
        saveOnboardingProgress($userId, $updates);
        
        echo json_encode([
            'status' => 'ok',
            'message' => 'Progress updated',
            'data' => getOnboardingStatus($userId)
        ]);
        exit;
        
    case 'complete':
        // Mark onboarding as complete
        $userId = $_SESSION['user']['email'] ?? $_SESSION['user']['id'] ?? 'unknown';
        
        // Get demo website URL and choice from request
        $demoWebsiteUrl = $input['demo_website_url'] ?? null;
        $demoContentChoice = $input['demo_content_choice'] ?? null;
        
        $progressData = [
            'completed' => true,
            'setup_wizard_completed' => true,
            'completed_at' => date('c')
        ];
        
        if ($demoContentChoice === 'keep') {
            $progressData['demo_content_installed'] = true;
        } elseif ($demoContentChoice === 'delete') {
            $progressData['demo_content_installed'] = false;
        }
        
        // Store demo website URL if provided
        if ($demoWebsiteUrl) {
            $progressData['demo_website_url'] = $demoWebsiteUrl;
        }
        if ($demoContentChoice) {
            $progressData['demo_content_choice'] = $demoContentChoice;
        }
        
        // Track AI skip for dashboard checklist (P19-D4 / P5.6-D)
        $userStorage = new Storage('../users');
        $existing = $userStorage->read(normalizeOnboardingUserKey($userId) . '_onboarding.json') ?? [];
        $existingProgress = $existing['progress'] ?? [];
        if (!empty($existingProgress['ai_skipped'])) {
            $progressData['ai_skipped'] = true;
        }
        if (!empty($existingProgress['ai_connected'])) {
            $progressData['ai_connected'] = true;
        }
        
        saveOnboardingProgress($userId, $progressData);

        require_once __DIR__ . '/../core/site_domain.php';
        birkly_ensure_site_domain_registered();
        
        // Store in session for modal display
        if ($demoWebsiteUrl) {
            $_SESSION['onboarding_demo_website_url'] = $demoWebsiteUrl;
        }
        if ($demoContentChoice) {
            $_SESSION['onboarding_demo_choice'] = $demoContentChoice;
        }
        
        echo json_encode([
            'status' => 'ok',
            'message' => 'Onboarding completed',
            'data' => getOnboardingStatus($userId)
        ]);
        exit;
        
    case 'skip':
        // Skip onboarding
        $userId = $_SESSION['user']['email'] ?? $_SESSION['user']['id'] ?? 'unknown';
        
        saveOnboardingProgress($userId, [
            'skipped' => true,
            'setup_wizard_completed' => true,
            'completed' => true,
            'completed_at' => date('c')
        ]);
        
        echo json_encode([
            'status' => 'ok',
            'message' => 'Onboarding skipped',
            'data' => getOnboardingStatus($userId)
        ]);
        exit;

    case 'set_install_path':
        $installPath = $input['install_path'] ?? ($_GET['install'] ?? '');
        if (!in_array($installPath, ['local', 'cloud'], true)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'install_path must be local or cloud']);
            exit;
        }
        saveSiteOnboardingSettings(['install_path' => $installPath]);
        echo json_encode([
            'status' => 'ok',
            'message' => 'Install path saved',
            'data' => ['install_path' => $installPath]
        ]);
        exit;

    case 'dismiss_checklist_item':
        $userId = $_SESSION['user']['email'] ?? $_SESSION['user']['id'] ?? 'unknown';
        $itemId = $input['item_id'] ?? '';
        if ($itemId === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'item_id required']);
            exit;
        }
        $userStorage = new Storage('../users');
        $file = normalizeOnboardingUserKey($userId) . '_onboarding.json';
        $existing = $userStorage->read($file) ?? [];
        $dismissed = $existing['checklist_dismissed'] ?? [];
        if (!in_array($itemId, $dismissed, true)) {
            $dismissed[] = $itemId;
        }
        saveOnboardingProgress($userId, ['checklist_dismissed' => $dismissed]);
        echo json_encode(['status' => 'ok', 'data' => getOnboardingStatus($userId)]);
        exit;

    case 'hide_checklist':
        $userId = $_SESSION['user']['email'] ?? $_SESSION['user']['id'] ?? 'unknown';
        saveOnboardingProgress($userId, ['checklist_hidden' => true]);
        echo json_encode(['status' => 'ok', 'data' => getOnboardingStatus($userId)]);
        exit;
        
        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
            exit;
    }
}

