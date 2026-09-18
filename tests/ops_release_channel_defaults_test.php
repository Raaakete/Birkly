<?php
/**
 * Alpha/beta release zips ship settings/birkly_ops_release_defaults.json for update channel.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/birkly_paths.php';
require_once $root . '/core/platform.php';
require_once $root . '/core/platform_ops.php';

$errors = [];

$testRoot = sys_get_temp_dir() . '/birkly_ops_channel_' . bin2hex(random_bytes(4));
mkdir($testRoot . '/settings', 0755, true);
$GLOBALS['__birkly_project_root'] = $testRoot;

file_put_contents(
    $testRoot . '/settings/birkly_ops_release_defaults.json',
    json_encode(['release_channel' => 'alpha'], JSON_UNESCAPED_SLASHES) . "\n"
);

$GLOBALS['__birkly_platform_config'] = [];

if (birkly_ops_release_channel() !== 'alpha') {
    $errors[] = 'release_channel should fall back to birkly_ops_release_defaults.json';
}

$GLOBALS['__birkly_platform_config'] = ['release_channel' => 'stable'];
if (birkly_ops_release_channel() !== 'stable') {
    $errors[] = 'platform.json release_channel should override zip defaults';
}

unset($GLOBALS['__birkly_project_root'], $GLOBALS['__birkly_platform_config']);

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($testRoot, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);
foreach ($iterator as $item) {
    $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
}
rmdir($testRoot);

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

echo "ops_release_channel_defaults_test: OK\n";
