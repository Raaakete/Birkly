<?php
/**
 * Catalog index — purchasable entries with Commerce fieldtype values.
 */

class CatalogIndexer
{
    private string $indexFile;

    public function __construct(?string $dataDir = null)
    {
        $dir = $dataDir ?? commerce_data_dir();
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $this->indexFile = $dir . '/catalog_index.json';
        if (!is_file($this->indexFile)) {
            file_put_contents($this->indexFile, json_encode(['entries' => []], JSON_PRETTY_PRINT));
        }
    }

    public function getIndexPath(): string
    {
        return $this->indexFile;
    }

    public function loadIndex(): array
    {
        if (!is_file($this->indexFile)) {
            return ['entries' => []];
        }
        $data = json_decode((string) file_get_contents($this->indexFile), true);
        return is_array($data) ? $data : ['entries' => []];
    }

    public function saveIndex(array $index): void
    {
        file_put_contents($this->indexFile, json_encode($index, JSON_PRETTY_PRINT));
    }

    public function indexEntry(array $entry, string $collectionSlug): void
    {
        $commerceData = $this->extractCommerceFromEntry($entry);
        $entryId = $entry['id'] ?? '';
        if ($entryId === '' || $collectionSlug === '') {
            return;
        }

        $index = $this->loadIndex();
        $key = $collectionSlug . ':' . $entryId;

        if ($commerceData === null) {
            unset($index['entries'][$key]);
            $this->saveIndex($index);
            return;
        }

        $index['entries'][$key] = array_merge($commerceData, [
            'entry_id' => $entryId,
            'collection_slug' => $collectionSlug,
            'entry_slug' => $entry['slug'] ?? $entryId,
            'entry_title' => $entry['title'] ?? '',
            'updated_at' => date('c'),
        ]);
        $this->saveIndex($index);
    }

    public function removeEntry(string $entryId, string $collectionSlug): void
    {
        if ($entryId === '' || $collectionSlug === '') {
            return;
        }
        $index = $this->loadIndex();
        $key = $collectionSlug . ':' . $entryId;
        unset($index['entries'][$key]);
        $this->saveIndex($index);
    }

    public function reindexAll(?string $contentRoot = null): int
    {
        $root = $contentRoot ?? dirname(__DIR__, 3) . '/content';
        require_once dirname(__DIR__, 3) . '/core/storage/StorageFactory.php';
        require_once dirname(__DIR__, 3) . '/core/public_read.php';

        $storage = StorageFactory::createStorage($root);
        $collectionsDir = $root . '/collections';
        $count = 0;
        $index = ['entries' => []];

        if (!is_dir($collectionsDir)) {
            $this->saveIndex($index);
            return 0;
        }

        foreach (scandir($collectionsDir) ?: [] as $slug) {
            if ($slug === '.' || $slug === '..') {
                continue;
            }
            $result = birkly_public_list_entries($storage, $slug);
            if (($result['status'] ?? '') !== 'ok') {
                continue;
            }
            foreach ($result['data']['entries'] ?? [] as $entry) {
                $commerceData = $this->extractCommerceFromEntry($entry);
                if ($commerceData === null) {
                    continue;
                }
                $entryId = $entry['id'] ?? '';
                $key = $slug . ':' . $entryId;
                $index['entries'][$key] = array_merge($commerceData, [
                    'entry_id' => $entryId,
                    'collection_slug' => $slug,
                    'entry_slug' => $entry['slug'] ?? $entryId,
                    'entry_title' => $entry['title'] ?? '',
                    'updated_at' => date('c'),
                ]);
                $count++;
            }
        }

        $this->saveIndex($index);
        return $count;
    }

    public function decrementQuantity(string $collectionSlug, string $entryId, int $qty): bool
    {
        $index = $this->loadIndex();
        $key = $collectionSlug . ':' . $entryId;
        if (!isset($index['entries'][$key])) {
            return false;
        }
        $row = $index['entries'][$key];
        if ($row['quantity_available'] === null) {
            return true;
        }
        $available = (int) $row['quantity_available'];
        $row['quantity_available'] = max(0, $available - $qty);
        $row['updated_at'] = date('c');
        $index['entries'][$key] = $row;
        $this->saveIndex($index);
        return true;
    }

    public function reserveQuantity(string $collectionSlug, string $entryId, int $qty): bool
    {
        $index = $this->loadIndex();
        $key = $collectionSlug . ':' . $entryId;
        if (!isset($index['entries'][$key])) {
            return false;
        }
        $row = $index['entries'][$key];
        if ($row['quantity_available'] === null) {
            return true;
        }
        return (int) $row['quantity_available'] >= $qty;
    }

    private function extractCommerceFromEntry(array $entry): ?array
    {
        foreach ($entry as $fieldKey => $fieldVal) {
            if (!is_array($fieldVal)) {
                continue;
            }
            if (isset($fieldVal['price_cents']) || isset($fieldVal['billing'])) {
                return $this->normalizeCommerceRow($fieldVal);
            }
        }
        if (isset($entry['commerce']) && is_array($entry['commerce'])) {
            return $this->normalizeCommerceRow($entry['commerce']);
        }
        return null;
    }

    private function normalizeCommerceRow(array $value): array
    {
        $qty = $value['quantity_available'] ?? null;
        if ($qty === '' || $qty === false) {
            $qty = null;
        } elseif ($qty !== null) {
            $qty = (int) $qty;
        }

        return [
            'price_cents' => max(0, (int) ($value['price_cents'] ?? 0)),
            'currency' => strtolower((string) ($value['currency'] ?? 'eur')),
            'billing' => in_array($value['billing'] ?? '', ['one_time', 'recurring'], true)
                ? $value['billing'] : 'one_time',
            'interval' => $value['interval'] ?? null,
            'quantity_available' => $qty,
            'sku' => isset($value['sku']) && $value['sku'] !== '' ? (string) $value['sku'] : null,
        ];
    }
}
