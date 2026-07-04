<?php
require_once __DIR__ . '/../AbstractProvider.php';

class PayssionProvider extends AbstractPaymentProvider
{
    public function getId(): string { return 'payssion'; }
    public function getName(): string { return 'Payssion'; }

    public function getCapabilities(): array
    {
        return [
            'regions' => ['CN', 'HK', 'TW', 'SG', 'MY', 'TH', 'PH', 'ID', 'VN'],
            'currencies' => ['cny', 'usd', 'eur', 'hkd', 'sgd', 'myr', 'thb'],
            'methods' => ['alipay', 'wechat', 'unionpay', 'grabpay', 'fpx'],
            'features' => ['capture' => false, 'refunds' => true, 'subscriptions' => false, 'webhooks' => true],
        ];
    }

    private function apiBase(): string
    {
        return 'https://www.payssion.com/api/v1';
    }

    public function testConnection(array $credentials): array
    {
        if ($this->isMockMode($credentials)) {
            return ['ok' => true, 'message' => 'Mock connection successful'];
        }
        return !empty($credentials['api_key']) && !empty($credentials['secret_key'])
            ? ['ok' => true, 'message' => 'Payssion credentials present']
            : ['ok' => false, 'error' => 'Missing credentials'];
    }

    private function sign(array $params, string $secret): string
    {
        ksort($params);
        $parts = [];
        foreach ($params as $k => $v) {
            if ($v !== '' && $v !== null) {
                $parts[] = $k . '=' . $v;
            }
        }
        return md5(implode('|', $parts) . '|' . $secret);
    }

    public function createPayment(array $credentials, array $params): array
    {
        if ($this->isMockMode($credentials)) {
            return $this->mockPaymentResponse($params, $this->getId());
        }
        $fields = [
            'api_key' => $credentials['api_key'] ?? '',
            'pm_id' => $params['method'] ?? 'alipay_cn',
            'amount' => number_format(($params['amount_cents'] ?? 0) / 100, 2, '.', ''),
            'currency' => strtoupper($params['currency'] ?? 'CNY'),
            'description' => $params['description'] ?? 'Payment',
            'order_id' => $params['reference'] ?? ('ord_' . bin2hex(random_bytes(4))),
        ];
        $fields['api_sig'] = $this->sign($fields, $credentials['secret_key'] ?? '');

        $resp = $this->formRequest('POST', $this->apiBase() . '/payments', $fields);
        if (!$resp['ok']) {
            return ['ok' => false, 'error' => $resp['error'] ?? 'Payssion payment failed'];
        }
        $data = $resp['data'];
        if (is_array($data) && ($data['result_code'] ?? '') !== '200') {
            return ['ok' => false, 'error' => $data['description'] ?? 'Payment failed'];
        }
        $transaction = is_array($data) ? ($data['transaction'] ?? $data) : [];
        return [
            'ok' => true,
            'payment_id' => $transaction['transaction_id'] ?? ($transaction['order_id'] ?? null),
            'status' => $transaction['state'] ?? 'pending',
            'redirect_url' => $transaction['redirect_url'] ?? null,
            'raw' => $data,
        ];
    }

    public function capturePayment(array $credentials, string $paymentId, array $params = []): array
    {
        return ['ok' => false, 'error' => 'Payssion does not support separate capture'];
    }

    public function refundPayment(array $credentials, string $paymentId, ?int $amountCents = null, array $params = []): array
    {
        if ($this->isMockMode($credentials)) {
            return ['ok' => true, 'status' => 'refunded'];
        }
        $fields = [
            'api_key' => $credentials['api_key'] ?? '',
            'transaction_id' => $paymentId,
        ];
        if ($amountCents !== null) {
            $fields['amount'] = number_format($amountCents / 100, 2, '.', '');
        }
        $fields['api_sig'] = $this->sign($fields, $credentials['secret_key'] ?? '');
        $resp = $this->formRequest('POST', $this->apiBase() . '/refunds', $fields);
        return $resp['ok']
            ? ['ok' => true, 'status' => 'refunded', 'raw' => $resp['data']]
            : ['ok' => false, 'error' => $resp['error'] ?? 'Refund failed'];
    }

    public function getPaymentStatus(array $credentials, string $paymentId): array
    {
        if ($this->isMockMode($credentials)) {
            return ['ok' => true, 'status' => 'completed'];
        }
        $fields = [
            'api_key' => $credentials['api_key'] ?? '',
            'transaction_id' => $paymentId,
        ];
        $fields['api_sig'] = $this->sign($fields, $credentials['secret_key'] ?? '');
        $resp = $this->formRequest('POST', $this->apiBase() . '/payment/details', $fields);
        return $resp['ok']
            ? ['ok' => true, 'status' => $resp['data']['transaction']['state'] ?? 'unknown', 'raw' => $resp['data']]
            : ['ok' => false, 'error' => $resp['error'] ?? 'Status failed'];
    }

    public function createSubscription(array $credentials, array $params): array
    {
        return ['ok' => false, 'error' => 'Payssion subscriptions not supported'];
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
        $parsed = json_decode($payload, true);
        if (!is_array($parsed)) {
            parse_str($payload, $parsed);
        }
        if (!is_array($parsed) || empty($parsed['notify_sig'])) {
            return false;
        }
        $sig = $parsed['notify_sig'];
        unset($parsed['notify_sig']);
        return hash_equals($this->sign($parsed, $credentials['secret_key'] ?? ''), $sig);
    }

    public function processWebhook(array $credentials, array $payload, array $headers): array
    {
        if (!is_array($payload)) {
            parse_str((string) $payload, $payload);
        }
        $state = $payload['state'] ?? 'received';
        $normalized = in_array($state, ['completed', 'paid'], true) ? 'succeeded' : $state;
        return [
            'event_type' => 'payment.' . $state,
            'status' => $normalized,
            'payment_id' => $payload['transaction_id'] ?? null,
            'provider' => $this->getId(),
            'message' => 'Payssion webhook: ' . $state,
        ];
    }
}
