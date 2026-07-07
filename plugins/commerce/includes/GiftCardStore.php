<?php
/**
 * Flat-file gift card / store credit storage (P11-M3).
 */

class GiftCardStore
{
    private string $baseDir;

    public function __construct(?string $baseDir = null)
    {
        if ($baseDir !== null) {
            $this->baseDir = rtrim($baseDir, '/');
        } else {
            $this->baseDir = commerce_data_dir() . '/gift_cards';
        }
        if (!is_dir($this->baseDir)) {
            mkdir($this->baseDir, 0755, true);
        }
    }

    public function issue(array $options): array
    {
        $code = strtoupper(trim((string) ($options['code'] ?? $this->generateCode())));
        if ($this->getByCode($code) !== null) {
            throw new InvalidArgumentException('Gift card code already exists');
        }
        $balance = max(0, (int) ($options['balance_cents'] ?? 0));
        if ($balance <= 0) {
            throw new InvalidArgumentException('balance_cents must be greater than zero');
        }
        $id = 'gc_' . bin2hex(random_bytes(6));
        $now = date('c');
        $record = [
            'id' => $id,
            'code' => $code,
            'initial_balance_cents' => $balance,
            'balance_cents' => $balance,
            'currency' => strtolower((string) ($options['currency'] ?? 'eur')),
            'status' => 'active',
            'expires_at' => $options['expires_at'] ?? null,
            'note' => trim((string) ($options['note'] ?? '')),
            'issued_by' => $options['issued_by'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
            'redemptions' => [],
        ];
        $this->write($id, $record);
        return $record;
    }

    public function getByCode(string $code): ?array
    {
        $code = strtoupper(trim($code));
        foreach ($this->listAll(false) as $card) {
            if (($card['code'] ?? '') === $code) {
                return $this->withDerivedStatus($card);
            }
        }
        return null;
    }

    public function listAll(bool $deriveStatus = true): array
    {
        $cards = [];
        foreach (glob($this->baseDir . '/*.json') ?: [] as $file) {
            $data = json_decode((string) file_get_contents($file), true);
            if (is_array($data)) {
                $cards[] = $deriveStatus ? $this->withDerivedStatus($data) : $data;
            }
        }
        usort($cards, fn ($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
        return $cards;
    }

    public function voidCard(string $code): bool
    {
        $card = $this->getByCode($code);
        if (!$card) {
            return false;
        }
        $this->update($card['id'], ['status' => 'void']);
        return true;
    }

    public function validateForRedeem(string $code): array
    {
        $card = $this->getByCode($code);
        if (!$card) {
            return ['ok' => false, 'error' => 'Gift card not found'];
        }
        $status = $card['status'] ?? 'active';
        if ($status === 'void') {
            return ['ok' => false, 'error' => 'Gift card has been voided'];
        }
        if ($status === 'expired') {
            return ['ok' => false, 'error' => 'Gift card has expired'];
        }
        if ($status === 'depleted' || (int) ($card['balance_cents'] ?? 0) <= 0) {
            return ['ok' => false, 'error' => 'Gift card has no remaining balance'];
        }
        return ['ok' => true, 'card' => $card];
    }

    public function redeem(string $code, int $amountCents, string $orderId): array
    {
        $amountCents = max(0, $amountCents);
        if ($amountCents <= 0) {
            return ['ok' => true, 'card' => $this->getByCode($code)];
        }
        $validation = $this->validateForRedeem($code);
        if (!($validation['ok'] ?? false)) {
            return $validation;
        }
        $card = $validation['card'];
        $balance = (int) ($card['balance_cents'] ?? 0);
        if ($amountCents > $balance) {
            return ['ok' => false, 'error' => 'Gift card balance insufficient'];
        }
        $remaining = $balance - $amountCents;
        $redemptions = $card['redemptions'] ?? [];
        $redemptions[] = ['order_id' => $orderId, 'amount_cents' => $amountCents, 'created_at' => date('c')];
        $patch = ['balance_cents' => $remaining, 'redemptions' => $redemptions];
        if ($remaining <= 0) {
            $patch['status'] = 'depleted';
        }
        return ['ok' => true, 'card' => $this->update($card['id'], $patch)];
    }

    public function update(string $id, array $patch): ?array
    {
        $file = $this->baseDir . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $id) . '.json';
        if (!is_file($file)) {
            return null;
        }
        $existing = json_decode((string) file_get_contents($file), true);
        if (!is_array($existing)) {
            return null;
        }
        $record = array_merge($existing, $patch, ['updated_at' => date('c')]);
        file_put_contents($file, json_encode($record, JSON_PRETTY_PRINT));
        return $this->withDerivedStatus($record);
    }

    private function withDerivedStatus(array $card): array
    {
        if (($card['status'] ?? '') === 'void') {
            return $card;
        }
        $expiresAt = $card['expires_at'] ?? null;
        if (is_string($expiresAt) && $expiresAt !== '' && strtotime($expiresAt) !== false && time() > strtotime($expiresAt)) {
            $card['status'] = 'expired';
            return $card;
        }
        if ((int) ($card['balance_cents'] ?? 0) <= 0) {
            $card['status'] = 'depleted';
        }
        return $card;
    }

    private function generateCode(): string
    {
        do {
            $code = 'GC-' . strtoupper(bin2hex(random_bytes(2))) . '-' . strtoupper(bin2hex(random_bytes(2)));
        } while ($this->getByCode($code) !== null);
        return $code;
    }

    private function write(string $id, array $record): void
    {
        file_put_contents($this->baseDir . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $id) . '.json', json_encode($record, JSON_PRETTY_PRINT));
    }
}
