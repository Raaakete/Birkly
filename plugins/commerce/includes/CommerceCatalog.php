<?php
/**
 * Reads catalog index for cart pricing.
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
        $lines = [];
        $totalCents = 0;
        $totalDiscount = 0;
        $currency = 'eur';
        $discountEngine = $discounts ?? new DiscountEngine();

        foreach ($cartItems as $item) {
            $collectionSlug = $item['collection'] ?? '';
            $entryId = $item['entry_id'] ?? '';
            $qty = max(1, (int) ($item['quantity'] ?? 1));

            $catalogItem = $this->getItem($collectionSlug, $entryId);
            if (!$catalogItem) {
                continue;
            }

            if ($catalogItem['quantity_available'] !== null && (int) $catalogItem['quantity_available'] < $qty) {
                continue;
            }

            $baseUnit = (int) $catalogItem['price_cents'];
            $pricing = $discountEngine->applyToLine($catalogItem, $qty, $coupon, $affiliateId);
            $effectiveUnit = (int) $pricing['effective_unit_cents'];
            $lineDiscount = (int) $pricing['discount_cents'];
            $lineTotal = $effectiveUnit * $qty;
            $currency = $catalogItem['currency'] ?: $currency;

            $lines[] = [
                'entry_id' => $entryId,
                'collection_slug' => $collectionSlug,
                'entry_slug' => $catalogItem['entry_slug'] ?? $entryId,
                'title' => $catalogItem['entry_title'] ?? $catalogItem['entry_slug'] ?? $entryId,
                'sku' => $catalogItem['sku'] ?? $catalogItem['entry_slug'] ?? '',
                'quantity' => $qty,
                'base_unit_cents' => $baseUnit,
                'effective_unit_cents' => $effectiveUnit,
                'base_amount_cents' => $baseUnit * $qty,
                'discount_amount_cents' => $lineDiscount,
                'effective_amount_cents' => $lineTotal,
                'line_total_cents' => $lineTotal,
                'unit_price_cents' => $effectiveUnit,
                'currency' => $currency,
                'billing' => $catalogItem['billing'] ?? 'one_time',
            ];
            $totalCents += $lineTotal;
            $totalDiscount += $lineDiscount;
        }

        return [
            'lines' => $lines,
            'total_cents' => $totalCents,
            'discount_cents' => $totalDiscount,
            'currency' => $currency,
        ];
    }
}
