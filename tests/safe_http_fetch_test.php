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

echo "==> Method, headers, and body\n";
$GLOBALS['__birkly_safe_http_fetch_override'] = ['ok' => true, 'body' => 'posted', 'http_code' => 200];
$posted = birkly_safe_http_fetch('https://93.184.216.34/hook', [
    'method' => 'POST',
    'headers' => [
        'Content-Type' => 'application/json',
        'Host' => '169.254.169.254',
        "X-Bad\r\nX-Injected" => '1',
    ],
    'body' => '{"event":"sale"}',
]);
$last = $GLOBALS['__birkly_safe_http_fetch_last_request'] ?? [];
assert_true(($posted['ok'] ?? false) === true && ($posted['body'] ?? '') === 'posted', 'public POST can succeed');
assert_true(($last['method'] ?? '') === 'POST', 'POST method is kept');
assert_true(($last['body'] ?? '') === '{"event":"sale"}', 'POST body is kept');
assert_true(in_array('Content-Type: application/json', $last['headers'] ?? [], true), 'content type header is kept');
assert_true(!in_array('Host: 169.254.169.254', $last['headers'] ?? [], true), 'Host header is dropped');
$blocked = birkly_safe_http_fetch('http://127.0.0.1/hook', ['method' => 'POST', 'body' => 'secret']);
assert_true(empty($blocked['ok']), 'POST to loopback is still rejected');
$trace = birkly_safe_http_fetch('https://93.184.216.34/hook', ['method' => 'TRACE']);
assert_true(empty($trace['ok']) && ($trace['error'] ?? '') === 'Unsupported HTTP method', 'unsupported method rejected');
$get = birkly_safe_http_fetch('https://93.184.216.34/page');
assert_true(($GLOBALS['__birkly_safe_http_fetch_last_request']['method'] ?? '') === 'GET', 'GET stays the default');
assert_true(($GLOBALS['__birkly_safe_http_fetch_last_request']['body'] ?? null) === null, 'GET does not send a body');
unset($GLOBALS['__birkly_safe_http_fetch_override'], $GLOBALS['__birkly_safe_http_fetch_last_request']);

exit($failures > 0 ? 1 : 0);
