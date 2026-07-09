<?php
/**
 * Admin polish smoke runner (P27-M15).
 */

$root = dirname(__DIR__);
require_once $root . '/tests/bootstrap.php';
require_once $root . '/core/birkly_paths.php';
require_once $root . '/core/security_policy.php';
require_once $root . '/admin/template_parser.php';

if (!function_exists('admin_url')) {
    function admin_url(string $page, array $params = []): string
    {
        return birkly_admin_url($page, $params);
    }
}

$fail = 0;
$assert = static function (string $label, bool $ok) use (&$fail): void {
    if ($ok) {
        echo "  OK: $label\n";
        return;
    }
    echo "  FAIL: $label\n";
    $fail++;
};

echo "[admin-polish-smoke] PHP checks\n";

$assert('birkly_project_root exists', function_exists('birkly_project_root'));
$assert('birkly_url exists', function_exists('birkly_url'));
$assert('birkly_media_url exists in birkly_paths', function_exists('birkly_media_url'));

$assert('security_policy loads', function_exists('birkly_require_2fa_enabled'));
$assert('grace helper loads', function_exists('birkly_user_in_2fa_grace'));

require_once $root . '/core/i18n.php';
I18n::init();
$dashboardPath = $root . '/admin/templates/dashboard.html';
$rendered = '';
try {
    $rendered = (string) render_template_file($dashboardPath, [
        'project_root' => $root,
        'is_local_dev' => false,
        'onboarding' => ['completed' => true, 'checklist' => []],
    ]);
} catch (Throwable $e) {
    $rendered = '';
}
$assert('dashboard template renders', $rendered !== '');

$syncScript = $root . '/scripts/sync-birkly-docs.sh';
$assert('sync-birkly-docs.sh present', is_file($syncScript));

exit($fail === 0 ? 0 : 1);
