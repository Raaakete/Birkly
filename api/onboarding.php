<?php
/**
 * Onboarding API
 * Handles onboarding status, progress tracking, and setup completion
 */

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/storage.php';

// Helper functions - always available (even when included)
// Helper to check if this is a first-time setup
function isFirstTimeSetup() {
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
function getOnboardingStatus($userId) {
    $settingsStorage = new Storage('../settings');
    $onboardingData = $settingsStorage->read('onboarding.json') ?? [];
    
    // Check user-specific onboarding
    $userStorage = new Storage('../users');
    $userOnboardingFile = md5($userId) . '_onboarding.json';
    $userOnboarding = $userStorage->read($userOnboardingFile) ?? [];
    
    return [
        'is_first_time' => isFirstTimeSetup(),
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
    
    // Check authentication
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    switch ($action) {
    case 'status':
        // Get onboarding status
        $userId = $_SESSION['user']['email'] ?? $_SESSION['user']['id'] ?? 'unknown';
        $status = getOnboardingStatus($userId);
        
        echo json_encode([
            'status' => 'ok',
            'data' => $status
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
        
        saveOnboardingProgress($userId, [
            'completed' => true,
            'completed_at' => date('c')
        ]);
        
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

