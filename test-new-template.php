<?php
/**
 * Test script for new templating system
 * Run: php test-new-template.php
 */

require_once __DIR__ . '/admin/template_parser.php';
require_once __DIR__ . '/core/i18n.php';

// Initialize i18n
I18n::init();

// Mock admin_url function
if (!function_exists('admin_url')) {
    function admin_url($page, $params = []) {
        $query = http_build_query(array_merge(['page' => $page], $params));
        return '/admin/index.php' . ($query ? ('?' . $query) : '');
    }
}

// Test data
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
            '__cells' => [
                '__title' => 'Test Entry 1',
                '__status' => 'published'
            ]
        ],
        [
            'id' => 'entry-002',
            'title' => 'Test Entry 2',
            '__sub_entry_count' => 3,
            '__cells' => [
                '__title' => 'Test Entry 2',
                '__status' => 'draft'
            ]
        ]
    ],
    'fields' => [
        [
            'name' => 'author',
            'width' => '200px'
        ]
    ]
];

echo "Testing new templating directives...\n\n";

// Test 1: Variable interpolation
echo "Test 1: Variable interpolation\n";
$template = '<div>{collection.title}</div>';
$result = render_template($template, $testData);
echo "Template: $template\n";
echo "Result: $result\n";
echo "Expected: <div>Test Blog</div>\n";
echo ($result === '<div>Test Blog</div>' ? "✓ PASS\n" : "✗ FAIL\n");
echo "\n";

// Test 2: admin_url directive
echo "Test 2: admin_url directive\n";
$template = '<a href="{admin_url \'entries\'}">Entries</a>';
$result = render_template($template, $testData);
echo "Template: $template\n";
echo "Result: $result\n";
echo "Expected: <a href=\"/admin/index.php?page=entries\">Entries</a>\n";
echo (strpos($result, 'page=entries') !== false ? "✓ PASS\n" : "✗ FAIL\n");
echo "\n";

// Test 3: admin_url with dynamic parameters
echo "Test 3: admin_url with dynamic variables\n";
$template = '<a href="{admin_url \'entry-editor\' collection=\'collection.slug\'}">Edit</a>';
$result = render_template($template, $testData);
echo "Template: $template\n";
echo "Result: $result\n";
echo "Expected: Contains collection=test-blog\n";
echo (strpos($result, 'collection=test-blog') !== false ? "✓ PASS\n" : "✗ FAIL\n");
echo "\n";

// Test 4: Conditional rendering
echo "Test 4: Conditional rendering\n";
$template = '{if entries exist}<div>Has entries</div>{endif}';
$result = render_template($template, $testData);
echo "Template: $template\n";
echo "Result: $result\n";
echo "Expected: <div>Has entries</div>\n";
echo (strpos($result, 'Has entries') !== false ? "✓ PASS\n" : "✗ FAIL\n");
echo "\n";

// Test 5: Loops
echo "Test 5: Loops\n";
$template = '{for each entry in entries}<div>{entry.title}</div>{endfor}';
$result = render_template($template, $testData);
echo "Template: $template\n";
echo "Result: $result\n";
echo "Expected: Contains both entry titles\n";
echo (strpos($result, 'Test Entry 1') !== false && strpos($result, 'Test Entry 2') !== false ? "✓ PASS\n" : "✗ FAIL\n");
echo "\n";

// Test 6: JSON encoding
echo "Test 6: JSON encoding\n";
$template = '<script>var data = {json collection};</script>';
$result = render_template($template, $testData);
echo "Template: $template\n";
echo "Result: $result\n";
echo "Expected: Valid JSON object\n";
echo (strpos($result, '"title":"Test Blog"') !== false ? "✓ PASS\n" : "✗ FAIL\n");
echo "\n";

// Test 7: Count directive
echo "Test 7: Count directive\n";
$template = 'Total: {count entries}';
$result = render_template($template, $testData);
echo "Template: $template\n";
echo "Result: $result\n";
echo "Expected: Total: 2\n";
echo (strpos($result, 'Total: 2') !== false ? "✓ PASS\n" : "✗ FAIL\n");
echo "\n";

// Test 8: Translation
echo "Test 8: Translation directive\n";
$template = '{translate \'admin.title\'}';
$result = render_template($template, $testData);
echo "Template: $template\n";
echo "Result: $result\n";
echo "Expected: Translated text\n";
echo (!empty($result) ? "✓ PASS\n" : "✗ FAIL\n");
echo "\n";

echo "All basic tests completed!\n";
echo "\nTo test the full entries page:\n";
echo "1. Old version: http://localhost:8001/admin/index.php?page=entries&collection=01_Blog\n";
echo "2. New version: http://localhost:8001/admin/index.php?page=entries&collection=01_Blog&use_new_template=1\n";
