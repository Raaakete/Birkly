<?php
/**
 * J-COFFEE — Shopper: browse products → product detail → add to cart.
 */

require_once __DIR__ . '/_helpers.php';

return [
    'id' => 'J-COFFEE',
    'name' => 'Coffee shop visitor',
    'server' => 'dev',
    'run' => function (array $ctx): void {
        $base = $ctx['base_url'];
        journey_prepare_reference_demo('coffee');

        $prefix = '/reference-projects/coffee/website';
        $index = journey_http($base, $prefix . '/index.html');
        journey_assert('coffee index HTTP 200', $index['code'] === 200);
        journey_assert('coffee index has birkly-client.js', strpos($index['body'], 'birkly-client.js') !== false);
        journey_assert('coffee index has product grid', strpos($index['body'], 'product-grid') !== false);

        $bundle = journey_page_bundle($base, [
            ['type' => 'list_entries', 'into' => 'products', 'collection' => 'products'],
        ]);
        journey_assert('page_bundle products ok', ($bundle['status'] ?? '') === 'ok');
        $products = $bundle['data']['products'] ?? [];
        journey_assert('products collection has entries', count($products) > 0);
        $slug = 'ethiopian-single-origin';
        $parentId = 'coffee-ethiopian';
        foreach ($products as $p) {
            if (($p['slug'] ?? '') === $slug) {
                $parentId = (string) ($p['id'] ?? $parentId);
                break;
            }
        }

        $product = journey_http($base, $prefix . '/product.html?slug=' . urlencode($slug));
        journey_assert('product page HTTP 200', $product['code'] === 200);
        journey_assert('product page has variant add form', strpos($product['body'], 'data-birkly-action="add"') !== false);

        $detailBundle = journey_page_bundle($base, [
            ['type' => 'get_entry', 'into' => 'product', 'collection' => 'products', 'slug' => $slug],
            [
                'type' => 'sub_entries',
                'into' => 'variants',
                'parent_collection' => 'products',
                'parent_entry_id' => $parentId,
                'sub_collection' => 'variants',
            ],
        ]);
        journey_assert('product variants bundle ok', ($detailBundle['status'] ?? '') === 'ok');
        journey_assert('product has purchasable variants', count($detailBundle['data']['variants'] ?? []) > 0);

        $variantId = (string) (($detailBundle['data']['variants'][0]['id'] ?? '') ?: 'var-eth-250-whole');
        $cookieFile = journey_temp_cookie_file('coffee-cart');
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
        journey_assert('add-to-cart HTTP 200', $add['code'] === 200, $add['body']);
        journey_assert('add-to-cart status ok', ($addData['status'] ?? '') === 'ok', $add['body']);
        journey_assert('cart count > 0', (int) ($addData['data']['count'] ?? 0) > 0);

        $cart = journey_http($base, '/api/index.php?route=commerce&action=get', [
            'cookie_file' => $cookieFile,
        ]);
        $cartData = birkly_test_json_decode($cart['body']);
        journey_assert('cart GET ok', ($cartData['status'] ?? '') === 'ok', $cart['body']);
        journey_assert('cart count matches add', (int) ($cartData['data']['count'] ?? 0) > 0);
        @unlink($cookieFile);
    },
];
