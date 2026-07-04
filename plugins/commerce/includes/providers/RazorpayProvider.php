<?php
require_once __DIR__ . '/../AbstractProvider.php';

class RazorpayProvider extends AbstractPaymentProvider
{
    public function getId(): string { return 'razorpay'; }
    public function getName(): string { return 'Razorpay'; }

    public function getCapabilities(): array
    {
        return [
            'regions' => ['IN'],
            'currencies' => ['inr'],
            'methods' => ['card', 'upi', 'netbanking', 'wallet'],
            'features' => ['capture' => true, 'refunds' => true, 'subscriptions' => true, 'webhooks' => true],
        ];
    }

    private function apiBase(): string
    {
        return 'https://api.razorpay.com/v1';
    }

    public function testConnection(array $credentials): array
    {
        if ($this->isMockMode($credentials)) {
            return ['ok' => true, 'message' => 'Mock connection successful'];
        }
        $keyId = $credentials['key_id'] ?? '';
        $keySecret = $credentials['key_secret'] ?? '';
        $resp = $this->httpRequest('GET', $this->apiBase() . '/payments?count=1', [
            'auth' => ['user' => $keyId, 'pass' => $keySecret],
        ]);
        return ($resp['ok'] || $resp['status'] === 200)
            ? ['ok' => true, 'message' => 'Razorpay connection verified']
            : ['ok' => false, 'error' => $resp['error'] ?? 'Connection failed'];
    }

    public function createPayment(array $credentials, array $params): array
    {
        if ($this->isMockMode($credentials)) {
            return $this->mockPaymentResponse($params, $this->getId());
        }
        $body = [
            'amount' => (int) ($params['amount_cents'] ?? 0),
            'currency' => strtoupper($params['currency'] ?? 'INR'),
            'receipt' => $params['reference'] ?? ('rcpt_' . bin2hex(random_bytes(4))),
        ];
        $resp = $this->httpRequest('POST', $this->apiBase() . '/orders', [
            'auth' => ['user' => $credentials['key_id'] ?? '', 'pass' => $credentials['key_secret'] ?? ''],
            'body' => $body,
        ]);
        if (!$resp['ok']) {
            return ['ok' => false, 'error' => $resp['error'] ?? 'Razorpay order failed'];
        }
        return [
            'ok' => true,
            'payment_id' => $resp['data']['id'] ?? null,
            'status' => $resp['data']['status'] ?? 'created',
            'raw' => $resp['data'],
        ];
    }

    public function capturePayment(array $credentials, string $paymentId, array $params = []): array
    {
        if ($this->isMockMode($credentials)) {
            return ['ok' => true, 'status' => 'captured'];
        }
        $amount = (int) ($params['amount_cents'] ?? 0);
        $resp = $this->httpRequest('POST', $this->apiBase() . '/payments/' . $paymentId . '/capture', [
            'auth' => ['user' => $credentials['key_id'] ?? '', 'pass' => $credentials['key_secret'] ?? ''],
            'body' => ['amount' => $amount, 'currency' => strtoupper($params['currency'] ?? 'INR')],
        ]);
        return $resp['ok']
            ? ['ok' => true, 'status' => 'captured', 'raw' => $resp['data']]
            : ['ok' => false, 'error' => $resp['error'] ?? 'Capture failed'];
    }

    public function refundPayment(array $credentials, string $paymentId, ?int $amountCents = null, array $params = []): array
    {
        if ($this->isMockMode($credentials)) {
            return ['ok' => true, 'status' => 'refunded'];
        }
        $body = [];
        if ($amountCents !== null) {
            $body['amount'] = $amountCents;
        }
        $resp = $this->httpRequest('POST', $this->apiBase() . '/payments/' . $paymentId . '/refund', [
            'auth' => ['user' => $credentials['key_id'] ?? '', 'pass' => $credentials['key_secret'] ?? ''],
            'body' => $body,
        ]);
        return $resp['ok']
            ? ['ok' => true, 'status' => 'refunded', 'raw' => $resp['data']]
            : ['ok' => false, 'error' => $resp['error'] ?? 'Refund failed'];
    }

    public function getPaymentStatus(array $credentials, string $paymentId): array
    {
        if ($this->isMockMode($credentials)) {
            return ['ok' => true, 'status' => 'captured'];
        }
        $resp = $this->httpRequest('GET', $this->apiBase() . '/payments/' . $paymentId, [
            'auth' => ['user' => $credentials['key_id'] ?? '', 'pass' => $credentials['key_secret'] ?? ''],
        ]);
        return $resp['ok']
            ? ['ok' => true, 'status' => $resp['data']['status'] ?? 'unknown', 'raw' => $resp['data']]
            : ['ok' => false, 'error' => $resp['error'] ?? 'Status failed'];
    }

    public function createSubscription(array $credentials, array $params): array
    {
        if ($this->isMockMode($credentials)) {
            return ['ok' => true, 'subscription_id' => 'mock_sub_rzp', 'status' => 'active'];
        }
        return ['ok' => false, 'error' => 'Razorpay subscription requires plan_id'];
    }

    public function cancelSubscription(array $credentials, string $subscriptionId): array
    {
        if ($this->isMockMode($credentials)) {
            return ['ok' => true, 'status' => 'cancelled'];
        }
        $resp = $this->httpRequest('POST', $this->apiBase() . '/subscriptions/' . $subscriptionId . '/cancel', [
            'auth' => ['user' => $credentials['key_id'] ?? '', 'pass' => $credentials['key_secret'] ?? ''],
            'body' => ['cancel_at_cycle_end' => 0],
        ]);
        return $resp['ok']
            ? ['ok' => true, 'status' => 'cancelled']
            : ['ok' => false, 'error' => $resp['error'] ?? 'Cancel failed'];
    }

    public function verifyWebhookSignature(array $credentials, string $payload, array $headers): bool
    {
        if ($this->isMockMode($credentials)) {
            return $this->getHeader($headers, 'X-Mock-Signature') === 'valid';
        }
        $secret = $credentials['webhook_secret'] ?? '';
        $sig = $this->getHeader($headers, 'X-Razorpay-Signature') ?? '';
        if ($secret === '' || $sig === '') {
            return false;
        }
        $expected = hash_hmac('sha256', $payload, $secret);
        return hash_equals($expected, $sig);
    }

    public function processWebhook(array $credentials, array $payload, array $headers): array
    {
        $event = $payload['event'] ?? '';
        $status = strpos($event, 'captured') !== false ? 'succeeded' : 'received';
        return [
            'event_type' => $event,
            'status' => $status,
            'payment_id' => $payload['payload']['payment']['entity']['id'] ?? null,
            'provider' => $this->getId(),
            'message' => 'Razorpay webhook: ' . $event,
        ];
    }
}
