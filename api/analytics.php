<?php
/**
 * Birkly Self-Hosted Analytics API
 * Zero-dependency analytics tracking endpoint
 * 
 * Receives tracking data from websites and stores it locally
 */

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

header('Content-Type: application/json');

// SECURITY FIX: Replace wildcard CORS with origin whitelist
// Analytics API should allow registered domains only
require_once __DIR__ . '/../core/cors.php';
set_cors_headers('public');

    require_once 'storage.php';
    require_once 'rate_limit.php';
    require_once 'log.php';
    require_once __DIR__ . '/../core/analytics_enrich.php';
    require_once __DIR__ . '/../core/analytics_presets.php';

$action = $_GET['action'] ?? '';

// Admin: get/save analytics preset settings (P61)
if ($action === 'get_settings' || $action === 'save_settings' || $action === 'presets') {
    require_once 'auth.php';
    requireAuth('manage_settings');
    if ($action === 'presets') {
        echo json_encode([
            'status' => 'ok',
            'data' => [
                'presets' => array_values(birkly_analytics_preset_definitions()),
                'settings' => birkly_analytics_load_settings(),
                'active' => birkly_analytics_active_preset(),
            ],
        ]);
        exit;
    }
    if ($action === 'get_settings') {
        echo json_encode([
            'status' => 'ok',
            'data' => [
                'settings' => birkly_analytics_load_settings(),
                'active' => birkly_analytics_active_preset(),
                'presets' => array_values(birkly_analytics_preset_definitions()),
            ],
        ]);
        exit;
    }
    if ($action === 'save_settings') {
        require_once __DIR__ . '/../core/csrf.php';
        $input = json_decode((string) file_get_contents('php://input'), true) ?? [];
        $csrf = $input['_csrf'] ?? $_POST['_csrf'] ?? '';
        if (!validate_csrf_token($csrf)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
            exit;
        }
        if (!birkly_analytics_save_settings($input)) {
            echo json_encode(['status' => 'error', 'message' => 'Failed to save analytics settings']);
            exit;
        }
        echo json_encode([
            'status' => 'ok',
            'message' => 'Analytics settings saved',
            'data' => [
                'settings' => birkly_analytics_load_settings(),
                'active' => birkly_analytics_active_preset(),
            ],
        ]);
        exit;
    }
}

// Track page view (POST request from tracking script)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'track') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST; // Fallback to form data
    }

    // Site-wide preset Off ⇒ collect nothing new (P61-D2)
    if (!birkly_analytics_collecting()) {
        echo json_encode(['status' => 'ok', 'tracked' => false, 'reason' => 'preset_off']);
        exit;
    }

    // Standard+ requires visitor consent cookie/flag when provided by client
    if (birkly_analytics_consent_required()) {
        $consent = $input['consent'] ?? $_COOKIE['birkly_analytics_consent'] ?? null;
        if ($consent !== true && $consent !== 1 && $consent !== '1' && $consent !== 'granted') {
            echo json_encode(['status' => 'ok', 'tracked' => false, 'reason' => 'consent_required']);
            exit;
        }
    }
    
    $trackingId = $input['tracking_id'] ?? '';
    $domain = $input['domain'] ?? '';
    $path = $input['path'] ?? '/';
    $referrer = $input['referrer'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $screenWidth = $input['screen_width'] ?? 0;
    $screenHeight = $input['screen_height'] ?? 0;
    $language = $input['language'] ?? '';
    $timestamp = time();
    
    // Validate tracking ID
    if (empty($trackingId)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Tracking ID required']);
        exit;
    }
    
    // Rate limiting per IP (max 100 requests per minute)
    $rateLimiter = new RateLimiter('analytics_track_' . $ip, 100, 60);
    if (!$rateLimiter->check()) {
        http_response_code(429);
        echo json_encode(['status' => 'error', 'message' => 'Rate limit exceeded']);
        exit;
    }
    
    // Verify tracking ID (domain) exists and analytics is enabled
    // Check both tracking_ids.json (legacy) and license system
    $analyticsDir = __DIR__ . '/../data/analytics';
    if (!is_dir($analyticsDir)) {
        mkdir($analyticsDir, 0755, true);
    }
    
    $trackingConfigFile = $analyticsDir . '/tracking_ids.json';
    $trackingIds = [];
    if (file_exists($trackingConfigFile)) {
        $trackingIds = json_decode(file_get_contents($trackingConfigFile), true) ?: [];
    }
    
    // Also check license system for domain-based analytics
    require_once __DIR__ . '/domain_auth.php';
    $domainAuth = new DomainAuth();
    $licenses = $domainAuth->getAllLicenses();
    $license = $licenses[$trackingId] ?? null;
    
    $analyticsEnabled = false;
    if ($license && !empty($license['analytics_enabled'])) {
        $analyticsEnabled = true;
        // Ensure tracking config exists
        if (!isset($trackingIds[$trackingId])) {
            $trackingIds[$trackingId] = [
                'id' => $trackingId,
                'name' => $trackingId,
                'domain' => $trackingId,
                'enabled' => true,
                'created_at' => date('c'),
                'updated_at' => date('c')
            ];
            file_put_contents($trackingConfigFile, json_encode($trackingIds, JSON_PRETTY_PRINT), LOCK_EX);
        }
    } elseif (isset($trackingIds[$trackingId])) {
        // Legacy support - check old tracking_ids.json
        $config = $trackingIds[$trackingId];
        $analyticsEnabled = $config['enabled'] ?? false;
    }
    
    if (!$analyticsEnabled) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Analytics not enabled for this domain']);
        exit;
    }
    
    // Store the tracking event
    $date = date('Y-m-d', $timestamp);
    $dataDir = $analyticsDir . '/data/' . $trackingId;
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0755, true);
    }
    
    $eventFile = $dataDir . '/' . $date . '.json';
    $events = [];
    if (file_exists($eventFile)) {
        $events = json_decode(file_get_contents($eventFile), true) ?: [];
    }
    
    // Create event and filter by active preset (P61)
    $event = birkly_analytics_build_event($input, $ip, $userAgent, $timestamp);
    $event = birkly_analytics_apply_preset_to_event($event);
    if ($event === null) {
        echo json_encode(['status' => 'ok', 'tracked' => false, 'reason' => 'preset_filtered']);
        exit;
    }
    
    $events[] = $event;
    
    // Keep only last 1000 events per day (to prevent file bloat)
    if (count($events) > 1000) {
        $events = array_slice($events, -1000);
    }
    
    file_put_contents($eventFile, json_encode($events, JSON_PRETTY_PRINT), LOCK_EX);
    
    // Return 1x1 transparent pixel (for image-based tracking)
    if ($_GET['format'] === 'image') {
        header('Content-Type: image/gif');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        exit;
    }
    
    // Return JSON response
    echo json_encode([
        'status' => 'ok',
        'tracked' => true
    ]);
    exit;
}

