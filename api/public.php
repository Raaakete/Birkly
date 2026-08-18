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

require_once __DIR__ . '/../core/storage/StorageFactory.php';
require_once __DIR__ . '/../core/cache.php';
require_once __DIR__ . '/../core/public_read.php';
require_once __DIR__ . '/rate_limit.php';
require_once __DIR__ . '/log.php';
require_once __DIR__ . '/../core/plugin_availability.php';

function birkly_public_deny_read(string $collectionSlug, string $reason) {
    log_security_event('public_read_denied', [
        'collection' => $collectionSlug,
        'reason' => $reason,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'origin' => $_SERVER['HTTP_ORIGIN'] ?? '',
        'referer' => $_SERVER['HTTP_REFERER'] ?? '',
        'action' => $_GET['action'] ?? $_POST['action'] ?? '',
    ]);
    http_response_code(403);
    respond([
        'status' => 'error',
        'message' => 'Authentication required',
        'code' => 'READ_AUTH_REQUIRED',
    ]);
}

function birkly_public_enforce_read_auth(array $collectionMeta, string $collectionSlug) {
    require_once __DIR__ . '/domain_auth.php';
    setDynamicCORS();
    $auth = birkly_public_read_auth_check($collectionMeta, $collectionSlug);
    if (empty($auth['allowed'])) {
        birkly_public_deny_read($collectionSlug, $auth['reason']);
    }
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
    birkly_public_rate_limit('template_function_call', 30, 60);
    // Public template function calls can be memory-heavy on the PHP built-in server
    // because the process is long-lived; give it more headroom.
    @ini_set('memory_limit', '512M');
    
    try {
        // Use SITE session so user_current / user_logged_in see the website user (e.g. demo Alice),
        // not the admin session. Required for demo-website and any frontend using template functions.
        require_once __DIR__ . '/session.php';
        if (function_exists('birkly_site_session_start')) {
            birkly_site_session_start();
        } elseif (function_exists('haru_secure_session_start')) {
            haru_secure_session_start();
        } else {
            if (session_status() === PHP_SESSION_NONE) @session_start();
        }

        require_once __DIR__ . '/../core/public_bootstrap.php';

        // Support both JSON (application/json) and form-encoded (application/x-www-form-urlencoded) requests
        $name = '';
        $args = [];
        
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'application/json') !== false) {
            // JSON request
            $raw = file_get_contents('php://input');
            $json = json_decode($raw, true);
            if (is_array($json)) {
                $name = $json['name'] ?? '';
                $args = $json['args'] ?? [];
            }
        } else {
            // Form-encoded request (from client)
            $name = $input['name'] ?? '';
            $argsRaw = $input['args'] ?? '[]';
            if (is_string($argsRaw)) {
                $decoded = json_decode($argsRaw, true);
                if (is_array($decoded)) {
                    $args = $decoded;
                } else {
                    $args = [];
                }
            } else if (is_array($argsRaw)) {
                $args = $argsRaw;
            }
        }

        if (!is_string($name) || $name === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            respond(['status' => 'error', 'message' => 'Invalid function name']);
        }

        // M2-T4: load only plugins that may register this template function
        birkly_public_load_plugins_for_template_function($name);

        if (!function_exists('get_template_function')) {
            respond(['status' => 'ok', 'data' => ['result' => null]]); // Return null instead of error
        }

        $fn = get_template_function($name);
        if (!$fn) {
            respond(['status' => 'ok', 'data' => ['result' => null]]); // Return null instead of error
        }

        $result = call_user_func_array($fn, $args);
        respond(['status' => 'ok', 'data' => ['result' => $result]]);
    } catch (Throwable $e) {
        // Log error but return null result to prevent breaking the client
        error_log('Template function call error: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        respond(['status' => 'ok', 'data' => ['result' => null]]); // Graceful degradation
    }
}

if ($action === 'list_collections') {
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
    require_once __DIR__ . '/../core/collection_groups.php';
    respond(['status' => 'ok', 'data' => ['collection_groups' => birkly_load_collection_groups()]]);
}

