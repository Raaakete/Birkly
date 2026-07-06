<?php
/**
 * CSV import — WooCommerce + Shopify presets, v2 commerce schema, optional variants.
 */

if (!class_exists('CommerceSchema')) {
    require_once __DIR__ . '/CommerceSchema.php';
}

class ImportService
{
    private CatalogIndexer $indexer;

    /** @return list<array<string, mixed>> */
    public static function getImportSources(): array
    {
        return [
            // Online stores
            [
                'id' => 'woocommerce',
                'category' => 'store',
                'name' => 'WooCommerce',
                'description' => 'WordPress store export',
                'export_steps' => 'Products → Export → CSV. Include columns: Name, SKU, Regular price, Stock.',
                'columns' => ['Name', 'SKU', 'Regular price', 'Stock', 'Slug'],
            ],
            [
                'id' => 'shopify',
                'category' => 'store',
                'name' => 'Shopify',
                'description' => 'Shopify Admin product export',
                'export_steps' => 'Products → Export → Export products. Uses Variant SKU, Variant price, Variant inventory qty.',
                'columns' => ['Title', 'Handle', 'Variant SKU', 'Variant price', 'Variant inventory qty'],
            ],
            [
                'id' => 'magento',
                'category' => 'store',
                'name' => 'Magento',
                'description' => 'Adobe Commerce / Magento Open Source',
                'export_steps' => 'System → Data Transfer → Export → Products CSV. Maps sku, name, price, qty.',
                'columns' => ['sku', 'name', 'price', 'qty'],
            ],
            [
                'id' => 'bigcommerce',
                'category' => 'store',
                'name' => 'BigCommerce',
                'description' => 'BigCommerce product export',
                'export_steps' => 'Products → Export → Default export. Maps Name, SKU, Price, Current Stock.',
                'columns' => ['Name', 'SKU', 'Price', 'Current Stock'],
            ],
            [
                'id' => 'prestashop',
                'category' => 'store',
                'name' => 'PrestaShop',
                'description' => 'PrestaShop catalog export',
                'export_steps' => 'Advanced Parameters → Import/Export → Export products. Maps Name, Reference, Price, Quantity.',
                'columns' => ['Name', 'Reference', 'Price', 'Quantity'],
            ],
            [
                'id' => 'wix',
                'category' => 'store',
                'name' => 'Wix Stores',
                'description' => 'Wix eCommerce product CSV',
                'export_steps' => 'Store Products → ⋯ → Export Products. Maps name, sku, price, inventory.',
                'columns' => ['name', 'sku', 'price', 'inventory'],
            ],
            [
                'id' => 'opencart',
                'category' => 'store',
                'name' => 'OpenCart',
                'description' => 'OpenCart product export',
                'export_steps' => 'Export tool or extension CSV. Maps product name, model (SKU), price, quantity.',
                'columns' => ['product name', 'model', 'price', 'quantity'],
            ],
            [
                'id' => 'etsy',
                'category' => 'store',
                'name' => 'Etsy',
                'description' => 'Etsy Shop Manager CSV',
                'export_steps' => 'Shop Manager → Settings → Options → Download Data → CSV. Or use Etsy export tools for listings.',
                'columns' => ['TITLE', 'SKU', 'PRICE', 'QUANTITY'],
            ],
            [
                'id' => 'square',
                'category' => 'store',
                'name' => 'Square',
                'description' => 'Square Item Library export',
                'export_steps' => 'Item library → Actions → Export library. Maps Item Name, SKU, Price, Current Quantity.',
                'columns' => ['Item Name', 'SKU', 'Price', 'Current Quantity'],
            ],
            [
                'id' => 'amazon',
                'category' => 'store',
                'name' => 'Amazon Seller',
                'description' => 'Amazon inventory flat file',
                'export_steps' => 'Inventory → Download report (Active Listings) or flat-file upload template. Maps item-name, seller-sku, price, quantity.',
                'columns' => ['item-name', 'seller-sku', 'price', 'quantity'],
            ],
            [
                'id' => 'ebay',
                'category' => 'store',
                'name' => 'eBay',
                'description' => 'eBay active listings export',
                'export_steps' => 'Seller Hub → Listings → Export. Maps Title, Custom label (SKU), Start price, Available quantity.',
                'columns' => ['Title', 'Custom label', 'Start price', 'Available quantity'],
            ],
            [
                'id' => 'ecwid',
                'category' => 'store',
                'name' => 'Ecwid',
                'description' => 'Ecwid by Lightspeed store export',
                'export_steps' => 'Catalog → Products → Export. Maps product_name, product_sku, product_price, product_quantity.',
                'columns' => ['product_name', 'product_sku', 'product_price', 'product_quantity'],
            ],
            // Inventory & stock systems
            [
                'id' => 'inflow',
                'category' => 'inventory',
                'name' => 'inFlow Inventory',
                'description' => 'inFlow product catalog export',
                'export_steps' => 'Main → Products → Export. Maps Product Name, SKU, Unit Price, Quantity On Hand.',
                'columns' => ['Product Name', 'SKU', 'Unit Price', 'Quantity On Hand'],
            ],
            [
                'id' => 'zoho_inventory',
                'category' => 'inventory',
                'name' => 'Zoho Inventory',
                'description' => 'Zoho Inventory item export',
                'export_steps' => 'Items → Export Items. Maps Item Name, SKU, Rate, Available Stock.',
                'columns' => ['Item Name', 'SKU', 'Rate', 'Available Stock'],
            ],
            [
                'id' => 'unleashed',
                'category' => 'inventory',
                'name' => 'Unleashed',
                'description' => 'Unleashed inventory product export',
                'export_steps' => 'Products → Export. Maps Product Description, Product Code, Default Sell Price, Available.',
                'columns' => ['Product Description', 'Product Code', 'Default Sell Price', 'Available'],
            ],
            [
                'id' => 'cin7',
                'category' => 'inventory',
                'name' => 'Cin7 Core',
                'description' => 'Cin7 / Dear Inventory product export',
                'export_steps' => 'Inventory → Products → Export. Maps ProductName, ProductCode, RetailPrice, Available.',
                'columns' => ['ProductName', 'ProductCode', 'RetailPrice', 'Available'],
            ],
            [
                'id' => 'fishbowl',
                'category' => 'inventory',
                'name' => 'Fishbowl',
                'description' => 'Fishbowl Inventory part export',
                'export_steps' => 'Reports → Export parts list. Maps Description, Num (part #), Price, Qty On Hand.',
                'columns' => ['Description', 'Num', 'Price', 'Qty On Hand'],
            ],
            [
                'id' => 'lightspeed',
                'category' => 'inventory',
                'name' => 'Lightspeed Retail',
                'description' => 'Lightspeed Retail item export',
                'export_steps' => 'Inventory → Items → Export. Maps Description, customSku, Price, Qty.',
                'columns' => ['Description', 'customSku', 'Price', 'Qty'],
            ],
            [
                'id' => 'katana',
                'category' => 'inventory',
                'name' => 'Katana MRP',
                'description' => 'Katana inventory export',
                'export_steps' => 'Items → Export CSV. Maps Product, SKU, Sales price, In stock.',
                'columns' => ['Product', 'SKU', 'Sales price', 'In stock'],
            ],
            [
                'id' => 'finale',
                'category' => 'inventory',
                'name' => 'Finale Inventory',
                'description' => 'Finale product export',
                'export_steps' => 'Reports → Product export. Maps Description, Product ID, Price, Quantity.',
                'columns' => ['Description', 'Product ID', 'Price', 'Quantity'],
            ],
            [
                'id' => 'sortly',
                'category' => 'inventory',
                'name' => 'Sortly',
                'description' => 'Sortly item export',
                'export_steps' => 'Items → Export → CSV. Maps Name, SKU, Price, Quantity.',
                'columns' => ['Name', 'SKU', 'Price', 'Quantity'],
            ],
            [
                'id' => 'ordoro',
                'category' => 'inventory',
                'name' => 'Ordoro',
                'description' => 'Ordoro product catalog export',
                'export_steps' => 'Products → Export. Maps Name, SKU, Price, Quantity on hand.',
                'columns' => ['Name', 'SKU', 'Price', 'Quantity on hand'],
            ],
            [
                'id' => 'tradegecko',
                'category' => 'inventory',
                'name' => 'QuickBooks Commerce',
                'description' => 'TradeGecko / QuickBooks Commerce export',
                'export_steps' => 'Products → Export CSV. Maps name, sku, retail_price, available.',
                'columns' => ['name', 'sku', 'retail_price', 'available'],
            ],
            // Other
            [
                'id' => 'generic',
                'category' => 'other',
                'name' => 'Generic CSV',
                'description' => 'Any spreadsheet or custom export',
                'export_steps' => 'Use headers: title, price, currency, sku, quantity, slug, fulfillment, parent_sku, variant_label.',
                'columns' => ['title', 'price', 'currency', 'sku', 'quantity', 'slug'],
            ],
        ];
    }

