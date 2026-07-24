<?php
require_once __DIR__ . '/../AbstractProvider.php';

class KomojuProvider extends AbstractPaymentProvider
{
    public function getId(): string { return 'komoju'; }
    public function getName(): string { return 'Komoju'; }

    public function getCapabilities(): array
    {
        return [
            'regions' => ['JP'],
            'currencies' => ['jpy'],
            'methods' => ['credit_card', 'konbini', 'paypay', 'bank_transfer', 'applepay'],
            'features' => ['capture' => false, 'refunds' => true, 'subscriptions' => false, 'webhooks' => true],
        ];
    }

    private function apiBase(array $credentials): string
    {
        return ($credentials['mode'] ?? 'test') === 'live'
            ? 'https://komoju.com/api/v1'
            : 'https://komoju.com/api/v1';
    }

    public function testConnection(array $credentials): array
    {
        if ($this->isMockMode($credentials)) {
            return ['ok' => true, 'message' => 'Mock connection successful'];
        }
        return !empty($credentials['secret_key'])
            ? ['ok' => true, 'message' => 'Komoju secret key present']
            : ['ok' => false, 'error' => 'Missing secret key'];
    }

    public function createPayment(array $credentials, array $params): array
    {
        if ($this->isMockMode($credentials)) {
            return $this->mockPaymentResponse($params, $this->getId());
        }
        $body = [
            'amount' => (int) ($params['amount_cents'] ?? 0),
            'currency' => strtoupper($params['currency'] ?? 'JPY'),
            'external_order_num' => $params['reference'] ?? ('order_' . bin2hex(random_bytes(4))),
        ];
        if (!empty($params['method'])) {
            $body['payment_details'] = ['type' => $params['method']];
        }
        $resp = $this->httpRequest('POST', $this->apiBase($credentials) . '/payments', [
            'auth' => ['user' => $credentials['secret_key'] ?? '', 'pass' => ''],
            'body' => $body,
        ]);
        if (!$resp['ok']) {
            return ['ok' => false, 'error' => $resp['error'] ?? 'Komoju payment failed'];
        }
        return [
            'ok' => true,
            'payment_id' => $resp['data']['id'] ?? null,
            'status' => $resp['data']['status'] ?? 'pending',
            'redirect_url' => $resp['data']['payment_details']['redirect_url'] ?? null,
            'raw' => $resp['data'],
        ];
    }

    public function capturePayment(array $credentials, string $paymentId, array $params = []): array
    {
        return ['ok' => false, 'error' => 'Komoju does not support separate capture'];
    }

    public function refundPayment(array $credentials, string $paymentId, ?int $amountCents = null, array $params = []): array
    {
        if ($this->isMockMode($credentials)) {
            return ['ok' => true, 'status' => 'refunded'];
        }
        $body = ['amount' => $amountCents];
        $resp = $this->httpRequest('POST', $this->apiBase($credentials) . '/payments/' . $paymentId . '/refund', [
            'auth' => ['user' => $credentials['secret_key'] ?? '', 'pass' => ''],
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
        $resp = $this->httpRequest('GET', $this->apiBase($credentials) . '/payments/' . $paymentId, [
            'auth' => ['user' => $credentials['secret_key'] ?? '', 'pass' => ''],
        ]);
        return $resp['ok']
            ? ['ok' => true, 'status' => $resp['data']['status'] ?? 'unknown', 'raw' => $resp['data']]
            : ['ok' => false, 'error' => $resp['error'] ?? 'Status failed'];
    }

    public function createSubscription(array $credentials, array $params): array
    {
        return ['ok' => false, 'error' => 'Komoju subscriptions not supported'];
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
        $secret = $credentials['webhook_secret'] ?? '';
        $sig = $this->getHeader($headers, 'X-Komoju-Signature') ?? '';
        if ($secret === '' || $sig === '') {
            return is_array(json_decode($payload, true));
        }
        $expected = hash_hmac('sha256', $payload, $secret);
        return hash_equals($expected, $sig);
    }

    public function processWebhook(array $credentials, array $payload, array $headers): array
    {
        $status = $payload['status'] ?? 'received';
        $normalized = in_array($status, ['captured', 'completed'], true) ? 'succeeded' : $status;
        return [
            'event_type' => 'payment.' . $status,
            'status' => $normalized,
            'payment_id' => $payload['id'] ?? null,
            'provider' => $this->getId(),
            'message' => 'Komoju webhook: ' . $status,
        ];
    }
}
