<?php
require_once __DIR__ . '/plugins.php';
// core/admin.php - Core administration functionality for Birkly

// Register core admin pages
function register_core_admin_pages() {
    // Core CMS pages are routed via admin/index.php $allowedPages — not the plugin admin registry.
    // register_admin_page() is for plugins only; registering core slugs here broke routing in P3-M8.
}

// Hook to add core dashboard stats
function register_core_dashboard_stats() {
    register_hook('dashboard_stats', function(&$stats) {
        // Content count
        $content_count = 0;
        $content_dir = __DIR__ . '/../content/collections/';
        if (is_dir($content_dir)) {
            foreach (scandir($content_dir) as $collection) {
                if ($collection === '.' || $collection === '..') continue;
                $entries_dir = $content_dir . $collection . '/entries/';
                if (is_dir($entries_dir)) {
                    $files = glob($entries_dir . '*.json');
                    $content_count += count($files);
                }
            }
        }
        
        $stats[] = [
            'title' => 'Total Content',
            'value' => $content_count,
            'icon' => 'content',
            'color' => 'green'
        ];
        
        // Media count
        $media_count = 0;
        $media_dir = __DIR__ . '/../media/';
        if (is_dir($media_dir)) {
            $files = glob($media_dir . '*.{jpg,jpeg,png,gif,pdf,doc,docx}', GLOB_BRACE);
            $media_count = count($files);
        }
        
        $stats[] = [
            'title' => 'Media Files',
            'value' => $media_count,
            'icon' => 'media',
            'color' => 'purple'
        ];
    });
}

// Initialize core admin functionality
register_core_admin_pages();
register_core_dashboard_stats(); 