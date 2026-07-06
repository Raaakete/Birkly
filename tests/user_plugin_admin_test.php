<?php
/**
 * P7-M3: User Management admin UI smoke tests.
 */

require_once __DIR__ . '/bootstrap.php';

$root = dirname(__DIR__);
require_once $root . '/core/plugins.php';

if (!function_exists('admin_url')) {
    function admin_url(string $page, array $params = []): string
    {
        return '/admin/index.php?' . http_build_query(array_merge(['page' => $page], $params));
    }
}

test_reset_failures();
echo "User plugin admin tests\n\n";

test_assert('register_plugin_admin_meta exists', function_exists('register_plugin_admin_meta'));
$tmpId = 'user_admin_test_' . bin2hex(random_bytes(3));
test_assert('register_plugin_admin_meta succeeds', register_plugin_admin_meta($tmpId, [
    'title' => 'Test Plugin', 'page' => 'test_root', 'sub_pages' => ['test_sub' => 'Sub Page'],
]));
$rootCrumbs = build_plugin_admin_breadcrumbs('test_root');
test_assert('breadcrumb root is plugin title only', count($rootCrumbs) === 1 && $rootCrumbs[0]['label'] === 'Test Plugin');
$subCrumbs = build_plugin_admin_breadcrumbs('test_sub');
test_assert('breadcrumb sub-page has two segments', count($subCrumbs) === 2 && $subCrumbs[1]['label'] === 'Sub Page');

$html = file_get_contents($root . '/plugins/user_management/admin/site_users.html');
test_assert('site_users uses settings-tab-panels', strpos($html, 'settings-tab-panels') !== false && strpos($html, '<div class="tab-content">') === false);
test_assert('site_users general tab uses settings-stack cards', strpos($html, 'settings-stack') !== false && strpos($html, '<section class="card">') !== false);
test_assert('site_users uses plugin_page_header', strpos($html, '{plugin_page_header}') !== false);
test_assert('site_users uses unified tabs', strpos($html, 'settings-tabs plugin-tabs') !== false && strpos($html, 'class="tabs"') !== false);
test_assert('site_users uses tab class not tab-btn', strpos($html, 'tab-btn') === false);
test_assert('site_users scripts use birkly_url', strpos($html, "{birkly_url '/plugins/user_management/admin/site_users.js'}") !== false);
test_assert('site_users inline apiUrl', strpos($html, 'function apiUrl(path)') !== false && strpos($html, "fetch(apiUrl('/api/") !== false);

$js = file_get_contents($root . '/plugins/user_management/admin/site_users.js');
test_assert('site_users.js apiUrl helper', strpos($js, 'function apiUrl(path)') !== false);
test_assert('site_users.js no bare fetch /api/', !preg_match("/fetch\\(['`]\\/api\\//", $js));

$tiers = file_get_contents($root . '/plugins/user_management/admin/tiers.html');
test_assert('tiers breadcrumb User › Tiers', strpos($tiers, "{admin_url 'site_users'}") !== false && strpos($tiers, '>User</a>') !== false && strpos($tiers, '<span class="current">Tiers</span>') !== false);

global $birkly_plugin_admin_meta;
$birkly_plugin_admin_meta = [];
require_once $root . '/plugins/user_management/plugin.php';
test_assert('user_management admin meta registered', ($birkly_plugin_admin_meta['user_management']['title'] ?? '') === 'User');
test_assert('user_management sidebar label', ($birkly_plugin_admin_meta['user_management']['sidebar']['label'] ?? '') === 'User');
test_assert('user_management sub_pages', isset($birkly_plugin_admin_meta['user_management']['sub_pages']['user_tiers']));
$crumbs = build_plugin_admin_breadcrumbs('user_tiers');
test_assert('tiers breadcrumb from meta', count($crumbs) === 2 && $crumbs[1]['label'] === 'Tiers');

exit(test_finish('user_plugin_admin_test.php') === 0 ? 0 : 1);
