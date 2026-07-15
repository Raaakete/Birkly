<?php
/**
 * Reference demo isolation — switching projects must not leave stale collections.
 * Run: php tests/reference_demo_isolation_test.php
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/journeys/_helpers.php';

test_reset_failures();
echo "Reference demo isolation tests\n\n";

$root = dirname(__DIR__);

function read_site_element_title(string $root, string $entryId): string
{
    $path = $root . '/content/collections/site_elements/entries/' . $entryId . '.json';
    if (!is_file($path)) {
        return '';
    }
    $data = json_decode((string) file_get_contents($path), true);
    if (!is_array($data)) {
        return '';
    }
    if (isset($data['content']) && is_array($data['content']) && isset($data['content'][0]['value'])) {
        return (string) $data['content'][0]['value'];
    }
    $en = $data['en'] ?? $data;
    $content = $en['content'] ?? [];
    if (is_array($content) && isset($content[0]['value'])) {
        return (string) $content[0]['value'];
    }
    return (string) ($en['title'] ?? '');
}

journey_prepare_reference_demo('blog');
$blogHero = read_site_element_title($root, 'hero');
test_assert('blog install has hero', $blogHero !== '', 'hero title empty');

journey_prepare_reference_demo('social');
$socialHero = read_site_element_title($root, 'hero');
test_assert('social install has hero', $socialHero !== '', 'hero title empty');
test_assert('social hero differs from blog', $socialHero !== $blogHero, "blog='$blogHero' social='$socialHero'");

$base = journey_parse_base_url();
$bundle = journey_page_bundle($base, [
    ['type' => 'list_entries', 'into' => 'site_elements', 'collection' => 'site_elements'],
]);
$se = $bundle['data']['site_elements'] ?? [];
$seIds = array_map(static fn ($e) => (string) ($e['id'] ?? ''), $se);
test_assert('page_bundle site_elements has no blog bleed', !in_array('blog-headline', $seIds, true), implode(',', $seIds));
$heroFromApi = '';
foreach ($se as $entry) {
    $entryKey = (string) ($entry['key'] ?? $entry['slug'] ?? $entry['id'] ?? '');
    if ($entryKey === 'hero') {
        if (isset($entry['content']) && is_array($entry['content']) && isset($entry['content'][0]['value'])) {
            $heroFromApi = (string) $entry['content'][0]['value'];
        } else {
            $heroFromApi = (string) ($entry['title'] ?? '');
        }
        break;
    }
}
test_assert('page_bundle hero matches social disk', $heroFromApi === $socialHero, "api='$heroFromApi' disk='$socialHero'");

test_assert('social has social_posts collection', is_dir($root . '/content/collections/social_posts'));
test_assert('blog collection removed after social install', !is_dir($root . '/content/collections/blog'));

journey_prepare_reference_demo('yoga');
test_assert('yoga has events collection', is_dir($root . '/content/collections/events'));
test_assert('social_posts removed after yoga install', !is_dir($root . '/content/collections/social_posts'));

exit(test_finish('reference_demo_isolation_test'));
