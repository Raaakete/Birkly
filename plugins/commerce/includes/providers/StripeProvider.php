<?php
/**
 * Stripe payment provider adapter.
 */

require_once __DIR__ . '/../AbstractProvider.php';

class StripeProvider extends AbstractPaymentProvider
{
    public function getId(): string { return 'stripe'; }
    public function getName(): string { return 'Stripe'; }

    public function getCapabilities(): array
    {
        return [
            'regions' => ['*'],
            'currencies' => ['usd', 'eur', 'gbp', 'jpy', 'aud', 'cad', 'sgd'],
            'methods' => ['card', 'apple_pay', 'google_pay', 'sepa_debit'],
            'features' => [
                'capture' => true,
                'refunds' => true,
                'subscriptions' => true,
                'webhooks' => true,
            ],
        ];
    }

    private function apiBase(array $credentials): string
    {
        return 'https://api.stripe.com/v1';
    }

    private function secret(array $credentials): string
    {
        return $credentials['secret_key'] ?? $credentials['api_key'] ?? '';
    }

    public function testConnection(array $credentials): array
    {
        if ($this->isMockMode($credentials)) {
            return ['ok' => true, 'message' => 'Mock connection successful'];
        }
        $resp = $this->httpRequest('GET', $this->apiBase($credentials) . '/balance', [
            'auth' => ['user' => $this->secret($credentials), 'pass' => ''],
        ]);
        return $resp['ok']
            ? ['ok' => true, 'message' => 'Stripe connection verified']
            : ['ok' => false, 'error' => $resp['error'] ?? 'Connection failed'];
    }

    public function createPayment(array $credentials, array $params): array
    {
        if ($this->isMockMode($credentials)) {
            return $this->mockPaymentResponse($params, $this->getId());
        }

        $fields = [
            'amount' => (int) ($params['amount_cents'] ?? 0),
            'currency' => strtolower($params['currency'] ?? 'usd'),
            'automatic_payment_methods[enabled]' => 'true',
        ];
        if (!empty($params['description'])) {
            $fields['description'] = $params['description'];
        }
        if (!empty($params['metadata']) && is_array($params['metadata'])) {
            foreach ($params['metadata'] as $k => $v) {
                $fields['metadata[' . $k . ']'] = (string) $v;
            }
        }

        $resp = $this->formRequest('POST', $this->apiBase($credentials) . '/payment_intents', $fields, [
            'auth' => ['user' => $this->secret($credentials), 'pass' => ''],
        ]);

        if (!$resp['ok']) {
            return ['ok' => false, 'error' => $resp['error'] ?? 'Stripe payment failed'];
        }

        $data = $resp['data'];
        return [
            'ok' => true,
            'payment_id' => $data['id'] ?? null,
            'status' => $data['status'] ?? 'pending',
            'client_secret' => $data['client_secret'] ?? null,
            'raw' => $data,
        ];
    }

    public function capturePayment(array $credentials, string $paymentId, array $params = []): array
    {
        if ($this->isMockMode($credentials)) {
            return ['ok' => true, 'status' => 'succeeded', 'payment_id' => $paymentId];
        }
        $resp = $this->formRequest('POST', $this->apiBase($credentials) . '/payment_intents/' . $paymentId . '/capture', [], [
            'auth' => ['user' => $this->secret($credentials), 'pass' => ''],
        ]);
        return $resp['ok']
            ? ['ok' => true, 'status' => $resp['data']['status'] ?? 'succeeded', 'raw' => $resp['data']]
            : ['ok' => false, 'error' => $resp['error'] ?? 'Capture failed'];
    }

    public function refundPayment(array $credentials, string $paymentId, ?int $amountCents = null, array $params = []): array
    {
        if ($this->isMockMode($credentials)) {
            return ['ok' => true, 'status' => 'refunded', 'refund_id' => 'mock_refund_' . $paymentId];
        }
        $fields = ['payment_intent' => $paymentId];
        if ($amountCents !== null) {
            $fields['amount'] = $amountCents;
        }
        $resp = $this->formRequest('POST', $this->apiBase($credentials) . '/refunds', $fields, [
            'auth' => ['user' => $this->secret($credentials), 'pass' => ''],
        ]);
        return $resp['ok']
            ? ['ok' => true, 'status' => 'refunded', 'refund_id' => $resp['data']['id'] ?? null, 'raw' => $resp['data']]
            : ['ok' => false, 'error' => $resp['error'] ?? 'Refund failed'];
    }

