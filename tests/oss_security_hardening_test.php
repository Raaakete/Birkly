<?php
/**
 * Content slug validation and link-metadata URL safety tests (OSS hardening).
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/entry_slug.php';
require_once $root . '/core/ai_presence.php';

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

assert_true(birkly_validate_content_slug('blog'), 'valid collection slug');
assert_true(birkly_validate_content_slug('site_elements'), 'underscore slug allowed');
assert_true(birkly_validate_content_slug('my-collection-2'), 'hyphenated slug allowed');
assert_true(!birkly_validate_content_slug('../etc'), 'rejects path traversal');
assert_true(!birkly_validate_content_slug('foo/bar'), 'rejects slash');
assert_true(!birkly_validate_content_slug(''), 'rejects empty slug');

try {
    birkly_require_valid_content_slug('blog');
    assert_true(true, 'require accepts valid slug');
} catch (Throwable $e) {
    assert_true(false, 'require accepts valid slug');
}
try {
    birkly_require_valid_content_slug('../etc');
    assert_true(false, 'require rejects path traversal');
} catch (Throwable $e) {
    assert_true(true, 'require rejects path traversal');
}

assert_true(!birkly_ai_presence_is_safe_external_url('http://127.0.0.1/secret'), 'blocks localhost URL');
assert_true(!birkly_ai_presence_is_safe_external_url('ftp://example.com'), 'blocks non-http URL');
assert_true(birkly_ai_presence_is_safe_external_url('https://example.com'), 'allows public https URL');

exit($failures === 0 ? 0 : 1);
