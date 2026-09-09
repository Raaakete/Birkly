<?php
/**
 * Ops-configured installs check updates via Ops and always expose local version in status.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/platform.php';
require_once $root . '/core/platform_ops.php';
require_once $root . '/core/version.php';

$errors = [];

$GLOBALS['__birkly_platform_config'] = [
    'updates_url' => 'https://ops.example.test/api/ops/v1/releases/check',
];
$GLOBALS['birkly_ops_check_updates_override'] = static function (string $currentVersion): ?array {
    return [
        'ok' => true,
        'current_version' => $currentVersion,
        'latest_version' => '9.9.9',
        'update_available' => true,
        'release_notes' => 'Ops release',
        'download_url' => 'https://ops.example.test/cms.zip',
        'checksum_sha256' => '',
    ];
};

$result = BirklyVersion::checkForUpdates();
unset($GLOBALS['birkly_ops_check_updates_override'], $GLOBALS['__birkly_platform_config']);

if (($result['source'] ?? '') !== 'ops') {
    $errors[] = 'checkForUpdates should prefer Ops when configured';
}
if (empty($result['update_available'])) {
    $errors[] = 'Ops update check should report update_available';
}

$status = BirklyVersion::getUpdateStatus();
if (($status['status'] ?? '') !== 'ok' || empty($status['current_version'])) {
    $errors[] = 'getUpdateStatus should always return local current_version';
}

$GLOBALS['__birkly_platform_config'] = ['owner_mode' => true];
if (birkly_ops_updates_check_url() !== 'https://ops.birkly.cloud/api/ops/v1/releases/check') {
    $errors[] = 'Owner installs should default to cloud Ops updates URL';
}
unset($GLOBALS['__birkly_platform_config']);

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

echo "local_dev_ops_updates_test: OK\n";
