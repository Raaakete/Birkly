<?php
/**
 * Commerce bundle field value schema — normalization, validation, pricing, stock.
 */

class CommerceBundleSchema
{
    public const PRICING_MODES = ['sum', 'sum_with_discount', 'fixed'];
    public const FULFILLMENTS = ['ship', 'deliver', 'register', 'none'];

    public static function defaultValue(): array
    {
        return [
            'items' => [],
            'pricing_mode' => 'sum',
            'bundle_discount_percent' => 0,
            'fixed_price_cents' => null,
            'currency' => 'eur',
            'billing' => 'one_time',
            'interval' => null,
            'quantity_available' => null,
            'fulfillment' => 'ship',
        ];
    }

    public static function normalizeValue($value): array
    {
        if (!is_array($value)) {
            return self::defaultValue();
        }

        $defaults = self::defaultValue();
        $pricingMode = in_array($value['pricing_mode'] ?? '', self::PRICING_MODES, true)
            ? $value['pricing_mode'] : $defaults['pricing_mode'];

        $items = [];
        foreach ($value['items'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $collection = trim((string) ($item['collection'] ?? ''));
            $entryId = trim((string) ($item['entry_id'] ?? ''));
            if ($collection === '' || $entryId === '') {
                continue;
            }
            $items[] = [
                'collection' => $collection,
                'entry_id' => $entryId,
                'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                'title' => trim((string) ($item['title'] ?? '')),
            ];
        }

        $discount = max(0, min(100, (int) ($value['bundle_discount_percent'] ?? 0)));
        $fixedPrice = $value['fixed_price_cents'] ?? null;
        if ($fixedPrice === '' || $fixedPrice === false) {
            $fixedPrice = null;
        } elseif ($fixedPrice !== null) {
            $fixedPrice = max(0, (int) $fixedPrice);
        }

        $qty = $value['quantity_available'] ?? null;
        if ($qty === '' || $qty === false) {
            $qty = null;
        } elseif ($qty !== null) {
            $qty = max(0, (int) $qty);
        }

        $billing = ($value['billing'] ?? 'one_time') === 'recurring' ? 'recurring' : 'one_time';
        $interval = null;
        if ($billing === 'recurring') {
            $interval = $value['interval'] ?? null;
        }

        return [
            'items' => $items,
            'pricing_mode' => $pricingMode,
            'bundle_discount_percent' => $discount,
            'fixed_price_cents' => $fixedPrice,
            'currency' => strtolower((string) ($value['currency'] ?? 'eur')),
            'billing' => $billing,
            'interval' => $interval,
            'quantity_available' => $qty,
            'fulfillment' => in_array($value['fulfillment'] ?? '', self::FULFILLMENTS, true)
                ? $value['fulfillment'] : $defaults['fulfillment'],
        ];
    }

    public static function isBundleValue(array $value): bool
    {
        return isset($value['items']) && is_array($value['items'])
            && isset($value['pricing_mode'])
            && in_array($value['pricing_mode'], self::PRICING_MODES, true);
    }

    public static function validate(array $value, bool $required = false): array
    {
        $errors = [];
        $normalized = self::normalizeValue($value);

        if ($required && empty($normalized['items'])) {
            $errors[] = 'At least one bundle component is required';
        }

        if ($normalized['billing'] !== 'one_time') {
            $errors[] = 'Bundle billing must be one_time in v1';
        }

        if ($normalized['pricing_mode'] === 'fixed' && $normalized['fixed_price_cents'] === null) {
            $errors[] = 'Fixed price is required when pricing mode is fixed';
        }

        if (!in_array($normalized['fulfillment'], self::FULFILLMENTS, true)) {
            $errors[] = 'Invalid fulfillment type';
        }

        return $errors;
    }

    /**
     * Resolve bundle components against catalog index rows.
     *
     * @return array{items: array, valid: bool, issues: string[]}
     */
    public static function resolveComponents(array $bundle, array $catalogEntries): array
    {
        $bundle = self::normalizeValue($bundle);
        $resolved = [];
        $issues = [];
        $currency = $bundle['currency'];

        foreach ($bundle['items'] as $item) {
            $key = $item['collection'] . ':' . $item['entry_id'];
            $catalogRow = $catalogEntries[$key] ?? null;

            if (!$catalogRow) {
                $issues[] = 'Component missing from catalog: ' . $key;
                $resolved[] = array_merge($item, [
                    'valid' => false,
                    'price_cents' => 0,
                    'currency' => $currency,
                    'fulfillment' => 'ship',
                    'commerce' => null,
                ]);
                continue;
            }

            if (!empty($catalogRow['is_bundle'])) {
                $issues[] = 'Nested bundles are not supported: ' . $key;
                $resolved[] = array_merge($item, [
                    'valid' => false,
                    'price_cents' => 0,
                    'currency' => $currency,
                    'fulfillment' => 'ship',
                    'commerce' => null,
                ]);
                continue;
            }

            if (!class_exists('CommerceSchema')) {
                require_once __DIR__ . '/CommerceSchema.php';
            }
            $commerce = commerce_normalize_value($catalogRow['commerce'] ?? $catalogRow);
            $option = CommerceSchema::findPurchaseOption($commerce, null);
            if (!$option) {
                $issues[] = 'Component has no purchase option: ' . $key;
                $resolved[] = array_merge($item, [
                    'valid' => false,
                    'price_cents' => 0,
                    'currency' => $currency,
                    'fulfillment' => $commerce['fulfillment'] ?? 'ship',
                    'commerce' => $commerce,
                ]);
                continue;
            }

            $componentCurrency = strtolower((string) ($option['currency'] ?? 'eur'));
            if ($componentCurrency !== $currency) {
                $issues[] = 'Component currency mismatch: ' . $key;
            }

            if (($option['billing'] ?? 'one_time') !== 'one_time') {
                $issues[] = 'Recurring components cannot be bundled in v1: ' . $key;
            }

            $resolved[] = array_merge($item, [
                'valid' => true,
                'title' => $item['title'] !== '' ? $item['title'] : ($catalogRow['entry_title'] ?? $item['entry_id']),
                'price_cents' => (int) ($option['price_cents'] ?? 0),
                'currency' => $componentCurrency,
                'fulfillment' => $commerce['fulfillment'] ?? 'ship',
                'purchase_option_id' => $option['id'] ?? 'default',
                'commerce' => $commerce,
                'quantity_available' => $catalogRow['quantity_available'] ?? null,
                'entry_slug' => $catalogRow['entry_slug'] ?? $item['entry_id'],
                'sku' => $catalogRow['sku'] ?? null,
            ]);
        }

        $valid = empty($issues) && !empty($resolved) && count(array_filter($resolved, fn ($r) => $r['valid'] ?? false)) === count($resolved);

        return [
            'items' => $resolved,
            'valid' => $valid,
            'issues' => $issues,
        ];
    }

    public static function computePriceCents(array $bundle, array $resolvedItems): int
    {
        $bundle = self::normalizeValue($bundle);

        if ($bundle['pricing_mode'] === 'fixed') {
            return max(0, (int) ($bundle['fixed_price_cents'] ?? 0));
        }

        $sum = 0;
        foreach ($resolvedItems as $item) {
            if (!($item['valid'] ?? false)) {
                continue;
            }
            $sum += (int) ($item['price_cents'] ?? 0) * max(1, (int) ($item['quantity'] ?? 1));
        }

        if ($bundle['pricing_mode'] === 'sum_with_discount') {
            $pct = max(0, min(100, (int) ($bundle['bundle_discount_percent'] ?? 0)));
            $sum = (int) round($sum * (100 - $pct) / 100);
        }

        return max(0, $sum);
    }

    public static function computeQuantityAvailable(array $bundle, array $resolvedItems): ?int
    {
        $bundle = self::normalizeValue($bundle);
        $limits = [];

        if ($bundle['quantity_available'] !== null) {
            $limits[] = (int) $bundle['quantity_available'];
        }

        foreach ($resolvedItems as $item) {
            if (!($item['valid'] ?? false)) {
                continue;
            }
            $available = $item['quantity_available'] ?? null;
            if ($available === null) {
                continue;
            }
            $need = max(1, (int) ($item['quantity'] ?? 1));
            $limits[] = (int) floor((int) $available / $need);
        }

        if (empty($limits)) {
            return $bundle['quantity_available'];
        }

        return max(0, min($limits));
    }
}

function commerce_bundle_normalize_value($value): array
{
    return CommerceBundleSchema::normalizeValue($value);
}
