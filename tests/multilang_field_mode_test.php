<?php
/**
 * Per-field multilanguage shared/localize mode (platform layer).
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

$schemaField = ['name' => 'hero', 'type' => 'text', 'multilanguage_mode' => 'shared'];
assert_true(
    'honours stored shared mode when multilang on',
    birkly_multilang_field_is_shared($schemaField, true)
);
assert_true(
    'multilang off ignores shared flag',
    !birkly_multilang_field_is_shared($schemaField, false)
);

$entry = [
    'id' => 'e1',
    'en' => ['cover' => 'media-en'],
    'de' => ['cover' => 'media-de'],
];
assert_true(
    'stored shared reads first language block',
    birkly_multilang_stored_shared_field_value($entry, 'cover', ['en', 'de']) === 'media-en'
);

$post = ['cover' => 'media-top'];
assert_true(
    'shared POST reads top-level field name',
    birkly_multilang_shared_field_raw_from_post($post, 'cover', ['en', 'de']) === 'media-top'
);

$fields = [
    ['name' => 'cover', 'type' => 'text', 'multilanguage_mode' => 'shared'],
    ['name' => 'teaser', 'type' => 'text', 'multilanguage_mode' => 'localize'],
];
$entryOutput = ['id' => 'e1', 'en' => [], 'de' => []];
$entryData = [
    'cover' => 'one-image',
    'en' => ['teaser' => 'Hello'],
    'de' => ['teaser' => 'Hallo'],
];
$out = birkly_entry_apply_multilang_fields(
    $entryOutput,
    $entryData,
    $fields,
    ['en', 'de'],
    null,
    'e1'
);
assert_true(
    'shared field fan-out to en',
    ($out['en']['cover'] ?? null) === 'one-image'
);
assert_true(
    'shared field fan-out to de',
    ($out['de']['cover'] ?? null) === 'one-image'
);
assert_true(
    'localize field stays per language',
    ($out['en']['teaser'] ?? null) === 'Hello' && ($out['de']['teaser'] ?? null) === 'Hallo'
);

require_once dirname(__DIR__) . '/core/public_read.php';
$publicEntry = [
    'id' => 'e1',
    'slug' => 'e1',
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

exit($failures > 0 ? 1 : 0);
