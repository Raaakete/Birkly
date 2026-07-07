<?php
/** B2B quote storage (P11-M4): quote → pending_payment → paid */

class CommerceQuoteStore
{
    private string $baseDir;

    public function __construct(?string $baseDir = null)
    {
        $this->baseDir = $baseDir !== null ? rtrim($baseDir, '/') : commerce_data_dir() . '/quotes';
        if (!is_dir($this->baseDir)) {
            mkdir($this->baseDir, 0755, true);
        }
    }

    public function create(array $quote): array
    {
        $id = $quote['id'] ?? ('cquote_' . bin2hex(random_bytes(8)));
        $token = $quote['token'] ?? bin2hex(random_bytes(16));
        $lines = array_values($quote['lines'] ?? []);
        $total = (int) ($quote['total_cents'] ?? $this->sumLines($lines));
        $record = array_merge([
            'id' => $id, 'token' => $token, 'status' => 'quote', 'created_at' => date('c'), 'updated_at' => date('c'),
            'expires_at' => $quote['expires_at'] ?? date('c', strtotime('+30 days')), 'accepted_at' => null, 'paid_at' => null,
            'customer_email' => trim((string) ($quote['customer_email'] ?? '')), 'note' => trim((string) ($quote['note'] ?? '')),
            'lines' => $lines, 'total_cents' => $total, 'currency' => strtolower((string) ($quote['currency'] ?? 'eur')),
            'order_id' => null, 'created_by' => $quote['created_by'] ?? null,
        ], $quote);
        $record['id'] = $id;
        $record['token'] = $token;
        $record['lines'] = $lines;
        $record['total_cents'] = $total;
        $this->write($id, $record);
        return $record;
    }

    public function createFromBuiltCart(array $built, string $customerEmail = '', array $lineOverrides = [], array $meta = []): array
    {
        $lines = [];
        foreach ($built['lines'] ?? [] as $line) {
            $lineId = $line['line_id'] ?? ($line['collection_slug'] ?? '') . ':' . ($line['entry_id'] ?? '');
            $unit = isset($lineOverrides[$lineId]) ? max(0, (int) $lineOverrides[$lineId]) : (int) ($line['effective_unit_cents'] ?? $line['unit_price_cents'] ?? 0);
            $qty = max(1, (int) ($line['quantity'] ?? 1));
            $lineTotal = $unit * $qty;
            $lines[] = array_merge($line, [
                'line_id' => $lineId, 'unit_price_cents' => $unit, 'effective_unit_cents' => $unit,
                'line_total_cents' => $lineTotal, 'effective_amount_cents' => $lineTotal,
            ]);
        }
        return $this->create(array_merge($meta, [
            'customer_email' => $customerEmail, 'lines' => $lines,
            'total_cents' => $this->sumLines($lines) + (int) ($built['shipping_cents'] ?? 0) + (int) ($built['tax_cents'] ?? 0),
            'shipping_cents' => (int) ($built['shipping_cents'] ?? 0), 'tax_cents' => (int) ($built['tax_cents'] ?? 0),
            'discount_cents' => (int) ($built['discount_cents'] ?? 0), 'currency' => $built['currency'] ?? 'eur',
        ]));
    }

    public function findByToken(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }
        foreach ($this->listAll(false) as $quote) {
            if (($quote['token'] ?? '') === $token) {
                return $this->withDerivedStatus($quote);
            }
        }
        return null;
    }

    public function get(string $id): ?array
    {
        $file = $this->path($id);
        if (!is_file($file)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($file), true);
        return is_array($data) ? $this->withDerivedStatus($data) : null;
    }

    public function listAll(bool $deriveStatus = true): array
    {
        $quotes = [];
        foreach (glob($this->baseDir . '/*.json') ?: [] as $file) {
            $data = json_decode((string) file_get_contents($file), true);
            if (is_array($data)) {
                $quotes[] = $deriveStatus ? $this->withDerivedStatus($data) : $data;
            }
        }
        usort($quotes, fn ($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
        return $quotes;
    }

    public function accept(string $token): array
    {
        $quote = $this->findByToken($token);
        if (!$quote) {
            return ['ok' => false, 'error' => 'Quote not found'];
        }
        $status = $quote['status'] ?? '';
        if (in_array($status, ['expired', 'canceled', 'paid'], true)) {
            return ['ok' => false, 'error' => 'Quote is not available'];
        }
        if ($status === 'pending_payment') {
            return ['ok' => true, 'quote' => $quote];
        }
        return ['ok' => true, 'quote' => $this->update($quote['id'], ['status' => 'pending_payment', 'accepted_at' => date('c')])];
    }

    public function markPaid(string $id, string $orderId): ?array
    {
        return $this->update($id, ['status' => 'paid', 'paid_at' => date('c'), 'order_id' => $orderId]);
    }

    public function cancel(string $id): bool
    {
        $quote = $this->get($id);
        if (!$quote || ($quote['status'] ?? '') === 'paid') {
            return false;
        }
        $this->update($id, ['status' => 'canceled']);
        return true;
    }

    public function update(string $id, array $patch): ?array
    {
        $existing = $this->get($id);
        if (!$existing) {
            return null;
        }
        $record = array_merge($existing, $patch, ['updated_at' => date('c')]);
        $this->write($id, $record);
        return $this->withDerivedStatus($record);
    }

    public function acceptUrl(string $token): string
    {
        return function_exists('birkly_url')
            ? birkly_url('/api/index.php?route=commerce&action=get_quote&token=' . urlencode($token))
            : '/api/index.php?route=commerce&action=get_quote&token=' . urlencode($token);
    }

    private function sumLines(array $lines): int
    {
        $t = 0;
        foreach ($lines as $line) {
            $t += (int) ($line['line_total_cents'] ?? $line['effective_amount_cents'] ?? 0);
        }
        return $t;
    }

    private function withDerivedStatus(array $quote): array
    {
        if (in_array($quote['status'] ?? '', ['paid', 'canceled'], true)) {
            return $quote;
        }
        $exp = $quote['expires_at'] ?? null;
        if (is_string($exp) && $exp !== '' && strtotime($exp) !== false && time() > strtotime($exp)) {
            $quote['status'] = 'expired';
        }
        return $quote;
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
