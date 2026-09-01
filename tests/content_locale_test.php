<?php
/**
 * Content locale helpers — date formats and translatable option labels.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/content_locale.php';
require_once dirname(__DIR__) . '/core/entry_slug.php';

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
    'default date format en',
    birkly_locale_default_date_format('en') === 'm/d/Y'
);
assert_true(
    'default date format ja',
    birkly_locale_default_date_format('ja') === 'Y/m/d'
);
assert_true(
    'default datetime format de',
    birkly_locale_default_datetime_format('de') === 'd.m.Y H:i'
);
assert_true(
    'locale field format uses default when empty',
    birkly_locale_field_display_format(['format' => ''], 'zh', 'date') === 'Y/m/d'
);
assert_true(
    'locale field format respects explicit schema',
    birkly_locale_field_display_format(['format' => 'd/m/Y'], 'en', 'date') === 'd/m/Y'
);
assert_true(
    'format stored date de locale',
    birkly_locale_format_stored_date('2026-09-01', 'd.m.Y') === '01.09.2026'
);

$options = [
    ['value' => 'small', 'label' => 'Small', 'labels' => ['zh' => '小', 'ja' => '小さい']],
];
assert_true(
    'option label zh',
    birkly_selection_option_label($options[0], 'zh') === '小'
);
assert_true(
    'option label fallback',
    birkly_selection_option_label($options[0], 'fr') === 'Small'
);
$resolved = birkly_selection_resolve_options($options, 'zh');
assert_true(
    'resolved options label zh',
    ($resolved[0]['label'] ?? '') === '小'
);

assert_true(
    'slug lacks ascii for cjk title',
    birkly_slug_title_lacks_ascii_slug_chars('日本語タイトル')
);
assert_true(
    'slug ascii title ok',
    !birkly_slug_title_lacks_ascii_slug_chars('Hello 世界')
);
assert_true(
    'slug id fallback detection',
    birkly_slug_is_id_fallback_for_unicode_title('日本語', 'abc123', 'abc123')
);

exit($failures > 0 ? 1 : 0);
