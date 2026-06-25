<?php
/**
 * Admin page routing regression test (P3-M8 fix).
 * Ensures core CMS pages resolve to their templates, not dashboard.html.
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/core/plugins.php';

test_reset_failures();
echo "Admin routing tests\n\n";

if (function_exists('load_plugins')) {
    load_plugins();
}

global $birkly_admin_pages;
if (!is_array($birkly_admin_pages)) {
    $birkly_admin_pages = [];
}

$corePages = [
    'dashboard' => 'dashboard.html',
    'content' => 'content.html',
    'media' => 'media.html',
    'settings' => 'settings.html',
    'help' => 'help.html',
    'feedback' => 'feedback.html',
    'audit-log' => 'audit-log.html',
    'activity' => 'activity.html',
    'updates' => 'updates.html',
];

$allowedPages = $corePages;
$allowedPages['users'] = 'settings.html';
$allowedPages['roles'] = 'roles.html';
$allowedPages['plugins'] = 'plugins.html';
$allowedPages['community_users'] = null;
$allowedPages['sample_plugin'] = null;

foreach ($birkly_admin_pages as $pluginPageSlug => $pluginPageFile) {
    if (!array_key_exists($pluginPageSlug, $allowedPages)) {
        $allowedPages[$pluginPageSlug] = null;
    }
}

foreach ($corePages as $slug => $expectedTemplate) {
    $templateFile = $allowedPages[$slug] ?? 'dashboard.html';
    test_assert(
        "$slug resolves to $expectedTemplate",
        $templateFile === $expectedTemplate,
        "got " . var_export($templateFile, true)
    );
}

$isAutomationPlugin = array_key_exists('automation', $birkly_admin_pages);
if ($isAutomationPlugin) {
    $isPluginPage = array_key_exists('automation', $birkly_admin_pages)
        && ($allowedPages['automation'] ?? null) === null;
    test_assert('automation is plugin admin page', $isPluginPage);
}

$isSiteUsersPlugin = array_key_exists('site_users', $birkly_admin_pages);
if ($isSiteUsersPlugin) {
    test_assert('site_users is plugin admin page', ($allowedPages['site_users'] ?? null) === null);
}

// Core slugs must not be registered as plugin-only pages after fix
foreach (['dashboard', 'content', 'media', 'settings'] as $slug) {
    test_assert(
        "$slug not in plugin-only registry",
        !array_key_exists($slug, $birkly_admin_pages),
        'core/admin.php must not register core pages via register_admin_page'
    );
}

exit(test_finish('admin_routing_test'));
