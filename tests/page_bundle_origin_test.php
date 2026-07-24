<?php
/**
 * page_bundle same-origin collection access tests.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/page_bundle_runner.php';

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
$GLOBALS['current_domain'] = '';

assert_true('same-origin allows blog collection', birkly_page_bundle_collection_allowed('blog'));
assert_true('same-origin allows gallery collection', birkly_page_bundle_collection_allowed('gallery'));
assert_true('site_elements always allowed', birkly_page_bundle_collection_allowed('site_elements'));

$_SERVER['HTTP_REFERER'] = 'https://evil.example/project/index.html';
assert_true('foreign origin does not bypass collection auth without domain', !birkly_page_bundle_collection_allowed('blog'));

echo "\npage_bundle_origin_test: " . ($failures === 0 ? "PASS\n" : "FAIL ($failures)\n");
exit($failures === 0 ? 0 : 1);
