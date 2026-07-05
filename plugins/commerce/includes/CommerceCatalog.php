<?php
/**
 * Reads catalog index for cart pricing (v2 — purchase_option_id, shipping zones).
 */

class CommerceCatalog
{
    private CatalogIndexer $indexer;

    public function __construct(?CatalogIndexer $indexer = null)
    {
        $this->indexer = $indexer ?? new CatalogIndexer();
    }

    public function getItem(string $collectionSlug, string $entryId): ?array
    {
        $index = $this->indexer->loadIndex();
        $key = $collectionSlug . ':' . $entryId;
        return $index['entries'][$key] ?? null;
    }

    public function listItems(array $filters = []): array
    {
        $index = $this->indexer->loadIndex();
        $items = array_values($index['entries'] ?? []);

        if (!empty($filters['collection'])) {
            $items = array_filter($items, fn ($i) => ($i['collection_slug'] ?? '') === $filters['collection']);
        }
        if (!empty($filters['billing'])) {
            $items = array_filter($items, fn ($i) => ($i['billing'] ?? '') === $filters['billing']);
        }
        if (!empty($filters['fulfillment'])) {
            $items = array_filter($items, fn ($i) => ($i['fulfillment'] ?? '') === $filters['fulfillment']);
        }
        if (isset($filters['low_stock']) && $filters['low_stock'] !== '') {
            $threshold = (int) $filters['low_stock'];
            $items = array_filter($items, function ($i) use ($threshold) {
                $qty = $i['quantity_available'] ?? null;
                return $qty !== null && (int) $qty <= $threshold;
            });
        }

        return array_values($items);
    }

    public function buildCartLines(array $cartItems, ?DiscountEngine $discounts = null, ?string $coupon = null, ?string $affiliateId = null): array
    {
        if (!class_exists('CommerceSchema')) {
            require_once __DIR__ . '/CommerceSchema.php';
        }

        $lines = [];
        $totalCents = 0;
        $totalDiscount = 0;
        $currency = 'eur';
        $discountEngine = $discounts ?? new DiscountEngine();
        $billingModes = [];

        foreach ($cartItems as $item) {
            $collectionSlug = $item['collection'] ?? '';
            $entryId = $item['entry_id'] ?? '';
            $qty = max(1, (int) ($item['quantity'] ?? 1));
            $purchaseOptionId = $item['purchase_option_id'] ?? null;

            $catalogItem = $this->getItem($collectionSlug, $entryId);
            if (!$catalogItem) {
                continue;
            }

            if ($catalogItem['quantity_available'] !== null && (int) $catalogItem['quantity_available'] < $qty) {
                continue;
            }

            $commerce = commerce_normalize_value($catalogItem['commerce'] ?? $catalogItem);
            $option = CommerceSchema::findPurchaseOption($commerce, $purchaseOptionId);
            if (!$option) {
                continue;
            }

            $billingModes[$option['billing'] ?? 'one_time'] = true;

            $pricedItem = array_merge($catalogItem, [
                'price_cents' => (int) $option['price_cents'],
                'currency' => $option['currency'] ?? 'eur',
                'billing' => $option['billing'] ?? 'one_time',
                'interval' => $option['interval'] ?? null,
            ]);

            $baseUnit = (int) $option['price_cents'];
            $pricing = $discountEngine->applyToLine($pricedItem, $qty, $coupon, $affiliateId);
            $effectiveUnit = (int) $pricing['effective_unit_cents'];
            $lineDiscount = (int) $pricing['discount_cents'];
            $lineTotal = $effectiveUnit * $qty;
            $currency = $option['currency'] ?: $currency;

            $lines[] = [
                'line_id' => $collectionSlug . ':' . $entryId . ':' . ($option['id'] ?? 'default'),
                'entry_id' => $entryId,
                'parent_id' => $item['parent_id'] ?? ($catalogItem['parent_id'] ?? null),
                'collection_slug' => $collectionSlug,
                'entry_slug' => $catalogItem['entry_slug'] ?? $entryId,
                'title' => $catalogItem['entry_title'] ?? $catalogItem['entry_slug'] ?? $entryId,
                'sku' => $catalogItem['sku'] ?? $catalogItem['entry_slug'] ?? '',
                'quantity' => $qty,
                'purchase_option_id' => $option['id'] ?? 'default',
                'purchase_option_label' => $option['label'] ?? '',
                'fulfillment' => $commerce['fulfillment'] ?? 'ship',
                'commerce' => $commerce,
                'variant_attrs' => $catalogItem['variant_attrs'] ?? null,
                'base_unit_cents' => $baseUnit,
                'effective_unit_cents' => $effectiveUnit,
                'base_amount_cents' => $baseUnit * $qty,
                'discount_amount_cents' => $lineDiscount,
                'effective_amount_cents' => $lineTotal,
                'line_total_cents' => $lineTotal,
                'unit_price_cents' => $effectiveUnit,
                'currency' => $currency,
                'billing' => $option['billing'] ?? 'one_time',
                'interval' => $option['interval'] ?? null,
            ];
            $totalCents += $lineTotal;
            $totalDiscount += $lineDiscount;
        }

        if (count($billingModes) > 1) {
            return [
                'ok' => false,
                'error' => 'Cannot mix one-time and recurring items in the same cart',
                'lines' => [],
                'total_cents' => 0,
                'discount_cents' => 0,
                'currency' => $currency,
            ];
        }

        $shippingCents = $this->calculateShipping($lines);
        $totalCents += $shippingCents;

        return [
            'ok' => true,
            'lines' => $lines,
            'total_cents' => $totalCents,
            'shipping_cents' => $shippingCents,
            'discount_cents' => $totalDiscount,
            'currency' => $currency,
        ];
    }

    public function calculateShipping(array $lines, ?array $shippingAddress = null): int
    {
        $hasShip = false;
        foreach ($lines as $line) {
            if (($line['fulfillment'] ?? '') === 'ship') {
                $hasShip = true;
                break;
            }
        }
        if (!$hasShip) {
            return 0;
        }

        $zonesFile = dirname($this->indexer->getIndexPath()) . '/shipping_zones.json';
        if (!is_file($zonesFile)) {
            $fallback = commerce_data_dir() . '/shipping_zones.json';
            if (is_file($fallback)) {
                $zonesFile = $fallback;
            } else {
                return 0;
            }
        }
        $data = json_decode((string) file_get_contents($zonesFile), true);
        $zones = $data['zones'] ?? [];
        if (empty($zones)) {
            return 0;
        }

        $country = strtoupper(trim((string) ($shippingAddress['country'] ?? '')));
        foreach ($zones as $zone) {
            $countries = $zone['countries'] ?? ['*'];
            if (in_array('*', $countries, true) || ($country !== '' && in_array($country, $countries, true))) {
                return max(0, (int) ($zone['flat_rate_cents'] ?? 0));
            }
        }

        return max(0, (int) ($zones[0]['flat_rate_cents'] ?? 0));
    }

    public function loadShippingZones(): array
    {
        $zonesFile = dirname($this->indexer->getIndexPath()) . '/shipping_zones.json';
        if (!is_file($zonesFile)) {
            $zonesFile = commerce_data_dir() . '/shipping_zones.json';
        }
        if (!is_file($zonesFile)) {
            return ['zones' => []];
        }
        $data = json_decode((string) file_get_contents($zonesFile), true);
        return is_array($data) ? $data : ['zones' => []];
    }

    public function saveShippingZones(array $data): void
    {
        $dir = dirname($this->indexer->getIndexPath());
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($dir . '/shipping_zones.json', json_encode($data, JSON_PRETTY_PRINT));
    }
}
