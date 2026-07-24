<?php
/**
 * Shopify/Woo inventory webhook processing (P14-M2).
 */

if (!class_exists('CatalogIndexer')) {
    require_once __DIR__ . '/CatalogIndexer.php';
}
if (!class_exists('WarehouseStockStore')) {
    require_once __DIR__ . '/WarehouseStockStore.php';
}

class CommerceInventoryWebhookService
{
    private CatalogIndexer $indexer;
    private WarehouseStockStore $warehouse;

    public function __construct(?CatalogIndexer $indexer = null, ?WarehouseStockStore $warehouse = null)
    {
        $this->indexer = $indexer ?? new CatalogIndexer();
        $this->warehouse = $warehouse ?? new WarehouseStockStore();
    }

    public function verifyShopifyHmac(string $rawBody, string $hmacHeader, string $secret): bool
    {
        if ($secret === '' || $hmacHeader === '') {
            return false;
        }
        $computed = base64_encode(hash_hmac('sha256', $rawBody, $secret, true));

        return hash_equals($computed, $hmacHeader);
    }

    public function verifyWooSignature(string $rawBody, string $signatureHeader, string $secret): bool
    {
        if ($secret === '' || $signatureHeader === '') {
            return false;
        }
        $computed = base64_encode(hash_hmac('sha256', $rawBody, $secret, true));

        return hash_equals($computed, trim($signatureHeader));
    }

    /** @param array<string, mixed> $payload */
    public function handleShopifyInventoryUpdate(array $payload): array
    {
        $sku = $this->normalizeSku((string) ($payload['sku'] ?? ''));
        if ($sku === '' && isset($payload['inventory_item_id'])) {
            $sku = 'SHOPIFY-' . (string) $payload['inventory_item_id'];
        }
        $qty = (int) ($payload['available'] ?? $payload['quantity'] ?? 0);

        return $this->applyStockUpdate($sku, $qty, 'shopify');
    }

    /** @param array<string, mixed> $payload */
    public function handleWooProductUpdated(array $payload): array
    {
        $sku = $this->normalizeSku((string) ($payload['sku'] ?? ''));
        if ($sku === '' && !empty($payload['id'])) {
            $sku = 'WOO-' . (string) $payload['id'];
        }
        if (!array_key_exists('stock_quantity', $payload) || $payload['stock_quantity'] === null) {
            if (($payload['manage_stock'] ?? false) === false) {
                return ['ok' => true, 'skipped' => true, 'reason' => 'stock not managed'];
            }
            return ['ok' => false, 'error' => 'No stock quantity in payload'];
        }
        $qty = (int) $payload['stock_quantity'];

        return $this->applyStockUpdate($sku, $qty, 'woocommerce');
    }

    public function applyStockUpdate(string $sku, int $quantity, string $source): array
    {
        $sku = $this->normalizeSku($sku);
        if ($sku === '') {
            return ['ok' => false, 'error' => 'SKU missing', 'source' => $source];
        }

        $updatedCatalog = [];
        $index = $this->indexer->loadIndex();
        foreach ($index['entries'] ?? [] as $key => $row) {
            $rowSku = $this->normalizeSku((string) ($row['sku'] ?? ''));
            if ($rowSku === '' || $rowSku !== $sku) {
                continue;
            }
            $row['quantity_available'] = max(0, $quantity);
            $row['updated_at'] = date('c');
            $index['entries'][$key] = $row;
            $updatedCatalog[] = $key;
        }
        if (!empty($updatedCatalog)) {
            $this->indexer->saveIndex($index);
        }

        $warehouseUpdated = false;
        if ($this->warehouse->getBySku($sku) !== null) {
            $this->warehouse->set($sku, max(0, $quantity));
            $warehouseUpdated = true;
        }

        if (empty($updatedCatalog) && !$warehouseUpdated) {
            return ['ok' => true, 'sku' => $sku, 'ignored' => true, 'reason' => 'unknown SKU'];
        }

        return [
            'ok' => true,
            'sku' => $sku,
            'quantity' => $quantity,
            'catalog_keys' => $updatedCatalog,
            'warehouse_updated' => $warehouseUpdated,
            'source' => $source,
        ];
    }

    public function webhookUrl(string $platform): string
    {
        $base = function_exists('birkly_base_url') ? birkly_base_url() : '';
        if ($base === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
            $base = $scheme . '://' . $host;
        }

        return rtrim($base, '/') . '/api/commerce_webhooks.php?platform=' . urlencode($platform);
    }

    private function normalizeSku(string $sku): string
    {
        return strtoupper(trim($sku));
    }
}
