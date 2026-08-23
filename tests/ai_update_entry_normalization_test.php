<?php

declare(strict_types=1);

$testRoot = sys_get_temp_dir() . '/birkly-update-entry-' . uniqid();
mkdir($testRoot . '/content/collections/sponsor-tiers/entries', 0755, true);
mkdir($testRoot . '/content/collections/page-heroes/entries', 0755, true);

file_put_contents($testRoot . '/content/collections/sponsor-tiers/collection.json', json_encode([
    'name' => 'Sponsor Tiers',
    'slug' => 'sponsor-tiers',
    'entry_method' => 'admin_only',
], JSON_PRETTY_PRINT));
file_put_contents($testRoot . '/content/collections/sponsor-tiers/schema.json', json_encode([
    'fields' => [
        ['name' => 'title', 'type' => 'text', 'label' => 'Title'],
        ['name' => 'description', 'type' => 'textarea', 'label' => 'Description'],
    ],
], JSON_PRETTY_PRINT));
file_put_contents($testRoot . '/content/collections/sponsor-tiers/entries/0d1fc37c92fd31b6.json', json_encode([
    'id' => '0d1fc37c92fd31b6',
    'title' => 'Main sponsor slot',
    'slug' => 'main-sponsor-slot',
    'description' => 'Old copy',
    'status' => 'published',
    'created_at' => '2026-01-01T00:00:00+00:00',
    'updated_at' => '2026-01-01T00:00:00+00:00',
], JSON_PRETTY_PRINT));

file_put_contents($testRoot . '/content/collections/page-heroes/collection.json', json_encode([
    'name' => 'Page Heroes',
    'slug' => 'page-heroes',
    'entry_method' => 'admin_only',
], JSON_PRETTY_PRINT));
file_put_contents($testRoot . '/content/collections/page-heroes/schema.json', json_encode([
    'fields' => [
        ['name' => 'title', 'type' => 'text', 'label' => 'Title'],
        ['name' => 'headline_1', 'type' => 'text', 'label' => 'Headline'],
    ],
], JSON_PRETTY_PRINT));
file_put_contents($testRoot . '/content/collections/page-heroes/entries/c94dc9e81d466159.json', json_encode([
    'id' => 'c94dc9e81d466159',
    'title' => 'Sponsors hero',
    'slug' => 'sponsors-hero',
    'headline_1' => 'Old headline',
    'status' => 'published',
    'created_at' => '2026-01-01T00:00:00+00:00',
    'updated_at' => '2026-01-01T00:00:00+00:00',
], JSON_PRETTY_PRINT));

$GLOBALS['__birkly_project_root'] = $testRoot;
$GLOBALS['__HARU_INTERNAL_CALL'] = true;

require_once dirname(__DIR__) . '/api/ai_helpers.php';
require_once dirname(__DIR__) . '/api/ai_approvals.php';

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

$aiUser = ['id' => 'ai_test', 'role' => 'admin'];

$descriptionPayload = [
    'collection' => 'sponsor-tiers',
    'id' => '0d1fc37c92fd31b6',
    'slug' => 'main-sponsor-slot',
    'data' => ['description' => 'New copy here'],
];
$normalizedDescription = birkly_ai_normalize_action_data('update_entry', $descriptionPayload);
assert_true(
    'normalize keeps collection when data contains description field',
    ($normalizedDescription['collection'] ?? '') === 'sponsor-tiers',
    json_encode($normalizedDescription)
);

$descriptionResult = executeUpdateEntry(
    birkly_ai_normalize_action_data('update_entry', $descriptionPayload),
    $aiUser,
    null
);
assert_true('update_entry succeeds with description field', ($descriptionResult['success'] ?? false) === true, json_encode($descriptionResult));
$descriptionEntry = $descriptionResult['data']['entry'] ?? [];
assert_true(
    'description field value updated',
    ($descriptionEntry['description'] ?? '') === 'New copy here',
    json_encode($descriptionEntry)
);

$slugPayload = [
    'collection' => 'page-heroes',
    'id' => 'c94dc9e81d466159',
    'data' => ['headline_1' => 'New copy'],
];
$normalizedSlug = birkly_ai_normalize_action_data('update_entry', $slugPayload);
assert_true(
    'normalize does not copy collection slug into entry slug',
    !array_key_exists('slug', $normalizedSlug) || ($normalizedSlug['slug'] ?? '') !== 'page-heroes',
    json_encode($normalizedSlug)
);

$slugResult = executeUpdateEntry(
    birkly_ai_normalize_action_data('update_entry', $slugPayload),
    $aiUser,
    null
);
assert_true('update_entry succeeds when slug omitted', ($slugResult['success'] ?? false) === true, json_encode($slugResult));
$slugEntry = $slugResult['data']['entry'] ?? [];
assert_true(
    'entry slug preserved when slug omitted from request',
    ($slugEntry['slug'] ?? '') === 'sponsors-hero',
    json_encode($slugEntry)
);
assert_true(
    'requested field updated when slug omitted',
    ($slugEntry['headline_1'] ?? '') === 'New copy',
    json_encode($slugEntry)
);

echo "\nai_update_entry_normalization_test: " . ($failures === 0 ? "PASS\n" : "FAIL ($failures)\n");
exit($failures === 0 ? 0 : 1);
