<?php
/**
 * J-INFLUENCER — Fan / merch (influencer + commerce).
 */

require_once __DIR__ . '/_helpers.php';

return [
    'id' => 'J-INFLUENCER',
    'name' => 'Influencer fan / merch',
    'server' => 'dev',
    'run' => function (array $ctx): void {
        $base = $ctx['base_url'];
        journey_install_dev_plugins();
        journey_activate_plugins('commerce', 'events', 'user_management');
        journey_install_reference_project('influencer');
        journey_commerce_reindex();

        $prefix = '/reference-projects/influencer/website';
        $index = journey_http($base, $prefix . '/index.html');
        journey_assert('influencer index HTTP 200', $index['code'] === 200);
        journey_assert('influencer index has birkly-client.js', strpos($index['body'], 'birkly-client.js') !== false);
        journey_assert('link blocks nav present', strpos($index['body'], '#blog') !== false && strpos($index['body'], '#shop') !== false);

        $bundle = journey_page_bundle($base, [
            ['type' => 'list_entries', 'into' => 'products', 'collection' => 'products'],
        ]);
        journey_assert('merch/products bundle ok', ($bundle['status'] ?? '') === 'ok');
        journey_assert('products for merch section', count($bundle['data']['products'] ?? []) > 0);

        $cookieFile = journey_temp_cookie_file('influencer-cart');
        $products = $bundle['data']['products'] ?? [];
        $variantId = 'var-eth-250-whole';
        journey_assert('product variant id available', $variantId !== '');

        $add = journey_http($base, '/api/index.php?route=commerce&action=add', [
            'method' => 'POST',
            'headers' => ['Content-Type: application/json'],
            'body' => json_encode([
                'entry_id' => $variantId,
                'collection' => 'products',
                'quantity' => 1,
                'purchase_option_id' => 'buy_once',
            ]),
            'cookie_file' => $cookieFile,
        ]);
        $addData = birkly_test_json_decode($add['body']);
        journey_assert('merch add-to-cart ok', ($addData['status'] ?? '') === 'ok', $add['body']);
        @unlink($cookieFile);
    },
];