// Get analytics data (for dashboard)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'data') {
    require_once 'auth.php';
    require_once 'validate.php';
    
    // Check authentication
    requireAuth('view_dashboard');
    
    $trackingId = $_GET['tracking_id'] ?? '';
    $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $endDate = $_GET['end_date'] ?? date('Y-m-d');
    
    if (empty($trackingId)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Tracking ID required']);
        exit;
    }
    
    $analytics = getAnalyticsData($trackingId, $startDate, $endDate);
    
    echo json_encode([
        'status' => 'ok',
        'data' => $analytics
    ]);
    exit;
}

// List all tracking IDs (for settings)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
    require_once 'auth.php';
    require_once 'validate.php';
    
    requireAuth('manage_settings');
    
    $analyticsDir = __DIR__ . '/../data/analytics';
    $trackingConfigFile = $analyticsDir . '/tracking_ids.json';
    
    $trackingIds = [];
    if (file_exists($trackingConfigFile)) {
        $trackingIds = json_decode(file_get_contents($trackingConfigFile), true) ?: [];
    }
    
    echo json_encode([
        'status' => 'ok',
        'data' => $trackingIds
    ]);
    exit;
}

// Generate inline analytics script (with full code embedded)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'inline_script') {
    $trackingId = $_GET['tracking_id'] ?? '';
    $cmsUrl = $_GET['cms_url'] ?? '';
    
    if (empty($trackingId) || empty($cmsUrl)) {
        http_response_code(400);
        header('Content-Type: text/javascript');
        echo 'console.error("Birkly Analytics: trackingId and cmsUrl are required");';
        exit;
    }
    
    // Read the analytics script file
    $scriptPath = __DIR__ . '/../birkly-analytics.js';
    $scriptContent = file_exists($scriptPath) ? file_get_contents($scriptPath) : '';
    
    if (empty($scriptContent)) {
        http_response_code(500);
        header('Content-Type: text/javascript');
        echo 'console.error("Birkly Analytics: Script file not found");';
        exit;
    }
    
    // Replace the config reading part with inline config
    $inlineScript = '(function() {' . "\n";
    $inlineScript .= "    'use strict';\n";
    $inlineScript .= "    \n";
    $inlineScript .= "    // Inline configuration\n";
    $inlineScript .= "    var trackingId = " . json_encode($trackingId) . ";\n";
    $inlineScript .= "    var cmsUrl = (" . json_encode($cmsUrl) . " || '').replace(/\\/\\$/, '');\n";
    $inlineScript .= "    \n";
    $inlineScript .= "    if (!trackingId || !cmsUrl) {\n";
    $inlineScript .= "        console.warn('Birkly Analytics: trackingId and cmsUrl are required');\n";
    $inlineScript .= "        return;\n";
    $inlineScript .= "    }\n";
    $inlineScript .= "    \n";
    
    // Extract the rest of the script (everything after the config check)
    $scriptParts = explode("const config = window.HaruAnalytics || {};", $scriptContent);
    if (count($scriptParts) > 1) {
        $restOfScript = $scriptParts[1];
        // Remove the config reading lines
        $restOfScript = preg_replace('/const trackingId = config\.trackingId \|\| \'\';\s*/', '', $restOfScript);
        $restOfScript = preg_replace('/const cmsUrl = \(config\.cmsUrl \|\| \'\'\)\.replace\(\/\\\\\/\$\/, \'\'\);\s*/', '', $restOfScript);
        $restOfScript = preg_replace('/if \(!trackingId \|\| !cmsUrl\) \{[\s\S]*?return;[\s\S]*?\}\s*/', '', $restOfScript);
        $inlineScript .= $restOfScript;
    } else {
        // Fallback: use the whole script and replace config
        $inlineScript .= str_replace(
            ["const config = window.HaruAnalytics || {};", "const trackingId = config.trackingId || '';", "const cmsUrl = (config.cmsUrl || '').replace(/\\/$/, '');"],
            '',
            $scriptContent
        );
    }
    
    header('Content-Type: text/javascript');
    header('Cache-Control: public, max-age=3600');
    echo $inlineScript;
    exit;
}

