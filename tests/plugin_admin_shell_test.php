<?php
/**
 * P7-M0 — Plugin admin shell tests.
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/core/plugins.php';

test_reset_failures();
echo "Plugin admin shell tests\n\n";

test_assert('register_plugin_admin_meta exists', function_exists('register_plugin_admin_meta'));
test_assert('get_plugin_admin_meta_for_page exists', function_exists('get_plugin_admin_meta_for_page'));
test_assert('birkly_render_plugin_page_breadcrumbs exists', function_exists('birkly_render_plugin_page_breadcrumbs'));

$tmpPluginId = 'admin_shelf_test_' . getmypid();
global $birkly_plugins;
$birkly_plugins[$tmpPluginId] = [
    'id' => $tmpPluginId,
    'path' => sys_get_temp_dir(),
    'active' => true,
    'name' => 'Admin Shelf Test',
];

$ok = register_plugin_admin_meta($tmpPluginId, [
    'title' => 'Shelf Test',
    'root_page' => 'shelf_test_root',
    'permission' => 'manage_settings',
    'page_slugs' => ['shelf_test_root', 'shelf_test_sub'],
    'sub_pages' => [
        'shelf_test_sub' => ['title' => 'Sub Page'],
    ],
    'sidebar' => ['show' => true, 'label' => 'Shelf Test', 'order' => 5],
]);

test_assert('register_plugin_admin_meta succeeds', $ok === true);

$meta = get_plugin_admin_meta($tmpPluginId);
test_assert('get_plugin_admin_meta returns title', ($meta['title'] ?? '') === 'Shelf Test');

$byPage = get_plugin_admin_meta_for_page('shelf_test_sub');
test_assert('get_plugin_admin_meta_for_page resolves sub slug', ($byPage['plugin_id'] ?? '') === $tmpPluginId);

$trail = birkly_plugin_admin_breadcrumb_trail($meta, 'shelf_test_root');
test_assert('root breadcrumb single segment', count($trail) === 1 && ($trail[0]['label'] ?? '') === 'Shelf Test');

$subTrail = birkly_plugin_admin_breadcrumb_trail($meta, 'shelf_test_sub');
test_assert('sub-page breadcrumb two segments', count($subTrail) === 2);
test_assert('sub-page ends with Sub Page', ($subTrail[1]['label'] ?? '') === 'Sub Page');

$html = birkly_render_plugin_page_breadcrumbs($meta, 'shelf_test_root');
test_assert('breadcrumb html has current class', strpos($html, 'class="current"') !== false);
test_assert('breadcrumb html no Settings', stripos($html, 'Settings') === false);

$perm = birkly_plugin_admin_page_permission('shelf_test_root');
test_assert('page permission from meta', $perm === 'manage_settings');

$nav = birkly_plugin_admin_sidebar_navigation();
test_assert('sidebar nav includes test plugin', count(array_filter($nav, fn ($n) => ($n['id'] ?? '') === $tmpPluginId)) === 1);

$birkly_plugins[$tmpPluginId]['active'] = false;
test_assert('inactive plugin excluded from sidebar', count(array_filter(birkly_plugin_admin_sidebar_navigation(), fn ($n) => ($n['id'] ?? '') === $tmpPluginId)) === 0);

test_assert('plugin_page_shell partial exists', is_file(dirname(__DIR__) . '/admin/partials/plugin_page_shell.php'));
test_assert('PLUGIN_ADMIN_UI_GUIDE exists', is_file(dirname(__DIR__) . '/docs/PLUGIN_ADMIN_UI_GUIDE.md'));

exit(test_finish('Plugin admin shell tests'));
