<?php
/**
 * Offer detail falls back to bundled catalog with release metadata for installs.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/marketplace.php';

$errors = [];

$GLOBALS['birkly_marketplace_mock_handler'] = static function (string $method, string $path): array {
    return [
        'ok' => false,
        'status' => 0,
        'data' => null,
        'error' => 'Remote marketplace unreachable',
    ];
};

$detail = birkly_marketplace_offer_detail('birkly-automation');
unset($GLOBALS['birkly_marketplace_mock_handler']);

if (empty($detail['ok'])) {
    $errors[] = 'offer_detail should succeed from bundled fallback when live API is unreachable';
}
if (empty($detail['fallback'])) {
    $errors[] = 'offer_detail should mark fallback=true when using bundled catalog';
}
$releases = is_array($detail['releases'] ?? null) ? $detail['releases'] : [];
if ($releases === []) {
    $errors[] = 'Bundled fallback should include releases for birkly-automation';
}
$latest = birkly_marketplace_latest_release_version($releases);
if ($latest === null || trim($latest) === '') {
    $errors[] = 'Bundled fallback releases should include a latest version';
}

$perm = birkly_marketplace_install_permissions('birkly-automation');
if (empty($perm['ok'])) {
    $errors[] = 'install_permissions should resolve from fallback releases, got: ' . ($perm['error'] ?? 'unknown');
}

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

echo "marketplace_offer_detail_fallback_test: OK\n";
