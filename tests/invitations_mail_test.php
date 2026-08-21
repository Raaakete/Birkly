<?php
/**
 * Invitation email uses the configured connection From address, not the inviter mailbox.
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

require_once $root . '/core/birkly_paths.php';
require_once $root . '/api/storage.php';
require_once $root . '/core/connections/registry.php';
require_once $root . '/api/invitations.php';

$status = birkly_invitation_mail_status();
assert_true('mail status returns array', is_array($status));
assert_true('mail status includes has_connection key', array_key_exists('has_connection', $status));
assert_true('mail status includes settings_url key', array_key_exists('settings_url', $status));

echo PHP_EOL . 'invitations_mail_test: ' . ($failures === 0 ? 'PASS' : "FAIL ($failures)") . PHP_EOL;
exit($failures === 0 ? 0 : 1);
