<?php
/**
 * Onboarding API
 * Handles onboarding status, progress tracking, and setup completion
 */

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/../core/birkly_users.php';
require_once __DIR__ . '/../core/birkly_paths.php';

/**
 * Storage rooted at the install tree (cwd-safe).
 */
function birkly_onboarding_storage(string $dir): Storage
{
    return new Storage(birkly_project_root() . '/' . $dir);
}

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
    
    $storage = birkly_onboarding_storage('content');
    $collections = $storage->list('collections');
    
    // Filter out empty collections
    $hasContent = false;
    foreach ($collections as $dir) {
        $entriesDir = birkly_project_root() . "/content/collections/$dir/entries";
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
    $settingsStorage = birkly_onboarding_storage('settings');
    return $settingsStorage->read('onboarding.json') ?? [];
}

function saveSiteOnboardingSettings(array $data) {
    $settingsStorage = birkly_onboarding_storage('settings');
    $existing = $settingsStorage->read('onboarding.json') ?? [];
    return $settingsStorage->write('onboarding.json', array_merge($existing, $data));
}

/**
 * Helper to get onboarding status.
 *
 * @param bool|null $includeStarterAvailability When null, starters are fetched only
 *        while setup is still in progress (avoids outbound catalog hits on every admin page).
 */
function getOnboardingStatus($userId = null, ?bool $includeStarterAvailability = null) {
    $siteOnboarding = getSiteOnboardingSettings();
    
    $needsFirstUser = !hasUsers();
    $isFirstTime = isFirstTimeSetup();
    $projectRoot = dirname(__DIR__);
    require_once __DIR__ . '/../core/birkly_paths.php';
    
    // If no userId provided (not logged in yet), return basic status
    if ($userId === null) {
        $includeStarters = $includeStarterAvailability ?? true;
        $status = [
            'needs_first_user' => $needsFirstUser,
            'is_first_time' => $isFirstTime,
            'completed' => false,
            'install_path' => $siteOnboarding['install_path'] ?? null,
            'is_local_dev' => birkly_is_local_dev_request(),
        ];
        if ($includeStarters) {
            require_once __DIR__ . '/../core/demo_starter_templates.php';
            $status['starter_availability'] = birkly_demo_starter_availability($projectRoot);
        }
        return $status;
    }
    
    // Check user-specific onboarding
    $userStorage = birkly_onboarding_storage('users');
    $userOnboardingFile = normalizeOnboardingUserKey($userId) . '_onboarding.json';
    $userOnboarding = $userStorage->read($userOnboardingFile) ?? [];
    
    $progress = $userOnboarding['progress'] ?? [];
    $completed = (bool) ($userOnboarding['completed'] ?? false);
    $includeStarters = $includeStarterAvailability;
    if ($includeStarters === null) {
        // Dashboard / admin chrome only needs completion flags — not a live catalog probe.
        $includeStarters = !$completed && ($needsFirstUser || $isFirstTime);
    }
    
    $status = [
        'needs_first_user' => $needsFirstUser,
        'is_first_time' => $isFirstTime,
        'completed' => $completed,
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
        'welcome_modal_seen' => !empty($userOnboarding['welcome_modal_seen']),
        'invited_user' => !empty($userOnboarding['invited_user']),
        'show_welcome_modal' => !empty($userOnboarding['setup_wizard_completed']) && empty($userOnboarding['welcome_modal_seen']),
        'started_at' => $userOnboarding['started_at'] ?? null,
        'completed_at' => $userOnboarding['completed_at'] ?? null,
        'is_local_dev' => birkly_is_local_dev_request(),
    ];
    if ($includeStarters) {
        require_once __DIR__ . '/../core/demo_starter_templates.php';
        $status['starter_availability'] = birkly_demo_starter_availability($projectRoot);
    }
    return $status;
}

