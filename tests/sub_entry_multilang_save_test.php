<?php
/**
 * Sub-entry multilanguage save normalization (admin JSON path).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/sub_entry_save.php';
require_once dirname(__DIR__) . '/core/entry_multilang.php';

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
    'sub_entry_save can normalize multilang modes without fatal',
    (static function (): bool {
        try {
            birkly_sub_entry_prepare_save_data(
                'pages',
                'sections',
                'sub_normalize_smoke',
                ['multilang_field_modes[hero]' => 'shared', 'title' => 'Smoke'],
                []
            );
            return true;
        } catch (Throwable $e) {
            return false;
        }
    })()
);

assert_true(
    'strip flat bracket keys',
    !array_key_exists(
        'en[cover]',
        birkly_entry_strip_flat_lang_bracket_keys([
            'en' => ['cover' => 'x'],
            'en[cover]' => 'y',
            'title' => 'Ok',
        ])
    )
);

$post = [
    'en[title]' => 'Home — hero',
    'en[cover]' => 'media-id-abc',
    'en[heading]' => 'Birkly',
    'da[cover]' => '',
];

$existing = [
    'id' => 'sub_test123',
    'title' => 'sub_test123',
    'section_key' => 'hero',
    'en' => ['heading' => 'Old heading'],
];

$fields = [
    ['name' => 'cover', 'type' => 'image'],
    ['name' => 'heading', 'type' => 'text'],
];

$result = birkly_entry_apply_multilang_fields(
    $existing,
    $post,
    $fields,
    ['en', 'da'],
    $existing,
    'sub_test123'
);

assert_true(
    'apply multilang resolves title from en[title]',
    ($result['title'] ?? '') === 'Home — hero'
);
assert_true(
    'apply multilang stores cover under en',
    ($result['en']['cover'] ?? '') === 'media-id-abc'
);

exit($failures > 0 ? 1 : 0);
