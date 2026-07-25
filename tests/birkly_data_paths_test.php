<?php
/**
 * birkly_data_paths.php — unified BIRKLY_DATA root helpers.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/birkly_data_paths.php';

$failures = 0;

function assert_true(bool $cond, string $label): void
{
    global $failures;
    if ($cond) {
        echo "OK  $label\n";
        return;
    }
    echo "FAIL $label\n";
    $failures++;
}

assert_true(function_exists('birkly_data_root'), 'birkly_data_root exists');
assert_true(function_exists('birkly_data_disk_path'), 'birkly_data_disk_path exists');
assert_true(function_exists('birkly_data_persistent_dir_names'), 'birkly_data_persistent_dir_names exists');

birkly_data_paths_reset_cache();
putenv('BIRKLY_DATA');
unset($_ENV['BIRKLY_DATA'], $_SERVER['BIRKLY_DATA']);
assert_true(birkly_data_root() === null, 'empty env → null data root');
assert_true(birkly_data_disk_path() === birkly_project_root(), 'disk path falls back to project root');

$tmp = sys_get_temp_dir() . '/birkly-data-test-' . bin2hex(random_bytes(4));
mkdir($tmp, 0755, true);
putenv('BIRKLY_DATA=' . $tmp);
birkly_data_paths_reset_cache();
assert_true(birkly_data_root() === realpath($tmp), 'BIRKLY_DATA resolves to realpath');
assert_true(birkly_data_disk_path() === realpath($tmp), 'disk path uses data root');

$names = birkly_data_persistent_dir_names();
assert_true(in_array('content', $names, true), 'persistent dirs include content');
assert_true(in_array('settings', $names, true), 'persistent dirs include settings');

putenv('BIRKLY_DATA');
birkly_data_paths_reset_cache();
@rmdir($tmp);

if ($failures > 0) {
    fwrite(STDERR, "$failures test(s) failed\n");
    exit(1);
}

echo "All birkly_data_paths tests passed.\n";
