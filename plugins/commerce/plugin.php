<?php
/**
 * Commerce Plugin — payment providers, catalog, cart, checkout, orders.
 */

if (defined('BIRKLY_COMMERCE_BOOTSTRAP')) {
    return;
}
define('BIRKLY_COMMERCE_BOOTSTRAP', true);

require_once __DIR__ . '/init.php';

$shelf = commerce_bootstrap();

if ($shelf && function_exists('register_plugin_service')) {
    register_plugin_service('commerce_shelf', $shelf, [
        'version' => '1.0.0',
        'description' => 'Payment processing shelf for consumer plugins',
        'capability' => 'access_commerce_shelf',
        'scope' => 'global',
    ]);
    register_plugin_service('payment_shelf', $shelf, [
        'version' => '1.0.0',
        'description' => 'Legacy alias for commerce_shelf',
        'capability' => 'access_payment_shelf',
        'scope' => 'global',
    ]);
}

if (function_exists('register_api_endpoint')) {
    register_api_endpoint('commerce', __DIR__ . '/api/commerce.php');
    register_api_endpoint('payments', __DIR__ . '/api/payments.php');
    register_api_endpoint('commerce_webhook', __DIR__ . '/api/webhook.php');
    register_api_endpoint('payments_webhook', __DIR__ . '/api/webhook.php');
}

if (function_exists('register_admin_page')) {
    register_admin_page('commerce', __DIR__ . '/admin/commerce.html');
    register_admin_page('payments', __DIR__ . '/admin/commerce.html');
}

if (function_exists('register_plugin_fieldtype') && commerce_plugin_active()) {
    if (!class_exists('Birkly\FieldTypes\BaseField')) {
        require_once dirname(__DIR__, 2) . '/fieldtypes/BaseField.php';
    }
    require_once __DIR__ . '/fieldtypes/Commerce/Commerce.php';
    register_plugin_fieldtype('commerce', 'Birkly\\FieldTypes\\Commerce\\Commerce', commerce_active_plugin_id());
}

if (function_exists('register_hook')) {
    register_hook('plugin_activated', function ($plugin_id) {
        if ($plugin_id === 'commerce' || $plugin_id === 'payments') {
            commerce_migrate_legacy_data();
            commerce_bootstrap();
        }
    });

    register_hook('automation_blocks_register', function () {
        if (commerce_plugin_active()) {
            commerce_init_automation_triggers();
        }
    });

    register_hook('after_entry_save', function ($entry, $collection, $oldEntry = null) {
        if (!commerce_plugin_active()) {
            return;
        }
        commerce_bootstrap_cart_services();
        $indexer = new CatalogIndexer();
        $indexer->indexEntry($entry, is_string($collection) ? $collection : ($collection['slug'] ?? ''));
    });

    register_hook('after_entry_delete', function ($entry, $collection) {
        if (!commerce_plugin_active()) {
            return;
        }
        commerce_bootstrap_cart_services();
        $indexer = new CatalogIndexer();
        $entryId = is_array($entry) ? ($entry['id'] ?? '') : (string) $entry;
        $collectionSlug = is_string($collection) ? $collection : ($collection['slug'] ?? '');
        $indexer->removeEntry($entryId, $collectionSlug);
    });
}

if (function_exists('automation_bootstrap') && automation_bootstrap()) {
    commerce_init_automation_triggers();
} elseif (function_exists('register_automation_block')) {
    commerce_init_automation_triggers();
}

commerce_register_payment_handlers();

if (function_exists('register_template_function') && commerce_plugin_active()) {
    register_template_function('commerce_cart_count', function () {
        commerce_bootstrap_cart_services();
        $cart = new CommerceCartSession();
        return (string) $cart->count();
    });

    register_template_function('commerce_cart_url', function () {
        return commerce_cart_url();
    });

    register_template_function('commerce_buy_url', function ($entryId = '', $collectionSlug = '', $qty = 1) {
        return commerce_buy_url(
            (string) $entryId,
            (string) $collectionSlug,
            (int) $qty
        );
    });

    register_template_function('commerce_add_to_cart', function ($entryId = '', $collectionSlug = '', $qty = 1) {
        return commerce_buy_url(
            (string) $entryId,
            (string) $collectionSlug,
            (int) $qty
        );
    });
}

global $birkly_plugins;
if (isset($birkly_plugins['commerce'])) {
    $birkly_plugins['commerce']['dashboard_widgets_callback'] = 'commerce_dashboard_widgets';
}
if (isset($birkly_plugins['payments'])) {
    $birkly_plugins['payments']['dashboard_widgets_callback'] = 'payments_dashboard_widgets';
}
