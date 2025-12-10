<?php
// api/dashboard.php - Dashboard statistics API
require_once '../core/plugins.php';
require_once 'auth.php';
require_once 'validate.php';

header('Content-Type: application/json');

// Check authentication
requireAuth('view_dashboard');

$action = $_GET['action'] ?? '';

// Start performance tracking
$startTime = microtime(true);

if ($action === 'stats') {
    $stats = [];
    
    error_log('Collecting dashboard stats...');
    
    // Collect stats from all active plugins by calling them directly
    $active_plugins = get_active_plugins();
    foreach ($active_plugins as $plugin_id => $plugin) {
        if (isset($plugin['dashboard_stats_callback'])) {
            $plugin_stats = call_user_func($plugin['dashboard_stats_callback']);
            if (is_array($plugin_stats)) {
                $stats = array_merge($stats, $plugin_stats);
            }
        }
    }
    
    error_log('Dashboard stats collected: ' . json_encode($stats));
    
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'stats' => $stats
        ]
    ]);
    exit;
}

if ($action === 'activity') {
    // Get real activity feed - use activity.php functions
    $limit = intval($_GET['limit'] ?? 20);
    $filter = $_GET['filter'] ?? 'all';
    
    // Include activity.php to get helper functions
    require_once __DIR__ . '/activity.php';
    
    $activity = [];
    $logFile = __DIR__ . '/../logs/security.log';
    
    if (file_exists($logFile)) {
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
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
                $category = getEventCategory($event);
                if ($category !== $filter) {
                    continue;
                }
            }
            
            $activityItem = formatActivityItem($event, $user, $details, $timestamp);
            if ($activityItem) {
                $activity[] = $activityItem;
                $processed++;
            }
        }
    }
    
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'activity' => $activity,
            'total' => count($activity)
        ]
    ]);
    exit;
}

if ($action === 'notifications') {
    $notifications = [];
    
    // Check for pending AI approvals
    $approvalsDir = __DIR__ . '/../data/ai_approvals';
    if (is_dir($approvalsDir)) {
        $approvalFiles = glob($approvalsDir . '/*.json');
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
                'link' => '/admin/index.php?page=settings&tab=ai_approvals',
                'icon' => '🤖',
                'priority' => 'medium'
            ];
        }
    }
    
    // Check for system errors
    $errorLog = __DIR__ . '/../logs/php_errors.log';
    if (file_exists($errorLog)) {
        $errorLines = file($errorLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
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
                'link' => '/admin/index.php?page=settings&tab=logs',
                'icon' => '⚠️',
                'priority' => 'high'
            ];
        }
    }
    
    // Check storage usage
    $mediaDir = __DIR__ . '/../media';
    if (is_dir($mediaDir)) {
        $storageUsed = getDirectorySize($mediaDir);
        $storageUsedMB = round($storageUsed / 1024 / 1024, 2);
        
        // Warn if storage is getting high (over 500MB)
        if ($storageUsedMB > 500) {
            $notifications[] = [
                'id' => 'storage_warning',
                'type' => 'warning',
                'title' => 'High Storage Usage',
                'message' => "Media storage is using {$storageUsedMB}MB. Consider cleaning up unused files.",
                'link' => '/admin/index.php?page=media',
                'icon' => '💾',
                'priority' => 'low'
            ];
        }
    }
    
    // Check for updates (if update system exists)
    // This could be expanded later
    
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'notifications' => $notifications,
            'count' => count($notifications)
        ]
    ]);
    exit;
}

if ($action === 'performance') {
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
        $cacheStats['size'] = getDirectorySize($cacheDir);
        $cacheFiles = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($cacheDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($cacheFiles as $file) {
            if ($file->isFile()) {
                $cacheStats['files']++;
            }
        }
    }
    
    // Get media stats
    $mediaDir = __DIR__ . '/../media';
    $mediaStats = [
        'count' => 0,
        'size' => 0
    ];
    
    if (is_dir($mediaDir)) {
        $mediaStats['size'] = getDirectorySize($mediaDir);
        $metadataFile = $mediaDir . '/.metadata.json';
        if (file_exists($metadataFile)) {
            $metadata = json_decode(file_get_contents($metadataFile), true);
            $mediaStats['count'] = is_array($metadata) ? count($metadata) : 0;
        } else {
            // Fallback: count files
            $mediaFiles = glob($mediaDir . '/*.{jpg,jpeg,png,gif,pdf,svg,mp4,webm}', GLOB_BRACE);
            $mediaStats['count'] = count($mediaFiles);
        }
    }
    
    // Get database size (if SQLite)
    $dbPath = __DIR__ . '/../database/harucms.db';
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
    
    echo json_encode([
        'status' => 'ok',
        'data' => $performance
    ]);
    exit;
}

