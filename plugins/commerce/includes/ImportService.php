<?php
/**
 * CSV import — WooCommerce + Shopify presets, dry-run support.
 */

class ImportService
{
    private CatalogIndexer $indexer;

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
            $preview[] = [
                'title' => $row['title'] ?? '',
                'slug' => $row['slug'] ?? $this->slugify($row['title'] ?? ''),
                'sku' => $row['sku'] ?? null,
                'price_cents' => $row['commerce']['price_cents'] ?? 0,
                'collection' => $collectionSlug,
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
        foreach ($rows as $row) {
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
            ],
            'shopify' => [
                'title' => 'title', 'sku' => 'variant sku', 'price' => 'variant price',
                'currency' => null, 'quantity' => 'variant inventory qty', 'slug' => 'handle',
            ],
            'generic' => [
                'title' => 'title', 'sku' => 'sku', 'price' => 'price',
                'currency' => 'currency', 'quantity' => 'quantity', 'slug' => 'slug',
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

        return [
            'title' => $row['title'] ?? 'Untitled',
            'slug' => $row['slug'] ?? $this->slugify($row['title'] ?? ''),
            'sku' => $row['sku'] ?? null,
            'body' => $row['body'] ?? '',
            'commerce' => [
                'price_cents' => $cents,
                'currency' => strtolower($row['currency'] ?? 'eur'),
                'billing' => 'one_time',
                'interval' => null,
                'quantity_available' => $qtyAvailable,
                'sku' => $row['sku'] ?? null,
            ],
        ];
    }

    private function buildEntryFromRow(array $row, string $collectionSlug): array
    {
        $commerce = $row['commerce'] ?? [];
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
