<?php
/**
 * Symmetric encryption for instance secrets stored in settings/ (P59-M3).
 *
 * Shared by AI connection keys and project git credentials so there is one
 * key and one on-disk format. The key lives in settings/security.json and is
 * generated on first use.
 */

declare(strict_types=1);

require_once __DIR__ . '/birkly_paths.php';
require_once __DIR__ . '/storage/StorageFactory.php';

function birkly_secret_storage(): Storage
{
    return new Storage(birkly_project_root() . '/settings');
}

function birkly_secret_encryption_key(): string
{
    $envKey = birkly_secret_key_from_env();
    if ($envKey !== null) {
        return $envKey;
    }

    $storage = birkly_secret_storage();
    $security = $storage->read('security.json');
    if (!is_array($security)) {
        $security = [];
    }

    if (empty($security['encryption_key']) || !is_string($security['encryption_key'])) {
        $security['encryption_key'] = bin2hex(random_bytes(32));
        $storage->write('security.json', $security);
        trigger_error(
            'Birkly: BIRKLY_SECRETS_KEY is not set. A local encryption key was generated in settings/security.json. '
            . 'Set BIRKLY_SECRETS_KEY in production so secrets are not tied to a single disk.',
            E_USER_WARNING
        );
    }

    return (string) $security['encryption_key'];
}

function birkly_secret_key_from_env(): ?string
{
    $raw = $_ENV['BIRKLY_SECRETS_KEY'] ?? $_SERVER['BIRKLY_SECRETS_KEY'] ?? getenv('BIRKLY_SECRETS_KEY') ?: null;
    if (!is_string($raw) || $raw === '') {
        return null;
    }

    $raw = trim($raw);

    // 64-character hex string -> 32-byte raw key.
    if (strlen($raw) === 64 && ctype_xdigit($raw)) {
        return strtolower($raw);
    }

    // 32 raw bytes are accepted as-is (internal binary is not exposed).
    if (strlen($raw) === 32) {
        return bin2hex($raw);
    }

    // Base64-encoded 32-byte key.
    $decoded = base64_decode($raw, true);
    if ($decoded !== false && strlen($decoded) === 32) {
        return bin2hex($decoded);
    }

    throw new RuntimeException('BIRKLY_SECRETS_KEY must be a 64-character hex string, 32 raw bytes, or base64-encoded 32-byte key');
}

function birkly_encrypt_secret(string $plaintext): string
{
    $key = birkly_secret_encryption_key();
    $iv = random_bytes(16);
    $encrypted = openssl_encrypt($plaintext, 'AES-256-CBC', hex2bin($key), 0, $iv);
    if ($encrypted === false) {
        throw new RuntimeException('Could not encrypt secret');
    }
    return base64_encode($iv . $encrypted);
}

/**
 * @return string|false Plaintext, or false when the payload cannot be decrypted.
 */
function birkly_decrypt_secret(string $encoded)
{
    if ($encoded === '') {
        return false;
    }
    $key = birkly_secret_encryption_key();
    return birkly_decrypt_secret_with_key($encoded, $key);
}

/**
 * @return string|false
 */
function birkly_decrypt_secret_with_key(string $encoded, string $keyHex)
{
    if ($encoded === '' || $keyHex === '') {
        return false;
    }
    $data = base64_decode($encoded, true);
    if ($data === false || strlen($data) <= 16) {
        return false;
    }
    $rawKey = hex2bin($keyHex);
    if ($rawKey === false) {
        return false;
    }
    return openssl_decrypt(substr($data, 16), 'AES-256-CBC', $rawKey, 0, substr($data, 0, 16));
}

function birkly_encrypt_secret_with_key(string $plaintext, string $keyHex): string
{
    $rawKey = hex2bin($keyHex);
    if ($rawKey === false) {
        throw new RuntimeException('Invalid encryption key');
    }
    $iv = random_bytes(16);
    $encrypted = openssl_encrypt($plaintext, 'AES-256-CBC', $rawKey, 0, $iv);
    if ($encrypted === false) {
        throw new RuntimeException('Could not encrypt secret');
    }
    return base64_encode($iv . $encrypted);
}

