<?php
/**
 * P13-M3 — Subscription marketplace license lapse blocks CMS updates.
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/core/marketplace.php';

test_reset_failures();
echo "Marketplace subscription license tests\n\n";

$subOffer = [
    'slug' => 'birkly-analytics-pro',
    'pricing' => [
        'model' => 'subscription',
        'amount_cents' => 999,
        'currency' => 'USD',
        'subscription_interval' => 'month',
    ],
];

test_assert('subscription offer requires license', birkly_marketplace_offer_requires_license($subOffer));
test_assert('subscription offer not free', !birkly_marketplace_offer_is_free($subOffer));

$fallback = birkly_marketplace_find_fallback_offer('birkly-analytics-pro');
test_assert(
    'fallback catalog has subscription offer',
    is_array($fallback) && (($fallback['pricing']['model'] ?? '') === 'subscription')
);

$GLOBALS['__birkly_installation_id'] = 'inst_subscription_test';

$GLOBALS['birkly_marketplace_mock_handler'] = function (string $method, string $path, ?array $body, array $query): array {
    if ($method === 'GET' && $path === '/api/v1/licenses/status') {
        $active = ($query['license_ref'] ?? '') !== 'LAPSED';
        return [
            'ok' => true,
            'status' => 200,
            'data' => $active ? [
                'ok' => true,
                'status' => 'active',
                'pricing_model' => 'subscription',
                'subscription_status' => 'active',
                'offer_slug' => (string) ($query['offer_slug'] ?? ''),
            ] : [
                'ok' => false,
                'error' => 'license_inactive',
                'message' => 'License inactive',
            ],
        ];
    }
    if ($method === 'GET' && str_contains($path, '/api/v1/offers/birkly-analytics-pro')) {
        return [
            'ok' => true,
            'status' => 200,
            'data' => [
                'offer' => [
                    'slug' => 'birkly-analytics-pro',
                    'pricing' => [
                        'model' => 'subscription',
                        'amount_cents' => 999,
                        'subscription_interval' => 'month',
                    ],
                ],
                'releases' => [['version' => '1.0.0'], ['version' => '1.2.0']],
            ],
        ];
    }
    return ['ok' => false, 'status' => 404, 'data' => null, 'error' => 'not_found'];
};

$active = birkly_marketplace_verify_license_status('birkly-analytics-pro', 'ACTIVE1');
test_assert('active subscription license verifies', !empty($active['ok']) && !empty($active['active']));

$lapsed = birkly_marketplace_verify_license_status('birkly-analytics-pro', 'LAPSED');
test_assert('lapsed subscription license flagged', empty($lapsed['active']) && !empty($lapsed['license_lapsed']));

$updateActive = birkly_marketplace_check_plugin_update('birkly-analytics-pro', '1.0.0', 'ACTIVE1');
test_assert('update available with active subscription', !empty($updateActive['update_available']));

$updateLapsed = birkly_marketplace_check_plugin_update('birkly-analytics-pro', '1.0.0', 'LAPSED');
test_assert('update blocked when subscription lapsed', empty($updateLapsed['update_available']));
test_assert('license lapsed flag set', !empty($updateLapsed['license_lapsed']));
test_assert('admin warning present', !empty($updateLapsed['license_warning']));

unset($GLOBALS['birkly_marketplace_mock_handler']);
birkly_installation_id_reset();
birkly_marketplace_settings_reset();

exit(test_finish('marketplace_subscription_license_test'));
