<?php
/**
 * Checkout flow — creates pending order and charges via commerce_shelf.
 */

class CommerceCheckoutService
{
    private CommerceOrderStore $orders;
    private CommerceCatalog $catalog;
    private CommerceCartSession $cart;
    private CatalogIndexer $indexer;
    private DiscountEngine $discounts;
    private PurchaseMappingService $mappings;

    public function __construct(
        ?CommerceOrderStore $orders = null,
        ?CommerceCatalog $catalog = null,
        ?CommerceCartSession $cart = null,
        ?CatalogIndexer $indexer = null,
        ?DiscountEngine $discounts = null,
        ?PurchaseMappingService $mappings = null
    ) {
        $this->orders = $orders ?? new CommerceOrderStore();
        $this->indexer = $indexer ?? new CatalogIndexer();
        $this->catalog = $catalog ?? new CommerceCatalog($this->indexer);
        $this->cart = $cart ?? new CommerceCartSession();
        $this->discounts = $discounts ?? new DiscountEngine();
        $this->mappings = $mappings ?? new PurchaseMappingService();
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

    public function createPendingOrder(string $customerEmail = ''): array
    {
        $coupon = $this->cart->getCouponCode();
        $affiliate = $this->cart->getAffiliateId();
        $built = $this->catalog->buildCartLines($this->cart->getItems(), $this->discounts, $coupon, $affiliate);
        if (empty($built['lines'])) {
            return ['ok' => false, 'error' => 'Cart is empty'];
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

        $order = $this->orders->create([
            'status' => 'pending',
            'lines' => $built['lines'],
            'total_cents' => $built['total_cents'],
            'discount_cents' => $built['discount_cents'] ?? 0,
            'currency' => $built['currency'],
            'customer_email' => $customerEmail,
            'coupon_code' => $coupon,
            'affiliate_id' => $affiliate,
        ]);

        $this->fireOrderCreated($order);
        return ['ok' => true, 'order' => $order];
    }

    public function checkout(string $customerEmail = '', string $returnUrl = '', string $cancelUrl = ''): array
    {
        $shelf = $this->getShelf();
        if (!$shelf || !$shelf->isConfigured()) {
            return ['ok' => false, 'error' => 'Payments not configured'];
        }

        $pending = $this->createPendingOrder($customerEmail);
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
            ],
        ];
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
        $this->mappings->applyPurchaseMappings($updated);
        $this->pushFulfillment($updated);

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

    private function pushFulfillment(array $order): void
    {
        $connectionsFile = commerce_data_dir() . '/fulfillment_connections.json';
        if (!is_file($connectionsFile)) {
            return;
        }
        $connections = json_decode((string) file_get_contents($connectionsFile), true);
        $adapterId = $connections['active_adapter'] ?? 'manual';
        if ($adapterId === 'shipstation') {
            $adapter = new ShipStationStubAdapter();
        } else {
            $adapter = new ManualFulfillmentAdapter();
        }
        if ($adapter->isConfigured()) {
            $adapter->pushOrder($order);
        }
    }
}
