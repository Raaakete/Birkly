<?php
/**
 * Reads shop_products collection entries for cart pricing.
 */

class ShoppingCartProductCatalog
{
    private string $collectionSlug = 'shop_products';

    public function listPublished(): array
    {
        require_once dirname(__DIR__, 3) . '/core/storage/StorageFactory.php';
        require_once dirname(__DIR__, 3) . '/core/public_read.php';
        $storage = StorageFactory::createStorage(dirname(__DIR__, 3) . '/content');
        $result = birkly_public_list_entries($storage, $this->collectionSlug);
        return ($result['status'] ?? '') === 'ok' ? ($result['data']['entries'] ?? []) : [];
    }

    public function getProduct(string $productId): ?array
    {
        require_once dirname(__DIR__, 3) . '/core/storage/StorageFactory.php';
        require_once dirname(__DIR__, 3) . '/core/public_read.php';
        $storage = StorageFactory::createStorage(dirname(__DIR__, 3) . '/content');
        $result = birkly_public_get_entry($storage, $this->collectionSlug, '', $productId);
        if (($result['status'] ?? '') !== 'ok') {
            return null;
        }
        return $result['data']['entry'] ?? null;
    }

    public function normalizeProduct(array $entry): array
    {
        $price = $entry['price'] ?? 0;
        if (is_string($price)) {
            $price = (float) $price;
        }
        $cents = (int) round(((float) $price) * 100);
        return [
            'id' => $entry['id'] ?? '',
            'title' => $entry['title'] ?? '',
            'sku' => $entry['sku'] ?? '',
            'price_cents' => $cents,
            'currency' => strtolower($entry['currency'] ?? 'usd'),
            'stock' => isset($entry['stock']) ? (int) $entry['stock'] : null,
            'image' => $entry['image'] ?? '',
        ];
    }

    public function buildCartLines(array $cartItems): array
    {
        $lines = [];
        $totalCents = 0;
        $currency = 'usd';
        foreach ($cartItems as $item) {
            $productId = $item['product_id'] ?? '';
            $qty = max(1, (int) ($item['quantity'] ?? 1));
            $entry = $this->getProduct($productId);
            if (!$entry) {
                continue;
            }
            $product = $this->normalizeProduct($entry);
            if ($product['stock'] !== null && $product['stock'] < $qty) {
                continue;
            }
            $lineTotal = $product['price_cents'] * $qty;
            $currency = $product['currency'] ?: $currency;
            $lines[] = [
                'product_id' => $product['id'],
                'title' => $product['title'],
                'sku' => $product['sku'],
                'quantity' => $qty,
                'unit_price_cents' => $product['price_cents'],
                'line_total_cents' => $lineTotal,
                'currency' => $product['currency'],
            ];
            $totalCents += $lineTotal;
        }
        return ['lines' => $lines, 'total_cents' => $totalCents, 'currency' => $currency];
    }
}
