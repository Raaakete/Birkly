<?php
/**
 * P11-M6 — Import legacy Shopping Cart / Events data into Commerce fieldtype entries.
 */

if (!class_exists('CommerceSchema')) {
    require_once __DIR__ . '/CommerceSchema.php';
}
if (!class_exists('ImportCollectionHelper')) {
    require_once __DIR__ . '/ImportCollectionHelper.php';
}
require_once __DIR__ . '/MigrationHistoryStore.php';

class LegacyMigrationService
{
    private CatalogIndexer $indexer;
    private string $contentRoot;
    private string $cmsRoot;
    private MigrationHistoryStore $history;

    public function __construct(?CatalogIndexer $indexer = null, ?string $contentRoot = null, ?string $cmsRoot = null, ?MigrationHistoryStore $history = null)
    {
        $this->indexer = $indexer ?? new CatalogIndexer();
        $this->contentRoot = $contentRoot ?? dirname(__DIR__, 3) . '/content';
        $this->cmsRoot = $cmsRoot ?? dirname(__DIR__, 3);
        $this->history = $history ?? new MigrationHistoryStore();
    }

    /** @return array<string, mixed> */
    public function scan(): array
    {
        $shopping = $this->detectShoppingCartSources();
        $events = $this->detectEventsSources();

        return [
            'shopping_cart' => $shopping,
            'events' => $events,
            'plugin_dirs' => [
                'shopping_cart' => is_dir($this->cmsRoot . '/plugins/shopping_cart'),
                'events' => is_dir($this->cmsRoot . '/plugins/events'),
            ],
            'plugin_data' => [
                'shopping_cart' => is_dir($this->cmsRoot . '/data/plugins/shopping_cart'),
                'events' => is_dir($this->cmsRoot . '/data/plugins/events'),
            ],
            'imported_source_keys' => $this->countImportedSourceKeys(),
        ];
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function preview(array $options = []): array
    {
        $sources = $this->normalizeSources($options['sources'] ?? ['shopping_cart', 'events']);
        $productsCollection = trim((string) ($options['products_collection'] ?? 'products'));
        $eventsCollection = trim((string) ($options['events_collection'] ?? 'events-commerce'));
        $createCollections = !empty($options['create_collections']);

        $rows = [];
        if (in_array('shopping_cart', $sources, true)) {
            foreach ($this->detectShoppingCartSources()['collections'] as $collection) {
                $rows = array_merge($rows, $this->previewShoppingCollection(
                    (string) $collection['slug'],
                    $productsCollection,
                    $createCollections
                ));
            }
        }
        if (in_array('events', $sources, true)) {
            foreach ($this->detectEventsSources()['collections'] as $collection) {
                $rows = array_merge($rows, $this->previewEventsCollection(
                    (string) $collection['slug'],
                    $eventsCollection,
                    $createCollections
                ));
            }
        }

        return [
            'ok' => true,
            'preview' => $rows,
            'count' => count($rows),
            'create_count' => count(array_filter($rows, fn ($r) => ($r['action'] ?? '') === 'create')),
            'skip_count' => count(array_filter($rows, fn ($r) => ($r['action'] ?? '') === 'skip')),
            'products_collection' => $productsCollection,
            'events_collection' => $eventsCollection,
            'create_collections' => $createCollections,
        ];
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function import(array $options = []): array
    {
        $preview = $this->preview($options);
        $toImport = array_values(array_filter(
            $preview['preview'] ?? [],
            fn ($row) => ($row['action'] ?? '') === 'create'
        ));

        if (empty($toImport)) {
            return [
                'ok' => true,
                'imported_count' => 0,
                'skipped_count' => (int) ($preview['skip_count'] ?? 0),
                'message' => 'Nothing new to import',
            ];
        }

        $productsCollection = (string) ($preview['products_collection'] ?? 'products');
        $eventsCollection = (string) ($preview['events_collection'] ?? 'events-commerce');
        $createCollections = !empty($preview['create_collections']);

        $collectionHelper = new ImportCollectionHelper($this->contentRoot);
        if ($createCollections) {
            $this->ensureTargetCollection('shopping_cart', $productsCollection, 'Products', $collectionHelper);
            $this->ensureTargetCollection('events', $eventsCollection, 'Events (Commerce)', $collectionHelper);
        }

        require_once dirname(__DIR__, 3) . '/core/storage/StorageFactory.php';
        $storage = StorageFactory::createStorage($this->contentRoot);

        $created = [];
        $skipped = [];
        $errors = [];

        foreach ($toImport as $row) {
            $sourceKey = (string) ($row['source_key'] ?? '');
            if ($sourceKey === '' || $this->history->hasSourceKey($sourceKey)) {
                $skipped[] = ['source_key' => $sourceKey, 'reason' => 'Already imported'];
                continue;
            }

            $targetCollection = (string) ($row['target_collection'] ?? '');
            $mapped = $row['mapped_entry'] ?? null;
            if ($targetCollection === '' || !is_array($mapped)) {
                $errors[] = ['source_key' => $sourceKey, 'reason' => 'Invalid preview row'];
                continue;
            }

            $resolved = $collectionHelper->resolveForImport($targetCollection, ['create' => false]);
            if (!($resolved['ok'] ?? false)) {
                $errors[] = [
                    'source_key' => $sourceKey,
                    'reason' => $resolved['message'] ?? 'Target collection not ready',
                ];
                continue;
            }

            $commerceField = (string) ($resolved['commerce_field'] ?? ImportCollectionHelper::COMMERCE_FIELD_NAME);
            $now = date('c');
            $entry = $mapped;
            $entry['id'] = uniqid('mig_');
            $entry['created_at'] = $now;
            $entry['updated_at'] = $now;
            if (!isset($entry[$commerceField]) && isset($entry['pricing'])) {
                $entry[$commerceField] = $entry['pricing'];
                unset($entry['pricing']);
            }
            if (isset($entry[$commerceField])) {
                $entry[$commerceField] = commerce_normalize_value($entry[$commerceField]);
            }

            $entryPath = "collections/{$targetCollection}/entries/{$entry['id']}.json";
            $entriesDir = $this->contentRoot . '/collections/' . $targetCollection . '/entries';
            if (!is_dir($entriesDir)) {
                mkdir($entriesDir, 0755, true);
            }

            if (!$storage->write($entryPath, $entry)) {
                $errors[] = ['source_key' => $sourceKey, 'reason' => 'Failed to write entry'];
                continue;
            }

            $this->indexer->indexEntry($entry, $targetCollection);
            $created[] = [
                'source_key' => $sourceKey,
                'collection' => $targetCollection,
                'entry_id' => $entry['id'],
                'slug' => $entry['slug'] ?? '',
                'title' => $entry['title'] ?? '',
            ];
        }

        $status = 'success';
        if (count($created) === 0 && (count($errors) > 0 || count($skipped) > 0)) {
            $status = count($errors) > 0 ? 'failed' : 'partial';
        } elseif (count($errors) > 0 || count($skipped) > 0) {
            $status = 'partial';
        }

        $record = $this->history->add([
            'status' => $status,
            'sources' => $this->normalizeSources($options['sources'] ?? ['shopping_cart', 'events']),
            'products_collection' => $productsCollection,
            'events_collection' => $eventsCollection,
            'create_collections' => $createCollections,
            'imported_count' => count($created),
            'skipped_count' => count($skipped) + (int) ($preview['skip_count'] ?? 0),
            'error_count' => count($errors),
            'entries' => $created,
            'errors' => array_slice($errors, 0, 50),
        ]);

        return [
            'ok' => true,
            'import_id' => $record['id'],
            'status' => $status,
            'imported_count' => count($created),
            'skipped_count' => count($skipped) + (int) ($preview['skip_count'] ?? 0),
            'errors' => $errors,
            'entries' => $created,
        ];
    }

    /** @return array<string, mixed> */
    public function rollback(string $runId): array
    {
        $record = $this->history->get($runId);
        if ($record === null) {
            return ['ok' => false, 'error' => 'Migration run not found'];
        }
        if (!empty($record['rolled_back_at'])) {
            return ['ok' => false, 'error' => 'This migration was already rolled back'];
        }

        require_once dirname(__DIR__, 3) . '/core/storage/StorageFactory.php';
        $storage = StorageFactory::createStorage($this->contentRoot);
        $deleted = 0;

        foreach ($record['entries'] ?? [] as $item) {
            $collection = (string) ($item['collection'] ?? '');
            $entryId = (string) ($item['entry_id'] ?? '');
            if ($collection === '' || $entryId === '') {
                continue;
            }
            $path = "collections/{$collection}/entries/{$entryId}.json";
            if ($storage->exists($path)) {
                $storage->delete($path);
                $deleted++;
            }
            $this->indexer->removeEntry($entryId, $collection);
        }

        $this->history->update($runId, [
            'rolled_back_at' => date('c'),
            'rollback_deleted' => $deleted,
        ]);

        return ['ok' => true, 'deleted' => $deleted, 'import_id' => $runId];
    }

    /** @return list<array<string, mixed>> */
    public function listHistory(int $limit = 50): array
    {
        return $this->history->list($limit);
    }

    /** @return array<string, mixed> */
    private function detectShoppingCartSources(): array
    {
        $collections = [];
        foreach ($this->listLegacyProductCollections() as $slug => $meta) {
            $collections[] = [
                'slug' => $slug,
                'name' => $meta['name'],
                'entry_count' => $meta['entry_count'],
            ];
        }

        return [
            'detected' => !empty($collections)
                || is_dir($this->cmsRoot . '/plugins/shopping_cart')
                || is_dir($this->cmsRoot . '/data/plugins/shopping_cart'),
            'collections' => $collections,
            'entry_count' => array_sum(array_column($collections, 'entry_count')),
        ];
    }

    /** @return array<string, mixed> */
    private function detectEventsSources(): array
    {
        $collections = [];
        foreach ($this->listLegacyEventsCollections() as $slug => $meta) {
            $collections[] = [
                'slug' => $slug,
                'name' => $meta['name'],
                'entry_count' => $meta['entry_count'],
            ];
        }

        return [
            'detected' => !empty($collections)
                || is_dir($this->cmsRoot . '/plugins/events')
                || is_dir($this->cmsRoot . '/data/plugins/events'),
            'collections' => $collections,
            'entry_count' => array_sum(array_column($collections, 'entry_count')),
        ];
    }

    /** @return array<string, array{name: string, entry_count: int}> */
    private function listLegacyProductCollections(): array
    {
        $out = [];
        $collectionsDir = $this->contentRoot . '/collections';
        if (!is_dir($collectionsDir)) {
            return $out;
        }

        foreach (scandir($collectionsDir) ?: [] as $slug) {
            if ($slug === '.' || $slug === '..') {
                continue;
            }
            $schema = $this->readSchema($slug);
            if ($this->schemaHasCommerceField($schema)) {
                continue;
            }
            if (!$this->schemaHasLegacyProductFields($schema)) {
                continue;
            }
            $out[$slug] = [
                'name' => $schema['name'] ?? $slug,
                'entry_count' => $this->countEntries($slug),
            ];
        }

        return $out;
    }

    /** @return array<string, array{name: string, entry_count: int}> */
    private function listLegacyEventsCollections(): array
    {
        $out = [];
        $collectionsDir = $this->contentRoot . '/collections';
        if (!is_dir($collectionsDir)) {
            return $out;
        }

        foreach (scandir($collectionsDir) ?: [] as $slug) {
            if ($slug === '.' || $slug === '..') {
                continue;
            }
            $schema = $this->readSchema($slug);
            if ($this->schemaHasCommerceField($schema)) {
                continue;
            }
            if (!$this->schemaHasLegacyEventsFields($schema)) {
                continue;
            }
            $out[$slug] = [
                'name' => $schema['name'] ?? $slug,
                'entry_count' => $this->countEntries($slug),
            ];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function previewShoppingCollection(string $sourceCollection, string $targetCollection, bool $createCollections): array
    {
        $rows = [];
        foreach ($this->loadCollectionEntries($sourceCollection) as $entry) {
            $slug = (string) ($entry['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $sourceKey = $this->sourceKey('shopping_cart', $sourceCollection, $slug);
            $action = $this->history->hasSourceKey($sourceKey) ? 'skip' : 'create';
            $rows[] = [
                'source' => 'shopping_cart',
                'source_collection' => $sourceCollection,
                'source_key' => $sourceKey,
                'target_collection' => $targetCollection,
                'action' => $action,
                'title' => $entry['title'] ?? $slug,
                'slug' => $slug,
                'fulfillment' => 'ship',
                'mapped_entry' => $this->mapProductEntry($entry),
                'create_collection' => $createCollections,
            ];
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    private function previewEventsCollection(string $sourceCollection, string $targetCollection, bool $createCollections): array
    {
        $rows = [];
        foreach ($this->loadCollectionEntries($sourceCollection) as $entry) {
            $slug = (string) ($entry['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $sourceKey = $this->sourceKey('events', $sourceCollection, $slug);
            $action = $this->history->hasSourceKey($sourceKey) ? 'skip' : 'create';
            $mapped = $this->mapEventsEntry($entry);
            $rows[] = [
                'source' => 'events',
                'source_collection' => $sourceCollection,
                'source_key' => $sourceKey,
                'target_collection' => $targetCollection,
                'action' => $action,
                'title' => $entry['title'] ?? $slug,
                'slug' => $slug,
                'fulfillment' => 'register',
                'ticket_options' => count($mapped['pricing']['purchase_options'] ?? []),
                'mapped_entry' => $mapped,
                'create_collection' => $createCollections,
            ];
        }

        return $rows;
    }

    /** @param array<string, mixed> $entry */
    private function mapProductEntry(array $entry): array
    {
        $price = (float) ($entry['price'] ?? 0);
        $stock = $entry['stock'] ?? null;
        if ($stock === '' || $stock === false) {
            $stock = null;
        } elseif ($stock !== null) {
            $stock = (int) $stock;
        }

        return [
            'title' => (string) ($entry['title'] ?? 'Untitled'),
            'slug' => (string) ($entry['slug'] ?? ''),
            'status' => (string) ($entry['status'] ?? 'published'),
            'body' => (string) ($entry['description'] ?? $entry['body'] ?? ''),
            'pricing' => [
                'fulfillment' => 'ship',
                'purchase_options' => [[
                    'id' => 'default',
                    'label' => 'Default',
                    'billing' => 'one_time',
                    'price_cents' => max(0, (int) round($price * 100)),
                    'currency' => strtolower((string) ($entry['currency'] ?? 'eur')),
                ]],
                'quantity_available' => $stock,
                'sku' => isset($entry['sku']) && $entry['sku'] !== '' ? trim((string) $entry['sku']) : null,
            ],
        ];
    }

    /** @param array<string, mixed> $entry */
    private function mapEventsEntry(array $entry): array
    {
        $ticketTypes = $this->parseTicketTypes($entry['ticket_types'] ?? []);
        $options = [];
        foreach ($ticketTypes as $idx => $type) {
            if (!is_array($type)) {
                continue;
            }
            $price = (float) ($type['price'] ?? 0);
            $id = trim((string) ($type['id'] ?? ''));
            if ($id === '') {
                $id = 'ticket_' . ($idx + 1);
            }
            $options[] = [
                'id' => $id,
                'label' => trim((string) ($type['name'] ?? $id)),
                'billing' => 'one_time',
                'price_cents' => max(0, (int) round($price * 100)),
                'currency' => strtolower((string) ($type['currency'] ?? $entry['currency'] ?? 'eur')),
            ];
        }
        if (empty($options)) {
            $options[] = [
                'id' => 'general',
                'label' => 'General admission',
                'billing' => 'one_time',
                'price_cents' => 0,
                'currency' => 'eur',
            ];
        }

        $capacity = $entry['capacity'] ?? null;
        if ($capacity === '' || $capacity === false) {
            $capacity = null;
        } elseif ($capacity !== null) {
            $capacity = (int) $capacity;
            if ($capacity === 0) {
                $capacity = null;
            }
        }

        return [
            'title' => (string) ($entry['title'] ?? 'Untitled'),
            'slug' => (string) ($entry['slug'] ?? ''),
            'status' => (string) ($entry['status'] ?? 'published'),
            'body' => (string) ($entry['description'] ?? ''),
            'start' => $entry['start'] ?? null,
            'end' => $entry['end'] ?? null,
            'venue' => $entry['venue'] ?? null,
            'pricing' => [
                'fulfillment' => 'register',
                'purchase_options' => $options,
                'quantity_available' => $capacity,
            ],
        ];
    }

  /**
     * @param mixed $raw
     * @return list<array<string, mixed>>
     */
    private function parseTicketTypes($raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function sourceKey(string $source, string $collection, string $slug): string
    {
        return 'legacy:' . $source . ':' . $collection . ':' . $slug;
    }

    private function countImportedSourceKeys(): int
    {
        $count = 0;
        foreach ($this->history->list(100) as $run) {
            if (!empty($run['rolled_back_at'])) {
                continue;
            }
            $count += count($run['entries'] ?? []);
        }

        return $count;
    }

    /** @param list<string> $sources */
    private function normalizeSources(array $sources): array
    {
        $allowed = ['shopping_cart', 'events'];
        $out = [];
        foreach ($sources as $source) {
            $source = trim((string) $source);
            if ($source !== '' && in_array($source, $allowed, true) && !in_array($source, $out, true)) {
                $out[] = $source;
            }
        }

        return $out ?: $allowed;
    }

    private function ensureTargetCollection(string $source, string $slug, string $title, ImportCollectionHelper $helper): void
    {
        $dir = $this->contentRoot . '/collections/' . $slug;
        if (is_dir($dir)) {
            return;
        }

        if ($source === 'events') {
            $this->createEventsCollection($slug, $title);

            return;
        }

        $helper->createProductCollection($slug, $title);
    }

    private function createEventsCollection(string $slug, string $title): bool
    {
        $dir = $this->contentRoot . '/collections/' . $slug;
        if (is_dir($dir)) {
            return is_file($dir . '/schema.json');
        }

        if (!mkdir($dir . '/entries', 0755, true) && !is_dir($dir . '/entries')) {
            return false;
        }

        $now = date('c');
        $meta = [
            'id' => 'col-' . uniqid(),
            'title' => $title,
            'slug' => $slug,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $schema = [
            'fields' => [
                ['name' => 'title', 'type' => 'text', 'label' => 'Title', 'required' => true],
                ['name' => 'slug', 'type' => 'text', 'label' => 'Slug'],
                ['name' => 'start', 'type' => 'datetime', 'label' => 'Start'],
                ['name' => 'end', 'type' => 'datetime', 'label' => 'End'],
                ['name' => 'venue', 'type' => 'text', 'label' => 'Venue'],
                ['name' => 'body', 'type' => 'textarea', 'label' => 'Description'],
                ['name' => ImportCollectionHelper::COMMERCE_FIELD_NAME, 'type' => 'commerce', 'label' => 'Tickets'],
                ['name' => 'status', 'type' => 'select', 'label' => 'Status', 'options' => [
                    ['label' => 'Draft', 'value' => 'draft'],
                    ['label' => 'Published', 'value' => 'published'],
                ], 'default' => 'published'],
            ],
        ];

        return file_put_contents($dir . '/collection.json', json_encode($meta, JSON_PRETTY_PRINT)) !== false
            && file_put_contents($dir . '/schema.json', json_encode($schema, JSON_PRETTY_PRINT)) !== false;
    }

    /** @return array{fields?: list<array<string, mixed>>, name?: string} */
    private function readSchema(string $slug): array
    {
        $schemaPath = $this->contentRoot . '/collections/' . $slug . '/schema.json';
        if (!is_file($schemaPath)) {
            return ['fields' => []];
        }
        $schema = json_decode((string) file_get_contents($schemaPath), true);

        return is_array($schema) ? $schema : ['fields' => []];
    }

    /** @param array{fields?: list<array<string, mixed>>} $schema */
    private function schemaHasCommerceField(array $schema): bool
    {
        foreach ($schema['fields'] ?? [] as $field) {
            $type = strtolower((string) ($field['type'] ?? ''));
            if (ImportCollectionHelper::isCommerceFieldType($type)) {
                return true;
            }
        }

        return false;
    }

    /** @param array{fields?: list<array<string, mixed>>} $schema */
    private function schemaHasLegacyProductFields(array $schema): bool
    {
        $hasPrice = false;
        foreach ($schema['fields'] ?? [] as $field) {
            $name = strtolower((string) ($field['name'] ?? ''));
            $type = strtolower((string) ($field['type'] ?? ''));
            if ($name === 'price' && in_array($type, ['number', 'text'], true)) {
                $hasPrice = true;
            }
        }

        return $hasPrice;
    }

    /** @param array{fields?: list<array<string, mixed>>} $schema */
    private function schemaHasLegacyEventsFields(array $schema): bool
    {
        $hasStart = false;
        $hasTickets = false;
        foreach ($schema['fields'] ?? [] as $field) {
            $name = strtolower((string) ($field['name'] ?? ''));
            if ($name === 'start') {
                $hasStart = true;
            }
            if ($name === 'ticket_types') {
                $hasTickets = true;
            }
        }

        return $hasStart && $hasTickets;
    }

    private function countEntries(string $collectionSlug): int
    {
        $dir = $this->contentRoot . '/collections/' . $collectionSlug . '/entries';

        return count(glob($dir . '/*.json') ?: []);
    }

    /** @return list<array<string, mixed>> */
    private function loadCollectionEntries(string $collectionSlug): array
    {
        $dir = $this->contentRoot . '/collections/' . $collectionSlug . '/entries';
        if (!is_dir($dir)) {
            return [];
        }

        $entries = [];
        foreach (glob($dir . '/*.json') ?: [] as $file) {
            $entry = json_decode((string) file_get_contents($file), true);
            if (is_array($entry)) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }
}
