<?php
/**
 * Catalog index — purchasable entries with Commerce fieldtype values (v2).
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
        $entryId = $entry['id'] ?? '';
        if ($entryId === '' || $collectionSlug === '') {
            return;
        }

        $index = $this->loadIndex();
        $parentKey = $collectionSlug . ':' . $entryId;

        $variants = $this->loadVariantSubEntries($collectionSlug, $entryId);
        if (!empty($variants)) {
            unset($index['entries'][$parentKey]);
            foreach ($variants as $variant) {
                $this->indexSellableUnit($index, $variant, $collectionSlug, $entryId, $entry);
            }
            $this->saveIndex($index);
            return;
        }

        $commerceData = $this->extractCommerceFromEntry($entry);
        if ($commerceData === null) {
            unset($index['entries'][$parentKey]);
            $this->saveIndex($index);
            return;
        }

        $index['entries'][$parentKey] = $this->buildIndexRow($commerceData, $entry, $collectionSlug);
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

        foreach (array_keys($index['entries']) as $existingKey) {
            $row = $index['entries'][$existingKey];
            if (($row['parent_id'] ?? '') === $entryId && ($row['collection_slug'] ?? '') === $collectionSlug) {
                unset($index['entries'][$existingKey]);
            }
        }

        $this->saveIndex($index);
    }

    public function reindexAll(?string $contentRoot = null): int
    {
        $root = $contentRoot ?? dirname(__DIR__, 3) . '/content';
        require_once dirname(__DIR__, 3) . '/core/storage/StorageFactory.php';
        require_once dirname(__DIR__, 3) . '/core/public_read.php';
        require_once dirname(__DIR__, 3) . '/core/sub_entries.php';

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
                $entryId = $entry['id'] ?? '';
                if ($entryId === '') {
                    continue;
                }

                $variants = getSubEntries($slug, $entryId, 'variants');
                if (!empty($variants)) {
                    foreach ($variants as $variant) {
                        $commerceData = $this->extractCommerceFromEntry($variant);
                        if ($commerceData === null) {
                            continue;
                        }
                        $variantId = $variant['id'] ?? '';
                        $key = $slug . ':' . $variantId;
                        $index['entries'][$key] = $this->buildIndexRow($commerceData, $variant, $slug, $entryId, $entry);
                        $count++;
                    }
                    continue;
                }

                $commerceData = $this->extractCommerceFromEntry($entry);
                if ($commerceData === null) {
                    continue;
                }
                $key = $slug . ':' . $entryId;
                $index['entries'][$key] = $this->buildIndexRow($commerceData, $entry, $slug);
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

    public function normalizeCommerceRow(array $value): array
    {
        if (!class_exists('CommerceSchema')) {
            require_once __DIR__ . '/CommerceSchema.php';
        }
        return commerce_normalize_value($value);
    }

    private function indexSellableUnit(array &$index, array $variant, string $collectionSlug, string $parentId, array $parentEntry): void
    {
        $commerceData = $this->extractCommerceFromEntry($variant);
        $variantId = $variant['id'] ?? '';
        if ($variantId === '' || $commerceData === null) {
            return;
        }
        $key = $collectionSlug . ':' . $variantId;
        $index['entries'][$key] = $this->buildIndexRow($commerceData, $variant, $collectionSlug, $parentId, $parentEntry);
    }

    private function buildIndexRow(array $commerceData, array $entry, string $collectionSlug, ?string $parentId = null, ?array $parentEntry = null): array
    {
        $entryId = $entry['id'] ?? '';
        $variantAttrs = $this->extractVariantAttrs($entry);

        $row = array_merge($commerceData, [
            'entry_id' => $entryId,
            'collection_slug' => $collectionSlug,
            'entry_slug' => $entry['slug'] ?? $entryId,
            'entry_title' => $entry['title'] ?? ($parentEntry['title'] ?? '') . ($variantAttrs ? ' — ' . implode(', ', $variantAttrs) : ''),
            'commerce' => $commerceData,
            'updated_at' => date('c'),
        ]);

        if ($parentId !== null) {
            $row['parent_id'] = $parentId;
            $row['parent_slug'] = $parentEntry['slug'] ?? $parentId;
            $row['parent_title'] = $parentEntry['title'] ?? '';
            $row['variant_attrs'] = $variantAttrs;
        }

        return $row;
    }

    private function extractVariantAttrs(array $entry): array
    {
        $skip = ['id', 'slug', 'title', 'status', 'collection', 'commerce', 'pricing', 'created_at', 'updated_at'];
        $attrs = [];
        foreach ($entry as $key => $val) {
            if (in_array($key, $skip, true) || !is_scalar($val) || $val === '') {
                continue;
            }
            if (is_array($val) && (isset($val['price_cents']) || isset($val['purchase_options']))) {
                continue;
            }
            $attrs[$key] = (string) $val;
        }
        return $attrs;
    }

    private function loadVariantSubEntries(string $collectionSlug, string $entryId): array
    {
        $subFile = dirname(__DIR__, 3) . '/core/sub_entries.php';
        if (!is_file($subFile)) {
            return [];
        }
        require_once $subFile;
        if (!function_exists('getSubEntries')) {
            return [];
        }
        return getSubEntries($collectionSlug, $entryId, 'variants') ?: [];
    }

    private function extractCommerceFromEntry(array $entry): ?array
    {
        foreach ($entry as $fieldKey => $fieldVal) {
            if (!is_array($fieldVal)) {
                continue;
            }
            if (isset($fieldVal['purchase_options']) || isset($fieldVal['price_cents']) || isset($fieldVal['billing']) || isset($fieldVal['fulfillment'])) {
                return $this->normalizeCommerceRow($fieldVal);
            }
        }
        if (isset($entry['commerce']) && is_array($entry['commerce'])) {
            return $this->normalizeCommerceRow($entry['commerce']);
        }
        return null;
    }
}
