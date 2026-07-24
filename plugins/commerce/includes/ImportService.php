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
    private string $contentRoot;

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

    public function __construct(?CatalogIndexer $indexer = null, ?string $contentRoot = null)
    {
        $this->indexer = $indexer ?? new CatalogIndexer();
        $this->contentRoot = $contentRoot ?? dirname(__DIR__, 3) . '/content';
    }

    public function parseCsv(string $csvContent, string $preset = 'generic'): array
    {
        $records = $this->parseCsvRecords($csvContent);
        if (count($records) < 2) {
            return ['ok' => false, 'error' => 'CSV must have header and at least one row'];
        }

        $headers = array_shift($records);
        $mapping = $this->getPresetMapping($preset, $headers);
        $rows = [];

        foreach ($records as $cols) {
            $row = [];
            foreach ($mapping as $target => $sourceIdx) {
                if ($sourceIdx !== null && isset($cols[$sourceIdx])) {
                    $row[$target] = trim((string) $cols[$sourceIdx]);
                }
            }
            if ($this->rowHasImportableData($row, $preset)) {
                $rows[] = $this->normalizeImportRow($row);
            }
        }

        if ($preset === 'shopify') {
            $rows = $this->normalizeShopifyRows($rows);
        }

        return ['ok' => true, 'rows' => $rows, 'mapping' => $mapping];
    }

    /** @return list<list<string|null>> */
    private function parseCsvRecords(string $csvContent): array
    {
        $records = [];
        $handle = fopen('php://memory', 'r+');
        if ($handle === false) {
            return [];
        }
        fwrite($handle, $csvContent);
        rewind($handle);
        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null] || (count($row) === 1 && trim((string) ($row[0] ?? '')) === '')) {
                continue;
            }
            $records[] = $row;
        }
        fclose($handle);

        return $records;
    }

    private function rowHasImportableData(array $row, string $preset): bool
    {
        if (!empty($row['title']) || !empty($row['sku'])) {
            return true;
        }
        if ($preset === 'shopify') {
            return !empty($row['slug'])
                || !empty($row['price'])
                || !empty($row['variant_label']);
        }

        return false;
    }

    /** @param list<array<string, mixed>> $rows */
    private function normalizeShopifyRows(array $rows): array
    {
        $out = [];
        $currentHandle = null;
        $currentTitle = null;

        foreach ($rows as $row) {
            $handle = trim((string) ($row['slug'] ?? ''));
            $title = trim((string) ($row['title'] ?? ''));

            if ($handle === '' && $currentHandle !== null) {
                $handle = $currentHandle;
            }

            if ($title !== '') {
                $currentHandle = $handle !== '' ? $handle : $currentHandle;
                $currentTitle = $title;
                $row['parent_sku'] = null;
                $row['slug'] = $currentHandle;
                $out[] = $row;
                continue;
            }

            if ($handle === '' && $currentHandle === null) {
                continue;
            }

            $parentHandle = $handle !== '' ? $handle : $currentHandle;
            $label = trim((string) ($row['variant_label'] ?? '')) ?: 'Variant';
            $row['parent_sku'] = $parentHandle;
            $row['variant_label'] = $label;
            $row['title'] = $currentTitle !== null && $currentTitle !== ''
                ? $currentTitle . ' — ' . $label
                : $label;
            $row['slug'] = $parentHandle . '-' . $this->slugify($label);
            $out[] = $row;
        }

        return $out;
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
        return ['ok' => true, 'preview' => $preview, 'count' => count($preview),
            'product_count' => count(array_filter($preview, fn ($p) => empty($p['is_variant']))),
            'variant_count' => count(array_filter($preview, fn ($p) => !empty($p['is_variant']))),
        ];
    }

    public function importRows(array $rows, string $collectionSlug, bool $dryRun = false, array $meta = []): array
    {
        if ($dryRun) {
            return $this->dryRun($rows, $collectionSlug);
        }

        require_once __DIR__ . '/ImportCollectionHelper.php';
        $collectionHelper = new ImportCollectionHelper($this->contentRoot);
        $resolved = $collectionHelper->resolveForImport($collectionSlug, [
            'create' => !empty($meta['create_collection']),
            'title' => $meta['collection_title'] ?? null,
        ]);
        if (!($resolved['ok'] ?? false)) {
            return ['ok' => false, 'error' => $resolved['message'] ?? 'Invalid collection'];
        }

        $collectionSlug = (string) $resolved['slug'];
        $commerceField = (string) ($resolved['commerce_field'] ?? ImportCollectionHelper::COMMERCE_FIELD_NAME);
        $variantsSub = (string) ($resolved['variants_sub'] ?? ImportCollectionHelper::VARIANTS_SUB_SLUG);
        $hasVariants = count(array_filter($rows, fn ($r) => !empty($r['parent_sku']))) > 0;
        if ($hasVariants) {
            $collectionHelper->ensureVariantsSubCollection($collectionSlug, $commerceField);
        }

        require_once dirname(__DIR__, 3) . '/core/storage/StorageFactory.php';
        $storage = StorageFactory::createStorage($this->contentRoot);
        $entriesDir = $this->contentRoot . '/collections/' . $collectionSlug . '/entries';
        if (!is_dir($entriesDir)) {
            mkdir($entriesDir, 0755, true);
        }

        $createdIds = [];
        $updatedIds = [];
        $skipped = [];
        $variantCount = 0;
        $parentMap = [];
        $collectionCreated = !empty($resolved['created']);

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

            $entry = $this->buildEntryFromRow($row, $commerceField);
            $existing = $this->findBySku($storage, $collectionSlug, $commerceField, $entry[$commerceField]['sku'] ?? null);
            $isUpdate = $existing !== null;
            $now = date('c');
            if ($existing) {
                $entry['id'] = $existing['id'];
                $entry['created_at'] = $existing['created_at'] ?? $now;
            } else {
                $entry['id'] = uniqid();
                $entry['created_at'] = $now;
            }
            $entry['updated_at'] = $now;
            $entryPath = "collections/{$collectionSlug}/entries/{$entry['id']}.json";
            $storage->write($entryPath, $entry);
            $this->fireAfterEntrySave($entry, $collectionSlug, $isUpdate ? $existing : null);
            if ($isUpdate) {
                $updatedIds[] = $entry['id'];
            } else {
                $createdIds[] = $entry['id'];
            }
            $entrySku = $entry[$commerceField]['sku'] ?? null;
            if (!empty($entrySku)) {
                $parentMap[$entrySku] = $entry['id'];
            }
            if (!empty($entry['slug'])) {
                $parentMap[$entry['slug']] = $entry['id'];
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
            $variant = $this->buildVariantSubEntry($row, $commerceField);
            $saved = $this->saveVariantSubEntry($storage, $collectionSlug, $parentId, $variantsSub, $variant);
            if ($saved === null) {
                $skipped[] = [
                    'row' => $rowIndex + 2,
                    'title' => $row['title'] ?? '',
                    'sku' => $row['sku'] ?? null,
                    'reason' => 'Failed to create variant sub-entry',
                ];
                continue;
            }
            $parentEntry = $storage->read("collections/{$collectionSlug}/entries/{$parentId}.json");
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
            'created_entry_ids' => $createdIds,
            'updated_entry_ids' => $updatedIds,
            'entry_ids' => array_merge($createdIds, $updatedIds),
            'collection_created' => $collectionCreated,
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
            'collection_created' => $collectionCreated,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function listImportCollections(): array
    {
        require_once __DIR__ . '/ImportCollectionHelper.php';

        return (new ImportCollectionHelper($this->contentRoot))->listProductCollections();
    }

    /**
     * @return array{ok: bool, deleted?: int, variants_deleted?: int, collection_deleted?: bool, message?: string, error?: string}
     */
    public function rollbackImport(string $importId, bool $deleteCollection = true): array
    {
        require_once __DIR__ . '/ImportCollectionHelper.php';
        require_once __DIR__ . '/ImportHistoryStore.php';
        $history = new ImportHistoryStore();
        $record = $history->get($importId);
        if ($record === null) {
            return ['ok' => false, 'error' => 'Import not found'];
        }
        if (!empty($record['rolled_back_at'])) {
            return ['ok' => false, 'error' => 'This import was already removed'];
        }

        $collectionSlug = (string) ($record['collection'] ?? '');
        if ($collectionSlug === '') {
            return ['ok' => false, 'error' => 'Import has no collection'];
        }

        $entryIds = array_values(array_unique(array_merge(
            $record['created_entry_ids'] ?? [],
            $record['updated_entry_ids'] ?? [],
            $record['entry_ids'] ?? []
        )));
        if ($entryIds === []) {
            return ['ok' => false, 'error' => 'No entries recorded for this import'];
        }

        require_once dirname(__DIR__, 3) . '/core/storage/StorageFactory.php';
        $storage = StorageFactory::createStorage($this->contentRoot);
        $variantsSub = ImportCollectionHelper::VARIANTS_SUB_SLUG;
        $deleted = 0;
        $variantsDeleted = 0;

        foreach ($entryIds as $entryId) {
            $result = $this->deleteImportedEntry($storage, $collectionSlug, (string) $entryId, $variantsSub);
            $deleted += $result['entries'];
            $variantsDeleted += $result['variants'];
        }

        $collectionDeleted = false;
        if ($deleteCollection && !empty($record['collection_created'])) {
            $collectionDeleted = $this->deleteCollection($collectionSlug);
        }

        $history->update($importId, [
            'status' => 'rolled_back',
            'rolled_back_at' => date('c'),
            'deleted_entry_count' => $deleted,
            'deleted_variant_count' => $variantsDeleted,
            'collection_deleted' => $collectionDeleted,
        ]);

        return [
            'ok' => true,
            'deleted' => $deleted,
            'variants_deleted' => $variantsDeleted,
            'collection_deleted' => $collectionDeleted,
            'collection' => $collectionSlug,
        ];
    }

    /** @return array{entries: int, variants: int} */
    private function deleteImportedEntry($storage, string $collectionSlug, string $entryId, string $variantsSub): array
    {
        $entryPath = "collections/{$collectionSlug}/entries/{$entryId}.json";
        $entry = $storage->read($entryPath);
        if (!is_array($entry)) {
            return ['entries' => 0, 'variants' => 0];
        }

        $variantsDeleted = $this->deleteVariantSubEntries($storage, $collectionSlug, $entryId, $variantsSub);
        $this->indexer->removeEntry($entryId, $collectionSlug);
        $storage->delete($entryPath);
        $this->deleteFilesystemPath($this->contentRoot . '/collections/' . $collectionSlug . '/entries/' . $entryId);

        if (function_exists('do_action')) {
            do_action('after_entry_delete', $entry, $collectionSlug);
        }

        return ['entries' => 1, 'variants' => $variantsDeleted];
    }

    private function deleteVariantSubEntries($storage, string $collectionSlug, string $entryId, string $variantsSub): int
    {
        $variantDir = $this->contentRoot . '/collections/' . $collectionSlug . '/entries/' . $entryId . '/sub_entries/' . $variantsSub;
        if (!is_dir($variantDir)) {
            return 0;
        }

        $count = 0;
        foreach (glob($variantDir . '/*.json') ?: [] as $file) {
            $variant = json_decode((string) file_get_contents($file), true);
            $variantId = is_array($variant) ? (string) ($variant['id'] ?? basename($file, '.json')) : basename($file, '.json');
            if ($variantId !== '') {
                $this->indexer->removeEntry($variantId, $collectionSlug);
            }
            @unlink($file);
            $count++;
        }

        $this->deleteFilesystemPath($variantDir);
        $subEntriesRoot = dirname($variantDir);
        if (is_dir($subEntriesRoot) && count(scandir($subEntriesRoot) ?: []) === 2) {
            @rmdir($subEntriesRoot);
        }

        return $count;
    }

    private function deleteCollection(string $collectionSlug): bool
    {
        $collectionDir = $this->contentRoot . '/collections/' . $collectionSlug;
        if (!is_dir($collectionDir)) {
            return false;
        }

        $this->deleteFilesystemPath($collectionDir);

        return !is_dir($collectionDir);
    }

    private function deleteFilesystemPath(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }
        if (is_file($path) || is_link($path)) {
            @unlink($path);
            return;
        }
        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $this->deleteFilesystemPath($path . '/' . $item);
        }
        @rmdir($path);
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
                'fulfillment' => null, 'parent_sku' => null, 'variant_label' => 'option1 value',
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

    private function buildEntryFromRow(array $row, string $commerceField): array
    {
        $commerce = $row['commerce'] ?? commerce_normalize_value([]);
        return [
            'title' => $row['title'] ?? 'Untitled',
            'slug' => $row['slug'] ?? $this->slugify($row['title'] ?? ''),
            'status' => 'published',
            'body' => $row['body'] ?? '',
            $commerceField => $commerce,
        ];
    }

    private function buildVariantSubEntry(array $row, string $commerceField): array
    {
        $label = $row['variant_label'] ?? $row['title'] ?? 'Variant';
        return [
            'title' => $label,
            'slug' => $this->slugify($label),
            'variant_label' => $label,
            $commerceField => $row['commerce'] ?? commerce_normalize_value([]),
        ];
    }

    private function findBySku($storage, string $collectionSlug, string $commerceField, ?string $sku): ?array
    {
        if ($sku === null || $sku === '') {
            return null;
        }
        $entriesPath = "collections/{$collectionSlug}/entries";
        foreach ($storage->list($entriesPath) ?: [] as $file) {
            if (!str_ends_with($file, '.json')) {
                continue;
            }
            $entry = $storage->read("{$entriesPath}/{$file}");
            if (!is_array($entry)) {
                continue;
            }
            $entrySku = $entry[$commerceField]['sku'] ?? $entry['commerce']['sku'] ?? $entry['sku'] ?? null;
            if ($entrySku === $sku) {
                return $entry;
            }
        }

        return null;
    }

    private function saveVariantSubEntry($storage, string $collectionSlug, string $parentId, string $variantsSub, array $variant): ?array
    {
        $variantId = uniqid('var_');
        $now = date('c');
        $variant['id'] = $variantId;
        $variant['status'] = 'published';
        $variant['created_at'] = $now;
        $variant['updated_at'] = $now;
        $variant['parent_collection'] = $collectionSlug;
        $variant['parent_entry_id'] = $parentId;
        $variant['sub_collection'] = $variantsSub;
        $variant['ancestry'] = [
            ['collection' => $collectionSlug, 'entry_id' => $parentId],
            ['collection' => $variantsSub, 'entry_id' => $variantId],
        ];

        $path = "collections/{$collectionSlug}/entries/{$parentId}/sub_entries/{$variantsSub}/{$variantId}.json";
        if (!$storage->write($path, $variant)) {
            return null;
        }

        return $variant;
    }

    private function fireAfterEntrySave(array $entry, string $collectionSlug, ?array $oldEntry = null): void
    {
        $this->indexer->indexEntry($entry, $collectionSlug);
        if (function_exists('do_action')) {
            do_action('after_entry_save', $entry, $collectionSlug, $oldEntry);
        }
    }

    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim($text, '-') ?: 'item';
    }
}
