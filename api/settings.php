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
// Handle both JSON and form-encoded data
$rawInput = file_get_contents('php://input');
$input = [];
if (!empty($rawInput)) {
    $decoded = json_decode($rawInput, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $input = sanitize_input($decoded);
    }
}
// Get action from POST, input, or GET (in that order)
$action = $_POST['action'] ?? $input['action'] ?? $_GET['action'] ?? '';

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

// Handle logo upload
if ($action === 'upload_logo') {
    $csrf = $_POST['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        log_security_event('permission_denied', ['action' => 'upload_logo', 'reason' => 'invalid CSRF']);
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['status' => 'error', 'message' => 'No file uploaded or upload error']);
        exit;
    }
    
    $file = $_FILES['file'];
    
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid file type. Only images are allowed.']);
        exit;
    }
    
    // Validate file size (max 2MB for logo)
    if ($file['size'] > 2 * 1024 * 1024) {
        echo json_encode(['status' => 'error', 'message' => 'File too large. Maximum size is 2MB.']);
        exit;
    }
    
    // Create admin/assets directory if it doesn't exist
    $assetsDir = __DIR__ . '/../admin/assets';
    if (!is_dir($assetsDir)) {
        mkdir($assetsDir, 0755, true);
    }
    
    // Generate unique filename
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'project-logo-' . uniqid() . '.' . $extension;
    $filepath = $assetsDir . '/' . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        $logoPath = '/admin/assets/' . $filename;
        
        // Save to branding settings
        $branding = $storage->read('branding.json') ?? [];
        $branding['logoPath'] = $logoPath;
        $branding['_updated_at'] = date('c');
        $branding['_updated_by'] = $_SESSION['user']['id'] ?? 'unknown';
        $storage->write('branding.json', $branding);
        
        log_security_event('logo_uploaded', ['user' => $_SESSION['user']['email'] ?? 'unknown', 'file' => $filename]);
        echo json_encode([
            'status' => 'ok',
            'message' => 'Logo uploaded successfully',
            'data' => ['logoPath' => $logoPath]
        ]);
        exit;
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to save file']);
        exit;
    }
}

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
    
    // Map form field names to settings structure
    // Admin settings
    $adminSettings = [];
    if (isset($settings['projectName'])) {
        $adminSettings['project_name'] = $settings['projectName'];
    }
    if (isset($settings['cmsUrl'])) {
        $adminSettings['cms_url'] = $settings['cmsUrl'];
    }
    if (isset($settings['projectDescription'])) {
        $adminSettings['project_description'] = $settings['projectDescription'];
    }
    
    // Site settings
    $siteSettings = [];
    if (isset($settings['siteTimezone'])) {
        $siteSettings['timezone'] = $settings['siteTimezone'];
    }
    if (isset($settings['siteLanguage'])) {
        $siteSettings['language'] = $settings['siteLanguage'];
    }
    
    // Media settings
    $mediaSettings = [];
    if (isset($settings['mediaMaxUploadSize'])) {
        $mediaSettings['max_upload_size'] = intval($settings['mediaMaxUploadSize']) * 1024 * 1024; // Convert MB to bytes
    }
    if (isset($settings['mediaImageQuality'])) {
        $mediaSettings['image_quality'] = intval($settings['mediaImageQuality']);
    }
    if (isset($settings['mediaThumbnailWidth'])) {
        $mediaSettings['thumbnail_size'][0] = intval($settings['mediaThumbnailWidth']);
    }
    if (isset($settings['mediaThumbnailHeight'])) {
        $mediaSettings['thumbnail_size'][1] = intval($settings['mediaThumbnailHeight']);
    }
    if (isset($settings['mediaCreateThumbnails'])) {
        $mediaSettings['create_thumbnails'] = ($settings['mediaCreateThumbnails'] === 'on' || $settings['mediaCreateThumbnails'] === true);
    }
    
    // Security settings
    $securitySettings = [];
    if (isset($settings['securitySessionTimeout'])) {
        $securitySettings['session_timeout'] = intval($settings['securitySessionTimeout']) * 60; // Convert minutes to seconds
    }
    if (isset($settings['securityMaxLoginAttempts'])) {
        $securitySettings['max_login_attempts'] = intval($settings['securityMaxLoginAttempts']);
    }
    if (isset($settings['securityPasswordMinLength'])) {
        $securitySettings['password_min_length'] = intval($settings['securityPasswordMinLength']);
    }
    if (isset($settings['securityLockoutTime'])) {
        $securitySettings['lockout_time'] = intval($settings['securityLockoutTime']) * 60; // Convert minutes to seconds
    }
    if (isset($settings['securityRequireStrongPassword'])) {
        $securitySettings['require_strong_password'] = ($settings['securityRequireStrongPassword'] === 'on' || $settings['securityRequireStrongPassword'] === true);
    }
    if (isset($settings['securityEnableCsrf'])) {
        $securitySettings['enable_csrf'] = ($settings['securityEnableCsrf'] === 'on' || $settings['securityEnableCsrf'] === true);
    }
    
    // Branding settings
    $branding = [];
    if (isset($settings['brandingPrimaryColor'])) {
        $branding['primaryColor'] = $settings['brandingPrimaryColor'];
    }
    if (isset($settings['brandingSecondaryColor'])) {
        $branding['secondaryColor'] = $settings['brandingSecondaryColor'];
    }
    // Handle logoPath if provided directly
    if (isset($settings['brandingLogoPath']) && !empty($settings['brandingLogoPath'])) {
        $branding['logoPath'] = $settings['brandingLogoPath'];
    }
    
    // Save each category
    if (!empty($adminSettings)) {
        $existing = $storage->read('admin.json') ?? [];
        $updated = array_merge($existing, $adminSettings);
        $updated['_updated_at'] = date('c');
        $updated['_updated_by'] = $_SESSION['user']['id'];
        $storage->write('admin.json', $updated);
    }
    
    if (!empty($siteSettings)) {
        $existing = $storage->read('site.json') ?? [];
        $updated = array_merge($existing, $siteSettings);
        $updated['_updated_at'] = date('c');
        $updated['_updated_by'] = $_SESSION['user']['id'];
        $storage->write('site.json', $updated);
    }
    
    if (!empty($mediaSettings)) {
        $existing = $storage->read('media.json') ?? [];
        // Handle thumbnail_size array merge
        if (isset($mediaSettings['thumbnail_size']) && isset($existing['thumbnail_size'])) {
            $mediaSettings['thumbnail_size'] = array_merge($existing['thumbnail_size'], $mediaSettings['thumbnail_size']);
        }
        $updated = array_merge($existing, $mediaSettings);
        $updated['_updated_at'] = date('c');
        $updated['_updated_by'] = $_SESSION['user']['id'];
        $storage->write('media.json', $updated);
    }
    
    if (!empty($securitySettings)) {
        $existing = $storage->read('security.json') ?? [];
        $updated = array_merge($existing, $securitySettings);
        $updated['_updated_at'] = date('c');
        $updated['_updated_by'] = $_SESSION['user']['id'];
        $storage->write('security.json', $updated);
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

// Set language
if ($action === 'set_language') {
    // Start session first
    require_once __DIR__ . '/session.php';
    haru_secure_session_start();
    
    // Check authentication
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    
    // Handle both form-encoded and JSON data
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