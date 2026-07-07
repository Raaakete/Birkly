<?php
/**
 * P10-M3 — Free marketplace install (no license key for free official plugins).
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/core/marketplace.php';

test_reset_failures();
echo "Marketplace free install tests\n\n";

$freeOffer = [
    'slug' => 'birkly-hello-starter',
    'title' => 'Hello Starter',
    'pricing' => ['model' => 'free', 'amount_cents' => 0, 'currency' => 'USD'],
];
$paidOffer = [
    'slug' => 'birkly-seo-basics',
    'title' => 'SEO Basics',
    'pricing' => ['model' => 'one_time', 'amount_cents' => 1499, 'currency' => 'USD'],
];

test_assert('free offer detected by model', birkly_marketplace_offer_is_free($freeOffer));
test_assert('paid offer not free', !birkly_marketplace_offer_is_free($paidOffer));

$zeroCentsOffer = ['pricing' => ['model' => 'one_time', 'amount_cents' => 0]];
test_assert('zero cents treated as free', birkly_marketplace_offer_is_free($zeroCentsOffer));

$fallbackOffer = birkly_marketplace_find_fallback_offer('birkly-hello-starter');
test_assert('fallback catalog has hello-starter', is_array($fallbackOffer) && ($fallbackOffer['slug'] ?? '') === 'birkly-hello-starter');

$GLOBALS['birkly_marketplace_mock_handler'] = function (string $method, string $path, ?array $body, array $query): array {
    return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'Marketplace request failed'];
};

$detail = birkly_marketplace_offer_detail('birkly-hello-starter');
test_assert('offer detail falls back to bundled catalog', !empty($detail['ok']) && !empty($detail['fallback']));
test_assert('fallback offer is free', birkly_marketplace_offer_is_free($detail['offer'] ?? []));

$claim = birkly_marketplace_claim_free_license('birkly-hello-starter');
test_assert('free claim succeeds offline', !empty($claim['ok']) && ($claim['source'] ?? '') === 'local');
test_assert('local free license key prefix', str_starts_with((string) ($claim['license_key'] ?? ''), 'BIRKLY-LOCAL-FREE-'));

$paidClaim = birkly_marketplace_claim_free_license('birkly-seo-basics');
test_assert('paid offer rejects free claim', empty($paidClaim['ok']) && ($paidClaim['code'] ?? '') === 'license_required');

unset($GLOBALS['birkly_marketplace_mock_handler']);

// Mock live marketplace: checkout + activate for free offer
$GLOBALS['birkly_marketplace_mock_handler'] = function (string $method, string $path, ?array $body, array $query): array {
    if ($method === 'GET' && str_contains($path, '/api/v1/offers/birkly-commerce')) {
        return [
            'ok' => true,
            'status' => 200,
            'data' => [
                'offer' => [
                    'slug' => 'birkly-commerce',
                    'title' => 'Commerce',
                    'pricing' => ['model' => 'free', 'amount_cents' => 0, 'currency' => 'USD'],
                ],
                'releases' => [['version' => '1.0.0']],
            ],
        ];
    }
    if ($method === 'POST' && $path === '/api/v1/checkout') {
        return [
            'ok' => true,
            'status' => 201,
            'data' => [
                'license' => ['license_key' => 'BIRKLY-AAAA-BBBB-CCCC-DDDD'],
                'order' => ['license_key' => 'BIRKLY-AAAA-BBBB-CCCC-DDDD'],
            ],
        ];
    }
    if ($method === 'POST' && $path === '/api/v1/licenses/activate') {
        return [
            'ok' => true,
            'status' => 200,
            'data' => [
                'ok' => true,
                'code' => 'activated',
                'offer_slug' => 'birkly-commerce',
                'license' => ['offer_slug' => 'birkly-commerce'],
            ],
        ];
    }
    return ['ok' => false, 'status' => 404, 'data' => null, 'error' => 'not_found'];
};

$GLOBALS['__birkly_installation_id'] = 'inst_free_install_test';
$liveClaim = birkly_marketplace_claim_free_license('birkly-commerce');
test_assert('live free claim uses marketplace checkout', !empty($liveClaim['ok']) && ($liveClaim['source'] ?? '') === 'marketplace');
test_assert('marketplace license key returned', ($liveClaim['license_key'] ?? '') === 'BIRKLY-AAAA-BBBB-CCCC-DDDD');

$updateCheck = birkly_marketplace_check_plugin_update('birkly-commerce', '1.0.0');
test_assert('update check works for marketplace offer', !empty($updateCheck['ok']));

unset($GLOBALS['birkly_marketplace_mock_handler']);
birkly_installation_id_reset();

exit(test_finish('marketplace_free_install_test'));
