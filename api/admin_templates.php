<?php
/**
 * Admin Templates API
 * Serves templates and data for the admin panel
 */

// CRITICAL: If being included (not called directly), skip all API execution
// Check this FIRST before any other code that might output
$__is_internal_include = isset($GLOBALS['__HARU_INTERNAL_CALL']) && $GLOBALS['__HARU_INTERNAL_CALL'] === true;

require_once 'storage.php';
require_once __DIR__ . '/../core/birkly_paths.php';

// Load fieldtype base and simple autoloader so fieldtypes can be instantiated for list rendering
$baseFieldPath = __DIR__ . '/../fieldtypes/BaseField.php';
if (file_exists($baseFieldPath)) {
    require_once $baseFieldPath;
}

// Load admin_fieldtypes.php to get getFieldtypeClass() function for plugin fieldtype support
$adminFieldtypesPath = __DIR__ . '/admin_fieldtypes.php';
if (file_exists($adminFieldtypesPath)) {
    require_once $adminFieldtypesPath;
}

spl_autoload_register(function ($class) {
    if (strpos($class, 'Birkly\\FieldTypes\\') === 0) {
        $suffix = str_replace('Birkly\\FieldTypes\\', '', $class); // e.g., Media\Media
        $parts = explode('\\', $suffix);
        $leaf = end($parts);
        $dir = __DIR__ . '/../fieldtypes/' . $leaf;
        $file = $dir . '/' . $leaf . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
    
    // Also handle plugin fieldtypes (e.g., UserManagement\Fieldtypes\Password)
    if (strpos($class, '\\') !== false && strpos($class, 'Fieldtypes\\') !== false) {
        $parts = explode('\\', $class);
        $fieldtypeName = end($parts);
        $pluginName = $parts[0] ?? '';
        
        // Look in plugins directory
        $pluginsDir = __DIR__ . '/../plugins';
        if (is_dir($pluginsDir)) {
            $pluginDirs = glob($pluginsDir . '/*', GLOB_ONLYDIR);
            foreach ($pluginDirs as $pluginDir) {
                $fieldtypeFile = $pluginDir . '/fieldtypes/' . $fieldtypeName . '/' . $fieldtypeName . '.php';
                if (file_exists($fieldtypeFile)) {
                    require_once $fieldtypeFile;
                    break;
                }
            }
        }
    }
});

// Simple database connection
function getDatabase() {
    static $pdo = null;
    if ($pdo === null) {
        $dbPath = __DIR__ . '/../database/birkly.db';
        $pdo = new PDO("sqlite:$dbPath");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    return $pdo;
}

// Only set headers if this file is being called directly (not included)
// When included via admin/index.php, headers are already sent, so skip
if (!$__is_internal_include && !headers_sent()) {
    header('Content-Type: application/json');
    
    // SECURITY FIX: Replace wildcard CORS with origin whitelist
    require_once __DIR__ . '/../core/cors.php';
    set_cors_headers('admin');
}

// Only execute API code if called directly (not included) AND action is set
// When included via admin/index.php, we only want the functions, not the switch statement
// CRITICAL: If being included, skip ALL API execution and go straight to function definitions
if (!$__is_internal_include) {
    // Might be a direct API call - check conditions
    $action = $_GET['action'] ?? '';
    $isDirectCall = isset($_SERVER['SCRIPT_NAME']) && basename($_SERVER['SCRIPT_NAME']) === 'admin_templates.php';
    
    // Only execute switch if this is definitely a direct API call with an action
    if ($isDirectCall && !empty($action)) {
        switch ($action) {
        case 'get_page_data':
            $page = $_GET['page'] ?? 'dashboard';
            try {
                $data = getPageData($page);
                ob_clean();
                echo json_encode(['status' => 'ok', 'data' => $data]);
                if (ob_get_level() > 0) {
                    ob_end_flush();
                }
            } catch (Exception $e) {
                error_log("Error in get_page_data: " . $e->getMessage());
                error_log("Stack trace: " . $e->getTraceAsString());
                ob_clean();
                echo json_encode(['status' => 'error', 'message' => $e->getMessage(), 'data' => []]);
                if (ob_get_level() > 0) {
                    ob_end_flush();
                }
            }
            exit;
            break;
        
        case 'list_templates':
            $data = getTemplatesData();
            ob_clean();
            echo json_encode(['status' => 'ok', 'data' => $data]);
            if (ob_get_level() > 0) {
                ob_end_flush();
            }
            exit;
            break;
            
        case 'create_template':
            $data = createTemplate();
            ob_clean();
            echo json_encode($data);
            if (ob_get_level() > 0) {
                ob_end_flush();
            }
            exit;
            break;
            
        case 'save_template':
            $data = saveTemplate();
            ob_clean();
            echo json_encode($data);
            if (ob_get_level() > 0) {
                ob_end_flush();
            }
            exit;
            break;
            
        case 'delete_template':
            $data = deleteTemplate();
            ob_clean();
            echo json_encode($data);
            if (ob_get_level() > 0) {
                ob_end_flush();
            }
            exit;
            break;
            
        default:
            ob_clean();
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
            if (ob_get_level() > 0) {
                ob_end_flush();
            }
            exit;
            break;
        }
    }
}

function getPageData($page) {
    switch ($page) {
        case 'dashboard':
            return getDashboardData();
        case 'content':
            return getContentData();
        case 'media':
            return getMediaData();
        case 'settings':
            return getSettingsData();
        case 'redirects':
            return [];
        case 'account':
            return getAccountData();
        case 'collection-editor':
            return getCollectionEditorData();
        case 'entry-editor':
            return getEntryEditorData(true);
        case 'entry-detail':
            return getEntryDetailData();
        case 'entries':
            return getEntriesData();
        case 'templates':
            return getTemplatesData();
        case 'users':
            return getUsersData();
        case 'roles':
            return getRolesData();
        case 'automation':
            return getAutomationData();
        case 'onboarding':
            return getOnboardingData();
        case 'plugins':
            return getPluginsData();
        case 'webhooks':
            return getWebhooksData();
        case 'help':
            return getHelpData();
        case 'updates':
            return getUpdatesData();
        case 'activity':
            return getActivityData();
        case 'audit-log':
            return getAuditLogData();
        case 'feedback':
            return getFeedbackData();
        case 'ai-presence':
        case 'agent-guide':
            return getAiPresenceData();
        default:
            return ['error' => 'Page not found'];
    }
}

function getAutomationData() {
    try {
        require_once __DIR__ . '/../core/automation/bootstrap.php';
        if (!automation_bootstrap()) {
            return [
                'automations' => [],
                'blocks' => [],
                'plugin_inactive' => true
            ];
        }
        
        $automations = function_exists('get_automations') ? get_automations() : [];
        $blocks = function_exists('get_all_automation_blocks') ? get_all_automation_blocks() : [];
        
        return [
            'automations' => $automations,
            'blocks' => $blocks
        ];
    } catch (Exception $e) {
        error_log("Error in getAutomationData: " . $e->getMessage());
        return [
            'automations' => [],
            'blocks' => [],
            'error' => $e->getMessage()
        ];
    }
}

function getAiPresenceData() {
    require_once __DIR__ . '/../core/csrf.php';

    return [
        'page' => 'ai-presence',
        'csrf_token' => get_csrf_token(),
    ];
}

function getDashboardData() {
    // Include onboarding to get user status
    $GLOBALS['__HARU_INTERNAL_CALL'] = true;
    require_once __DIR__ . '/onboarding.php';
    
    // Get current user
    if (session_status() === PHP_SESSION_NONE) {
        require_once __DIR__ . '/session.php';
        haru_secure_session_start();
    }
    $userId = $_SESSION['user']['email'] ?? $_SESSION['user']['id'] ?? 'unknown';
    // Dashboard checklist does not need a live starter-catalog probe.
    $onboardingStatus = getOnboardingStatus($userId, false);
    
    // Get collections
    $collectionsPath = __DIR__ . '/../content/collections';
    $collections = [];
    $totalEntries = 0;
    $published = 0;
    $drafts = 0;
    
    if (is_dir($collectionsPath)) {
        $collectionDirs = glob($collectionsPath . '/*', GLOB_ONLYDIR);
        foreach ($collectionDirs as $dir) {
            $collectionName = basename($dir);
            $collectionFile = $dir . '/collection.json';
            
            if (file_exists($collectionFile)) {
                $collectionData = json_decode(file_get_contents($collectionFile), true);
                $entriesDir = $dir . '/entries';
                $entriesCount = is_dir($entriesDir) ? count(glob($entriesDir . '/*.json')) : 0;
                
                $collections[] = [
                    'title' => $collectionData['name'] ?? $collectionName,
                    'slug' => $collectionName,
                    'entries_count' => $entriesCount
                ];
                
                $totalEntries += $entriesCount;
            }
        }
    }
    
    // Get recent activity from activity log
    $recentActivity = [];
    $logFile = __DIR__ . '/../logs/security.log';
    if (file_exists($logFile)) {
        // Include helper functions without executing top-level code
        if (!function_exists('getEventCategory')) {
            function getEventCategory($event) {
                $contentEvents = ['create_entry', 'update_entry', 'delete_entry', 'create_collection', 'update_collection', 'delete_collection', 'publish_entry', 'unpublish_entry', 'scheduled_publish', 'review_action'];
                $userEvents = ['create_user', 'update_user', 'delete_user', 'invitation_created', 'invitation_accepted'];
                $systemEvents = ['login_success', 'login_failed', 'logout', 'rate_limited'];
                
                if (in_array($event, $contentEvents)) return 'content';
                if (in_array($event, $userEvents)) return 'users';
                if (in_array($event, $systemEvents)) return 'system';
                
                return 'other';
            }
        }
        
        if (!function_exists('getEventIcon')) {
            function getEventIcon($event) {
                $icons = [
                    'create_entry' => '📝',
                    'update_entry' => '✏️',
                    'delete_entry' => '🗑️',
                    'create_collection' => '📁',
                    'publish_entry' => '✅',
                    'unpublish_entry' => '❌',
                    'scheduled_publish' => '⏰',
                    'review_action' => '👁️',
                    'create_user' => '👤',
                    'invitation_created' => '✉️',
                    'login_success' => '🔓',
                    'login_failed' => '🔒',
                    'logout' => '🚪',
                    'upload_media' => '📷'
                ];
                return $icons[$event] ?? '📌';
            }
        }
        
        if (!function_exists('getEventMessage')) {
            function getEventMessage($event, $details) {
                switch ($event) {
                    case 'create_entry':
                        $collection = $details['collection'] ?? 'collection';
                        $title = $details['title'] ?? $details['id'] ?? 'entry';
                        return "Created entry \"{$title}\" in {$collection}";
                    case 'update_entry':
                        $collection = $details['collection'] ?? 'collection';
                        $title = $details['title'] ?? $details['id'] ?? 'entry';
                        return "Updated entry \"{$title}\" in {$collection}";
                    case 'delete_entry':
                        $collection = $details['collection'] ?? 'collection';
                        return "Deleted entry from {$collection}";
                    case 'create_collection':
                        $name = $details['name'] ?? 'collection';
                        return "Created collection \"{$name}\"";
                    case 'publish_entry':
                        $collection = $details['collection'] ?? 'collection';
                        return "Published entry in {$collection}";
                    case 'scheduled_publish':
                        $collection = $details['collection'] ?? 'collection';
                        return "Scheduled entry in {$collection}";
                    case 'review_action':
                        $action = $details['action'] ?? 'reviewed';
                        $collection = $details['collection'] ?? 'collection';
                        return "{$action} entry in {$collection}";
                    case 'create_user':
                        $email = $details['email'] ?? 'user';
                        return "Created user {$email}";
                    case 'invitation_created':
                        $email = $details['email'] ?? 'user';
                        return "Sent invitation to {$email}";
                    case 'invitation_accepted':
                        $email = $details['email'] ?? 'user';
                        return "{$email} accepted invitation";
                    case 'login_success':
                        return "User logged in";
                    case 'login_failed':
                        return "Failed login attempt";
                    case 'logout':
                        return "User logged out";
                    case 'upload_media':
                        $filename = $details['filename'] ?? $details['name'] ?? 'file';
                        return "Uploaded media: {$filename}";
                    default:
                        return ucfirst(str_replace('_', ' ', $event));
                }
            }
        }
        
        if (!function_exists('formatActivityItem')) {
            function formatActivityItem($event, $user, $details, $timestamp) {
                $icon = getEventIcon($event);
                $message = getEventMessage($event, $details);
                
                return [
                    'event' => $event,
                    'user' => $user,
                    'message' => $message,
                    'icon' => $icon,
                    'timestamp' => $timestamp,
                    'details' => $details,
                    'category' => getEventCategory($event)
                ];
            }
        }
        
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $lines = array_reverse($lines); // Newest first
        $limit = 10;
        $processed = 0;
        
        foreach ($lines as $line) {
            if ($processed >= $limit) break;
            
            $entry = json_decode($line, true);
            if (!$entry || !is_array($entry)) {
                continue;
            }
            
            $event = $entry['event'] ?? '';
            $timestamp = $entry['timestamp'] ?? '';
            $user = $entry['user'] ?? 'Unknown';
            $details = $entry['details'] ?? [];
            
            $activityItem = formatActivityItem($event, $user, $details, $timestamp);
            if ($activityItem) {
                $recentActivity[] = [
                    'message' => $activityItem['message'] ?? '',
                    'created_at' => $timestamp,
                    'icon' => $activityItem['icon'] ?? '📌',
                    'category' => $activityItem['category'] ?? 'other'
                ];
                $processed++;
            }
        }
    }
    
    // If no activity found, show placeholder
    if (empty($recentActivity)) {
        $recentActivity = [
            [
                'message' => 'No recent activity',
                'created_at' => date('Y-m-d H:i:s'),
                'icon' => '📌',
                'category' => 'system'
            ]
        ];
    }
    
    // Get recent collections (last 5)
    $recentCollections = array_slice($collections, 0, 5);
    
    // Build onboarding checklist v2 — essential steps only (P19-M6 / P5.6-D)
    require_once __DIR__ . '/../core/plugins.php';
    $installedPlugins = get_all_plugins();
    $hasMarketplacePlugin = false;
    foreach ($installedPlugins as $plugin) {
        if (!empty($plugin['marketplace_slug'])) {
            $hasMarketplacePlugin = true;
            break;
        }
    }

    $installPath = $onboardingStatus['install_path'] ?? null;
    $aiSkipped = !empty($onboardingStatus['ai_skipped']);
    $aiConnected = !empty($onboardingStatus['ai_connected']);
    $dismissed = $onboardingStatus['checklist_dismissed'] ?? [];
    $checklistHidden = !empty($onboardingStatus['checklist_hidden']);

    // Check for existing AI connections
    $hasAiConnection = $aiConnected;
    if (!$hasAiConnection) {
        $usersStorage = new Storage('../users');
        foreach ($usersStorage->list('') as $userFile) {
            $u = $usersStorage->read($userFile);
            if ($u && ($u['type'] ?? '') === 'ai' && ($u['status'] ?? 'active') === 'active') {
                $hasAiConnection = true;
                break;
            }
        }
    }

    $checklist = [
        [
            'id' => 'first_collection',
            'label' => 'Create your first collection',
            'completed' => count($collections) > 0,
            'link' => birkly_admin_url('content'),
            'hint' => $installPath === 'local'
                ? 'Use Content in the admin sidebar. Your local server is at http://localhost:8080'
                : ($installPath === 'cloud' ? 'Need FTP help? Re-open START_HERE.html from your site root.' : null),
        ],
        [
            'id' => 'first_entry',
            'label' => 'Add your first entry',
            'completed' => $totalEntries > 0,
            'link' => count($collections) > 0
                ? birkly_admin_url('entry-editor', ['collection' => $collections[0]['slug']])
                : birkly_admin_url('content'),
        ],
    ];

    if (!$hasMarketplacePlugin) {
        $checklist[] = [
            'id' => 'install_marketplace_plugins',
            'label' => __('admin.install_marketplace_plugins'),
            'completed' => false,
            'link' => birkly_admin_url('plugins', ['tab' => 'discover']),
        ];
    }

    // AI checklist item ONLY when user skipped the wizard AI step (P19-D4 / P5.6-D)
    if ($aiSkipped && !$hasAiConnection) {
        $checklist[] = [
            'id' => 'connect_ai',
            'label' => __('admin.connect_ai_assistant'),
            'completed' => false,
            'link' => birkly_admin_url('settings', ['tab' => 'ai']),
            'optional' => true,
        ];
    }

    // Filter dismissed items
    $checklist = array_values(array_filter($checklist, function ($item) use ($dismissed) {
        return !in_array($item['id'] ?? '', $dismissed, true);
    }));
    
    $checklistCompleted = 0;
    foreach ($checklist as $item) {
        if ($item['completed']) {
            $checklistCompleted++;
        }
    }
    
    return [
        'stats' => [
            'collections' => count($collections),
            'total_entries' => $totalEntries,
            'published' => $published,
            'drafts' => $drafts
        ],
        'recent_activity' => $recentActivity,
        'recent_collections' => $recentCollections,
        'onboarding' => [
            'completed' => $onboardingStatus['completed'] ?? false,
            'is_first_time' => $onboardingStatus['is_first_time'] ?? false,
            'install_path' => $installPath,
            'checklist_hidden' => $checklistHidden,
            'checklist' => $checklistHidden ? [] : $checklist,
            'checklist_progress' => [
                'completed' => $checklistCompleted,
                'total' => count($checklist)
            ]
        ]
    ];
}

function getContentData() {
    $helpersPath = __DIR__ . '/admin_content_helpers.php';
    if (!is_file($helpersPath)) {
        return [
            'error' => 'Content page helpers are missing on this server. Redeploy api/admin_content_helpers.php (sync the full Birkly-0.1 embed).',
        ];
    }
    require_once $helpersPath;

    return birkly_get_content_page_data();
}

function birkly_admin_collection_slug_sidebar_state(array $collection, bool $isNew = false): bool
{
    require_once __DIR__ . '/../core/entry_slug.php';
    $slug = (string) ($collection['slug'] ?? '');
    $name = (string) ($collection['name'] ?? $collection['title'] ?? '');
    $id = (string) ($collection['id'] ?? $slug);

    return birkly_auto_slug_checked($slug, $name, $id !== '' ? $id : null, $isNew || $slug === '');
}

function birkly_admin_entry_slug_sidebar_state(array $entry): bool
{
    require_once __DIR__ . '/../core/entry_slug.php';
    $slug = (string) ($entry['slug'] ?? '');
    $title = (string) ($entry['title'] ?? '');
    $id = (string) ($entry['id'] ?? '');
    $isNew = $id === '';

    return birkly_auto_slug_checked($slug, $title, $id !== '' ? $id : null, $isNew);
}

function birkly_admin_collection_automation_context(string $collectionSlug, ?string $subCollectionSlug = null): array
{
    $automationData = getAutomationData();
    $automations = is_array($automationData['automations'] ?? null) ? $automationData['automations'] : [];
    $filtered = [];

    foreach ($automations as $automation) {
        if (!is_array($automation)) {
            continue;
        }
        foreach (($automation['triggers'] ?? []) as $trigger) {
            if (!is_array($trigger)) {
                continue;
            }
            $config = is_array($trigger['config'] ?? null) ? $trigger['config'] : [];
            $triggerCollection = (string) ($config['collection'] ?? $trigger['collection'] ?? '');
            if ($triggerCollection !== $collectionSlug) {
                continue;
            }
            if ($subCollectionSlug !== null && $subCollectionSlug !== '') {
                $triggerSub = (string) ($config['sub_collection'] ?? '');
                if ($triggerSub !== '' && $triggerSub !== $subCollectionSlug) {
                    continue;
                }
            }
            $filtered[] = $automation;
            break;
        }
    }

    return [
        'collection_automations' => $filtered,
        'automation_plugin_inactive' => !empty($automationData['plugin_inactive']),
    ];
}

function birkly_admin_entry_sidebar_context(array $collection, ?string $entryId = null, bool $isSubEntry = false): array
{
    $siteUrl = '';
    $siteFile = __DIR__ . '/../settings/site.json';
    if (is_file($siteFile)) {
        $siteData = json_decode((string) file_get_contents($siteFile), true);
        if (is_array($siteData)) {
            $siteUrl = rtrim((string) ($siteData['url'] ?? ''), '/');
        }
    }

    return [
        'site_url' => $siteUrl,
        'collection_slug' => (string) ($collection['slug'] ?? ''),
        'collection_template' => (string) ($collection['template'] ?? ''),
        'entry_id' => (string) ($entryId ?? ''),
        'is_sub_entry' => $isSubEntry,
    ];
}

function getCollectionEditorData() {
    // Get collection data from URL parameters
    $collectionSlug = $_GET['collection'] ?? null;
    $subCollectionSlug = $_GET['sub_collection'] ?? null;
    $parentSubCollectionSlug = $_GET['parent_sub_collection'] ?? null;
    $newSubCollection = !empty($_GET['new_sub_collection']);
    
    // Check if this is a sub-collection editor
    $isSubCollection = !empty($subCollectionSlug) || !empty($parentSubCollectionSlug) || $newSubCollection;
    
    // Get available fieldtypes with categories (including plugin-registered ones)
    $fieldtypes = [];
    
    try {
        // Use the admin_fieldtypes API function to get fieldtypes (includes plugin fieldtypes)
        // Prevent admin_fieldtypes.php from executing its switch statement when included
        $GLOBALS['__HARU_INTERNAL_CALL'] = true;
        require_once __DIR__ . '/admin_fieldtypes.php';
        if (function_exists('getAvailableFieldtypes')) {
            $allFieldtypes = getAvailableFieldtypes();
            foreach ($allFieldtypes as $ft) {
                $fieldtypes[] = [
                    'name' => $ft['name'] ?? strtolower($ft['type'] ?? ''),
                    'type' => $ft['type'] ?? $ft['name'] ?? '',
                    'label' => $ft['label'] ?? ucwords(str_replace(['_', '-'], ' ', $ft['name'] ?? '')),
                    'category' => $ft['category'] ?? 'Misc',
                    'config' => $ft['config'] ?? []
                ];
            }
        } else {
            // Fallback to directory scan if function not available
            $fieldtypesDir = __DIR__ . "/../fieldtypes";
            if (is_dir($fieldtypesDir)) {
                $dirs = glob($fieldtypesDir . '/*', GLOB_ONLYDIR);
                if ($dirs !== false) {
                    foreach ($dirs as $dir) {
                        $fieldtypeName = basename($dir);
                        
                        // Skip special directories
                        if (in_array($fieldtypeName, ['.', '..', 'Tag'])) {
                            continue;
                        }
                        
                        // Check if PHP file exists
                        $phpFile = $dir . '/' . $fieldtypeName . '.php';
                        if (!file_exists($phpFile)) {
                            continue;
                        }
                        
                        // Try to get fieldtype class and category
                        $className = "Birkly\\FieldTypes\\{$fieldtypeName}\\{$fieldtypeName}";
                        
                        // Try to load the class
                        if (!class_exists($className)) {
                            if (file_exists($phpFile)) {
                                require_once $phpFile;
                            }
                        }
                        
                        // Verify class exists and extends BaseField
                        if (class_exists($className)) {
                            try {
                                // Check if it extends BaseField
                                if (!is_subclass_of($className, 'Birkly\\FieldTypes\\BaseField')) {
                                    continue;
                                }
                                
                                // Create a temporary instance to get config and category
                                $tempConfig = [
                                    'name' => 'temp',
                                    'label' => ucfirst($fieldtypeName),
                                    'required' => false
                                ];
                                $fieldtype = new $className($tempConfig);
                                $config = $fieldtype->getAdminConfig();
                                $category = method_exists($fieldtype, 'getCategory') ? $fieldtype->getCategory() : 'Misc';
                                
                                $fieldtypes[] = [
                                    'name' => strtolower($fieldtypeName),
                                    'label' => ucfirst($fieldtypeName),
                                    'type' => $config['type'] ?? strtolower($fieldtypeName),
                                    'category' => $category,
                                    'config' => $config
                                ];
                            } catch (\Exception $e) {
                                // Skip fieldtypes that can't be instantiated
                                error_log("Error loading fieldtype {$fieldtypeName}: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
                                continue;
                            } catch (\Throwable $e) {
                                // Skip fieldtypes that can't be instantiated (PHP 7+)
                                error_log("Error loading fieldtype {$fieldtypeName}: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
                                continue;
                            }
                        }
                    }
                }
            }
        }
    } catch (\Exception $e) {
        error_log("Fatal error in getCollectionEditorData: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
        error_log("Stack trace: " . $e->getTraceAsString());
        // Return empty fieldtypes array on error
        $fieldtypes = [];
    }
    
    // Debug: Log fieldtypes found
    error_log("Found " . count($fieldtypes) . " fieldtypes: " . implode(', ', array_column($fieldtypes, 'name')));
    
    // Handle sub-collection editor
    if ($isSubCollection) {
        if (!$collectionSlug) {
            return ['error' => 'Collection parameter required'];
        }
        
        // Get parent collection data
        $collectionFile = __DIR__ . "/../content/collections/$collectionSlug/collection.json";
        if (!file_exists($collectionFile)) {
            return ['error' => 'Collection not found'];
        }
        
        $collectionData = json_decode(file_get_contents($collectionFile), true);
        $collectionData['slug'] = $collectionSlug;
        
        // Get parent sub-collection if specified (for nested sub-collections)
        $parentSubCollection = null;
        if ($parentSubCollectionSlug) {
            require_once __DIR__ . '/../core/sub_entries.php';
            $parentSubCollection = getSubCollectionConfig($collectionSlug, $parentSubCollectionSlug);
        }
        
        // If editing existing sub-collection, get its data
        $subCollection = null;
        if ($subCollectionSlug) {
            require_once __DIR__ . '/../core/sub_entries.php';
            // Use the recursive getSubCollectionConfig which handles nested sub-collections
            $subCollection = getSubCollectionConfig($collectionSlug, $subCollectionSlug, $parentSubCollectionSlug);
        }
        
        $subData = $subCollection ?? [
                'slug' => '',
                'name' => '',
                'description' => '',
                'entry_method' => 'admin_only',
                'auto_publish' => false,
                'fields' => [],
                'parent_sub_collection' => $parentSubCollectionSlug
            ];

        return [
            'collection' => $collectionData,
            'sub_collection' => $subData,
            'parent_sub_collection' => $parentSubCollection,
            'fieldtypes' => $fieldtypes,
            'subCollection' => $subData,
            'is_sub_collection' => true,
            'subAutoSlugChecked' => birkly_admin_collection_slug_sidebar_state($subData, empty($subData['slug'])),
        ] + birkly_admin_collection_automation_context(
            (string) ($collectionData['slug'] ?? $collectionSlug),
            (string) ($subData['slug'] ?? $subCollectionSlug)
        );
    }
    
    // Handle regular collection editor
    if ($collectionSlug) {
        // Editing existing collection
        $collectionFile = __DIR__ . "/../content/collections/$collectionSlug/collection.json";
        $schemaFile = __DIR__ . "/../content/collections/$collectionSlug/schema.json";
        
        if (file_exists($collectionFile)) {
            $collectionData = json_decode(file_get_contents($collectionFile), true);
            $collectionData['slug'] = $collectionSlug;
            $collectionData['title'] = $collectionData['name'] ?? $collectionSlug;
            
            // Read fields from schema.json
            $fields = [];
            if (file_exists($schemaFile)) {
                $schemaData = json_decode(file_get_contents($schemaFile), true);
                if (isset($schemaData['fields'])) {
                    $fields = $schemaData['fields'];
                    // Add IDs to fields for easier reference
                    foreach ($fields as $index => $field) {
                        $fields[$index]['id'] = 'field-' . $index;
                    }
                }
            }
            
            return [
                'collection' => $collectionData,
                'fields' => $fields,
                'fieldtypes' => $fieldtypes,
                'is_sub_collection' => false,
                'collAutoSlugChecked' => birkly_admin_collection_slug_sidebar_state($collectionData),
            ] + birkly_admin_collection_automation_context((string) $collectionSlug);
        }
    }
    
    // New collection
    return [
        'collection' => [
            'title' => '',
            'slug' => '',
            'description' => '',
            'template' => '',
            'sort' => 'created_desc'
        ],
        'fields' => [],
        'fieldtypes' => $fieldtypes,
        'is_sub_collection' => false,
        'collAutoSlugChecked' => true,
        'collection_automations' => [],
        'automation_plugin_inactive' => false,
    ];
}

/**
 * Rewrite fieldtype HTML input names/ids for a language tab (en[field]).
 */
function birkly_admin_multilang_field_html(string $html, string $fieldName, string $langCode): string
{
    if ($fieldName === '' || $html === '') {
        return $html;
    }

    $newName = $langCode . '[' . $fieldName . ']';
    $langFieldName = $langCode . '_' . $fieldName;
    $quoted = preg_quote($fieldName, '/');

    $html = preg_replace('/\bname=(["\'])' . $quoted . '\1/i', 'name=$1' . $newName . '$1', $html, 1);
    $html = preg_replace('/\bname=(["\'])' . $quoted . '\[\]/', 'name=$1' . $newName . '[]', $html);

    // Hidden value ids first (field_{name}_value) then container ids (field_{name})
    $html = preg_replace('/\bid=(["\'])field_' . $quoted . '_value\1/i', 'id=$1field_' . $langFieldName . '_value$1', $html);
    $html = preg_replace('/\bid=(["\'])field_' . $quoted . '\1/i', 'id=$1field_' . $langFieldName . '$1', $html);

    // DynamicItems onclick handlers use bare field name as DOM key
    $html = preg_replace(
        "/showAddBlockModal\\(['\"]" . $quoted . "['\"]\\)/",
        "showAddBlockModal('" . $langFieldName . "')",
        $html
    );
    $html = preg_replace(
        "/window\\.HaruFieldtypes\\['dynamicitems'\\]\\.showAddBlockModal\\(['\"]" . $quoted . "['\"]\\)/",
        "window.HaruFieldtypes['dynamicitems'].showAddBlockModal('" . $langFieldName . "')",
        $html
    );

    // data-field-config JSON "name":"fieldname"
    $html = preg_replace(
        '/"name"\s*:\s*"' . $quoted . '"/',
        '"name":"' . $langFieldName . '"',
        $html
    );

    return $html;
}

function birkly_admin_multilang_label_for_id(string $labelForId, string $fieldName, string $langCode): string
{
    if ($labelForId === '' || $fieldName === '') {
        return $labelForId;
    }

    $prefix = 'field_' . $fieldName;
    if (strpos($labelForId, $prefix) !== 0) {
        return $labelForId;
    }

    return 'field_' . $langCode . '_' . $fieldName . substr($labelForId, strlen($prefix));
}

/**
 * Build per-language field payloads for the entry editor when multilanguage is enabled.
 */
function birkly_admin_build_language_fields(array $fields, array $entry, array $collectionData, bool $includeFieldHtml): array
{
    $languages = $collectionData['multilanguage']['languages'] ?? [];
    if (empty($languages)) {
        return [];
    }

    $languageFields = [];
    foreach ($languages as $langCode) {
        $langFields = [];
        foreach ($fields as $f) {
            $name = $f['name'] ?? '';
            $langField = $f;
            $langField['lang_value'] = $entry[$langCode][$name] ?? '';
            if (!empty($langField['label_for_id'])) {
                $langField['label_for_id'] = birkly_admin_multilang_label_for_id(
                    (string) $langField['label_for_id'],
                    $name,
                    $langCode
                );
            }
            if ($includeFieldHtml && !empty($f['html'])) {
                $langField['html'] = birkly_admin_multilang_field_html($f['html'], $name, $langCode);
            }
            $langFields[] = $langField;
        }
        $languageFields[$langCode] = $langFields;
    }

    return $languageFields;
}

function getEntryEditorData($includeFieldHtml = false) {
    // Get collection and entry data from URL parameters
    $collectionSlug = $_GET['collection'] ?? null;
    $entryId = $_GET['entry'] ?? null;
    $subCollectionSlug = $_GET['sub_collection'] ?? null;
    $subEntryId = $_GET['sub_entry'] ?? null;
    $parentSubCollectionSlug = $_GET['parent_sub_collection'] ?? null;
    $parentSubEntryId = $_GET['parent_sub_entry'] ?? null;
    
    // Debug logging - log full URL and all GET parameters
    $fullUrl = $_SERVER['REQUEST_URI'] ?? 'unknown';
    $allGetParams = json_encode($_GET);
    $queryString = $_SERVER['QUERY_STRING'] ?? 'unknown';
    $httpReferer = $_SERVER['HTTP_REFERER'] ?? 'unknown';
    error_log("getEntryEditorData: Full URL: {$fullUrl}");
    error_log("getEntryEditorData: Query string: {$queryString}");
    error_log("getEntryEditorData: HTTP Referer: {$httpReferer}");
    
    // CRITICAL FIX: If entryId looks like a sub-entry ID (starts with 'sub_'), it's likely wrong
    // This happens when clicking on a sub-entry from a sub-entry detail page incorrectly
    // We need to extract the correct main entry ID and sub-entry info from the sub-entry itself
    if (!empty($entryId) && strpos($entryId, 'sub_') === 0 && empty($subCollectionSlug) && empty($subEntryId)) {
        error_log("getEntryEditorData: WARNING - entryId looks like a sub-entry ID but sub_collection is empty! entryId={$entryId}");
        error_log("getEntryEditorData: Attempting to load sub-entry to extract parent info...");
        
        
        // Try to find which sub-collection this sub-entry belongs to by searching all sub-collections
        // This is a recovery mechanism for malformed URLs
        if (!empty($collectionSlug)) {
            require_once __DIR__ . '/../core/sub_entries.php';
            // Load collection data from file
            $collectionFile = __DIR__ . "/../content/collections/{$collectionSlug}/collection.json";
            $collectionData = null;
            if (file_exists($collectionFile)) {
                $collectionData = json_decode(file_get_contents($collectionFile), true);
                if ($collectionData) {
                    $collectionData['slug'] = $collectionSlug;
                    $collectionData['title'] = $collectionData['name'] ?? $collectionSlug;
                }
            }
            if ($collectionData && !empty($collectionData['sub_collections'])) {
                foreach ($collectionData['sub_collections'] as $subCol) {
                    $subColSlug = $subCol['slug'] ?? '';
                    if (empty($subColSlug)) continue;
                    
                    // Try to find the sub-entry in this sub-collection
                    // We need to search through all entries to find which one has this sub-entry
                    // This is a fallback - ideally the URL should be correct
                    if (!function_exists('get_storage')) {
                        require_once __DIR__ . '/../core/storage/StorageFactory.php';
                    }
                    $storage = get_storage();
                    $entriesPath = "collections/{$collectionSlug}/entries";
                    $files = $storage->list($entriesPath);
                    $entries = [];
                    foreach ($files as $file) {
                        if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                            $entryData = $storage->read("{$entriesPath}/{$file}");
                            if ($entryData) {
                                $entryData['id'] = basename($file, '.json');
                                $entries[] = $entryData;
                            }
                        }
                    }
                    
                    foreach ($entries as $mainEntry) {
                        $mainEntryId = $mainEntry['id'] ?? '';
                        if (empty($mainEntryId)) continue;
                        
                        $subEntry = getSubEntry($collectionSlug, $mainEntryId, $subColSlug, $entryId);
                        if ($subEntry) {
                            // Found it! Update the parameters
                            error_log("getEntryEditorData: Found sub-entry! Main entry: {$mainEntryId}, Sub-collection: {$subColSlug}");
                            $originalSubEntryId = $entryId; // Store the original sub-entry ID
                            $entryId = $mainEntryId; // Update to main entry ID
                            $subCollectionSlug = $subColSlug;
                            $subEntryId = $originalSubEntryId; // The original entryId was the sub-entry ID
                            
                            // IMPORTANT: Recalculate $isSubEntry after recovery
                            $isSubEntry = !empty($subCollectionSlug) && (!empty($subEntryId) || !empty($entryId));
                            
                            break 2; // Break out of both loops
                        }
                    }
                }
            }
        }
    }
    
    // Check if we're creating or editing a sub-entry
    // Sub-entry if: sub_collection is present AND (sub_entry is present OR entry is present for parent)
    $isSubEntry = !empty($subCollectionSlug) && (!empty($subEntryId) || !empty($entryId));
    
    // Check if we're creating a nested sub-entry (sub-entry of a sub-entry)
    $isNestedSubEntry = !empty($parentSubCollectionSlug) && !empty($parentSubEntryId) && empty($subEntryId);
    
    if (!$collectionSlug) {
        return ['error' => 'Collection parameter required'];
    }
    
    // Get collection data
    $collectionFile = __DIR__ . "/../content/collections/$collectionSlug/collection.json";
    if (!file_exists($collectionFile)) {
        return ['error' => 'Collection not found'];
    }
    
    $collectionData = json_decode(file_get_contents($collectionFile), true);
    $collectionData['slug'] = $collectionSlug;
    // Ensure 'name' field exists for consistency
    if (!isset($collectionData['name']) && isset($collectionData['title'])) {
        $collectionData['name'] = $collectionData['title'];
    }
    
    // Get fields - either from sub-collection or parent collection
    $fields = [];
    if ($isSubEntry && !empty($subCollectionSlug)) {
        // Load sub-collection configuration and use its fields
        // getSubCollectionConfig now searches recursively for nested sub-collections
        require_once __DIR__ . '/../core/sub_entries.php';
        $subCollection = getSubCollectionConfig($collectionSlug, $subCollectionSlug);
        
        // If not found, try searching in nested sub-collections manually
        if (!$subCollection) {
            $allSubCollections = getSubCollections($collectionSlug);
            foreach ($allSubCollections as $parentSubCol) {
                $nested = $parentSubCol['sub_collections'] ?? [];
                foreach ($nested as $nestedSubCol) {
                    if (($nestedSubCol['slug'] ?? '') === $subCollectionSlug) {
                        $subCollection = $nestedSubCol;
                        break 2;
                    }
                }
            }
        }
        
        if ($subCollection) {
            // Get fields from sub-collection (check both schema[fields] and fields)
            $subFields = $subCollection['schema']['fields'] ?? $subCollection['fields'] ?? [];
            $fields = $subFields;
            // Add id to each field
            foreach ($fields as $index => $field) {
                $fields[$index]['id'] = 'sub-field-' . $index;
            }
        } else {
            return ['error' => 'Sub-collection not found'];
        }
    } else {
        // Get fields from parent collection schema.json
        $schemaFile = __DIR__ . "/../content/collections/$collectionSlug/schema.json";
        if (file_exists($schemaFile)) {
            $schemaData = json_decode(file_get_contents($schemaFile), true);
            if (isset($schemaData['fields'])) {
                $fields = $schemaData['fields'];
                // Add id to each field
                foreach ($fields as $index => $field) {
                    $fields[$index]['id'] = 'field-' . $index;
                }
            }
        }
    }
    
    
    // Filter reserved system fields from main editor loop (P29-M6)
    require_once __DIR__ . '/../core/entry_slug.php';
    $reservedFieldNames = array_flip(array_map('strtolower', birkly_entry_reserved_field_names()));
    $fields = array_values(array_filter($fields, static function ($field) use ($reservedFieldNames) {
        $name = strtolower((string) ($field['name'] ?? ''));
        return $name === 'title' || !isset($reservedFieldNames[$name]);
    }));

    // Get all available fieldtypes (including plugin-registered ones)
    // Use the admin_fieldtypes API function to get fieldtypes
    // Prevent admin_fieldtypes.php from executing its switch statement when included
    $GLOBALS['__HARU_INTERNAL_CALL'] = true;
    require_once __DIR__ . '/admin_fieldtypes.php';
    if (function_exists('getAvailableFieldtypes')) {
        $allFieldtypes = getAvailableFieldtypes();
        $fieldtypes = [];
        foreach ($allFieldtypes as $ft) {
            $fieldtypes[] = [
                'name' => $ft['name'] ?? strtolower($ft['type'] ?? ''),
                'type' => $ft['type'] ?? $ft['name'] ?? '',
                'label' => $ft['label'] ?? ucwords(str_replace(['_', '-'], ' ', $ft['name'] ?? ''))
            ];
        }
    } else {
        // Fallback to directory scan if function not available
        $fieldtypes = [];
        $fieldtypesDir = __DIR__ . '/../fieldtypes';
        if (is_dir($fieldtypesDir)) {
            $fieldtypeDirs = glob($fieldtypesDir . '/*', GLOB_ONLYDIR);
            foreach ($fieldtypeDirs as $fieldtypeDir) {
                $fieldtypeName = basename($fieldtypeDir);
                $normalizedName = strtolower(str_replace('-', '', $fieldtypeName));
                $fieldtypes[] = [
                    'name' => $normalizedName,
                    'type' => $normalizedName,
                    'label' => ucwords(str_replace(['-', '_'], ' ', $fieldtypeName))
                ];
            }
        }
    }
    
    // Get entry data if editing
    $entry = null;
    if ($isSubEntry && $subEntryId && $entryId) {
        // Load sub-entry data (editing existing sub-entry)
        require_once __DIR__ . '/../core/sub_entries.php';
        error_log("getEntryEditorData: Loading sub-entry - collection={$collectionSlug}, entry={$entryId}, sub_collection={$subCollectionSlug}, sub_entry={$subEntryId}, parent_sub_collection={$parentSubCollectionSlug}, parent_sub_entry={$parentSubEntryId}");
        
        // Check if this is a nested sub-entry (has parent sub-entry info in URL)
        if (!empty($parentSubCollectionSlug) && !empty($parentSubEntryId)) {
            // This is a nested sub-entry - need to load from nested path
            if (!function_exists('get_storage')) {
                require_once __DIR__ . '/../core/storage/StorageFactory.php';
            }
            try {
                $storage = get_storage();
                $nestedEntryPath = "collections/{$collectionSlug}/entries/{$entryId}/sub_entries/{$parentSubCollectionSlug}/{$parentSubEntryId}/sub_entries/{$subCollectionSlug}/{$subEntryId}.json";
                error_log("getEntryEditorData: Trying nested path: {$nestedEntryPath}");
                $entryData = $storage->read($nestedEntryPath);
                if ($entryData) {
                    $entry = $entryData;
                    error_log("getEntryEditorData: Nested sub-entry loaded successfully");
                } else {
                    error_log("getEntryEditorData: Nested sub-entry not found at {$nestedEntryPath}");
                }
            } catch (Exception $e) {
                error_log("getEntryEditorData: Error loading nested sub-entry: " . $e->getMessage());
            }
        }
        
        // If not found as nested, try first-level path
        if (!$entry) {
            error_log("getEntryEditorData: Trying first-level path for sub_entry_id={$subEntryId}");
            
            
            $entry = getSubEntry($collectionSlug, $entryId, $subCollectionSlug, $subEntryId);
            if ($entry) {
                error_log("getEntryEditorData: First-level sub-entry loaded successfully");
                // Ensure ID is set correctly
                $entry['id'] = $subEntryId;
                
            } else {
                error_log("getEntryEditorData: First-level sub-entry not found - checking if file exists");
                
                
                // Try to list the directory to see what files are there
                if (!function_exists('get_storage')) {
                    require_once __DIR__ . '/../core/storage/StorageFactory.php';
                }
                try {
                    $storage = get_storage();
                    $listPath = "collections/{$collectionSlug}/entries/{$entryId}/sub_entries/{$subCollectionSlug}";
                    $files = $storage->list($listPath);
                    error_log("getEntryEditorData: Files in directory: " . json_encode($files));
                    error_log("getEntryEditorData: Looking for: {$subEntryId}.json");
                    
                } catch (Exception $e) {
                    error_log("getEntryEditorData: Error listing directory: " . $e->getMessage());
                }
            }
        }
        
        if ($entry) {
            $entry['id'] = $subEntryId;
            $entry['_is_sub_entry'] = true;
            $entry['_parent_collection'] = $collectionSlug;
            $entry['_parent_entry_id'] = $entryId;
            $entry['_sub_collection'] = $subCollectionSlug;
            // Get parent entry title for breadcrumbs
            $parentEntryFile = __DIR__ . "/../content/collections/$collectionSlug/entries/$entryId.json";
            if (file_exists($parentEntryFile)) {
                $parentEntry = json_decode(file_get_contents($parentEntryFile), true);
                $entry['_parent_entry_title'] = $parentEntry['title'] ?? 'Parent Entry';
            }
            // Check if this is a nested sub-entry (has parent sub-entry info)
            // This happens when editing a nested sub-entry
            if (!empty($parentSubCollectionSlug) && !empty($parentSubEntryId)) {
                $entry['_parent_sub_collection'] = $parentSubCollectionSlug;
                $entry['_parent_sub_entry_id'] = $parentSubEntryId;
                // Load parent sub-entry for breadcrumbs
                $parentSubEntry = getSubEntry($collectionSlug, $entryId, $parentSubCollectionSlug, $parentSubEntryId);
                if ($parentSubEntry) {
                    $entry['_parent_sub_entry_title'] = $parentSubEntry['title'] ?? 'Parent Sub-Entry';
                    $entry['_parent_sub_collection_slug'] = $parentSubCollectionSlug;
                }
            }
        } else {
            error_log("getEntryEditorData: Failed to load sub-entry - entry will be null, showing new entry form");
        }
    } elseif ($isSubEntry && $entryId && empty($subEntryId)) {
        // Creating new sub-entry - load parent entry for context
        if ($isNestedSubEntry) {
            // Creating a nested sub-entry - load parent sub-entry for context
            require_once __DIR__ . '/../core/sub_entries.php';
            error_log("Loading parent sub-entry: collection={$collectionSlug}, entry={$entryId}, sub_collection={$parentSubCollectionSlug}, sub_entry={$parentSubEntryId}");
            $parentSubEntry = getSubEntry($collectionSlug, $entryId, $parentSubCollectionSlug, $parentSubEntryId);
            if ($parentSubEntry) {
                error_log("Parent sub-entry loaded successfully");
                // Create a new entry object for the nested sub-entry being created
                $entry = [
                    'title' => '',
                    'status' => 'draft',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                $entry['_is_sub_entry'] = true; // This is a sub-entry
                $entry['_parent_entry_id'] = $entryId; // Main entry ID
                $entry['_sub_collection'] = $subCollectionSlug; // The sub-collection we're creating in
                // Store parent info for nested sub-entry creation
                $entry['_parent_sub_collection'] = $parentSubCollectionSlug;
                $entry['_parent_sub_entry_id'] = $parentSubEntryId;
                // Store parent sub-entry info for breadcrumbs
                $entry['_parent_sub_entry_title'] = $parentSubEntry['title'] ?? 'Parent Sub-Entry';
                $entry['_parent_sub_collection_slug'] = $parentSubCollectionSlug;
                // Get main entry title for breadcrumbs
                $parentEntryFile = __DIR__ . "/../content/collections/$collectionSlug/entries/$entryId.json";
                if (file_exists($parentEntryFile)) {
                    $parentEntry = json_decode(file_get_contents($parentEntryFile), true);
                    $entry['_parent_entry_title'] = $parentEntry['title'] ?? 'Parent Entry';
                }
            } else {
                error_log("Failed to load parent sub-entry - falling back to main entry");
                // Fallback to main entry
                $entryFile = __DIR__ . "/../content/collections/$collectionSlug/entries/$entryId.json";
                if (file_exists($entryFile)) {
                    $entry = json_decode(file_get_contents($entryFile), true);
                    $entry['id'] = $entryId;
                    $entry['_is_sub_entry'] = false;
                    $entry['_parent_entry_id'] = $entryId;
                    $entry['_sub_collection'] = $subCollectionSlug;
                }
            }
        } else {
            // Creating first-level sub-entry - load parent entry for context
            $entryFile = __DIR__ . "/../content/collections/$collectionSlug/entries/$entryId.json";
            if (file_exists($entryFile)) {
                $entry = json_decode(file_get_contents($entryFile), true);
                $entry['id'] = $entryId;
                $entry['_is_sub_entry'] = false; // This is the parent
                $entry['_parent_entry_id'] = $entryId;
                $entry['_sub_collection'] = $subCollectionSlug;
            }
        }
    } elseif ($entryId && !$isSubEntry) {
        // Load parent entry data (regular entry editing)
        $entryFile = __DIR__ . "/../content/collections/$collectionSlug/entries/$entryId.json";
        if (file_exists($entryFile)) {
            $entry = json_decode(file_get_contents($entryFile), true);
            $entry['id'] = $entryId;
        }
    }
    
    $payload = [
        'collection' => $collectionData,
        'entry' => $entry ?? [
            'title' => '',
            'status' => 'draft',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ],
        'fields' => $fields,
        'fieldtypes' => $fieldtypes,
        'is_sub_entry' => $isSubEntry,
        'sub_collection' => $subCollectionSlug,
        'sub_entry_id' => $subEntryId, // Add sub_entry_id to payload so template can use it
        'parent_collection' => $collectionSlug,
        'parent_entry_id' => $entryId,
        'parent_sub_collection' => $parentSubCollectionSlug,
        'parent_sub_entry_id' => $parentSubEntryId
    ];
    
    // Always add mandatory title field at the top (not from schema)
    // This ensures every entry has a title, regardless of collection schema
    // Check if title field already exists in fields (from schema)
    $hasTitleField = false;
    foreach ($payload['fields'] as $field) {
        if (($field['name'] ?? '') === 'title') {
            $hasTitleField = true;
            break;
        }
    }
    
    // Always add title field if it doesn't exist
    if (!$hasTitleField) {
        $titleField = [
            'name' => 'title',
            'label' => 'Title',
            'type' => 'text',
            'required' => false, // Not mandatory - will auto-fill with entry ID if empty
            'id' => 'field-title',
            'help_text' => 'If left empty, the entry ID will be used as the title',
            'label_for_id' => 'field_title',
        ];
        
        // Add title field HTML if including field HTML
        if ($includeFieldHtml) {
            require_once __DIR__ . '/../fieldtypes/BaseField.php';
            require_once __DIR__ . '/../fieldtypes/Text/Text.php';
            
            // Get title value - if editing, use entry title (which might be the ID)
            $titleValue = $payload['entry']['title'] ?? '';
            // If creating new entry, leave empty (will be auto-filled with ID on save)
            if (empty($payload['entry']['id'])) {
                $titleValue = '';
            }
            
            $titleFieldtype = new \Birkly\FieldTypes\Text\Text([
                'name' => 'title',
                'label' => 'Title',
                'value' => $titleValue,
                'required' => false, // Not mandatory
                'help' => 'If left empty, the entry ID will be used as the title',
                'group' => '',
                'options' => []
            ]);
            
            $titleField['html'] = $titleFieldtype->render();
        }
        
        // Prepend title field to fields array
        $payload['fields'] = array_merge([$titleField], $payload['fields']);
    } else {
        // If title field already exists, ensure it's not required
        foreach ($payload['fields'] as $idx => $field) {
            if (($field['name'] ?? '') === 'title') {
                $payload['fields'][$idx]['required'] = false;
                // Update HTML if it exists to remove required attribute
                if ($includeFieldHtml && isset($field['html'])) {
                    // Remove required attribute and asterisk from HTML
                    $payload['fields'][$idx]['html'] = preg_replace('/\s+required/i', '', $field['html']);
                    $payload['fields'][$idx]['html'] = preg_replace('/<span[^>]*class="[^"]*required[^"]*"[^>]*>\s*\*\s*<\/span>/i', '', $field['html']);
                }
                break;
            }
        }
    }

    // Optionally embed rendered field HTML for template-driven editor
    if ($includeFieldHtml && !empty($fields)) {
        require_once __DIR__ . '/../fieldtypes/BaseField.php';
        // Include fieldtypes API in internal mode to avoid emitting output
        $____prevInternal = $GLOBALS['__HARU_INTERNAL_CALL'] ?? null;
        $GLOBALS['__HARU_INTERNAL_CALL'] = true;
        // Prevent admin_fieldtypes.php from executing its switch statement when included
        $GLOBALS['__HARU_INTERNAL_CALL'] = true;
        require_once __DIR__ . '/admin_fieldtypes.php'; // Use the fieldtype loading functions
        if ($____prevInternal === null) {
            unset($GLOBALS['__HARU_INTERNAL_CALL']);
        } else {
            $GLOBALS['__HARU_INTERNAL_CALL'] = $____prevInternal;
        }
        
        $__fieldtypeCss = [];
        $__fieldtypeJs = [];
        foreach ($payload['fields'] as $idx => $f) {
            $type = $f['type'] ?? '';
            $name = $f['name'] ?? '';
            if (!$type || !$name) continue;
            
            // Note: Keep type as-is - getFieldtypeClass handles aliases (content-editor -> contenteditor)
            
            // For multi-language entries, we need to get the value from the default language
            // The entry editor template will handle language-specific values
            $multilanguageEnabled = !empty($collectionData['multilanguage']['enabled']);
            $multilanguageLanguages = $collectionData['multilanguage']['languages'] ?? [];
            $defaultLang = !empty($multilanguageLanguages) ? $multilanguageLanguages[0] : 'en';
            
            $rawValue = ($payload['entry'][$name] ?? null);
            // If multi-language is enabled and value is not at top level, try default language
            if ($rawValue === null && $multilanguageEnabled && !empty($multilanguageLanguages) && isset($payload['entry'][$defaultLang][$name])) {
                $rawValue = $payload['entry'][$defaultLang][$name];
            }
            
            // Use the proper fieldtype loader from admin_fieldtypes.php
            $className = getFieldtypeClass($type);
            if ($className && class_exists($className)) {
                try {
                    // Process the value through processLoadedValue if method exists
                    $value = $rawValue;
                    if ($rawValue !== null) {
                        // Create a temporary instance to process the value
                        $tempFt = new $className([
                            'name' => $name,
                            'label' => $f['label'] ?? $name,
                            'value' => null,
                            'required' => false
                        ]);
                        if (method_exists($tempFt, 'processLoadedValue')) {
                            $value = $tempFt->processLoadedValue($rawValue);
                        }
                    } else {
                        // Use default value if no value exists
                        $tempFt = new $className([
                            'name' => $name,
                            'label' => $f['label'] ?? $name,
                            'value' => null,
                            'required' => false
                        ]);
                        if (method_exists($tempFt, 'getDefaultValue')) {
                            $value = $tempFt->getDefaultValue();
                        }
                    }
                    
                    // Build config array with all field settings
                    $fieldConfig = [
                        'name' => $name,
                        'label' => $f['label'] ?? $name,
                        'value' => $value,
                        'required' => $f['required'] ?? false,
                        'help' => $f['help_text'] ?? '',
                        'group' => $f['group'] ?? 'Content',
                        'options' => $f['options'] ?? [],
                        'field_type' => $type,
                    ];
                    
                    // Add all other field settings to config (format, css_url, etc.)
                    foreach ($f as $key => $val) {
                        if (!in_array($key, ['name', 'label', 'type', 'required', 'help_text', 'group', 'options', 'id'])) {
                            $fieldConfig[$key] = $val;
                        }
                    }
                    
                    // Add entry context for fieldtypes that need it (e.g., password fieldtype needs user_id)
                    if (isset($payload['entry'])) {
                        $fieldConfig['entry_id'] = $payload['entry']['id'] ?? '';
                        $fieldConfig['user_id'] = $payload['entry']['user_id'] ?? '';
                    }
                    
                    $ft = new $className($fieldConfig);
                    
                    if (method_exists($ft, 'render')) {
                        // For editor form, use full field render
                        $renderedHtml = $ft->render();
                        if (method_exists($ft, 'getLabelForId')) {
                            $payload['fields'][$idx]['label_for_id'] = $ft->getLabelForId();
                        }
                        
                        // Add data-fieldtype attribute to help with JS initialization
                        // Wrap the HTML or add attribute to the first input/textarea/select
                        $renderedHtml = preg_replace_callback(
                            '/<(input|textarea|select|div|span)([^>]*)>/i',
                            function($matches) use ($type) {
                                $tag = $matches[1];
                                $attrs = $matches[2];
                                // Only add if not already present
                                if (strpos($attrs, 'data-fieldtype') === false) {
                                    $attrs .= ' data-fieldtype="' . htmlspecialchars($type) . '"';
                                }
                                return "<{$tag}{$attrs}>";
                            },
                            $renderedHtml,
                            1 // Only replace first match
                        );
                        
                        $payload['fields'][$idx]['html'] = $renderedHtml;
                        
                        // Collect CSS
                        if (method_exists($ft, 'getCSS')) {
                            $css = (string)$ft->getCSS();
                            if ($css !== '' && !in_array($css, $__fieldtypeCss, true)) {
                                $__fieldtypeCss[] = $css;
                            }
                        }
                        
                        // Collect JavaScript
                        if (method_exists($ft, 'getJavaScript')) {
                            $js = (string)$ft->getJavaScript();
                            if ($js !== '' && !in_array($js, $__fieldtypeJs, true)) {
                                $__fieldtypeJs[] = $js;
                            }
                        }
                    } else {
                        $payload['fields'][$idx]['html'] = '<div class="field-error">Fieldtype "' . htmlspecialchars($type) . '" missing render() method</div>';
                    }
                } catch (\Throwable $e) {
                    error_log("Fieldtype rendering error for {$type}: " . $e->getMessage());
                    $payload['fields'][$idx]['html'] = '<div class="field-error">Fieldtype "' . htmlspecialchars($type) . '" failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            } else {
                $payload['fields'][$idx]['html'] = '<div class="field-error">Fieldtype "' . htmlspecialchars($type) . '" not found</div>';
            }
        }
        
        if (!empty($__fieldtypeCss) || !empty($__fieldtypeJs)) {
            // Write assets to temp files to avoid inline </script> parsing issues
            $tempDir = __DIR__ . '/../temp';
            if (!is_dir($tempDir)) { @mkdir($tempDir, 0755, true); }
            $hashBase = implode('\n', $__fieldtypeCss) . '\n' . implode('\n', $__fieldtypeJs);
            $hash = substr(sha1($hashBase), 0, 12);
            $cssPath = $tempDir . "/fieldtypes-{$hash}.css";
            $cssBundled = implode("\n\n", $__fieldtypeCss);
            $cssUrl = '';
            $cssInline = '';
            if ($cssBundled !== '') {
                @file_put_contents($cssPath, $cssBundled);
                if (is_file($cssPath) && filesize($cssPath) > 0) {
                    $cssUrl = "/temp/fieldtypes-{$hash}.css";
                } else {
                    // Fallback when temp/ is not writable (common on read-only deploys)
                    $cssInline = $cssBundled;
                }
            }

            // Write each JS chunk as a separate file to isolate failures
            $jsFiles = [];
            $jsInline = '';
            if (!empty($__fieldtypeJs)) {
                foreach ($__fieldtypeJs as $i => $chunk) {
                    $file = $tempDir . "/fieldtypes-{$hash}-{$i}.js";
                    $wrapped = ";(function(){\n" . $chunk . "\n})();";
                    @file_put_contents($file, "/* Birkly fieldtype chunk {$i} */\n'use strict';\n" . $wrapped . "\n");
                    if (is_file($file) && filesize($file) > 0) {
                        $jsFiles[] = "/temp/fieldtypes-{$hash}-{$i}.js";
                    }
                }
                if ($jsFiles === []) {
                    $jsInline = implode(
                        "\n\n",
                        array_map(
                            static fn(string $chunk): string => ";(function(){\n" . $chunk . "\n})();",
                            $__fieldtypeJs
                        )
                    );
                }
            }
            $payload['fieldtype_assets'] = [
                'css' => $cssUrl,
                'css_inline' => $cssInline,
                'js_files'  => $jsFiles,
                'js_inline' => $jsInline,
            ];
        }
    }

    $payload['autoSlugChecked'] = birkly_admin_entry_slug_sidebar_state($payload['entry']);
    $payload['sidebar'] = birkly_admin_entry_sidebar_context(
        $payload['collection'] ?? [],
        $payload['entry']['id'] ?? $entryId,
        !empty($subCollectionSlug)
    );
    $payload['can_review_entry'] = !$isSubEntry
        && !empty($payload['entry']['id'])
        && in_array($_SESSION['user']['role'] ?? '', ['admin', 'editor'], true);
    $payload['is_nested_sub_entry'] = $isNestedSubEntry;

    $payload['multilanguage_enabled'] = !empty($collectionData['multilanguage']['enabled']);
    $payload['multilanguage_languages'] = $collectionData['multilanguage']['languages'] ?? [];
    if ($payload['multilanguage_enabled'] && !empty($payload['multilanguage_languages'])) {
        $payload['language_fields'] = birkly_admin_build_language_fields(
            $payload['fields'] ?? [],
            $payload['entry'] ?? [],
            $collectionData,
            $includeFieldHtml
        );
    }

    require_once __DIR__ . '/../admin/template-helpers.php';
    $payload['breadcrumbs'] = build_entry_breadcrumbs($payload);
    $payload['cancel_url'] = build_cancel_url($payload);

    return $payload;
}

function getEntriesData() {
    $params = birkly_admin_get_entries_list_params();
    $collectionSlug = $params['collection'];
    $statusFilter = $params['status'];

    if ($collectionSlug === '') {
        return ['error' => 'Collection parameter required'];
    }

    $collectionFile = __DIR__ . "/../content/collections/$collectionSlug/collection.json";
    if (!file_exists($collectionFile)) {
        return ['error' => 'Collection not found'];
    }

    $collectionData = json_decode(file_get_contents($collectionFile), true);
    $collectionData['slug'] = $collectionSlug;

    require_once __DIR__ . '/../core/collection_meta.php';
    $collectionData = array_merge($collectionData, birkly_normalize_collection_meta($collectionData, $collectionSlug));

    $entries = [];
    $entriesDir = __DIR__ . "/../content/collections/$collectionSlug/entries";
    if (is_dir($entriesDir)) {
        $entryFiles = glob($entriesDir . '/*.json');
        foreach ($entryFiles as $entryFile) {
            $entryData = json_decode(file_get_contents($entryFile), true);
            if (!is_array($entryData)) {
                continue;
            }
            $entryData['id'] = basename($entryFile, '.json');
            $entries[] = $entryData;
        }
    }

    $trashedCount = 0;
    foreach ($entries as $entry) {
        if (birkly_admin_normalize_entry_status((string) ($entry['status'] ?? '')) === 'trashed') {
            $trashedCount++;
        }
    }

    $orderFile = __DIR__ . "/../content/collections/$collectionSlug/order.json";
    if (file_exists($orderFile)) {
        $order = json_decode(file_get_contents($orderFile), true);
        if (is_array($order) && !empty($order)) {
            $entryMap = [];
            foreach ($entries as $entry) {
                $entryMap[$entry['id']] = $entry;
            }

            $orderedEntries = [];
            foreach ($order as $entryId) {
                if (isset($entryMap[$entryId])) {
                    $orderedEntries[] = $entryMap[$entryId];
                    unset($entryMap[$entryId]);
                }
            }

            foreach ($entryMap as $entry) {
                $orderedEntries[] = $entry;
            }

            $entries = $orderedEntries;
        }
    }

    $entries = array_values(array_filter($entries, static function (array $entry) use ($statusFilter): bool {
        return birkly_admin_entry_matches_status_filter($entry, $statusFilter);
    }));

    if ($params['q'] !== '') {
        $query = $params['q'];
        $entries = array_values(array_filter($entries, static function (array $entry) use ($query, $collectionData): bool {
            return birkly_admin_entry_matches_query($entry, $query, $collectionData);
        }));
    }

    if ($params['sort'] !== '') {
        $entries = birkly_admin_sort_entries($entries, $params['sort'], $params['order'], $collectionData);
    }

    $totalEntries = count($entries);
    $perPage = max(1, (int) $params['per_page']);
    $totalPages = max(1, (int) ceil($totalEntries / $perPage));
    $page = min(max(1, (int) $params['page']), $totalPages);
    $offset = ($page - 1) * $perPage;
    $entries = array_slice($entries, $offset, $perPage);

    $subCollections = $collectionData['sub_collections'] ?? [];
    if (!empty($subCollections)) {
        $helpersPath = __DIR__ . '/admin_content_helpers.php';
        if (is_file($helpersPath)) {
            require_once $helpersPath;
        }

        foreach ($entries as &$entry) {
            if (empty($entry['id'])) {
                continue;
            }
            try {
                if (function_exists('birkly_count_sub_entries_for_entry')) {
                    $entry['__sub_entry_count'] = birkly_count_sub_entries_for_entry(
                        $collectionSlug,
                        (string) $entry['id'],
                        $subCollections
                    );
                } else {
                    $entry['__sub_entry_count'] = 0;
                }
            } catch (Throwable $e) {
                error_log('Sub-entry count for entry ' . $entry['id'] . ': ' . $e->getMessage());
                $entry['__sub_entry_count'] = 0;
            }
        }
        unset($entry);
    }

    $fields = [];
    $schemaFile = __DIR__ . "/../content/collections/$collectionSlug/schema.json";
    if (file_exists($schemaFile)) {
        $schemaData = json_decode(file_get_contents($schemaFile), true);
        if (isset($schemaData['fields']) && is_array($schemaData['fields'])) {
            $fields = $schemaData['fields'];
        }
    }

    require_once __DIR__ . '/../core/entry_slug.php';
    $fields = birkly_entries_list_visible_fields($fields);

    $entriesWithCells = [];
    $typeWidths = [];
    $typeMinWidths = [];

    if (!function_exists('getFieldtypeClass')) {
        require_once __DIR__ . '/admin_fieldtypes.php';
    }

    foreach ($fields as $field) {
        $name = $field['name'] ?? null;
        $type = $field['type'] ?? null;
        if (!$name || !$type) {
            continue;
        }
        if (isset($typeWidths[$type])) {
            continue;
        }
        $width = null;
        $minWidth = null;
        try {
            $class = getFieldtypeClass($type);
            if ($class && class_exists($class)) {
                $ft = new $class(['name' => $name]);
                if (method_exists($ft, 'getListColumnWidth')) {
                    $width = $ft->getListColumnWidth();
                }
                if (method_exists($ft, 'getTableMinWidth')) {
                    $minWidth = $ft->getTableMinWidth();
                }
            }
        } catch (\Throwable $e) {
            error_log("Error loading fieldtype {$type} for width calculation: " . $e->getMessage());
            $width = null;
            $minWidth = null;
        }
        $typeWidths[$type] = $width ?: '240px';
        $typeMinWidths[$type] = $minWidth ?: ($width ?: '240px');
    }

    foreach ($fields as $idx => $field) {
        $ftype = $field['type'] ?? '';
        $fields[$idx]['width'] = $typeWidths[$ftype] ?? '240px';
        $fields[$idx]['min_width'] = $typeMinWidths[$ftype] ?? ($typeWidths[$ftype] ?? '240px');
    }

    $preferredWidth = '240px';
    if (!empty($fields)) {
        $preferredWidth = $fields[0]['width'] ?? '240px';
    }

    foreach ($entries as $entry) {
        $cells = [];
        $title = birkly_admin_extract_entry_title($entry, $collectionData);
        $cells['__title'] = $title;
        $cells['__status'] = $entry['status'] ?? 'draft';

        $entryFieldPairs = [];
        foreach ($fields as $field) {
            $name = $field['name'] ?? null;
            $type = $field['type'] ?? null;
            if (!$name || !$type) {
                continue;
            }

            $value = birkly_admin_extract_entry_value($entry, $name, $collectionData, $field['default'] ?? null);

            $rendered = null;
            try {
                if (function_exists('getFieldtypeClass')) {
                    $fieldtypeClass = getFieldtypeClass($type);
                } else {
                    $ftName = ucfirst(strtolower($type));
                    $fieldtypeClass = "Birkly\\FieldTypes\\$ftName\\$ftName";
                    if (!class_exists($fieldtypeClass)) {
                        $fieldtypeClass = null;
                    }
                }

                if ($fieldtypeClass && class_exists($fieldtypeClass)) {
                    /** @var \Birkly\FieldTypes\BaseField $ft */
                    $ftConfig = [
                        'name' => $name,
                        'label' => $field['label'] ?? $name,
                        'value' => $value,
                        'required' => $field['required'] ?? false,
                        'help' => $field['help_text'] ?? '',
                        'group' => $field['group'] ?? 'Content',
                    ];

                    if (isset($field['options'])) {
                        $ftConfig['options'] = $field['options'];
                    }
                    if (isset($field['collection'])) {
                        $ftConfig['collection'] = $field['collection'];
                    }

                    $ft = new $fieldtypeClass($ftConfig);
                    if (method_exists($ft, 'renderListCell')) {
                        $rendered = $ft->renderListCell($value, ['entry' => $entry, 'field' => $field]);
                    }
                }
            } catch (\Throwable $e) {
                $rendered = null;
            }

            if ($rendered === null) {
                if (is_array($value)) {
                    $rendered = '<span class="ft-list-badge">' . count($value) . ' items</span>';
                } elseif (is_object($value)) {
                    $rendered = '<span class="ft-list-badge">object</span>';
                } else {
                    $text = htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
                    if ($text === '') {
                        $text = '<span class="ft-list-empty">–</span>';
                    }
                    $rendered = '<span class="ft-list-text">' . $text . '</span>';
                }
            }

            $cells[$name] = strip_tags($rendered);
            $cells[$name . '_html'] = $rendered;

            $entryFieldPairs[] = [
                'name' => $name,
                'label' => $field['label'] ?? $name,
                'type' => $type,
                'width' => $typeWidths[$type] ?? '240px',
                'value' => strip_tags($rendered),
                'value_html' => $rendered
            ];
        }

        $combined = '';
        foreach ($fields as $field) {
            $fname = $field['name'] ?? null;
            if (!$fname) {
                continue;
            }
            $label = htmlspecialchars($field['label'] ?? $fname, ENT_QUOTES, 'UTF-8');
            $valueHtml = $cells[$fname . '_html'] ?? '<span class="ft-list-empty">–</span>';
            $combined .= '<div class="field-chip"><span class="field-label">' . $label . '</span> <span class="field-value">' . $valueHtml . '</span></div>';
        }
        $cells['__fields_html'] = $combined;

        $entryWithCells = $entry;
        $entryWithCells['__cells'] = $cells;
        $entryWithCells['entryfields'] = $entryFieldPairs;
        $entryWithCells['row_cells'] = array_map(function ($pair) {
            return [
                'width' => $pair['width'] ?? '240px',
                'html' => $pair['value_html'] ?? '<span class="ft-list-empty">–</span>'
            ];
        }, $entryFieldPairs);

        $rowCellsHtml = '';
        foreach ($entryFieldPairs as $pair) {
            $w = htmlspecialchars($pair['width'] ?? '240px', ENT_QUOTES, 'UTF-8');
            $h = $pair['value_html'] ?? '<span class="ft-list-empty">–</span>';
            $rowCellsHtml .= '<div class="cell cell-field" style="--col-w: ' . $w . '">' . $h . '</div>';
        }
        $entryWithCells['row_cells_html'] = $rowCellsHtml;
        $entriesWithCells[] = $entryWithCells;
    }

    return [
        'collection' => $collectionData,
        'fields' => $fields,
        'entries' => $entriesWithCells,
        'list_column_width' => $preferredWidth,
        'current_status_filter' => $statusFilter,
        'current_search_query' => $params['q'],
        'current_sort' => $params['sort'],
        'current_order' => $params['order'],
        'entries_page' => $page,
        'entries_per_page' => $perPage,
        'entries_total' => $totalEntries,
        'entries_total_pages' => $totalPages,
        'is_trash_view' => $statusFilter === 'trashed',
        'trashed_count' => $trashedCount,
    ];
}

function birkly_admin_get_entries_list_params(): array {
    $defaultPerPage = birkly_admin_get_default_admin_items_per_page();
    $requestedPerPage = (int) ($_GET['per_page'] ?? 0);
    $perPage = $requestedPerPage > 0 ? $requestedPerPage : $defaultPerPage;

    return [
        'collection' => trim((string) ($_GET['collection'] ?? '')),
        'status' => trim((string) ($_GET['status'] ?? '')),
        'page' => max(1, (int) ($_GET['page'] ?? 1)),
        'per_page' => max(1, min($perPage, 200)),
        'q' => trim((string) ($_GET['q'] ?? $_GET['search'] ?? '')),
        'sort' => trim((string) ($_GET['sort'] ?? '')),
        'order' => birkly_admin_normalize_sort_order((string) ($_GET['order'] ?? '')),
    ];
}

function birkly_admin_get_default_admin_items_per_page(): int {
    static $defaultItemsPerPage = null;
    if ($defaultItemsPerPage !== null) {
        return $defaultItemsPerPage;
    }

    $defaultItemsPerPage = 20;
    $settingsFile = __DIR__ . '/../settings/admin.json';
    if (!is_file($settingsFile)) {
        return $defaultItemsPerPage;
    }

    $settingsData = json_decode((string) file_get_contents($settingsFile), true);
    $configured = (int) ($settingsData['items_per_page'] ?? 0);
    if ($configured > 0) {
        $defaultItemsPerPage = $configured;
    }

    return $defaultItemsPerPage;
}

function birkly_admin_normalize_sort_order(string $order): string {
    $normalized = strtolower(trim($order));
    return $normalized === 'desc' ? 'desc' : 'asc';
}

function birkly_admin_normalize_entry_status(string $status): string {
    return $status === 'draft' ? 'unpublished' : ($status !== '' ? $status : 'unpublished');
}

function birkly_admin_entry_matches_status_filter(array $entry, string $statusFilter): bool {
    $status = birkly_admin_normalize_entry_status((string) ($entry['status'] ?? 'draft'));
    if ($statusFilter === '') {
        return $status !== 'trashed';
    }

    $normalizedFilter = birkly_admin_normalize_entry_status($statusFilter);
    return $status === $normalizedFilter;
}

function birkly_admin_extract_entry_value(array $entry, string $name, array $collectionData, $default = null) {
    if (array_key_exists($name, $entry)) {
        return $entry[$name];
    }

    $multilanguageEnabled = !empty($collectionData['multilanguage']['enabled']);
    $multilanguageLanguages = $collectionData['multilanguage']['languages'] ?? [];
    if ($multilanguageEnabled && !empty($multilanguageLanguages)) {
        $defaultLanguage = $multilanguageLanguages[0];
        if (isset($entry[$defaultLanguage]) && is_array($entry[$defaultLanguage]) && array_key_exists($name, $entry[$defaultLanguage])) {
            return $entry[$defaultLanguage][$name];
        }
        foreach ($multilanguageLanguages as $lang) {
            if (isset($entry[$lang]) && is_array($entry[$lang]) && array_key_exists($name, $entry[$lang])) {
                return $entry[$lang][$name];
            }
        }
    }

    return $default;
}

function birkly_admin_extract_entry_title(array $entry, array $collectionData): string {
    $title = birkly_admin_extract_entry_value($entry, 'title', $collectionData, '');
    $title = is_scalar($title) ? trim((string) $title) : '';
    if ($title === '') {
        $title = (string) ($entry['key'] ?? $entry['slug'] ?? 'Untitled');
    }

    return $title;
}

function birkly_admin_entry_matches_query(array $entry, string $query, array $collectionData): bool {
    $needle = trim($query);
    if ($needle === '') {
        return true;
    }

    $haystack = [
        birkly_admin_extract_entry_title($entry, $collectionData),
        (string) ($entry['slug'] ?? ''),
        (string) ($entry['id'] ?? ''),
        birkly_admin_normalize_entry_status((string) ($entry['status'] ?? '')),
    ];

    $encoded = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (is_string($encoded)) {
        $haystack[] = $encoded;
    }

    return stripos(implode(' ', $haystack), $needle) !== false;
}

function birkly_admin_sort_entries(array $entries, string $sort, string $order, array $collectionData): array {
    if ($sort === '' || count($entries) < 2) {
        return $entries;
    }

    $direction = strtolower($order) === 'desc' ? -1 : 1;
    $indexedEntries = [];
    foreach ($entries as $index => $entry) {
        $indexedEntries[] = [
            'index' => $index,
            'entry' => $entry,
        ];
    }

    usort($indexedEntries, static function (array $left, array $right) use ($sort, $direction, $collectionData): int {
        $leftValue = birkly_admin_get_entry_sort_value($left['entry'], $sort, $collectionData);
        $rightValue = birkly_admin_get_entry_sort_value($right['entry'], $sort, $collectionData);

        if (is_numeric($leftValue) && is_numeric($rightValue)) {
            $comparison = $leftValue <=> $rightValue;
        } else {
            $comparison = strnatcasecmp((string) $leftValue, (string) $rightValue);
        }

        if ($comparison === 0) {
            $comparison = $left['index'] <=> $right['index'];
        }

        return $comparison * $direction;
    });

    return array_values(array_map(static function (array $item): array {
        return $item['entry'];
    }, $indexedEntries));
}

function birkly_admin_get_entry_sort_value(array $entry, string $sort, array $collectionData) {
    switch ($sort) {
        case 'title':
            return birkly_admin_extract_entry_title($entry, $collectionData);
        case 'status':
            return birkly_admin_normalize_entry_status((string) ($entry['status'] ?? ''));
        case 'updated':
        case 'updated_at':
        case 'modified_at':
            return birkly_admin_normalize_sort_value($entry['updated_at'] ?? $entry['modified_at'] ?? $entry['created_at'] ?? '', true);
        case 'created':
        case 'created_at':
            return birkly_admin_normalize_sort_value($entry['created_at'] ?? '', true);
        case 'slug':
            return birkly_admin_normalize_sort_value($entry['slug'] ?? '');
        case 'id':
            return birkly_admin_normalize_sort_value($entry['id'] ?? '');
        default:
            return birkly_admin_normalize_sort_value(
                birkly_admin_extract_entry_value($entry, $sort, $collectionData, $entry[$sort] ?? ''),
                false
            );
    }
}

function birkly_admin_normalize_sort_value($value, bool $preferTimestamp = false) {
    if ($preferTimestamp) {
        $timestamp = strtotime((string) $value);
        return $timestamp !== false ? $timestamp : 0;
    }

    if (is_array($value) || is_object($value)) {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($encoded) ? $encoded : '';
    }

    if (is_bool($value)) {
        return $value ? 1 : 0;
    }

    if (is_numeric($value)) {
        return (float) $value;
    }

    return is_scalar($value) ? trim((string) $value) : '';
}

/**
 * Process an entry for list display (renders fields, creates cells)
 * Used by both getEntriesData() and getEntryDetailData()
 */
function processEntryForList($entry, $fields, $collectionData) {
    require_once __DIR__ . '/../core/entry_slug.php';
    $fields = birkly_entries_list_visible_fields($fields);

    // Build width map per fieldtype
    $typeWidths = [];
    $typeMinWidths = [];
    foreach ($fields as $field) {
        $type = $field['type'] ?? null;
        if (!$type || isset($typeWidths[$type])) continue;
        $width = null;
        $minWidth = null;
        try {
            $ftName = ucfirst(strtolower($type));
            $class = "Birkly\\FieldTypes\\$ftName\\$ftName";
            $file = __DIR__ . "/../fieldtypes/$ftName/$ftName.php";
            if (!class_exists($class) && file_exists($file)) {
                require_once $file;
            }
            if (class_exists($class)) {
                $ft = new $class(['name' => $field['name'] ?? '']);
                if (method_exists($ft, 'getListColumnWidth')) {
                    $width = $ft->getListColumnWidth();
                }
                if (method_exists($ft, 'getTableMinWidth')) {
                    $minWidth = $ft->getTableMinWidth();
                }
            }
        } catch (\Throwable $e) {
            $width = null;
            $minWidth = null;
        }
        $typeWidths[$type] = $width ?: '240px';
        $typeMinWidths[$type] = $minWidth ?: ($width ?: '240px');
    }
    
    // Attach min-width to field definitions
    foreach ($fields as $idx => $field) {
        $ftype = $field['type'] ?? '';
        if (!isset($fields[$idx]['min_width'])) {
            $fields[$idx]['min_width'] = $typeMinWidths[$ftype] ?? ($typeWidths[$ftype] ?? '240px');
        }
    }
    
    // Check if collection has multi-language enabled
    $multilanguageEnabled = !empty($collectionData['multilanguage']['enabled']);
    $multilanguageLanguages = $collectionData['multilanguage']['languages'] ?? [];
    $defaultLanguage = !empty($multilanguageLanguages) ? $multilanguageLanguages[0] : 'en';
    
    $cells = [];
    // Title and status are mandatory in UI
    $title = $entry['title'] ?? '';
    if (empty($title) && $multilanguageEnabled && !empty($multilanguageLanguages)) {
        if (isset($entry[$defaultLanguage]['title'])) {
            $title = $entry[$defaultLanguage]['title'];
        } else {
            foreach ($multilanguageLanguages as $lang) {
                if (isset($entry[$lang]['title']) && !empty($entry[$lang]['title'])) {
                    $title = $entry[$lang]['title'];
                    break;
                }
            }
        }
    }
    if (empty($title)) {
        $title = $entry['key'] ?? $entry['slug'] ?? 'Untitled';
    }
    $cells['__title'] = $title;
    $cells['__status'] = $entry['status'] ?? 'draft';

    $entryFieldPairs = [];
    foreach ($fields as $field) {
        $name = $field['name'] ?? null;
        $type = $field['type'] ?? null;
        
        // If name is missing, try to generate it from label
        if (empty($name) && !empty($field['label'])) {
            $name = strtolower(trim(preg_replace('/[^A-Za-z0-9_]+/', '_', $field['label'])));
        }
        
        if (empty($name) || empty($type)) continue;

        // For multi-language entries, extract value from language section
        $value = $entry[$name] ?? ($field['default'] ?? null);
        if ($value === null && $multilanguageEnabled && !empty($multilanguageLanguages)) {
            if (isset($entry[$defaultLanguage][$name])) {
                $value = $entry[$defaultLanguage][$name];
            } else {
                foreach ($multilanguageLanguages as $lang) {
                    if (isset($entry[$lang][$name])) {
                        $value = $entry[$lang][$name];
                        break;
                    }
                }
            }
        }

        // Try to render via fieldtype class if present
        $rendered = null;
        try {
            // Use getFieldtypeClass to support both core and plugin fieldtypes
            if (function_exists('getFieldtypeClass')) {
                $fieldtypeClass = getFieldtypeClass($type);
            } else {
                // Fallback to core fieldtype namespace
                $ftName = ucfirst(strtolower($type));
                $fieldtypeClass = "Birkly\\FieldTypes\\$ftName\\$ftName";
                if (!class_exists($fieldtypeClass)) {
                    $fieldtypeClass = null;
                }
            }

            if ($fieldtypeClass && class_exists($fieldtypeClass)) {
                /** @var \Birkly\FieldTypes\BaseField $ft */
                $ftConfig = [
                    'name' => $name,
                    'label' => $field['label'] ?? $name,
                    'value' => $value,
                    'required' => $field['required'] ?? false,
                    'help' => $field['help_text'] ?? '',
                    'group' => $field['group'] ?? 'Content',
                ];
                
                // Add field-specific config (e.g., options for Select, collection for Relation)
                if (isset($field['options'])) {
                    $ftConfig['options'] = $field['options'];
                }
                if (isset($field['collection'])) {
                    $ftConfig['collection'] = $field['collection'];
                }
                
                $ft = new $fieldtypeClass($ftConfig);
                if (method_exists($ft, 'renderListCell')) {
                    $rendered = $ft->renderListCell($value, ['entry' => $entry, 'field' => $field]);
                }
            }
        } catch (\Throwable $e) {
            $rendered = null;
        }

        if ($rendered === null) {
            // Generic fallback
            if (is_array($value)) {
                $rendered = '<span class="ft-list-badge">' . count($value) . ' items</span>';
            } else if (is_object($value)) {
                $rendered = '<span class="ft-list-badge">object</span>';
            } else {
                $text = htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
                if ($text === '') $text = '<span class="ft-list-empty">–</span>';
                $rendered = '<span class="ft-list-text">' . $text . '</span>';
            }
        }

        $cells[$name] = strip_tags($rendered);
        $cells[$name . '_html'] = $rendered;

        $entryFieldPairs[] = [
            'name' => $name,
            'label' => $field['label'] ?? $name,
            'type' => $type,
            'width' => $typeWidths[$type] ?? '240px',
            'value' => strip_tags($rendered),
            'value_html' => $rendered
        ];
    }

    $entryWithCells = $entry;
    $entryWithCells['__cells'] = $cells;
    $entryWithCells['entryfields'] = $entryFieldPairs;
    $entryWithCells['row_cells'] = array_map(function ($pair) {
        return [
            'width' => $pair['width'] ?? '240px',
            'html' => $pair['value_html'] ?? '<span class="ft-list-empty">–</span>'
        ];
    }, $entryFieldPairs);
    
    $rowCellsHtml = '';
    foreach ($entryFieldPairs as $pair) {
        $w = htmlspecialchars($pair['width'] ?? '240px', ENT_QUOTES, 'UTF-8');
        $h = $pair['value_html'] ?? '<span class="ft-list-empty">–</span>';
        $rowCellsHtml .= '<div class="cell cell-field" style="--col-w: '.$w.'">'.$h.'</div>';
    }
    $entryWithCells['row_cells_html'] = $rowCellsHtml;
    
    return $entryWithCells;
}

function getMediaData() {
    // Get media files
    $mediaPath = __DIR__ . '/../media';
    $mediaFiles = [];
    
    if (is_dir($mediaPath)) {
        $files = glob($mediaPath . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                $filename = basename($file);
                $extension = pathinfo($filename, PATHINFO_EXTENSION);
                $size = filesize($file);
                
                $mediaFiles[] = [
                    'name' => $filename,
                    'size' => $size,
                    'type' => $extension,
                    'url' => '/media/' . $filename
                ];
            }
        }
    }
    
    return [
        'files' => $mediaFiles
    ];
}

function getSettingsData() {
    require_once __DIR__ . '/../core/roles.php';
    birkly_ensure_default_roles();

    // Get settings data
    $settingsPath = __DIR__ . '/../settings';
    $settings = [
        'site' => [
            'title' => 'Birkly',
            'description' => 'A headless CMS'
        ],
        'admin' => [
            'email' => 'admin@example.com'
        ],
        'security' => [
            'session_timeout' => 3600,
            'max_login_attempts' => 5,
            'enable_csrf' => true
        ]
    ];
    
    if (is_dir($settingsPath)) {
        $settingFiles = glob($settingsPath . '/*.json');
        foreach ($settingFiles as $file) {
            $category = basename($file, '.json');
            $fileData = json_decode(file_get_contents($file), true);
            if ($fileData) {
                $settings[$category] = array_merge($settings[$category] ?? [], $fileData);
            }
        }
    }
    
    // Load plugins data
    require_once __DIR__ . '/../core/plugins.php';
    $plugins = get_all_plugins();
    $settings['plugins'] = [];
    foreach ($plugins as $pluginId => $plugin) {
        $settings['plugins'][$pluginId] = [
            'name' => $plugin['name'] ?? ucwords(str_replace(['_', '-'], ' ', $pluginId)),
            'version' => $plugin['version'] ?? '1.0.0',
            'description' => $plugin['description'] ?? '',
            'author' => $plugin['author'] ?? 'Unknown',
            'active' => $plugin['active'] ?? false,
            'settings' => $plugin['settings'] ?? [],
            'settings_schema' => $plugin['settings_schema'] ?? []
        ];
    }
    
    $settings['roles'] = getRolesData()['roles'];
    $webhooksData = getWebhooksData();

    return [
        'settings' => $settings,
        'users' => getUsersData()['users'],
        'webhook_collections' => $webhooksData['collections'],
    ];
}

function getAccountData() {
    // Get current user data from session
    if (!isset($_SESSION['user'])) {
        return ['error' => 'User not found'];
    }

    $currentUser = $_SESSION['user'];

    // Load full user data from storage to get name and avatar
    require_once __DIR__ . '/../core/birkly_users.php';
    $userData = birkly_load_user_account_for_session($currentUser);
    if ($userData) {
        // Merge session data with stored data
        $currentUser = array_merge($currentUser, $userData);
    }

    // Don't send password hash
    unset($currentUser['password']);

    // Get current language and available languages for language selector
    $currentLanguage = getCurrentLanguage();
    $availableLanguages = [];
    if (class_exists('I18n')) {
        $langs = I18n::getAvailableLanguages();
        foreach ($langs as $code => $info) {
            $availableLanguages[] = [
                'code' => $code,
                'name' => $info['name'] ?? $code,
                'native_name' => $info['native_name'] ?? $code,
                'flag' => $info['flag'] ?? ''
            ];
        }
    }

    // Get notification preferences (defaults if not set)
    $notificationPreferences = $currentUser['notification_preferences'] ?? [
        'entry_published' => true,
        'entry_review' => true,
        'entry_commented' => true,
        'invitation_accepted' => true,
        'role_changed' => true,
        'system_updates' => true,
        'security_events' => ($currentUser['role'] ?? 'viewer') === 'admin'
    ];
    $currentUser['notification_preferences'] = $notificationPreferences;

    return [
        'user' => $currentUser,
        'current_language' => $currentLanguage,
        'available_languages' => $availableLanguages
    ];
}

function getTemplatesData() {
    $templatesPath = __DIR__ . '/../templates';
    $templates = [];
    
    // Create templates directory if it doesn't exist
    if (!is_dir($templatesPath)) {
        mkdir($templatesPath, 0755, true);
    }
    
    // Get template files
    $templateFiles = glob($templatesPath . '/*.html');
    foreach ($templateFiles as $file) {
        $filename = basename($file, '.html');
        $content = file_get_contents($file);
        $stat = stat($file);
        
        $templates[] = [
            'id' => $filename,
            'name' => ucwords(str_replace(['-', '_'], ' ', $filename)),
            'slug' => $filename,
            'description' => 'Template for ' . $filename,
            'type' => 'custom',
            'content' => $content,
            'created_at' => date('Y-m-d H:i:s', $stat['ctime']),
            'updated_at' => date('Y-m-d H:i:s', $stat['mtime'])
        ];
    }
    
    // Add default templates if none exist
    if (empty($templates)) {
        $defaultTemplates = [
            [
                'id' => 'home',
                'name' => 'Homepage Template',
                'slug' => 'home',
                'description' => 'Default homepage template',
                'type' => 'home',
                'content' => getDefaultHomeTemplate(),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'id' => 'page',
                'name' => 'Page Template',
                'slug' => 'page',
                'description' => 'Default page template',
                'type' => 'page',
                'content' => getDefaultPageTemplate(),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'id' => 'post',
                'name' => 'Post Template',
                'slug' => 'post',
                'description' => 'Default blog post template',
                'type' => 'post',
                'content' => getDefaultPostTemplate(),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]
        ];
        
        // Create default template files
        foreach ($defaultTemplates as $template) {
            $filePath = $templatesPath . '/' . $template['slug'] . '.html';
            file_put_contents($filePath, $template['content']);
        }
        
        $templates = $defaultTemplates;
    }
    
    return [
        'templates' => $templates
    ];
}

function createTemplate() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['status' => 'error', 'message' => 'Method not allowed'];
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $name = $input['name'] ?? '';
    $slug = $input['slug'] ?? '';
    $description = $input['description'] ?? '';
    $type = $input['type'] ?? 'custom';
    
    if (empty($name) || empty($slug)) {
        return ['status' => 'error', 'message' => 'Name and slug are required'];
    }
    
    $templatesPath = __DIR__ . '/../templates';
    if (!is_dir($templatesPath)) {
        mkdir($templatesPath, 0755, true);
    }
    
    $filePath = $templatesPath . '/' . $slug . '.html';
    
    if (file_exists($filePath)) {
        return ['status' => 'error', 'message' => 'Template with this slug already exists'];
    }
    
    $content = getDefaultTemplateContent($type);
    
    if (file_put_contents($filePath, $content) === false) {
        return ['status' => 'error', 'message' => 'Failed to create template file'];
    }
    
    return [
        'status' => 'ok',
        'message' => 'Template created successfully',
        'data' => [
            'id' => $slug,
            'name' => $name,
            'slug' => $slug
        ]
    ];
}

function saveTemplate() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['status' => 'error', 'message' => 'Method not allowed'];
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $content = $input['content'] ?? '';
    $templateId = $input['template_id'] ?? '';
    
    if (empty($templateId)) {
        return ['status' => 'error', 'message' => 'Template ID is required'];
    }
    
    $templatesPath = __DIR__ . '/../templates';
    $filePath = $templatesPath . '/' . $templateId . '.html';
    
    if (!file_exists($filePath)) {
        return ['status' => 'error', 'message' => 'Template not found'];
    }
    
    if (file_put_contents($filePath, $content) === false) {
        return ['status' => 'error', 'message' => 'Failed to save template'];
    }
    
    return [
        'status' => 'ok',
        'message' => 'Template saved successfully'
    ];
}

function deleteTemplate() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['status' => 'error', 'message' => 'Method not allowed'];
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $templateId = $input['template_id'] ?? '';
    
    if (empty($templateId)) {
        return ['status' => 'error', 'message' => 'Template ID is required'];
    }
    
    $templatesPath = __DIR__ . '/../templates';
    $filePath = $templatesPath . '/' . $templateId . '.html';
    
    if (!file_exists($filePath)) {
        return ['status' => 'error', 'message' => 'Template not found'];
    }
    
    if (!unlink($filePath)) {
        return ['status' => 'error', 'message' => 'Failed to delete template'];
    }
    
    return [
        'status' => 'ok',
        'message' => 'Template deleted successfully'
    ];
}

function getDefaultTemplateContent($type) {
    switch ($type) {
        case 'home':
            return getDefaultHomeTemplate();
        case 'page':
            return getDefaultPageTemplate();
        case 'post':
            return getDefaultPostTemplate();
        case 'list':
            return getDefaultListTemplate();
        default:
            return getDefaultPageTemplate();
    }
}

function getDefaultHomeTemplate() {
    return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ site.title }}</title>
    <meta name="description" content="{{ site.description }}">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; line-height: 1.6; margin: 0; padding: 0; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        .header { background: #f8f9fa; padding: 1rem 0; border-bottom: 1px solid #e9ecef; }
        .nav { display: flex; gap: 2rem; }
        .nav a { text-decoration: none; color: #333; }
        .nav a:hover { color: #007bff; }
        .hero { padding: 4rem 0; text-align: center; }
        .blog-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; margin: 3rem 0; }
        .blog-card { border: 1px solid #e9ecef; border-radius: 8px; padding: 1.5rem; }
        .blog-card h3 { margin-top: 0; }
        .blog-card .date { color: #6c757d; font-size: 0.9rem; }
        .footer { background: #f8f9fa; padding: 2rem 0; margin-top: 3rem; text-align: center; }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="nav">
                <a href="/">Home</a>
                <a href="/blog">Blog</a>
                <a href="/about">About</a>
            </nav>
        </div>
    </header>
    
    <main>
        <div class="hero">
            <div class="container">
                <h1>{{ page.title }}</h1>
                <div>{{ page.content }}</div>
            </div>
        </div>
        
        <div class="container">
            <h2>Latest Blog Posts</h2>
            <div class="blog-grid">
                {% for entry in blog_entries %}
                    <article class="blog-card">
                        <h3><a href="/blog/{{ entry.slug }}">{{ entry.title }}</a></h3>
                        <div class="date">{{ entry.created_at }}</div>
                        <p>{{ entry.excerpt }}</p>
                    </article>
                {% endfor %}
            </div>
        </div>
    </main>
    
    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 {{ site.title }}. Built with Birkly.</p>
        </div>
    </footer>
</body>
</html>';
}

function getDefaultPageTemplate() {
    return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ page.title }} - {{ site.title }}</title>
    <meta name="description" content="{{ page.meta_description }}">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; line-height: 1.6; margin: 0; padding: 0; }
        .container { max-width: 800px; margin: 0 auto; padding: 0 20px; }
        .header { background: #f8f9fa; padding: 1rem 0; border-bottom: 1px solid #e9ecef; }
        .nav { display: flex; gap: 2rem; }
        .nav a { text-decoration: none; color: #333; }
        .nav a:hover { color: #007bff; }
        .content { padding: 3rem 0; }
        .footer { background: #f8f9fa; padding: 2rem 0; margin-top: 3rem; text-align: center; }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="nav">
                <a href="/">Home</a>
                <a href="/blog">Blog</a>
                <a href="/about">About</a>
            </nav>
        </div>
    </header>
    
    <main class="content">
        <div class="container">
            <h1>{{ page.title }}</h1>
            <div>{{ page.content }}</div>
        </div>
    </main>
    
    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 {{ site.title }}. Built with Birkly.</p>
        </div>
    </footer>
</body>
</html>';
}

function getDefaultPostTemplate() {
    return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ entry.title }} - {{ site.title }}</title>
    <meta name="description" content="{{ entry.meta_description }}">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; line-height: 1.6; margin: 0; padding: 0; }
        .container { max-width: 800px; margin: 0 auto; padding: 0 20px; }
        .header { background: #f8f9fa; padding: 1rem 0; border-bottom: 1px solid #e9ecef; }
        .nav { display: flex; gap: 2rem; }
        .nav a { text-decoration: none; color: #333; }
        .nav a:hover { color: #007bff; }
        .content { padding: 3rem 0; }
        .post-meta { color: #6c757d; font-size: 0.9rem; margin-bottom: 2rem; }
        .footer { background: #f8f9fa; padding: 2rem 0; margin-top: 3rem; text-align: center; }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="nav">
                <a href="/">Home</a>
                <a href="/blog">Blog</a>
                <a href="/about">About</a>
            </nav>
        </div>
    </header>
    
    <main class="content">
        <div class="container">
            <h1>{{ entry.title }}</h1>
            <div class="post-meta">
                Published on {{ entry.created_at }}
                {% if entry.author %} by {{ entry.author }}{% endif %}
            </div>
            <div>{{ entry.content }}</div>
        </div>
    </main>
    
    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 {{ site.title }}. Built with Birkly.</p>
        </div>
    </footer>
</body>
</html>';
}

function getDefaultListTemplate() {
    return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ page.title }} - {{ site.title }}</title>
    <meta name="description" content="{{ page.meta_description }}">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; line-height: 1.6; margin: 0; padding: 0; }
        .container { max-width: 800px; margin: 0 auto; padding: 0 20px; }
        .header { background: #f8f9fa; padding: 1rem 0; border-bottom: 1px solid #e9ecef; }
        .nav { display: flex; gap: 2rem; }
        .nav a { text-decoration: none; color: #333; }
        .nav a:hover { color: #007bff; }
        .content { padding: 3rem 0; }
        .list-item { border-bottom: 1px solid #e9ecef; padding: 2rem 0; }
        .list-item h2 { margin-top: 0; }
        .list-item .date { color: #6c757d; font-size: 0.9rem; }
        .footer { background: #f8f9fa; padding: 2rem 0; margin-top: 3rem; text-align: center; }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="nav">
                <a href="/">Home</a>
                <a href="/blog">Blog</a>
                <a href="/about">About</a>
            </nav>
        </div>
    </header>
    
    <main class="content">
        <div class="container">
            <h1>{{ page.title }}</h1>
            <div class="list">
                {% for entry in entries %}
                    <article class="list-item">
                        <h2><a href="/{{ collection.slug }}/{{ entry.slug }}">{{ entry.title }}</a></h2>
                        <div class="date">{{ entry.created_at }}</div>
                        <p>{{ entry.excerpt }}</p>
                    </article>
                {% endfor %}
            </div>
        </div>
    </main>
    
    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 {{ site.title }}. Built with Birkly.</p>
        </div>
    </footer>
</body>
</html>';
}

function getUsersData() {
    require_once __DIR__ . '/../core/birkly_users.php';
    require_once __DIR__ . '/../core/mcp_oauth.php';

    $users = birkly_list_human_user_records(true);
    foreach ($users as &$user) {
        $userId = (string) ($user['id'] ?? '');
        $user['mcp_sessions'] = $userId !== '' ? birkly_mcp_oauth_list_sessions($userId) : [];
        $user['byoai_connections'] = $userId !== '' ? birkly_list_byoai_connections_for_user($userId) : [];
    }
    unset($user);

    return [
        'users' => $users,
        'roles' => ['admin', 'editor', 'viewer']
    ];
}

function getRolesData() {
    require_once __DIR__ . '/../core/roles.php';
    require_once __DIR__ . '/../core/birkly_users.php';
    $roles = birkly_ensure_default_roles();

    return [
        'roles' => $roles,
        'users' => birkly_list_human_user_records(true),
    ];
}

function getOnboardingData() {
    // Get current user
    if (session_status() === PHP_SESSION_NONE) {
        require_once __DIR__ . '/session.php';
        haru_secure_session_start();
    }
    $userId = $_SESSION['user']['email'] ?? $_SESSION['user']['id'] ?? 'unknown';
    
    // Include onboarding functions
    require_once __DIR__ . '/onboarding.php';
    
    // Get onboarding status
    $status = getOnboardingStatus($userId);
    
    // Check if demo content exists (it ships with the CMS by default)
    $demoContentInfo = [
        'has_demo_content' => false,
        'collections' => [],
        'has_demo_website' => false
    ];
    
    // Check for demo blog collection
    $blogCollectionPath = __DIR__ . '/../content/collections/blog';
    if (is_dir($blogCollectionPath)) {
        $collectionFile = "$blogCollectionPath/collection.json";
        if (file_exists($collectionFile)) {
            $collectionData = json_decode(file_get_contents($collectionFile), true);
            if ($collectionData) {
                $entriesPath = "$blogCollectionPath/entries";
                $entryCount = 0;
                if (is_dir($entriesPath)) {
                    $entryFiles = glob("$entriesPath/*.json");
                    $entryCount = count($entryFiles);
                }
                
                $demoContentInfo['has_demo_content'] = true;
                $demoContentInfo['collections'][] = [
                    'slug' => 'blog',
                    'name' => $collectionData['title'] ?? 'Blog',
                    'entries_count' => $entryCount
                ];
            }
        }
    }
    
    // Check for demo website
    $demoBlogPath = __DIR__ . '/../project';
    $demoContentInfo['has_demo_website'] = is_dir($demoBlogPath);
    
    return [
        'status' => $status,
        'demo_content' => $demoContentInfo,
        'install_path' => getSiteOnboardingSettings()['install_path'] ?? ($_GET['install'] ?? null),
        'resume_step' => $status['last_step'] ?? null,
        'use_case' => $status['use_case'] ?? null,
        'user' => [
            'name' => $_SESSION['user']['name'] ?? $_SESSION['user']['email'] ?? 'User',
            'email' => $_SESSION['user']['email'] ?? ''
        ]
    ];
}

/**
 * Get sub-collection editor data
 * Similar to collection editor but for sub-collections
 */
/**
 * Get entry detail data (main entry + sub-entries list)
 */
function getEntryDetailData() {
    $collectionSlug = $_GET['collection'] ?? null;
    $entryId = $_GET['entry'] ?? null;
    $subCollectionSlug = $_GET['sub_collection'] ?? null;
    $subEntryId = $_GET['sub_entry'] ?? null;
    $parentSubCollectionSlug = $_GET['parent_sub_collection'] ?? null;
    $parentSubEntryId = $_GET['parent_sub_entry'] ?? null;
    
    // Check if viewing a sub-entry detail page
    $isSubEntryDetail = !empty($subCollectionSlug) && !empty($subEntryId);
    // Check if viewing a nested sub-entry detail page
    $isNestedSubEntryDetail = $isSubEntryDetail && !empty($parentSubCollectionSlug) && !empty($parentSubEntryId);
    
    if (!$collectionSlug || !$entryId) {
        return ['error' => 'Collection and entry parameters required'];
    }
    
    // Get collection data
    $collectionFile = __DIR__ . "/../content/collections/$collectionSlug/collection.json";
    if (!file_exists($collectionFile)) {
        return ['error' => 'Collection not found'];
    }
    
    $collectionData = json_decode(file_get_contents($collectionFile), true);
    $collectionData['slug'] = $collectionSlug;
    
    // Get entry data - either sub-entry or main entry
    if ($isSubEntryDetail) {
        // Load sub-entry data
        require_once __DIR__ . '/../core/sub_entries.php';
        
        if ($isNestedSubEntryDetail) {
            // This is a nested sub-entry - load from nested path
            if (!function_exists('get_storage')) {
                require_once __DIR__ . '/../core/storage/StorageFactory.php';
            }
            try {
                $storage = get_storage();
                $nestedEntryPath = "collections/{$collectionSlug}/entries/{$entryId}/sub_entries/{$parentSubCollectionSlug}/{$parentSubEntryId}/sub_entries/{$subCollectionSlug}/{$subEntryId}.json";
                error_log("getEntryDetailData: Loading nested sub-entry from: {$nestedEntryPath}");
                $entry = $storage->read($nestedEntryPath);
            } catch (Exception $e) {
                error_log("getEntryDetailData: Error loading nested sub-entry: " . $e->getMessage());
                $entry = null;
            }
        } else {
            // First-level sub-entry
            $entry = getSubEntry($collectionSlug, $entryId, $subCollectionSlug, $subEntryId);
        }
        
        if (!$entry) {
            return ['error' => 'Sub-entry not found'];
        }
        $entry['id'] = $subEntryId;
        $entry['_is_sub_entry'] = true;
        $entry['_parent_entry_id'] = $entryId; // This is the parent entry ID (main entry)
        $entry['_sub_collection'] = $subCollectionSlug; // This is the sub-collection of the sub-entry we're viewing
        
        if ($isNestedSubEntryDetail) {
            // Store the parent sub-collection and sub-entry ID for nested sub-entry creation
            // When viewing a nested sub-entry detail page, the parent sub-entry info comes from URL
            $entry['_parent_sub_collection'] = $parentSubCollectionSlug;
            $entry['_parent_sub_entry_id'] = $parentSubEntryId;
        } else {
            // When viewing a first-level sub-entry detail page, this sub-entry becomes the parent for nested sub-entries
            $entry['_parent_sub_collection'] = $subCollectionSlug;
            $entry['_parent_sub_entry_id'] = $subEntryId;
        }
        
        // Get parent entry for context
        $parentEntryFile = __DIR__ . "/../content/collections/$collectionSlug/entries/$entryId.json";
        if (file_exists($parentEntryFile)) {
            $parentEntry = json_decode(file_get_contents($parentEntryFile), true);
            $entry['_parent_entry_title'] = $parentEntry['title'] ?? 'Parent Entry';
        }

        if ($isNestedSubEntryDetail) {
            $parentSubEntry = getSubEntry($collectionSlug, $entryId, $parentSubCollectionSlug, $parentSubEntryId);
            if ($parentSubEntry) {
                $entry['_parent_sub_entry_title'] = $parentSubEntry['title'] ?? __('admin.untitled');
            }
        }
        
        // Get sub-collection config for fields (handle nested sub-collections)
        // For nested sub-entries, we need to determine the parent sub-collection
        // The subCollectionSlug could be a nested one, so we search recursively
        $subCollection = getSubCollectionConfig($collectionSlug, $subCollectionSlug);
        if (!$subCollection) {
            // Try to find it as a nested sub-collection
            $allSubCollections = getSubCollections($collectionSlug);
            foreach ($allSubCollections as $parentSubCol) {
                $nested = $parentSubCol['sub_collections'] ?? [];
                foreach ($nested as $nestedSubCol) {
                    if (($nestedSubCol['slug'] ?? '') === $subCollectionSlug) {
                        $subCollection = $nestedSubCol;
                        break 2;
                    }
                }
            }
        }
        $fields = $subCollection['fields'] ?? $subCollection['schema']['fields'] ?? [];
    } else {
        // Load main entry data
        $entryFile = __DIR__ . "/../content/collections/$collectionSlug/entries/$entryId.json";
        if (!file_exists($entryFile)) {
            return ['error' => 'Entry not found'];
        }
        $entry = json_decode(file_get_contents($entryFile), true);
        $entry['id'] = $entryId;
        
        // Get fields from schema
        $fields = [];
        $schemaFile = __DIR__ . "/../content/collections/$collectionSlug/schema.json";
        if (file_exists($schemaFile)) {
            $schemaData = json_decode(file_get_contents($schemaFile), true);
            if (isset($schemaData['fields']) && is_array($schemaData['fields'])) {
                $fields = $schemaData['fields'];
            }
        }
    }
    
    // Process entry like in getEntriesData() - render fields for table display
    $entryWithCells = processEntryForList($entry, $fields, $collectionData);
    
    // Get sub-collections - either from main collection or from sub-collection (for nested)
    require_once __DIR__ . '/../core/sub_entries.php';
    
    if ($isSubEntryDetail) {
        // Get nested sub-collections from the sub-collection config
        if ($isNestedSubEntryDetail) {
            // For nested sub-entries, get the sub-collection config for the sub-entry we're viewing
            // This sub-collection's nested sub-collections are what we want to show
            $subCollection = getSubCollectionConfig($collectionSlug, $subCollectionSlug);
            if (!$subCollection) {
                // Try to find it recursively
                $allSubCollections = getSubCollections($collectionSlug);
                foreach ($allSubCollections as $parentSubCol) {
                    $nested = $parentSubCol['sub_collections'] ?? [];
                    foreach ($nested as $nestedSubCol) {
                        if (($nestedSubCol['slug'] ?? '') === $subCollectionSlug) {
                            $subCollection = $nestedSubCol;
                            break 2;
                        }
                    }
                }
            }
            $subCollections = $subCollection['sub_collections'] ?? [];
        } else {
            // For first-level sub-entries, get nested sub-collections from the sub-collection config
            $subCollection = getSubCollectionConfig($collectionSlug, $subCollectionSlug);
            if (!$subCollection) {
                // Try to find it recursively
                $allSubCollections = getSubCollections($collectionSlug);
                foreach ($allSubCollections as $parentSubCol) {
                    if (($parentSubCol['slug'] ?? '') === $subCollectionSlug) {
                        $subCollection = $parentSubCol;
                        break;
                    }
                    $nested = $parentSubCol['sub_collections'] ?? [];
                    foreach ($nested as $nestedSubCol) {
                        if (($nestedSubCol['slug'] ?? '') === $subCollectionSlug) {
                            $subCollection = $nestedSubCol;
                            break 2;
                        }
                    }
                }
            }
            $subCollections = $subCollection['sub_collections'] ?? [];
        }
    } else {
        // Get top-level sub-collections
        $subCollections = $collectionData['sub_collections'] ?? [];
    }
    
    /**
     * Recursively process sub-collections and their nested sub-collections
     */
    function processSubCollectionRecursive($subCollection, $collectionSlug, $parentEntryId, $collectionData, $parentSubCollectionSlug = null, $parentSubEntryId = null) {
        $subCollectionSlug = $subCollection['slug'] ?? '';
        if (empty($subCollectionSlug)) {
            return null;
        }
        
        $result = [
            'sub_collection' => $subCollection,
            'entries' => [],
            'fields' => [],
            'nested_sub_collections' => []
        ];
        
        try {
            // Get sub-entries for this sub-collection
            if ($parentSubCollectionSlug && $parentSubEntryId) {
                // Nested sub-entry - get from nested path directly
                $nestedSubEntryPath = "collections/{$collectionSlug}/entries/{$parentEntryId}/sub_entries/{$parentSubCollectionSlug}/{$parentSubEntryId}/sub_entries/{$subCollectionSlug}";
                if (!function_exists('get_storage')) {
                    require_once __DIR__ . '/../core/storage/StorageFactory.php';
                }
                try {
                    $storage = get_storage();
                    $files = $storage->list($nestedSubEntryPath);
                    $subEntries = [];
                    foreach ($files as $file) {
                        if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                            // Skip dotfiles like .order.json
                            if (strpos($file, '.') === 0) {
                                continue;
                            }
                            
                            // Use full path for reading
                            $fullPath = "{$nestedSubEntryPath}/{$file}";
                            $entryData = $storage->read($fullPath);
                            if ($entryData) {
                                // Ensure ID is set from filename
                                $entryData['id'] = basename($file, '.json');
                                $subEntries[] = $entryData;
                            }
                        }
                    }
                    
                    // Apply custom order from .order.json if it exists
                    try {
                        $orderFilePath = "{$nestedSubEntryPath}/.order.json";
                        $orderData = $storage->read($orderFilePath);
                        if ($orderData && isset($orderData['order']) && is_array($orderData['order'])) {
                            $customOrder = $orderData['order'];
                            
                            // Create a map of entry IDs to entries
                            $entryMap = [];
                            foreach ($subEntries as $entry) {
                                $entryMap[$entry['id']] = $entry;
                            }
                            
                            // Rebuild entries array in the custom order
                            $orderedEntries = [];
                            foreach ($customOrder as $entryId) {
                                if (isset($entryMap[$entryId])) {
                                    $orderedEntries[] = $entryMap[$entryId];
                                    unset($entryMap[$entryId]); // Remove from map
                                }
                            }
                            
                            // Add any entries that weren't in the order file (new entries)
                            foreach ($entryMap as $entry) {
                                $orderedEntries[] = $entry;
                            }
                            
                            $subEntries = $orderedEntries;
                        }
                    } catch (Exception $orderEx) {
                        // If .order.json doesn't exist or can't be read, just use the default order
                    }
                } catch (Exception $e) {
                    error_log("Error loading nested sub-entries: " . $e->getMessage());
                    $subEntries = [];
                }
            } else {
                // Top-level sub-entry
                $subEntries = getSubEntries($collectionSlug, $parentEntryId, $subCollectionSlug);
            }
            
            // Get sub-collection fields
            $subFields = $subCollection['fields'] ?? $subCollection['schema']['fields'] ?? [];
            
            // Normalize sub-field definitions
            foreach ($subFields as $idx => $subField) {
                if (empty($subField['label']) && !empty($subField['name'])) {
                    $subFields[$idx]['label'] = ucfirst(str_replace('_', ' ', $subField['name']));
                }
                if (empty($subField['name']) && !empty($subField['label'])) {
                    $subFields[$idx]['name'] = strtolower(trim(preg_replace('/[^A-Za-z0-9_]+/', '_', $subField['label'])));
                }
                if (empty($subFields[$idx]['name']) && empty($subFields[$idx]['label'])) {
                    unset($subFields[$idx]);
                    continue;
                }
                if (empty($subFields[$idx]['type'])) {
                    unset($subFields[$idx]);
                    continue;
                }
            }
            $subFields = array_values($subFields);
            
            // Calculate field widths
            $subTypeWidths = [];
            $subTypeMinWidths = [];
            foreach ($subFields as $subField) {
                $type = $subField['type'] ?? null;
                if (!$type || isset($subTypeWidths[$type])) continue;
                $width = null;
                $minWidth = null;
                try {
                    $ftName = ucfirst(strtolower($type));
                    $class = "Birkly\\FieldTypes\\$ftName\\$ftName";
                    $file = __DIR__ . "/../fieldtypes/$ftName/$ftName.php";
                    if (!class_exists($class) && file_exists($file)) {
                        require_once $file;
                    }
                    if (class_exists($class)) {
                        $fieldName = $subField['name'] ?? '';
                        $ft = new $class(['name' => $fieldName]);
                        if (method_exists($ft, 'getListColumnWidth')) {
                            $width = $ft->getListColumnWidth();
                        }
                        if (method_exists($ft, 'getTableMinWidth')) {
                            $minWidth = $ft->getTableMinWidth();
                        }
                    }
                } catch (\Throwable $e) {
                    $width = null;
                    $minWidth = null;
                }
                $subTypeWidths[$type] = $width ?: '240px';
                $subTypeMinWidths[$type] = $minWidth ?: ($width ?: '240px');
            }
            
            foreach ($subFields as $idx => $subField) {
                $ftype = $subField['type'] ?? '';
                $subFields[$idx]['width'] = $subTypeWidths[$ftype] ?? '240px';
                $subFields[$idx]['min_width'] = $subTypeMinWidths[$ftype] ?? ($subTypeWidths[$ftype] ?? '240px');
            }
            
            // Process sub-entries for table display
            $processedSubEntries = [];
            foreach ($subEntries as $subEntry) {
                $processedSubEntries[] = processEntryForList($subEntry, $subFields, $collectionData);
            }
            
            $result['entries'] = $processedSubEntries;
            $result['fields'] = $subFields;
            
            // Note: Nested sub-collections are handled per sub-entry
            // When a sub-entry is clicked, it will show its own entry-detail page with nested sub-collections
            // We don't need to process them here as they'll be loaded when viewing that sub-entry's detail page
            
        } catch (Exception $e) {
            error_log("Error processing sub-collection {$subCollectionSlug}: " . $e->getMessage());
        }
        
        return $result;
    }
    
    // Get sub-entries for each sub-collection and process them (recursively handles nested sub-collections)
    $subEntriesByCollection = [];
    foreach ($subCollections as $subCollection) {
        if ($isNestedSubEntryDetail) {
            // For nested sub-entry detail pages, pass the parent sub-collection and sub-entry ID from URL
            $processed = processSubCollectionRecursive($subCollection, $collectionSlug, $entryId, $collectionData, $subCollectionSlug, $subEntryId);
        } elseif ($isSubEntryDetail) {
            // For first-level sub-entry detail pages, pass the sub-collection and sub-entry ID
            $processed = processSubCollectionRecursive($subCollection, $collectionSlug, $entryId, $collectionData, $subCollectionSlug, $subEntryId);
        } else {
            // For top-level sub-entries
            $processed = processSubCollectionRecursive($subCollection, $collectionSlug, $entryId, $collectionData);
        }
        if ($processed) {
            $subCollectionSlugLoop = $subCollection['slug'] ?? '';
            $subEntriesByCollection[$subCollectionSlugLoop] = $processed;
        }
    }
    
    $detailPayload = [
        'collection' => $collectionData,
        'entry' => $entryWithCells,
        'fields' => $fields,
        'sub_collections' => $subCollections, // Sub-collection configs for the template
        'sub_entries_by_collection' => $subEntriesByCollection,
        'is_sub_entry' => $isSubEntryDetail,
        'is_sub_entry_detail' => $isSubEntryDetail,
        'is_nested_sub_entry' => $isNestedSubEntryDetail ?? false,
        'is_nested_sub_entry_detail' => $isNestedSubEntryDetail ?? false,
        'sub_collection' => $subCollectionSlug, // This is the sub-collection of the sub-entry we're viewing (e.g., "comments")
        'sub_collection_slug' => $subCollectionSlug,
        'sub_entry_id' => $subEntryId,
        'parent_entry_id' => $entryId,
        'parent_sub_collection' => $parentSubCollectionSlug ?? null,
        'parent_sub_collection_slug' => $parentSubCollectionSlug ?? null,
        'parent_sub_entry_id' => $parentSubEntryId ?? null,
    ];

    require_once __DIR__ . '/../admin/template-helpers.php';
    $detailPayload['breadcrumbs'] = build_entry_breadcrumbs($detailPayload);

    return $detailPayload;
}

// ===== NEW PAGE DATA FUNCTIONS =====

function getPluginsData() {
    require_once __DIR__ . '/../core/build_info.php';
    $tab = isset($_GET['tab']) ? (string) $_GET['tab'] : 'discover';
    if ($tab === 'marketplace') {
        $tab = 'discover';
    }
    $hubTabs = ['discover', 'installed', 'updates', 'licenses', 'upload'];
    if (!in_array($tab, $hubTabs, true)) {
        $tab = 'discover';
    }

    $titles = [
        'discover' => __('admin.hub_tab_discover'),
        'installed' => __('admin.hub_tab_installed'),
        'updates' => __('admin.hub_tab_updates'),
        'licenses' => __('admin.hub_tab_licenses'),
        'upload' => __('admin.hub_tab_upload'),
    ];

    return [
        'active_tab' => $tab,
        'page_title' => $titles[$tab] ?? __('admin.marketplace'),
        'discover_title' => $titles['discover'],
        'marketplace_title' => $titles['discover'],
        'installed_title' => $titles['installed'],
        'updates_title' => $titles['updates'],
        'licenses_title' => $titles['licenses'],
        'upload_title' => $titles['upload'],
        'plugins_css_url' => birkly_admin_asset_url('/admin/css/pages/plugins.css'),
        'plugins_page_js_url' => birkly_admin_asset_url('/admin/js/plugins-page.js'),
        'i18n' => [
            'no_plugins_installed' => __('admin.no_plugins_installed'),
            'no_plugins_marketplace_hint' => __('admin.no_plugins_marketplace_hint'),
            'no_site_packs_installed' => __('admin.no_site_packs_installed'),
            'no_site_packs_hint' => __('admin.no_site_packs_hint'),
            'browse_marketplace' => __('admin.browse_marketplace'),
            'upload_site_pack' => __('admin.upload_site_pack'),
            'price_free' => __('admin.price_free'),
            'price_from' => __('admin.price_from'),
            'install' => __('admin.install'),
            'install_with_license' => __('admin.install_with_license'),
            'install_from_marketplace' => __('admin.install_from_marketplace'),
            'enter_license_key' => __('admin.enter_license_key'),
            'confirm_free_install' => __('admin.confirm_free_install'),
            'install_success' => __('admin.install_plugin_success'),
            'manual_install_unavailable' => __('admin.manual_install_unavailable'),
            'loading_marketplace' => __('admin.loading_marketplace'),
            'marketplace_type_plugin' => __('admin.marketplace_type_plugin'),
            'marketplace_type_theme' => __('admin.marketplace_type_theme'),
            'marketplace_type_template' => __('admin.marketplace_type_template'),
            'marketplace_type_fieldtype' => __('admin.marketplace_type_fieldtype'),
            'marketplace_filter_category_all' => __('admin.marketplace_filter_category_all'),
            'marketplace_filter_all_categories' => __('admin.marketplace_filter_all_categories'),
            'marketplace_filter_all_types' => __('admin.marketplace_filter_all_types'),
            'marketplace_no_reviews' => __('admin.marketplace_no_reviews'),
            'marketplace_downloads' => __('admin.marketplace_downloads'),
            'marketplace_offers_count' => __('admin.marketplace_offers_count'),
            'buy' => __('admin.marketplace_buy'),
            'marketplace_pricing' => __('admin.marketplace_pricing'),
            'marketplace_pricing_plan' => __('admin.marketplace_pricing_plan'),
            'marketplace_pricing_price' => __('admin.marketplace_pricing_price'),
            'marketplace_pricing_action' => __('admin.marketplace_pricing_action'),
            'marketplace_pricing_one_time' => __('admin.marketplace_pricing_one_time'),
            'marketplace_pricing_monthly' => __('admin.marketplace_pricing_monthly'),
            'marketplace_pricing_yearly' => __('admin.marketplace_pricing_yearly'),
            'marketplace_pricing_per_month' => __('admin.marketplace_pricing_per_month'),
            'marketplace_pricing_billed_yearly' => __('admin.marketplace_pricing_billed_yearly'),
            'marketplace_pricing_discounts' => __('admin.marketplace_pricing_discounts'),
            'installed' => __('admin.marketplace_installed'),
            'marketplace_coming_soon' => __('admin.marketplace_coming_soon'),
            'marketplace_coming_soon_hint' => __('admin.marketplace_coming_soon_hint'),
            'marketplace_coming_soon_install_blocked' => __('admin.marketplace_coming_soon_install_blocked'),
            'update' => __('admin.marketplace_update'),
            'back_to_marketplace' => __('admin.marketplace_back'),
            'description' => __('admin.marketplace_description'),
            'compatibility' => __('admin.marketplace_compatibility'),
            'releases' => __('admin.marketplace_releases'),
            'provider' => __('admin.marketplace_provider'),
            'offer_by_provider' => __('admin.marketplace_offer_by_provider'),
            'view_provider_profile' => __('admin.marketplace_view_provider_profile'),
            'back_to_offer' => __('admin.marketplace_back_to_offer'),
            'marketplace_offer_tabs' => __('admin.marketplace_offer_tabs'),
            'marketplace_tab_offer' => __('admin.marketplace_tab_offer'),
            'marketplace_tab_pricing' => __('admin.marketplace_tab_pricing'),
            'marketplace_tab_reviews' => __('admin.marketplace_tab_reviews'),
            'marketplace_tab_versions' => __('admin.marketplace_tab_versions'),
            'marketplace_reviews_count' => __('admin.marketplace_reviews_count'),
            'marketplace_review_verified' => __('admin.marketplace_review_verified'),
            'marketplace_provider_reply' => __('admin.marketplace_provider_reply'),
            'marketplace_no_versions' => __('admin.marketplace_no_versions'),
            'marketplace_no_changelog' => __('admin.marketplace_no_changelog'),
            'marketplace_latest_version' => __('admin.marketplace_latest_version'),
            'marketplace_pricing_no_discount' => __('admin.marketplace_pricing_no_discount'),
            'marketplace_pricing_select_discount' => __('admin.marketplace_pricing_select_discount'),
            'marketplace_pricing_discount_checkout_hint' => __('admin.marketplace_pricing_discount_checkout_hint'),
            'marketplace_pricing_licenses' => __('admin.marketplace_pricing_licenses'),
            'rate_plugin' => __('admin.rate_plugin'),
            'your_review' => __('admin.your_review'),
            'view_details' => __('admin.marketplace_view_details'),
            'no_description' => __('admin.marketplace_no_description'),
            'requires_birkly' => __('admin.marketplace_requires_birkly'),
            'requires_php' => __('admin.marketplace_requires_php'),
            'changelog' => __('admin.marketplace_changelog'),
            'hub_pack_has_project' => __('admin.hub_pack_has_project'),
            'hub_pack_collections' => __('admin.hub_pack_collections'),
            'hub_pack_replace_all' => __('admin.hub_pack_replace_all'),
            'hub_pack_replaced' => __('admin.hub_pack_replaced'),
            'hub_updates_none' => __('admin.hub_updates_none'),
            'hub_updates_none_hint' => __('admin.hub_updates_none_hint'),
            'hub_licenses_none' => __('admin.hub_licenses_none'),
            'hub_licenses_none_hint' => __('admin.hub_licenses_none_hint'),
            'hub_license_activated' => __('admin.hub_license_activated'),
            'hub_license_install_now' => __('admin.hub_license_install_now'),
            'hub_license_install_pending' => __('admin.hub_license_install_pending'),
            'install_now' => __('admin.install_now'),
            'hub_revoke_license' => __('admin.hub_revoke_license'),
            'hub_revoke_license_hint' => __('admin.hub_revoke_license_hint'),
            'hub_revoke_license_confirm' => __('admin.hub_revoke_license_confirm'),
            'hub_license_revoked' => __('admin.hub_license_revoked'),
            'enter_license_key_revoke' => __('admin.enter_license_key_revoke'),
            'pack_replace_all_confirm' => __('admin.pack_replace_all_confirm'),
            'cancel' => __('admin.cancel'),
            'site_pack_upload_success' => __('admin.site_pack_upload_success'),
            'select_site_pack_zip' => __('admin.select_site_pack_zip'),
            'site_pack_install_success' => __('admin.site_pack_install_success'),
            'confirm_required_plugins' => __('admin.confirm_required_plugins'),
        ],
    ];
}

function getWebhooksData() {
    $webhooksPath = __DIR__ . '/../data/webhooks.json';
    $webhooks = [];
    
    if (file_exists($webhooksPath)) {
        $webhooks = json_decode(file_get_contents($webhooksPath), true) ?? [];
    }
    
    // Get collections for webhook configuration
    $collectionsPath = __DIR__ . '/../content/collections';
    $collections = [];
    
    if (is_dir($collectionsPath)) {
        $collectionDirs = glob($collectionsPath . '/*', GLOB_ONLYDIR);
        foreach ($collectionDirs as $dir) {
            $collectionName = basename($dir);
            $collectionFile = $dir . '/collection.json';
            
            if (file_exists($collectionFile)) {
                $collectionData = json_decode(file_get_contents($collectionFile), true);
                $collections[] = [
                    'title' => $collectionData['name'] ?? $collectionName,
                    'slug' => $collectionName
                ];
            }
        }
    }
    
    return [
        'webhooks' => $webhooks,
        'collections' => $collections
    ];
}

function getHelpData() {
    $section = $_GET['section'] ?? 'getting-started';
    
    // Validate section name (security)
    $section = preg_replace('/[^a-z0-9_-]/', '', $section);
    
    return [
        'current_section' => $section
    ];
}

function getUpdatesData() {
    $currentVersion = file_exists(__DIR__ . '/../VERSION') 
        ? trim(file_get_contents(__DIR__ . '/../VERSION')) 
        : '1.0.0';
    
    return [
        'current_version' => $currentVersion,
        'latest_version' => $currentVersion, // Would check remote in production
        'updates_available' => false,
        'changelog' => []
    ];
}

function getActivityData() {
    $activityPath = __DIR__ . '/../data/activity.json';
    $activities = [];
    
    if (file_exists($activityPath)) {
        $activities = json_decode(file_get_contents($activityPath), true) ?? [];
    }
    
    return [
        'activities' => $activities
    ];
}

function getAuditLogData() {
    $auditPath = __DIR__ . '/../data/audit.json';
    $auditLogs = [];
    
    if (file_exists($auditPath)) {
        $auditLogs = json_decode(file_get_contents($auditPath), true) ?? [];
    }
    
    return [
        'audit_logs' => $auditLogs
    ];
}

function getFeedbackData() {
    return [
        'items' => [],
        'filters' => [
            'status' => $_GET['status'] ?? '',
            'type' => $_GET['type'] ?? '',
        ],
    ];
}
?> 