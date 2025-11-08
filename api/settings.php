<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// Only set headers if this file is being called directly (not included)
// When included via admin/index.php, headers are already sent, so skip
if (!headers_sent() && !isset($GLOBALS['__HARU_INTERNAL_CALL'])) {
    header('Content-Type: application/json');
}

require_once 'storage.php';
require_once 'users.php';
require_once 'auth.php';
require_once 'validate.php';
require_once '../core/csrf.php';
require_once 'log.php';

$storage = new Storage('../settings');
$input = sanitize_input(json_decode(file_get_contents('php://input'), true));
$action = $input['action'] ?? $_GET['action'] ?? '';

// Default settings structure
$defaultSettings = [
    'site' => [
        'name' => 'HaruCMS',
        'description' => 'A simple, flat-file CMS',
        'url' => '',
        'timezone' => 'UTC',
        'language' => 'en',
        'date_format' => 'Y-m-d',
        'time_format' => 'H:i:s'
    ],
    'admin' => [
        'project_name' => 'HaruCMS',
        'project_description' => '',
        'cms_url' => '',
        'items_per_page' => 20,
        'default_collection' => '',
        'show_welcome' => true,
        'theme' => 'light'
    ],
    'media' => [
        'max_upload_size' => 5242880, // 5MB
        'allowed_types' => ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'],
        'image_quality' => 85,
        'create_thumbnails' => true,
        'thumbnail_size' => [150, 150]
    ],
    'security' => [
        'session_timeout' => 3600, // 1 hour
        'password_min_length' => 8,
        'require_strong_password' => true,
        'max_login_attempts' => 5,
        'lockout_time' => 900 // 15 minutes
    ]
];

// Handle simple get requests without authentication
if ($action === 'get') {
    $category = $_GET['category'] ?? '';
    
    if ($category === 'branding') {
        // Return branding settings without authentication
        $branding = $storage->read('branding.json') ?? [
            'logoUrl' => '',
            'primaryColor' => '#3b82f6',
            'secondaryColor' => '#64748b'
        ];
        echo json_encode(['status' => 'ok', 'data' => ['settings' => $branding]]);
        exit;
    }
    
    if ($category === 'all') {
        // Return all settings without authentication for basic display
        $settings = [];
        foreach (['site', 'admin', 'media', 'security'] as $cat) {
            $settings[$cat] = $storage->read("$cat.json") ?? $defaultSettings[$cat];
        }
        echo json_encode(['status' => 'ok', 'data' => ['settings' => $settings]]);
        exit;
    }
}

// All other actions require authentication and manage_settings permission
requireAuth('manage_settings');

// Add support for saving all settings at once, including branding
if ($action === 'save_all') {
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        log_security_event('permission_denied', ['action' => 'settings_save_all', 'reason' => 'invalid CSRF']);
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    $settings = $input['settings'] ?? [];
    if (empty($settings)) {
        echo json_encode(['status' => 'error', 'message' => 'Settings required']);
        exit;
    }
    // Save core categories
    foreach (['site', 'admin', 'media', 'security'] as $cat) {
        $catSettings = [];
        foreach ($settings as $k => $v) {
            if (strpos($k, $cat) === 0) {
                $key = lcfirst(substr($k, strlen($cat)));
                $catSettings[$key] = $v;
            }
        }
        if (!empty($catSettings)) {
            $existing = $storage->read("$cat.json") ?? [];
            $updated = array_merge($existing, $catSettings);
            $updated['_updated_at'] = date('c');
            $updated['_updated_by'] = $_SESSION['user']['id'];
            $storage->write("$cat.json", $updated);
        }
    }
    // Save branding settings
    $branding = [];
    foreach ($settings as $k => $v) {
        if (strpos($k, 'branding') === 0) {
            $key = lcfirst(substr($k, strlen('branding')));
            $branding[$key] = $v;
        }
    }
    if (!empty($branding)) {
        $existing = $storage->read('branding.json') ?? [];
        $updated = array_merge($existing, $branding);
        $updated['_updated_at'] = date('c');
        $updated['_updated_by'] = $_SESSION['user']['id'];
        $storage->write('branding.json', $updated);
    }
    log_security_event('settings_save_all', ['user' => $_SESSION['user']['email'] ?? 'unknown']);
    echo json_encode(['status' => 'ok', 'message' => 'Settings saved']);
    exit;
}

