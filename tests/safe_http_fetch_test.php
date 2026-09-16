<?php
/**
 * P88 — SSRF guard helpers for safe HTTP fetch.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/safe_http_fetch.php';

$failures = 0;

function assert_true(bool $cond, string $label): void
{
    global $failures;
    if ($cond) {
        echo "OK  $label\n";
        return;
    }
    echo "FAIL $label\n";
    $failures++;
}

echo "==> Blocked URLs\n";
assert_true(birkly_ai_presence_is_safe_external_url('http://127.0.0.1/') === false, 'blocks localhost');
assert_true(birkly_ai_presence_is_safe_external_url('http://169.254.169.254/') === false, 'blocks link-local metadata');
$fetch = birkly_safe_http_fetch('http://127.0.0.1/');
assert_true(empty($fetch['ok']), 'fetch rejects localhost');

echo "==> Resolve helper\n";
assert_true(birkly_safe_http_resolve_public_ip('localhost') === null, 'localhost does not resolve public');

exit($failures > 0 ? 1 : 0);
