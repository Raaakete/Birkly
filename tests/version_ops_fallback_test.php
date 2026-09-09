<?php
/**
 * System updates fall back to GitHub when Ops is configured but unreachable.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/platform.php';
require_once $root . '/core/platform_ops.php';
require_once $root . '/core/version.php';

$errors = [];

if (birkly_ops_updates_check_url() === '') {
    $errors[] = 'Ops updates URL should be configured by default for customer installs';
}

$GLOBALS['birkly_ops_check_updates_override'] = static function (string $currentVersion): ?array {
    return null;
};

$GLOBALS['birkly_fetch_github_json_override'] = static function (string $url): array {
    return [
        'data' => [
            'tag_name' => 'v9.9.9',
            'body' => 'Mock release notes',
            'zipball_url' => 'https://github.com/Raaakete/Birkly/archive/refs/tags/v9.9.9.zip',
            'published_at' => '2026-01-01T00:00:00Z',
            'prerelease' => false,
        ],
        'http_code' => 200,
    ];
};

$result = BirklyVersion::checkForUpdates();

if (isset($result['error'])) {
    $errors[] = 'checkForUpdates should fall back to GitHub when Ops is unreachable, got: ' . $result['error'];
}
if (($result['source'] ?? '') !== 'github') {
    $errors[] = 'Fallback update source should be github';
}
if (trim((string) ($result['latest_version'] ?? '')) === '') {
    $errors[] = 'GitHub fallback should return a latest_version';
}

unset($GLOBALS['birkly_ops_check_updates_override'], $GLOBALS['birkly_fetch_github_json_override']);

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

echo "version_ops_fallback_test: OK\n";
