<?php
/**
 * Shopping Cart plugin — seed shop_products collection on activation.
 */

function shopping_cart_plugin_dir(): string
{
    return __DIR__;
}

function shopping_cart_plugin_active(): bool
{
    if (!function_exists('birkly_is_plugin_active')) {
        require_once dirname(__DIR__, 2) . '/core/plugins.php';
    }
    return birkly_is_plugin_active('shopping_cart');
}

function shopping_cart_seed_collection(): void
{
    $source = shopping_cart_plugin_dir() . '/collections/shop_products';
    $target = dirname(__DIR__, 2) . '/content/collections/shop_products';
    if (!is_dir($source)) {
        return;
    }
    if (is_dir($target)) {
        return;
    }
    shopping_cart_copy_dir($source, $target);
}

function shopping_cart_copy_dir(string $src, string $dst): void
{
    if (!is_dir($dst)) {
        mkdir($dst, 0755, true);
    }
    foreach (scandir($src) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $from = $src . '/' . $entry;
        $to = $dst . '/' . $entry;
        if (is_dir($from)) {
            shopping_cart_copy_dir($from, $to);
        } else {
            copy($from, $to);
        }
    }
}

function shopping_cart_require_admin(): void
{
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    $role = $_SESSION['user']['role'] ?? '';
    if (!in_array($role, ['admin', 'superadmin'], true)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
        exit;
    }
}

function shopping_cart_on_payment_succeeded(array $data): void
{
    $paymentId = $data['payment_id'] ?? '';
    if ($paymentId === '') {
        return;
    }
    require_once __DIR__ . '/includes/CheckoutService.php';
    $checkout = new ShoppingCartCheckoutService();
    $checkout->markPaidByPaymentId($paymentId);
}

function shopping_cart_on_payment_failed(array $data): void
{
    $paymentId = $data['payment_id'] ?? '';
    if ($paymentId === '') {
        return;
    }
    require_once __DIR__ . '/includes/CheckoutService.php';
    $checkout = new ShoppingCartCheckoutService();
    $checkout->markFailedByPaymentId($paymentId);
}

function shopping_cart_init_automation_triggers(): void
{
    if (!function_exists('register_automation_block')) {
        return;
    }
    $blocks = [
        'order_created' => ['name' => 'Order Created', 'description' => 'Fires when a cart order is created'],
        'order_paid' => ['name' => 'Order Paid', 'description' => 'Fires when an order is marked paid'],
        'order_cancelled' => ['name' => 'Order Cancelled', 'description' => 'Fires when an order is cancelled'],
    ];
    foreach ($blocks as $id => $meta) {
        register_automation_block('trigger', $id, [
            'name' => $meta['name'],
            'description' => $meta['description'],
            'category' => 'Shopping Cart',
            'schema' => [],
            'handler' => function () {
                return true;
            },
        ]);
    }
}

function shopping_cart_register_payment_handlers(): void
{
    require_once __DIR__ . '/includes/ConsumerPaymentHelper.php';
    ShoppingCartConsumerPaymentHelper::registerPaymentEventHandlers(
        function (array $data) {
            shopping_cart_on_payment_succeeded($data);
        },
        function (array $data) {
            shopping_cart_on_payment_failed($data);
        }
    );
}

function shopping_cart_bootstrap(): void
{
    require_once __DIR__ . '/includes/ConsumerPaymentHelper.php';
    require_once __DIR__ . '/includes/OrderStore.php';
    require_once __DIR__ . '/includes/CartSession.php';
    require_once __DIR__ . '/includes/ProductCatalog.php';
    require_once __DIR__ . '/includes/CheckoutService.php';
    shopping_cart_register_payment_handlers();
}