// Create new tracking ID
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create') {
    require_once 'auth.php';
    require_once 'validate.php';
    require_once '../core/csrf.php';
    
    requireAuth('manage_settings');
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    // Validate CSRF
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $domain = $input['domain'] ?? '';
    $name = $input['name'] ?? $domain;
    
    if (empty($domain)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Domain required']);
        exit;
    }
    
    $analyticsDir = __DIR__ . '/../data/analytics';
    if (!is_dir($analyticsDir)) {
        mkdir($analyticsDir, 0755, true);
    }
    
    $trackingConfigFile = $analyticsDir . '/tracking_ids.json';
    $trackingIds = [];
    if (file_exists($trackingConfigFile)) {
        $trackingIds = json_decode(file_get_contents($trackingConfigFile), true) ?: [];
    }
    
    // Generate unique tracking ID
    $trackingId = 'haru_' . bin2hex(random_bytes(8));
    while (isset($trackingIds[$trackingId])) {
        $trackingId = 'haru_' . bin2hex(random_bytes(8));
    }
    
    $trackingIds[$trackingId] = [
        'id' => $trackingId,
        'name' => $name,
        'domain' => $domain,
        'enabled' => true,
        'created_at' => date('c'),
        'updated_at' => date('c')
    ];
    
    file_put_contents($trackingConfigFile, json_encode($trackingIds, JSON_PRETTY_PRINT), LOCK_EX);
    
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'tracking_id' => $trackingId,
            'config' => $trackingIds[$trackingId]
        ]
    ]);
    exit;
}

// Update tracking ID
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update') {
    require_once 'auth.php';
    require_once 'validate.php';
    require_once '../core/csrf.php';
    
    requireAuth('manage_settings');
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $trackingId = $input['tracking_id'] ?? '';
    $updates = $input['updates'] ?? [];
    
    if (empty($trackingId)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Tracking ID required']);
        exit;
    }
    
    $analyticsDir = __DIR__ . '/../data/analytics';
    $trackingConfigFile = $analyticsDir . '/tracking_ids.json';
    
    $trackingIds = [];
    if (file_exists($trackingConfigFile)) {
        $trackingIds = json_decode(file_get_contents($trackingConfigFile), true) ?: [];
    }
    
    if (!isset($trackingIds[$trackingId])) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Tracking ID not found']);
        exit;
    }
    
    // Update allowed fields
    $allowedFields = ['name', 'domain', 'enabled'];
    foreach ($allowedFields as $field) {
        if (isset($updates[$field])) {
            $trackingIds[$trackingId][$field] = $updates[$field];
        }
    }
    $trackingIds[$trackingId]['updated_at'] = date('c');
    
    file_put_contents($trackingConfigFile, json_encode($trackingIds, JSON_PRETTY_PRINT), LOCK_EX);
    
    echo json_encode([
        'status' => 'ok',
        'data' => $trackingIds[$trackingId]
    ]);
    exit;
}

// Delete tracking ID
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    require_once 'auth.php';
    require_once 'validate.php';
    require_once '../core/csrf.php';
    
    requireAuth('manage_settings');
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $trackingId = $input['tracking_id'] ?? '';
    
    if (empty($trackingId)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Tracking ID required']);
        exit;
    }
    
    $analyticsDir = __DIR__ . '/../data/analytics';
    $trackingConfigFile = $analyticsDir . '/tracking_ids.json';
    
    $trackingIds = [];
    if (file_exists($trackingConfigFile)) {
        $trackingIds = json_decode(file_get_contents($trackingConfigFile), true) ?: [];
    }
    
    if (isset($trackingIds[$trackingId])) {
        unset($trackingIds[$trackingId]);
        file_put_contents($trackingConfigFile, json_encode($trackingIds, JSON_PRETTY_PRINT), LOCK_EX);
    }
    
    echo json_encode([
        'status' => 'ok',
        'message' => 'Tracking ID deleted'
    ]);
    exit;
}

// Helper function to get analytics data
function getAnalyticsData($trackingId, $startDate, $endDate) {
    return birkly_analytics_get_report((string) $trackingId, (string) $startDate, (string) $endDate);
}

// Default response - only execute if called directly, not when included
if (!isset($GLOBALS['__HARU_INTERNAL_CALL']) && basename($_SERVER['PHP_SELF'] ?? '') === basename(__FILE__)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    exit;
}

