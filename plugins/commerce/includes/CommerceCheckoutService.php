<?php
/**
 * Checkout flow — creates pending order and charges via commerce_shelf (v2).
 */

class CommerceCheckoutService
{
    private CommerceOrderStore $orders;
    private CommerceCatalog $catalog;
    private CommerceCartSession $cart;
    private CatalogIndexer $indexer;
    private DiscountEngine $discounts;
    private PurchaseMappingService $mappings;
    private PostPurchaseRouter $router;

    public function __construct(
        ?CommerceOrderStore $orders = null,
        ?CommerceCatalog $catalog = null,
        ?CommerceCartSession $cart = null,
        ?CatalogIndexer $indexer = null,
        ?DiscountEngine $discounts = null,
        ?PurchaseMappingService $mappings = null,
        ?PostPurchaseRouter $router = null
    ) {
        $this->orders = $orders ?? new CommerceOrderStore();
        $this->indexer = $indexer ?? new CatalogIndexer();
        $this->catalog = $catalog ?? new CommerceCatalog($this->indexer);
        $this->cart = $cart ?? new CommerceCartSession();
        $this->discounts = $discounts ?? new DiscountEngine();
        $this->mappings = $mappings ?? new PurchaseMappingService();
        $this->router = $router ?? new PostPurchaseRouter();
    }

    public function getShelf(): ?PaymentShelf
    {
        if (function_exists('get_plugin_service')) {
            $shelf = get_plugin_service('commerce_shelf', false);
            if ($shelf instanceof PaymentShelf) {
                return $shelf;
            }
            $shelf = get_plugin_service('payment_shelf', false);
            if ($shelf instanceof PaymentShelf) {
                return $shelf;
            }
        }
        if (function_exists('commerce_bootstrap')) {
            return commerce_bootstrap();
        }
        return null;
    }

    public function createPendingOrder(string $customerEmail = '', array $checkoutData = []): array
    {
        $coupon = $this->cart->getCouponCode();
        $affiliate = $this->cart->getAffiliateId();
        $shippingAddress = $checkoutData['shipping_address'] ?? null;
        $attendeeName = $checkoutData['attendee_name'] ?? null;

        $built = $this->catalog->buildCartLines($this->cart->getItems(), $this->discounts, $coupon, $affiliate);
        if (($built['ok'] ?? true) === false) {
            return ['ok' => false, 'error' => $built['error'] ?? 'Cart error'];
        }
        if (empty($built['lines'])) {
            return ['ok' => false, 'error' => 'Cart is empty'];
        }

        $needsShipping = false;
        $needsAttendee = false;
        foreach ($built['lines'] as $line) {
            if (($line['fulfillment'] ?? '') === 'ship') {
                $needsShipping = true;
            }
            if (($line['fulfillment'] ?? '') === 'register') {
                $needsAttendee = true;
            }
        }

        if ($needsShipping && empty($shippingAddress)) {
            return ['ok' => false, 'error' => 'Shipping address required'];
        }
        if ($needsAttendee && ($attendeeName === null || trim((string) $attendeeName) === '')) {
            return ['ok' => false, 'error' => 'Attendee name required'];
        }

        if ($needsShipping && is_array($shippingAddress)) {
            $oldShipping = $built['shipping_cents'] ?? 0;
            $newShipping = $this->catalog->calculateShipping($built['lines'], $shippingAddress);
            $built['shipping_cents'] = $newShipping;
            $built['total_cents'] = $built['total_cents'] - $oldShipping + $newShipping;
        }

        foreach ($built['lines'] as $line) {
            if (!$this->indexer->reserveQuantity(
                $line['collection_slug'],
                $line['entry_id'],
                (int) $line['quantity']
            )) {
                return ['ok' => false, 'error' => 'Insufficient stock for ' . ($line['title'] ?? 'item')];
            }
        }

        $orderPayload = [
            'status' => 'pending',
            'lines' => $built['lines'],
            'total_cents' => $built['total_cents'],
            'shipping_cents' => $built['shipping_cents'] ?? 0,
            'discount_cents' => $built['discount_cents'] ?? 0,
            'currency' => $built['currency'],
            'customer_email' => $customerEmail,
            'coupon_code' => $coupon,
            'affiliate_id' => $affiliate,
        ];
        if (is_array($shippingAddress)) {
            $orderPayload['shipping_address'] = $shippingAddress;
        }
        if ($attendeeName !== null && trim((string) $attendeeName) !== '') {
            $orderPayload['attendee_name'] = trim((string) $attendeeName);
        }

        $billingMode = $built['lines'][0]['billing'] ?? 'one_time';
        $orderPayload['billing_mode'] = $billingMode;
        if ($billingMode === 'recurring') {
            $orderPayload['billing_interval'] = $built['lines'][0]['interval'] ?? 'month';
        }

        $order = $this->orders->create($orderPayload);
        $this->fireOrderCreated($order);
        return ['ok' => true, 'order' => $order];
    }

