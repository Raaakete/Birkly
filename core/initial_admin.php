<?php
/**
 * First admin account for a fresh install. Does nothing when an account already exists.
 */

declare(strict_types=1);

require_once __DIR__ . '/birkly_users.php';
require_once __DIR__ . '/../api/validate.php';

/**
 * @return array{ok: bool, status: string, message: string}
 */
function birkly_create_initial_admin(string $email, string $password): array
{
    $email = strtolower(trim($email));
    if (preg_match('/^[^\s@]+@[^\s@]+$/', $email) !== 1) {
        return ['ok' => false, 'status' => 'invalid_email', 'message' => 'A valid email is required'];
    }

    if (birkly_has_users()) {
        return [
            'ok' => true,
            'status' => 'exists',
            'message' => 'An admin account already exists. No account was created.',
        ];
    }

    $validation = validate_password_strength($password);
    if (empty($validation['valid'])) {
        return [
            'ok' => false,
            'status' => 'weak_password',
            'message' => (string) ($validation['message'] ?? 'Password does not meet requirements'),
        ];
    }

    $user = [
        'id' => bin2hex(random_bytes(16)),
        'email' => $email,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'role' => 'admin',
        'created_at' => date('c'),
    ];

    $usersDir = birkly_users_write_dir();
    if (!is_dir($usersDir) && !mkdir($usersDir, 0755, true) && !is_dir($usersDir)) {
        return ['ok' => false, 'status' => 'write_failed', 'message' => 'Could not create the users directory'];
    }

    $userFile = $usersDir . '/' . md5($email) . '.json';
    $json = json_encode($user, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents($userFile, $json . "\n") === false) {
        return ['ok' => false, 'status' => 'write_failed', 'message' => 'Could not write the admin account'];
    }

    return [
        'ok' => true,
        'status' => 'created',
        'message' => 'Admin account created for ' . $email,
    ];
}
