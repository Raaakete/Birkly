<?php
/**
 * Event registrations storage (RSVP + paid tickets).
 */

class EventsRegistrationStore
{
    private string $baseDir;

    public function __construct(?string $baseDir = null)
    {
        if ($baseDir !== null) {
            $this->baseDir = rtrim($baseDir, '/');
        } else {
            $root = dirname(__DIR__, 3);
            $this->baseDir = $root . '/data/plugins/events/registrations';
        }
        if (!is_dir($this->baseDir)) {
            mkdir($this->baseDir, 0755, true);
        }
    }

    public function create(array $registration): array
    {
        $id = $registration['id'] ?? ('reg_' . bin2hex(random_bytes(8)));
        $now = date('c');
        $record = array_merge([
            'id' => $id,
            'event_id' => '',
            'ticket_type' => 'free',
            'status' => 'confirmed',
            'name' => '',
            'email' => '',
            'amount_cents' => 0,
            'currency' => 'usd',
            'payment_id' => null,
            'provider' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $registration);
        $record['id'] = $id;
        $this->write($id, $record);
        return $record;
    }

    public function get(string $id): ?array
    {
        $file = $this->path($id);
        if (!is_file($file)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($file), true);
        return is_array($data) ? $data : null;
    }

    public function update(string $id, array $patch): ?array
    {
        $existing = $this->get($id);
        if (!$existing) {
            return null;
        }
        $record = array_merge($existing, $patch, ['updated_at' => date('c')]);
        $this->write($id, $record);
        return $record;
    }

    public function findByPaymentId(string $paymentId): ?array
    {
        foreach ($this->listAll() as $reg) {
            if (($reg['payment_id'] ?? '') === $paymentId) {
                return $reg;
            }
        }
        return null;
    }

    public function listForEvent(string $eventId): array
    {
        return array_values(array_filter($this->listAll(), function ($reg) use ($eventId) {
            return ($reg['event_id'] ?? '') === $eventId && in_array($reg['status'] ?? '', ['confirmed', 'paid', 'pending'], true);
        }));
    }

    public function countForEvent(string $eventId, ?string $ticketType = null): int
    {
        $regs = $this->listForEvent($eventId);
        if ($ticketType !== null) {
            $regs = array_filter($regs, function ($reg) use ($ticketType) {
                return ($reg['ticket_type'] ?? '') === $ticketType;
            });
        }
        return count($regs);
    }

    public function listAll(): array
    {
        $items = [];
        foreach (glob($this->baseDir . '/*.json') ?: [] as $file) {
            $data = json_decode((string) file_get_contents($file), true);
            if (is_array($data)) {
                $items[] = $data;
            }
        }
        usort($items, function ($a, $b) {
            return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
        });
        return $items;
    }

    private function path(string $id): string
    {
        return $this->baseDir . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $id) . '.json';
    }

    private function write(string $id, array $record): void
    {
        file_put_contents($this->path($id), json_encode($record, JSON_PRETTY_PRINT));
    }
}
