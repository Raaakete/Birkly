<?php
/**
 * Checkout flow — creates pending order and charges via payment_shelf.
 */

class ShoppingCartCheckoutService
{
    private ShoppingCartOrderStore $orders;
    private ShoppingCartProductCatalog $catalog;
    private ShoppingCartSession $cart;

    public function __construct(
        ?ShoppingCartOrderStore $orders = null,
        ?ShoppingCartProductCatalog $catalog = null,
        ?ShoppingCartSession $cart = null
    ) {
        $this->orders = $orders ?? new ShoppingCartOrderStore();
        $this->catalog = $catalog ?? new ShoppingCartProductCatalog();
        $this->cart = $cart ?? new ShoppingCartSession();
    }

    public function createPendingOrder(string $customerEmail = ''): array
    {
        $built = $this->catalog->buildCartLines($this->cart->getItems());
        if (empty($built['lines'])) {
            return ['ok' => false, 'error' => 'Cart is empty'];
        }
        $order = $this->orders->create([
            'status' => 'pending',
            'lines' => $built['lines'],
            'total_cents' => $built['total_cents'],
            'currency' => $built['currency'],
            'customer_email' => $customerEmail,
        ]);
        if (function_exists('broadcast_event')) {
            broadcast_event('order_created', ['order_id' => $order['id'], 'total_cents' => $order['total_cents']], 'shopping_cart');
        }
        if (function_exists('enqueue_automation')) {
            enqueue_automation('order_created', ['order_id' => $order['id'], 'total_cents' => $order['total_cents']]);
        }
        return ['ok' => true, 'order' => $order];
    }

    public function checkout(string $customerEmail = '', string $returnUrl = '', string $cancelUrl = ''): array
    {
        if (!ShoppingCartConsumerPaymentHelper::isPaymentsAvailable()) {
            return ['ok' => false, 'error' => 'Payments not configured'];
        }
        $pending = $this->createPendingOrder($customerEmail);
        if (!($pending['ok'] ?? false)) {
            return $pending;
        }
        $order = $pending['order'];
        $shelf = ShoppingCartConsumerPaymentHelper::getShelf();
        if (!$shelf) {
            return ['ok' => false, 'error' => 'Payment shelf unavailable'];
        }

        $params = [
            'amount_cents' => (int) $order['total_cents'],
            'currency' => $order['currency'],
            'description' => 'Order ' . $order['id'],
            'metadata' => [
                'order_id' => $order['id'],
                'line_count' => count($order['lines']),
            ],
        ];
        if ($returnUrl !== '') {
            $params['return_url'] = $returnUrl;
        }
        if ($cancelUrl !== '') {
            $params['cancel_url'] = $cancelUrl;
        }

        $result = $shelf->createPayment('auto', $params, [
            'source_plugin' => 'shopping_cart',
            'source_ref' => $order['id'],
        ]);

        if (!($result['ok'] ?? false)) {
            $this->orders->update($order['id'], ['status' => 'failed', 'error' => $result['error'] ?? 'Payment failed']);
            return ['ok' => false, 'error' => $result['error'] ?? 'Payment failed', 'order_id' => $order['id']];
        }

        $this->orders->update($order['id'], [
            'payment_id' => $result['payment_id'] ?? null,
            'provider' => $result['provider'] ?? null,
            'status' => 'pending',
        ]);

        return [
            'ok' => true,
            'order_id' => $order['id'],
            'payment_id' => $result['payment_id'] ?? null,
            'redirect_url' => $result['redirect_url'] ?? null,
            'client_secret' => $result['client_secret'] ?? null,
            'provider' => $result['provider'] ?? null,
        ];
    }

    public function markPaidByPaymentId(string $paymentId): ?array
    {
        $order = $this->orders->findByPaymentId($paymentId);
        if (!$order || ($order['status'] ?? '') === 'paid') {
            return $order;
        }
        $updated = $this->orders->update($order['id'], ['status' => 'paid']);
        $this->cart->clear();
        if (function_exists('broadcast_event')) {
            broadcast_event('order_paid', ['order_id' => $order['id'], 'payment_id' => $paymentId], 'shopping_cart');
        }
        if (function_exists('enqueue_automation')) {
            enqueue_automation('order_paid', ['order_id' => $order['id'], 'payment_id' => $paymentId]);
        }
        return $updated;
    }

    public function markFailedByPaymentId(string $paymentId): ?array
    {
        $order = $this->orders->findByPaymentId($paymentId);
        if (!$order) {
            return null;
        }
        return $this->orders->update($order['id'], ['status' => 'failed']);
    }
}
