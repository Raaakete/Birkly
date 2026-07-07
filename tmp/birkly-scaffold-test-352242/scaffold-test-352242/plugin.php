<?php
/**
 * Scaffold Test Plugin — minimal plugin scaffold (UI shelf only).
 */

if (!get_plugin_state('scaffold-test-352242')) {
    return;
}

if (function_exists('register_admin_page')) {
    register_admin_page('scaffold_test_352242', __DIR__ . '/admin/scaffold_test_352242.html');
}

if (function_exists('register_plugin_admin_meta')) {
    register_plugin_admin_meta('scaffold-test-352242', [
        'title' => 'Scaffold Test Plugin',
        'root_page' => 'scaffold_test_352242',
        'permission' => null,
        'page_slugs' => ['scaffold_test_352242'],
        'sidebar' => [
            'show' => true,
            'label' => 'Scaffold Test Plugin',
            'order' => 90,
        ],
    ]);
}

if (function_exists('register_plugin_docs')) {
    register_plugin_docs('scaffold-test-352242', [
        'title' => 'Scaffold Test Plugin',
        'summary' => 'Short summary for AI and Help.',
        'docs' => [
            'help' => __DIR__ . '/docs/help-overview.md',
            'ai' => __DIR__ . '/docs/ai-context.md',
            'public_path' => '06-extensibility/plugins/scaffold-test-352242.html',
        ],
        'keywords' => ['scaffold-test-352242'],
    ]);
}