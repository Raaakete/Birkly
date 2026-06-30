<?php
// api/dashboard.php - Dashboard statistics API

// CRITICAL: Suppress ALL errors and warnings FIRST, before anything else
// Use 0 to completely suppress all error output
error_reporting(0);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// CRITICAL: Start output buffering IMMEDIATELY to catch any stray output
ob_start();

require_once __DIR__ . '/../core/birkly_paths.php';

// Helper function for directory size (define early)
if (!function_exists('getDirectorySize')) {
    function getDirectorySize($directory) {
        $size = 0;
        if (!is_dir($directory)) {
            return 0;
        }
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                }
            }
        } catch (Exception $e) {
            error_log("Error calculating directory size: " . $e->getMessage());
            return 0;
        }
        return $size;
    }
}

// Suppress errors again after includes (in case included files changed settings)
error_reporting(0);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

// Wrap includes in try-catch to prevent fatal errors from breaking JSON
// Use @ to suppress any warnings during require
try {
    // Clear any output that might have been generated
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    @require_once '../core/plugins.php';
    
    // Clear output again after each include
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    @require_once 'auth.php';
    
    // Clear output again
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    @require_once 'validate.php';
    
    // Final clear
    if (ob_get_level() > 0) {
        ob_clean();
    }
} catch (Throwable $e) {
    // Suppress any output from included files
    if (ob_get_level() > 0) {
        ob_clean();
    }
    error_log("Error loading dashboard dependencies: " . $e->getMessage());
    if (ob_get_level() > 0) {
        ob_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to load dashboard dependencies'
    ]);
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
    exit;
}

// Suppress errors one more time after all includes
error_reporting(0);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

// Clear any output before setting headers
if (ob_get_level() > 0) {
    ob_clean();
}

// Set headers before authentication check
if (!headers_sent()) {
    header('Content-Type: application/json');
    http_response_code(200);
}

// Check authentication - requireAuth exits on failure, so we just call it
// If it fails, it will exit with 401/403, so we only get here if auth succeeds
requireAuth('view_dashboard');

$action = trim($_GET['action'] ?? '');

// Start performance tracking
$startTime = microtime(true);

if ($action === 'stats') {
    try {
        // Get core stats
        require_once __DIR__ . '/../core/storage/StorageFactory.php';
        $storage = get_storage();
        
        $collections = $storage->list('collections');
        $collectionCount = count($collections);
        $totalEntries = 0;
        $published = 0;
        $drafts = 0;
    
        // Count entries and their status
        foreach ($collections as $collectionDir) {
            $entries = $storage->list("collections/$collectionDir/entries");
            foreach ($entries as $entryFile) {
                $entry = $storage->read("collections/$collectionDir/entries/$entryFile");
                if ($entry) {
                    $totalEntries++;
                    $status = $entry['status'] ?? 'draft';
                    if ($status === 'published') {
                        $published++;
                    } else {
                        $drafts++;
                    }
                }
            }
        }
        
        $stats = [
            'collections' => $collectionCount,
            'total_entries' => $totalEntries,
            'published' => $published,
            'drafts' => $drafts
        ];
        
        // Collect additional stats from plugins
    $active_plugins = get_active_plugins();
    foreach ($active_plugins as $plugin_id => $plugin) {
        if (isset($plugin['dashboard_stats_callback'])) {
            $plugin_stats = call_user_func($plugin['dashboard_stats_callback']);
            if (is_array($plugin_stats)) {
                $stats = array_merge($stats, $plugin_stats);
            }
        }
    }
    
    ob_clean();
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'stats' => $stats
        ]
    ]);
    ob_end_flush();
    exit;
    } catch (Exception $e) {
        error_log("Error collecting dashboard stats: " . $e->getMessage());
        ob_clean();
        echo json_encode([
            'status' => 'ok',
            'data' => [
                'stats' => [
                    'collections' => 0,
                    'total_entries' => 0,
                    'published' => 0,
                    'drafts' => 0
                ]
            ]
        ]);
        ob_end_flush();
        exit;
    }
}

