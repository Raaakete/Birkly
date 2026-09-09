<?php
/**
 * Local dev installs skip default Ops update URL and use GitHub instead.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/platform.php';
require_once $root . '/core/platform_ops.php';
require_once $root . '/core/version.php';

$errors = [];

$previousHost = $_SERVER['HTTP_HOST'] ?? null;
$_SERVER['HTTP_HOST'] = 'localhost:8080';

if (birkly_ops_updates_check_url() !== '') {
    $errors[] = 'Local dev should not use default Ops updates URL without explicit config';
}

$_SERVER['HTTP_HOST'] = 'example.com';
if (birkly_ops_updates_check_url() === '') {
    $errors[] = 'Production-like hosts should still resolve default Ops updates URL';
}

if ($previousHost === null) {
    unset($_SERVER['HTTP_HOST']);
} else {
    $_SERVER['HTTP_HOST'] = $previousHost;
}

$GLOBALS['birkly_fetch_github_json_override'] = static function (string $url): array {
    return [
        'data' => [
            'tag_name' => 'v2.0.0',
            'body' => 'Mock release notes',
            'zipball_url' => 'https://github.com/Raaakete/Birkly/archive/refs/tags/v2.0.0.zip',
            'published_at' => '2026-01-01T00:00:00Z',
            'prerelease' => false,
        ],
        'http_code' => 200,
    ];
};

$_SERVER['HTTP_HOST'] = 'localhost:8080';
$result = BirklyVersion::checkForUpdates();
unset($GLOBALS['birkly_fetch_github_json_override']);
if ($previousHost === null) {
    unset($_SERVER['HTTP_HOST']);
} else {
    $_SERVER['HTTP_HOST'] = $previousHost;
}

if (isset($result['error'])) {
    $errors[] = 'Local dev update check should use GitHub directly, got: ' . $result['error'];
}
if (($result['source'] ?? '') !== 'github') {
    $errors[] = 'Local dev update source should be github';
}

$status = BirklyVersion::getUpdateStatus();
if (($status['status'] ?? '') !== 'ok' || empty($status['current_version'])) {
    $errors[] = 'getUpdateStatus should always return local current_version';
}

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

echo "local_dev_ops_updates_test: OK\n";
