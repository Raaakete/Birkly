<?php
/**
 * Event index — entries with event fieldtype → occurrences for calendar views.
 */

require_once __DIR__ . '/EventSchema.php';
require_once __DIR__ . '/EventRecurrence.php';

class EventIndexer
{
    private string $indexFile;

    public function __construct(?string $dataDir = null)
    {
        $dir = $dataDir ?? events_data_dir();
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $this->indexFile = $dir . '/events_index.json';
        if (!is_file($this->indexFile)) {
            file_put_contents($this->indexFile, json_encode(['entries' => [], 'indexed_at' => null], JSON_PRETTY_PRINT));
        }
    }

    public function getIndexPath(): string
    {
        return $this->indexFile;
    }

    public function loadIndex(): array
    {
        if (!is_file($this->indexFile)) {
            return ['entries' => [], 'indexed_at' => null];
        }
        $data = json_decode((string) file_get_contents($this->indexFile), true);
        return is_array($data) ? $data : ['entries' => [], 'indexed_at' => null];
    }

    public function saveIndex(array $index): void
    {
        $index['indexed_at'] = date('c');
        file_put_contents($this->indexFile, json_encode($index, JSON_PRETTY_PRINT));
    }

    public function indexEntry(array $entry, string $collectionSlug): void
    {
        $entryId = $entry['id'] ?? '';
        if ($entryId === '' || $collectionSlug === '') {
            return;
        }

        $index = $this->loadIndex();
        $key = $collectionSlug . ':' . $entryId;

        $eventData = $this->extractEventFromEntry($entry);
        if ($eventData === null) {
            unset($index['entries'][$key]);
            $this->saveIndex($index);
            return;
        }

        if (($entry['status'] ?? 'published') !== 'published') {
            unset($index['entries'][$key]);
            $this->saveIndex($index);
            return;
        }

        $occurrences = EventRecurrence::expandOccurrences($eventData, $key);

        $index['entries'][$key] = [
            'entry_id' => $entryId,
            'collection_slug' => $collectionSlug,
            'entry_slug' => $entry['slug'] ?? $entryId,
            'entry_title' => $entry['title'] ?? $entryId,
            'event' => $eventData,
            'occurrences' => $occurrences,
            'updated_at' => date('c'),
        ];

        $this->saveIndex($index);
    }

    public function removeEntry(string $entryId, string $collectionSlug): void
    {
        if ($entryId === '' || $collectionSlug === '') {
            return;
        }
        $index = $this->loadIndex();
        unset($index['entries'][$collectionSlug . ':' . $entryId]);
        $this->saveIndex($index);
    }

    public function reindexAll(?string $contentRoot = null): int
    {
        $root = $contentRoot ?? dirname(__DIR__, 3) . '/content';
        require_once dirname(__DIR__, 3) . '/core/storage/StorageFactory.php';
        require_once dirname(__DIR__, 3) . '/core/public_read.php';

        $storage = StorageFactory::createStorage($root);
        $collectionsDir = $root . '/collections';
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
                if ($entryId === '' || ($entry['status'] ?? 'published') !== 'published') {
                    continue;
                }
                $eventData = $this->extractEventFromEntry($entry);
                if ($eventData === null) {
                    continue;
                }
                $key = $slug . ':' . $entryId;
                $index['entries'][$key] = [
                    'entry_id' => $entryId,
                    'collection_slug' => $slug,
                    'entry_slug' => $entry['slug'] ?? $entryId,
                    'entry_title' => $entry['title'] ?? $entryId,
                    'event' => $eventData,
                    'occurrences' => EventRecurrence::expandOccurrences($eventData, $key),
                    'updated_at' => date('c'),
                ];
            }
        }

        $this->saveIndex($index);
        return count($index['entries']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listOccurrences(?string $from = null, ?string $to = null): array
    {
        $rangeStart = null;
        $rangeEnd = null;
        if ($from !== null && $from !== '') {
            try {
                $rangeStart = new DateTimeImmutable($from);
            } catch (Exception $e) {
                $rangeStart = null;
            }
        }
        if ($to !== null && $to !== '') {
            try {
                $rangeEnd = new DateTimeImmutable($to . ' 23:59:59');
            } catch (Exception $e) {
                $rangeEnd = null;
            }
        }

        $out = [];
        $now = new DateTimeImmutable('now');

        foreach ($this->loadIndex()['entries'] ?? [] as $key => $row) {
            $eventData = $row['event'] ?? [];
            $occurrences = EventRecurrence::expandOccurrences($eventData, $key, $rangeStart, $rangeEnd);
            foreach ($occurrences as $occ) {
                $out[] = array_merge($occ, [
                    'entry_id' => $row['entry_id'] ?? '',
                    'collection_slug' => $row['collection_slug'] ?? '',
                    'entry_slug' => $row['entry_slug'] ?? '',
                    'entry_title' => $row['entry_title'] ?? '',
                    'venue' => $eventData['venue'] ?? '',
                    'virtual_url' => $eventData['virtual_url'] ?? '',
                    'hybrid' => !empty($eventData['hybrid']),
                    'is_past' => (new DateTimeImmutable($occ['ends_at'])) < $now,
                ]);
            }
        }

        usort($out, fn ($a, $b) => strcmp($a['starts_at'] ?? '', $b['starts_at'] ?? ''));
        return $out;
    }

    public function getIndexedEntry(string $collectionSlug, string $entryId): ?array
    {
        $key = $collectionSlug . ':' . $entryId;
        return $this->loadIndex()['entries'][$key] ?? null;
    }

    public function extractEventFromEntry(array $entry): ?array
    {
        foreach ($entry as $fieldKey => $fieldVal) {
            if (!is_array($fieldVal)) {
                continue;
            }
            if (EventSchema::isEventFieldValue($fieldVal)) {
                return event_normalize_value($fieldVal);
            }
        }
        if (isset($entry['event']) && is_array($entry['event'])) {
            return event_normalize_value($entry['event']);
        }
        return null;
    }
}