    public function getPaymentStatus(array $credentials, string $paymentId): array
    {
        if ($this->isMockMode($credentials)) {
            return ['ok' => true, 'status' => 'succeeded', 'payment_id' => $paymentId];
        }
        $resp = $this->httpRequest('GET', $this->apiBase($credentials) . '/payment_intents/' . $paymentId, [
            'auth' => ['user' => $this->secret($credentials), 'pass' => ''],
        ]);
        return $resp['ok']
            ? ['ok' => true, 'status' => $resp['data']['status'] ?? 'unknown', 'raw' => $resp['data']]
            : ['ok' => false, 'error' => $resp['error'] ?? 'Status check failed'];
    }

    public function createSubscription(array $credentials, array $params): array
    {
        if ($this->isMockMode($credentials)) {
            $id = 'mock_sub_' . bin2hex(random_bytes(6));
            return ['ok' => true, 'subscription_id' => $id, 'status' => 'active'];
        }
        $fields = [
            'customer' => $params['customer_id'] ?? '',
            'items[0][price]' => $params['price_id'] ?? '',
        ];
        $resp = $this->formRequest('POST', $this->apiBase($credentials) . '/subscriptions', $fields, [
            'auth' => ['user' => $this->secret($credentials), 'pass' => ''],
        ]);
        return $resp['ok']
            ? ['ok' => true, 'subscription_id' => $resp['data']['id'] ?? null, 'status' => $resp['data']['status'] ?? 'active', 'raw' => $resp['data']]
            : ['ok' => false, 'error' => $resp['error'] ?? 'Subscription failed'];
    }

    public function cancelSubscription(array $credentials, string $subscriptionId): array
    {
        if ($this->isMockMode($credentials)) {
            return ['ok' => true, 'status' => 'cancelled', 'subscription_id' => $subscriptionId];
        }
        $resp = $this->formRequest('DELETE', $this->apiBase($credentials) . '/subscriptions/' . $subscriptionId, [], [
            'auth' => ['user' => $this->secret($credentials), 'pass' => ''],
        ]);
        return $resp['ok']
            ? ['ok' => true, 'status' => 'cancelled', 'raw' => $resp['data']]
            : ['ok' => false, 'error' => $resp['error'] ?? 'Cancel failed'];
    }

    public function updateSubscription(array $credentials, string $subscriptionId, array $params): array
    {
        if ($this->isMockMode($credentials)) {
            $interval = strtolower((string) ($params['interval'] ?? 'month'));
            $seconds = $this->intervalSeconds($interval);
            $amount = (int) ($params['amount_cents'] ?? 0);
            return [
                'ok' => true,
                'subscription_id' => $subscriptionId,
                'status' => 'active',
                'next_billing_at' => date('c', time() + $seconds),
                'proration_cents' => max(0, (int) round($amount * 0.1)),
            ];
        }

        $priceId = $params['price_id'] ?? '';
        if ($priceId === '') {
            return ['ok' => false, 'error' => 'price_id required for Stripe subscription update'];
        }

        $fetch = $this->httpRequest('GET', $this->apiBase($credentials) . '/subscriptions/' . $subscriptionId, [
            'auth' => ['user' => $this->secret($credentials), 'pass' => ''],
        ]);
        if (!$fetch['ok']) {
            return ['ok' => false, 'error' => $fetch['error'] ?? 'Failed to load subscription'];
        }

        $itemId = $fetch['data']['items']['data'][0]['id'] ?? '';
        if ($itemId === '') {
            return ['ok' => false, 'error' => 'Subscription has no line items'];
        }

        $fields = [
            'items[0][id]' => $itemId,
            'items[0][price]' => $priceId,
            'proration_behavior' => $params['proration_behavior'] ?? 'create_prorations',
        ];

        $resp = $this->formRequest('POST', $this->apiBase($credentials) . '/subscriptions/' . $subscriptionId, $fields, [
            'auth' => ['user' => $this->secret($credentials), 'pass' => ''],
        ]);

        if (!$resp['ok']) {
            return ['ok' => false, 'error' => $resp['error'] ?? 'Subscription update failed'];
        }

        $data = $resp['data'];
        $periodEnd = $data['current_period_end'] ?? null;

        return [
            'ok' => true,
            'subscription_id' => $data['id'] ?? $subscriptionId,
            'status' => $data['status'] ?? 'active',
            'next_billing_at' => $periodEnd ? date('c', (int) $periodEnd) : null,
            'raw' => $data,
        ];
    }

