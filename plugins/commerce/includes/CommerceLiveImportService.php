<?php
/**
 * Live Shopify/WooCommerce import orchestration (P12-M3).
 */

if (!class_exists('CommerceImportCredentialStore')) {
    require_once __DIR__ . '/CommerceImportCredentialStore.php';
}
if (!class_exists('ShopifyImportAdapter')) {
    require_once __DIR__ . '/ShopifyImportAdapter.php';
}
if (!class_exists('WooImportAdapter')) {
    require_once __DIR__ . '/WooImportAdapter.php';
}
if (!class_exists('EtsyImportAdapter')) {
    require_once __DIR__ . '/EtsyImportAdapter.php';
}
if (!class_exists('SquareImportAdapter')) {
    require_once __DIR__ . '/SquareImportAdapter.php';
}
if (!class_exists('AmazonImportAdapter')) {
    require_once __DIR__ . '/AmazonImportAdapter.php';
}
if (!class_exists('EbayImportAdapter')) {
    require_once __DIR__ . '/EbayImportAdapter.php';
}
if (!class_exists('ImportService')) {
    require_once __DIR__ . '/ImportService.php';
}

class CommerceLiveImportService
{
    /** @var array<string, string> */
    public const PLATFORMS = [
        'shopify' => 'Shopify',
        'woocommerce' => 'WooCommerce',
        'etsy' => 'Etsy',
        'square' => 'Square',
        'amazon' => 'Amazon',
        'ebay' => 'eBay',
    ];

    private CommerceImportCredentialStore $credentials;
    private ImportService $import;
    private ?ShopifyImportAdapter $shopify;
    private ?WooImportAdapter $woo;
    private ?EtsyImportAdapter $etsy;
    private ?SquareImportAdapter $square;
    private ?AmazonImportAdapter $amazon;
    private ?EbayImportAdapter $ebay;

    public function __construct(
        ?CommerceImportCredentialStore $credentials = null,
        ?ImportService $import = null,
        ?ShopifyImportAdapter $shopify = null,
        ?WooImportAdapter $woo = null,
        ?EtsyImportAdapter $etsy = null,
        ?SquareImportAdapter $square = null,
        ?AmazonImportAdapter $amazon = null,
        ?EbayImportAdapter $ebay = null
    ) {
        $this->credentials = $credentials ?? new CommerceImportCredentialStore();
        $this->import = $import ?? new ImportService();
        $this->shopify = $shopify;
        $this->woo = $woo;
        $this->etsy = $etsy;
        $this->square = $square;
        $this->amazon = $amazon;
        $this->ebay = $ebay;
    }

    /** @return array<string, mixed> */
    public function getPublicConfig(): array
    {
        return $this->credentials->getPublicConfig();
    }

    /**
     * @param array<string, mixed> $patch
     * @return array<string, mixed>
     */
    public function savePlatformConfig(string $platform, array $patch): array
    {
        $platform = $this->normalizePlatform($platform);
        if ($platform === null) {
            return ['ok' => false, 'error' => 'Unknown platform'];
        }

        $saved = $this->credentials->savePlatformConfig($platform, $patch);

        return ['ok' => true, 'platform' => $saved];
    }

