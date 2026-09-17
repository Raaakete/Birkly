<?php
/**
 * P89: page_bundle status when requests fail validation.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/storage/StorageFactory.php';
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

$tmpRoot = sys_get_temp_dir() . '/birkly-pb-val-' . bin2hex(random_bytes(4));
$content = $tmpRoot . '/content';
mkdir($content . '/collections/blog/entries', 0755, true);
file_put_contents($content . '/collections/blog/collection.json', json_encode([
    'slug' => 'blog',
    'allow_public_read' => true,
], JSON_THROW_ON_ERROR));
file_put_contents($content . '/collections/blog/entries/a.json', json_encode([
    'id' => 'a',
    'slug' => 'a',
    'status' => 'published',
    'title' => 'A',
], JSON_THROW_ON_ERROR));

StorageFactory::reset();
$storage = StorageFactory::createStorage($content);

$allInvalid = birkly_run_page_bundle_requests($storage, [
    ['type' => 'list_entries', 'collection' => 'blog'],
], 'en', ['trusted_ssr' => true]);
assert_true(
    'missing into yields error status',
    ($allInvalid['status'] ?? '') === 'error' && ($allInvalid['errors'] ?? []) !== [],
    json_encode($allInvalid, JSON_UNESCAPED_SLASHES)
);

$mixed = birkly_run_page_bundle_requests($storage, [
    ['type' => 'list_entries', 'collection' => 'blog'],
    ['type' => 'list_entries', 'into' => 'blog', 'collection' => 'blog'],
], 'en', ['trusted_ssr' => true]);
assert_true(
    'mixed valid/invalid yields partial',
    ($mixed['status'] ?? '') === 'partial' && count($mixed['data']['blog'] ?? []) === 1,
    json_encode($mixed, JSON_UNESCAPED_SLASHES)
);

$ok = birkly_run_page_bundle_requests($storage, [
    ['type' => 'list_entries', 'into' => 'blog', 'collection' => 'blog'],
], 'en', ['trusted_ssr' => true]);
assert_true('valid batch stays ok', ($ok['status'] ?? '') === 'ok');

echo "\npage_bundle_validation_test: " . ($failures === 0 ? "PASS\n" : "FAIL ($failures)\n");
exit($failures === 0 ? 0 : 1);
