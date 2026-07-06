<?php
/**
 * Flat-file subscription storage for Commerce plugin (P8-M4).
 * Mirrors CommerceOrderStore conventions.
 */

class CommerceSubscriptionStore
{
    private string $baseDir;

    public function __construct(?string $baseDir = null)
    {
        if ($baseDir !== null) {
            $this->baseDir = rtrim($baseDir, '/');
        } else {
            $this->baseDir = commerce_data_dir() . '/subscriptions';
        }
        if (!is_dir($this->baseDir)) {
            mkdir($this->baseDir, 0755, true);
        }
    }

    public function getDataDir(): string
    {
        return $this->baseDir;
    }

    public function create(array $subscription): array
    {
        $id = $subscription['id'] ?? ('csub_' . bin2hex(random_bytes(8)));
        $now = date('c');
        $record = array_merge([
            'id' => $id,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
            'order_id' => null,
            'payment_id' => null,
            'provider' => null,
            'provider_subscription_id' => null,
            'customer_email' => '',
            'interval' => 'month',
            'amount_cents' => 0,
            'currency' => 'eur',
            'lines' => [],
            'current_period_start' => $now,
            'next_billing_at' => null,
            'canceled_at' => null,
            'past_due_at' => null,
        ], $subscription);
        $record['id'] = $id;
        $this->write($id, $record);
        return $record;
    }

    public function get(string $id): ?array
    {
        $file = $this->path($id);
        if (!is_file($file)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($file), true);
        return is_array($data) ? $data : null;
    }

    public function update(string $id, array $patch): ?array
    {
        $existing = $this->get($id);
        if (!$existing) {
            return null;
        }
        $record = array_merge($existing, $patch, ['updated_at' => date('c')]);
        $this->write($id, $record);
        return $record;
    }

    public function findByOrderId(string $orderId): ?array
    {
        foreach ($this->listAll() as $sub) {
            if (($sub['order_id'] ?? '') === $orderId) {
                return $sub;
            }
        }
        return null;
    }

    public function findByPaymentId(string $paymentId): ?array
    {
        foreach ($this->listAll() as $sub) {
            if (($sub['payment_id'] ?? '') === $paymentId) {
                return $sub;
            }
        }
        return null;
    }

    public function findByProviderSubscriptionId(string $providerSubscriptionId): ?array
    {
        foreach ($this->listAll() as $sub) {
            if (($sub['provider_subscription_id'] ?? '') === $providerSubscriptionId) {
                return $sub;
            }
        }
        return null;
    }

    public function listAll(): array
    {
        $subs = [];
        foreach (glob($this->baseDir . '/*.json') ?: [] as $file) {
            $data = json_decode((string) file_get_contents($file), true);
            if (is_array($data)) {
                $subs[] = $data;
            }
        }
        usort($subs, function ($a, $b) {
            return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
        });
        return $subs;
    }

    private function path(string $id): string
    {
        return $this->baseDir . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $id) . '.json';
    }

    private function write(string $id, array $record): void
    {
        file_put_contents($this->path($id), json_encode($record, JSON_PRETTY_PRINT));
    }
}
