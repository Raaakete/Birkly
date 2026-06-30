<?php
/**
 * P5.4-M0 — Content page render smoke on subdirectory base path (no HTTP auth).
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/core/birkly_paths.php';
require_once dirname(__DIR__) . '/core/i18n.php';
require_once dirname(__DIR__) . '/admin/template_parser.php';

test_reset_failures();
echo "Admin subdir content render tests\n\n";

$_SERVER['SCRIPT_NAME'] = '/Birkly-0.1/admin/index.php';
$_SERVER['REQUEST_URI'] = '/Birkly-0.1/admin/index.php?page=content';
unset($GLOBALS['__birkly_base_path']);

if (!function_exists('admin_url')) {
    function admin_url(string $page, array $params = []): string {
        return birkly_admin_url($page, $params);
    }
}

I18n::init();

$GLOBALS['__HARU_INTERNAL_CALL'] = true;
require_once dirname(__DIR__) . '/api/admin_templates.php';

$data = getContentData();
$data2 = getContentData();
test_assert('getContentData repeat calls succeed', is_array($data2['content_bootstrap'] ?? null));

try {
    $output = '';
    ob_start();
    extract($data);
    include dirname(__DIR__) . '/admin/templates/content.html';
    $output = ob_get_clean();
    test_assert('content template renders', $output !== '');
    test_assert('output contains collections-container', strpos($output, 'collections-container') !== false);
    test_assert('output contains content-page', strpos($output, 'content-page') !== false);
    test_assert('output contains content-data JSON', strpos($output, 'id="content-data"') !== false);
    test_assert('no syntax error text in output', stripos($output, 'syntax error') === false);
} catch (Throwable $e) {
    test_assert('content template render without exception', false, $e->getMessage());
}

test_assert('admin_url uses subdirectory prefix', strpos(admin_url('content'), '/Birkly-0.1/') !== false);

exit(test_finish('admin_subdir_content_test'));