    /** @return array<string, mixed> */
    public function testConnection(string $platform): array
    {
        $platform = $this->normalizePlatform($platform);
        if ($platform === null) {
            return ['ok' => false, 'error' => 'Unknown platform'];
        }

        $creds = $this->credentials->getPlatformConfig($platform, true);
        $adapter = $this->adapterFor($platform);
        if ($adapter === null) {
            return ['ok' => false, 'error' => 'Adapter unavailable'];
        }

        return $adapter->testConnection($creds);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function preview(string $platform, array $options = []): array
    {
        $platform = $this->normalizePlatform($platform);
        if ($platform === null) {
            return ['ok' => false, 'error' => 'Unknown platform'];
        }

        $config = $this->credentials->getPlatformConfig($platform, true);
        $adapter = $this->adapterFor($platform);
        if ($adapter === null) {
            return ['ok' => false, 'error' => 'Adapter unavailable'];
        }

        $fetch = $adapter->fetchProductRows($config, array_merge($options, ['max_pages' => 1]));
        if (!($fetch['ok'] ?? false)) {
            return $fetch;
        }

        $rows = $this->import->applyDefaultCurrency(
            $fetch['rows'] ?? [],
            strtolower((string) ($config['default_currency'] ?? 'eur'))
        );
        $collection = (string) ($config['collection'] ?? 'products');
        $dry = $this->import->dryRun($rows, $collection);

        return [
            'ok' => true,
            'preview' => $dry['preview'] ?? [],
            'count' => $dry['count'] ?? 0,
            'product_count' => $fetch['product_count'] ?? 0,
            'pages_fetched' => $fetch['pages_fetched'] ?? 1,
        ];
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function syncPlatform(string $platform, array $options = []): array
    {
        $platform = $this->normalizePlatform($platform);
        if ($platform === null) {
            return ['ok' => false, 'error' => 'Unknown platform'];
        }

        $public = $this->credentials->getPlatformConfig($platform, false);
        if (empty($public['connected'])) {
            return ['ok' => false, 'error' => 'Platform is not connected'];
        }

        $config = $this->credentials->getPlatformConfig($platform, true);
        $adapter = $this->adapterFor($platform);
        if ($adapter === null) {
            return ['ok' => false, 'error' => 'Adapter unavailable'];
        }

        $fetch = $adapter->fetchProductRows($config, $options);
        if (!($fetch['ok'] ?? false)) {
            $result = [
                'ok' => false,
                'error' => $fetch['error'] ?? 'Fetch failed',
                'status' => 'failed',
                'message' => $fetch['error'] ?? 'Fetch failed',
            ];
            $this->credentials->recordSyncResult($platform, $result);

            return $result;
        }

        $rows = $this->import->applyDefaultCurrency(
            $fetch['rows'] ?? [],
            strtolower((string) ($config['default_currency'] ?? 'eur'))
        );

        foreach ($rows as &$row) {
            if (!empty($row['commerce']['purchase_options'][0])) {
                $row['commerce']['purchase_options'][0]['currency'] = strtolower((string) ($config['default_currency'] ?? 'eur'));
                if (function_exists('commerce_normalize_value')) {
                    $row['commerce'] = commerce_normalize_value($row['commerce']);
                }
            }
        }
        unset($row);

        $collection = (string) ($config['collection'] ?? 'products');
        $dryRun = !empty($options['dry_run']);
        $importResult = $this->import->importRows($rows, $collection, $dryRun, [
            'preset' => $platform,
            'platform' => self::PLATFORMS[$platform] ?? $platform,
            'currency' => strtolower((string) ($config['default_currency'] ?? 'eur')),
            'file_name' => null,
        ]);

        if (!($importResult['ok'] ?? false)) {
            $result = [
                'ok' => false,
                'error' => $importResult['error'] ?? 'Import failed',
                'status' => 'failed',
                'message' => $importResult['error'] ?? 'Import failed',
            ];
            $this->credentials->recordSyncResult($platform, $result);

            return $result;
        }

        $result = [
            'ok' => true,
            'status' => $importResult['status'] ?? 'success',
            'message' => 'Synced ' . ($importResult['count'] ?? 0) . ' products',
            'import_id' => $importResult['import_id'] ?? null,
            'imported_count' => $importResult['count'] ?? 0,
            'created_count' => $importResult['created_count'] ?? 0,
            'updated_count' => $importResult['updated_count'] ?? 0,
            'skipped_count' => $importResult['skipped_count'] ?? 0,
            'product_count' => $fetch['product_count'] ?? 0,
            'pages_fetched' => $fetch['pages_fetched'] ?? 0,
        ];
        $this->credentials->recordSyncResult($platform, $result);

        return $result;
    }

    /** @return list<array<string, mixed>> */
    public function syncScheduled(): array
    {
        $results = [];
        foreach (array_keys(self::PLATFORMS) as $platform) {
            $config = $this->credentials->getPlatformConfig($platform, false);
            if (empty($config['enabled']) || empty($config['schedule_enabled']) || empty($config['connected'])) {
                continue;
            }
            $results[] = array_merge(
                ['platform' => $platform],
                $this->syncPlatform($platform)
            );
        }

        return $results;
    }

    private function normalizePlatform(string $platform): ?string
    {
        $platform = strtolower(trim($platform));
        if ($platform === 'woo') {
            $platform = 'woocommerce';
        }

        return isset(self::PLATFORMS[$platform]) ? $platform : null;
    }

    private function adapterFor(string $platform): ShopifyImportAdapter|WooImportAdapter|EtsyImportAdapter|SquareImportAdapter|AmazonImportAdapter|EbayImportAdapter|null
    {
        if ($platform === 'shopify') {
            return $this->shopify ?? new ShopifyImportAdapter();
        }
        if ($platform === 'woocommerce') {
            return $this->woo ?? new WooImportAdapter();
        }
        if ($platform === 'etsy') {
            return $this->etsy ?? new EtsyImportAdapter();
        }
        if ($platform === 'square') {
            return $this->square ?? new SquareImportAdapter();
        }
        if ($platform === 'amazon') {
            return $this->amazon ?? new AmazonImportAdapter();
        }
        if ($platform === 'ebay') {
            return $this->ebay ?? new EbayImportAdapter();
        }

        return null;
    }
}
