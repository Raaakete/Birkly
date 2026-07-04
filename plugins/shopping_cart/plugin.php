<?php
/**
 * Shopping Cart plugin bootstrap — payment_shelf consumer.
 */

require_once __DIR__ . '/init.php';

if (shopping_cart_plugin_active()) {
    shopping_cart_bootstrap();
}

if (function_exists('register_api_endpoint')) {
    register_api_endpoint('shopping_cart', __DIR__ . '/api/cart.php');
}

if (function_exists('register_admin_page')) {
    register_admin_page('shopping_cart', __DIR__ . '/admin/cart.html');
}

if (function_exists('register_hook')) {
    register_hook('plugin_activated', function ($plugin_id) {
        if ($plugin_id !== 'shopping_cart') {
            return;
        }
        shopping_cart_seed_collection();
        if (function_exists('grant_plugin_capability')) {
            grant_plugin_capability('shopping_cart', 'access_payment_shelf', 'global');
        }
    });

    register_hook('automation_blocks_register', function () {
        if (shopping_cart_plugin_active()) {
            shopping_cart_init_automation_triggers();
        }
    });
}

if (function_exists('automation_bootstrap') && automation_bootstrap()) {
    shopping_cart_init_automation_triggers();
} elseif (function_exists('register_automation_block') && shopping_cart_plugin_active()) {
    shopping_cart_init_automation_triggers();
}

if (function_exists('register_template_function') && shopping_cart_plugin_active()) {
    register_template_function('cart_count', function () {
        shopping_cart_bootstrap();
        $cart = new ShoppingCartSession();
        return (string) $cart->count();
    });

    register_template_function('cart_checkout_url', function () {
        return function_exists('birkly_url')
            ? birkly_url('/api/index.php?route=shopping_cart&action=checkout')
            : '/api/index.php?route=shopping_cart&action=checkout';
    });

    register_template_function('cart_add_url', function ($productId = '') {
        $base = function_exists('birkly_url')
            ? birkly_url('/api/index.php?route=shopping_cart&action=add')
            : '/api/index.php?route=shopping_cart&action=add';
        return $productId !== '' ? $base . '&product_id=' . urlencode($productId) : $base;
    });
}
