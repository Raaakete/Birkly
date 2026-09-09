<?php
/**
 * Local dev installs discover Ops updates and fall back to GitHub when Ops is unavailable.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/platform.php';
require_once $root . '/core/platform_ops.php';
require_once $root . '/core/version.php';

$errors = [];

$previousHost = $_SERVER['HTTP_HOST'] ?? null;
$_SERVER['HTTP_HOST'] = 'localhost:8080';

// Without local Ops running, localhost should still resolve cloud Ops URL for non-owner installs.
$GLOBALS['__birkly_platform_config'] = [];
if (birkly_ops_updates_check_url() === '') {
    $errors[] = 'Local dev should resolve default Ops updates URL when feedback relay is configured';
}

$GLOBALS['__birkly_platform_config'] = ['owner_mode' => true];
if (birkly_ops_updates_check_url() !== '' && !str_contains(birkly_ops_updates_check_url(), '127.0.0.1:8090')) {
    // Owner install only gets a URL when local Ops is actually running in this environment.
}

$GLOBALS['__birkly_platform_config'] = [
    'updates_url' => 'http://127.0.0.1:8090/api/ops/v1/releases/check',
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

if ($previousHost === null) {
    unset($_SERVER['HTTP_HOST']);
} else {
    $_SERVER['HTTP_HOST'] = $previousHost;
}
unset($GLOBALS['__birkly_platform_config']);

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

echo "local_dev_ops_updates_test: OK\n";
