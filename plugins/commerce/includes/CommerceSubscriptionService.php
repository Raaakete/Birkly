<?php
/**
 * Subscription lifecycle service (P8-M4).
 *
 * - Creates a subscription record when a recurring order is paid.
 * - Cancels a subscription (admin action), optionally through the provider.
 * - Applies provider webhook events to move status to past_due / canceled / active.
 *
 * NO plan upgrades in P8 (locked decision P8-D4).
 */

class CommerceSubscriptionService
{
    private CommerceSubscriptionStore $store;
    private ?PaymentShelf $shelf;

    /** @var array<string, int> interval => seconds */
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

    public function __construct(?CommerceSubscriptionStore $store = null, ?PaymentShelf $shelf = null)
    {
        $this->store = $store ?? new CommerceSubscriptionStore();
        $this->shelf = $shelf;
    }

    public function getStore(): CommerceSubscriptionStore
    {
        return $this->store;
    }

    /**
     * Create a subscription record from a paid recurring order.
     * Idempotent: returns the existing subscription if the order already has one.
     */
    public function createFromOrder(array $order): ?array
    {
        if (($order['billing_mode'] ?? 'one_time') !== 'recurring') {
            return null;
        }
        $orderId = $order['id'] ?? '';
        if ($orderId === '') {
            return null;
        }

        $existing = $this->store->findByOrderId($orderId);
        if ($existing) {
            return $existing;
        }

        $interval = $order['billing_interval'] ?? ($order['lines'][0]['interval'] ?? 'month');
        $now = time();

        return $this->store->create([
            'order_id' => $orderId,
            'payment_id' => $order['payment_id'] ?? null,
            'provider' => $order['provider'] ?? null,
            'provider_subscription_id' => $order['provider_subscription_id'] ?? null,
            'customer_email' => $order['customer_email'] ?? '',
            'interval' => $interval,
            'amount_cents' => (int) ($order['total_cents'] ?? 0),
            'currency' => $order['currency'] ?? 'eur',
            'lines' => $order['lines'] ?? [],
            'status' => 'active',
            'current_period_start' => date('c', $now),
            'next_billing_at' => date('c', $this->advance($now, $interval)),
        ]);
    }

    /**
     * Cancel a subscription. Sets status=canceled and clears next billing.
     * Attempts a provider cancel when a provider subscription id + shelf are present.
     */
    public function cancel(string $subscriptionId): ?array
    {
        $sub = $this->store->get($subscriptionId);
        if (!$sub) {
            return null;
        }
        if (($sub['status'] ?? '') === 'canceled') {
            return $sub;
        }

        $providerSubId = $sub['provider_subscription_id'] ?? '';
        if ($providerSubId && $this->shelf) {
            $provider = $sub['provider'] ?? '';
            try {
                $this->shelf->cancelSubscription($provider, $providerSubId);
            } catch (\Throwable $e) {
                // Provider cancel is best-effort; local record is still canceled below.
            }
        }

        $updated = $this->store->update($subscriptionId, [
            'status' => 'canceled',
            'canceled_at' => date('c'),
            'next_billing_at' => null,
        ]);
        $this->broadcast('commerce_subscription_canceled', $updated);
        return $updated;
    }

    /**
     * Apply a normalized provider webhook result to the matching subscription.
     * Expects keys produced by a provider's processWebhook(): subscription_status,
     * subscription_id (provider id), and optionally payment_id.
     */
    public function applyProviderEvent(array $result): ?array
    {
        $status = $this->normalizeStatus($result['subscription_status'] ?? '');
        if ($status === null) {
            return null;
        }

        $sub = $this->matchSubscription($result);
        if (!$sub) {
            return null;
        }

        $id = $sub['id'];
        if ($status === 'past_due') {
            $updated = $this->store->update($id, [
                'status' => 'past_due',
                'past_due_at' => date('c'),
            ]);
            $this->broadcast('commerce_subscription_past_due', $updated);
            return $updated;
        }
        if ($status === 'canceled') {
            $updated = $this->store->update($id, [
                'status' => 'canceled',
                'canceled_at' => date('c'),
                'next_billing_at' => null,
            ]);
            $this->broadcast('commerce_subscription_canceled', $updated);
            return $updated;
        }
        // active — renewal succeeded; advance the billing period.
        $now = time();
        $updated = $this->store->update($id, [
            'status' => 'active',
            'past_due_at' => null,
            'current_period_start' => date('c', $now),
            'next_billing_at' => date('c', $this->advance($now, $sub['interval'] ?? 'month')),
        ]);
        $this->broadcast('commerce_subscription_renewed', $updated);
        return $updated;
    }

    private function matchSubscription(array $result): ?array
    {
        $providerSubId = $result['subscription_id'] ?? '';
        if ($providerSubId !== '') {
            $sub = $this->store->findByProviderSubscriptionId($providerSubId);
            if ($sub) {
                return $sub;
            }
        }
        $paymentId = $result['payment_id'] ?? '';
        if ($paymentId !== '') {
            $sub = $this->store->findByPaymentId($paymentId);
            if ($sub) {
                return $sub;
            }
        }
        $orderId = $result['order_id'] ?? ($result['metadata']['order_id'] ?? '');
        if ($orderId !== '') {
            return $this->store->findByOrderId($orderId);
        }
        return null;
    }

    private function normalizeStatus(string $raw): ?string
    {
        switch (strtolower($raw)) {
            case 'past_due':
            case 'unpaid':
            case 'payment_failed':
                return 'past_due';
            case 'canceled':
            case 'cancelled':
            case 'deleted':
                return 'canceled';
            case 'active':
            case 'paid':
            case 'renewed':
                return 'active';
            default:
                return null;
        }
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
        ];
        if (function_exists('broadcast_event')) {
            broadcast_event($event, $ctx, 'commerce');
        }
        if (function_exists('enqueue_automation')) {
            enqueue_automation($event, $ctx);
        }
    }
}
