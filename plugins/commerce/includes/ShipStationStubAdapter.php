<?php
/**
 * ShipStation stub adapter — test connection without live API keys.
 */

class ShipStationStubAdapter implements FulfillmentAdapterInterface
{
    private array $config;

    public function __construct(?array $config = null)
    {
        if ($config !== null) {
            $this->config = $config;
        } else {
            $file = commerce_data_dir() . '/fulfillment_connections.json';
            $this->config = is_file($file)
                ? (json_decode((string) file_get_contents($file), true)['shipstation'] ?? [])
                : [];
        }
    }

    public function getId(): string
    {
        return 'shipstation';
    }

    public function getName(): string
    {
        return 'ShipStation (stub)';
    }

    public function isConfigured(): bool
    {
        return !empty($this->config['api_key']) || !empty($this->config['mock']);
    }

    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'error' => 'ShipStation not configured'];
        }
        if (!empty($this->config['mock'])) {
            return ['ok' => true, 'message' => 'Mock connection OK'];
        }
        return ['ok' => true, 'message' => 'Stub connection test passed (no live API call in v1)'];
    }

    public function pushOrder(array $order): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'error' => 'ShipStation not configured'];
        }
        return [
            'ok' => true,
            'provider' => 'shipstation',
            'order_id' => $order['id'] ?? '',
            'stub' => true,
        ];
    }
}
