<?php
/**
 * Connection-secret wrapper around core/secret_box.php.
 *
 * Secrets stored in connection records are encrypted at rest using the
 * instance encryption key (settings/security.json) or the BIRKLY_SECRETS_KEY
 * env override.
 */

declare(strict_types=1);

require_once __DIR__ . '/../secret_box.php';

/**
 * Encrypt a connection secret value before storing it.
 */
function birkly_connection_encrypt_secret(array $secrets): string
{
    return birkly_encrypt_secret(json_encode($secrets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

/**
 * Decrypt a connection secret payload. Returns null on failure.
 *
 * @return array|null
 */
function birkly_connection_decrypt_secret(?string $ciphertext): ?array
{
    if ($ciphertext === null || $ciphertext === '') {
        return null;
    }
    $plaintext = birkly_decrypt_secret($ciphertext);
    if ($plaintext === false) {
        return null;
    }
    $decoded = json_decode($plaintext, true);
    return is_array($decoded) ? $decoded : null;
}
