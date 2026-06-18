<?php
/**
 * M5-T2: Template parser directive regression tests.
 * Run: php tests/template_parser_test.php
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/admin/template_parser.php';
require_once dirname(__DIR__) . '/core/i18n.php';

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

test_reset_failures();

echo "Template parser directive tests\n\n";

$r = render_template('<div>{collection.title}</div>', $testData);
test_assert('Variable interpolation', $r === '<div>Test Blog</div>', "got: $r");

$r = render_template('<a href="{admin_url \'entries\'}">Entries</a>', $testData);
test_assert('admin_url', strpos($r, 'page=entries') !== false, "got: $r");

$r = render_template('<a href="{admin_url \'entry-editor\' collection=\'collection.slug\'}">Edit</a>', $testData);
test_assert('admin_url dynamic params', strpos($r, 'collection=test-blog') !== false, "got: $r");

$r = render_template('{if entries exist}<div>Has entries</div>{endif}', $testData);
test_assert('Conditional', strpos($r, 'Has entries') !== false, "got: $r");

$r = render_template('{for each entry in entries}<div>{entry.title}</div>{endfor}', $testData);
test_assert('Loop', strpos($r, 'Test Entry 1') !== false && strpos($r, 'Test Entry 2') !== false, "got: $r");

$r = render_template('<script>var data = {json collection};</script>', $testData);
test_assert('JSON', strpos($r, '"title":"Test Blog"') !== false, "got: $r");

$r = render_template('Total: {count entries}', $testData);
test_assert('Count', strpos($r, 'Total: 2') !== false, "got: $r");

$r = render_template('{translate \'admin.title\'}', $testData);
test_assert('Translate', $r !== '', "got: $r");

exit(test_finish('template_parser_test'));