    public function checkout(string $customerEmail = '', string $returnUrl = '', string $cancelUrl = '', array $checkoutData = []): array
    {
        $shelf = $this->getShelf();
        if (!$shelf || !$shelf->isConfigured()) {
            return ['ok' => false, 'error' => 'Payments not configured'];
        }

        $pending = $this->createPendingOrder($customerEmail, $checkoutData);
        if (!($pending['ok'] ?? false)) {
            return $pending;
        }
        $order = $pending['order'];

        $params = [
            'amount_cents' => (int) $order['total_cents'],
            'currency' => $order['currency'],
            'description' => 'Order ' . $order['id'],
            'metadata' => [
                'order_id' => $order['id'],
                'line_count' => count($order['lines']),
                'billing_mode' => $order['billing_mode'] ?? 'one_time',
            ],
        ];
        if (($order['billing_mode'] ?? '') === 'recurring') {
            $params['billing'] = 'recurring';
            $params['interval'] = $order['billing_interval'] ?? 'month';
        }
        if ($returnUrl !== '') {
            $params['return_url'] = $returnUrl;
        }
        if ($cancelUrl !== '') {
            $params['cancel_url'] = $cancelUrl;
        }

        $result = $shelf->createPayment('auto', $params, [
            'source_plugin' => 'commerce',
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

        foreach ($order['lines'] ?? [] as $line) {
            $this->indexer->decrementQuantity(
                $line['collection_slug'] ?? '',
                $line['entry_id'] ?? '',
                (int) ($line['quantity'] ?? 1)
            );
        }

        $updated = $this->orders->update($order['id'], ['status' => 'paid']);
        $this->cart->clear();

        $this->fireOrderPaid($updated);
        $fulfillmentResults = $this->router->route($updated);
        $this->mappings->applyPurchaseMappings($updated);

        if (!empty($fulfillmentResults)) {
            $updated = $this->orders->update($order['id'], ['fulfillment_results' => $fulfillmentResults]);
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

    private function fireOrderCreated(array $order): void
    {
        $ctx = ['order_id' => $order['id'], 'total_cents' => $order['total_cents']];
        if (function_exists('broadcast_event')) {
            broadcast_event('commerce_order_created', $ctx, 'commerce');
        }
        if (function_exists('enqueue_automation')) {
            enqueue_automation('commerce_order_created', $ctx);
        }
    }

    private function fireOrderPaid(array $order): void
    {
        $ctx = [
            'order_id' => $order['id'],
            'payment_id' => $order['payment_id'] ?? null,
            'total_cents' => $order['total_cents'],
        ];
        if (function_exists('broadcast_event')) {
            broadcast_event('commerce_order_paid', $ctx, 'commerce');
        }
        if (function_exists('enqueue_automation')) {
            enqueue_automation('commerce_order_paid', $ctx);
        }
    }
}
