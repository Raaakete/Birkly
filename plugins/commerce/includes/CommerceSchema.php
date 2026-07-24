<?php
/**
 * Commerce field value schema — v2 normalization and legacy migration.
 */

class CommerceSchema
{
    public const FULFILLMENTS = ['ship', 'deliver', 'register', 'none'];
    public const BILLING_MODES = ['one_time', 'recurring'];
    public const INTERVALS = ['week', 'month', 'year'];
    public const DELIVERY_MODES = ['secure_download', 'external_url'];

    public static function normalizeValue($value): array
    {
        if (!is_array($value)) {
            return self::withLegacyFields(self::defaultValue());
        }

        if (self::isLegacyValue($value)) {
            $value = self::migrateLegacyValue($value);
        }

        return self::withLegacyFields(self::normalizeV2($value));
    }

    public static function isLegacyValue(array $value): bool
    {
        if (!empty($value['purchase_options']) && is_array($value['purchase_options'])) {
            return false;
        }
        return isset($value['price_cents']) || isset($value['billing']);
    }

    public static function migrateLegacyValue(array $value): array
    {
        $billing = in_array($value['billing'] ?? '', self::BILLING_MODES, true)
            ? $value['billing'] : 'one_time';
        $interval = $value['interval'] ?? null;
        if ($billing === 'recurring' && !self::isValidInterval($interval)) {
            $interval = 'month';
        }
        if ($billing !== 'recurring') {
            $interval = null;
        }

        $priceCents = max(0, (int) ($value['price_cents'] ?? 0));
        if ($priceCents === 0 && isset($value['price']) && is_numeric($value['price'])) {
            $priceCents = (int) round((float) $value['price'] * 100);
        }

        $qty = $value['quantity_available'] ?? null;
        if ($qty === '' || $qty === false) {
            $qty = null;
        } elseif ($qty !== null) {
            $qty = (int) $qty;
        }

        $sku = isset($value['sku']) && $value['sku'] !== '' ? trim((string) $value['sku']) : null;

        return [
            'fulfillment' => in_array($value['fulfillment'] ?? '', self::FULFILLMENTS, true)
                ? $value['fulfillment'] : 'ship',
            'purchase_options' => [[
                'id' => 'default',
                'label' => 'Default',
                'billing' => $billing,
                'price_cents' => $priceCents,
                'currency' => strtolower((string) ($value['currency'] ?? 'eur')),
                'interval' => $interval,
            ]],
            'quantity_available' => $qty,
            'sku' => $sku,
            'weight_grams' => isset($value['weight_grams']) ? (int) $value['weight_grams'] : null,
            'delivery_asset' => isset($value['delivery_asset']) ? trim((string) $value['delivery_asset']) : null,
            'delivery_url' => isset($value['delivery_url']) ? trim((string) $value['delivery_url']) : null,
            'delivery_mode' => in_array($value['delivery_mode'] ?? '', self::DELIVERY_MODES, true)
                ? $value['delivery_mode'] : 'secure_download',
            'custom_fields' => is_array($value['custom_fields'] ?? null) ? $value['custom_fields'] : [],
        ];
    }

