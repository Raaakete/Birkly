<?php
/**
 * P83-M8 — Fourth-party fixture plugin stub (admin shelf end-to-end PHP surface).
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/birkly_data_paths.php';

$tmpSettings = sys_get_temp_dir() . '/birkly-stub-shelf-' . getmypid();
@mkdir($tmpSettings, 0775, true);
$GLOBALS['__birkly_plugin_state_settings_dir'] = $tmpSettings;
$GLOBALS['__birkly_data_root'] = null;

require_once $root . '/core/plugins.php';
require_once $root . '/core/plugins/admin_asset_shelf.php';
require_once $root . '/core/plugins/notification_shelf.php';

$failures = [];

function stub_assert(string $label, bool $ok): void
{
    global $failures;
    if ($ok) {
        echo "OK: $label\n";
        return;
    }
    $failures[] = $label;
    fwrite(STDERR, "FAIL: $label\n");
}

$pluginsDir = sys_get_temp_dir() . '/birkly-stub-plugin-' . getmypid();
@mkdir($pluginsDir . '/acme_widget/admin', 0775, true);

file_put_contents($pluginsDir . '/acme_widget/plugin.php', <<<'PHP'
<?php
if (function_exists('register_notification_formatter')) {
    register_broadcast_actor('acme_widget', ['acme_widget'], ['acme_']);
    register_notification_formatter('acme_widget', 'acme_ping', static function (): array {
        return ['message' => 'Acme ping', 'icon' => '📦', 'category' => 'acme'];
    });
}
PHP);

file_put_contents($pluginsDir . '/acme_widget/plugin.json', json_encode([
    'name' => 'Acme Widget',
    'version' => '0.0.1',
    'admin' => [
        'scripts' => [[
            'path' => 'admin/dashboard-widget.js',
            'provides' => 'BirklyAcmeDashboard',
            'load' => 'dashboard',
        ]],
    ],
], JSON_THROW_ON_ERROR));

file_put_contents($pluginsDir . '/acme_widget/admin/dashboard-widget.js', <<<'JS'
(function () {
  window.BirklyDashboardWidgets = window.BirklyDashboardWidgets || {};
  window.BirklyDashboardWidgets.acmeStats = function () {};
})();
JS);

file_put_contents($tmpSettings . '/plugins.json', json_encode([
    'acme_widget' => ['active' => true, 'settings' => []],
], JSON_THROW_ON_ERROR));

$GLOBALS['birkly_plugins'] = [];
$GLOBALS['haru_plugins_loaded'] = true;
load_plugins($pluginsDir);

if (!function_exists('birkly_is_plugin_active')) {
    function birkly_is_plugin_active(string $plugin_id): bool
    {
        return $plugin_id === 'acme_widget';
    }
}

$scripts = birkly_get_admin_scripts('dashboard');
stub_assert(
    'fixture admin.scripts aggregated',
    count(array_filter(
        $scripts,
        static fn(array $s): bool => ($s['plugin_id'] ?? '') === 'acme_widget'
            && ($s['provides'] ?? '') === 'BirklyAcmeDashboard'
    )) === 1
);

$js = (string) file_get_contents($pluginsDir . '/acme_widget/admin/dashboard-widget.js');
stub_assert('fixture mount name in plugin JS', str_contains($js, 'acmeStats'));

stub_assert(
    'fixture notification formatter registered',
    in_array('acme_widget', birkly_notification_registered_actors(), true)
);
$notification = ['event' => 'acme_ping', 'details' => []];
$entry = ['event' => 'acme_ping', 'user' => 'acme_widget', 'details' => []];
$handled = birkly_notification_apply_formatter($notification, $entry, 'admin@example.com');
stub_assert('fixture formatter handles event', $handled && ($notification['message'] ?? '') === 'Acme ping');

@unlink($pluginsDir . '/acme_widget/admin/dashboard-widget.js');
@unlink($pluginsDir . '/acme_widget/plugin.php');
@unlink($pluginsDir . '/acme_widget/plugin.json');
@rmdir($pluginsDir . '/acme_widget/admin');
@rmdir($pluginsDir . '/acme_widget');
@rmdir($pluginsDir);
@unlink($tmpSettings . '/plugins.json');
@rmdir($tmpSettings);

if ($failures !== []) {
    exit(1);
}

echo "admin_plugin_shelf_stub_test: OK\n";
