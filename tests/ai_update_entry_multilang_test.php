<?php

declare(strict_types=1);

$testRoot = sys_get_temp_dir() . '/birkly-update-entry-multilang-' . uniqid();
mkdir($testRoot . '/content/collections/legal-pages/entries', 0755, true);

file_put_contents($testRoot . '/content/collections/legal-pages/collection.json', json_encode([
    'name' => 'Legal Pages',
    'slug' => 'legal-pages',
    'entry_method' => 'admin_only',
    'multilanguage' => [
        'enabled' => true,
        'languages' => ['en', 'de'],
    ],
], JSON_PRETTY_PRINT));

file_put_contents($testRoot . '/content/collections/legal-pages/schema.json', json_encode([
    'fields' => [
        ['name' => 'title', 'type' => 'text', 'label' => 'Title'],
        ['name' => 'body', 'type' => 'textarea', 'label' => 'Body'],
    ],
], JSON_PRETTY_PRINT));

file_put_contents($testRoot . '/content/collections/legal-pages/entries/21e42b1c6d5a80ab.json', json_encode([
    'id' => '21e42b1c6d5a80ab',
    'title' => 'Privacy policy',
    'slug' => 'privacy-policy',
    'status' => 'published',
    'created_at' => '2026-01-01T00:00:00+00:00',
    'updated_at' => '2026-01-01T00:00:00+00:00',
    'en' => [
        'title' => 'Privacy policy',
        'body' => 'English body',
    ],
], JSON_PRETTY_PRINT));

$GLOBALS['__birkly_project_root'] = $testRoot;
$GLOBALS['__HARU_INTERNAL_CALL'] = true;

require_once dirname(__DIR__) . '/api/ai_helpers.php';
require_once dirname(__DIR__) . '/api/ai_approvals.php';
require_once dirname(__DIR__) . '/core/ai_write_validation.php';

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

$dePayload = [
    'collection' => 'legal-pages',
    'id' => '21e42b1c6d5a80ab',
    'slug' => 'privacy-policy',
    'de' => [
        'title' => 'Datenschutzerklärung',
        'body' => 'Deutscher Text',
    ],
];

$normalized = birkly_ai_normalize_action_data('update_entry', $dePayload);
$unknownKeys = birkly_ai_reject_unknown_keys(
    $normalized,
    birkly_ai_entry_write_allowed_keys_for_collection('legal-pages'),
    'update_entry'
);
assert_true(
    'allows configured language code de after normalization',
    $unknownKeys === null,
    (string) $unknownKeys
);

$deResult = executeUpdateEntry(
    birkly_ai_normalize_action_data('update_entry', $dePayload),
    $aiUser,
    null
);
assert_true('update_entry succeeds with de language block', ($deResult['success'] ?? false) === true, json_encode($deResult));

$written = $deResult['data']['entry'] ?? [];
assert_true(
    'de title written',
    ($written['de']['title'] ?? '') === 'Datenschutzerklärung',
    json_encode($written['de'] ?? null)
);
assert_true(
    'de body written',
    ($written['de']['body'] ?? '') === 'Deutscher Text',
    json_encode($written['de'] ?? null)
);
assert_true(
    'en block preserved when updating de only',
    ($written['en']['title'] ?? '') === 'Privacy policy'
    && ($written['en']['body'] ?? '') === 'English body',
    json_encode($written['en'] ?? null)
);
assert_true(
    'shared slug preserved',
    ($written['slug'] ?? '') === 'privacy-policy',
    json_encode($written)
);

$partialDePayload = [
    'collection' => 'legal-pages',
    'id' => '21e42b1c6d5a80ab',
    'de' => [
        'body' => 'Aktualisierter Text',
    ],
];
$partialResult = executeUpdateEntry(
    birkly_ai_normalize_action_data('update_entry', $partialDePayload),
    $aiUser,
    null
);
$partialEntry = $partialResult['data']['entry'] ?? [];
assert_true('partial de update succeeds', ($partialResult['success'] ?? false) === true, json_encode($partialResult));
assert_true(
    'partial de update merges body without wiping de title',
    ($partialEntry['de']['body'] ?? '') === 'Aktualisierter Text'
    && ($partialEntry['de']['title'] ?? '') === 'Datenschutzerklärung',
    json_encode($partialEntry['de'] ?? null)
);

$dataWrapperPayload = [
    'collection' => 'legal-pages',
    'id' => '21e42b1c6d5a80ab',
    'slug' => 'privacy-policy',
    'data' => [
        'de' => [
            'body' => 'Via data wrapper',
        ],
    ],
];
$dataWrapperResult = executeUpdateEntry(
    birkly_ai_normalize_action_data('update_entry', $dataWrapperPayload),
    $aiUser,
    null
);
$dataWrapperEntry = $dataWrapperResult['data']['entry'] ?? [];
assert_true('update via data wrapper succeeds', ($dataWrapperResult['success'] ?? false) === true, json_encode($dataWrapperResult));
assert_true(
    'data wrapper de body merged',
    ($dataWrapperEntry['de']['body'] ?? '') === 'Via data wrapper',
    json_encode($dataWrapperEntry['de'] ?? null)
);

echo "\nai_update_entry_multilang_test: " . ($failures === 0 ? "PASS\n" : "FAIL ($failures)\n");
exit($failures === 0 ? 0 : 1);
