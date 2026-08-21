<?php
/**
 * Invitation list helpers for Team Access overview.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$GLOBALS['__HARU_INTERNAL_CALL'] = true;

$failures = 0;

function assert_true(bool $cond, string $label): void
{
    global $failures;
    if ($cond) {
        echo "OK  $label\n";
        return;
    }
    echo "FAIL $label\n";
    $failures++;
}

require_once $root . '/api/storage.php';
require_once $root . '/api/invitations.php';

assert_true('pending invite stays pending', birkly_invitation_effective_status([
    'status' => 'pending',
    'expires_at' => date('c', strtotime('+1 day')),
]) === 'pending');

assert_true('expired invite detected', birkly_invitation_effective_status([
    'status' => 'pending',
    'expires_at' => date('c', strtotime('-1 day')),
]) === 'expired');

assert_true('delivery label for link only', birkly_invitation_delivery_label([
    'delivery' => 'link_only',
]) === 'Link only');

echo PHP_EOL . 'invitations_list_test: ' . ($failures === 0 ? 'PASS' : "FAIL ($failures)") . PHP_EOL;
exit($failures === 0 ? 0 : 1);
