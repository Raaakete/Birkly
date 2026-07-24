<?php
/**
 * Reads events collection and ticket type metadata.
 */

class EventsCatalog
{
    private string $collectionSlug = 'events';

    public function listPublished(): array
    {
        require_once dirname(__DIR__, 3) . '/core/storage/StorageFactory.php';
        require_once dirname(__DIR__, 3) . '/core/public_read.php';
        $storage = StorageFactory::createStorage(dirname(__DIR__, 3) . '/content');
        $result = birkly_public_list_entries($storage, $this->collectionSlug);
        return ($result['status'] ?? '') === 'ok' ? ($result['data']['entries'] ?? []) : [];
    }

    public function getEvent(string $eventId): ?array
    {
        require_once dirname(__DIR__, 3) . '/core/storage/StorageFactory.php';
        require_once dirname(__DIR__, 3) . '/core/public_read.php';
        $storage = StorageFactory::createStorage(dirname(__DIR__, 3) . '/content');
        $result = birkly_public_get_entry($storage, $this->collectionSlug, '', $eventId);
        if (($result['status'] ?? '') !== 'ok') {
            return null;
        }
        return $result['data']['entry'] ?? null;
    }

    public function normalizeEvent(array $entry): array
    {
        $ticketTypes = $entry['ticket_types'] ?? [];
        if (is_string($ticketTypes)) {
            $decoded = json_decode($ticketTypes, true);
            $ticketTypes = is_array($decoded) ? $decoded : [];
        }
        return [
            'id' => $entry['id'] ?? '',
            'title' => $entry['title'] ?? '',
            'slug' => $entry['slug'] ?? '',
            'start' => $entry['start'] ?? '',
            'end' => $entry['end'] ?? '',
            'venue' => $entry['venue'] ?? '',
            'capacity' => isset($entry['capacity']) ? (int) $entry['capacity'] : 0,
            'visibility' => $entry['visibility'] ?? 'public',
            'ticket_types' => $this->normalizeTicketTypes($ticketTypes),
        ];
    }

    public function normalizeTicketTypes(array $types): array
    {
        $out = [];
        foreach ($types as $type) {
            if (!is_array($type)) {
                continue;
            }
            $price = $type['price'] ?? 0;
            if (is_string($price)) {
                $price = (float) $price;
            }
            $out[] = [
                'id' => $type['id'] ?? ($type['name'] ?? 'ticket'),
                'name' => $type['name'] ?? 'Ticket',
                'price_cents' => (int) round(((float) $price) * 100),
                'currency' => strtolower($type['currency'] ?? 'usd'),
                'capacity' => isset($type['capacity']) ? (int) $type['capacity'] : 0,
            ];
        }
        return $out;
    }

    public function getTicketType(array $event, string $ticketTypeId): ?array
    {
        foreach ($event['ticket_types'] ?? [] as $type) {
            if (($type['id'] ?? '') === $ticketTypeId) {
                return $type;
            }
        }
        return null;
    }
}
