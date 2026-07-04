<?php
/**
 * Shopping Cart public + admin API.
 */

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../../api/session.php';
haru_secure_session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../init.php';

if (!shopping_cart_plugin_active()) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Shopping Cart plugin is not active']);
    exit;
}

shopping_cart_bootstrap();

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? [];
if ($action === '' && isset($input['action'])) {
    $action = $input['action'];
}

$cart = new ShoppingCartSession();
$catalog = new ShoppingCartProductCatalog();
$orders = new ShoppingCartOrderStore();
$checkout = new ShoppingCartCheckoutService($orders, $catalog, $cart);

$publicActions = ['get', 'add', 'update', 'remove', 'checkout'];
$adminActions = ['list_orders', 'get_order', 'cancel_order', 'list_products'];

if (in_array($action, $adminActions, true)) {
    shopping_cart_require_admin();
}

switch ($action) {
    case 'get':
        $built = $catalog->buildCartLines($cart->getItems());
        echo json_encode([
            'status' => 'ok',
            'data' => [
                'items' => $built['lines'],
                'count' => $cart->count(),
                'total_cents' => $built['total_cents'],
                'currency' => $built['currency'],
            ],
        ]);
        break;

    case 'add':
        $productId = $input['product_id'] ?? $_GET['product_id'] ?? '';
        $qty = (int) ($input['quantity'] ?? $_GET['quantity'] ?? 1);
        if ($productId === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'product_id required']);
            break;
        }
        if (!$catalog->getProduct($productId)) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Product not found']);
            break;
        }
        $cart->addItem($productId, $qty);
        $built = $catalog->buildCartLines($cart->getItems());
        echo json_encode(['status' => 'ok', 'data' => ['count' => $cart->count(), 'total_cents' => $built['total_cents']]]);
        break;

    case 'update':
        $productId = $input['product_id'] ?? '';
        $qty = (int) ($input['quantity'] ?? 0);
        if ($productId === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'product_id required']);
            break;
        }
        $cart->updateItem($productId, $qty);
        echo json_encode(['status' => 'ok', 'data' => ['count' => $cart->count()]]);
        break;

    case 'remove':
        $productId = $input['product_id'] ?? $_GET['product_id'] ?? '';
        if ($productId === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'product_id required']);
            break;
        }
        $cart->removeItem($productId);
        echo json_encode(['status' => 'ok', 'data' => ['count' => $cart->count()]]);
        break;

    case 'checkout':
        $email = $input['customer_email'] ?? '';
        $base = function_exists('birkly_base_url') ? rtrim(birkly_base_url(), '/') : '';
        $successPath = get_plugin_setting('shopping_cart', 'checkout_success_path', '/shop/thank-you') ?? '/shop/thank-you';
        $cancelPath = get_plugin_setting('shopping_cart', 'checkout_cancel_path', '/shop/cart') ?? '/shop/cart';
        $returnUrl = $input['return_url'] ?? ($base !== '' ? $base . $successPath : $successPath);
        $cancelUrl = $input['cancel_url'] ?? ($base !== '' ? $base . $cancelPath : $cancelPath);
        $result = $checkout->checkout($email, $returnUrl, $cancelUrl);
        if (!($result['ok'] ?? false)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => $result['error'] ?? 'Checkout failed', 'data' => $result]);
            break;
        }
        echo json_encode(['status' => 'ok', 'data' => $result]);
        break;

    case 'list_orders':
        echo json_encode(['status' => 'ok', 'data' => ['orders' => $orders->listAll()]]);
        break;

    case 'get_order':
        $id = $input['id'] ?? $_GET['id'] ?? '';
        $order = $orders->get($id);
        if (!$order) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Order not found']);
            break;
        }
        echo json_encode(['status' => 'ok', 'data' => ['order' => $order]]);
        break;

    case 'cancel_order':
        require_once __DIR__ . '/../../../core/csrf.php';
        $csrf = $input['_csrf'] ?? '';
        if (!validate_csrf_token($csrf)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
            break;
        }
        $id = $input['id'] ?? '';
        $order = $orders->update($id, ['status' => 'cancelled']);
        if (!$order) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Order not found']);
            break;
        }
        if (function_exists('broadcast_event')) {
            broadcast_event('order_cancelled', ['order_id' => $id], 'shopping_cart');
        }
        echo json_encode(['status' => 'ok', 'data' => ['order' => $order]]);
        break;

    case 'list_products':
        $products = array_map(function ($entry) use ($catalog) {
            return $catalog->normalizeProduct($entry);
        }, $catalog->listPublished());
        echo json_encode(['status' => 'ok', 'data' => ['products' => $products]]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
}
