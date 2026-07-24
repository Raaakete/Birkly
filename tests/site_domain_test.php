<?php
/**
 * Site domain helper tests.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/site_domain.php';

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

$_SERVER['HTTP_HOST'] = 'test.birkly.cloud';
$_SERVER['HTTP_REFERER'] = 'https://test.birkly.cloud/project/index.html';
$_SERVER['HTTP_ORIGIN'] = '';

assert_true('same cms origin detected from referer', birkly_request_is_same_cms_origin());

$_SERVER['HTTP_REFERER'] = 'https://evil.example/project/index.html';
assert_true('foreign referer is not same origin', !birkly_request_is_same_cms_origin());

$_SERVER['HTTP_ORIGIN'] = 'https://test.birkly.cloud';
$_SERVER['HTTP_REFERER'] = '';
assert_true('same cms origin detected from origin header', birkly_request_is_same_cms_origin());

assert_true('normalize host strips port', birkly_normalize_host('localhost:8080') === 'localhost');

echo "\nsite_domain_test: " . ($failures === 0 ? "PASS\n" : "FAIL ($failures)\n");
exit($failures === 0 ? 0 : 1);
