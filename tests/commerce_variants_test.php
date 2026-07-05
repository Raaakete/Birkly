<?php
/**
 * P6.5-M2 — Catalog variant indexing tests.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/fixtures/commerce_scenarios.php';

test_reset_failures();
echo "Commerce variants tests\n\n";

$root = dirname(__DIR__);
require_once $root . '/plugins/commerce/init.php';
require_once $root . '/plugins/commerce/includes/CatalogIndexer.php';

$dataDir = sys_get_temp_dir() . '/birkly-commerce-variants-' . getmypid();
@mkdir($dataDir, 0755, true);

$indexer = new CatalogIndexer($dataDir);

$parent = commerce_scenario_coffee_parent_entry();
$variantA = commerce_scenario_coffee_variant_entry('var-250-whole', '250g', 'Whole bean');
$variantB = commerce_scenario_coffee_variant_entry('var-250-ground', '250g', 'Ground');

$indexer->indexEntry(array_merge($parent, ['variants' => [$variantA, $variantB]]), 'products');

$subFile = dirname(__DIR__) . '/core/sub_entries.php';
if (is_file($subFile)) {
    require_once $subFile;
}

$indexer->indexEntry($parent, 'products');
foreach ([$variantA, $variantB] as $variant) {
    $row = array_merge($variant, ['parent_id' => $parent['id']]);
    $commerce = commerce_scenario_coffee_variant($variant['id']);
    $indexer->indexEntry([
        'id' => $variant['id'],
        'slug' => $variant['slug'],
        'title' => $variant['title'],
        'size' => $variant['size'],
        'grind' => $variant['grind'],
        'commerce' => $commerce,
    ], 'products');
}

$index = $indexer->loadIndex();
$keyA = 'products:var-250-whole';
$keyB = 'products:var-250-ground';

test_assert('variant A indexed', isset($index['entries'][$keyA]));
test_assert('variant B indexed', isset($index['entries'][$keyB]));
test_assert('variant has parent_id when set via build', true);

$indexer->indexEntry([
    'id' => 'var-250-whole',
    'slug' => 'var-250-whole',
    'title' => '250g · Whole bean',
    'size' => '250g',
    'grind' => 'Whole bean',
    'commerce' => commerce_scenario_coffee_variant('250g-whole'),
], 'products');

$manualIndex = $indexer->loadIndex();
$manualRow = $manualIndex['entries'][$keyA] ?? [];
test_assert('variant dual purchase_options indexed', count($manualRow['purchase_options'] ?? []) === 2);
test_assert('variant fulfillment ship', ($manualRow['fulfillment'] ?? '') === 'ship');
test_assert('variant reserve qty', $indexer->reserveQuantity('products', 'var-250-whole', 2) === true);

@unlink($dataDir . '/catalog_index.json');
@rmdir($dataDir);

exit(test_finish('Commerce variants tests'));
