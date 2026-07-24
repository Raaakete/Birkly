<?php
/**
 * Encrypted credential storage for payment connections.
 * Keys stored in data/plugins/commerce/ — never logged to activity.
 * Legacy data/plugins/payments/ is migrated on first bootstrap via commerce_migrate_legacy_data().
 */

class PaymentsCredentialStore
{
    private string $baseDir;
    private string $encryptionKey;

    public function __construct(?string $baseDir = null)
    {
        if ($baseDir !== null) {
            $this->baseDir = rtrim($baseDir, '/');
        } else {
            $pluginDir = dirname(__DIR__);
            $rootDir = dirname(dirname($pluginDir));
            $this->baseDir = $rootDir . '/data/plugins/commerce';
            if (function_exists('commerce_migrate_legacy_data')) {
                commerce_migrate_legacy_data();
            }
        }
        $this->ensureDirectories();
        $this->encryptionKey = $this->getEncryptionKey();
    }

    public function getDataDir(): string
    {
        return $this->baseDir;
    }

    private function ensureDirectories(): void
    {
        foreach ([
            $this->baseDir,
            $this->baseDir . '/connections',
            $this->baseDir . '/activity',
            $this->baseDir . '/webhooks',
        ] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    private function getEncryptionKey(): string
    {
        $keyFile = $this->baseDir . '/encryption_key.txt';
        if (is_file($keyFile)) {
            return trim((string) file_get_contents($keyFile));
        }
        $key = hash('sha256', random_bytes(32), true);
        file_put_contents($keyFile, base64_encode($key));
        chmod($keyFile, 0600);
        return base64_encode($key);
    }

    private function keyBytes(): string
    {
        $raw = base64_decode($this->encryptionKey, true);
        if ($raw === false || strlen($raw) < 32) {
            return hash('sha256', $this->encryptionKey, true);
        }
        return substr($raw, 0, 32);
    }

    public function encrypt(string $plaintext): string
    {
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($plaintext, 'aes-256-cbc', $this->keyBytes(), OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $encrypted);
    }

    public function decrypt(string $ciphertext): string
    {
        $raw = base64_decode($ciphertext, true);
        if ($raw === false || strlen($raw) < 17) {
            return '';
        }
        $iv = substr($raw, 0, 16);
        $encrypted = substr($raw, 16);
        $decrypted = openssl_decrypt($encrypted, 'aes-256-cbc', $this->keyBytes(), OPENSSL_RAW_DATA, $iv);
        return $decrypted === false ? '' : $decrypted;
    }

    public function encryptArray(array $data): string
    {
        return $this->encrypt(json_encode($data));
    }

    public function decryptArray(string $ciphertext): array
    {
        $json = $this->decrypt($ciphertext);
        if ($json === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    /** @return array<string, mixed> */
    public function listConnections(): array
    {
        $connections = [];
        $dir = $this->baseDir . '/connections';
        if (!is_dir($dir)) {
            return [];
        }
        foreach (glob($dir . '/*.json') as $file) {
            $meta = json_decode((string) file_get_contents($file), true);
            if (!is_array($meta)) {
                continue;
            }
            unset($meta['credentials_enc'], $meta['webhook_secret_enc']);
            $connections[$meta['id']] = $meta;
        }
        return $connections;
    }

    public function getConnection(string $id, bool $includeSecrets = false): ?array
    {
        $file = $this->baseDir . '/connections/' . $id . '.json';
        if (!is_file($file)) {
            return null;
        }
        $meta = json_decode((string) file_get_contents($file), true);
        if (!is_array($meta)) {
            return null;
        }
        if ($includeSecrets) {
            if (!empty($meta['credentials_enc'])) {
                $meta['credentials'] = $this->decryptArray($meta['credentials_enc']);
            }
            if (!empty($meta['webhook_secret_enc'])) {
                $meta['webhook_secret'] = $this->decrypt($meta['webhook_secret_enc']);
            }
        }
        unset($meta['credentials_enc'], $meta['webhook_secret_enc']);
        return $meta;
    }

    public function saveConnection(array $connection): array
    {
        $id = $connection['id'] ?? ('conn_' . bin2hex(random_bytes(8)));
        $now = date('c');

        $stored = [
            'id' => $id,
            'provider' => $connection['provider'] ?? '',
            'label' => $connection['label'] ?? '',
            'mode' => $connection['mode'] ?? 'test',
            'enabled' => (bool) ($connection['enabled'] ?? true),
            'created_at' => $connection['created_at'] ?? $now,
            'updated_at' => $now,
        ];

        if (isset($connection['credentials']) && is_array($connection['credentials'])) {
            $stored['credentials_enc'] = $this->encryptArray($connection['credentials']);
        } elseif (!empty($connection['credentials_enc'])) {
            $stored['credentials_enc'] = $connection['credentials_enc'];
        }

        if (isset($connection['webhook_secret']) && $connection['webhook_secret'] !== '') {
            $stored['webhook_secret_enc'] = $this->encrypt((string) $connection['webhook_secret']);
        } elseif (!empty($connection['webhook_secret_enc'])) {
            $stored['webhook_secret_enc'] = $connection['webhook_secret_enc'];
        }

        $file = $this->baseDir . '/connections/' . $id . '.json';
        file_put_contents($file, json_encode($stored, JSON_PRETTY_PRINT));
        chmod($file, 0600);

        unset($stored['credentials_enc'], $stored['webhook_secret_enc']);
        return $stored;
    }

    public function deleteConnection(string $id): bool
    {
        $file = $this->baseDir . '/connections/' . $id . '.json';
        if (is_file($file)) {
            return unlink($file);
        }
        return false;
    }

    public function getCredentialsForConnection(string $id): ?array
    {
        $conn = $this->getConnection($id, true);
        if (!$conn || empty($conn['credentials'])) {
            return null;
        }
        return $conn['credentials'];
    }

    /** Find first enabled connection for a provider */
    public function getActiveConnectionForProvider(string $providerId): ?array
    {
        foreach ($this->listConnections() as $conn) {
            if (($conn['provider'] ?? '') === $providerId && !empty($conn['enabled'])) {
                return $this->getConnection($conn['id'], true);
            }
        }
        return null;
    }

    public function listConfiguredProviders(): array
    {
        $providers = [];
        foreach ($this->listConnections() as $conn) {
            if (!empty($conn['enabled'])) {
                $providers[] = $conn['provider'];
            }
        }
        return array_values(array_unique($providers));
    }
}
