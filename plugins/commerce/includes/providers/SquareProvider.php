<?php
require_once __DIR__ . '/../AbstractProvider.php';

class SquareProvider extends AbstractPaymentProvider
{
    public function getId(): string { return 'square'; }
    public function getName(): string { return 'Square'; }

    public function getCapabilities(): array
    {
        return [
            'regions' => ['US', 'CA', 'GB', 'AU', 'JP'],
            'currencies' => ['usd', 'cad', 'gbp', 'aud', 'jpy'],
            'methods' => ['card', 'apple_pay', 'google_pay', 'cash_app'],
            'features' => ['capture' => true, 'refunds' => true, 'subscriptions' => false, 'webhooks' => true],
        ];
    }

    private function apiBase(array $credentials): string
    {
        return ($credentials['environment'] ?? 'sandbox') === 'production'
            ? 'https://connect.squareup.com'
            : 'https://connect.squareupsandbox.com';
    }

    public function testConnection(array $credentials): array
    {
        if ($this->isMockMode($credentials)) {
            return ['ok' => true, 'message' => 'Mock connection successful'];
        }
        $resp = $this->httpRequest('GET', $this->apiBase($credentials) . '/v2/locations', [
            'auth' => $credentials['access_token'] ?? '',
            'headers' => ['Square-Version: 2024-01-18'],
        ]);
        return $resp['ok']
            ? ['ok' => true, 'message' => 'Square connection verified']
            : ['ok' => false, 'error' => $resp['error'] ?? 'Connection failed'];
    }

    public function createPayment(array $credentials, array $params): array
    {
        if ($this->isMockMode($credentials)) {
            return $this->mockPaymentResponse($params, $this->getId());
        }
        $body = [
            'source_id' => $params['source_id'] ?? 'EXTERNAL',
            'idempotency_key' => bin2hex(random_bytes(16)),
            'amount_money' => [
                'amount' => (int) ($params['amount_cents'] ?? 0),
                'currency' => strtoupper($params['currency'] ?? 'USD'),
            ],
            'location_id' => $credentials['location_id'] ?? '',
        ];
        $resp = $this->httpRequest('POST', $this->apiBase($credentials) . '/v2/payments', [
            'auth' => $credentials['access_token'] ?? '',
            'headers' => ['Square-Version: 2024-01-18'],
            'body' => $body,
        ]);
        if (!$resp['ok']) {
            return ['ok' => false, 'error' => $resp['error'] ?? 'Square payment failed'];
        }
        $payment = $resp['data']['payment'] ?? [];
        return [
            'ok' => true,
            'payment_id' => $payment['id'] ?? null,
            'status' => $payment['status'] ?? 'PENDING',
            'raw' => $resp['data'],
        ];
    }

    public function capturePayment(array $credentials, string $paymentId, array $params = []): array
    {
        if ($this->isMockMode($credentials)) {
            return ['ok' => true, 'status' => 'COMPLETED'];
        }
        $resp = $this->httpRequest('POST', $this->apiBase($credentials) . '/v2/payments/' . $paymentId . '/complete', [
            'auth' => $credentials['access_token'] ?? '',
            'headers' => ['Square-Version: 2024-01-18'],
            'body' => new stdClass(),
        ]);
        return $resp['ok']
            ? ['ok' => true, 'status' => 'COMPLETED', 'raw' => $resp['data']]
            : ['ok' => false, 'error' => $resp['error'] ?? 'Capture failed'];
    }

    public function refundPayment(array $credentials, string $paymentId, ?int $amountCents = null, array $params = []): array
    {
        if ($this->isMockMode($credentials)) {
            return ['ok' => true, 'status' => 'refunded'];
        }
        $body = [
            'idempotency_key' => bin2hex(random_bytes(16)),
            'payment_id' => $paymentId,
        ];
        if ($amountCents !== null) {
            $body['amount_money'] = ['amount' => $amountCents, 'currency' => strtoupper($params['currency'] ?? 'USD')];
        }
        $resp = $this->httpRequest('POST', $this->apiBase($credentials) . '/v2/refunds', [
            'auth' => $credentials['access_token'] ?? '',
            'headers' => ['Square-Version: 2024-01-18'],
            'body' => $body,
        ]);
        return $resp['ok']
            ? ['ok' => true, 'status' => 'refunded', 'raw' => $resp['data']]
            : ['ok' => false, 'error' => $resp['error'] ?? 'Refund failed'];
    }

    public function getPaymentStatus(array $credentials, string $paymentId): array
    {
        if ($this->isMockMode($credentials)) {
            return ['ok' => true, 'status' => 'COMPLETED'];
        }
        $resp = $this->httpRequest('GET', $this->apiBase($credentials) . '/v2/payments/' . $paymentId, [
            'auth' => $credentials['access_token'] ?? '',
            'headers' => ['Square-Version: 2024-01-18'],
        ]);
        return $resp['ok']
            ? ['ok' => true, 'status' => $resp['data']['payment']['status'] ?? 'unknown', 'raw' => $resp['data']]
            : ['ok' => false, 'error' => $resp['error'] ?? 'Status failed'];
    }

    public function createSubscription(array $credentials, array $params): array
    {
        return ['ok' => false, 'error' => 'Square subscriptions not supported in this adapter'];
    }

    public function cancelSubscription(array $credentials, string $subscriptionId): array
    {
        return ['ok' => false, 'error' => 'Not supported'];
    }

    public function verifyWebhookSignature(array $credentials, string $payload, array $headers): bool
    {
        if ($this->isMockMode($credentials)) {
            return $this->getHeader($headers, 'X-Mock-Signature') === 'valid';
        }
        return is_array(json_decode($payload, true));
    }

    public function processWebhook(array $credentials, array $payload, array $headers): array
    {
        $type = $payload['type'] ?? '';
        $status = strpos($type, 'payment') !== false ? 'succeeded' : 'received';
        return [
            'event_type' => $type,
            'status' => $status,
            'payment_id' => $payload['data']['object']['payment']['id'] ?? null,
            'provider' => $this->getId(),
            'message' => 'Square webhook: ' . $type,
        ];
    }
}
