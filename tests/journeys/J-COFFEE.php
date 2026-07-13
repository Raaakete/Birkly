<?php
/**
 * J-COFFEE — Shopper (commerce plugin + coffee reference project).
 */

require_once __DIR__ . '/_helpers.php';

return [
    'id' => 'J-COFFEE',
    'name' => 'Coffee shop visitor',
    'server' => 'dev',
    'run' => function (array $ctx): void {
        $base = $ctx['base_url'];
        journey_install_dev_plugins();
        journey_activate_plugins('commerce');
        journey_install_reference_project('coffee');
        journey_commerce_reindex();

        $prefix = '/reference-projects/coffee/website';
        $index = journey_http($base, $prefix . '/index.html');
        journey_assert('coffee index HTTP 200', $index['code'] === 200);
        journey_assert('coffee index has birkly-client.js', strpos($index['body'], 'birkly-client.js') !== false);

        $bundle = journey_page_bundle($base, [
            ['type' => 'list_entries', 'into' => 'products', 'collection' => 'products'],
        ]);
        journey_assert('page_bundle products ok', ($bundle['status'] ?? '') === 'ok');
        journey_assert('products collection has entries', count($bundle['data']['products'] ?? []) > 0);

        $product = journey_http($base, $prefix . '/product.html?slug=ethiopian-single-origin');
        journey_assert('product page HTTP 200', $product['code'] === 200);
        journey_assert('product page template has commerce_price_from', strpos($product['body'], 'commerce_price_from') !== false);

        $cookieFile = journey_temp_cookie_file('coffee-cart');
        $add = journey_http($base, '/api/index.php?route=commerce&action=add', [
            'method' => 'POST',
            'headers' => ['Content-Type: application/json'],
            'body' => json_encode([
                'entry_id' => 'var-eth-250-whole',
                'collection' => 'products',
                'quantity' => 1,
                'purchase_option_id' => 'buy_once',
            ]),
            'cookie_file' => $cookieFile,
        ]);
        $addData = birkly_test_json_decode($add['body']);
        journey_assert('add-to-cart HTTP 200', $add['code'] === 200, $add['body']);
        journey_assert('add-to-cart status ok', ($addData['status'] ?? '') === 'ok', $add['body']);
        journey_assert('cart count > 0', (int) ($addData['data']['count'] ?? 0) > 0);
        @unlink($cookieFile);
    },
];