// Update settings
if ($action === 'update') {
    // Check authentication first
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        log_security_event('permission_denied', ['action' => 'settings_update', 'reason' => 'invalid CSRF']);
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    $category = $input['category'] ?? '';
    $settings = $input['settings'] ?? [];
    
    if (empty($category) || empty($settings)) {
        echo json_encode(['status' => 'error', 'message' => 'Category and settings required']);
        exit;
    }
    
    // Validate settings against defaults
    if (!isset($defaultSettings[$category]) && $category !== 'branding') {
        echo json_encode(['status' => 'error', 'message' => 'Invalid category']);
        exit;
    }
    
    // Merge with existing settings
    $existing = $storage->read("$category.json") ?? [];
    $updated = array_merge($existing, $settings);
    // Add metadata
    $updated['_updated_at'] = date('c');
    $updated['_updated_by'] = $_SESSION['user']['id'];
    $result = $storage->write("$category.json", $updated);
    
    log_security_event('settings_update', ['category' => $category, 'user' => $_SESSION['user']['email'] ?? 'unknown']);
    echo json_encode([
        'status' => $result ? 'ok' : 'error',
        'message' => $result ? 'Settings updated' : 'Failed to update settings'
    ]);
    exit;
}

// Reset settings to defaults
if ($action === 'set_language') {
    $csrf = $_POST['_csrf'] ?? $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        log_security_event('permission_denied', ['action' => 'set_language', 'reason' => 'invalid CSRF']);
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $language = $_POST['language'] ?? $input['language'] ?? '';
    if (empty($language)) {
        echo json_encode(['status' => 'error', 'message' => 'Language required']);
        exit;
    }
    
    // Validate language exists
    require_once __DIR__ . '/../core/i18n.php';
    $availableLangs = I18n::getAvailableLanguages();
    if (!isset($availableLangs[$language])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid language']);
        exit;
    }
    
    // Set language in session
    if (session_status() === PHP_SESSION_NONE) {
        require_once __DIR__ . '/session.php';
        haru_secure_session_start();
    }
    $_SESSION['admin_language'] = $language;
    
    // Save to admin settings
    $adminSettings = $storage->read('admin.json') ?? $defaultSettings['admin'];
    $adminSettings['language'] = $language;
    $storage->write('admin.json', $adminSettings);
    
    // Set language in i18n system
    I18n::setLanguage($language);
    
    echo json_encode(['status' => 'ok', 'message' => 'Language changed successfully']);
    exit;
}

if ($action === 'reset') {
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        log_security_event('permission_denied', ['action' => 'settings_reset', 'reason' => 'invalid CSRF']);
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    $category = $input['category'] ?? '';
    
    if (empty($category)) {
        echo json_encode(['status' => 'error', 'message' => 'Category required']);
        exit;
    }
    
    if (!isset($defaultSettings[$category]) && $category !== 'branding') {
        echo json_encode(['status' => 'error', 'message' => 'Invalid category']);
        exit;
    }
    if ($category === 'branding') {
        $result = $storage->write('branding.json', []);
    } else {
        $result = $storage->write("$category.json", $defaultSettings[$category]);
    }
    
    log_security_event('settings_reset', ['category' => $category, 'user' => $_SESSION['user']['email'] ?? 'unknown']);
    echo json_encode([
        'status' => $result ? 'ok' : 'error',
        'message' => $result ? 'Settings reset to defaults' : 'Failed to reset settings'
    ]);
    exit;
}

// Default response
echo json_encode([
    'status' => 'ok',
    'message' => 'Settings API is working',
    'data' => []
]); 