<?php
/**
 * Unified payment activity log — source_plugin, source_ref, no secrets.
 */

class PaymentsActivityLog
{
    private string $logFile;
    private const MAX_ENTRIES = 10000;
    private static array $secretKeys = [
        'api_key', 'secret', 'password', 'token', 'credentials',
        'webhook_secret', 'client_secret', 'authorization',
    ];

    public function __construct(?string $dataDir = null)
    {
        if ($dataDir === null) {
            $pluginDir = dirname(__DIR__);
            $rootDir = dirname(dirname($pluginDir));
            $dataDir = $rootDir . '/data/plugins/payments';
        }
        if (!is_dir($dataDir . '/activity')) {
            mkdir($dataDir . '/activity', 0755, true);
        }
        $this->logFile = $dataDir . '/activity/log.json';
        if (!is_file($this->logFile)) {
            file_put_contents($this->logFile, json_encode([]));
        }
    }

    public function append(array $entry): array
    {
        $record = [
            'id' => 'act_' . bin2hex(random_bytes(8)),
            'timestamp' => date('c'),
            'type' => $entry['type'] ?? 'event',
            'provider' => $entry['provider'] ?? null,
            'status' => $entry['status'] ?? null,
            'source_plugin' => $entry['source_plugin'] ?? null,
            'source_ref' => $entry['source_ref'] ?? null,
            'payment_id' => $entry['payment_id'] ?? null,
            'amount_cents' => isset($entry['amount_cents']) ? (int) $entry['amount_cents'] : null,
            'currency' => $entry['currency'] ?? null,
            'message' => $entry['message'] ?? '',
            'metadata' => $this->sanitize($entry['metadata'] ?? []),
        ];

        $entries = $this->readAll();
        array_unshift($entries, $record);
        if (count($entries) > self::MAX_ENTRIES) {
            $entries = array_slice($entries, 0, self::MAX_ENTRIES);
        }
        file_put_contents($this->logFile, json_encode($entries, JSON_PRETTY_PRINT));

        return $record;
    }

    /** @return array<int, array<string, mixed>> */
    public function list(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $entries = $this->readAll();

        if (!empty($filters['provider'])) {
            $entries = array_values(array_filter($entries, fn($e) => ($e['provider'] ?? '') === $filters['provider']));
        }
        if (!empty($filters['status'])) {
            $entries = array_values(array_filter($entries, fn($e) => ($e['status'] ?? '') === $filters['status']));
        }
        if (!empty($filters['source_plugin'])) {
            $entries = array_values(array_filter($entries, fn($e) => ($e['source_plugin'] ?? '') === $filters['source_plugin']));
        }
        if (!empty($filters['type'])) {
            $entries = array_values(array_filter($entries, fn($e) => ($e['type'] ?? '') === $filters['type']));
        }

        return array_slice($entries, $offset, $limit);
    }

    public function stats(): array
    {
        $entries = $this->readAll();
        $now = time();
        $periods = [
            'today' => strtotime('today'),
            '7d' => $now - 7 * 86400,
            '30d' => $now - 30 * 86400,
        ];

        $result = [
            'total' => count($entries),
            'by_provider' => [],
            'success_rate' => 0,
            'volume' => ['today' => 0, '7d' => 0, '30d' => 0],
        ];

        $success = 0;
        $attempts = 0;

        foreach ($entries as $entry) {
            if (($entry['type'] ?? '') !== 'payment') {
                continue;
            }
            $attempts++;
            $status = $entry['status'] ?? '';
            if (in_array($status, ['succeeded', 'paid', 'completed', 'captured'], true)) {
                $success++;
            }

            $provider = $entry['provider'] ?? 'unknown';
            if (!isset($result['by_provider'][$provider])) {
                $result['by_provider'][$provider] = ['count' => 0, 'volume_cents' => 0];
            }
            $result['by_provider'][$provider]['count']++;

            $ts = strtotime($entry['timestamp'] ?? '');
            $amount = (int) ($entry['amount_cents'] ?? 0);
            if ($status === 'succeeded' || $status === 'paid') {
                foreach ($periods as $key => $since) {
                    if ($ts >= $since) {
                        $result['volume'][$key] += $amount;
                    }
                }
                $result['by_provider'][$provider]['volume_cents'] += $amount;
            }
        }

        $result['success_rate'] = $attempts > 0 ? round($success / $attempts * 100, 1) : 0;
        return $result;
    }

    private function readAll(): array
    {
        if (!is_file($this->logFile)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($this->logFile), true);
        return is_array($data) ? $data : [];
    }

    private function sanitize($value)
    {
        if (!is_array($value)) {
            if (is_string($value) && $this->looksSecret($value)) {
                return '[REDACTED]';
            }
            return $value;
        }
        $clean = [];
        foreach ($value as $key => $item) {
            if ($this->isSecretKey((string) $key)) {
                $clean[$key] = '[REDACTED]';
            } else {
                $clean[$key] = $this->sanitize($item);
            }
        }
        return $clean;
    }

    private function isSecretKey(string $key): bool
    {
        $lower = strtolower($key);
        foreach (self::$secretKeys as $secret) {
            if (strpos($lower, $secret) !== false) {
                return true;
            }
        }
        return false;
    }

    private function looksSecret(string $value): bool
    {
        return strlen($value) > 20 && preg_match('/^(sk_|pk_|whsec_|api_|Bearer )/i', $value);
    }
}
