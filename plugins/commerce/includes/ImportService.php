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
            [
                'id' => 'woocommerce',
                'name' => 'WooCommerce',
                'description' => 'WordPress store export',
                'export_steps' => 'Products → Export → CSV. Include columns: Name, SKU, Regular price, Stock.',
                'columns' => ['Name', 'SKU', 'Regular price', 'Stock', 'Slug'],
            ],
            [
                'id' => 'shopify',
                'name' => 'Shopify',
                'description' => 'Shopify Admin product export',
                'export_steps' => 'Products → Export → Export products. Uses Variant SKU, Variant price, Variant inventory qty.',
                'columns' => ['Title', 'Handle', 'Variant SKU', 'Variant price', 'Variant inventory qty'],
            ],
            [
                'id' => 'etsy',
                'name' => 'Etsy',
                'description' => 'Etsy Shop Manager CSV',
                'export_steps' => 'Shop Manager → Settings → Options → Download Data → CSV. Or use Etsy export tools for listings.',
                'columns' => ['TITLE', 'SKU', 'PRICE', 'QUANTITY'],
            ],
            [
                'id' => 'square',
                'name' => 'Square',
                'description' => 'Square Item Library export',
                'export_steps' => 'Item library → Actions → Export library. Maps Item Name, SKU, Price, Current Quantity.',
                'columns' => ['Item Name', 'SKU', 'Price', 'Current Quantity'],
            ],
            [
                'id' => 'generic',
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

    public function importRows(array $rows, string $collectionSlug, bool $dryRun = false): array
    {
        if ($dryRun) {
            return $this->dryRun($rows, $collectionSlug);
        }

        $contentRoot = dirname(__DIR__, 3) . '/content';
        $entriesDir = $contentRoot . '/collections/' . $collectionSlug . '/entries';
        if (!is_dir($entriesDir)) {
            mkdir($entriesDir, 0755, true);
        }

        $created = [];
        $parentMap = [];

        foreach ($rows as $row) {
            if (!empty($row['parent_sku'])) {
                continue;
            }
            $entry = $this->buildEntryFromRow($row, $collectionSlug);
            $existing = $this->findBySku($entriesDir, $entry['commerce']['sku'] ?? null);
            if ($existing) {
                $entry['id'] = $existing['id'];
                $file = $entriesDir . '/' . $existing['id'] . '.json';
            } else {
                $entry['id'] = 'imp_' . bin2hex(random_bytes(6));
                $file = $entriesDir . '/' . $entry['id'] . '.json';
            }
            file_put_contents($file, json_encode($entry, JSON_PRETTY_PRINT));
            $this->indexer->indexEntry($entry, $collectionSlug);
            $created[] = $entry['id'];
            if (!empty($entry['commerce']['sku'])) {
                $parentMap[$entry['commerce']['sku']] = $entry['id'];
            }
        }

        foreach ($rows as $row) {
            if (empty($row['parent_sku'])) {
                continue;
            }
            $parentId = $parentMap[$row['parent_sku']] ?? null;
            if (!$parentId) {
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
            $created[] = $parentId . '/variants/' . $variant['id'];
        }

        $logFile = commerce_data_dir() . '/import_log.json';
        $log = is_file($logFile) ? json_decode((string) file_get_contents($logFile), true) : [];
        if (!is_array($log)) {
            $log = [];
        }
        $log[] = ['at' => date('c'), 'collection' => $collectionSlug, 'count' => count($created), 'ids' => $created];
        file_put_contents($logFile, json_encode($log, JSON_PRETTY_PRINT));

        return ['ok' => true, 'created' => $created, 'count' => count($created)];
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
            if ($headerName === null) {
                $mapping[$target] = null;
                continue;
            }
            $mapping[$target] = $headerMap[strtolower($headerName)] ?? null;
        }
        return $mapping;
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
