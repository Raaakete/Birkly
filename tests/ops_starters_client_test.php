<?php
/**
 * Ops starter client — live catalog probe (requires network).
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/birkly_paths.php';
require_once __DIR__ . '/../core/ops_starters_client.php';
require_once __DIR__ . '/../core/demo_starter_templates.php';

$failed = 0;

function assert_true(string $label, bool $condition): void
{
    global $failed;
    if (!$condition) {
        echo "FAIL: $label\n";
        $failed++;
        return;
    }
    echo "OK: $label\n";
}

$errors = [];
$catalog = birkly_ops_fetch_starters_catalog($errors);
assert_true('ops catalog reachable', $catalog !== null);
if ($errors !== []) {
    echo 'catalog errors: ' . implode('; ', $errors) . "\n";
}

$availability = birkly_demo_starter_availability(birkly_project_root());
assert_true('marsk selectable from ops', !empty($availability['marsk']['selectable']));

$diagnostics = birkly_ops_starters_diagnostics('marsk');
assert_true('marsk diagnostics selectable', ($diagnostics['selectable'] ?? false) === true);

$tmp = sys_get_temp_dir() . '/birkly-ops-marsk-' . getmypid() . '.zip';
$downloadErrors = [];
$download = birkly_ops_download_starter_zip('marsk', $tmp, $downloadErrors);
assert_true('marsk zip downloads from ops', $download !== null && is_file($tmp));
if ($downloadErrors !== []) {
    echo 'download errors: ' . implode('; ', $downloadErrors) . "\n";
}
@unlink($tmp);

exit($failed > 0 ? 1 : 0);
