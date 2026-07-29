<?php
/**
 * DynamicItems multilang HTML rewrite and render_for_dynamicitems smoke tests (P50-M3).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/api/admin_templates.php';

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

function assert_contains(string $name, string $haystack, string $needle): void
{
    assert_true($name, str_contains($haystack, $needle), "expected substring: {$needle}");
}

function assert_not_contains(string $name, string $haystack, string $needle): void
{
    assert_true($name, !str_contains($haystack, $needle), "unexpected substring: {$needle}");
}

// --- Multilang rewrite for DynamicItems markup ---

require_once dirname(__DIR__) . '/fieldtypes/DynamicItems/DynamicItems.php';

$config = [
    'name' => 'blocks',
    'label' => 'Blocks',
    'required' => false,
    'value' => [],
];
$field = new Birkly\FieldTypes\DynamicItems\DynamicItems($config);
$rawHtml = $field->render();

assert_contains('render includes showAddBlockModal', $rawHtml, "showAddBlockModal('blocks')");
assert_contains('render includes field_blocks id', $rawHtml, 'id="field_blocks"');

$enHtml = birkly_admin_multilang_field_html($rawHtml, 'blocks', 'en');

assert_contains('multilang container id', $enHtml, 'id="field_en_blocks"');
assert_contains('multilang hidden value id', $enHtml, 'id="field_en_blocks_value"');
assert_contains('multilang onclick field name', $enHtml, "showAddBlockModal('en_blocks')");
assert_not_contains('multilang stale bare onclick', $enHtml, "showAddBlockModal('blocks')");
assert_contains('multilang data-field-config name', $enHtml, '"name":"en_blocks"');
assert_contains('multilang input name', $enHtml, 'name="en[blocks]"');

$deHtml = birkly_admin_multilang_field_html($rawHtml, 'blocks', 'de');
assert_contains('de onclick field name', $deHtml, "showAddBlockModal('de_blocks')");

// --- render_for_dynamicitems (via renderFieldtype smoke) ---

require_once dirname(__DIR__) . '/api/admin_fieldtypes.php';
$GLOBALS['__HARU_INTERNAL_CALL'] = true;

$textRender = renderFieldtype('text', [
    'name' => 'headline',
    'label' => 'Headline',
    'required' => true,
    'value' => 'Hello',
]);
assert_true(
    'render_for_dynamicitems text via renderFieldtype',
    is_array($textRender) && !empty($textRender['html']) && empty($textRender['error']),
    json_encode($textRender)
);

$renderCases = [
    'textarea' => ['class' => 'Birkly\\FieldTypes\\Textarea\\Textarea', 'value' => 'Sample'],
    'number' => ['class' => 'Birkly\\FieldTypes\\Number\\Number', 'value' => '42'],
];

foreach ($renderCases as $type => $case) {
    $className = $case['class'];
    if (!class_exists($className)) {
        $phpFile = dirname(__DIR__) . '/fieldtypes/' . ucfirst($type) . '/' . ucfirst($type) . '.php';
        if (is_file($phpFile)) {
            require_once $phpFile;
        }
    }
    assert_true("fieldtype class exists: {$type}", class_exists($className));
    $instance = new $className([
        'name' => 'sample_' . $type,
        'label' => ucfirst($type),
        'required' => false,
        'value' => $case['value'],
    ]);
    $html = $instance->render();
    assert_true("render_for_dynamicitems {$type} html non-empty", $html !== '');
}

exit($failures > 0 ? 1 : 0);