if ($action === 'analytics') {
    // Get analytics summary for dashboard
    require_once 'analytics.php';
    require_once 'domain_auth.php';
    
    $domainAuth = new DomainAuth();
    $licenses = $domainAuth->getAllLicenses();
    
    $analyticsSummary = [
        'total_page_views' => 0,
        'total_visitors' => 0,
        'websites' => [],
        'tracking_count' => 0,
        'has_domains' => count($licenses) > 0,
        'has_analytics' => false
    ];
    
    $totalPageViews = 0;
    $totalVisitors = 0;
    $websites = [];
    $analyticsEnabledCount = 0;
    
    foreach ($licenses as $domain => $license) {
        if (!empty($license['analytics_enabled'])) {
            $analyticsEnabledCount++;
            $analyticsSummary['has_analytics'] = true;
            
            $startDate = date('Y-m-d', strtotime('-30 days'));
            $endDate = date('Y-m-d');
            $data = getAnalyticsData($domain, $startDate, $endDate);
            
            $totalPageViews += $data['page_views'];
            $totalVisitors += $data['unique_visitors'];
            
            $websites[] = [
                'name' => $domain,
                'domain' => $domain,
                'page_views' => $data['page_views'],
                'visitors' => $data['unique_visitors'],
                'top_pages' => array_slice($data['top_pages'], 0, 5)
            ];
        }
    }
    
    $analyticsSummary['total_page_views'] = $totalPageViews;
    $analyticsSummary['total_visitors'] = $totalVisitors;
    $analyticsSummary['websites'] = $websites;
    $analyticsSummary['tracking_count'] = $analyticsEnabledCount;
    
    echo json_encode([
        'status' => 'ok',
        'data' => $analyticsSummary
    ]);
    exit;
}

if ($action === 'widgets') {
    // Get widgets from plugins
    $widgets = [];
    $active_plugins = get_active_plugins();
    
    foreach ($active_plugins as $plugin_id => $plugin) {
        // Check for dashboard_widgets_callback in plugin array
        if (isset($plugin['dashboard_widgets_callback'])) {
            $pluginWidgets = call_user_func($plugin['dashboard_widgets_callback']);
            if (is_array($pluginWidgets)) {
                foreach ($pluginWidgets as $widget) {
                    $widget['plugin_id'] = $plugin_id;
                    $widgets[] = $widget;
                }
            }
        }
        
        // Also check global plugin registry
        global $haru_plugins;
        if (isset($haru_plugins[$plugin_id]['dashboard_widgets_callback'])) {
            $pluginWidgets = call_user_func($haru_plugins[$plugin_id]['dashboard_widgets_callback']);
            if (is_array($pluginWidgets)) {
                foreach ($pluginWidgets as $widget) {
                    $widget['plugin_id'] = $plugin_id;
                    $widgets[] = $widget;
                }
            }
        }
    }
    
    // Also check for widgets registered via hooks
    global $haru_hooks;
    if (isset($haru_hooks['dashboard_widgets'])) {
        foreach ($haru_hooks['dashboard_widgets'] as $hook_data) {
            $callback = $hook_data['callback'];
            $pluginWidgets = call_user_func($callback);
            if (is_array($pluginWidgets)) {
                foreach ($pluginWidgets as $widget) {
                    $widget['plugin_id'] = $hook_data['plugin_id'] ?? 'unknown';
                    $widgets[] = $widget;
                }
            }
        }
    }
    
    // Always add analytics widget (shows setup message if no domains/analytics)
    $widgets[] = [
        'plugin_id' => 'harucms_core',
        'title' => 'Website Analytics',
        'html' => '<div id="analytics-widget-content">Loading analytics...</div>',
        'javascript' => 'loadAnalyticsWidget();'
    ];
    
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'widgets' => $widgets
        ]
    ]);
    exit;
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

// Default response
echo json_encode([
    'status' => 'error',
    'message' => 'Invalid action'
]); 