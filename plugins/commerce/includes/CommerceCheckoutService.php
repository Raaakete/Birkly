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
    private ?CommerceSubscriptionService $subscriptionService = null;

    public function __construct(
        ?CommerceOrderStore $orders = null,
        ?CommerceCatalog $catalog = null,
        ?CommerceCartSession $cart = null,
        ?CatalogIndexer $indexer = null,
        ?DiscountEngine $discounts = null,
        ?PurchaseMappingService $mappings = null,
        ?PostPurchaseRouter $router = null,
        ?CommerceSubscriptionService $subscriptionService = null
    ) {
        $this->orders = $orders ?? new CommerceOrderStore();
        $this->indexer = $indexer ?? new CatalogIndexer();
        $this->catalog = $catalog ?? new CommerceCatalog($this->indexer);
        $this->cart = $cart ?? new CommerceCartSession();
        $this->discounts = $discounts ?? new DiscountEngine();
        $this->mappings = $mappings ?? new PurchaseMappingService();
        $this->router = $router ?? new PostPurchaseRouter();
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Lazily build the subscription service, colocating its store next to the
     * order store so custom (test) data directories are respected.
     */
    private function subscriptions(): CommerceSubscriptionService
    {
        if ($this->subscriptionService === null) {
            if (!class_exists('CommerceSubscriptionStore')) {
                require_once __DIR__ . '/CommerceSubscriptionStore.php';
            }
            if (!class_exists('CommerceSubscriptionService')) {
                require_once __DIR__ . '/CommerceSubscriptionService.php';
            }
            $subDir = dirname($this->orders->getDataDir()) . '/subscriptions';
            $this->subscriptionService = new CommerceSubscriptionService(
                new CommerceSubscriptionStore($subDir),
                $this->getShelf()
            );
        }
        return $this->subscriptionService;
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
            if (!empty($line['is_bundle'])) {
                foreach ($line['components'] ?? [] as $comp) {
                    if (($comp['fulfillment'] ?? '') === 'ship') {
                        $needsShipping = true;
                    }
                    if (($comp['fulfillment'] ?? '') === 'register') {
                        $needsAttendee = true;
                    }
                }
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

        $tax = $this->catalog->calculateTax($built['lines'], is_array($shippingAddress) ? $shippingAddress : null);
        $built['tax_cents'] = $tax['tax_cents'];
        $built['tax_rate'] = $tax['rate'];
        $built['tax_country'] = $tax['country'];
        $built['total_cents'] += $tax['tax_cents'];

        foreach ($built['lines'] as $line) {
            if (!$this->reserveLineStock($line)) {
                return ['ok' => false, 'error' => 'Insufficient stock for ' . ($line['title'] ?? 'item')];
            }
        }

        $orderPayload = [
            'status' => 'pending',
            'lines' => $built['lines'],
            'total_cents' => $built['total_cents'],
            'shipping_cents' => $built['shipping_cents'] ?? 0,
            'tax_cents' => $built['tax_cents'] ?? 0,
            'tax_rate' => $built['tax_rate'] ?? 0.0,
            'tax_country' => $built['tax_country'] ?? '',
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
            $this->decrementLineStock($line);
        }

        $updated = $this->orders->update($order['id'], ['status' => 'paid']);
        $this->cart->clear();

        $this->fireOrderPaid($updated);
        $fulfillmentResults = $this->router->route($updated);
        $this->mappings->applyPurchaseMappings($updated);

        if (!empty($fulfillmentResults)) {
            $updated = $this->orders->update($order['id'], ['fulfillment_results' => $fulfillmentResults]);
        }

        if (($updated['billing_mode'] ?? 'one_time') === 'recurring') {
            $subscription = $this->subscriptions()->createFromOrder($updated);
            if ($subscription) {
                $updated = $this->orders->update($order['id'], ['subscription_id' => $subscription['id']]);
            }
        }

        return $updated;
    }

    /**
     * Refund an order (full or partial) via the commerce shelf.
     *
     * @param int|null $amountCents Null = refund the full remaining balance.
     * @return array{ok: bool, error?: string, order?: array, refund?: array}
     */
    public function refundOrder(string $orderId, ?int $amountCents = null, string $reason = ''): array
    {
        $order = $this->orders->get($orderId);
        if (!$order) {
            return ['ok' => false, 'error' => 'Order not found'];
        }

        $status = $order['status'] ?? '';
        if (!in_array($status, ['paid', 'partially_refunded'], true)) {
            return ['ok' => false, 'error' => 'Order is not refundable (status: ' . ($status ?: 'unknown') . ')'];
        }

        $paymentId = (string) ($order['payment_id'] ?? '');
        $provider = (string) ($order['provider'] ?? '');
        if ($paymentId === '' || $provider === '') {
            return ['ok' => false, 'error' => 'Order has no captured payment to refund'];
        }

        $total = (int) ($order['total_cents'] ?? 0);
        $alreadyRefunded = (int) ($order['refunded_cents'] ?? 0);
        $remaining = max(0, $total - $alreadyRefunded);
        if ($remaining <= 0) {
            return ['ok' => false, 'error' => 'Order is already fully refunded'];
        }

        $refundAmount = $amountCents === null ? $remaining : (int) $amountCents;
        if ($refundAmount <= 0) {
            return ['ok' => false, 'error' => 'Refund amount must be greater than zero'];
        }
        if ($refundAmount > $remaining) {
            return ['ok' => false, 'error' => 'Refund amount exceeds remaining balance'];
        }

        $shelf = $this->getShelf();
        if (!$shelf) {
            return ['ok' => false, 'error' => 'Payments not configured'];
        }

        $isFull = $refundAmount >= $remaining;
        $result = $shelf->refundPayment($provider, $paymentId, $isFull ? null : $refundAmount, [
            'source_plugin' => 'commerce',
            'source_ref' => $orderId,
        ]);

        if (!($result['ok'] ?? false)) {
            return ['ok' => false, 'error' => $result['error'] ?? 'Refund failed'];
        }

        $refundRecord = [
            'amount_cents' => $refundAmount,
            'reason' => trim($reason),
            'provider' => $provider,
            'refund_id' => $result['refund_id'] ?? ($result['payment_id'] ?? null),
            'created_at' => date('c'),
        ];

        $refunds = $order['refunds'] ?? [];
        $refunds[] = $refundRecord;
        $newRefunded = $alreadyRefunded + $refundAmount;
        $newStatus = $newRefunded >= $total ? 'refunded' : 'partially_refunded';

        $updated = $this->orders->update($orderId, [
            'refunds' => $refunds,
            'refunded_cents' => $newRefunded,
            'status' => $newStatus,
        ]);

        $this->fireOrderRefunded($updated ?? $order, $refundRecord);

        return ['ok' => true, 'order' => $updated, 'refund' => $refundRecord];
    }

    private function fireOrderRefunded(array $order, array $refund): void
    {
        $ctx = array_merge($this->buildOrderPayload($order), [
            'refund_amount_cents' => $refund['amount_cents'] ?? 0,
            'refund_reason' => $refund['reason'] ?? '',
            'refunded_cents' => (int) ($order['refunded_cents'] ?? 0),
        ]);
        if (function_exists('broadcast_event')) {
            broadcast_event('commerce_order_refunded', $ctx, 'commerce');
        }
        if (function_exists('enqueue_automation')) {
            enqueue_automation('commerce_order_refunded', $ctx);
        }
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
        $payload = $this->buildOrderPayload($order);

        if (function_exists('broadcast_event')) {
            broadcast_event('commerce_order_paid', $payload, 'commerce');
        }
        if (function_exists('enqueue_automation')) {
            // Core paid trigger plus dedicated order-email triggers (P8-D5).
            enqueue_automation('commerce_order_paid', $payload);
            enqueue_automation('commerce_order_receipt', $payload);
            enqueue_automation('commerce_order_admin_notify', $payload);
        }
    }

    /**
     * Build the automation/event payload for an order — includes line items,
     * totals, tax, and customer email so email triggers can render receipts.
     */
    private function buildOrderPayload(array $order): array
    {
        $lines = [];
        foreach ($order['lines'] ?? [] as $line) {
            $lines[] = [
                'title' => $line['title'] ?? ($line['entry_id'] ?? ''),
                'quantity' => (int) ($line['quantity'] ?? 1),
                'unit_price_cents' => (int) ($line['unit_price_cents'] ?? $line['effective_unit_cents'] ?? 0),
                'line_total_cents' => (int) ($line['line_total_cents'] ?? 0),
                'fulfillment' => $line['fulfillment'] ?? '',
            ];
        }

        return [
            'order_id' => $order['id'] ?? '',
            'payment_id' => $order['payment_id'] ?? null,
            'provider' => $order['provider'] ?? null,
            'status' => $order['status'] ?? 'paid',
            'customer_email' => $order['customer_email'] ?? '',
            'currency' => $order['currency'] ?? 'eur',
            'subtotal_cents' => max(0, (int) ($order['total_cents'] ?? 0)
                - (int) ($order['tax_cents'] ?? 0) - (int) ($order['shipping_cents'] ?? 0)),
            'shipping_cents' => (int) ($order['shipping_cents'] ?? 0),
            'tax_cents' => (int) ($order['tax_cents'] ?? 0),
            'tax_rate' => (float) ($order['tax_rate'] ?? 0.0),
            'discount_cents' => (int) ($order['discount_cents'] ?? 0),
            'total_cents' => (int) ($order['total_cents'] ?? 0),
            'shipping_address' => $order['shipping_address'] ?? null,
            'lines' => $lines,
        ];
    }

    private function reserveLineStock(array $line): bool
    {
        if (!$this->indexer->reserveQuantity(
            $line['collection_slug'] ?? '',
            $line['entry_id'] ?? '',
            (int) ($line['quantity'] ?? 1)
        )) {
            return false;
        }

        if (!empty($line['is_bundle'])) {
            foreach ($line['components'] ?? [] as $comp) {
                if (!$this->indexer->reserveQuantity(
                    $comp['collection'] ?? '',
                    $comp['entry_id'] ?? '',
                    (int) ($comp['quantity'] ?? 1)
                )) {
                    return false;
                }
            }
        }

        return true;
    }

    private function decrementLineStock(array $line): void
    {
        $this->indexer->decrementQuantity(
            $line['collection_slug'] ?? '',
            $line['entry_id'] ?? '',
            (int) ($line['quantity'] ?? 1)
        );

        if (!empty($line['is_bundle'])) {
            foreach ($line['components'] ?? [] as $comp) {
                $this->indexer->decrementQuantity(
                    $comp['collection'] ?? '',
                    $comp['entry_id'] ?? '',
                    (int) ($comp['quantity'] ?? 1)
                );
            }
        }
    }
}