    public static function normalizeV2(array $value): array
    {
        $defaults = self::defaultValue();
        $fulfillment = in_array($value['fulfillment'] ?? '', self::FULFILLMENTS, true)
            ? $value['fulfillment'] : $defaults['fulfillment'];

        $options = [];
        foreach ($value['purchase_options'] ?? [] as $idx => $opt) {
            if (!is_array($opt)) {
                continue;
            }
            $billing = in_array($opt['billing'] ?? '', self::BILLING_MODES, true)
                ? $opt['billing'] : 'one_time';
            $interval = $opt['interval'] ?? null;
            if ($billing === 'recurring' && !self::isValidInterval($interval)) {
                $interval = 'month';
            }
            if ($billing !== 'recurring') {
                $interval = null;
            }

            $id = trim((string) ($opt['id'] ?? ''));
            if ($id === '') {
                $id = 'opt_' . ($idx + 1);
            }

            $options[] = [
                'id' => $id,
                'label' => trim((string) ($opt['label'] ?? $id)),
                'billing' => $billing,
                'price_cents' => max(0, (int) ($opt['price_cents'] ?? 0)),
                'currency' => strtolower((string) ($opt['currency'] ?? (function_exists('commerce_get_default_currency') ? commerce_get_default_currency() : 'eur'))),
                'interval' => $interval,
            ];
        }

        if (empty($options)) {
            $options = $defaults['purchase_options'];
        }

        $qty = $value['quantity_available'] ?? null;
        if ($qty === '' || $qty === false) {
            $qty = null;
        } elseif ($qty !== null) {
            $qty = (int) $qty;
        }

        $sku = isset($value['sku']) && $value['sku'] !== '' ? trim((string) $value['sku']) : null;
        $weight = $value['weight_grams'] ?? null;
        if ($weight === '' || $weight === false) {
            $weight = null;
        } elseif ($weight !== null) {
            $weight = max(0, (int) $weight);
        }

        return [
            'fulfillment' => $fulfillment,
            'purchase_options' => $options,
            'quantity_available' => $qty,
            'sku' => $sku,
            'weight_grams' => $weight,
            'delivery_asset' => isset($value['delivery_asset']) && $value['delivery_asset'] !== ''
                ? trim((string) $value['delivery_asset']) : null,
            'delivery_url' => isset($value['delivery_url']) && $value['delivery_url'] !== ''
                ? trim((string) $value['delivery_url']) : null,
            'delivery_mode' => in_array($value['delivery_mode'] ?? '', self::DELIVERY_MODES, true)
                ? $value['delivery_mode'] : 'secure_download',
            'custom_fields' => is_array($value['custom_fields'] ?? null) ? $value['custom_fields'] : [],
        ];
    }

    public static function withLegacyFields(array $value): array
    {
        $first = $value['purchase_options'][0] ?? [];
        $value['price_cents'] = (int) ($first['price_cents'] ?? 0);
        $value['billing'] = $first['billing'] ?? 'one_time';
        $value['currency'] = $first['currency'] ?? 'eur';
        $value['interval'] = $first['interval'] ?? null;
        return $value;
    }

    public static function defaultValue(): array
    {
        $defaultCurrency = function_exists('commerce_get_default_currency') ? commerce_get_default_currency() : 'eur';

        return self::withLegacyFields([
            'fulfillment' => 'ship',
            'purchase_options' => [[
                'id' => 'default',
                'label' => 'Default',
                'billing' => 'one_time',
                'price_cents' => 0,
                'currency' => $defaultCurrency,
                'interval' => null,
            ]],
            'quantity_available' => null,
            'sku' => null,
            'weight_grams' => null,
            'delivery_asset' => null,
            'delivery_url' => null,
            'delivery_mode' => 'secure_download',
            'custom_fields' => [],
        ]);
    }

    public static function findPurchaseOption(array $commerce, ?string $optionId): ?array
    {
        $commerce = self::normalizeValue($commerce);
        $options = $commerce['purchase_options'] ?? [];
        if ($optionId === null || $optionId === '') {
            return $options[0] ?? null;
        }
        foreach ($options as $opt) {
            if (($opt['id'] ?? '') === $optionId) {
                return $opt;
            }
        }
        return null;
    }

    public static function priceFrom(array $commerce): int
    {
        $commerce = self::normalizeValue($commerce);
        $min = null;
        foreach ($commerce['purchase_options'] ?? [] as $opt) {
            $price = (int) ($opt['price_cents'] ?? 0);
            if ($min === null || $price < $min) {
                $min = $price;
            }
        }
        return $min ?? 0;
    }

    public static function validate(array $value, bool $required = false): array
    {
        $errors = [];
        $normalized = self::normalizeValue($value);

        if ($required && empty($normalized['purchase_options'])) {
            $errors[] = 'At least one purchase option is required';
        }

        foreach ($normalized['purchase_options'] as $opt) {
            if ((int) ($opt['price_cents'] ?? 0) < 0) {
                $errors[] = 'Price must be zero or greater';
            }
            if (!in_array($opt['billing'] ?? '', self::BILLING_MODES, true)) {
                $errors[] = 'Billing must be one_time or recurring';
            }
        }

        if (!in_array($normalized['fulfillment'], self::FULFILLMENTS, true)) {
            $errors[] = 'Invalid fulfillment type';
        }

        return $errors;
    }

    public static function isValidInterval(?string $interval): bool
    {
        if ($interval === null || $interval === '') {
            return false;
        }
        if (in_array($interval, self::INTERVALS, true)) {
            return true;
        }
        return (bool) preg_match('/^[a-z0-9_]+$/', $interval);
    }
}

function commerce_normalize_value($value): array
{
    return CommerceSchema::normalizeValue($value);
}