/**
 * Rotate settings/security.json encryption_key and re-encrypt all instance secrets.
 *
 * Encrypts: connection secrets (SMTP/API credentials), project git credentials,
 * and AI provider api_key_encrypted fields. Does not encrypt collection api_keys
 * (stored plaintext in collection.json) or user password hashes.
 *
 * @return array{ok: bool, message: string, new_key?: string, reencrypted?: array<string, int>}
 */
function birkly_rotate_instance_encryption_key(): array
{
    if (birkly_secret_key_from_env() !== null) {
        return [
            'ok' => false,
            'message' => 'BIRKLY_SECRETS_KEY env override is active. Update the env value and re-encrypt stored secrets manually.',
        ];
    }

    $storage = birkly_secret_storage();
    $security = $storage->read('security.json');
    if (!is_array($security)) {
        $security = [];
    }

    $oldKey = (string) ($security['encryption_key'] ?? '');
    if ($oldKey === '' || strlen($oldKey) !== 64 || !ctype_xdigit($oldKey)) {
        return ['ok' => false, 'message' => 'No valid encryption_key in security.json'];
    }

    $newKey = bin2hex(random_bytes(32));
    $counts = [
        'connections' => 0,
        'project_git' => 0,
        'ai_users' => 0,
    ];

    require_once __DIR__ . '/connections/registry.php';
    $connStore = birkly_connections_load();
    foreach ($connStore['connections'] as $idx => $connection) {
        if (empty($connection['secrets_ciphertext']) || !is_string($connection['secrets_ciphertext'])) {
            continue;
        }
        $plain = birkly_decrypt_secret_with_key($connection['secrets_ciphertext'], $oldKey);
        if ($plain === false) {
            return ['ok' => false, 'message' => 'Failed to decrypt connection secrets during rotation'];
        }
        $connStore['connections'][$idx]['secrets_ciphertext'] = birkly_encrypt_secret_with_key($plain, $newKey);
        $counts['connections']++;
    }
    if ($counts['connections'] > 0 && !birkly_connections_save($connStore)) {
        return ['ok' => false, 'message' => 'Failed to save re-encrypted connections'];
    }

    require_once __DIR__ . '/project_git_settings.php';
    $git = birkly_project_git_read();
    $gitPatch = [];
    foreach (['token_encrypted', 'ssh_key_encrypted', 'nostr_key_encrypted'] as $field) {
        if (empty($git[$field]) || !is_string($git[$field])) {
            continue;
        }
        $plain = birkly_decrypt_secret_with_key($git[$field], $oldKey);
        if ($plain === false) {
            return ['ok' => false, 'message' => 'Failed to decrypt project git credentials during rotation'];
        }
        $gitPatch[$field] = birkly_encrypt_secret_with_key($plain, $newKey);
        $counts['project_git']++;
    }
    if ($counts['project_git'] > 0 && !birkly_project_git_write($gitPatch)) {
        return ['ok' => false, 'message' => 'Failed to save re-encrypted project git credentials'];
    }

    $usersDir = birkly_project_root() . '/users';
    if (is_dir($usersDir)) {
        foreach (scandir($usersDir) ?: [] as $file) {
            if (!str_ends_with($file, '.json')) {
                continue;
            }
            $path = $usersDir . '/' . $file;
            $user = json_decode((string) file_get_contents($path), true);
            if (!is_array($user) || empty($user['api_key_encrypted']) || !is_string($user['api_key_encrypted'])) {
                continue;
            }
            $plain = birkly_decrypt_secret_with_key($user['api_key_encrypted'], $oldKey);
            if ($plain === false) {
                return ['ok' => false, 'message' => 'Failed to decrypt AI user api_key_encrypted during rotation'];
            }
            $user['api_key_encrypted'] = birkly_encrypt_secret_with_key($plain, $newKey);
            if (file_put_contents($path, json_encode($user, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) {
                return ['ok' => false, 'message' => 'Failed to save re-encrypted AI user record'];
            }
            $counts['ai_users']++;
        }
    }

    $security['encryption_key'] = $newKey;
    $security['_updated_at'] = date('c');
    if (!$storage->write('security.json', $security)) {
        return ['ok' => false, 'message' => 'Failed to write new encryption_key to security.json'];
    }

    return [
        'ok' => true,
        'message' => 'Encryption key rotated and secrets re-encrypted',
        'reencrypted' => $counts,
    ];
}
