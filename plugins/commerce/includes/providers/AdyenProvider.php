<?php
require_once __DIR__ . '/../AbstractProvider.php';

class AdyenProvider extends AbstractPaymentProvider
{
    public function getId(): string { return 'adyen'; }
    public function getName(): string { return 'Adyen'; }

    public function getCapabilities(): array
    {
        return [
            'regions' => ['EU', 'US', 'APAC', '*'],
            'currencies' => ['eur', 'usd', 'gbp', 'jpy', 'aud', 'sgd'],
            'methods' => ['card', 'ideal', 'applepay', 'googlepay', 'klarna'],
            'features' => ['capture' => true, 'refunds' => true, 'subscriptions' => false, 'webhooks' => true],
        ];
    }

    private function apiBase(array $credentials): string
    {
        return ($credentials['environment'] ?? 'test') === 'live'
            ? 'https://checkout-live.adyen.com/v71'
            : 'https://checkout-test.adyen.com/v71';
    }

    public function testConnection(array $credentials): array
    {
        if ($this->isMockMode($credentials)) {
            return ['ok' => true, 'message' => 'Mock connection successful'];
        }
        return !empty($credentials['api_key'])
            ? ['ok' => true, 'message' => 'Adyen API key present']
            : ['ok' => false, 'error' => 'Missing API key'];
    }

    public function createPayment(array $credentials, array $params): array
    {
        if ($this->isMockMode($credentials)) {
            return $this->mockPaymentResponse($params, $this->getId());
        }
        $body = [
            'amount' => ['currency' => strtoupper($params['currency'] ?? 'EUR'), 'value' => (int) ($params['amount_cents'] ?? 0)],
            'reference' => $params['reference'] ?? ('ref_' . bin2hex(random_bytes(4))),
            'merchantAccount' => $credentials['merchant_account'] ?? '',
            'returnUrl' => $params['return_url'] ?? 'https://example.com/return',
        ];
        $resp = $this->httpRequest('POST', $this->apiBase($credentials) . '/payments', [
            'headers' => ['X-API-Key: ' . ($credentials['api_key'] ?? '')],
            'body' => $body,
        ]);
        if (!$resp['ok']) {
            return ['ok' => false, 'error' => $resp['error'] ?? 'Adyen payment failed'];
        }
        return [
            'ok' => true,
            'payment_id' => $resp['data']['pspReference'] ?? null,
            'status' => $resp['data']['resultCode'] ?? 'Pending',
            'action' => $resp['data']['action'] ?? null,
            'raw' => $resp['data'],
        ];
    }

    public function capturePayment(array $credentials, string $paymentId, array $params = []): array
    {
        if ($this->isMockMode($credentials)) {
            return ['ok' => true, 'status' => 'Authorised'];
        }
        $body = [
            'merchantAccount' => $credentials['merchant_account'] ?? '',
            'amount' => ['currency' => strtoupper($params['currency'] ?? 'EUR'), 'value' => (int) ($params['amount_cents'] ?? 0)],
        ];
        $resp = $this->httpRequest('POST', $this->apiBase($credentials) . '/payments/' . $paymentId . '/captures', [
            'headers' => ['X-API-Key: ' . ($credentials['api_key'] ?? '')],
            'body' => $body,
        ]);
        return $resp['ok']
            ? ['ok' => true, 'status' => 'Captured', 'raw' => $resp['data']]
            : ['ok' => false, 'error' => $resp['error'] ?? 'Capture failed'];
    }

    public function refundPayment(array $credentials, string $paymentId, ?int $amountCents = null, array $params = []): array
    {
        if ($this->isMockMode($credentials)) {
            return ['ok' => true, 'status' => 'refunded'];
        }
        $body = [
            'merchantAccount' => $credentials['merchant_account'] ?? '',
            'amount' => ['currency' => strtoupper($params['currency'] ?? 'EUR'), 'value' => $amountCents ?? 0],
        ];
        $resp = $this->httpRequest('POST', $this->apiBase($credentials) . '/payments/' . $paymentId . '/refunds', [
            'headers' => ['X-API-Key: ' . ($credentials['api_key'] ?? '')],
            'body' => $body,
        ]);
        return $resp['ok']
            ? ['ok' => true, 'status' => 'refunded', 'raw' => $resp['data']]
            : ['ok' => false, 'error' => $resp['error'] ?? 'Refund failed'];
    }

    public function getPaymentStatus(array $credentials, string $paymentId): array
    {
        if ($this->isMockMode($credentials)) {
            return ['ok' => true, 'status' => 'Authorised'];
        }
        return ['ok' => true, 'status' => 'unknown', 'payment_id' => $paymentId];
    }

    public function createSubscription(array $credentials, array $params): array
    {
        return ['ok' => false, 'error' => 'Adyen subscriptions not supported in this adapter'];
    }

    public function cancelSubscription(array $credentials, string $subscriptionId): array
    {
        return ['ok' => false, 'error' => 'Adyen subscriptions not supported'];
    }

    public function verifyWebhookSignature(array $credentials, string $payload, array $headers): bool
    {
        if ($this->isMockMode($credentials)) {
            return $this->getHeader($headers, 'X-Mock-Signature') === 'valid';
        }
        $hmacKey = $credentials['hmac_key'] ?? '';
        if ($hmacKey === '') {
            return is_array(json_decode($payload, true));
        }
        $sig = $this->getHeader($headers, 'HmacSignature') ?? '';
        $expected = base64_encode(hash_hmac('sha256', $payload, pack('H*', $hmacKey), true));
        return hash_equals($expected, $sig);
    }

    public function processWebhook(array $credentials, array $payload, array $headers): array
    {
        $eventCode = $payload['notificationItems'][0]['NotificationRequestItem']['eventCode'] ?? ($payload['eventCode'] ?? '');
        $status = in_array($eventCode, ['AUTHORISATION', 'CAPTURE'], true) ? 'succeeded' : 'received';
        return [
            'event_type' => $eventCode,
            'status' => $status,
            'payment_id' => $payload['notificationItems'][0]['NotificationRequestItem']['pspReference'] ?? null,
            'provider' => $this->getId(),
            'message' => 'Adyen webhook: ' . $eventCode,
        ];
    }
}
