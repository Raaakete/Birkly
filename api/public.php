<?php
// api/public.php - Public read-only API for Birkly
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
header('Content-Type: application/json');

// SECURITY FIX: Set security headers for public API
require_once __DIR__ . '/validate.php';
set_security_headers('api');

require_once __DIR__ . '/domain_auth.php';
setDynamicCORS();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../core/storage/StorageFactory.php';
require_once __DIR__ . '/../core/cache.php';
require_once __DIR__ . '/../core/public_read.php';
require_once __DIR__ . '/rate_limit.php';
require_once __DIR__ . '/log.php';
require_once __DIR__ . '/../core/plugin_availability.php';

function birkly_public_api_deny(string $reason, string $actionName, ?string $collectionSlug = null, string $logEvent = 'public_api_denied') {
    log_security_event($logEvent, [
        'collection' => $collectionSlug ?? '',
        'reason' => $reason,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'origin' => $_SERVER['HTTP_ORIGIN'] ?? '',
        'referer' => $_SERVER['HTTP_REFERER'] ?? '',
        'action' => $actionName,
        'hint' => $reason === 'public_read_disabled'
            ? 'Enable Allow public read access in collection settings'
            : '',
    ]);
    http_response_code(403);
    $code = $reason === 'public_read_disabled' ? 'PUBLIC_READ_DISABLED' : 'AUTH_REQUIRED';
    respond([
        'status' => 'error',
        'message' => birkly_public_api_deny_message($reason, $collectionSlug),
        'code' => $code,
    ]);
}

function birkly_public_api_enforce_auth(string $mode, ?array $collectionMeta, ?string $collectionSlug, string $actionName) {
    require_once __DIR__ . '/domain_auth.php';
    setDynamicCORS();
    $auth = birkly_public_api_auth_check($collectionMeta, $collectionSlug, $mode);
    if (empty($auth['allowed'])) {
        $logEvent = $mode === 'write' ? 'public_submit_denied' : 'public_read_denied';
        birkly_public_api_deny($auth['reason'], $actionName, $collectionSlug, $logEvent);
    }
}

function birkly_public_enforce_read_auth(array $collectionMeta, string $collectionSlug) {
    birkly_public_api_enforce_auth('read', $collectionMeta, $collectionSlug, $_GET['action'] ?? $_POST['action'] ?? 'read');
}

function birkly_public_rate_limit($bucket, $limit = 120, $window = 60) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $safeIp = preg_replace('/[^a-zA-Z0-9._-]/', '_', $ip);
    $rateLimiter = new RateLimiter('public_' . $bucket . '_' . $safeIp, $limit, $window);
    if (!$rateLimiter->check()) {
        http_response_code(429);
        echo json_encode([
            'status' => 'error',
            'message' => 'Too many requests',
            'retry_after' => $rateLimiter->getRetryAfter()
        ]);
        exit;
    }
}

// M2-T4: Rate-limit all public API traffic
birkly_public_rate_limit('api', 120, 60);

$storage = get_storage(__DIR__ . '/../content');
$input = array_merge($_GET, $_POST);
$action = $input['action'] ?? '';

function respond($data) {
    echo json_encode($data);
    exit;
}

/**
 * Template Functions (plugin-provided, template-first)
 *
 * These allow a website to include ONLY:
 *   <script src="/birkly-client.js" data-cms-url="..."></script>
 * and still use plugin template functions like:
 *   {user_logged_in}
 *   {user_current.email}
 *   {if user_can 'create' 'blog'} ... {endif}
 *
 * Implementation:
 * - Plugins register template functions in PHP via register_template_function() (already exists).
 * - This API exposes:
 *   - template_functions_list: discover available functions (names + metadata)
 *   - template_function_call: execute a template function (optionally with args)
 *
 * NOTE: This is required because client-side rendering cannot execute PHP callbacks.
 */
