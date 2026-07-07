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
        if (!class_exists('CommerceBundleSchema')) {
            require_once __DIR__ . '/CommerceBundleSchema.php';
        }

        $lines = [];
        $totalCents = 0;
        $totalDiscount = 0;
        $currency = function_exists('commerce_get_default_currency') ? commerce_get_default_currency() : 'eur';
        $discountEngine = $discounts ?? new DiscountEngine();
        $billingModes = [];
        $lineCurrencies = [];
        $errors = [];

        foreach ($cartItems as $item) {
            $collectionSlug = $item['collection'] ?? '';
            $entryId = $item['entry_id'] ?? '';
            $qty = max(1, (int) ($item['quantity'] ?? 1));
            $purchaseOptionId = $item['purchase_option_id'] ?? null;

            $catalogItem = $this->getItem($collectionSlug, $entryId);
            if (!$catalogItem) {
                $errors[] = 'Item not in catalog: ' . $collectionSlug . ':' . $entryId;
                continue;
            }

            if (!empty($catalogItem['is_bundle'])) {
                $bundleLine = $this->buildBundleCartLine($catalogItem, $collectionSlug, $entryId, $qty, $discountEngine, $coupon, $affiliateId);
                if ($bundleLine === null) {
                    $errors[] = 'Bundle unavailable: ' . ($catalogItem['entry_title'] ?? $entryId);
                    continue;
                }
                $billingModes[$bundleLine['billing'] ?? 'one_time'] = true;
                $currency = $bundleLine['currency'] ?: $currency;
                $lines[] = $bundleLine;
                $totalCents += (int) $bundleLine['line_total_cents'];
                $totalDiscount += (int) ($bundleLine['discount_amount_cents'] ?? 0);
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
            $lineCurrency = strtolower((string) ($option['currency'] ?? $currency));
            $lineCurrencies[$lineCurrency] = true;

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
                'currency' => $lineCurrency,
                'billing' => $option['billing'] ?? 'one_time',
                'interval' => $option['interval'] ?? null,
            ];
            $currency = $lineCurrency ?: $currency;
            $totalCents += $lineTotal;
            $totalDiscount += $lineDiscount;
        }

        if (count($lineCurrencies) > 1) {
            return [
                'ok' => false,
                'error' => 'Cannot mix currencies in the same cart',
                'lines' => [],
                'total_cents' => 0,
                'discount_cents' => 0,
                'currency' => $currency,
            ];
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

        if (!empty($errors) && empty($lines)) {
            return [
                'ok' => false,
                'error' => $errors[0],
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
            if (!empty($line['is_bundle'])) {
                foreach ($line['components'] ?? [] as $comp) {
                    if (($comp['fulfillment'] ?? '') === 'ship') {
                        $hasShip = true;
                        break 2;
                    }
                }
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

    /**
     * Tax rules v1 — flat default rate + optional country → rate map.
     *
     * @return array{enabled: bool, default_rate: float, country_rates: array<int, array{country: string, rate: float}>}
     */
    public function loadTaxRules(): array
    {
        $file = dirname($this->indexer->getIndexPath()) . '/tax_rules.json';
        if (!is_file($file)) {
            $file = commerce_data_dir() . '/tax_rules.json';
        }
        if (!is_file($file)) {
            return ['enabled' => false, 'default_rate' => 0.0, 'country_rates' => []];
        }
        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data)) {
            return ['enabled' => false, 'default_rate' => 0.0, 'country_rates' => []];
        }
        return [
            'enabled' => (bool) ($data['enabled'] ?? false),
            'default_rate' => (float) ($data['default_rate'] ?? 0.0),
            'country_rates' => array_values(array_filter(array_map(function ($r) {
                $country = strtoupper(trim((string) ($r['country'] ?? '')));
                if ($country === '') {
                    return null;
                }
                return ['country' => $country, 'rate' => (float) ($r['rate'] ?? 0.0)];
            }, is_array($data['country_rates'] ?? null) ? $data['country_rates'] : []))),
        ];
    }

    public function saveTaxRules(array $data): void
    {
        $dir = dirname($this->indexer->getIndexPath());
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $normalized = [
            'enabled' => (bool) ($data['enabled'] ?? false),
            'default_rate' => (float) ($data['default_rate'] ?? 0.0),
            'country_rates' => array_values(array_filter(array_map(function ($r) {
                $country = strtoupper(trim((string) ($r['country'] ?? '')));
                if ($country === '') {
                    return null;
                }
                return ['country' => $country, 'rate' => (float) ($r['rate'] ?? 0.0)];
            }, is_array($data['country_rates'] ?? null) ? $data['country_rates'] : []))),
        ];
        file_put_contents($dir . '/tax_rules.json', json_encode($normalized, JSON_PRETTY_PRINT));
    }

    /**
     * Resolve the applicable tax rate (percent) for a destination country.
     */
    public function resolveTaxRate(?string $country, ?array $rules = null): float
    {
        $rules = $rules ?? $this->loadTaxRules();
        if (empty($rules['enabled'])) {
            return 0.0;
        }
        $country = strtoupper(trim((string) $country));
        if ($country !== '') {
            foreach ($rules['country_rates'] ?? [] as $entry) {
                if (($entry['country'] ?? '') === $country) {
                    return max(0.0, (float) ($entry['rate'] ?? 0.0));
                }
            }
        }
        return max(0.0, (float) ($rules['default_rate'] ?? 0.0));
    }

    /**
     * Compute tax for a set of cart lines. Tax base is the sum of effective line
     * totals (post-discount goods subtotal); shipping is excluded in v1.
     *
     * @return array{tax_cents: int, rate: float, country: string, taxable_cents: int}
     */
    public function calculateTax(array $lines, ?array $shippingAddress = null): array
    {
        $rules = $this->loadTaxRules();
        $country = strtoupper(trim((string) ($shippingAddress['country'] ?? '')));
        $rate = $this->resolveTaxRate($country, $rules);

        $taxable = 0;
        foreach ($lines as $line) {
            $taxable += (int) ($line['line_total_cents'] ?? $line['effective_amount_cents'] ?? 0);
        }

        $taxCents = $rate > 0 ? (int) round($taxable * ($rate / 100)) : 0;

        return [
            'tax_cents' => $taxCents,
            'rate' => $rate,
            'country' => $country,
            'taxable_cents' => $taxable,
        ];
    }

    private function buildBundleCartLine(
        array $catalogItem,
        string $collectionSlug,
        string $entryId,
        int $qty,
        DiscountEngine $discountEngine,
        ?string $coupon,
        ?string $affiliateId
    ): ?array {
        $bundle = commerce_bundle_normalize_value($catalogItem['bundle'] ?? []);
        if ($bundle['billing'] !== 'one_time') {
            return null;
        }

        $index = $this->indexer->loadIndex();
        $resolved = CommerceBundleSchema::resolveComponents($bundle, $index['entries'] ?? []);
        if (!$resolved['valid'] || empty($resolved['items'])) {
            return null;
        }

        $components = $resolved['items'];
        $baseUnit = CommerceBundleSchema::computePriceCents($bundle, $components);
        $quantityAvailable = CommerceBundleSchema::computeQuantityAvailable($bundle, $components);

        if ($quantityAvailable !== null && (int) $quantityAvailable < $qty) {
            return null;
        }
        $pricedItem = array_merge($catalogItem, [
            'price_cents' => $baseUnit,
            'currency' => $catalogItem['currency'] ?? 'eur',
            'billing' => 'one_time',
            'interval' => null,
        ]);

        $pricing = $discountEngine->applyToLine($pricedItem, $qty, $coupon, $affiliateId);
        $effectiveUnit = (int) $pricing['effective_unit_cents'];
        $lineDiscount = (int) $pricing['discount_cents'];
        $lineTotal = $effectiveUnit * $qty;

        $expandedComponents = [];
        foreach ($components as $comp) {
            if (!($comp['valid'] ?? false)) {
                return null;
            }
            $compQty = max(1, (int) ($comp['quantity'] ?? 1)) * $qty;
            $expandedComponents[] = [
                'collection' => $comp['collection'],
                'entry_id' => $comp['entry_id'],
                'quantity' => $compQty,
                'title' => $comp['title'] ?? $comp['entry_id'],
                'fulfillment' => $comp['fulfillment'] ?? 'ship',
                'commerce' => $comp['commerce'] ?? null,
                'purchase_option_id' => $comp['purchase_option_id'] ?? 'default',
            ];
        }

        return [
            'line_id' => $collectionSlug . ':' . $entryId . ':bundle',
            'entry_id' => $entryId,
            'collection_slug' => $collectionSlug,
            'entry_slug' => $catalogItem['entry_slug'] ?? $entryId,
            'title' => $catalogItem['entry_title'] ?? $entryId,
            'sku' => $catalogItem['sku'] ?? '',
            'quantity' => $qty,
            'purchase_option_id' => 'default',
            'purchase_option_label' => 'Bundle',
            'fulfillment' => $bundle['fulfillment'] ?? ($catalogItem['fulfillment'] ?? 'ship'),
            'commerce' => $bundle,
            'is_bundle' => true,
            'bundle' => $bundle,
            'components' => $expandedComponents,
            'base_unit_cents' => $baseUnit,
            'effective_unit_cents' => $effectiveUnit,
            'base_amount_cents' => $baseUnit * $qty,
            'discount_amount_cents' => $lineDiscount,
            'effective_amount_cents' => $lineTotal,
            'line_total_cents' => $lineTotal,
            'unit_price_cents' => $effectiveUnit,
            'currency' => $catalogItem['currency'] ?? 'eur',
            'billing' => 'one_time',
            'interval' => null,
        ];
    }
}
