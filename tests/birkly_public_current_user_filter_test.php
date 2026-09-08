<?php
/**
 * P82-M3 — birkly_public_current_user filter tests.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/birkly_data_paths.php';
require_once $root . '/core/plugins.php';
require_once $root . '/core/public_current_user.php';

$failures = [];

function assert_true(string $label, bool $condition): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $label;
        fwrite(STDERR, "FAIL: $label\n");
        return;
    }
    echo "OK: $label\n";
}

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
$_SESSION['user'] = ['id' => 'session-user', 'email' => 'session@example.com'];

add_filter('birkly_public_current_user', static function (?array $user): ?array {
    return [
        'id' => 'filtered-user',
        'email' => 'filtered@example.com',
    ];
}, 10, 'test_plugin');

$resolved = birkly_public_current_user();
assert_true('filter overrides session user', ($resolved['id'] ?? '') === 'filtered-user');
assert_true('birkly_public_current_user_id returns filtered id', birkly_public_current_user_id() === 'filtered-user');

add_filter('birkly_entries_view_filter', static function (array $entries, string $userId, string $slug): array {
    if ($userId !== 'filtered-user') {
        return $entries;
    }
    return array_values(array_filter($entries, static fn(array $e): bool => ($e['id'] ?? '') !== 'hidden'));
}, 10, 'test_plugin');

$filtered = birkly_filter_entries_for_current_user([
    ['id' => 'visible'],
    ['id' => 'hidden'],
], 'blog');
assert_true('entries view filter applied', count($filtered) === 1 && ($filtered[0]['id'] ?? '') === 'visible');

unset($_SESSION['user']);

if ($failures !== []) {
    exit(1);
}

echo "birkly_public_current_user_filter_test: OK\n";
