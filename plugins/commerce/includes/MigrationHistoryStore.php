<?php
/**
 * Persists Shop/Events → Commerce migration runs and source-key idempotency.
 */

class MigrationHistoryStore
{
    private string $file;

    public function __construct(?string $dataDir = null)
    {
        $dir = $dataDir ?? commerce_data_dir();
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $this->file = $dir . '/migration_history.json';
    }

    /** @return list<array<string, mixed>> */
    public function list(int $limit = 50): array
    {
        $items = $this->readRuns();
        $items = array_reverse($items);

        return array_slice($items, 0, max(1, $limit));
    }

    public function get(string $id): ?array
    {
        foreach ($this->readRuns() as $item) {
            if (($item['id'] ?? '') === $id) {
                return $item;
            }
        }

        return null;
    }

    public function hasSourceKey(string $sourceKey): bool
    {
        $sourceKey = trim($sourceKey);
        if ($sourceKey === '') {
            return false;
        }

        foreach ($this->readRuns() as $run) {
            if (!empty($run['rolled_back_at'])) {
                continue;
            }
            foreach ($run['entries'] ?? [] as $entry) {
                if (($entry['source_key'] ?? '') === $sourceKey) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @param array<string, mixed> $record */
    public function add(array $record): array
    {
        $record['id'] = $record['id'] ?? ('mig_' . bin2hex(random_bytes(6)));
        $record['at'] = $record['at'] ?? date('c');

        $data = $this->readData();
        $runs = $data['runs'] ?? [];
        $runs[] = $record;
        if (count($runs) > 100) {
            $runs = array_slice($runs, -100);
        }
        $data['runs'] = array_values($runs);
        $this->writeData($data);

        return $record;
    }

    /** @param array<string, mixed> $patch */
    public function update(string $id, array $patch): ?array
    {
        $data = $this->readData();
        $updated = null;
        foreach ($data['runs'] ?? [] as $index => $item) {
            if (($item['id'] ?? '') !== $id) {
                continue;
            }
            $data['runs'][$index] = array_merge($item, $patch);
            $updated = $data['runs'][$index];
            break;
        }
        if ($updated === null) {
            return null;
        }

        $this->writeData($data);

        return $updated;
    }

    /** @return array{runs: list<array<string, mixed>>} */
    private function readData(): array
    {
        if (!is_file($this->file)) {
            return ['runs' => []];
        }
        $data = json_decode((string) file_get_contents($this->file), true);

        return is_array($data) ? $data : ['runs' => []];
    }

    /** @return list<array<string, mixed>> */
    private function readRuns(): array
    {
        $data = $this->readData();
        $runs = $data['runs'] ?? [];

        return is_array($runs) ? $runs : [];
    }

    /** @param array{runs: list<array<string, mixed>>} $data */
    private function writeData(array $data): void
    {
        file_put_contents($this->file, json_encode($data, JSON_PRETTY_PRINT));
    }
}
