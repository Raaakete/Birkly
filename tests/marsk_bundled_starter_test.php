<?php
/**
 * Bundled Marsk starter — offline install fallback.
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/birkly_paths.php';
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

$root = birkly_project_root();
$localDir = birkly_demo_local_starter_dir('marsk');
assert_true('marsk bundled pack exists', $localDir !== null && is_dir($localDir . '/collections'));

$availability = birkly_demo_starter_availability($root);
assert_true('marsk selectable via bundled fallback', !empty($availability['marsk']['selectable']));
assert_true('marsk is_selectable', birkly_demo_starter_is_selectable($root, 'marsk'));

$tmpRoot = sys_get_temp_dir() . '/birkly-marsk-test-' . getmypid();
@mkdir($tmpRoot . '/content/collections', 0755, true);
$result = birkly_demo_install_from_local_dir($localDir, $tmpRoot);
assert_true('local install succeeds', !empty($result['success']));
assert_true('blog collection installed', in_array('blog', $result['installed_collections'], true));
assert_true('project site installed', !empty($result['project_site']));

birkly_demo_remove_tree($tmpRoot);

exit($failed > 0 ? 1 : 0);
