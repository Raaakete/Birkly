<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/api/admin_templates.php';
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

$collection = [
    'multilanguage' => [
        'enabled' => true,
        'languages' => ['en', 'de'],
    ],
];

$fields = [
    ['name' => 'title', 'type' => 'text', 'label' => 'Title'],
    ['name' => 'excerpt', 'type' => 'textarea', 'label' => 'Excerpt'],
    ['name' => 'body', 'type' => 'contenteditor', 'label' => 'Body'],
    ['name' => 'hero_image', 'type' => 'image', 'label' => 'Hero image'],
];

$entry = [
    'id' => '1',
    BIRKLY_MULTILANG_ENTRY_MODES_KEY => [
        'hero_image' => BIRKLY_MULTILANG_MODE_SHARED,
    ],
];

$segments = birkly_admin_build_multilang_segments($fields, $entry, $collection, false);

assert_true('two segments when shared is last', count($segments) === 2);
assert_true('first segment is localized', ($segments[0]['kind'] ?? '') === 'localized');
assert_true('second segment is shared', ($segments[1]['kind'] ?? '') === 'shared');

$sharedFields = $segments[1]['fields'] ?? [];
assert_true('shared segment has hero_image', ($sharedFields[0]['name'] ?? '') === 'hero_image');

$fieldsMiddleShared = [
    ['name' => 'title', 'type' => 'text', 'label' => 'Title'],
    ['name' => 'excerpt', 'type' => 'textarea', 'label' => 'Excerpt'],
    ['name' => 'hero_image', 'type' => 'image', 'label' => 'Hero image'],
    ['name' => 'body', 'type' => 'contenteditor', 'label' => 'Body'],
];

$segmentsMiddle = birkly_admin_build_multilang_segments($fieldsMiddleShared, $entry, $collection, false);
assert_true('three segments when shared is in the middle', count($segmentsMiddle) === 3);
assert_true('middle segment is shared', ($segmentsMiddle[1]['kind'] ?? '') === 'shared');

$template = (string) file_get_contents(dirname(__DIR__) . '/admin/templates/entry-editor.html');
assert_true('template uses multilang_segments', str_contains($template, 'multilang_segments'));
assert_true('template removed hoisted entry-shared-fields block', !str_contains($template, 'entry-shared-fields'));

echo PHP_EOL . 'entry_editor_multilang_layout_test: ' . ($failures === 0 ? 'PASS' : "FAIL ($failures)") . PHP_EOL;
exit($failures > 0 ? 1 : 0);