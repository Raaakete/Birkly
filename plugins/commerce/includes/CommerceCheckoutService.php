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
    private ?GiftCardStore $giftCards = null;
    private ?CommerceQuoteStore $quotes = null;
    private ?WarehouseStockStore $warehouse = null;

    public function __construct(
        ?CommerceOrderStore $orders = null,
        ?CommerceCatalog $catalog = null,
        ?CommerceCartSession $cart = null,
        ?CatalogIndexer $indexer = null,
        ?DiscountEngine $discounts = null,
        ?PurchaseMappingService $mappings = null,
        ?PostPurchaseRouter $router = null,
        ?CommerceSubscriptionService $subscriptionService = null,
        ?GiftCardStore $giftCards = null,
        ?CommerceQuoteStore $quotes = null,
        ?WarehouseStockStore $warehouse = null
    ) {
        $this->orders = $orders ?? new CommerceOrderStore();
        $this->indexer = $indexer ?? new CatalogIndexer();
        $this->catalog = $catalog ?? new CommerceCatalog($this->indexer);
        $this->cart = $cart ?? new CommerceCartSession();
        $this->discounts = $discounts ?? new DiscountEngine();
        $this->mappings = $mappings ?? new PurchaseMappingService();
        $this->router = $router ?? new PostPurchaseRouter();
        $this->subscriptionService = $subscriptionService;
        $this->giftCards = $giftCards;
        $this->quotes = $quotes;
        $this->warehouse = $warehouse;
    }

    private function warehouseStore(): WarehouseStockStore
    {
        if ($this->warehouse === null) {
            if (!class_exists('WarehouseStockStore')) {
                require_once __DIR__ . '/WarehouseStockStore.php';
            }
            $this->warehouse = new WarehouseStockStore(dirname($this->orders->getDataDir()));
        }
        return $this->warehouse;
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


    private function giftCardStore(): GiftCardStore {
        if ($this->giftCards === null) { if (!class_exists('GiftCardStore')) require_once __DIR__ . '/GiftCardStore.php';
            $this->giftCards = new GiftCardStore(dirname($this->orders->getDataDir()) . '/gift_cards'); }
        return $this->giftCards;
    }
    private function quoteStore(): CommerceQuoteStore {
        if ($this->quotes === null) { if (!class_exists('CommerceQuoteStore')) require_once __DIR__ . '/CommerceQuoteStore.php';
            $this->quotes = new CommerceQuoteStore(dirname($this->orders->getDataDir()) . '/quotes'); }
        return $this->quotes;
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

        $giftCardCode = $this->cart->getGiftCardCode();
        $giftTender = $this->discounts->applyGiftCardTender((int) $built['total_cents'], $giftCardCode, $this->giftCardStore(), $built['currency'] ?? null);
        if (!($giftTender['ok'] ?? false)) return ['ok' => false, 'error' => $giftTender['error'] ?? 'Gift card error'];
        $giftCardCents = (int) ($giftTender['applied_cents'] ?? 0);
        if ($giftCardCents > 0) { $built['gift_card_cents'] = $giftCardCents; $built['gift_card_code'] = $giftTender['gift_card_code'] ?? $giftCardCode; $built['total_cents'] -= $giftCardCents; }

        foreach ($built['lines'] as &$line) {
            if (!$this->reserveLineStock($line)) {
                return ['ok' => false, 'error' => 'Insufficient stock for ' . ($line['title'] ?? 'item')];
            }
        }
        unset($line);

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
        if ($giftCardCents > 0) { $orderPayload['gift_card_code'] = $built['gift_card_code'] ?? $giftCardCode; $orderPayload['gift_card_cents'] = $giftCardCents; }
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

    public function checkoutQuote(string $token, string $customerEmail = '', string $returnUrl = '', string $cancelUrl = ''): array {
        $qs = $this->quoteStore(); $quote = $qs->findByToken($token);
        if (!$quote) return ['ok'=>false,'error'=>'Quote not found'];
        if (($quote['status']??'')==='quote') { $a=$qs->accept($token); if(!($a['ok']??false)) return $a; $quote=$a['quote']; }
        if (($quote['status']??'')!=='pending_payment') return ['ok'=>false,'error'=>'Quote not available'];
        $pending = $this->createPendingOrderFromQuote($quote, trim($customerEmail)!==''?trim($customerEmail):(string)($quote['customer_email']??''));
        if (!($pending['ok']??false)) return $pending; $order=$pending['order']; $shelf=$this->getShelf();
        if (!$shelf||!$shelf->isConfigured()) return ['ok'=>false,'error'=>'Payments not configured','order_id'=>$order['id']];
        $params=['amount_cents'=>(int)$order['total_cents'],'currency'=>$order['currency'],'description'=>'Quote '.($quote['id']??'')];
        if ($returnUrl!=='') $params['return_url']=$returnUrl; if ($cancelUrl!=='') $params['cancel_url']=$cancelUrl;
        $result=$shelf->createPayment('auto',$params,['source_plugin'=>'commerce','source_ref'=>$order['id']]);
        if (!($result['ok']??false)) { $this->orders->update($order['id'],['status'=>'failed']); return ['ok'=>false,'error'=>$result['error']??'Payment failed','order_id'=>$order['id']]; }
        $this->orders->update($order['id'],['payment_id'=>$result['payment_id']??null,'provider'=>$result['provider']??null,'status'=>'pending']);
        return ['ok'=>true,'order_id'=>$order['id'],'quote_id'=>$quote['id']??null,'payment_id'=>$result['payment_id']??null,'redirect_url'=>$result['redirect_url']??null];
    }
    public function createPendingOrderFromQuote(array $quote, string $customerEmail=''): array {
        $lines=$quote['lines']??[]; if(empty($lines)) return ['ok'=>false,'error'=>'Quote has no lines'];
        foreach($lines as &$line){ if(!$this->reserveLineStock($line)) return ['ok'=>false,'error'=>'Insufficient stock']; }
        unset($line);
        $order=$this->orders->create(['status'=>'pending','lines'=>$lines,'total_cents'=>(int)($quote['total_cents']??0),'currency'=>$quote['currency']??'eur','customer_email'=>$customerEmail,'quote_id'=>$quote['id']??null]);
        $this->quoteStore()->update($quote['id'],['order_id'=>$order['id']]); $this->fireOrderCreated($order); return ['ok'=>true,'order'=>$order];
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
        if ((int)($updated['gift_card_cents']??0)>0 && ($updated['gift_card_code']??'')!=='') $this->giftCardStore()->redeem($updated['gift_card_code'],(int)$updated['gift_card_cents'],$order['id']);
        if (!empty($updated['quote_id'])) $this->quoteStore()->markPaid((string)$updated['quote_id'],$order['id']);
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

        if ($isFull) {
            foreach ($order['lines'] ?? [] as $line) {
                $this->restoreLineStock($line);
            }
        }

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

    private function reserveLineStock(array &$line): bool
    {
        $sku = trim((string) ($line['sku'] ?? ''));
        if ($sku !== '' && $this->warehouseStore()->tracksSku($sku)) {
            $warehouseId = $this->warehouseStore()->allocateWarehouse($sku, (int) ($line['quantity'] ?? 1));
            if ($warehouseId === null) {
                return false;
            }
            $line['warehouse_id'] = $warehouseId;
        }

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

    private function restoreLineStock(array $line): void
    {
        $sku = trim((string) ($line['sku'] ?? ''));
        if ($sku !== '' && ($line['warehouse_id'] ?? null)) {
            $this->warehouseStore()->increment($sku, (int) ($line['quantity'] ?? 1), $line['warehouse_id']);
        } elseif ($sku !== '' && $this->warehouseStore()->tracksSku($sku)) {
            $this->warehouseStore()->increment($sku, (int) ($line['quantity'] ?? 1));
        }

        $this->indexer->incrementQuantity(
            $line['collection_slug'] ?? '',
            $line['entry_id'] ?? '',
            (int) ($line['quantity'] ?? 1)
        );

        if (!empty($line['is_bundle'])) {
            foreach ($line['components'] ?? [] as $comp) {
                $this->indexer->incrementQuantity(
                    $comp['collection'] ?? '',
                    $comp['entry_id'] ?? '',
                    (int) ($comp['quantity'] ?? 1)
                );
            }
        }
    }

    private function decrementLineStock(array $line): void
    {
        $sku = trim((string) ($line['sku'] ?? ''));
        if ($sku !== '' && ($line['warehouse_id'] ?? null)) {
            $this->warehouseStore()->decrement($sku, (int) ($line['quantity'] ?? 1), $line['warehouse_id']);
        } elseif ($sku !== '' && $this->warehouseStore()->tracksSku($sku)) {
            $warehouseId = $this->warehouseStore()->allocateWarehouse($sku, (int) ($line['quantity'] ?? 1));
            $this->warehouseStore()->decrement($sku, (int) ($line['quantity'] ?? 1), $warehouseId);
        }

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
