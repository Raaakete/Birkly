<?php
/**
 * Commerce scenario fixtures — yoga, coffee, course, community.
 */

if (!class_exists('CommerceSchema')) {
    require_once dirname(__DIR__, 2) . '/plugins/commerce/includes/CommerceSchema.php';
}

function commerce_scenario_yoga(): array
{
    return commerce_normalize_value([
        'fulfillment' => 'register',
        'purchase_options' => [[
            'id' => 'class_ticket',
            'label' => 'Class ticket',
            'billing' => 'one_time',
            'price_cents' => 2500,
            'currency' => 'eur',
        ]],
        'quantity_available' => 20,
        'sku' => 'YOGA-SUN',
    ]);
}

function commerce_scenario_coffee_variant(string $variantId = '250g-whole'): array
{
    return commerce_normalize_value([
        'fulfillment' => 'ship',
        'purchase_options' => [
            [
                'id' => 'buy_once',
                'label' => 'One-time purchase',
                'billing' => 'one_time',
                'price_cents' => 1499,
                'currency' => 'eur',
            ],
            [
                'id' => 'subscribe',
                'label' => 'Subscribe & save',
                'billing' => 'recurring',
                'interval' => 'month',
                'price_cents' => 1299,
                'currency' => 'eur',
            ],
        ],
        'quantity_available' => 100,
        'sku' => 'COFFEE-' . strtoupper(str_replace('-', '_', $variantId)),
        'weight_grams' => 250,
    ]);
}

function commerce_scenario_course(): array
{
    return commerce_normalize_value([
        'fulfillment' => 'deliver',
        'purchase_options' => [[
            'id' => 'course_once',
            'label' => 'Full course access',
            'billing' => 'one_time',
            'price_cents' => 8900,
            'currency' => 'eur',
        ]],
        'delivery_mode' => 'secure_download',
        'delivery_asset' => '/media/courses/lighting-masterclass.zip',
        'quantity_available' => null,
        'sku' => 'COURSE-LIGHT',
    ]);
}

function commerce_scenario_community(): array
{
    return commerce_normalize_value([
        'fulfillment' => 'none',
        'purchase_options' => [[
            'id' => 'community_monthly',
            'label' => 'Community membership',
            'billing' => 'recurring',
            'interval' => 'month',
            'price_cents' => 900,
            'currency' => 'eur',
        ]],
        'quantity_available' => null,
        'sku' => 'MEM-COMMUNITY',
    ]);
}

function commerce_scenario_legacy_p64(): array
{
    return [
        'price_cents' => 1999,
        'currency' => 'usd',
        'billing' => 'one_time',
        'quantity_available' => 5,
        'sku' => 'LEGACY-1',
    ];
}

function commerce_scenario_coffee_parent_entry(): array
{
    return [
        'id' => 'coffee-parent',
        'slug' => 'ethiopian-single-origin',
        'title' => 'Ethiopian Single Origin',
        'status' => 'published',
    ];
}

function commerce_scenario_coffee_variant_entry(string $id, string $size, string $grind): array
{
    return [
        'id' => $id,
        'slug' => $id,
        'title' => $size . ' · ' . $grind,
        'size' => $size,
        'grind' => $grind,
        'commerce' => commerce_scenario_coffee_variant($id),
    ];
}
