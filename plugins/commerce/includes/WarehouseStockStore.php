<?php
/**
 * Multi-warehouse SKU stock (P14-M3 single warehouse, P17-M1 multi-warehouse).
 */

class WarehouseStockStore
{
    private string $stockFile;

    public const DEFAULT_WAREHOUSE_ID = 'wh-default';

    public function __construct(?string $dataDir = null)
    {
        $dir = $dataDir ?? commerce_data_dir();
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $this->stockFile = $dir . '/warehouse_stock.json';
        if (!is_file($this->stockFile)) {
            $this->save($this->defaultData());
        } else {
            $this->migrateIfNeeded();
        }
    }

    /** @return array<string, mixed> */
    public function load(): array
    {
        $data = json_decode((string) file_get_contents($this->stockFile), true);
        if (!is_array($data)) {
            return $this->defaultData();
        }
        $this->migrateDataInPlace($data);

        return $data;
    }

    /** @param array<string, mixed> $data */
    public function save(array $data): void
    {
        $data['updated_at'] = date('c');
        $data['schema_version'] = 2;
        file_put_contents($this->stockFile, json_encode($data, JSON_PRETTY_PRINT));
    }

    /** @return list<array<string, mixed>> */
    public function listWarehouses(): array
    {
        $warehouses = $this->load()['warehouses'] ?? [];
        $rows = array_values(is_array($warehouses) ? $warehouses : []);
        usort($rows, fn ($a, $b) => ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0))
            ?: strcmp($a['name'] ?? '', $b['name'] ?? ''));

        return $rows;
    }

    public function getWarehouse(string $warehouseId): ?array
    {
        foreach ($this->listWarehouses() as $warehouse) {
            if (($warehouse['id'] ?? '') === $warehouseId) {
                return $warehouse;
            }
        }

        return null;
    }

    public function createWarehouse(string $name, ?int $sortOrder = null): array
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('Warehouse name is required');
        }
        $data = $this->load();
        $warehouses = is_array($data['warehouses'] ?? null) ? $data['warehouses'] : [];
        $id = 'wh-' . substr(bin2hex(random_bytes(4)), 0, 8);
        if ($sortOrder === null) {
            $sortOrder = count($warehouses);
        }
        $row = [
            'id' => $id,
            'name' => $name,
            'sort_order' => max(0, $sortOrder),
            'created_at' => date('c'),
        ];
        $warehouses[] = $row;
        $data['warehouses'] = $warehouses;
        $this->save($data);

        return $row;
    }

    public function updateWarehouse(string $warehouseId, string $name, ?int $sortOrder = null): array
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('Warehouse name is required');
        }
        $data = $this->load();
        $found = false;
        foreach ($data['warehouses'] ?? [] as &$warehouse) {
            if (($warehouse['id'] ?? '') !== $warehouseId) {
                continue;
            }
            $warehouse['name'] = $name;
            if ($sortOrder !== null) {
                $warehouse['sort_order'] = max(0, $sortOrder);
            }
            $warehouse['updated_at'] = date('c');
            $row = $warehouse;
            $found = true;
            break;
        }
        unset($warehouse);
        if (!$found) {
            throw new InvalidArgumentException('Warehouse not found');
        }
        $this->save($data);

        return $row;
    }

    public function deleteWarehouse(string $warehouseId): bool
    {
        if ($warehouseId === self::DEFAULT_WAREHOUSE_ID) {
            throw new InvalidArgumentException('Cannot delete the default warehouse');
        }
        $data = $this->load();
        $stock = is_array($data['stock'] ?? null) ? $data['stock'] : [];
        foreach ($stock as $row) {
            if (is_array($row) && ($row['warehouse_id'] ?? '') === $warehouseId && (int) ($row['quantity_on_hand'] ?? 0) > 0) {
                throw new InvalidArgumentException('Warehouse has stock on hand');
            }
        }
        $before = count($data['warehouses'] ?? []);
        $data['warehouses'] = array_values(array_filter(
            $data['warehouses'] ?? [],
            fn ($w) => is_array($w) && ($w['id'] ?? '') !== $warehouseId
        ));
        if (count($data['warehouses']) === $before) {
            return false;
        }
        $data['stock'] = array_values(array_filter(
            $stock,
            fn ($row) => is_array($row) && ($row['warehouse_id'] ?? '') !== $warehouseId
        ));
        $this->save($data);

        return true;
    }

    public function getBySku(string $sku, ?string $warehouseId = null): ?array
    {
        $sku = $this->normalizeSku($sku);
        if ($sku === '') {
            return null;
        }
        if ($warehouseId !== null) {
            return $this->getStockRow($sku, $warehouseId);
        }

        $rows = $this->getSkuRows($sku);
        if (empty($rows)) {
            return null;
        }
        $total = 0;
        $threshold = 0;
        foreach ($rows as $row) {
            $total += (int) ($row['quantity_on_hand'] ?? 0);
            $threshold = max($threshold, (int) ($row['low_stock_threshold'] ?? 0));
        }

        return [
            'sku' => $sku,
            'quantity_on_hand' => $total,
            'low_stock_threshold' => $threshold,
            'updated_at' => $rows[0]['updated_at'] ?? date('c'),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function listAll(?string $warehouseId = null): array
    {
        $stock = $this->load()['stock'] ?? [];
        $rows = [];
        foreach (is_array($stock) ? $stock : [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            if ($warehouseId !== null && ($row['warehouse_id'] ?? '') !== $warehouseId) {
                continue;
            }
            $rows[] = $row;
        }
        usort($rows, fn ($a, $b) => strcmp($a['sku'] ?? '', $b['sku'] ?? '')
            ?: strcmp($a['warehouse_id'] ?? '', $b['warehouse_id'] ?? ''));

        return $rows;
    }

    public function set(string $sku, int $quantityOnHand, ?int $lowStockThreshold = null, ?string $warehouseId = null): array
    {
        $sku = $this->normalizeSku($sku);
        if ($sku === '') {
            throw new InvalidArgumentException('SKU is required');
        }
        $warehouseId = $this->resolveWarehouseId($warehouseId);
        $data = $this->load();
        $existing = $this->findStockIndex($data, $sku, $warehouseId);
        $prev = $existing >= 0 ? ($data['stock'][$existing] ?? []) : [];
        $row = [
            'warehouse_id' => $warehouseId,
            'sku' => $sku,
            'quantity_on_hand' => max(0, $quantityOnHand),
            'low_stock_threshold' => $lowStockThreshold !== null
                ? max(0, $lowStockThreshold)
                : (int) ($prev['low_stock_threshold'] ?? 0),
            'updated_at' => date('c'),
        ];
        if ($existing >= 0) {
            $data['stock'][$existing] = $row;
        } else {
            $data['stock'][] = $row;
        }
        $this->save($data);

        return $row;
    }

    public function hasSufficient(string $sku, int $qty): bool
    {
        if (!$this->tracksSku($sku)) {
            return true;
        }

        return $this->allocateWarehouse($sku, $qty) !== null;
    }

    public function tracksSku(string $sku): bool
    {
        return !empty($this->getSkuRows($this->normalizeSku($sku)));
    }

    public function allocateWarehouse(string $sku, int $qty): ?string
    {
        $sku = $this->normalizeSku($sku);
        if ($sku === '') {
            return null;
        }
        $qty = max(1, $qty);
        $rows = $this->getSkuRows($sku);
        if (empty($rows)) {
            return null;
        }
        $order = $this->warehouseSortOrder();
        foreach ($order as $warehouseId) {
            foreach ($rows as $row) {
                if (($row['warehouse_id'] ?? '') !== $warehouseId) {
                    continue;
                }
                if ((int) ($row['quantity_on_hand'] ?? 0) >= $qty) {
                    return $warehouseId;
                }
            }
        }

        return null;
    }

    public function decrement(string $sku, int $qty, ?string $warehouseId = null): bool
    {
        $sku = $this->normalizeSku($sku);
        $qty = max(1, $qty);
        if ($warehouseId === null) {
            $warehouseId = $this->allocateWarehouse($sku, $qty);
            if ($warehouseId === null) {
                $row = $this->getBySku($sku);
                if ($row === null) {
                    return true;
                }

                return false;
            }
        }
        $row = $this->getStockRow($sku, $warehouseId);
        if ($row === null) {
            return $this->getBySku($sku) === null;
        }
        $onHand = (int) ($row['quantity_on_hand'] ?? 0);
        if ($onHand < $qty) {
            return false;
        }
        $this->set($sku, $onHand - $qty, (int) ($row['low_stock_threshold'] ?? 0), $warehouseId);

        return true;
    }

    public function increment(string $sku, int $qty, ?string $warehouseId = null): bool
    {
        $sku = $this->normalizeSku($sku);
        $qty = max(1, $qty);
        $warehouseId = $warehouseId ?? $this->allocateWarehouse($sku, 1) ?? self::DEFAULT_WAREHOUSE_ID;
        $row = $this->getStockRow($sku, $warehouseId);
        if ($row === null && $this->getBySku($sku) === null) {
            return true;
        }
        $onHand = (int) ($row['quantity_on_hand'] ?? 0);
        $this->set($sku, $onHand + $qty, (int) ($row['low_stock_threshold'] ?? 0), $warehouseId);

        return true;
    }

    public function transfer(string $sku, int $qty, string $fromWarehouseId, string $toWarehouseId): array
    {
        $sku = $this->normalizeSku($sku);
        $qty = max(1, $qty);
        if ($fromWarehouseId === $toWarehouseId) {
            throw new InvalidArgumentException('Source and destination warehouses must differ');
        }
        if ($this->getWarehouse($fromWarehouseId) === null || $this->getWarehouse($toWarehouseId) === null) {
            throw new InvalidArgumentException('Warehouse not found');
        }
        if (!$this->decrement($sku, $qty, $fromWarehouseId)) {
            throw new InvalidArgumentException('Insufficient stock in source warehouse');
        }
        $this->increment($sku, $qty, $toWarehouseId);

        return [
            'ok' => true,
            'sku' => $sku,
            'quantity' => $qty,
            'from_warehouse_id' => $fromWarehouseId,
            'to_warehouse_id' => $toWarehouseId,
        ];
    }

    /** @return array<string, int> */
    private function warehouseSortOrder(): array
    {
        $order = [];
        foreach ($this->listWarehouses() as $warehouse) {
            $order[$warehouse['id'] ?? ''] = (int) ($warehouse['sort_order'] ?? 0);
        }

        return array_keys($order);
    }

    /** @return list<array<string, mixed>> */
    private function getSkuRows(string $sku): array
    {
        $rows = [];
        foreach ($this->load()['stock'] ?? [] as $row) {
            if (is_array($row) && strtoupper((string) ($row['sku'] ?? '')) === $sku) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function getStockRow(string $sku, string $warehouseId): ?array
    {
        foreach ($this->getSkuRows($sku) as $row) {
            if (($row['warehouse_id'] ?? '') === $warehouseId) {
                return $row;
            }
        }

        return null;
    }

    private function resolveWarehouseId(?string $warehouseId): string
    {
        if ($warehouseId !== null && $warehouseId !== '') {
            if ($this->getWarehouse($warehouseId) === null) {
                throw new InvalidArgumentException('Warehouse not found');
            }

            return $warehouseId;
        }
        $warehouses = $this->listWarehouses();

        return $warehouses[0]['id'] ?? self::DEFAULT_WAREHOUSE_ID;
    }

    /** @param array<string, mixed> $data */
    private function findStockIndex(array $data, string $sku, string $warehouseId): int
    {
        foreach ($data['stock'] ?? [] as $idx => $row) {
            if (is_array($row)
                && strtoupper((string) ($row['sku'] ?? '')) === $sku
                && ($row['warehouse_id'] ?? '') === $warehouseId) {
                return (int) $idx;
            }
        }

        return -1;
    }

    /** @return array<string, mixed> */
    private function defaultData(): array
    {
        return [
            'schema_version' => 2,
            'warehouses' => [[
                'id' => self::DEFAULT_WAREHOUSE_ID,
                'name' => 'Default',
                'sort_order' => 0,
                'created_at' => date('c'),
            ]],
            'stock' => [],
            'updated_at' => date('c'),
        ];
    }

    private function migrateIfNeeded(): void
    {
        $data = json_decode((string) file_get_contents($this->stockFile), true);
        if (!is_array($data)) {
            $this->save($this->defaultData());

            return;
        }
        if ($this->migrateDataInPlace($data)) {
            $this->save($data);
        }
    }

    /** @param array<string, mixed> $data */
    private function migrateDataInPlace(array &$data): bool
    {
        if (($data['schema_version'] ?? 1) >= 2 && isset($data['warehouses'], $data['stock'])) {
            if (empty($data['warehouses'])) {
                $data['warehouses'] = $this->defaultData()['warehouses'];
            }

            return false;
        }
        $changed = true;
        $data['schema_version'] = 2;
        $data['warehouses'] = [[
            'id' => self::DEFAULT_WAREHOUSE_ID,
            'name' => 'Default',
            'sort_order' => 0,
            'created_at' => date('c'),
        ]];
        $stock = [];
        $legacySkus = is_array($data['skus'] ?? null) ? $data['skus'] : [];
        foreach ($legacySkus as $sku => $row) {
            if (!is_array($row)) {
                continue;
            }
            $stock[] = [
                'warehouse_id' => self::DEFAULT_WAREHOUSE_ID,
                'sku' => strtoupper(trim((string) ($row['sku'] ?? $sku))),
                'quantity_on_hand' => (int) ($row['quantity_on_hand'] ?? 0),
                'low_stock_threshold' => (int) ($row['low_stock_threshold'] ?? 0),
                'updated_at' => $row['updated_at'] ?? date('c'),
            ];
        }
        $data['stock'] = $stock;
        unset($data['skus']);

        return $changed;
    }

    private function normalizeSku(string $sku): string
    {
        return strtoupper(trim($sku));
    }
}
