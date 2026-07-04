<?php
/**
 * RSVP (free) and paid ticket checkout via payment_shelf.
 */

class EventsRegistrationService
{
    private EventsCatalog $catalog;
    private EventsRegistrationStore $registrations;
    private EventsCapacityService $capacity;

    public function __construct(
        ?EventsCatalog $catalog = null,
        ?EventsRegistrationStore $registrations = null,
        ?EventsCapacityService $capacity = null
    ) {
        $this->catalog = $catalog ?? new EventsCatalog();
        $this->registrations = $registrations ?? new EventsRegistrationStore();
        $this->capacity = $capacity ?? new EventsCapacityService($this->registrations);
    }

    public function registerFree(string $eventId, string $name, string $email, string $ticketTypeId = 'free'): array
    {
        $entry = $this->catalog->getEvent($eventId);
        if (!$entry) {
            return ['ok' => false, 'error' => 'Event not found'];
        }
        $event = $this->catalog->normalizeEvent($entry);
        if ($this->capacity->isEventFull($event)) {
            $this->fireEventFull($event);
            return ['ok' => false, 'error' => 'Event is full'];
        }
        if ($name === '' || $email === '') {
            return ['ok' => false, 'error' => 'Name and email required'];
        }

        $registration = $this->registrations->create([
            'event_id' => $eventId,
            'event_title' => $event['title'],
            'ticket_type' => $ticketTypeId,
            'status' => 'confirmed',
            'name' => $name,
            'email' => $email,
            'amount_cents' => 0,
            'currency' => 'usd',
        ]);

        $this->fireRsvp($registration, $event);
        if ($this->capacity->isEventFull($event)) {
            $this->fireEventFull($event);
        }

        return ['ok' => true, 'registration' => $registration];
    }

    public function startPaidRegistration(string $eventId, string $ticketTypeId, string $name, string $email): array
    {
        if (!EventsConsumerPaymentHelper::isPaymentsAvailable()) {
            return ['ok' => false, 'error' => 'Payments not configured for paid tickets'];
        }
        $entry = $this->catalog->getEvent($eventId);
        if (!$entry) {
            return ['ok' => false, 'error' => 'Event not found'];
        }
        $event = $this->catalog->normalizeEvent($entry);
        $ticket = $this->catalog->getTicketType($event, $ticketTypeId);
        if (!$ticket) {
            return ['ok' => false, 'error' => 'Ticket type not found'];
        }
        if ($this->capacity->isTicketTypeFull($event, $ticket)) {
            $this->fireEventFull($event);
            return ['ok' => false, 'error' => 'Ticket type is sold out'];
        }
        if ($name === '' || $email === '') {
            return ['ok' => false, 'error' => 'Name and email required'];
        }

        $registration = $this->registrations->create([
            'event_id' => $eventId,
            'event_title' => $event['title'],
            'ticket_type' => $ticketTypeId,
            'ticket_name' => $ticket['name'],
            'status' => 'pending',
            'name' => $name,
            'email' => $email,
            'amount_cents' => $ticket['price_cents'],
            'currency' => $ticket['currency'],
        ]);

        $shelf = EventsConsumerPaymentHelper::getShelf();
        if (!$shelf) {
            return ['ok' => false, 'error' => 'Payment shelf unavailable'];
        }

        $result = $shelf->createPayment('auto', [
            'amount_cents' => $ticket['price_cents'],
            'currency' => $ticket['currency'],
            'description' => $event['title'] . ' — ' . $ticket['name'],
            'metadata' => [
                'event_id' => $eventId,
                'registration_id' => $registration['id'],
                'ticket_type' => $ticketTypeId,
            ],
        ], [
            'source_plugin' => 'events',
            'source_ref' => $registration['id'],
        ]);

        if (!($result['ok'] ?? false)) {
            $this->registrations->update($registration['id'], ['status' => 'failed']);
            return ['ok' => false, 'error' => $result['error'] ?? 'Payment failed'];
        }

        $this->registrations->update($registration['id'], [
            'payment_id' => $result['payment_id'] ?? null,
            'provider' => $result['provider'] ?? null,
        ]);

        return [
            'ok' => true,
            'registration_id' => $registration['id'],
            'payment_id' => $result['payment_id'] ?? null,
            'redirect_url' => $result['redirect_url'] ?? null,
            'client_secret' => $result['client_secret'] ?? null,
        ];
    }

    public function confirmPaidByPaymentId(string $paymentId): ?array
    {
        $reg = $this->registrations->findByPaymentId($paymentId);
        if (!$reg || ($reg['status'] ?? '') === 'paid') {
            return $reg;
        }
        $updated = $this->registrations->update($reg['id'], ['status' => 'paid']);
        if ($updated) {
            $this->fireRegistrationPaid($updated);
            $entry = $this->catalog->getEvent($reg['event_id']);
            if ($entry) {
                $event = $this->catalog->normalizeEvent($entry);
                if ($this->capacity->isEventFull($event)) {
                    $this->fireEventFull($event);
                }
            }
        }
        return $updated;
    }

    public function failPaidByPaymentId(string $paymentId): ?array
    {
        $reg = $this->registrations->findByPaymentId($paymentId);
        if (!$reg) {
            return null;
        }
        return $this->registrations->update($reg['id'], ['status' => 'failed']);
    }

    private function fireRsvp(array $registration, array $event): void
    {
        $ctx = ['registration_id' => $registration['id'], 'event_id' => $event['id']];
        if (function_exists('broadcast_event')) {
            broadcast_event('event_rsvp', $ctx, 'events');
        }
        if (function_exists('enqueue_automation')) {
            enqueue_automation('event_rsvp', $ctx);
        }
    }

    private function fireRegistrationPaid(array $registration): void
    {
        $ctx = ['registration_id' => $registration['id'], 'event_id' => $registration['event_id']];
        if (function_exists('broadcast_event')) {
            broadcast_event('event_registration_paid', $ctx, 'events');
        }
        if (function_exists('enqueue_automation')) {
            enqueue_automation('event_registration_paid', $ctx);
        }
    }

    private function fireEventFull(array $event): void
    {
        $ctx = ['event_id' => $event['id']];
        if (function_exists('broadcast_event')) {
            broadcast_event('event_full', $ctx, 'events');
        }
        if (function_exists('enqueue_automation')) {
            enqueue_automation('event_full', $ctx);
        }
    }
}
