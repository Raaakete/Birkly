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
    $data = base64_decode($encoded, true);
    if ($data === false || strlen($data) <= 16) {
        return false;
    }
    return openssl_decrypt(substr($data, 16), 'AES-256-CBC', hex2bin($key), 0, substr($data, 0, 16));
}
