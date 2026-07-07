<?php
/**
 * P13-M2 — Paid official marketplace install gate in CMS.
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/core/marketplace.php';

test_reset_failures();
echo "Marketplace paid official tests\n\n";

$paidOffer = [
    'slug' => 'birkly-seo-basics',
    'title' => 'SEO Basics',
    'pricing' => ['model' => 'one_time', 'amount_cents' => 1499, 'currency' => 'USD'],
];
$freeOffer = [
    'slug' => 'birkly-hello-starter',
    'pricing' => ['model' => 'free', 'amount_cents' => 0, 'currency' => 'USD'],
];

test_assert('paid offer requires license', birkly_marketplace_offer_requires_license($paidOffer));
test_assert('free offer does not require license', !birkly_marketplace_offer_requires_license($freeOffer));

$paidClaim = birkly_marketplace_claim_free_license('birkly-seo-basics');
test_assert('paid offer rejects free claim', empty($paidClaim['ok']) && ($paidClaim['code'] ?? '') === 'license_required');

$fallbackPaid = birkly_marketplace_find_fallback_offer('birkly-seo-basics');
test_assert('fallback catalog has paid seo-basics', is_array($fallbackPaid) && (int) (($fallbackPaid['pricing']['amount_cents'] ?? 0)) > 0);

test_assert(
    'purchase URL uses marketplace base',
    str_contains(birkly_marketplace_purchase_url('birkly-seo-basics'), '/buy/birkly-seo-basics')
);

$GLOBALS['birkly_marketplace_mock_handler'] = function (string $method, string $path, ?array $body, array $query): array {
    if ($method === 'GET' && $path === '/api/v1/licenses/status') {
        return [
            'ok' => true,
            'status' => 200,
            'data' => [
                'ok' => true,
                'status' => 'active',
                'pricing_model' => 'one_time',
                'offer_slug' => (string) ($query['offer_slug'] ?? ''),
            ],
        ];
    }
    if ($method === 'GET' && str_contains($path, '/api/v1/offers/birkly-seo-basics')) {
        return [
            'ok' => true,
            'status' => 200,
            'data' => [
                'offer' => [
                    'slug' => 'birkly-seo-basics',
                    'pricing' => ['model' => 'one_time', 'amount_cents' => 1499],
                ],
                'releases' => [['version' => '1.0.0'], ['version' => '1.1.0']],
            ],
        ];
    }
    return ['ok' => false, 'status' => 404, 'data' => null, 'error' => 'not_found'];
};

$activeCheck = birkly_marketplace_check_plugin_update('birkly-seo-basics', '1.0.0', 'ABCD');
test_assert('paid plugin update available with active license', !empty($activeCheck['update_available']));

unset($GLOBALS['birkly_marketplace_mock_handler']);
birkly_marketplace_settings_reset();

exit(test_finish('marketplace_paid_official_test'));
