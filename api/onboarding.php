<?php
/**
 * Onboarding API
 * Handles onboarding status, progress tracking, and setup completion
 */

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/storage.php';

// Helper functions - always available (even when included)
// Helper to check if there are any users
function hasUsers() {
    $usersDir = __DIR__ . '/../users';
    if (!is_dir($usersDir)) {
        return false;
    }
    $files = array_filter(scandir($usersDir), function($f) {
        return $f !== '.' && $f !== '..' && pathinfo($f, PATHINFO_EXTENSION) === 'json';
    });
    return count($files) > 0;
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

// Helper to get onboarding status
function getOnboardingStatus($userId = null) {
    $settingsStorage = new Storage('../settings');
    $onboardingData = $settingsStorage->read('onboarding.json') ?? [];
    
    $needsFirstUser = !hasUsers();
    $isFirstTime = isFirstTimeSetup();
    
    // If no userId provided (not logged in yet), return basic status
    if ($userId === null) {
        return [
            'needs_first_user' => $needsFirstUser,
            'is_first_time' => $isFirstTime,
            'completed' => false
        ];
    }
    
    // Check user-specific onboarding
    $userStorage = new Storage('../users');
    $userOnboardingFile = md5($userId) . '_onboarding.json';
    $userOnboarding = $userStorage->read($userOnboardingFile) ?? [];
    
    return [
        'needs_first_user' => $needsFirstUser,
        'is_first_time' => $isFirstTime,
        'completed' => $userOnboarding['completed'] ?? false,
        'setup_wizard_completed' => $userOnboarding['setup_wizard_completed'] ?? false,
        'demo_content_installed' => $userOnboarding['demo_content_installed'] ?? false,
        'demo_template_installed' => $userOnboarding['demo_template_installed'] ?? false,
        'progress' => $userOnboarding['progress'] ?? [],
        'last_step' => $userOnboarding['last_step'] ?? null,
        'started_at' => $userOnboarding['started_at'] ?? null,
        'completed_at' => $userOnboarding['completed_at'] ?? null
    ];
}

// Helper to save onboarding progress
function saveOnboardingProgress($userId, $data) {
    $userStorage = new Storage('../users');
    $userOnboardingFile = md5($userId) . '_onboarding.json';
    
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
        
        // Validate password strength (minimum 8 characters)
        if (strlen($password) < 8) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Password must be at least 8 characters']);
            exit;
        }
        
        // Create users directory if it doesn't exist
        $usersDir = __DIR__ . '/../users';
        if (!is_dir($usersDir)) {
            mkdir($usersDir, 0755, true);
        }
        
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
        
        echo json_encode([
            'status' => 'ok',
            'message' => 'First admin user created and logged in',
            'data' => [
                'user' => $user,
                'onboarding' => getOnboardingStatus($userId)
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
            'completed_at' => date('c')
        ];
        
        // Store demo website URL if provided
        if ($demoWebsiteUrl) {
            $progressData['demo_website_url'] = $demoWebsiteUrl;
        }
        if ($demoContentChoice) {
            $progressData['demo_content_choice'] = $demoContentChoice;
        }
        
        saveOnboardingProgress($userId, $progressData);
        
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
            'completed' => true,
            'skipped' => true,
            'completed_at' => date('c')
        ]);
        
        echo json_encode([
            'status' => 'ok',
            'message' => 'Onboarding skipped',
            'data' => getOnboardingStatus($userId)
        ]);
        exit;
        
        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
            exit;
    }
}