if ($action === 'activity') {
    try {
        // Get real activity feed - use activity.php functions
        $limit = intval($_GET['limit'] ?? 20);
        $filter = $_GET['filter'] ?? 'all';
        
        // Include activity.php to get helper functions
        if (file_exists(__DIR__ . '/activity.php')) {
            @require_once __DIR__ . '/activity.php';
        }
        
        $activity = [];
        $logFile = __DIR__ . '/../logs/security.log';
        
        if (file_exists($logFile)) {
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines !== false) {
                $lines = array_reverse($lines); // Newest first
                
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
                    
                    // Apply filter
                    if ($filter && $filter !== 'all') {
                        if (function_exists('getEventCategory')) {
                            $category = getEventCategory($event);
                            if ($category !== $filter) {
                                continue;
                            }
                        }
                    }
                    
                    if (function_exists('formatActivityItem')) {
                        $activityItem = formatActivityItem($event, $user, $details, $timestamp);
                        if ($activityItem) {
                            $activity[] = $activityItem;
                            $processed++;
                        }
                    } else {
                        // Fallback if formatActivityItem doesn't exist
                        $activity[] = [
                            'event' => $event,
                            'user' => $user,
                            'message' => ucfirst(str_replace('_', ' ', $event)),
                            'icon' => '📌',
                            'timestamp' => $timestamp,
                            'details' => $details,
                            'category' => 'other'
                        ];
                        $processed++;
                    }
                }
            }
        }
        
        // Clear any output before JSON
        ob_clean();
        echo json_encode([
            'status' => 'ok',
            'data' => [
                'activity' => $activity,
                'total' => count($activity)
            ]
        ]);
        ob_end_flush();
        exit;
    } catch (Exception $e) {
        error_log("Error in dashboard activity: " . $e->getMessage());
        ob_clean();
        echo json_encode([
            'status' => 'ok',
            'data' => [
                'activity' => [],
                'total' => 0
            ]
        ]);
        ob_end_flush();
        exit;
    }
}