if ($action === 'list_entries') {
    $collection = $input['collection'] ?? '';
    $id = $input['id'] ?? '';
    $language = $input['language'] ?? $_GET['language'] ?? $_GET['lang'] ?? $input['lang'] ?? 'en'; // Default to 'en'
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
    // SECURITY FIX: Use generic error message to prevent collection enumeration
    if (!$dir) respond(['status'=>'error','message'=>'Invalid request']);
    
    // Get collection metadata
    $collectionMeta = $storage->read("collections/$dir/collection.json");
    $collectionSlug = $collectionMeta['slug'] ?? '';
    $collectionMeta = birkly_public_maybe_migrate_collection_meta($storage, $dir, is_array($collectionMeta) ? $collectionMeta : []);
    birkly_public_enforce_read_auth($collectionMeta, $collectionSlug);
    $multilanguageEnabled = !empty($collectionMeta['multilanguage']['enabled']);
    $multilanguageLanguages = $collectionMeta['multilanguage']['languages'] ?? [];
    
    // Validate language
    if ($multilanguageEnabled && !empty($multilanguageLanguages)) {
        if (!in_array($language, $multilanguageLanguages)) {
            $language = $multilanguageLanguages[0] ?? 'en'; // Fallback to first available language
        }
    } else {
        $language = null; // No multi-language, use original structure
    }
    
    // Try to get cached entries
    $cache = new Cache();
    $cachedEntries = null;
    if ($collectionSlug) {
        $cachedEntries = $cache->getEntries($collectionSlug);
    }
    
    if ($cachedEntries === null) {
        // Cache miss - load entries from files
    $entries = $storage->list("collections/$dir/entries");
    $entryData = [];
    foreach ($entries as $entry) {
        $data = $storage->read("collections/$dir/entries/$entry");
            if ($data) {
            $entryData[] = $data;
        }
    }
        
        // Cache all entries (we'll filter published below)
        if ($collectionSlug) {
            $cache->setEntries($collectionSlug, $entryData);
        }
        $cachedEntries = $entryData;
    }
    
    // Filter only published entries for public API
    $publishedEntries = array_filter($cachedEntries, function($entry) {
        return ($entry['status'] ?? 'draft') === 'published';
    });
    
        // Apply permission filtering if user management plugin is installed and active
        if (function_exists('get_plugin_state') && get_plugin_state('user_management')
            && birkly_require_plugin_file('user_management', 'includes/EntryPermissions.php')) {
            $entryPermissions = new EntryPermissions();
            $currentUserId = $entryPermissions->getCurrentUserId();
            
            // Filter entries by view permission
            $publishedEntries = $entryPermissions->filterEntriesByViewPermission($currentUserId, $publishedEntries);
        }
        
        // Calculate likes_count and comments_count for social_posts collection
        if ($collectionSlug === 'social_posts') {
            require_once __DIR__ . '/../core/sub_entries.php';
            foreach ($publishedEntries as &$entry) {
                if (empty($entry['id'])) continue;
                // Count likes
                $likes = getSubEntries($collectionSlug, $entry['id'], 'likes');
                $entry['likes_count'] = count($likes);
                // Count comments
                $comments = getSubEntries($collectionSlug, $entry['id'], 'comments');
                $entry['comments_count'] = count($comments);
            }
            unset($entry); // Break reference
        }
        
        // Transform entries for language-specific content
        if ($language && $multilanguageEnabled) {
        $transformedEntries = array_map(function($entry) use ($language, $multilanguageLanguages) {
            // Check if entry has multi-language structure
            $hasMultilang = false;
            foreach ($multilanguageLanguages as $lang) {
                if (isset($entry[$lang]) && is_array($entry[$lang])) {
                    $hasMultilang = true;
                    break;
                }
            }
            
            if ($hasMultilang && isset($entry[$language])) {
                // Merge language-specific content with entry metadata
                $transformed = array_merge($entry, $entry[$language]);
                // Keep metadata fields
                $transformed['id'] = $entry['id'] ?? '';
                $transformed['slug'] = $entry['slug'] ?? '';
                $transformed['status'] = $entry['status'] ?? 'draft';
                $transformed['created_at'] = $entry['created_at'] ?? '';
                $transformed['updated_at'] = $entry['updated_at'] ?? '';
                $transformed['published_at'] = $entry['published_at'] ?? '';
                return $transformed;
            }
            
            // No multi-language structure, return as-is
            return $entry;
        }, $publishedEntries);
        
        // Calculate likes_count and comments_count for social_posts collection (after transformation)
        if ($collectionSlug === 'social_posts') {
            require_once __DIR__ . '/../core/sub_entries.php';
            foreach ($transformedEntries as &$entry) {
                if (empty($entry['id'])) continue;
                // Count likes
                $likes = getSubEntries($collectionSlug, $entry['id'], 'likes');
                $entry['likes_count'] = count($likes);
                // Count comments
                $comments = getSubEntries($collectionSlug, $entry['id'], 'comments');
                $entry['comments_count'] = count($comments);
            }
            unset($entry); // Break reference
        }
        
        // Apply permission filtering if user management plugin is installed and active
        if (function_exists('get_plugin_state') && get_plugin_state('user_management')
            && birkly_require_plugin_file('user_management', 'includes/EntryPermissions.php')) {
            $entryPermissions = new EntryPermissions();
            $currentUserId = $entryPermissions->getCurrentUserId();

            // Filter entries by view permission
            $transformedEntries = $entryPermissions->filterEntriesByViewPermission($currentUserId, $transformedEntries);
        }
        
        require_once __DIR__ . '/../core/entry_slug.php';
        respond(['status'=>'ok','data'=>['entries'=>array_map('birkly_public_entry_with_effective_slug', array_values($transformedEntries))]]);
    } else {
        // Apply permission filtering if user management plugin is installed and active
        if (function_exists('get_plugin_state') && get_plugin_state('user_management')
            && birkly_require_plugin_file('user_management', 'includes/EntryPermissions.php')) {
            $entryPermissions = new EntryPermissions();
            $currentUserId = $entryPermissions->getCurrentUserId();
            
            // Filter entries by view permission
            $publishedEntries = $entryPermissions->filterEntriesByViewPermission($currentUserId, $publishedEntries);
        }
        
        // No multi-language, return entries as-is
        require_once __DIR__ . '/../core/entry_slug.php';
        respond(['status'=>'ok','data'=>['entries'=>array_map('birkly_public_entry_with_effective_slug', array_values($publishedEntries))]]);
    }
}

