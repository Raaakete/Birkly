<?php
/**
 * Fieldtype presentation layer (markdown HTML, masking, template shelf).
 *
 * Run: php tests/fieldtype_presentation_test.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/fieldtype_presentation.php';
require_once dirname(__DIR__) . '/fieldtypes/Markdown/markdown_render.php';
require_once dirname(__DIR__) . '/core/birkly_template_engine.php';
require_once dirname(__DIR__) . '/core/entry_multilang.php';

$failures = 0;
$total = 0;

function assert_true(string $name, bool $ok, string $detail = ''): void
{
    global $failures, $total;
    $total++;
    echo '  ' . $name . ': ' . ($ok ? "PASS\n" : "FAIL\n");
    if (!$ok) {
        $failures++;
        if ($detail !== '') {
            echo '    ' . $detail . "\n";
        }
    }
}

echo "\n[FP-01] Markdown renders trusted HTML\n";
$html = birkly_markdown_render("**Hello**\n\n- one\n- two");
assert_true('strong tag present', str_contains($html, '<strong>Hello</strong>'));
assert_true('list items present', str_contains($html, '<li>one</li>'));

echo "\n[FP-02] Template engine formats multiselect leaf\n";
$rendered = birkly_template_engine_render(
    "<p>{'tags'}</p>",
    [
        'entry' => ['tags' => ['design', 'cms']],
        '_field_types' => ['tags' => 'tags'],
    ]
);
assert_true('tags joined for direct field tag', $rendered === '<p>design, cms</p>', 'got: ' . var_export($rendered, true));

echo "\n[FP-03] Markdown field outputs raw HTML with _html_fields\n";
$md = birkly_template_engine_render(
    "<div>{'body'}</div>",
    [
        'entry' => ['body' => '# Title'],
        '_field_types' => ['body' => 'markdown'],
        '_html_fields' => ['body'],
    ]
);
assert_true('markdown heading not escaped', str_contains($md, '<h1>Title</h1>') && !str_contains($md, '# Title'), 'got: ' . $md);

echo "\n[FP-04] Password values masked for API filter\n";
birkly_fieldtype_presentation_register_filters();
$entry = ['password_field' => '$2y$10$abcdefghijklmnopqrstuv'];
$masked = birkly_entry_mask_sensitive_fields($entry, [
    ['name' => 'password_field', 'type' => 'password'],
]);
assert_true('password hash redacted', ($masked['password_field'] ?? '') === '[redacted]');

echo "\n[FP-05] Multilang HTML patch preserves dollar signs in code fields\n";
$sample = '<input id="field_en_code" value="const x = ${name};" />';
$patched = birkly_entry_multilang_apply_field_value($sample, 'code', 'en', 'const x = ${name};');
assert_true('template literal preserved', str_contains($patched, '${name}'), 'got: ' . $patched);

echo "\n[FP-06] Template shelf media_url callable\n";
birkly_fieldtype_presentation_boot();
assert_true('media_url registered', function_exists('get_template_function') && get_template_function('media_url') !== null);
assert_true('gallery_html registered', get_template_function('gallery_html') !== null);
assert_true('dynamicitems_html registered', get_template_function('dynamicitems_html') !== null);

echo "\n[FP-07] DynamicItems markdown block renders HTML\n";
require_once dirname(__DIR__) . '/core/dynamicitems_presentation.php';
$blocksHtml = birkly_dynamicitems_render_html([
    ['type' => 'markdown', 'name' => 'body', 'label' => 'Body', 'value' => '# Hello'],
]);
assert_true('dynamicitems markdown h1', str_contains($blocksHtml, '<h1>Hello</h1>'), 'got: ' . $blocksHtml);

echo "\n[FP-08] Map HTML embed\n";
require_once dirname(__DIR__) . '/core/map_presentation.php';
$mapHtml = birkly_map_render_html(['lat' => '48.137', 'lng' => '11.575', 'label' => 'Munich']);
assert_true('map iframe present', str_contains($mapHtml, 'birkly-map-frame') && str_contains($mapHtml, 'openstreetmap.org'), $mapHtml);

echo "\n[FP-09] MCP field hints attached on read path\n";
$hinted = birkly_entry_attach_mcp_read_hints(
    ['title' => 'T', 'tags' => ['a']],
    'nonexistent-collection-slug-for-test'
);
assert_true('no hints when collection missing', !isset($hinted['_mcp_field_hints']));

echo "\n" . ($failures === 0 ? "All $total tests passed.\n" : "$failures of $total tests FAILED.\n");
exit($failures === 0 ? 0 : 1);
