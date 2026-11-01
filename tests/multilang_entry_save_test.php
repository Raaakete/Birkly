<?php
/**
 * Multi-language entry save helpers.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/entry_multilang.php';
require_once dirname(__DIR__) . '/core/public_read.php';

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

$post = [
    'en' => [
        'title' => 'English title',
        'blocks' => '[{"type":"text","name":"hero","label":"Hero","value":"Hello"}]',
    ],
    'de' => [
        'title' => 'Deutscher Titel',
        'blocks' => '[{"type":"text","name":"hero","label":"Hero","value":"Hallo"}]',
    ],
];

assert_true(
    'reads nested en title',
    birkly_entry_lang_field_raw($post, 'en', 'title') === 'English title'
);
assert_true(
    'reads nested de blocks',
    birkly_entry_lang_field_raw($post, 'de', 'blocks') !== null
);
assert_true(
    'flat bracket fallback',
    birkly_entry_lang_field_raw(['en[title]' => 'Flat title'], 'en', 'title') === 'Flat title'
);

$missingLang = ['en' => ['title' => 'English only'], 'de' => ['title' => 'Deutsch']];
assert_true(
    'transform falls back to first configured language block',
    (birkly_public_transform_entry_for_language($missingLang, 'da', true, ['en', 'de'])['title'] ?? '') === 'English only'
);

$entryOutput = [
    'id' => 'entry123',
    'en' => ['title' => 'English title'],
    'de' => ['title' => 'Deutscher Titel'],
];
$title = birkly_entry_resolve_top_level_title($entryOutput, [], ['en', 'de'], 'entry123', null);
assert_true('resolves top-level title from default language', $title === 'English title');

$placeholderEntry = ['id' => 'entry123', 'title' => 'entry123', 'en' => ['title' => 'Real title']];
assert_true(
    'detects placeholder title',
    birkly_entry_title_is_placeholder('entry123', $placeholderEntry)
);

$titleHtml = '<input type="text" name="de[title]" id="field_de_title" value="entry123" class="input">';
$localized = birkly_entry_multilang_apply_field_value($titleHtml, 'title', 'de', 'Deutscher Titel');
assert_true(
    'applies per-language title value to field HTML',
    strpos($localized, 'value="Deutscher Titel"') !== false
);

$blocksHtml = '<input type="hidden" name="de[blocks]" id="field_de_blocks_value" value="[]">';
$localizedBlocks = birkly_entry_multilang_apply_field_value(
    $blocksHtml,
    'blocks',
    'de',
    '[{"type":"text","name":"hero","value":"Hallo"}]'
);
assert_true(
    'applies per-language dynamic items hidden value',
    strpos($localizedBlocks, 'Hallo') !== false
);

exit($failures > 0 ? 1 : 0);
