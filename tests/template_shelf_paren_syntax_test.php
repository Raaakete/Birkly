<?php
/**
 * Shelf helpers use parenthesis call syntax in docs: {gallery_html('photos')}.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/fieldtype_presentation.php';
require_once dirname(__DIR__) . '/core/birkly_template_engine.php';

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

birkly_fieldtype_presentation_boot();

register_template_function('echo_args', static function (...$args) {
    return json_encode($args, JSON_UNESCAPED_SLASHES);
});

$paren = birkly_template_engine_render("{echo_args('hero_image', title)}", [
    'entry' => ['hero_image' => 'media-1', 'title' => 'Alt label'],
    'hero_image' => 'media-1',
    'title' => 'Alt label',
], ['escapeHtml' => false]);
assert_true(
    'paren template function resolves field refs',
    str_contains($paren, 'media-1') && str_contains($paren, 'Alt label'),
    $paren
);

$md = birkly_template_engine_render(
    "<div>{'body_markdown'}</div>",
    [
        'entry' => ['body_markdown' => '# Heading'],
        'body_markdown' => '# Heading',
        '_field_types' => ['body_markdown' => 'markdown'],
        '_html_fields' => ['body_markdown'],
    ]
);
assert_true(
    'top-level merged entry field uses markdown formatter',
    str_contains($md, '<h1>Heading</h1>') && !str_contains($md, '# Heading'),
    $md
);

$pw = birkly_template_engine_render(
    "<p>{'api_secret'}</p>",
    [
        'entry' => ['api_secret' => 'plain-secret'],
        'api_secret' => 'plain-secret',
        '_field_types' => ['api_secret' => 'password'],
    ]
);
assert_true('password field masked on storefront leaf', $pw === '<p>••••••</p>', $pw);

echo "\ntemplate_shelf_paren_syntax_test: " . ($failures === 0 ? "PASS\n" : "FAIL ($failures)\n");
exit($failures === 0 ? 0 : 1);
