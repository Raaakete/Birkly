<?php
/**
 * Marketplace URL resolution for local dev fallback.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/marketplace.php';

$errors = [];
$settingsDir = sys_get_temp_dir() . '/birkly-mp-url-' . bin2hex(random_bytes(4));
mkdir($settingsDir, 0777, true);
$GLOBALS['__birkly_marketplace_settings_dir'] = $settingsDir;
birkly_marketplace_settings_reset();

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
        if (str_contains($url, '127.0.0.1:8090')) {
            return [
                'ok' => true,
                'status' => 200,
                'body' => json_encode(['status' => 'ok'], JSON_THROW_ON_ERROR),
                'error' => null,
            ];
        }

        return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'Connection timed out'];
    }

    return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'unexpected url: ' . $url];
};

$resolved = birkly_marketplace_resolve_effective_url(BIRKLY_MARKETPLACE_DEFAULT_URL);
if ($resolved !== 'http://127.0.0.1:8090') {
    $errors[] = 'Local dev should fall back to marketplace on 127.0.0.1:8090 when cloud health probe fails';
}

birkly_marketplace_settings_reset();
$settings = birkly_marketplace_settings();
if (($settings['marketplace_url'] ?? '') !== 'http://127.0.0.1:8090') {
    $errors[] = 'Settings should expose resolved local marketplace URL';
}

$hint = birkly_marketplace_user_error_message('Marketplace request failed: Connection timed out');
if (!str_contains($hint, 'curl -4')) {
    $errors[] = 'User-facing marketplace error should include connectivity hints';
}

unset($GLOBALS['birkly_marketplace_http_transport'], $GLOBALS['__birkly_marketplace_settings_dir']);
birkly_marketplace_settings_reset();

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

echo "marketplace_url_resolve_test: OK\n";
