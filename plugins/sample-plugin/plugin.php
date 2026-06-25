<?php
// plugins/sample-plugin/plugin.php
// Sample plugin demonstrating plugin system features

// Only register functionality if plugin is active
if (!get_plugin_state('sample-plugin')) {
    return;
}

if (function_exists('register_admin_page')) {
    register_admin_page('sample_plugin', __DIR__ . '/admin/sample_plugin.html');
}

// Hook to log content save events
register_hook('after_content_save', function($collection, $entry) {
    $enable_logging = get_plugin_setting('sample-plugin', 'enable_logging', true);
    $log_level = get_plugin_setting('sample-plugin', 'log_level', 'info');
    $max_log_size = get_plugin_setting('sample-plugin', 'max_log_size', 1024);
    
    if (!$enable_logging) {
        return;
    }
    
    $log_file = __DIR__ . '/../logs/plugin.log';
    $log_entry = date('Y-m-d H:i:s') . " [{$log_level}] Content saved in {$collection}: " . json_encode($entry) . "\n";
    
    // Check log file size and rotate if needed
    if (file_exists($log_file) && filesize($log_file) > ($max_log_size * 1024)) {
        $backup_file = $log_file . '.backup';
        if (file_exists($backup_file)) {
            unlink($backup_file);
        }
        rename($log_file, $backup_file);
    }
    
    file_put_contents($log_file, $log_entry, FILE_APPEND);
});



// Hook to add dashboard stats
register_hook('dashboard_stats', function(&$stats) {
    $log_file = __DIR__ . '/../logs/plugin.log';
    $log_entries = 0;
    
    if (file_exists($log_file)) {
        $log_entries = count(file($log_file));
    }
    
    $stats[] = [
        'title' => 'Plugin Log Entries',
        'value' => $log_entries,
        'icon' => 'log',
        'color' => 'orange'
    ];
});

// Hook for plugin activation
register_hook('plugin_activated', function($plugin_id) {
    if ($plugin_id === 'sample-plugin') {
        $log_file = __DIR__ . '/../logs/plugin.log';
        $activation_entry = date('Y-m-d H:i:s') . " [INFO] Sample Plugin activated\n";
        file_put_contents($log_file, $activation_entry, FILE_APPEND);
    }
});

// Hook for plugin deactivation
register_hook('plugin_deactivated', function($plugin_id) {
    if ($plugin_id === 'sample-plugin') {
        $log_file = __DIR__ . '/../logs/plugin.log';
        $deactivation_entry = date('Y-m-d H:i:s') . " [INFO] Sample Plugin deactivated\n";
        file_put_contents($log_file, $deactivation_entry, FILE_APPEND);
    }
}); 