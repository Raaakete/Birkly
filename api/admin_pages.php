<?php
// api/admin_pages.php - Returns registered admin pages for the admin UI
require_once '../core/plugins.php';
header('Content-Type: application/json');
global $haru_admin_pages;

error_log("Admin pages API called");

$pages = [];
$active_plugins = get_active_plugins();
error_log("Active plugins: " . json_encode(array_keys($active_plugins)));

foreach ($haru_admin_pages as $slug => $file) {
    // Only include pages from active plugins
    $plugin_active = false;
    
    // Check if this page belongs to an active plugin
    foreach ($active_plugins as $plugin_id => $plugin) {
        // Check if the page file is within this plugin's directory
        if (strpos($file, '/plugins/' . $plugin_id . '/') !== false) {
            $plugin_active = true;
            error_log("Page {$slug} belongs to active plugin {$plugin_id}");
            break;
        }
    }
    
    // Also include core pages (not in plugins directory)
    if (!$plugin_active && strpos($file, '/plugins/') === false) {
        $plugin_active = true;
        error_log("Page {$slug} is a core page");
    }
    
    if ($plugin_active) {
        $pages[] = [
            'slug' => $slug,
            'file' => $file
        ];
        error_log("Including page: {$slug}");
    } else {
        error_log("Excluding page: {$slug} (plugin inactive)");
    }
}

error_log("Final pages: " . json_encode($pages));
echo json_encode(['status' => 'ok', 'data' => ['admin_pages' => $pages]]); 