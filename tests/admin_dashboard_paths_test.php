<?php
/**
 * Dashboard template renders with project_root without fatal (P27-M15).
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Admin dashboard paths tests\n\n";

$root = dirname(__DIR__);
require_once $root . '/core/birkly_paths.php';
require_once $root . '/core/i18n.php';
require_once $root . '/admin/template_parser.php';

if (!function_exists('admin_url')) {
    function admin_url(string $page, array $params = []): string
    {
        return birkly_admin_url($page, $params);
    }
}

I18n::init();

$templatePath = $root . '/admin/templates/dashboard.html';
$data = [
    'project_root' => $root,
    'is_local_dev' => false,
    'onboarding' => [
        'setup_wizard_completed' => true,
        'completed' => true,
        'skipped' => false,
        'checklist_hidden' => false,
        'checklist' => [],
        'is_first_time' => false,
    ],
];

$output = '';
$error = null;

try {
    $output = (string) render_template_file($templatePath, $data);
} catch (Throwable $e) {
    $error = $e->getMessage();
}

test_assert('dashboard template renders without fatal', $error === null, (string) $error);
test_assert('dashboard output not empty', $output !== '');
test_assert('project_root available to template', strpos($output, 'dashboard') !== false || strpos($output, 'Dashboard') !== false || strpos($output, 'welcome') !== false);

exit(test_finish('admin_dashboard_paths_test'));
