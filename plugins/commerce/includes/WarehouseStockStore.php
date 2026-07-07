<?php
/**
 * Single-warehouse SKU stock (P14-M3).
 */

class WarehouseStockStore
{
    private string $stockFile;

    public function __construct(?string $dataDir = null)
    {
        $dir = $dataDir ?? commerce_data_dir();
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $this->stockFile = $dir . '/warehouse_stock.json';
        if (!is_file($this->stockFile)) {
            file_put_contents($this->stockFile, json_encode(['skus' => []], JSON_PRETTY_PRINT));
        }
    }

    /** @return array<string, mixed> */
    public function load(): array
    {
        $data = json_decode((string) file_get_contents($this->stockFile), true);
        return is_array($data) ? $data : ['skus' => []];
    }

    /** @param array<string, mixed> $data */
    public function save(array $data): void
    {
        $data['updated_at'] = date('c');
        file_put_contents($this->stockFile, json_encode($data, JSON_PRETTY_PRINT));
    }

    public function getBySku(string $sku): ?array
    {
        $sku = $this->normalizeSku($sku);
        if ($sku === '') {
            return null;
        }
        $skus = $this->load()['skus'] ?? [];
        return is_array($skus[$sku] ?? null) ? $skus[$sku] : null;
    }

    /** @return list<array<string, mixed>> */
    public function listAll(): array
    {
        $skus = $this->load()['skus'] ?? [];
        $rows = array_values(is_array($skus) ? $skus : []);
        usort($rows, fn ($a, $b) => strcmp($a['sku'] ?? '', $b['sku'] ?? ''));

        return $rows;
    }

    public function set(string $sku, int $quantityOnHand, ?int $lowStockThreshold = null): array
    {
        $sku = $this->normalizeSku($sku);
        if ($sku === '') {
            throw new InvalidArgumentException('SKU is required');
        }
        $data = $this->load();
        $existing = is_array($data['skus'][$sku] ?? null) ? $data['skus'][$sku] : [];
        $row = [
            'sku' => $sku,
            'quantity_on_hand' => max(0, $quantityOnHand),
            'low_stock_threshold' => $lowStockThreshold !== null
                ? max(0, $lowStockThreshold)
                : (int) ($existing['low_stock_threshold'] ?? 0),
            'updated_at' => date('c'),
        ];
        $data['skus'][$sku] = $row;
        $this->save($data);

        return $row;
    }

    public function hasSufficient(string $sku, int $qty): bool
    {
        $row = $this->getBySku($sku);
        if ($row === null) {
            return true;
        }

        return (int) ($row['quantity_on_hand'] ?? 0) >= max(1, $qty);
    }

    public function decrement(string $sku, int $qty): bool
    {
        $sku = $this->normalizeSku($sku);
        $row = $this->getBySku($sku);
        if ($row === null) {
            return true;
        }
        $qty = max(1, $qty);
        $onHand = (int) ($row['quantity_on_hand'] ?? 0);
        if ($onHand < $qty) {
            return false;
        }
        $this->set($sku, $onHand - $qty, (int) ($row['low_stock_threshold'] ?? 0));

        return true;
    }

    public function increment(string $sku, int $qty): bool
    {
        $sku = $this->normalizeSku($sku);
        $row = $this->getBySku($sku);
        if ($row === null) {
            return true;
        }
        $qty = max(1, $qty);
        $onHand = (int) ($row['quantity_on_hand'] ?? 0);
        $this->set($sku, $onHand + $qty, (int) ($row['low_stock_threshold'] ?? 0));

        return true;
    }

    private function normalizeSku(string $sku): string
    {
        return strtoupper(trim($sku));
    }
}
