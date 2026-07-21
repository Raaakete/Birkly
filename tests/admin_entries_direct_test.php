<?php
/**
 * P5.4 — Entries list direct PHP template smoke (no HTTP auth).
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/core/birkly_paths.php';
require_once dirname(__DIR__) . '/core/i18n.php';

test_reset_failures();
echo "Admin entries direct template tests\n\n";

if (!function_exists('admin_url')) {
    function admin_url(string $page, array $params = []): string {
        return birkly_admin_url($page, $params);
    }
}

I18n::init();

$GLOBALS['__HARU_INTERNAL_CALL'] = true;
require_once dirname(__DIR__) . '/api/admin_templates.php';

$collectionsPath = dirname(__DIR__) . '/content/collections';
$slug = null;
if (is_dir($collectionsPath)) {
    foreach (glob($collectionsPath . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
        if (is_file($dir . '/collection.json')) {
            $slug = basename($dir);
            break;
        }
    }
}

if ($slug === null) {
    $slug = 'test-entries-direct';
    $dir = $collectionsPath . '/' . $slug;
    mkdir($dir, 0755, true);
    file_put_contents($dir . '/collection.json', json_encode([
        'name' => 'Test Collection',
        'slug' => $slug,
    ], JSON_PRETTY_PRINT));
    mkdir($dir . '/entries', 0755, true);
    file_put_contents($dir . '/entries/sample-entry.json', json_encode([
        'title' => 'Sample Entry',
        'status' => 'published',
    ], JSON_PRETTY_PRINT));
}

$_GET['collection'] = $slug;
$data = getEntriesData();
test_assert('getEntriesData returns collection', is_array($data['collection'] ?? null));
test_assert('getEntriesData returns entries array', is_array($data['entries'] ?? null));

try {
    ob_start();
    if (!defined('__TEMPLATE_DIR__')) {
        define('__TEMPLATE_DIR__', dirname(__DIR__) . '/admin/templates');
    }
    extract($data);
    include dirname(__DIR__) . '/admin/templates/entries.html';
    $output = ob_get_clean();

    test_assert('entries template renders', $output !== '');
    test_assert('output contains entries-smart-table or empty-state', strpos($output, 'entries-smart-table') !== false || strpos($output, 'empty-state') !== false);
    test_assert('output contains entry-editor link', strpos($output, 'page=entry-editor') !== false || strpos($output, 'entry-editor') !== false);
    test_assert('output contains trash filter option', strpos($output, 'value="trashed"') !== false);
    test_assert('output contains bulk field edit controls', strpos($output, 'bulk-field-apply-btn') !== false && strpos($output, 'bulk-field-name') !== false);
    test_assert('no syntax error text in output', stripos($output, 'syntax error') === false);
    test_assert('no unexpected token in output', stripos($output, 'unexpected token') === false);
} catch (Throwable $e) {
    test_assert('entries template render without exception', false, $e->getMessage());
}

$entriesHead = file_get_contents(dirname(__DIR__) . '/admin/templates/entries.html', false, null, 0, 120);
test_assert('entries.html is pure PHP template', str_contains($entriesHead, 'pure PHP template'));

exit(test_finish('admin_entries_direct_test'));
