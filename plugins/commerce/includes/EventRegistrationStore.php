<?php
/**
 * Event registration records for fulfillment: register.
 */

class EventRegistrationStore
{
    private string $file;

    public function __construct(?string $dataDir = null)
    {
        $dir = $dataDir ?? commerce_data_dir();
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $this->file = $dir . '/registrations.json';
        if (!is_file($this->file)) {
            file_put_contents($this->file, json_encode(['registrations' => []], JSON_PRETTY_PRINT));
        }
    }

    public function load(): array
    {
        $data = json_decode((string) file_get_contents($this->file), true);
        return is_array($data) ? $data : ['registrations' => []];
    }

    public function save(array $data): void
    {
        file_put_contents($this->file, json_encode($data, JSON_PRETTY_PRINT));
    }

    public function register(array $order, array $line, int $lineIndex, ?string $attendeeName = null): array
    {
        $orderId = $order['id'] ?? '';
        $lineId = $line['line_id'] ?? ($orderId . ':' . $lineIndex);
        $key = $orderId . ':' . $lineId;

        $data = $this->load();
        if (isset($data['registrations'][$key])) {
            return $data['registrations'][$key];
        }

        $record = [
            'order_id' => $orderId,
            'line_id' => $lineId,
            'entry_id' => $line['entry_id'] ?? '',
            'collection_slug' => $line['collection_slug'] ?? '',
            'customer_email' => $order['customer_email'] ?? '',
            'attendee_name' => $attendeeName ?? ($order['attendee_name'] ?? ''),
            'quantity' => (int) ($line['quantity'] ?? 1),
            'registered_at' => date('c'),
        ];

        $data['registrations'][$key] = $record;
        $this->save($data);
        return $record;
    }

    public function getByOrderLine(string $orderId, string $lineId): ?array
    {
        $data = $this->load();
        return $data['registrations'][$orderId . ':' . $lineId] ?? null;
    }

    public function listAll(): array
    {
        return array_values($this->load()['registrations'] ?? []);
    }
}
