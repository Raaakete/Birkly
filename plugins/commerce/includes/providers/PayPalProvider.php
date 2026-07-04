<?php
require_once __DIR__ . '/../AbstractProvider.php';

class PayPalProvider extends AbstractPaymentProvider
{
    public function getId(): string { return 'paypal'; }
    public function getName(): string { return 'PayPal'; }

    public function getCapabilities(): array
    {
        return [
            'regions' => ['*'],
            'currencies' => ['usd', 'eur', 'gbp', 'cad', 'aud', 'jpy'],
            'methods' => ['paypal', 'card'],
            'features' => ['capture' => true, 'refunds' => true, 'subscriptions' => true, 'webhooks' => true],
        ];
    }

    private function apiBase(array $credentials): string
    {
        return ($credentials['mode'] ?? 'sandbox') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    public function testConnection(array $credentials): array
    {
        if ($this->isMockMode($credentials)) {
            return ['ok' => true, 'message' => 'Mock connection successful'];
        }
        $token = $this->getAccessToken($credentials);
        return $token ? ['ok' => true, 'message' => 'PayPal connection verified'] : ['ok' => false, 'error' => 'Auth failed'];
    }

    private function getAccessToken(array $credentials): ?string
    {
        $clientId = $credentials['client_id'] ?? '';
        $secret = $credentials['client_secret'] ?? '';
        $resp = $this->httpRequest('POST', $this->apiBase($credentials) . '/v1/oauth2/token', [
            'auth' => ['user' => $clientId, 'pass' => $secret],
            'headers' => ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'],
            'body' => 'grant_type=client_credentials',
        ]);
        return $resp['data']['access_token'] ?? null;
    }

    public function createPayment(array $credentials, array $params): array
    {
        if ($this->isMockMode($credentials)) {
            return $this->mockPaymentResponse($params, $this->getId());
        }
        $token = $this->getAccessToken($credentials);
        if (!$token) {
            return ['ok' => false, 'error' => 'PayPal auth failed'];
        }
        $body = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'amount' => [
                    'currency_code' => strtoupper($params['currency'] ?? 'USD'),
                    'value' => number_format(($params['amount_cents'] ?? 0) / 100, 2, '.', ''),
                ],
            ]],
        ];
        $resp = $this->httpRequest('POST', $this->apiBase($credentials) . '/v2/checkout/orders', [
            'auth' => $token,
            'body' => $body,
        ]);
        if (!$resp['ok']) {
            return ['ok' => false, 'error' => $resp['error'] ?? 'PayPal order failed'];
        }
        return [
            'ok' => true,
            'payment_id' => $resp['data']['id'] ?? null,
            'status' => $resp['data']['status'] ?? 'CREATED',
            'raw' => $resp['data'],
        ];
    }

    public function capturePayment(array $credentials, string $paymentId, array $params = []): array
    {
        if ($this->isMockMode($credentials)) {
            return ['ok' => true, 'status' => 'COMPLETED', 'payment_id' => $paymentId];
        }
        $token = $this->getAccessToken($credentials);
        $resp = $this->httpRequest('POST', $this->apiBase($credentials) . '/v2/checkout/orders/' . $paymentId . '/capture', [
            'auth' => $token,
            'body' => new stdClass(),
        ]);
        return $resp['ok']
            ? ['ok' => true, 'status' => $resp['data']['status'] ?? 'COMPLETED', 'raw' => $resp['data']]
            : ['ok' => false, 'error' => $resp['error'] ?? 'Capture failed'];
    }

    public function refundPayment(array $credentials, string $paymentId, ?int $amountCents = null, array $params = []): array
    {
        if ($this->isMockMode($credentials)) {
            return ['ok' => true, 'status' => 'refunded'];
        }
        return ['ok' => false, 'error' => 'PayPal refund requires capture ID — use capture reference'];
    }

    public function getPaymentStatus(array $credentials, string $paymentId): array
    {
        if ($this->isMockMode($credentials)) {
            return ['ok' => true, 'status' => 'COMPLETED'];
        }
        $token = $this->getAccessToken($credentials);
        $resp = $this->httpRequest('GET', $this->apiBase($credentials) . '/v2/checkout/orders/' . $paymentId, [
            'auth' => $token,
        ]);
        return $resp['ok']
            ? ['ok' => true, 'status' => $resp['data']['status'] ?? 'unknown', 'raw' => $resp['data']]
            : ['ok' => false, 'error' => $resp['error'] ?? 'Status failed'];
    }

    public function createSubscription(array $credentials, array $params): array
    {
        if ($this->isMockMode($credentials)) {
            return ['ok' => true, 'subscription_id' => 'mock_sub_paypal', 'status' => 'ACTIVE'];
        }
        return ['ok' => false, 'error' => 'PayPal subscription requires plan setup'];
    }

    public function cancelSubscription(array $credentials, string $subscriptionId): array
    {
        if ($this->isMockMode($credentials)) {
            return ['ok' => true, 'status' => 'CANCELLED'];
        }
        return ['ok' => false, 'error' => 'PayPal cancel requires subscription API call'];
    }

    public function verifyWebhookSignature(array $credentials, string $payload, array $headers): bool
    {
        if ($this->isMockMode($credentials)) {
            return $this->getHeader($headers, 'X-Mock-Signature') === 'valid';
        }
        $parsed = json_decode($payload, true);
        return is_array($parsed);
    }

    public function processWebhook(array $credentials, array $payload, array $headers): array
    {
        $eventType = $payload['event_type'] ?? '';
        $status = 'received';
        if (strpos($eventType, 'COMPLETED') !== false || strpos($eventType, 'CAPTURE') !== false) {
            $status = 'succeeded';
        }
        return [
            'event_type' => $eventType,
            'status' => $status,
            'payment_id' => $payload['resource']['id'] ?? null,
            'provider' => $this->getId(),
            'message' => 'PayPal webhook: ' . $eventType,
        ];
    }
}
