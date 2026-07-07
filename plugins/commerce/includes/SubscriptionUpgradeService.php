<?php
/**
 * Subscription plan upgrades (P11-M1).
 *
 * Locked: same product plan change only; simple proration via provider.
 */

class SubscriptionUpgradeService
{
    private CommerceSubscriptionStore $store;
    private ?PaymentShelf $shelf;
    private CommerceCatalog $catalog;

    /** @var array<string, int> */
    private const INTERVAL_SECONDS = [
        'day' => 86400,
        'daily' => 86400,
        'week' => 604800,
        'weekly' => 604800,
        'month' => 2592000,
        'monthly' => 2592000,
        'quarter' => 7776000,
        'year' => 31536000,
        'yearly' => 31536000,
        'annual' => 31536000,
    ];

    public function __construct(
        ?CommerceSubscriptionStore $store = null,
        ?PaymentShelf $shelf = null,
        ?CommerceCatalog $catalog = null
    ) {
        $this->store = $store ?? new CommerceSubscriptionStore();
        $this->shelf = $shelf;
        $this->catalog = $catalog ?? new CommerceCatalog();
    }

    /**
     * Recurring purchase_options on the same catalog entry (excluding current).
     *
     * @return list<array<string, mixed>>
     */
    public function listUpgradeOptions(string $subscriptionId): array
    {
        $sub = $this->store->get($subscriptionId);
        if (!$sub) {
            return [];
        }

        $line = $this->primaryLine($sub);
        if (!$line) {
            return [];
        }

        $item = $this->catalog->getItem($line['collection_slug'] ?? '', $line['entry_id'] ?? '');
        if (!$item) {
            return [];
        }

        if (!class_exists('CommerceSchema')) {
            require_once __DIR__ . '/CommerceSchema.php';
        }

        $commerce = commerce_normalize_value($item['commerce'] ?? $item);
        $currentId = $line['purchase_option_id'] ?? '';
        $options = [];
        foreach ($commerce['purchase_options'] ?? [] as $opt) {
            if (($opt['billing'] ?? '') !== 'recurring') {
                continue;
            }
            if (($opt['id'] ?? '') === $currentId) {
                continue;
            }
            $options[] = [
                'id' => $opt['id'],
                'label' => $opt['label'] ?? $opt['id'],
                'interval' => $opt['interval'] ?? 'month',
                'price_cents' => (int) ($opt['price_cents'] ?? 0),
                'currency' => $opt['currency'] ?? 'eur',
            ];
        }

        return $options;
    }

