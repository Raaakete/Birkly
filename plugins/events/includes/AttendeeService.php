<?php
/**
 * Merged attendee view — Commerce EventRegistrationStore + legacy Events registrations.
 */

class AttendeeService
{
    private EventsRegistrationStore $legacyStore;

    public function __construct(?EventsRegistrationStore $legacyStore = null)
    {
        $this->legacyStore = $legacyStore ?? new EventsRegistrationStore();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listAll(?string $eventId = null, ?string $collectionSlug = null, ?string $entryId = null): array
    {
        $attendees = array_merge(
            $this->listLegacyAttendees($eventId),
            $this->listCommerceAttendees($collectionSlug, $entryId)
        );

        usort($attendees, fn ($a, $b) => strcmp($b['registered_at'] ?? '', $a['registered_at'] ?? ''));
        return $attendees;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listLegacyAttendees(?string $eventId): array
    {
        $items = $eventId !== null && $eventId !== ''
            ? $this->legacyStore->listForEvent($eventId)
            : $this->legacyStore->listAll();

        $out = [];
        foreach ($items as $row) {
            $out[] = [
                'source' => 'legacy',
                'id' => $row['id'] ?? '',
                'entry_id' => $row['event_id'] ?? '',
                'collection_slug' => 'events',
                'name' => $row['name'] ?? '',
                'email' => $row['email'] ?? '',
                'ticket_type' => $row['ticket_type'] ?? '',
                'status' => $row['status'] ?? '',
                'amount_cents' => (int) ($row['amount_cents'] ?? 0),
                'payment_id' => $row['payment_id'] ?? '',
                'registered_at' => $row['created_at'] ?? '',
                'event_title' => $row['event_title'] ?? '',
            ];
        }
        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listCommerceAttendees(?string $collectionSlug, ?string $entryId): array
    {
        if (!class_exists('EventRegistrationStore')) {
            $commerceInit = dirname(__DIR__, 2) . '/commerce/init.php';
            if (is_file($commerceInit)) {
                require_once $commerceInit;
            }
            if (function_exists('commerce_bootstrap_cart_services')) {
                commerce_bootstrap_cart_services();
            }
        }

        if (!class_exists('EventRegistrationStore')) {
            return [];
        }

        $store = new EventRegistrationStore();
        $out = [];

        foreach ($store->listAll() as $row) {
            $rowEntryId = $row['entry_id'] ?? '';
            $rowCollection = $row['collection_slug'] ?? '';

            if ($entryId !== null && $entryId !== '' && $rowEntryId !== $entryId) {
                continue;
            }
            if ($collectionSlug !== null && $collectionSlug !== '' && $rowCollection !== $collectionSlug) {
                continue;
            }

            $out[] = [
                'source' => 'commerce',
                'id' => ($row['order_id'] ?? '') . ':' . ($row['line_id'] ?? ''),
                'entry_id' => $rowEntryId,
                'collection_slug' => $rowCollection,
                'name' => $row['attendee_name'] ?? '',
                'email' => $row['customer_email'] ?? '',
                'ticket_type' => 'register',
                'status' => 'paid',
                'amount_cents' => 0,
                'payment_id' => $row['order_id'] ?? '',
                'registered_at' => $row['registered_at'] ?? '',
                'event_title' => '',
                'quantity' => (int) ($row['quantity'] ?? 1),
            ];
        }

        return $out;
    }

    /**
     * @return array{upcoming: int, past: int, attendees: int, revenue_cents: int}
     */
    public function overviewStats(EventIndexer $indexer): array
    {
        $now = new DateTimeImmutable('now');
        $upcoming = 0;
        $past = 0;

        foreach ($indexer->listOccurrences() as $occ) {
            try {
                $end = new DateTimeImmutable($occ['ends_at']);
            } catch (Exception $e) {
                continue;
            }
            if ($end >= $now) {
                $upcoming++;
            } else {
                $past++;
            }
        }

        $attendees = $this->listAll();
        $revenueCents = 0;
        foreach ($attendees as $a) {
            if (($a['status'] ?? '') === 'paid') {
                $revenueCents += (int) ($a['amount_cents'] ?? 0);
            }
        }

        return [
            'upcoming' => $upcoming,
            'past' => $past,
            'indexed_events' => count($indexer->loadIndex()['entries'] ?? []),
            'attendees' => count($attendees),
            'revenue_cents' => $revenueCents,
        ];
    }
}
