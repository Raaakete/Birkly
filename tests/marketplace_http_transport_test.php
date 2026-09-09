<?php
/**
 * Marketplace HTTP transport — cURL preference and stream fallback.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/marketplace/http.php';

$errors = [];

$GLOBALS['birkly_marketplace_http_transport'] = static function (
    string $url,
    string $method,
    array $headers,
    ?string $body,
    int $timeout,
    bool $followRedirects,
    bool $allowRetry = true
): array {
    if ($url === 'https://marketplace.test/api/v1/registry' && strtoupper($method) === 'GET') {
        return [
            'ok' => true,
            'status' => 200,
            'body' => json_encode(['offers' => []], JSON_THROW_ON_ERROR),
            'error' => null,
        ];
    }

    return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'unexpected transport call'];
};

$fetch = birkly_marketplace_http_fetch('https://marketplace.test/api/v1/registry', 'GET', ['Accept: application/json']);
if (!$fetch['ok'] || $fetch['status'] !== 200) {
    $errors[] = 'Transport mock should return a successful registry response';
}

$GLOBALS['birkly_marketplace_http_download'] = static function (
    string $url,
    string $destinationPath,
    array $headers,
    int $timeout
): array {
    if ($url !== 'https://marketplace.test/artifact.zip') {
        return ['ok' => false, 'error' => 'unexpected download url'];
    }

    if (file_put_contents($destinationPath, 'zip-bytes') === false) {
        return ['ok' => false, 'error' => 'failed to write mock artifact'];
    }

    return ['ok' => true, 'error' => null];
};

$tmp = tempnam(sys_get_temp_dir(), 'birkly-mp-http-');
if ($tmp === false) {
    $errors[] = 'Could not create temp file for download test';
} else {
    $download = birkly_marketplace_http_download_to_file(
        'https://marketplace.test/artifact.zip',
        $tmp,
        ['User-Agent: Birkly-CMS'],
        30
    );
    if (!$download['ok'] || !is_file($tmp) || filesize($tmp) !== 8) {
        $errors[] = 'Download transport mock should write artifact bytes';
    }
    @unlink($tmp);
}

unset($GLOBALS['birkly_marketplace_http_transport'], $GLOBALS['birkly_marketplace_http_download']);

$message = birkly_marketplace_transport_error_message('Marketplace request failed', 'Connection timed out');
if ($message !== 'Marketplace request failed: Connection timed out') {
    $errors[] = 'Transport error message should include detail';
}

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

echo "marketplace_http_transport_test: OK\n";
