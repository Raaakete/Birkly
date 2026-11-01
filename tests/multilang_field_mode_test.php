<?php
/**
 * Per-field multilanguage shared/localize mode (entry-level storage).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/entry_multilang.php';
require_once dirname(__DIR__) . '/core/entry_multilang_field_mode.php';

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

assert_true(
    'file field defaults to shared',
    birkly_multilang_field_mode_default('file') === BIRKLY_MULTILANG_MODE_SHARED
);
assert_true(
    'text field defaults to localize',
    birkly_multilang_field_mode_default('text') === BIRKLY_MULTILANG_MODE_LOCALIZE
);

$entryWithMode = [
    'id' => 'e1',
    BIRKLY_MULTILANG_ENTRY_MODES_KEY => ['hero' => 'shared'],
];
$textField = ['name' => 'hero', 'type' => 'text'];
assert_true(
    'entry mode overrides type default',
    birkly_multilang_field_is_shared_for_entry($textField, true, $entryWithMode)
);

$post = [
    BIRKLY_MULTILANG_ENTRY_MODES_KEY => ['cover' => 'shared'],
    'cover' => 'media-top',
    'en' => ['teaser' => 'Hello'],
    'de' => ['teaser' => 'Hallo'],
];
$fields = [
    ['name' => 'cover', 'type' => 'file'],
    ['name' => 'teaser', 'type' => 'text'],
];
$modes = birkly_multilang_entry_field_modes_from_post($post, $fields, null, true);
assert_true(
    'POST stores shared mode on entry',
    ($modes['cover'] ?? '') === 'shared'
);
assert_true(
    'POST omits unchecked fields from explicit shared list',
    !isset($modes['teaser']) || $modes['teaser'] === BIRKLY_MULTILANG_MODE_LOCALIZE
);

$entryOutput = [
    'id' => 'e1',
    BIRKLY_MULTILANG_ENTRY_MODES_KEY => $modes,
    'en' => [],
    'de' => [],
];
$out = birkly_entry_apply_multilang_fields(
    $entryOutput,
    $post,
    $fields,
    ['en', 'de'],
    null,
    'e1'
);
assert_true(
    'shared field fan-out to en',
    ($out['en']['cover'] ?? null) === 'media-top'
);
assert_true(
    'shared field fan-out to de',
    ($out['de']['cover'] ?? null) === 'media-top'
);
assert_true(
    'localize field stays per language',
    ($out['en']['teaser'] ?? null) === 'Hello' && ($out['de']['teaser'] ?? null) === 'Hallo'
);

require_once dirname(__DIR__) . '/core/public_read.php';
$publicEntry = [
    'id' => 'e1',
    'slug' => 'e1',
    BIRKLY_MULTILANG_ENTRY_MODES_KEY => ['cover' => 'shared'],
    'en' => ['cover' => 'canonical', 'teaser' => 'EN'],
    'de' => ['cover' => 'stale', 'teaser' => 'DE'],
];
$merged = birkly_public_transform_entry_for_language_with_schema(
    $publicEntry,
    'de',
    true,
    ['en', 'de'],
    $fields
);
assert_true(
    'public merge uses canonical shared value',
    ($merged['cover'] ?? null) === 'canonical'
);
assert_true(
    'public merge keeps localized teaser',
    ($merged['teaser'] ?? null) === 'DE'
);

assert_true(
    'dynamicitems wrapper is never shared',
    !birkly_multilang_field_is_shared_for_entry(['name' => 'content', 'type' => 'dynamicitems'], true, [])
);

$nestedPost = [
    BIRKLY_MULTILANG_ENTRY_MODES_KEY => [
        'content' => ['before_text' => 'shared'],
    ],
    'en' => ['content' => json_encode([['name' => 'before_text', 'type' => 'text', 'value' => 'Hello']])],
    'de' => ['content' => json_encode([['name' => 'before_text', 'type' => 'text', 'value' => 'Alt']])],
];
$dynamicFields = [
    ['name' => 'content', 'type' => 'dynamicitems'],
];
$nestedModes = birkly_multilang_entry_field_modes_from_post($nestedPost, $dynamicFields, null, true);
assert_true(
    'nested POST stores block mode key',
    ($nestedModes['content.before_text'] ?? '') === 'shared'
);

$dynamicOut = [
    'id' => 'e2',
    BIRKLY_MULTILANG_ENTRY_MODES_KEY => $nestedModes,
    'en' => [
        'content' => [
            ['name' => 'before_text', 'type' => 'text', 'value' => 'Hello'],
        ],
    ],
    'de' => [
        'content' => [
            ['name' => 'before_text', 'type' => 'text', 'value' => 'Alt'],
        ],
    ],
];
$dynamicOut = birkly_multilang_entry_apply_shared_dynamic_block_values(
    $dynamicOut,
    $dynamicFields,
    ['en', 'de'],
    $nestedModes
);
assert_true(
    'shared dynamic block fan-out to de',
    ($dynamicOut['de']['content'][0]['value'] ?? null) === 'Hello'
);

$daEnModes = ['content.background' => 'shared'];
$existingLocalized = [
    'id' => 'e3',
    BIRKLY_MULTILANG_ENTRY_MODES_KEY => $daEnModes,
    'da' => [
        'content' => [
            ['name' => 'background', 'type' => 'file', 'value' => 'media-old-da'],
            ['name' => 'title', 'type' => 'text', 'value' => 'DA title'],
        ],
    ],
    'en' => [
        'content' => [
            ['name' => 'background', 'type' => 'file', 'value' => 'media-new-en'],
            ['name' => 'title', 'type' => 'text', 'value' => 'EN title'],
        ],
    ],
];
$savedOutput = [
    'id' => 'e3',
    BIRKLY_MULTILANG_ENTRY_MODES_KEY => $daEnModes,
    'da' => $existingLocalized['da'],
    'en' => $existingLocalized['en'],
];
$savedOutput = birkly_multilang_entry_apply_shared_dynamic_block_values(
    $savedOutput,
    [['name' => 'content', 'type' => 'dynamicitems']],
    ['da', 'en'],
    $daEnModes,
    $existingLocalized
);
assert_true(
    'shared file block save prefers edited language over first tab',
    ($savedOutput['da']['content'][0]['value'] ?? null) === 'media-new-en'
        && ($savedOutput['en']['content'][0]['value'] ?? null) === 'media-new-en'
);
assert_true(
    'shared save leaves localized text blocks untouched',
    ($savedOutput['da']['content'][1]['value'] ?? null) === 'DA title'
        && ($savedOutput['en']['content'][1]['value'] ?? null) === 'EN title'
);

$publicDynamicFields = [['name' => 'content', 'type' => 'dynamicitems']];
$publicEntry = [
    'id' => 'e3',
    BIRKLY_MULTILANG_ENTRY_MODES_KEY => $daEnModes,
    'da' => $existingLocalized['da'],
    'en' => $existingLocalized['en'],
];
$publicDa = birkly_public_transform_entry_for_language_with_schema(
    $publicEntry,
    'da',
    true,
    ['da', 'en'],
    $publicDynamicFields
);
assert_true(
    'public read canonicalizes shared dynamic file block',
    ($publicDa['content'][0]['value'] ?? null) === 'media-new-en'
);

$flatModesPost = [
    'multilang_field_modes[cover]' => 'shared',
    'multilang_field_modes[content][before_text]' => 'shared',
    'en' => ['teaser' => 'Hi'],
];
$normalizedFlat = birkly_entry_data_normalize_multilang_post_keys($flatModesPost);
assert_true(
    'flat bracket keys normalize to multilang_field_modes tree',
    is_array($normalizedFlat['multilang_field_modes'] ?? null)
        && ($normalizedFlat['multilang_field_modes']['cover'] ?? '') === 'shared'
        && ($normalizedFlat['multilang_field_modes']['content']['before_text'] ?? '') === 'shared'
        && !isset($normalizedFlat['multilang_field_modes[cover]'])
);

exit($failures > 0 ? 1 : 0);
