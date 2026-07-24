<?php
/**
 * Manual fulfillment — export order JSON to data dir.
 */

class ManualFulfillmentAdapter implements FulfillmentAdapterInterface
{
    private string $exportDir;

    public function __construct(?string $exportDir = null)
    {
        $this->exportDir = $exportDir ?? commerce_data_dir() . '/fulfillment_exports';
        if (!is_dir($this->exportDir)) {
            mkdir($this->exportDir, 0755, true);
        }
    }

    public function getId(): string
    {
        return 'manual';
    }

    public function getName(): string
    {
        return 'Manual / Export';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function testConnection(): array
    {
        return ['ok' => true, 'message' => 'Manual export always available'];
    }

    public function pushOrder(array $order): array
    {
        $id = $order['id'] ?? ('export_' . time());
        $file = $this->exportDir . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $id) . '.json';
        file_put_contents($file, json_encode($order, JSON_PRETTY_PRINT));
        return ['ok' => true, 'export_path' => $file];
    }
}
