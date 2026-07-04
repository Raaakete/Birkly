<?php
/**
 * Flat-file order storage for Commerce plugin.
 */

class CommerceOrderStore
{
    private string $baseDir;

    public function __construct(?string $baseDir = null)
    {
        if ($baseDir !== null) {
            $this->baseDir = rtrim($baseDir, '/');
        } else {
            $this->baseDir = commerce_data_dir() . '/orders';
        }
        if (!is_dir($this->baseDir)) {
            mkdir($this->baseDir, 0755, true);
        }
    }

    public function getDataDir(): string
    {
        return $this->baseDir;
    }

    public function create(array $order): array
    {
        $id = $order['id'] ?? ('cord_' . bin2hex(random_bytes(8)));
        $now = date('c');
        $record = array_merge([
            'id' => $id,
            'status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
            'lines' => [],
            'total_cents' => 0,
            'discount_cents' => 0,
            'currency' => 'eur',
            'payment_id' => null,
            'provider' => null,
            'customer_email' => '',
            'coupon_code' => null,
            'affiliate_id' => null,
        ], $order);
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

    public function findByPaymentId(string $paymentId): ?array
    {
        foreach ($this->listAll() as $order) {
            if (($order['payment_id'] ?? '') === $paymentId) {
                return $order;
            }
        }
        return null;
    }

    public function listAll(): array
    {
        $orders = [];
        foreach (glob($this->baseDir . '/*.json') ?: [] as $file) {
            $data = json_decode((string) file_get_contents($file), true);
            if (is_array($data)) {
                $orders[] = $data;
            }
        }
        usort($orders, function ($a, $b) {
            return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
        });
        return $orders;
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
