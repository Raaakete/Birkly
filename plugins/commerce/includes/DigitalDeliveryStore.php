<?php
/**
 * Digital delivery tokens for fulfillment: deliver.
 */

class DigitalDeliveryStore
{
    private string $file;
    private int $ttlSeconds;
    private int $maxDownloads;

    public function __construct(?string $dataDir = null, int $ttlSeconds = 604800, int $maxDownloads = 5)
    {
        $dir = $dataDir ?? commerce_data_dir();
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $this->file = $dir . '/digital_deliveries.json';
        $this->ttlSeconds = $ttlSeconds;
        $this->maxDownloads = $maxDownloads;
        if (!is_file($this->file)) {
            file_put_contents($this->file, json_encode(['tokens' => []], JSON_PRETTY_PRINT));
        }
    }

    public function load(): array
    {
        $data = json_decode((string) file_get_contents($this->file), true);
        return is_array($data) ? $data : ['tokens' => []];
    }

    public function save(array $data): void
    {
        file_put_contents($this->file, json_encode($data, JSON_PRETTY_PRINT));
    }

    public function createToken(array $order, array $line, int $lineIndex): array
    {
        $orderId = $order['id'] ?? '';
        $lineId = $line['line_id'] ?? ($orderId . ':' . $lineIndex);
        $key = $orderId . ':' . $lineId;

        $data = $this->load();
        if (isset($data['tokens'][$key])) {
            return $data['tokens'][$key];
        }

        $commerce = $line['commerce'] ?? [];
        $secret = commerce_get_setting('digital_delivery_secret', 'birkly-commerce-digital');
        $payload = $orderId . '|' . $lineId . '|' . time();
        $token = hash_hmac('sha256', $payload, (string) $secret);

        $record = [
            'token' => $token,
            'order_id' => $orderId,
            'line_id' => $lineId,
            'entry_id' => $line['entry_id'] ?? '',
            'collection_slug' => $line['collection_slug'] ?? '',
            'customer_email' => $order['customer_email'] ?? '',
            'delivery_asset' => $commerce['delivery_asset'] ?? null,
            'delivery_url' => $commerce['delivery_url'] ?? null,
            'delivery_mode' => $commerce['delivery_mode'] ?? 'secure_download',
            'expires_at' => date('c', time() + $this->ttlSeconds),
            'max_downloads' => $this->maxDownloads,
            'download_count' => 0,
            'created_at' => date('c'),
        ];

        $data['tokens'][$key] = $record;
        $this->save($data);
        return $record;
    }

    public function getByToken(string $token): ?array
    {
        $data = $this->load();
        foreach ($data['tokens'] ?? [] as $record) {
            if (($record['token'] ?? '') === $token) {
                if (strtotime($record['expires_at'] ?? '') < time()) {
                    return null;
                }
                return $record;
            }
        }
        return null;
    }

    public function getByOrderLine(string $orderId, string $lineId): ?array
    {
        $data = $this->load();
        return $data['tokens'][$orderId . ':' . $lineId] ?? null;
    }
}
