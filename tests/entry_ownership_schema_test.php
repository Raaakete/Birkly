<?php
/**
 * P75 ownership schema validation tests.
 */

require_once __DIR__ . '/../core/entry_ownership.php';

$failures = 0;

function assert_true(string $label, bool $condition, string $detail = ''): void
{
    global $failures;
    if (!$condition) {
        $failures++;
        fwrite(STDERR, "FAIL: $label" . ($detail !== '' ? " — $detail" : '') . PHP_EOL);
    }
}

$valid = birkly_validate_schema_ownership_fields([
    'fields' => [
        ['name' => 'owner', 'type' => 'user', 'ownership' => true],
        ['name' => 'content', 'type' => 'textarea'],
    ],
]);
assert_true('single ownership field is valid', $valid['valid'] === true);

$invalidType = birkly_validate_schema_ownership_fields([
    'fields' => [
        ['name' => 'owner', 'type' => 'text', 'ownership' => true],
    ],
]);
assert_true('ownership on non-user field is invalid', $invalidType['valid'] === false);

$duplicate = birkly_validate_schema_ownership_fields([
    'fields' => [
        ['name' => 'owner_a', 'type' => 'user', 'ownership' => true],
        ['name' => 'owner_b', 'type' => 'user', 'ownership' => true],
    ],
]);
assert_true('two ownership fields are invalid', $duplicate['valid'] === false);

$collectionError = birkly_validate_collection_ownership_schemas(
    [['name' => 'owner', 'type' => 'user', 'ownership' => true]],
    [
        [
            'slug' => 'comments',
            'fields' => [
                ['name' => 'owner', 'type' => 'text', 'ownership' => true],
            ],
        ],
    ]
);
assert_true(
    'sub-collection ownership validation returns error',
    $collectionError !== null && str_contains($collectionError, 'comments')
);

$publicFields = ['title' => 'Hello', 'owner' => 'spoofed'];
$filtered = birkly_filter_public_submit_fields($publicFields, [
    'fields' => [
        ['name' => 'title', 'type' => 'text', 'public_visible' => true],
        ['name' => 'owner', 'type' => 'user', 'ownership' => true, 'public_visible' => false],
    ],
]);
assert_true('public submit strips hidden owner field', !array_key_exists('owner', $filtered));
assert_true('public submit keeps visible fields', ($filtered['title'] ?? '') === 'Hello');

echo "\nentry_ownership_schema_test: " . ($failures === 0 ? "PASS\n" : "FAIL ($failures)\n");
exit($failures === 0 ? 0 : 1);
