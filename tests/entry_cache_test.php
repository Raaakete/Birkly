<?php
/**
 * Entry cache separation tests (admin summaries vs public full payloads).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/cache.php';

$failures = 0;
$tmpRoot = sys_get_temp_dir() . '/birkly-entry-cache-test-' . bin2hex(random_bytes(4));
$collectionSlug = 'site_elements';
$entriesDir = $tmpRoot . '/content/collections/' . $collectionSlug . '/entries';
$cacheDir = $tmpRoot . '/cache';

mkdir($entriesDir, 0755, true);

$hero = [
    'id' => 'hero',
    'slug' => 'hero',
    'status' => 'published',
    'title' => 'Hero Section',
    'created_at' => '2026-01-01T00:00:00+00:00',
    'updated_at' => '2026-01-01T00:00:00+00:00',
    'en' => [
        'key' => 'hero',
        'content' => [['type' => 'text', 'value' => 'Notes']],
    ],
];
file_put_contents($entriesDir . '/hero.json', json_encode($hero, JSON_PRETTY_PRINT));

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

$cache = new Cache($cacheDir);

$summary = [[
    'id' => 'hero',
    'title' => 'Hero Section',
    'status' => 'published',
    'updated_at' => '2026-01-01T00:00:00+00:00',
    'created_at' => '2026-01-01T00:00:00+00:00',
]];

$cache->setEntries($collectionSlug, $summary);
$cached = $cache->getEntries($collectionSlug);
assert_true('legacy summary entries cache is ignored', $cached === null);

$cache->setEntrySummaries($collectionSlug, $summary);
$summaries = $cache->getEntrySummaries($collectionSlug);
assert_true('admin summaries cache round-trips', is_array($summaries) && count($summaries) === 1);

$cache->setEntries($collectionSlug, [$hero]);
$full = $cache->getEntries($collectionSlug);
assert_true(
    'full entries cache round-trips',
    is_array($full) && isset($full[0]['en']['content'])
);

$summaryStillThere = $cache->getEntrySummaries($collectionSlug);
assert_true('summary cache unaffected by full entry cache', is_array($summaryStillThere) && count($summaryStillThere) === 1);

function delete_dir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            delete_dir($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

delete_dir($tmpRoot);

echo "\nentry_cache_test: " . ($failures === 0 ? "PASS\n" : "FAIL ($failures)\n");
exit($failures === 0 ? 0 : 1);
