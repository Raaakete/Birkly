<?php
/**
 * Privileged Test — minimal plugin scaffold (UI shelf only).
 */

if (!get_plugin_state('scaffold-test-352242-priv')) {
    return;
}

if (function_exists('register_admin_page')) {
    register_admin_page('scaffold_test_352242_priv', __DIR__ . '/admin/scaffold_test_352242_priv.html');
}

if (function_exists('register_plugin_admin_meta')) {
    register_plugin_admin_meta('scaffold-test-352242-priv', [
        'title' => 'Privileged Test',
        'root_page' => 'scaffold_test_352242_priv',
        'permission' => null,
        'page_slugs' => ['scaffold_test_352242_priv'],
        'sidebar' => [
            'show' => true,
            'label' => 'Privileged Test',
            'order' => 90,
        ],
    ]);
}

if (function_exists('register_plugin_docs')) {
    register_plugin_docs('scaffold-test-352242-priv', [
        'title' => 'Privileged Test',
        'summary' => 'Short summary for AI and Help.',
        'docs' => [
            'help' => __DIR__ . '/docs/help-overview.md',
            'ai' => __DIR__ . '/docs/ai-context.md',
            'public_path' => '06-extensibility/plugins/scaffold-test-352242-priv.html',
        ],
        'keywords' => ['scaffold-test-352242-priv'],
    ]);
}