    public function __construct(?CatalogIndexer $indexer = null)
    {
        $this->indexer = $indexer ?? new CatalogIndexer();
    }

    public function parseCsv(string $csvContent, string $preset = 'generic'): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($csvContent));
        if (count($lines) < 2) {
            return ['ok' => false, 'error' => 'CSV must have header and at least one row'];
        }

        $headers = str_getcsv(array_shift($lines));
        $mapping = $this->getPresetMapping($preset, $headers);
        $rows = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $cols = str_getcsv($line);
            $row = [];
            foreach ($mapping as $target => $sourceIdx) {
                if ($sourceIdx !== null && isset($cols[$sourceIdx])) {
                    $row[$target] = trim($cols[$sourceIdx]);
                }
            }
            if (!empty($row['title']) || !empty($row['sku'])) {
                $rows[] = $this->normalizeImportRow($row);
            }
        }

        return ['ok' => true, 'rows' => $rows, 'mapping' => $mapping];
    }

    public function dryRun(array $rows, string $collectionSlug): array
    {
        $preview = [];
        foreach ($rows as $row) {
            $commerce = $row['commerce'] ?? [];
            $preview[] = [
                'title' => $row['title'] ?? '',
                'slug' => $row['slug'] ?? $this->slugify($row['title'] ?? ''),
                'sku' => $row['sku'] ?? null,
                'fulfillment' => $commerce['fulfillment'] ?? 'ship',
                'price_cents' => $commerce['price_cents'] ?? CommerceSchema::priceFrom($commerce),
                'collection' => $collectionSlug,
                'is_variant' => !empty($row['parent_sku']),
                'action' => 'create',
            ];
        }
        return ['ok' => true, 'preview' => $preview, 'count' => count($preview)];
    }

    public function importRows(array $rows, string $collectionSlug, bool $dryRun = false, array $meta = []): array
    {
        if ($dryRun) {
            return $this->dryRun($rows, $collectionSlug);
        }

        $contentRoot = dirname(__DIR__, 3) . '/content';
        $entriesDir = $contentRoot . '/collections/' . $collectionSlug . '/entries';
        if (!is_dir($entriesDir)) {
            mkdir($entriesDir, 0755, true);
        }

        $createdIds = [];
        $updatedIds = [];
        $skipped = [];
        $variantCount = 0;
        $parentMap = [];

        foreach ($rows as $rowIndex => $row) {
            if (!empty($row['parent_sku'])) {
                continue;
            }
            $title = trim((string) ($row['title'] ?? ''));
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($title === '' && $sku === '') {
                $skipped[] = [
                    'row' => $rowIndex + 2,
                    'title' => $title,
                    'sku' => $sku ?: null,
                    'reason' => 'Missing product title and SKU',
                ];
                continue;
            }

            $entry = $this->buildEntryFromRow($row, $collectionSlug);
            $existing = $this->findBySku($entriesDir, $entry['commerce']['sku'] ?? null);
            $isUpdate = $existing !== null;
            if ($existing) {
                $entry['id'] = $existing['id'];
                $file = $entriesDir . '/' . $existing['id'] . '.json';
            } else {
                $entry['id'] = 'imp_' . bin2hex(random_bytes(6));
                $file = $entriesDir . '/' . $entry['id'] . '.json';
            }
            file_put_contents($file, json_encode($entry, JSON_PRETTY_PRINT));
            $this->indexer->indexEntry($entry, $collectionSlug);
            if ($isUpdate) {
                $updatedIds[] = $entry['id'];
            } else {
                $createdIds[] = $entry['id'];
            }
            if (!empty($entry['commerce']['sku'])) {
                $parentMap[$entry['commerce']['sku']] = $entry['id'];
            }
        }

        foreach ($rows as $rowIndex => $row) {
            if (empty($row['parent_sku'])) {
                continue;
            }
            $parentId = $parentMap[$row['parent_sku']] ?? null;
            if (!$parentId) {
                $skipped[] = [
                    'row' => $rowIndex + 2,
                    'title' => $row['title'] ?? '',
                    'sku' => $row['sku'] ?? null,
                    'reason' => 'Parent SKU not found: ' . $row['parent_sku'],
                ];
                continue;
            }
            $variant = $this->buildVariantSubEntry($row, $parentId);
            $variantDir = $entriesDir . '/' . $parentId . '/sub_entries/variants';
            if (!is_dir($variantDir)) {
                mkdir($variantDir, 0755, true);
            }
            $variantFile = $variantDir . '/' . $variant['id'] . '.json';
            file_put_contents($variantFile, json_encode($variant, JSON_PRETTY_PRINT));
            $parentEntry = json_decode((string) file_get_contents($entriesDir . '/' . $parentId . '.json'), true);
            if (is_array($parentEntry)) {
                $this->indexer->indexEntry($parentEntry, $collectionSlug);
            }
            $variantCount++;
        }

        $importedCount = count($createdIds) + count($updatedIds);
        $status = 'success';
        if ($importedCount === 0 && count($skipped) > 0) {
            $status = 'failed';
        } elseif (count($skipped) > 0) {
            $status = 'partial';
        }

        $preset = $meta['preset'] ?? 'generic';
        $historyRecord = [
            'status' => $status,
            'preset' => $preset,
            'platform' => $meta['platform'] ?? self::platformName($preset),
            'collection' => $collectionSlug,
            'currency' => strtolower((string) ($meta['currency'] ?? 'eur')),
            'file_name' => $meta['file_name'] ?? null,
            'imported_count' => $importedCount,
            'created_count' => count($createdIds),
            'updated_count' => count($updatedIds),
            'skipped_count' => count($skipped),
            'variant_count' => $variantCount,
            'total_rows' => count($rows),
            'errors' => array_slice($skipped, 0, 100),
            'entry_ids' => array_slice(array_merge($createdIds, $updatedIds), 0, 50),
        ];

        require_once __DIR__ . '/ImportHistoryStore.php';
        $history = new ImportHistoryStore();
        $saved = $history->add($historyRecord);

        return [
            'ok' => true,
            'import_id' => $saved['id'],
            'status' => $status,
            'created' => $createdIds,
            'updated' => $updatedIds,
            'skipped' => $skipped,
            'count' => $importedCount,
            'created_count' => count($createdIds),
            'updated_count' => count($updatedIds),
            'skipped_count' => count($skipped),
            'variant_count' => $variantCount,
            'collection' => $collectionSlug,
        ];
    }

    public static function platformName(string $presetId): string
    {
        foreach (self::getImportSources() as $source) {
            if (($source['id'] ?? '') === $presetId) {
                return (string) ($source['name'] ?? $presetId);
            }
        }
        return ucfirst(str_replace('_', ' ', $presetId));
    }

    private function getPresetMapping(string $preset, array $headers): array
    {
        $headerMap = [];
        foreach ($headers as $i => $h) {
            $headerMap[strtolower(trim($h))] = $i;
        }

        $presets = [
            'woocommerce' => [
                'title' => 'name', 'sku' => 'sku', 'price' => 'regular price',
                'currency' => null, 'quantity' => 'stock', 'slug' => 'slug',
                'fulfillment' => null, 'parent_sku' => null, 'variant_label' => null,
                'body' => 'description',
            ],
            'shopify' => [
                'title' => 'title', 'sku' => 'variant sku', 'price' => 'variant price',
                'currency' => null, 'quantity' => 'variant inventory qty', 'slug' => 'handle',
                'fulfillment' => null, 'parent_sku' => 'handle', 'variant_label' => 'option1 value',
                'body' => 'body (html)',
            ],
            'magento' => [
                'title' => ['name', 'product name'], 'sku' => 'sku', 'price' => ['price', 'special price'],
                'currency' => null, 'quantity' => ['qty', 'quantity'], 'slug' => 'url key',
                'fulfillment' => null, 'parent_sku' => null, 'variant_label' => null,
                'body' => 'description',
            ],
            'bigcommerce' => [
                'title' => ['name', 'item name'], 'sku' => 'sku', 'price' => 'price',
                'currency' => null, 'quantity' => ['current stock', 'quantity'], 'slug' => null,
                'fulfillment' => null, 'parent_sku' => null, 'variant_label' => null,
                'body' => 'description',
            ],
            'prestashop' => [
                'title' => 'name', 'sku' => ['reference', 'sku'], 'price' => ['price', 'price tax excluded'],
                'currency' => null, 'quantity' => 'quantity', 'slug' => 'link rewrite',
                'fulfillment' => null, 'parent_sku' => null, 'variant_label' => null,
                'body' => 'description',
            ],
            'wix' => [
                'title' => ['name', 'product name'], 'sku' => 'sku', 'price' => 'price',
                'currency' => null, 'quantity' => ['inventory', 'quantity'], 'slug' => 'slug',
                'fulfillment' => null, 'parent_sku' => null, 'variant_label' => null,
                'body' => 'description',
            ],
            'opencart' => [
                'title' => ['product name', 'name'], 'sku' => ['model', 'sku'], 'price' => 'price',
                'currency' => null, 'quantity' => 'quantity', 'slug' => null,
                'fulfillment' => null, 'parent_sku' => null, 'variant_label' => null,
                'body' => 'description',
            ],
            'etsy' => [
                'title' => 'title', 'sku' => 'sku', 'price' => 'price',
                'currency' => null, 'quantity' => 'quantity', 'slug' => null,
                'fulfillment' => null, 'parent_sku' => null, 'variant_label' => null,
                'body' => 'description',
            ],
            'square' => [
                'title' => 'item name', 'sku' => 'sku', 'price' => 'price',
                'currency' => null, 'quantity' => 'current quantity', 'slug' => null,
                'fulfillment' => null, 'parent_sku' => null, 'variant_label' => 'variation name',
                'body' => 'description',
            ],
            'amazon' => [
                'title' => ['item-name', 'product-name', 'title'], 'sku' => ['seller-sku', 'sku'],
                'price' => ['price', 'standard-price'], 'currency' => null,
                'quantity' => ['quantity', 'quantity available'], 'slug' => null,
                'fulfillment' => null, 'parent_sku' => null, 'variant_label' => null,
                'body' => 'item-description',
            ],
            'ebay' => [
                'title' => 'title', 'sku' => ['custom label', 'sku'], 'price' => ['start price', 'price'],
                'currency' => null, 'quantity' => ['available quantity', 'quantity'], 'slug' => null,
                'fulfillment' => null, 'parent_sku' => null, 'variant_label' => null,
                'body' => 'description',
            ],
            'ecwid' => [
                'title' => 'product_name', 'sku' => 'product_sku', 'price' => 'product_price',
                'currency' => null, 'quantity' => 'product_quantity', 'slug' => null,
                'fulfillment' => null, 'parent_sku' => null, 'variant_label' => null,
                'body' => 'product_description',
            ],
            'inflow' => [
                'title' => 'product name', 'sku' => 'sku', 'price' => ['unit price', 'price'],
                'currency' => null, 'quantity' => ['quantity on hand', 'qty on hand'], 'slug' => null,
                'fulfillment' => null, 'parent_sku' => null, 'variant_label' => null,
                'body' => 'description',
            ],
            'zoho_inventory' => [
                'title' => 'item name', 'sku' => 'sku', 'price' => ['rate', 'selling price'],
                'currency' => null, 'quantity' => ['available stock', 'stock on hand'], 'slug' => null,
                'fulfillment' => null, 'parent_sku' => null, 'variant_label' => null,
                'body' => 'description',
            ],
            'unleashed' => [
                'title' => ['product description', 'product name'], 'sku' => ['product code', 'sku'],
                'price' => ['default sell price', 'sell price'], 'currency' => null,
                'quantity' => ['available', 'available qty'], 'slug' => null,
                'fulfillment' => null, 'parent_sku' => null, 'variant_label' => null,
                'body' => 'notes',
            ],
            'cin7' => [
                'title' => ['productname', 'product name'], 'sku' => ['productcode', 'sku'],
                'price' => ['retailprice', 'sell price'], 'currency' => null,
                'quantity' => ['available', 'on hand'], 'slug' => null,
                'fulfillment' => null, 'parent_sku' => null, 'variant_label' => null,
                'body' => 'description',
            ],
            'fishbowl' => [
                'title' => 'description', 'sku' => ['num', 'part number'], 'price' => 'price',
                'currency' => null, 'quantity' => ['qty on hand', 'quantity on hand'], 'slug' => null,
                'fulfillment' => null, 'parent_sku' => null, 'variant_label' => null,
                'body' => 'details',
            ],
            'lightspeed' => [
                'title' => 'description', 'sku' => ['customsku', 'sku'], 'price' => 'price',
                'currency' => null, 'quantity' => ['qty', 'quantity'], 'slug' => null,
                'fulfillment' => null, 'parent_sku' => null, 'variant_label' => null,
                'body' => 'note',
            ],
            'katana' => [
                'title' => ['product', 'variant'], 'sku' => 'sku', 'price' => ['sales price', 'price'],
                'currency' => null, 'quantity' => ['in stock', 'quantity'], 'slug' => null,
                'fulfillment' => null, 'parent_sku' => null, 'variant_label' => 'variant',
                'body' => null,
            ],
            'finale' => [
                'title' => 'description', 'sku' => ['product id', 'sku'], 'price' => 'price',
                'currency' => null, 'quantity' => 'quantity', 'slug' => null,
                'fulfillment' => null, 'parent_sku' => null, 'variant_label' => null,
                'body' => null,
            ],
            'sortly' => [
                'title' => 'name', 'sku' => 'sku', 'price' => 'price',
                'currency' => null, 'quantity' => 'quantity', 'slug' => null,
                'fulfillment' => null, 'parent_sku' => null, 'variant_label' => null,
                'body' => 'notes',
            ],
            'ordoro' => [
                'title' => 'name', 'sku' => 'sku', 'price' => 'price',
                'currency' => null, 'quantity' => ['quantity on hand', 'qty on hand'], 'slug' => null,
                'fulfillment' => null, 'parent_sku' => null, 'variant_label' => null,
                'body' => 'description',
            ],
            'tradegecko' => [
                'title' => 'name', 'sku' => 'sku', 'price' => ['retail_price', 'price'],
                'currency' => null, 'quantity' => ['available', 'stock'], 'slug' => null,
                'fulfillment' => null, 'parent_sku' => null, 'variant_label' => null,
                'body' => 'description',
            ],
            'generic' => [
                'title' => 'title', 'sku' => 'sku', 'price' => 'price',
                'currency' => 'currency', 'quantity' => 'quantity', 'slug' => 'slug',
                'fulfillment' => 'fulfillment', 'parent_sku' => 'parent_sku', 'variant_label' => 'variant_label',
                'body' => 'body',
            ],
        ];

        $presetCols = $presets[$preset] ?? $presets['generic'];
        $mapping = [];
        foreach ($presetCols as $target => $headerName) {
            $mapping[$target] = $this->resolveHeaderIndex($headerMap, $headerName);
        }
        return $mapping;
    }

    /** @param string|list<string>|null $headerNames */
    private function resolveHeaderIndex(array $headerMap, string|array|null $headerNames): ?int
    {
        if ($headerNames === null) {
            return null;
        }
        foreach ((array) $headerNames as $name) {
            $key = strtolower(trim($name));
            if (isset($headerMap[$key])) {
                return $headerMap[$key];
            }
        }
        return null;
    }

    private function normalizeImportRow(array $row): array
    {
        $price = $row['price'] ?? '0';
        $price = preg_replace('/[^0-9.]/', '', (string) $price);
        $cents = (int) round((float) $price * 100);

        $qty = $row['quantity'] ?? '';
        $qtyAvailable = ($qty === '' || $qty === null) ? null : (int) $qty;

        $fulfillment = in_array($row['fulfillment'] ?? '', CommerceSchema::FULFILLMENTS, true)
            ? $row['fulfillment'] : 'ship';

        $currencyProvided = array_key_exists('currency', $row) && trim((string) ($row['currency'] ?? '')) !== '';

        $purchaseOption = [
            'id' => 'import_default',
            'label' => 'Default',
            'billing' => 'one_time',
            'price_cents' => $cents,
        ];
        if ($currencyProvided) {
            $purchaseOption['currency'] = strtolower(trim((string) $row['currency']));
        }

        $commerce = commerce_normalize_value([
            'fulfillment' => $fulfillment,
            'purchase_options' => [$purchaseOption],
            'quantity_available' => $qtyAvailable,
            'sku' => $row['sku'] ?? null,
        ]);

        return [
            'title' => $row['title'] ?? 'Untitled',
            'slug' => $row['slug'] ?? $this->slugify($row['title'] ?? ''),
            'sku' => $row['sku'] ?? null,
            'body' => $row['body'] ?? '',
            'parent_sku' => $row['parent_sku'] ?? null,
            'variant_label' => $row['variant_label'] ?? null,
            'currency_from_csv' => $currencyProvided,
            'commerce' => $commerce,
        ];
    }

    /** @param list<array<string, mixed>> $rows */
    public function applyDefaultCurrency(array $rows, string $currency = 'eur'): array
    {
        $currency = strtolower($currency);
        foreach ($rows as &$row) {
            if (empty($row['currency_from_csv']) && !empty($row['commerce']['purchase_options'][0])) {
                $row['commerce']['purchase_options'][0]['currency'] = $currency;
                $row['commerce'] = commerce_normalize_value($row['commerce']);
            }
            unset($row['currency_from_csv']);
        }
        unset($row);
        return $rows;
    }

    private function buildEntryFromRow(array $row, string $collectionSlug): array
    {
        $commerce = $row['commerce'] ?? commerce_normalize_value([]);
        return [
            'id' => $row['id'] ?? ('imp_' . bin2hex(random_bytes(6))),
            'title' => $row['title'] ?? 'Untitled',
            'slug' => $row['slug'] ?? $this->slugify($row['title'] ?? ''),
            'status' => 'published',
            'collection' => $collectionSlug,
            'body' => $row['body'] ?? '',
            'commerce' => $commerce,
        ];
    }

    private function buildVariantSubEntry(array $row, string $parentId): array
    {
        $label = $row['variant_label'] ?? $row['title'] ?? 'Variant';
        return [
            'id' => 'var_' . bin2hex(random_bytes(4)),
            'title' => $label,
            'slug' => $this->slugify($label),
            'status' => 'published',
            'variant_label' => $label,
            'commerce' => $row['commerce'] ?? commerce_normalize_value([]),
        ];
    }

    private function findBySku(string $entriesDir, ?string $sku): ?array
    {
        if ($sku === null || $sku === '') {
            return null;
        }
        foreach (glob($entriesDir . '/*.json') ?: [] as $file) {
            $entry = json_decode((string) file_get_contents($file), true);
            if (!is_array($entry)) {
                continue;
            }
            $entrySku = $entry['commerce']['sku'] ?? $entry['sku'] ?? null;
            if ($entrySku === $sku) {
                return $entry;
            }
        }
        return null;
    }

    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim($text, '-') ?: 'item';
    }
}
