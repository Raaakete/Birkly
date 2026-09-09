<?php
/**
 * Official Hub plugins resolve permissions and release artifacts from bundled catalog without live API calls.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/marketplace.php';

$errors = [];
$slug = 'birkly-automation';
$version = '1.1.31';

$GLOBALS['birkly_marketplace_mock_handler'] = static function (string $method, string $path): array {
    return [
        'ok' => false,
        'status' => 0,
        'data' => null,
        'error' => 'Remote marketplace unreachable',
    ];
};

$start = microtime(true);
$permissions = birkly_marketplace_install_permissions($slug, $version);
$permMs = (int) round((microtime(true) - $start) * 1000);

if (empty($permissions['ok'])) {
    $errors[] = 'install_permissions should succeed from bundled catalog';
}
if ($permMs > 500) {
    $errors[] = 'install_permissions should be fast without live API, took ' . $permMs . 'ms';
}

$start = microtime(true);
$release = birkly_marketplace_release_download($slug, $version);
$releaseMs = (int) round((microtime(true) - $start) * 1000);

if (empty($release['ok']) || trim((string) ($release['download_url'] ?? '')) === '') {
    $errors[] = 'release_download should resolve artifact URL from bundled catalog';
}
if ($releaseMs > 500) {
    $errors[] = 'release_download should be fast without live API, took ' . $releaseMs . 'ms';
}

$_SERVER['HTTP_HOST'] = 'localhost:8080';
$claim = birkly_marketplace_claim_free_license($slug);
unset($_SERVER['HTTP_HOST']);

if (empty($claim['ok']) || ($claim['source'] ?? '') !== 'local') {
    $errors[] = 'claim_free_license should use local entitlement on localhost without checkout';
}

unset($GLOBALS['birkly_marketplace_mock_handler']);

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

echo "marketplace_install_fast_path_test: OK\n";
