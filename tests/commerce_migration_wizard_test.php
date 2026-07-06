<?php
/**
 * P6.6 — Migration wizard scan tests.
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Commerce migration wizard tests\n\n";

$root = dirname(__DIR__);
require_once $root . '/plugins/commerce/includes/MigrationWizardService.php';

$dataDir = sys_get_temp_dir() . '/birkly-migration-' . getmypid();
$collectionsDir = $dataDir . '/collections';
@mkdir($collectionsDir . '/products', 0755, true);
@mkdir($collectionsDir . '/classes', 0755, true);
@mkdir($collectionsDir . '/blog', 0755, true);

file_put_contents($collectionsDir . '/products/schema.json', json_encode([
    'name' => 'Products',
    'fields' => [['name' => 'title', 'type' => 'text']],
]));
file_put_contents($collectionsDir . '/classes/schema.json', json_encode([
    'name' => 'Classes',
    'fields' => [['name' => 'title', 'type' => 'text']],
]));
file_put_contents($collectionsDir . '/blog/schema.json', json_encode([
    'name' => 'Blog',
    'fields' => [['name' => 'pricing', 'type' => 'commerce_events']],
]));

$wizard = new MigrationWizardService();
$result = $wizard->scan($dataDir);

test_assert('detects commerce ready collection', count($result['commerce_ready'] ?? []) === 1);
test_assert('blog is commerce ready', ($result['commerce_ready'][0]['collection'] ?? '') === 'blog');
test_assert('suggests products migration', count($result['legacy_shopping_cart'] ?? []) >= 1);
test_assert('suggests classes migration', count($result['legacy_events'] ?? []) >= 1);
test_assert('guides include shopping cart', count(array_filter($wizard->getGuides(), fn ($g) => $g['id'] === 'shopping_cart')) === 1);
test_assert('guides include bundles', count(array_filter($wizard->getGuides(), fn ($g) => $g['id'] === 'bundles')) === 1);

exit(test_finish('Commerce migration wizard tests'));
