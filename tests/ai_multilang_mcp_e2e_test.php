<?php

declare(strict_types=1);

/**
 * End-to-end MCP path: create collection → enable multilanguage → create entry (en) → update_entry (de) → read back.
 * Mirrors the disposable i18n-write-test-temp flow requested for live MCP verification.
 */

$testRoot = sys_get_temp_dir() . '/birkly-multilang-mcp-e2e-' . uniqid();
$collectionSlug = 'i18n-write-test-temp';
$GLOBALS['__birkly_project_root'] = $testRoot;
$GLOBALS['__HARU_INTERNAL_CALL'] = true;

require_once dirname(__DIR__) . '/api/ai_helpers.php';
require_once dirname(__DIR__) . '/api/ai_approvals.php';
require_once dirname(__DIR__) . '/core/ai_write_validation.php';
require_once dirname(__DIR__) . '/core/ai_collection_config.php';

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

function echo_step(string $label, array $request, array $response): void
{
    echo "\n--- {$label} ---\n";
    echo "REQUEST:\n" . json_encode($request, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    echo "RESPONSE:\n" . json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}

$aiUser = ['id' => 'ai_e2e_test', 'role' => 'admin'];

// Step 1: create_collection
$createCollectionRequest = [
    'name' => 'I18n write test temp',
    'slug' => $collectionSlug,
    'fields' => [
        ['name' => 'title', 'type' => 'text', 'label' => 'Title', 'required' => true],
        ['name' => 'body', 'type' => 'textarea', 'label' => 'Body'],
    ],
];
$createCollectionResponse = executeCreateCollection($createCollectionRequest, $aiUser, null);
echo_step('create_collection', $createCollectionRequest, $createCollectionResponse);
assert_true('create_collection succeeds', ($createCollectionResponse['success'] ?? false) === true, json_encode($createCollectionResponse));

// Step 2: update_collection — enable multilanguage
$updateCollectionRequest = [
    'slug' => $collectionSlug,
    'multilanguage' => [
        'enabled' => true,
        'languages' => ['en', 'de'],
    ],
];
$updateCollectionResponse = executeUpdateCollection($updateCollectionRequest, $aiUser, null);
echo_step('update_collection (enable multilanguage)', $updateCollectionRequest, $updateCollectionResponse);
assert_true('update_collection multilanguage succeeds', ($updateCollectionResponse['success'] ?? false) === true, json_encode($updateCollectionResponse));
$storedCollection = $updateCollectionResponse['data']['collection'] ?? [];
assert_true(
    'read back multilanguage.enabled',
    !empty($storedCollection['multilanguage']['enabled']),
    json_encode($storedCollection['multilanguage'] ?? null)
);
assert_true(
    'read back multilanguage.languages',
    ($storedCollection['multilanguage']['languages'] ?? []) === ['en', 'de'],
    json_encode($storedCollection['multilanguage'] ?? null)
);

// Step 3: create_entry with en block
$createEntryRequest = [
    'collection' => $collectionSlug,
    'title' => 'Test EN',
    'status' => 'published',
    'en' => [
        'title' => 'Test EN',
        'body' => 'English body',
    ],
];
$createEntryResponse = executeCreateEntry(
    birkly_ai_normalize_action_data('create_entry', $createEntryRequest),
    $aiUser,
    null
);
echo_step('create_entry (en block)', $createEntryRequest, $createEntryResponse);
assert_true('create_entry succeeds', ($createEntryResponse['success'] ?? false) === true, json_encode($createEntryResponse));

$entryId = (string) ($createEntryResponse['data']['entry_id'] ?? '');
$entrySlug = (string) ($createEntryResponse['data']['entry']['slug'] ?? '');
assert_true('entry id returned', $entryId !== '', json_encode($createEntryResponse['data'] ?? null));
assert_true('entry slug returned', $entrySlug !== '', json_encode($createEntryResponse['data'] ?? null));

// Step 4: update_entry — add de block
$updateEntryRequest = [
    'collection' => $collectionSlug,
    'id' => $entryId,
    'slug' => $entrySlug,
    'de' => [
        'title' => 'Test DE',
        'body' => 'Test body',
    ],
];
$updateEntryResponse = executeUpdateEntry(
    birkly_ai_normalize_action_data('update_entry', $updateEntryRequest),
    $aiUser,
    null
);
echo_step('update_entry (de block)', $updateEntryRequest, $updateEntryResponse);
assert_true('update_entry de block succeeds', ($updateEntryResponse['success'] ?? false) === true, json_encode($updateEntryResponse));

$writtenEntry = $updateEntryResponse['data']['entry'] ?? [];
assert_true(
    'de title written',
    ($writtenEntry['de']['title'] ?? '') === 'Test DE',
    json_encode($writtenEntry['de'] ?? null)
);
assert_true(
    'de body written',
    ($writtenEntry['de']['body'] ?? '') === 'Test body',
    json_encode($writtenEntry['de'] ?? null)
);
assert_true(
    'en block preserved after de update',
    ($writtenEntry['en']['title'] ?? '') === 'Test EN'
    && ($writtenEntry['en']['body'] ?? '') === 'English body',
    json_encode($writtenEntry['en'] ?? null)
);

// Step 5: read_entry from disk (same as read_entry MCP would return)
$entryFile = $testRoot . '/content/collections/' . $collectionSlug . '/entries/' . $entryId . '.json';
$readBack = is_file($entryFile) ? json_decode((string) file_get_contents($entryFile), true) : null;
$readEntryResponse = [
    'success' => is_array($readBack),
    'data' => ['entry' => $readBack],
];
echo_step('read_entry (from storage)', ['collection' => $collectionSlug, 'id' => $entryId], $readEntryResponse);
assert_true('read back entry file exists', is_array($readBack), $entryFile);
assert_true(
    'read back en untouched',
    ($readBack['en']['title'] ?? '') === 'Test EN'
    && ($readBack['en']['body'] ?? '') === 'English body',
    json_encode($readBack['en'] ?? null)
);
assert_true(
    'read back de merged',
    ($readBack['de']['title'] ?? '') === 'Test DE'
    && ($readBack['de']['body'] ?? '') === 'Test body',
    json_encode($readBack['de'] ?? null)
);

echo "\nai_multilang_mcp_e2e_test: " . ($failures === 0 ? "PASS\n" : "FAIL ($failures)\n");
exit($failures === 0 ? 0 : 1);
