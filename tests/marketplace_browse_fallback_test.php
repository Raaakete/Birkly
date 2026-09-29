<?php
/**
 * Hub browse should degrade to bundled official offers when the registry is unreachable.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/marketplace.php';

$errors = [];

$fallback = birkly_marketplace_fallback_catalog();
if (count($fallback['offers'] ?? []) < 2) {
    $errors[] = 'Bundled fallback catalog should include official offers';
}

$GLOBALS['birkly_marketplace_http_transport'] = static function (
    string $url,
    string $method,
    array $headers,
    ?string $body,
    int $timeout,
    bool $followRedirects,
    bool $allowRetry = true
): array {
    if (str_ends_with($url, '/health')) {
        return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'Connection timed out'];
    }

    return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'unexpected url: ' . $url];
};

$started = microtime(true);
$browse = birkly_marketplace_browse('', '', 'plugin');
$elapsed = microtime(true) - $started;

if (empty($browse['ok']) || empty($browse['fallback'])) {
    $errors[] = 'Browse should succeed from bundled fallback when registry is unreachable';
}
if (count($browse['offers'] ?? []) < 2) {
    $errors[] = 'Browse fallback should return official offers';
}

$byPrice = birkly_marketplace_browse('', '', '', 'price_desc');
$priceTitles = array_map(static fn ($offer) => (string) ($offer['title'] ?? ''), $byPrice['offers'] ?? []);
$commerceAt = array_search('Birkly Commerce', $priceTitles, true);
$automationAt = array_search('Birkly Automation', $priceTitles, true);
if ($commerceAt === false || $automationAt === false || $commerceAt > $automationAt) {
    $errors[] = 'Price high-to-low sort should list paid offers before free offers';
}
if ($elapsed > 2.0) {
    $errors[] = 'Browse fallback should fail fast without waiting for registry timeouts';
}

unset($GLOBALS['birkly_marketplace_http_transport']);
birkly_marketplace_settings_reset();

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

echo "marketplace_browse_fallback_test: OK\n";