    /**
     * Upgrade an active subscription to a new purchase_option on the same product.
     */
    public function upgrade(string $subscriptionId, string $purchaseOptionId): array
    {
        $sub = $this->store->get($subscriptionId);
        if (!$sub) {
            return ['ok' => false, 'error' => 'Subscription not found'];
        }

        $status = $sub['status'] ?? '';
        if (!in_array($status, ['active', 'past_due'], true)) {
            return ['ok' => false, 'error' => 'Subscription cannot be upgraded in status: ' . $status];
        }

        $line = $this->primaryLine($sub);
        if (!$line) {
            return ['ok' => false, 'error' => 'Subscription has no product line'];
        }

        $collectionSlug = $line['collection_slug'] ?? '';
        $entryId = $line['entry_id'] ?? '';
        $item = $this->catalog->getItem($collectionSlug, $entryId);
        if (!$item) {
            return ['ok' => false, 'error' => 'Catalog item not found'];
        }

        if (!class_exists('CommerceSchema')) {
            require_once __DIR__ . '/CommerceSchema.php';
        }

        $commerce = commerce_normalize_value($item['commerce'] ?? $item);
        $newOption = CommerceSchema::findPurchaseOption($commerce, $purchaseOptionId);
        if (!$newOption) {
            return ['ok' => false, 'error' => 'Purchase option not found'];
        }
        if (($newOption['billing'] ?? '') !== 'recurring') {
            return ['ok' => false, 'error' => 'Target must be a recurring purchase option'];
        }

        $currentOptionId = $line['purchase_option_id'] ?? '';
        if ($currentOptionId === $purchaseOptionId) {
            return ['ok' => false, 'error' => 'Subscription is already on this purchase option'];
        }

        $newInterval = $newOption['interval'] ?? 'month';
        $newAmount = (int) ($newOption['price_cents'] ?? 0);
        $newCurrency = strtolower((string) ($newOption['currency'] ?? ($sub['currency'] ?? 'eur')));

        $providerResult = ['ok' => true];
        $providerSubId = $sub['provider_subscription_id'] ?? '';
        $provider = $sub['provider'] ?? '';
        if ($providerSubId !== '' && $provider !== '' && $this->shelf) {
            $providerResult = $this->shelf->updateSubscription($provider, $providerSubId, [
                'price_id' => $newOption['provider_price_id'] ?? ($newOption['stripe_price_id'] ?? null),
                'purchase_option_id' => $purchaseOptionId,
                'amount_cents' => $newAmount,
                'currency' => $newCurrency,
                'interval' => $newInterval,
                'proration_behavior' => 'create_prorations',
            ], [
                'source_plugin' => 'commerce',
                'source_ref' => $subscriptionId,
            ]);
            if (!($providerResult['ok'] ?? false)) {
                return ['ok' => false, 'error' => $providerResult['error'] ?? 'Provider plan change failed'];
            }
        }

        $now = time();
        $nextBilling = $providerResult['next_billing_at'] ?? null;
        if ($nextBilling === null || $nextBilling === '') {
            $nextBilling = date('c', $this->advance($now, $newInterval));
        }

        $lines = $sub['lines'] ?? [];
        foreach ($lines as $idx => $subLine) {
            if (($subLine['entry_id'] ?? '') === $entryId
                && ($subLine['collection_slug'] ?? '') === $collectionSlug) {
                $lines[$idx]['purchase_option_id'] = $purchaseOptionId;
                $lines[$idx]['interval'] = $newInterval;
                $lines[$idx]['price_cents'] = $newAmount;
                break;
            }
        }

        $patch = [
            'purchase_option_id' => $purchaseOptionId,
            'interval' => $newInterval,
            'amount_cents' => $newAmount,
            'currency' => $newCurrency,
            'lines' => $lines,
            'next_billing_at' => $nextBilling,
            'upgraded_at' => date('c'),
        ];

        if (isset($providerResult['proration_cents'])) {
            $patch['last_proration_cents'] = (int) $providerResult['proration_cents'];
        }

        $updated = $this->store->update($subscriptionId, $patch);
        if (!$updated) {
            return ['ok' => false, 'error' => 'Failed to update subscription record'];
        }

        $this->broadcast('commerce_subscription_upgraded', $updated);

        return ['ok' => true, 'subscription' => $updated, 'provider' => $providerResult];
    }

    private function primaryLine(array $subscription): ?array
    {
        $lines = $subscription['lines'] ?? [];
        return $lines[0] ?? null;
    }

    private function advance(int $from, string $interval): int
    {
        $seconds = self::INTERVAL_SECONDS[strtolower($interval)] ?? self::INTERVAL_SECONDS['month'];
        return $from + $seconds;
    }

    private function broadcast(string $event, ?array $subscription): void
    {
        if (!$subscription) {
            return;
        }
        $ctx = [
            'subscription_id' => $subscription['id'] ?? null,
            'order_id' => $subscription['order_id'] ?? null,
            'status' => $subscription['status'] ?? null,
            'customer_email' => $subscription['customer_email'] ?? null,
            'purchase_option_id' => $subscription['purchase_option_id'] ?? null,
            'interval' => $subscription['interval'] ?? null,
        ];
        if (function_exists('broadcast_event')) {
            broadcast_event($event, $ctx, 'commerce');
        }
        if (function_exists('enqueue_automation')) {
            enqueue_automation($event, $ctx);
        }
    }
}
