<?php
/**
 * Test script for admin templating directives (canonical syntax).
 * Run: php test-new-template.php
 * Exit 0 = all pass, 1 = one or more failures.
 */

require_once __DIR__ . '/admin/template_parser.php';
require_once __DIR__ . '/core/i18n.php';

I18n::init();

if (!function_exists('admin_url')) {
    function admin_url($page, $params = []) {
        $query = http_build_query(array_merge(['page' => $page], $params));
        return '/admin/index.php' . ($query ? ('?' . $query) : '');
    }
}

$testData = [
    'collection' => [
        'slug' => 'test-blog',
        'title' => 'Test Blog',
        'id' => 'col-001',
        'sub_collections' => []
    ],
    'entries' => [
        [
            'id' => 'entry-001',
            'title' => 'Test Entry 1',
            '__sub_entry_count' => 0,
            '__cells' => ['__title' => 'Test Entry 1', '__status' => 'published']
        ],
        [
            'id' => 'entry-002',
            'title' => 'Test Entry 2',
            '__sub_entry_count' => 3,
            '__cells' => ['__title' => 'Test Entry 2', '__status' => 'draft']
        ]
    ],
    'fields' => [['name' => 'author', 'width' => '200px']]
];

$failures = 0;

function assert_test(string $name, bool $ok, string $detail = ''): void {
    global $failures;
    echo $name . ': ' . ($ok ? "PASS\n" : "FAIL\n");
    if (!$ok) {
        $failures++;
        if ($detail !== '') echo "  $detail\n";
    }
}

echo "Testing templating directives...\n\n";

$r = render_template('<div>{collection.title}</div>', $testData);
assert_test('Variable interpolation', $r === '<div>Test Blog</div>', "got: $r");

$r = render_template('<a href="{admin_url \'entries\'}">Entries</a>', $testData);
assert_test('admin_url', strpos($r, 'page=entries') !== false, "got: $r");

$r = render_template('<a href="{admin_url \'entry-editor\' collection=\'collection.slug\'}">Edit</a>', $testData);
assert_test('admin_url dynamic params', strpos($r, 'collection=test-blog') !== false, "got: $r");

$r = render_template('{if entries exist}<div>Has entries</div>{endif}', $testData);
assert_test('Conditional', strpos($r, 'Has entries') !== false, "got: $r");

$r = render_template('{for each entry in entries}<div>{entry.title}</div>{endfor}', $testData);
assert_test('Loop', strpos($r, 'Test Entry 1') !== false && strpos($r, 'Test Entry 2') !== false, "got: $r");

$r = render_template('<script>var data = {json collection};</script>', $testData);
assert_test('JSON', strpos($r, '"title":"Test Blog"') !== false, "got: $r");

$r = render_template('Total: {count entries}', $testData);
assert_test('Count', strpos($r, 'Total: 2') !== false, "got: $r");

$r = render_template('{translate \'admin.title\'}', $testData);
assert_test('Translate', $r !== '', "got: $r");

echo "\n" . ($failures === 0 ? "All tests passed.\n" : "$failures test(s) failed.\n");
exit($failures === 0 ? 0 : 1);
