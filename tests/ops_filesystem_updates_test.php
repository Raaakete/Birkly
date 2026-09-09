<?php
/**
 * Ops update checks can read CMS releases directly from local Marketplace data files.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/platform.php';
require_once $root . '/core/platform_ops.php';

$errors = [];

$tmpMarketplace = sys_get_temp_dir() . '/birkly-mp-ops-' . bin2hex(random_bytes(4));
$channelDir = $tmpMarketplace . '/data/ops/releases/alpha';
mkdir($channelDir, 0777, true);

file_put_contents($channelDir . '/_channel.json', json_encode([
    'channel' => 'alpha',
    'active_version' => '2.5.0',
], JSON_THROW_ON_ERROR));

file_put_contents($channelDir . '/2.5.0.json', json_encode([
    'channel' => 'alpha',
    'version' => '2.5.0',
    'checksum_sha256' => 'abc123',
    'release_notes' => 'Filesystem release',
], JSON_THROW_ON_ERROR));

file_put_contents($channelDir . '/2.5.0.zip', 'zip-content');

putenv('BIRKLY_MARKETPLACE_ROOT=' . $tmpMarketplace);
$GLOBALS['__birkly_platform_config'] = ['release_channel' => 'alpha'];

$result = birkly_ops_check_updates_from_filesystem('1.0.0');
unset($GLOBALS['__birkly_platform_config']);
putenv('BIRKLY_MARKETPLACE_ROOT');

if ($result === null || empty($result['ok'])) {
    $errors[] = 'Filesystem ops check should succeed from local Marketplace data';
}
if (empty($result['update_available']) || ($result['latest_version'] ?? '') !== '2.5.0') {
    $errors[] = 'Filesystem ops check should report active published version';
}
if (($result['download_url'] ?? '') !== $channelDir . '/2.5.0.zip') {
    $errors[] = 'Filesystem ops check should return local zip path for download';
}

$recursiveDelete = static function (string $dir) use (&$recursiveDelete): void {
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $dir . '/' . $entry;
        if (is_dir($path)) {
            $recursiveDelete($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
};
$recursiveDelete($tmpMarketplace);

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

echo "ops_filesystem_updates_test: OK\n";
