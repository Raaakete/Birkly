<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/cors.php';

$failures = 0;

function assert_true(string $name, bool $ok, string $detail = ''): void
{
    global $failures;
    echo $name . ': ' . ($ok ? "PASS\n" : "FAIL\n");
    if (!$ok) {
        $failures++;
        if ($detail !== '') {
            echo '  ' . $detail . "\n";
        }
    }
}

$headers = birkly_cors_allowed_request_headers();

assert_true('allows If-None-Match', stripos($headers, 'If-None-Match') !== false, $headers);
assert_true('allows If-Modified-Since', stripos($headers, 'If-Modified-Since') !== false, $headers);
assert_true('allows Content-Type', stripos($headers, 'Content-Type') !== false, $headers);

echo "\ncors_preflight_headers_test: " . ($failures === 0 ? "PASS\n" : "FAIL ($failures)\n");
exit($failures === 0 ? 0 : 1);
