<?php
/**
 * P81: SSR template functions (render_mentions, user_logged_in) and dotted date paths.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/plugins/template_functions.php';
require_once dirname(__DIR__) . '/core/birkly_template_engine.php';

function assert_true(string $label, bool $ok, string $detail = ''): void
{
    if (!$ok) {
        fwrite(STDERR, "FAIL: $label" . ($detail !== '' ? " — $detail" : '') . "\n");
        exit(1);
    }
    echo "OK: $label\n";
}

register_template_function('render_mentions', static function ($text) {
    if (!is_string($text) || $text === '') {
        return '';
    }
    return '<span class="mention">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</span>';
});

register_template_function('user_logged_in', static function () {
    return true;
});

$data = [
    'body' => 'Hello @world',
    'user_logged_in' => true,
    'comment' => [
        'created_at' => '2026-09-04T11:22:09+00:00',
        'body' => 'A comment',
    ],
];

$mentionsHtml = birkly_template_engine_render("{render_mentions {'body'}}", $data, ['escapeHtml' => true]);
assert_true(
    'render_mentions resolves field arg',
    str_contains($mentionsHtml, 'mention') && str_contains($mentionsHtml, 'Hello @world'),
    $mentionsHtml
);

$dateHtml = birkly_template_engine_render('{comment.created_at format "M j, Y"}', $data, ['escapeHtml' => true]);
assert_true(
    'dotted context path supports date format',
    str_contains($dateHtml, 'Sep 4, 2026'),
    $dateHtml
);

$ifHtml = birkly_template_engine_render('{if user_logged_in}yes{else}no{endif}', $data, ['escapeHtml' => true]);
assert_true('user_logged_in condition renders true branch', $ifHtml === 'yes', $ifHtml);

echo "template_function_ssr_test: all passed\n";
