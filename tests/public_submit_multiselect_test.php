<?php
/**
 * Public submit multiselect/tags coercion tests.
 * Run: php tests/public_submit_multiselect_test.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/public_submit_values.php';

function assert_true(string $label, bool $condition): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    fwrite(STDOUT, "{$label}: PASS\n");
}

function assert_same(string $label, $expected, $actual): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$label}\n");
        fwrite(STDERR, '  expected: ' . json_encode($expected) . "\n");
        fwrite(STDERR, '  actual:   ' . json_encode($actual) . "\n");
        exit(1);
    }
    fwrite(STDOUT, "{$label}: PASS\n");
}

$field = [
    'name' => 'services',
    'type' => 'multiselect',
    'options' => [
        ['value' => 'design', 'label' => 'Design'],
        ['value' => 'development', 'label' => 'Development'],
        ['value' => 'strategy', 'label' => 'Strategy'],
    ],
];

assert_same(
    'checkbox array payload',
    ['design', 'development'],
    birkly_public_submit_coerce_string_list_field($field, ['design', 'development', 'unknown'])
);

assert_same(
    'comma-separated payload',
    ['design', 'development'],
    birkly_public_submit_coerce_string_list_field($field, 'design, development')
);

assert_same(
    'json string payload',
    ['design', 'development'],
    birkly_public_submit_coerce_string_list_field($field, '["design","development"]')
);

assert_same(
    'empty array',
    [],
    birkly_public_submit_coerce_string_list_field($field, [])
);

$tagsField = ['name' => 'topics', 'type' => 'tags'];
assert_same(
    'tags allow free values',
    ['alpha', 'beta'],
    birkly_public_submit_coerce_string_list_field($tagsField, 'alpha, beta')
);

assert_true(
    'required empty multiselect',
    birkly_public_submit_field_value_is_empty('multiselect', [])
);
assert_true(
    'required filled multiselect',
    !birkly_public_submit_field_value_is_empty('multiselect', ['a'])
);

fwrite(STDOUT, "OK\n");