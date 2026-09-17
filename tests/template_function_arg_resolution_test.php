<?php
/**
 * P89: SSR template-function arg resolution (slug, field refs).
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/plugins/template_functions.php';
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

register_template_function('echo_args', static function (...$args) {
    return json_encode($args, JSON_UNESCAPED_SLASHES);
});

$data = [
    'slug' => 'dinkelbrot',
    'entry' => [
        'slug' => 'dinkelbrot',
        'commerce' => [
            'purchase_options' => [
                ['id' => 'once', 'billing' => 'one_time', 'price_cents' => 650],
            ],
        ],
    ],
    'commerce' => [
        'purchase_options' => [
            ['id' => 'once', 'billing' => 'one_time', 'price_cents' => 650],
        ],
    ],
];

$slugProducts = birkly_template_engine_render('{echo_args slug products}', $data, ['escapeHtml' => false]);
assert_true(
    'bare slug resolves from detail context',
    str_contains($slugProducts, 'dinkelbrot') && str_contains($slugProducts, 'products'),
    $slugProducts
);

$field = birkly_template_engine_render("{echo_args {'commerce'}}", $data, ['escapeHtml' => false]);
assert_true('field ref resolves commerce array', str_contains($field, 'purchase_options'), $field);

echo "\ntemplate_function_arg_resolution_test: " . ($failures === 0 ? "PASS\n" : "FAIL ($failures)\n");
exit($failures === 0 ? 0 : 1);
