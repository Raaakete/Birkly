<?php
/**
 * Payments Plugin — connection hub bootstrap.
 */

require_once __DIR__ . '/init.php';

$shelf = payments_bootstrap();

if ($shelf && function_exists('register_plugin_service')) {
    register_plugin_service('payment_shelf', $shelf, [
        'version' => '1.0.0',
        'description' => 'Payment processing shelf for consumer plugins',
        'capability' => 'access_payment_shelf',
        'scope' => 'global',
    ]);
}

if (function_exists('register_api_endpoint')) {
    register_api_endpoint('payments', __DIR__ . '/api/payments.php');
    register_api_endpoint('payments_webhook', __DIR__ . '/api/webhook.php');
}

if (function_exists('register_admin_page')) {
    register_admin_page('payments', __DIR__ . '/admin/payments.html');
}

if (function_exists('register_hook')) {
    register_hook('plugin_activated', function ($plugin_id) {
        if ($plugin_id === 'payments') {
            payments_bootstrap();
        }
    });

    register_hook('automation_blocks_register', function () {
        if (payments_plugin_active()) {
            payments_init_automation_triggers();
        }
    });
}

if (function_exists('automation_bootstrap') && automation_bootstrap()) {
    payments_init_automation_triggers();
} elseif (function_exists('register_automation_block')) {
    payments_init_automation_triggers();
}