// Helper to save onboarding progress
function saveOnboardingProgress($userId, $data) {
    $userStorage = birkly_onboarding_storage('users');
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

/**
 * Skip the install wizard for users added after initial setup (admin create / invite).
 * They still see the first-visit welcome modal on the dashboard.
 */
function birkly_init_invited_user_onboarding($userId): void
{
    saveOnboardingProgress($userId, [
        'setup_wizard_completed' => true,
        'completed' => true,
        'invited_user' => true,
        'welcome_modal_seen' => false,
        'completed_at' => date('c'),
    ]);
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
        // Get onboarding status - can work without authentication for first user check.
        // Always include starter availability here (wizard needs it; catalog is cached).
        $userId = isset($_SESSION['user']) ? ($_SESSION['user']['email'] ?? $_SESSION['user']['id'] ?? 'unknown') : null;
        $status = getOnboardingStatus($userId, true);
        
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
        $language = trim((string) ($input['language'] ?? 'en'));
        
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
        require_once __DIR__ . '/../core/i18n.php';
        $availableLangs = I18n::getAvailableLanguages();
        if ($language === '' || !isset($availableLangs[$language])) {
            $language = 'en';
        }
        
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
            'language' => $language,
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
        $_SESSION['admin_language'] = $language;
        $_SESSION['last_activity'] = time();
        require_once __DIR__ . '/../core/csrf.php';
        generate_csrf_token();
        I18n::setLanguage($language);
        
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

    case 'save_site_info':
        $userId = $_SESSION['user']['email'] ?? $_SESSION['user']['id'] ?? 'unknown';
        $projectName = trim((string) ($input['project_name'] ?? ''));
        $projectDescription = trim((string) ($input['project_description'] ?? ''));

        if (strlen($projectName) < 2) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Project name must be at least 2 characters']);
            exit;
        }

        $settingsStorage = birkly_onboarding_storage('settings');
        $adminSettings = $settingsStorage->read('admin.json') ?? [];
        $adminSettings['project_name'] = $projectName;
        $adminSettings['project_description'] = $projectDescription;
        $adminSettings['_updated_at'] = date('c');
        $adminSettings['_updated_by'] = $_SESSION['user']['id'] ?? '';
        if (!$settingsStorage->write('admin.json', $adminSettings)) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to save project settings']);
            exit;
        }

        $siteSettings = $settingsStorage->read('site.json') ?? [];
        $siteSettings['name'] = $projectName;
        if ($projectDescription !== '') {
            $siteSettings['description'] = $projectDescription;
        }
        $settingsStorage->write('site.json', $siteSettings);

        $userStorage = birkly_onboarding_storage('users');
        $existing = $userStorage->read(normalizeOnboardingUserKey($userId) . '_onboarding.json') ?? [];
        $progress = is_array($existing['progress'] ?? null) ? $existing['progress'] : [];
        $progress['project_name'] = $projectName;
        if ($projectDescription !== '') {
            $progress['project_description'] = $projectDescription;
        }

        saveOnboardingProgress($userId, [
            'last_step' => 2,
            'current_step' => 'site-info',
            'progress' => $progress,
        ]);

        echo json_encode([
            'status' => 'ok',
            'message' => 'Project information saved',
            'data' => getOnboardingStatus($userId),
        ]);
        exit;

    case 'install_starter':
        require_once __DIR__ . '/../core/demo_starter_templates.php';
        require_once __DIR__ . '/../core/site_domain.php';
        $templateId = strtolower(trim((string) ($input['template'] ?? $input['starter_template'] ?? '')));
        if ($templateId === '') {
            $templateId = 'blank';
        }
        $result = birkly_demo_install_starter_template($templateId, birkly_project_root());
        if (!$result['success']) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => $result['message'] ?? 'Install failed',
                'diagnostics' => $result['diagnostics'] ?? null,
            ]);
            exit;
        }
        birkly_ensure_site_domain_registered();
        $userId = $_SESSION['user']['email'] ?? $_SESSION['user']['id'] ?? 'unknown';
        saveOnboardingProgress($userId, [
            'starter_template' => $templateId,
            'demo_content_installed' => $templateId !== 'blank',
            'demo_content_kept' => $templateId !== 'blank',
        ]);
        echo json_encode([
            'status' => 'ok',
            'message' => $result['message'],
            'data' => $result,
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

        $userStorage = birkly_onboarding_storage('users');
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
        $userStorage = birkly_onboarding_storage('users');
        $existing = $userStorage->read(normalizeOnboardingUserKey($userId) . '_onboarding.json') ?? [];
        $existingProgress = $existing['progress'] ?? [];
        if (!empty($existingProgress['ai_skipped'])) {
            $progressData['ai_skipped'] = true;
        }
        if (!empty($existingProgress['ai_connected'])) {
            $progressData['ai_connected'] = true;
        }
        
        saveOnboardingProgress($userId, $progressData);

        require_once __DIR__ . '/../core/birkly_users.php';
        require_once __DIR__ . '/../core/admin_guide.php';
        $account = birkly_load_user_account_for_session($_SESSION['user']);
        if ($account !== null) {
            $updatedGuide = birkly_guide_merge_prefs($account, ['post_onboarding_nudge_pending' => true]);
            if ($updatedGuide !== null) {
                $_SESSION['user']['ui_preferences'] = $updatedGuide['ui_preferences'] ?? [];
            }
        }

        require_once __DIR__ . '/../core/site_domain.php';
        require_once __DIR__ . '/../core/hosting_urls.php';
        birkly_ensure_site_domain_registered();
        $hosting = birkly_hosting_load();
        if ($hosting['cms_base_url'] !== '') {
            birkly_hosting_register_domains($hosting);
        }
        
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
        $userStorage = birkly_onboarding_storage('users');
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

    case 'dismiss_welcome_modal':
        $userId = $_SESSION['user']['email'] ?? $_SESSION['user']['id'] ?? 'unknown';
        saveOnboardingProgress($userId, ['welcome_modal_seen' => true]);
        echo json_encode(['status' => 'ok', 'data' => getOnboardingStatus($userId)]);
        exit;

    default:
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        exit;
    }
}

