<?php
/**
 * Studio Plugin — visual site builder (P22).
 */

if (defined('BIRKLY_STUDIO_BOOTSTRAP')) {
    return;
}
define('BIRKLY_STUDIO_BOOTSTRAP', true);

require_once __DIR__ . '/init.php';

if (!studio_plugin_active()) {
    return;
}

require_once __DIR__ . '/includes/StudioProjectRepository.php';
require_once __DIR__ . '/includes/StudioCompiler.php';
require_once __DIR__ . '/includes/StudioRenderer.php';
require_once __DIR__ . '/includes/StudioExporter.php';
require_once __DIR__ . '/includes/StudioBlockRegistry.php';

if (function_exists('register_api_endpoint')) {
    register_api_endpoint('studio', __DIR__ . '/api/studio.php');
}

if (function_exists('register_admin_page')) {
    register_admin_page('studio', __DIR__ . '/admin/studio.html');
}

if (function_exists('register_plugin_admin_meta')) {
    register_plugin_admin_meta('studio', [
        'title' => 'Studio',
        'root_page' => 'studio',
        'permission' => 'manage_studio',
        'page_slugs' => ['studio'],
        'sidebar' => [
            'show' => true,
            'label' => 'Studio',
            'order' => 25,
            'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"></rect><path d="M3 9h18"></path><path d="M9 21V9"></path></svg>',
        ],
    ]);
}

if (function_exists('register_public_route')) {
    register_public_route('/site/', function (string $uri): bool {
        require_once __DIR__ . '/includes/StudioRenderer.php';
        $renderer = new StudioRenderer();
        return $renderer->handlePublicRequest($uri);
    }, ['plugin_id' => 'studio', 'priority' => 10]);
}

if (function_exists('register_hook')) {
    register_hook('plugin_activated', function ($plugin_id) {
        if ($plugin_id === 'studio') {
            $root = studio_content_root();
            if (!is_dir($root)) {
                mkdir($root, 0755, true);
            }
        }
    });
}

if (function_exists('register_plugin_docs')) {
    register_plugin_docs('studio', [
        'title' => 'Studio',
        'summary' => 'Project IDE: filesystem, tabs, visual/code/split editor, style kits, Birkly bindings, SSR publish, and ZIP export.',
        'badge' => 'Extension',
        'docs' => [
            'help' => __DIR__ . '/README.md',
        ],
        'keywords' => ['studio', 'visual builder', 'site builder', 'SSR', 'style kit'],
    ]);
}

global $birkly_plugins;
if (isset($birkly_plugins['studio'])) {
    $birkly_plugins['studio']['dashboard_widgets_callback'] = 'studio_dashboard_widgets';
}
