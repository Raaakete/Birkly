<?php
/**
 * Marketplace resolve cache — skip repeated health probes between Hub loads.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/marketplace.php';

$errors = [];
$settingsDir = sys_get_temp_dir() . '/birkly-mp-cache-' . bin2hex(random_bytes(4));
mkdir($settingsDir, 0777, true);
$GLOBALS['__birkly_marketplace_settings_dir'] = $settingsDir;
birkly_marketplace_settings_reset();

$calls = 0;
$GLOBALS['birkly_marketplace_http_transport'] = static function (
    string $url,
    string $method,
    array $headers,
    ?string $body,
    int $timeout,
    bool $followRedirects,
    bool $allowRetry = true
) use (&$calls): array {
    $calls++;
    if (str_ends_with($url, '/health') && str_contains($url, 'marketplace.birkly.cloud')) {
        return [
            'ok' => true,
            'status' => 200,
            'body' => json_encode(['status' => 'ok'], JSON_THROW_ON_ERROR),
            'error' => null,
        ];
    }

    return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'unexpected url: ' . $url];
};

$first = birkly_marketplace_resolve_effective_url(BIRKLY_MARKETPLACE_DEFAULT_URL);
if ($first !== BIRKLY_MARKETPLACE_DEFAULT_URL) {
    $errors[] = 'First resolve should select cloud marketplace when health succeeds';
}
if ($calls !== 1) {
    $errors[] = 'First resolve should perform one health probe, got ' . $calls;
}

$calls = 0;
$second = birkly_marketplace_resolve_effective_url(BIRKLY_MARKETPLACE_DEFAULT_URL);
if ($second !== BIRKLY_MARKETPLACE_DEFAULT_URL) {
    $errors[] = 'Cached resolve should return the same effective URL';
}
if ($calls !== 0) {
    $errors[] = 'Cached resolve should not repeat health probes, got ' . $calls;
}

@unlink(birkly_marketplace_resolve_cache_path());
unset($GLOBALS['birkly_marketplace_http_transport'], $GLOBALS['__birkly_marketplace_settings_dir']);
birkly_marketplace_settings_reset();

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

echo "marketplace_resolve_cache_test: OK\n";
