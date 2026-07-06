<?php
/**
 * Persists product import runs — success, partial, and failed.
 */

class ImportHistoryStore
{
    private string $file;

    public function __construct(?string $dataDir = null)
    {
        $dir = $dataDir ?? commerce_data_dir();
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $this->file = $dir . '/import_history.json';
    }

    /** @return list<array<string, mixed>> */
    public function list(int $limit = 50): array
    {
        $items = $this->readAll();
        $items = array_reverse($items);
        return array_slice($items, 0, max(1, $limit));
    }

    public function get(string $id): ?array
    {
        foreach ($this->readAll() as $item) {
            if (($item['id'] ?? '') === $id) {
                return $item;
            }
        }
        return null;
    }

    /** @param array<string, mixed> $patch */
    public function update(string $id, array $patch): ?array
    {
        $items = $this->readAll();
        $updated = null;
        foreach ($items as $index => $item) {
            if (($item['id'] ?? '') !== $id) {
                continue;
            }
            $items[$index] = array_merge($item, $patch);
            $updated = $items[$index];
            break;
        }
        if ($updated === null) {
            return null;
        }

        file_put_contents($this->file, json_encode(array_values($items), JSON_PRETTY_PRINT));

        return $updated;
    }

    /** @param array<string, mixed> $record */
    public function add(array $record): array
    {
        $record['id'] = $record['id'] ?? ('imp_' . bin2hex(random_bytes(6)));
        $record['at'] = $record['at'] ?? date('c');

        $items = $this->readAll();
        $items[] = $record;
        if (count($items) > 200) {
            $items = array_slice($items, -200);
        }

        file_put_contents($this->file, json_encode(array_values($items), JSON_PRETTY_PRINT));
        return $record;
    }

    /** @return list<array<string, mixed>> */
    private function readAll(): array
    {
        $items = [];
        if (is_file($this->file)) {
            $data = json_decode((string) file_get_contents($this->file), true);
            if (is_array($data)) {
                $items = $data;
            }
        }

        // One-time migration from legacy import_log.json
        $legacyFile = dirname($this->file) . '/import_log.json';
        if (is_file($legacyFile)) {
            $legacy = json_decode((string) file_get_contents($legacyFile), true);
            if (is_array($legacy)) {
                foreach ($legacy as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $items[] = [
                        'id' => 'legacy_' . md5(json_encode($row)),
                        'at' => $row['at'] ?? date('c'),
                        'status' => 'success',
                        'preset' => $row['preset'] ?? 'generic',
                        'platform' => $row['platform'] ?? 'Import',
                        'collection' => $row['collection'] ?? 'products',
                        'currency' => $row['currency'] ?? 'eur',
                        'file_name' => $row['file_name'] ?? null,
                        'imported_count' => (int) ($row['count'] ?? count($row['ids'] ?? [])),
                        'updated_count' => 0,
                        'skipped_count' => 0,
                        'variant_count' => 0,
                        'total_rows' => (int) ($row['count'] ?? 0),
                        'errors' => [],
                        'entry_ids' => array_slice($row['ids'] ?? [], 0, 50),
                    ];
                }
            }
            @unlink($legacyFile);
            file_put_contents($this->file, json_encode(array_values($items), JSON_PRETTY_PRINT));
        }

        return $items;
    }
}
