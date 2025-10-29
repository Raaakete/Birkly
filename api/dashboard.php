<?php
// api/dashboard.php - Dashboard statistics API
require_once '../core/plugins.php';
require_once 'auth.php';
require_once 'validate.php';

header('Content-Type: application/json');

// Check authentication
requireAuth('view_dashboard');

$action = $_GET['action'] ?? '';

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

// Default response
echo json_encode([
    'status' => 'error',
    'message' => 'Invalid action'
]); 