if ($action === 'template_functions_list') {
    birkly_public_api_enforce_auth('read', null, null, 'template_functions_list');
    birkly_public_rate_limit('template_functions_list', 20, 60);
    // Public template function discovery can be memory-heavy on the PHP built-in server
    // because the process is long-lived; give it more headroom.
    @ini_set('memory_limit', '512M');
    
    try {
        require_once __DIR__ . '/session.php';
        // Public endpoints use the shared secure session initializer.
        // (Legacy function name retained in api/session.php)
        if (function_exists('haru_secure_session_start')) {
            haru_secure_session_start();
        } else {
            // Fallback
            if (session_status() === PHP_SESSION_NONE) @session_start();
        }

        require_once __DIR__ . '/../core/public_bootstrap.php';
        birkly_public_load_active_plugins();

        // Optional debug info (only when explicitly requested)
        $debugInfo = null;
        if (!empty($input['__dbg'])) {
            try {
                $plugins = function_exists('get_all_plugins') ? get_all_plugins() : [];
                $active = [];
                foreach ($plugins as $pid => $p) {
                    if (!empty($p['active'])) $active[] = $pid;
                }
                global $haru_template_functions;
                $tfCount = (isset($haru_template_functions) && is_array($haru_template_functions)) ? count($haru_template_functions) : -1;
                $debugInfo = [
                    'active_plugins' => $active,
                    'total_plugins' => count($plugins),
                    'registry_count' => $tfCount,
                ];
            } catch (Throwable $e) {
                $debugInfo = ['error' => $e->getMessage()];
            }
        }

        if (!function_exists('get_all_template_functions')) {
            respond(['status' => 'ok', 'data' => ['functions' => []]]); // Return empty list instead of error
        }

        $all = get_all_template_functions();
        $out = [];
        foreach ($all as $name => $meta) {
            $out[] = [
                'name' => $name,
                'description' => $meta['description'] ?? '',
                'arguments' => $meta['arguments'] ?? [],
                'plugin_id' => $meta['plugin_id'] ?? null,
            ];
        }

        $resp = ['status' => 'ok', 'data' => ['functions' => $out]];
        if (is_array($debugInfo)) $resp['debug'] = $debugInfo;
        respond($resp);
    } catch (Throwable $e) {
        // Log error but return empty list to prevent breaking the client
        error_log('Template functions list error: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        $resp = ['status' => 'ok', 'data' => ['functions' => []]];
        if (!empty($input['__dbg'])) {
            $resp['debug'] = [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ];
        }
        respond($resp); // Graceful degradation
    }
}

if ($action === 'template_function_call') {
    birkly_public_api_enforce_auth('read', null, null, 'template_function_call');
    birkly_public_rate_limit('template_function_call', 30, 60);
    @ini_set('memory_limit', '512M');

    try {
        require_once __DIR__ . '/session.php';
        if (function_exists('birkly_site_session_start')) {
            birkly_site_session_start();
        } elseif (function_exists('haru_secure_session_start')) {
            haru_secure_session_start();
        } elseif (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        require_once __DIR__ . '/../core/public_bootstrap.php';
        require_once __DIR__ . '/../core/page_bundle_template_functions.php';

        $name = '';
        $args = [];

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            $json = json_decode($raw, true);
            if (is_array($json)) {
                $name = $json['name'] ?? '';
                $args = $json['args'] ?? [];
            }
        } else {
            $name = $input['name'] ?? '';
            $argsRaw = $input['args'] ?? '[]';
            if (is_string($argsRaw)) {
                $decoded = json_decode($argsRaw, true);
                $args = is_array($decoded) ? $decoded : [];
            } elseif (is_array($argsRaw)) {
                $args = $argsRaw;
            }
        }

        if (!is_string($name) || $name === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            respond(['status' => 'error', 'message' => 'Invalid function name']);
        }

        if (!is_array($args)) {
            $args = [];
        }

        $result = birkly_page_bundle_call_template_function($name, $args);
        respond(['status' => 'ok', 'data' => ['result' => $result]]);
    } catch (Throwable $e) {
        error_log('Template function call error: ' . $e->getMessage());
        respond(['status' => 'ok', 'data' => ['result' => null]]);
    }
}

if ($action === 'list_collections') {
    birkly_public_api_enforce_auth('read', null, null, 'list_collections');
    require_once __DIR__ . '/../core/collection_meta.php';
    $collections = $storage->list('collections');
    $result = [];
    foreach ($collections as $dir) {
        $meta = $storage->read("collections/$dir/collection.json");
        if ($meta) {
            $result[] = birkly_normalize_collection_meta($meta, $dir);
        }
    }
    respond(['status'=>'ok','data'=>['collections'=>$result]]);
}

if ($action === 'list_collection_groups') {
    birkly_public_api_enforce_auth('read', null, null, 'list_collection_groups');
    require_once __DIR__ . '/../core/collection_groups.php';
    respond(['status' => 'ok', 'data' => ['collection_groups' => birkly_load_collection_groups()]]);
}

if ($action === 'list_entries') {
    $collection = $input['collection'] ?? '';
    $id = $input['id'] ?? '';
    $language = $input['language'] ?? $_GET['language'] ?? $_GET['lang'] ?? $input['lang'] ?? 'en';
    $dir = '';
    $collections = $storage->list('collections');
    if ($id) {
        foreach ($collections as $d) {
            $m = $storage->read("collections/$d/collection.json");
            if ($m && $m['id'] === $id) { $dir = $d; break; }
        }
    } elseif ($collection) {
        foreach ($collections as $d) {
            $m = $storage->read("collections/$d/collection.json");
            if ($m && $m['slug'] === $collection) { $dir = $d; break; }
        }
    }
    if (!$dir) respond(['status'=>'error','message'=>'Invalid request']);

    $collectionMeta = $storage->read("collections/$dir/collection.json");
    $collectionSlug = $collectionMeta['slug'] ?? '';
    $collectionMeta = birkly_public_maybe_migrate_collection_meta($storage, $dir, is_array($collectionMeta) ? $collectionMeta : []);
    birkly_public_enforce_read_auth($collectionMeta, $collectionSlug);

    require_once __DIR__ . '/session.php';
    birkly_site_session_start();

    $scope = $input['scope'] ?? $_GET['scope'] ?? null;
    $result = birkly_public_list_entries($storage, $collectionSlug, $language, [
        'scope' => is_string($scope) ? $scope : null,
    ]);
    if (($result['status'] ?? '') !== 'ok') {
        respond(['status'=>'error','message'=>'Invalid request']);
    }
    respond(['status'=>'ok','data'=>['entries'=>$result['data']['entries'] ?? []]]);
}

if ($action === 'get_entry') {
    $collection = $input['collection'] ?? '';
    $id = $input['id'] ?? '';
    $entryId = $input['entry'] ?? '';
    if ($entryId === '' && $id !== '' && $collection !== '') {
        $entryId = $id;
        $id = '';
    }
    $slug = $input['slug'] ?? $_GET['slug'] ?? '';
    $language = $input['language'] ?? $_GET['language'] ?? $_GET['lang'] ?? $input['lang'] ?? 'en';
    $dir = '';
    $collections = $storage->list('collections');
    if ($id) {
        foreach ($collections as $d) {
            $m = $storage->read("collections/$d/collection.json");
            if ($m && $m['id'] === $id) { $dir = $d; break; }
        }
    } elseif ($collection) {
        foreach ($collections as $d) {
            $m = $storage->read("collections/$d/collection.json");
            if ($m && $m['slug'] === $collection) { $dir = $d; break; }
        }
    }
    if (!$dir) respond(['status'=>'error','message'=>'Invalid request']);

    $collectionMeta = $storage->read("collections/$dir/collection.json");
    $collectionSlug = $collectionMeta['slug'] ?? '';
    $collectionMeta = birkly_public_maybe_migrate_collection_meta($storage, $dir, is_array($collectionMeta) ? $collectionMeta : []);
    birkly_public_enforce_read_auth($collectionMeta, $collectionSlug);

    require_once __DIR__ . '/session.php';
    birkly_site_session_start();

    $result = birkly_public_get_entry($storage, $collectionSlug, $slug, $entryId, $language);
    if (($result['status'] ?? '') !== 'ok') {
        respond(['status'=>'error','message'=>'Entry not found or not published']);
    }
    respond(['status'=>'ok','data'=>['entry'=>$result['data']['entry'] ?? null]]);
}

if ($action === 'get_site_settings') {
    birkly_public_api_enforce_auth('read', null, null, 'get_site_settings');
    $settingsPath = __DIR__ . '/../settings/site.json';
    $siteSettings = [
        'title' => 'Birkly',
        'name' => 'Birkly',
        'description' => '',
        'url' => ''
    ];
    
    if (file_exists($settingsPath)) {
        $fileData = json_decode(file_get_contents($settingsPath), true);
        if ($fileData) {
            $siteSettings = array_merge($siteSettings, $fileData);
        }
    }
    
    respond(['status'=>'ok','data'=>['settings'=>$siteSettings]]);
}

if ($action === 'get_collection_info') {
    $collection = $input['collection'] ?? '';
    if (empty($collection)) {
        respond(['status'=>'error','message'=>'Collection parameter required']);
    }
    
    $dir = '';
    $collections = $storage->list('collections');
    foreach ($collections as $d) {
        $m = $storage->read("collections/$d/collection.json");
        if ($m && ($m['slug'] === $collection || $m['id'] === $collection)) {
            $dir = $d;
            break;
        }
    }
    
    if (!$dir) {
        respond(['status'=>'error','message'=>'Collection not found']);
    }
    
    $collectionData = $storage->read("collections/$dir/collection.json");
    $collectionSlug = $collectionData['slug'] ?? $collection;
    $collectionData = birkly_public_maybe_migrate_collection_meta($storage, $dir, is_array($collectionData) ? $collectionData : []);
    birkly_public_enforce_read_auth($collectionData, $collectionSlug);
    $schema = $storage->read("collections/$dir/schema.json");
    
    // Only return if Public API is enabled
    if (($collectionData['entry_method'] ?? 'admin_only') !== 'public_api') {
        respond(['status'=>'error','message'=>'Public API not enabled for this collection']);
    }
    
    // SECURITY FIX: Remove API key from public response
    // API keys should only be shown in admin panel to authenticated users
    // Remove api_key from collection data before returning
    $publicCollectionData = $collectionData;
    unset($publicCollectionData['api_key']);
    
    respond([
        'status'=>'ok',
        'data'=>[
            'schema' => $schema,
            // API key removed - should only be accessible via admin panel
            'collection' => $publicCollectionData
        ]
    ]);
}

if ($action === 'delete_entry') {
    require_once __DIR__ . '/session.php';
    birkly_site_session_start();
    require_once __DIR__ . '/../core/public_bootstrap.php';
    require_once __DIR__ . '/../core/entry_ownership.php';
    birkly_public_load_active_plugins();
    
    $collection = $input['collection'] ?? '';
    $id = $input['id'] ?? '';
    
    if (empty($collection) || empty($id)) {
        respond(['status'=>'error','message'=>'Collection and ID required']);
    }
    
    // Find collection directory
    $dir = '';
    $collections = $storage->list('collections');
    foreach ($collections as $d) {
        $m = $storage->read("collections/$d/collection.json");
        if ($m && ($m['slug'] === $collection || $m['id'] === $collection)) {
            $dir = $d;
            break;
        }
    }
    
    if (!$dir) {
        respond(['status'=>'error','message'=>'Collection not found']);
    }
    
    // Get entry to check ownership
    $entryPath = "collections/$dir/entries/$id.json";
    $entry = $storage->read($entryPath);
    
    if (!$entry) {
        // SECURITY FIX: Use generic error message
        respond(['status'=>'error','message'=>'Invalid request']);
    }
    
    // Check ownership - user can only delete their own entries
    require_once __DIR__ . '/session.php';
    birkly_site_session_start();
    require_once __DIR__ . '/../core/public_bootstrap.php';
    birkly_public_load_active_plugins();
    
  // Use EntryPermissions to get current user ID
    $currentUserId = '';
    if (birkly_require_plugin_file('user_management', 'includes/EntryPermissions.php')) {
        $entryPermissions = new EntryPermissions();
        $currentUserId = $entryPermissions->getCurrentUserId();
    }
    
    if (empty($currentUserId)) {
        http_response_code(401);
        respond(['status'=>'error','message'=>'Authentication required']);
    }

    $schema = $storage->read("collections/$dir/schema.json");
    $entryAuthorId = birkly_get_entry_owner_id(is_array($entry) ? $entry : [], is_array($schema) ? $schema : null);
    
    if ($entryAuthorId === '' || $entryAuthorId !== $currentUserId) {
        http_response_code(403);
        respond(['status'=>'error','message'=>'You can only delete your own entries']);
    }
    
    // Delete the entry
    $result = $storage->delete($entryPath);
    
    if ($result) {
        respond(['status'=>'ok','message'=>'Entry deleted']);
    } else {
        http_response_code(500);
        respond(['status'=>'error','message'=>'Failed to delete entry']);
    }
}

respond(['status'=>'error','message'=>'Invalid action']); 