<?php
/**
 * Capacity checks for events and ticket types.
 */

class EventsCapacityService
{
    private EventsRegistrationStore $registrations;

    public function __construct(?EventsRegistrationStore $registrations = null)
    {
        $this->registrations = $registrations ?? new EventsRegistrationStore();
    }

    public function isEventFull(array $event): bool
    {
        $capacity = (int) ($event['capacity'] ?? 0);
        if ($capacity <= 0) {
            return false;
        }
        return $this->registrations->countForEvent($event['id']) >= $capacity;
    }

    public function isTicketTypeFull(array $event, array $ticketType): bool
    {
        $capacity = (int) ($ticketType['capacity'] ?? 0);
        if ($capacity <= 0) {
            return $this->isEventFull($event);
        }
        return $this->registrations->countForEvent($event['id'], $ticketType['id']) >= $capacity;
    }

    public function remainingCapacity(array $event): ?int
    {
        $capacity = (int) ($event['capacity'] ?? 0);
        if ($capacity <= 0) {
            return null;
        }
        return max(0, $capacity - $this->registrations->countForEvent($event['id']));
    }
}
