<?php
/**
 * Mollie payment provider adapter.
 */

require_once __DIR__ . '/../AbstractProvider.php';

class MollieProvider extends AbstractPaymentProvider
{
    public function getId(): string { return 'mollie'; }
    public function getName(): string { return 'Mollie'; }

    public function getCapabilities(): array
    {
        return [
            'regions' => ['NL', 'BE', 'DE', 'AT', 'FR', 'GB', 'EU'],
            'currencies' => ['eur', 'gbp', 'usd', 'chf', 'sek', 'nok', 'dkk', 'pln'],
            'methods' => ['ideal', 'bancontact', 'creditcard', 'sepa', 'paypal', 'applepay'],
            'features' => [
                'capture' => false,
                'refunds' => true,
                'subscriptions' => true,
                'webhooks' => true,
            ],
        ];
    }

    private function apiBase(): string
    {
        return 'https://api.mollie.com/v2';
    }

    private function token(array $credentials): string
    {
        return $credentials['api_key'] ?? $credentials['access_token'] ?? '';
    }

    public function testConnection(array $credentials): array
    {
        if ($this->isMockMode($credentials)) {
            return ['ok' => true, 'message' => 'Mock connection successful'];
        }
        $resp = $this->httpRequest('GET', $this->apiBase() . '/payments?limit=1', [
            'auth' => $this->token($credentials),
            'headers' => ['Accept: application/json'],
        ]);
        return $resp['ok'] || $resp['status'] === 200
            ? ['ok' => true, 'message' => 'Mollie connection verified']
            : ['ok' => false, 'error' => $resp['error'] ?? 'Connection failed'];
    }

    public function createPayment(array $credentials, array $params): array
    {
        if ($this->isMockMode($credentials)) {
            return $this->mockPaymentResponse($params, $this->getId());
        }

        $body = [
            'amount' => [
                'currency' => strtoupper($params['currency'] ?? 'EUR'),
                'value' => number_format(($params['amount_cents'] ?? 0) / 100, 2, '.', ''),
            ],
            'description' => $params['description'] ?? 'Payment',
            'redirectUrl' => $params['return_url'] ?? $params['redirect_url'] ?? 'https://example.com/return',
        ];
        if (!empty($params['method'])) {
            $body['method'] = $params['method'];
        }
        if (!empty($params['webhook_url'])) {
            $body['webhookUrl'] = $params['webhook_url'];
        }

        $resp = $this->httpRequest('POST', $this->apiBase() . '/payments', [
            'auth' => $this->token($credentials),
            'headers' => ['Accept: application/json'],
            'body' => $body,
        ]);

        if (!$resp['ok']) {
            return ['ok' => false, 'error' => $resp['error'] ?? 'Mollie payment failed'];
        }

        $data = $resp['data'];
        return [
            'ok' => true,
            'payment_id' => $data['id'] ?? null,
            'status' => $data['status'] ?? 'open',
            'redirect_url' => $data['_links']['checkout']['href'] ?? null,
            'raw' => $data,
        ];
    }

    public function capturePayment(array $credentials, string $paymentId, array $params = []): array
    {
        return ['ok' => false, 'error' => 'Mollie does not support separate capture'];
    }

    public function refundPayment(array $credentials, string $paymentId, ?int $amountCents = null, array $params = []): array
    {
        if ($this->isMockMode($credentials)) {
            return ['ok' => true, 'status' => 'refunded', 'refund_id' => 'mock_refund_' . $paymentId];
        }
        $body = [];
        if ($amountCents !== null) {
            $body['amount'] = [
                'currency' => strtoupper($params['currency'] ?? 'EUR'),
                'value' => number_format($amountCents / 100, 2, '.', ''),
            ];
        }
        $resp = $this->httpRequest('POST', $this->apiBase() . '/payments/' . $paymentId . '/refunds', [
            'auth' => $this->token($credentials),
            'headers' => ['Accept: application/json'],
            'body' => $body,
        ]);
        return $resp['ok']
            ? ['ok' => true, 'status' => 'refunded', 'refund_id' => $resp['data']['id'] ?? null, 'raw' => $resp['data']]
            : ['ok' => false, 'error' => $resp['error'] ?? 'Refund failed'];
    }

    public function getPaymentStatus(array $credentials, string $paymentId): array
    {
        if ($this->isMockMode($credentials)) {
            return ['ok' => true, 'status' => 'paid', 'payment_id' => $paymentId];
        }
        $resp = $this->httpRequest('GET', $this->apiBase() . '/payments/' . $paymentId, [
            'auth' => $this->token($credentials),
            'headers' => ['Accept: application/json'],
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
        $body = [
            'amount' => [
                'currency' => strtoupper($params['currency'] ?? 'EUR'),
                'value' => number_format(($params['amount_cents'] ?? 0) / 100, 2, '.', ''),
            ],
            'interval' => $params['interval'] ?? '1 month',
            'description' => $params['description'] ?? 'Subscription',
        ];
        $resp = $this->httpRequest('POST', $this->apiBase() . '/subscriptions', [
            'auth' => $this->token($credentials),
            'headers' => ['Accept: application/json'],
            'body' => $body,
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
        $resp = $this->httpRequest('DELETE', $this->apiBase() . '/subscriptions/' . $subscriptionId, [
            'auth' => $this->token($credentials),
            'headers' => ['Accept: application/json'],
        ]);
        return $resp['ok']
            ? ['ok' => true, 'status' => 'cancelled']
            : ['ok' => false, 'error' => $resp['error'] ?? 'Cancel failed'];
    }

    public function verifyWebhookSignature(array $credentials, string $payload, array $headers): bool
    {
        if ($this->isMockMode($credentials)) {
            return ($this->getHeader($headers, 'X-Mock-Signature') === 'valid');
        }
        // Mollie recommends verifying by fetching payment status; accept if webhook secret matches header or payload is valid JSON with id
        $parsed = json_decode($payload, true);
        return is_array($parsed) && !empty($parsed['id']);
    }

    public function processWebhook(array $credentials, array $payload, array $headers): array
    {
        $paymentId = $payload['id'] ?? null;
        $status = $payload['status'] ?? 'received';

        if ($this->isMockMode($credentials)) {
            return [
                'event_type' => 'payment.' . $status,
                'status' => $status === 'paid' ? 'succeeded' : $status,
                'payment_id' => $paymentId,
                'provider' => $this->getId(),
                'message' => 'Mollie webhook processed',
            ];
        }

        if ($paymentId && !$this->isMockMode($credentials)) {
            $check = $this->getPaymentStatus($credentials, $paymentId);
            if ($check['ok']) {
                $status = $check['status'];
            }
        }

        $normalized = $status === 'paid' ? 'succeeded' : $status;
        return [
            'event_type' => 'payment.' . $status,
            'status' => $normalized,
            'payment_id' => $paymentId,
            'provider' => $this->getId(),
            'message' => 'Mollie webhook: ' . $status,
        ];
    }
}