if ($action === 'notifications') {
    try {
        $notifications = [];
        
        // Check for pending AI approvals
        $approvalsDir = __DIR__ . '/../data/ai_approvals';
        if (is_dir($approvalsDir)) {
            $approvalFiles = glob($approvalsDir . '/*.json');
            if ($approvalFiles !== false) {
                $pendingCount = 0;
                foreach ($approvalFiles as $file) {
                    $approval = json_decode(file_get_contents($file), true);
                    if ($approval && ($approval['status'] ?? '') === 'pending') {
                        $pendingCount++;
                    }
                }
                if ($pendingCount > 0) {
                    $notifications[] = [
                        'id' => 'ai_approvals',
                        'type' => 'info',
                        'title' => 'Pending AI Approvals',
                        'message' => "You have {$pendingCount} pending AI action" . ($pendingCount > 1 ? 's' : '') . " requiring approval",
                        'link' => birkly_admin_url('settings', ['tab' => 'ai_approvals']),
                        'icon' => '🤖',
                        'priority' => 'medium'
                    ];
                }
            }
        }
        
        // Check for system errors
        $errorLog = __DIR__ . '/../logs/php_errors.log';
        if (file_exists($errorLog)) {
            $errorLines = file($errorLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($errorLines !== false) {
                $recentErrors = array_slice($errorLines, -10);
                $errorCount = 0;
                foreach ($recentErrors as $line) {
                    if (stripos($line, 'error') !== false || stripos($line, 'fatal') !== false) {
                        $errorCount++;
                    }
                }
                if ($errorCount > 0) {
                    $notifications[] = [
                        'id' => 'system_errors',
                        'type' => 'warning',
                        'title' => 'System Errors Detected',
                        'message' => "{$errorCount} recent error" . ($errorCount > 1 ? 's' : '') . " found in logs",
                        'link' => birkly_admin_url('settings', ['tab' => 'logs']),
                        'icon' => '⚠️',
                        'priority' => 'high'
                    ];
                }
            }
        }
        
        // Check storage usage
        $mediaDir = __DIR__ . '/../media';
        if (is_dir($mediaDir) && function_exists('getDirectorySize')) {
            $storageUsed = getDirectorySize($mediaDir);
            $storageUsedMB = round($storageUsed / 1024 / 1024, 2);
            
            // Warn if storage is getting high (over 500MB)
            if ($storageUsedMB > 500) {
                $notifications[] = [
                    'id' => 'storage_warning',
                    'type' => 'warning',
                    'title' => 'High Storage Usage',
                    'message' => "Media storage is using {$storageUsedMB}MB. Consider cleaning up unused files.",
                    'link' => birkly_admin_url('media'),
                    'icon' => '💾',
                    'priority' => 'low'
                ];
            }
        }
        
        // Check for updates (if update system exists)
        // This could be expanded later
        
        ob_clean();
        echo json_encode([
            'status' => 'ok',
            'data' => [
                'notifications' => $notifications,
                'count' => count($notifications)
            ]
        ]);
        ob_end_flush();
        exit;
    } catch (Exception $e) {
        error_log("Error in dashboard notifications: " . $e->getMessage());
        ob_clean();
        echo json_encode([
            'status' => 'ok',
            'data' => [
                'notifications' => [],
                'count' => 0
            ]
        ]);
        ob_end_flush();
        exit;
    }
}

if ($action === 'performance') {
    try {
        $performance = [];
        
        // Calculate API response time
        $responseTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
    
    // Get cache stats
    $cacheDir = __DIR__ . '/../cache';
    $cacheStats = [
        'enabled' => is_dir($cacheDir),
        'size' => 0,
        'files' => 0
    ];
    
    if (is_dir($cacheDir)) {
        try {
            $cacheStats['size'] = getDirectorySize($cacheDir);
            $cacheFiles = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($cacheDir, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($cacheFiles as $file) {
                if ($file->isFile()) {
                    $cacheStats['files']++;
                }
            }
        } catch (Exception $e) {
            error_log("Error reading cache directory: " . $e->getMessage());
        }
    }
    
    // Get media stats
    $mediaDir = __DIR__ . '/../media';
    $mediaStats = [
        'count' => 0,
        'size' => 0
    ];
    
    if (is_dir($mediaDir)) {
        try {
            $mediaStats['size'] = getDirectorySize($mediaDir);
        $metadataFile = $mediaDir . '/.metadata.json';
        if (file_exists($metadataFile)) {
            $metadata = json_decode(file_get_contents($metadataFile), true);
            $mediaStats['count'] = is_array($metadata) ? count($metadata) : 0;
        } else {
            // Fallback: count files
            $mediaFiles = glob($mediaDir . '/*.{jpg,jpeg,png,gif,pdf,svg,mp4,webm}', GLOB_BRACE);
            $mediaStats['count'] = $mediaFiles !== false ? count($mediaFiles) : 0;
        }
        } catch (Exception $e) {
            error_log("Error reading media directory: " . $e->getMessage());
        }
    }
    
    // Get database size (if SQLite)
    $dbPath = __DIR__ . '/../database/birkly.db';
    $dbSize = 0;
    if (file_exists($dbPath)) {
        $dbSize = filesize($dbPath);
    }
    
    // Get PHP memory usage
    $memoryUsage = memory_get_usage(true);
    $memoryPeak = memory_get_peak_usage(true);
    
    $performance = [
        'api_response_time' => round($responseTime, 2),
        'cache' => [
            'enabled' => $cacheStats['enabled'],
            'size_mb' => round($cacheStats['size'] / 1024 / 1024, 2),
            'files' => $cacheStats['files']
        ],
        'media' => [
            'count' => $mediaStats['count'],
            'size_mb' => round($mediaStats['size'] / 1024 / 1024, 2)
        ],
        'database' => [
            'size_mb' => round($dbSize / 1024 / 1024, 2),
            'exists' => file_exists($dbPath)
        ],
        'memory' => [
            'current_mb' => round($memoryUsage / 1024 / 1024, 2),
            'peak_mb' => round($memoryPeak / 1024 / 1024, 2)
        ],
        'timestamp' => date('c')
    ];
    
    ob_clean();
    echo json_encode([
        'status' => 'ok',
        'data' => $performance
    ]);
    ob_end_flush();
    exit;
    } catch (Exception $e) {
        error_log("Error in dashboard performance: " . $e->getMessage());
        ob_clean();
        echo json_encode([
            'status' => 'ok',
            'data' => [
                'api_response_time' => 0,
                'cache' => ['enabled' => false, 'size_mb' => 0, 'files' => 0],
                'media' => ['count' => 0, 'size_mb' => 0],
                'database' => ['size_mb' => 0, 'exists' => false],
                'memory' => ['current_mb' => 0, 'peak_mb' => 0],
                'timestamp' => date('c')
            ]
        ]);
        ob_end_flush();
        exit;
    }
}

if ($action === 'analytics') {
    // Clear ALL output buffers first
    while (ob_get_level() > 0) {
        ob_end_clean();
        }
    ob_start();
    
    // Set response code and headers immediately - before any output
    if (!headers_sent()) {
        http_response_code(200);
        header('Content-Type: application/json');
        }
    
    // Clear output buffer again after setting headers
    ob_clean();
    
    // Default empty response
    $response = [
        'status' => 'ok',
        'data' => [
            'total_page_views' => 0,
            'total_visitors' => 0,
            'websites' => [],
            'tracking_count' => 0,
            'has_domains' => false,
            'has_analytics' => false
        ]
    ];
    
    // Check if files exist
    $analyticsFile = __DIR__ . '/analytics.php';
    $domainAuthFile = __DIR__ . '/domain_auth.php';
    
    if (file_exists($analyticsFile) && file_exists($domainAuthFile)) {
        // Set a flag to prevent analytics.php from executing its default response
        // when included (it checks for direct execution)
        $GLOBALS['__HARU_INTERNAL_CALL'] = true;
        
        // Use output buffering to prevent analytics.php from outputting anything
        // analytics.php has code at the bottom that executes when included
        ob_start();
        @require_once $analyticsFile;
        $output = ob_get_clean(); // Get and discard any output from analytics.php
        
        // Now require domain_auth.php
        ob_start();
        @require_once $domainAuthFile;
        ob_end_clean(); // Discard any output from domain_auth.php
        
        // Clear any remaining output
        if (ob_get_level() > 0) {
            ob_clean();
        }
        
        // Unset the flag
        unset($GLOBALS['__HARU_INTERNAL_CALL']);
        
        if (function_exists('getAnalyticsData') && class_exists('DomainAuth')) {
            try {
                $domainAuth = new DomainAuth();
                $licenses = $domainAuth->getAllLicenses();
        
        $totalPageViews = 0;
        $totalVisitors = 0;
        $websites = [];
        $analyticsEnabledCount = 0;
        
        foreach ($licenses as $domain => $license) {
            if (!empty($license['analytics_enabled'])) {
                $analyticsEnabledCount++;
                        $response['data']['has_analytics'] = true;
                
                $startDate = date('Y-m-d', strtotime('-30 days'));
                $endDate = date('Y-m-d');
                
                try {
                    $data = getAnalyticsData($domain, $startDate, $endDate);
                            if (is_array($data)) {
                    $totalPageViews += $data['page_views'] ?? 0;
                    $totalVisitors += $data['unique_visitors'] ?? 0;
                    
                    $websites[] = [
                        'name' => $domain,
                        'domain' => $domain,
                        'page_views' => $data['page_views'] ?? 0,
                        'visitors' => $data['unique_visitors'] ?? 0,
                        'top_pages' => array_slice($data['top_pages'] ?? [], 0, 5)
                    ];
                            }
                } catch (Exception $e) {
                    error_log("Error getting analytics for domain {$domain}: " . $e->getMessage());
                        }
                    }
                }
                
                $response['data']['total_page_views'] = $totalPageViews;
                $response['data']['total_visitors'] = $totalVisitors;
                $response['data']['websites'] = $websites;
                $response['data']['tracking_count'] = $analyticsEnabledCount;
                $response['data']['has_domains'] = count($licenses) > 0;
            } catch (Throwable $e) {
                error_log("Error in analytics handler: " . $e->getMessage());
                // Use default empty response
            }
        }
    }
    
    // Always return valid JSON with 200 status
    echo json_encode($response);
        ob_end_flush();
        exit;
}

if ($action === 'widgets') {
    try {
        // Get widgets from plugins
        $widgets = [];
        $active_plugins = function_exists('get_active_plugins') ? get_active_plugins() : [];
    
    // Track which plugins we've already processed to avoid duplicates
    $processedPlugins = [];
    
    foreach ($active_plugins as $plugin_id => $plugin) {
        // Skip if we've already processed this plugin
        if (isset($processedPlugins[$plugin_id])) {
            continue;
        }
        
        // Check for dashboard_widgets_callback in plugin array first
        if (isset($plugin['dashboard_widgets_callback'])) {
            $pluginWidgets = call_user_func($plugin['dashboard_widgets_callback']);
            if (is_array($pluginWidgets)) {
                foreach ($pluginWidgets as $widget) {
                    $widget['plugin_id'] = $plugin_id;
                    $widgets[] = $widget;
                }
            }
            $processedPlugins[$plugin_id] = true;
        } else {
            // Also check global plugin registry if not found in plugin array
            global $birkly_plugins;
            if (isset($birkly_plugins[$plugin_id]['dashboard_widgets_callback'])) {
                $pluginWidgets = call_user_func($birkly_plugins[$plugin_id]['dashboard_widgets_callback']);
                if (is_array($pluginWidgets)) {
                    foreach ($pluginWidgets as $widget) {
                        $widget['plugin_id'] = $plugin_id;
                        $widgets[] = $widget;
                    }
                }
                $processedPlugins[$plugin_id] = true;
            }
        }
    }
    
    // Also check for widgets registered via hooks (but skip if already processed)
    global $haru_hooks;
    if (isset($haru_hooks['dashboard_widgets'])) {
        foreach ($haru_hooks['dashboard_widgets'] as $hook_data) {
            $hookPluginId = $hook_data['plugin_id'] ?? 'unknown';
            // Skip if this plugin was already processed
            if (isset($processedPlugins[$hookPluginId])) {
                continue;
            }
            $callback = $hook_data['callback'];
            $pluginWidgets = call_user_func($callback);
            if (is_array($pluginWidgets)) {
                foreach ($pluginWidgets as $widget) {
                    $widget['plugin_id'] = $hookPluginId;
                    $widgets[] = $widget;
                }
            }
            $processedPlugins[$hookPluginId] = true;
        }
    }
    
    // Don't add analytics widget here - it has its own dedicated section at the top
    
        ob_clean();
        echo json_encode([
            'status' => 'ok',
            'data' => [
                'widgets' => $widgets
            ]
        ]);
        ob_end_flush();
        exit;
    } catch (Exception $e) {
        error_log("Error in dashboard widgets: " . $e->getMessage());
        ob_clean();
        echo json_encode([
            'status' => 'ok',
            'data' => [
                'widgets' => []
            ]
        ]);
        ob_end_flush();
        exit;
    }
}

// Helper function to get directory size
function getDirectorySize($directory) {
    $size = 0;
    if (is_dir($directory)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
    }
    return $size;
}

// Default response - handle unknown actions gracefully
ob_clean();
http_response_code(200); // Return 200 even for invalid actions
echo json_encode([
    'status' => 'ok',
    'data' => null,
    'message' => 'Invalid or unsupported action'
]);
ob_end_flush();
exit; 