if ($action === 'get_entry') {
    $collection = $input['collection'] ?? '';
    $id = $input['id'] ?? '';
    $entryId = $input['entry'] ?? '';
    // Entry id alias: ?id= on detail pages (when not collection id)
    if ($entryId === '' && $id !== '' && $collection !== '') {
        $entryId = $id;
        $id = '';
    }
    $slug = $input['slug'] ?? $_GET['slug'] ?? '';
    $language = $input['language'] ?? $_GET['language'] ?? $_GET['lang'] ?? $input['lang'] ?? 'en'; // Default to 'en'
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
    // SECURITY FIX: Use generic error message to prevent collection enumeration
    if (!$dir) respond(['status'=>'error','message'=>'Invalid request']);
    
    // Get collection metadata
    $collectionMeta = $storage->read("collections/$dir/collection.json");
    $collectionSlug = $collectionMeta['slug'] ?? '';
    $collectionMeta = birkly_public_maybe_migrate_collection_meta($storage, $dir, is_array($collectionMeta) ? $collectionMeta : []);
    birkly_public_enforce_read_auth($collectionMeta, $collectionSlug);
    $multilanguageEnabled = !empty($collectionMeta['multilanguage']['enabled']);
    $multilanguageLanguages = $collectionMeta['multilanguage']['languages'] ?? [];
    
    // Validate language
    if ($multilanguageEnabled && !empty($multilanguageLanguages)) {
        if (!in_array($language, $multilanguageLanguages)) {
            $language = $multilanguageLanguages[0] ?? 'en'; // Fallback to first available language
        }
    } else {
        $language = null; // No multi-language, use original structure
    }
    
    // Find entry by slug or ID
    require_once __DIR__ . '/../core/entry_slug.php';
    $data = null;
    if ($slug) {
        $entries = $storage->list("collections/$dir/entries");
        foreach ($entries as $entryFile) {
            $entryData = $storage->read("collections/$dir/entries/$entryFile");
            if ($entryData && birkly_entry_matches_slug($entryData, $slug)) {
                $data = $entryData;
                break;
            }
        }
    } elseif ($entryId) {
    $data = $storage->read("collections/$dir/entries/$entryId.json");
    }
    
    if (!$data || ($data['status'] ?? 'draft') !== 'published') {
        respond(['status'=>'error','message'=>'Entry not found or not published']);
    }
    
    $data = birkly_public_entry_with_effective_slug($data);
    
    // Calculate likes_count and comments_count for social_posts collection
    $collectionSlug = $collectionMeta['slug'] ?? '';
    if ($collectionSlug === 'social_posts' && !empty($data['id'])) {
        require_once __DIR__ . '/../core/sub_entries.php';
        // Count likes
        $likes = getSubEntries($collectionSlug, $data['id'], 'likes');
        $data['likes_count'] = count($likes);
        // Count comments
        $comments = getSubEntries($collectionSlug, $data['id'], 'comments');
        $data['comments_count'] = count($comments);
    }
    
    // Transform entry for language-specific content
    if ($language && $multilanguageEnabled) {
        // Check if entry has multi-language structure
        $hasMultilang = false;
        foreach ($multilanguageLanguages as $lang) {
            if (isset($data[$lang]) && is_array($data[$lang])) {
                $hasMultilang = true;
                break;
            }
        }
        
        if ($hasMultilang && isset($data[$language])) {
            // Merge language-specific content with entry metadata
            $transformed = array_merge($data, $data[$language]);
            // Keep metadata fields
            $transformed['id'] = $data['id'] ?? '';
            $transformed['slug'] = $data['slug'] ?? '';
            $transformed['status'] = $data['status'] ?? 'draft';
            $transformed['created_at'] = $data['created_at'] ?? '';
            $transformed['updated_at'] = $data['updated_at'] ?? '';
            $transformed['published_at'] = $data['published_at'] ?? '';
            $data = $transformed;
        }
    }
    
    respond(['status'=>'ok','data'=>['entry'=>$data]]);
}

if ($action === 'get_site_settings') {
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
    
    $entryAuthorId = $entry['author_user_id'] ?? $entry['author_id'] ?? '';
    
    if ($entryAuthorId !== $currentUserId) {
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