    public function retrySubscriptionPayment(array $credentials, string $subscriptionId, array $params = []): array
    {
        if ($this->isMockMode($credentials)) {
            return ['ok' => true, 'status' => 'paid', 'subscription_id' => $subscriptionId];
        }

        $invoiceId = $params['invoice_id'] ?? '';
        if ($invoiceId === '') {
            $list = $this->httpRequest(
                'GET',
                $this->apiBase($credentials) . '/invoices?subscription=' . urlencode($subscriptionId) . '&status=open&limit=1',
                ['auth' => ['user' => $this->secret($credentials), 'pass' => '']]
            );
            if (!$list['ok']) {
                return ['ok' => false, 'error' => $list['error'] ?? 'Failed to list invoices'];
            }
            $invoiceId = $list['data']['data'][0]['id'] ?? '';
        }

        if ($invoiceId === '') {
            return ['ok' => false, 'error' => 'No open invoice found for subscription'];
        }

        $resp = $this->formRequest('POST', $this->apiBase($credentials) . '/invoices/' . $invoiceId . '/pay', [], [
            'auth' => ['user' => $this->secret($credentials), 'pass' => ''],
        ]);

        return $resp['ok']
            ? ['ok' => true, 'status' => 'paid', 'invoice_id' => $invoiceId, 'raw' => $resp['data']]
            : ['ok' => false, 'error' => $resp['error'] ?? 'Invoice payment retry failed'];
    }

    private function intervalSeconds(string $interval): int
    {
        $map = [
            'day' => 86400,
            'week' => 604800,
            'month' => 2592000,
            'monthly' => 2592000,
            'year' => 31536000,
            'yearly' => 31536000,
            'annual' => 31536000,
        ];
        return $map[$interval] ?? 2592000;
    }

    public function verifyWebhookSignature(array $credentials, string $payload, array $headers): bool
    {
        if ($this->isMockMode($credentials)) {
            return ($this->getHeader($headers, 'X-Mock-Signature') === 'valid');
        }

        $secret = $credentials['webhook_secret'] ?? '';
        if ($secret === '') {
            return false;
        }

        $sigHeader = $this->getHeader($headers, 'Stripe-Signature');
        if (!$sigHeader) {
            return false;
        }

        $parts = [];
        foreach (explode(',', $sigHeader) as $item) {
            $kv = explode('=', trim($item), 2);
            if (count($kv) === 2) {
                $parts[$kv[0]] = $kv[1];
            }
        }

        $timestamp = $parts['t'] ?? '';
        $signature = $parts['v1'] ?? '';
        if ($timestamp === '' || $signature === '') {
            return false;
        }

        $signed = $timestamp . '.' . $payload;
        $expected = hash_hmac('sha256', $signed, $secret);
        return hash_equals($expected, $signature);
    }

    public function processWebhook(array $credentials, array $payload, array $headers): array
    {
        $type = $payload['type'] ?? '';
        $object = $payload['data']['object'] ?? [];
        $status = 'received';

        if ($type === 'payment_intent.succeeded') {
            $status = 'succeeded';
        } elseif ($type === 'payment_intent.payment_failed') {
            $status = 'failed';
        }

        $result = [
            'event_type' => $type,
            'status' => $status,
            'payment_id' => $object['id'] ?? null,
            'amount_cents' => $object['amount'] ?? null,
            'currency' => $object['currency'] ?? null,
            'provider' => $this->getId(),
            'message' => 'Stripe webhook: ' . $type,
        ];

        $subscription = $this->mapSubscriptionEvent($type, $object);
        if ($subscription !== null) {
            $result = array_merge($result, $subscription);
        }

        return $result;
    }

    /**
     * Normalize subscription lifecycle events to a common shape (P8-M4).
     * @return array{subscription_id: ?string, subscription_status: string}|null
     */
    private function mapSubscriptionEvent(string $type, array $object): ?array
    {
        switch ($type) {
            case 'customer.subscription.deleted':
                return [
                    'subscription_id' => $object['id'] ?? null,
                    'subscription_status' => 'canceled',
                ];
            case 'customer.subscription.updated':
                return [
                    'subscription_id' => $object['id'] ?? null,
                    'subscription_status' => $object['status'] ?? 'active',
                ];
            case 'invoice.payment_failed':
                return [
                    'subscription_id' => $object['subscription'] ?? null,
                    'subscription_status' => 'past_due',
                    'payment_failed' => true,
                    'attempt_count' => (int) ($object['attempt_count'] ?? 0),
                    'invoice_id' => $object['id'] ?? null,
                ];
            case 'invoice.paid':
            case 'invoice.payment_succeeded':
                return [
                    'subscription_id' => $object['subscription'] ?? null,
                    'subscription_status' => 'active',
                ];
            default:
                return null;
        }
    }
}
