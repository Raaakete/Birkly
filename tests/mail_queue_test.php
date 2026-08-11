<?php
/**
 * Mail queue, delivery log, and suppression tests (Phase 65 M4).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/birkly_paths.php';

$testRoot = sys_get_temp_dir() . '/birkly-queue-test-' . uniqid();
mkdir($testRoot, 0755, true);
mkdir($testRoot . '/settings', 0755, true);
mkdir($testRoot . '/logs', 0755, true);

$GLOBALS['__birkly_project_root'] = $testRoot;
putenv('BIRKLY_SECRETS_KEY=' . bin2hex(random_bytes(32)));

require_once dirname(__DIR__) . '/core/connections/registry.php';
require_once dirname(__DIR__) . '/core/mail/suppressions.php';
require_once dirname(__DIR__) . '/core/mail/queue.php';
require_once dirname(__DIR__) . '/core/mail/birkly_send_mail.php';

$failures = 0;

function assert_true(string $name, bool $ok, string $detail = ''): void
{
    global $failures;
    echo $name . ': ' . ($ok ? "PASS\n" : "FAIL\n");
    if (!$ok) {
        $failures++;
        if ($detail !== '') {
            echo '  ' . $detail . "\n";
        }
    }
}

function rrmdir(string $dir): void
{
    if (!is_dir($dir)) return;
    $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
    }
    rmdir($dir);
}

// Suppression list.
assert_true('suppression add succeeds', birkly_mail_suppression_add('bounced@example.com', 'bounce', 'test') === true);
assert_true('suppression check finds address', birkly_mail_suppression_check('bounced@example.com') !== null);
assert_true('suppression check misses unknown', birkly_mail_suppression_check('good@example.com') === null);

// Sending to suppressed address returns suppressed.
$result = birkly_send_mail([
    'to' => 'bounced@example.com',
    'subject' => 'Test',
    'html' => '<p>Test</p>',
]);
assert_true('send to suppressed returns suppressed', $result['status'] === 'suppressed');

// Mixed recipients: send to allowed only.
birkly_mail_suppression_add('bad@example.com', 'complaint', 'test');
$result = birkly_send_mail([
    'to' => ['good@example.com', 'bad@example.com'],
    'subject' => 'Mixed',
    'html' => '<p>Mixed</p>',
]);
assert_true('mixed recipients accepted for allowed', $result['status'] === 'accepted');

// Queue push against a deliberately failing SMTP connection.
birkly_connection_create([
    'alias' => 'failing-smtp',
    'provider' => 'smtp',
    'host' => 'invalid-smtp.example.test',
    'port' => 587,
    'encryption' => 'tls',
    'username' => 'user',
    'password' => 'secret',
    'from_email' => 'test@example.com',
], 'email');

$push = birkly_mail_queue_push([
    'to' => 'user@example.com',
    'subject' => 'Queued',
    'html' => '<p>Queued</p>',
], 'failing-smtp', ['max_attempts' => 2]);
assert_true('queue push succeeds', $push['ok'] === true);
assert_true('queue item has id', !empty($push['item']['id']));

$counts = birkly_mail_queue_count();
assert_true('queue count shows one queued', $counts['queued'] === 1);

// Process queue with no configured connection: native mail() fallback or log? Since no default, native fallback. In CLI mail() may not work, result failed.
$process = birkly_mail_queue_process(10);
assert_true('queue process ran', $process['processed'] === 1);

// After processing, item should be failed (max attempts reached after one try? Actually attempts becomes 1, result failed, not >= max 2, so still queued with next_attempt in future).
$counts = birkly_mail_queue_count();
assert_true('queue item still queued after one failure', $counts['queued'] === 1);

// Force retry by setting next_attempt to past.
$items = birkly_mail_queue_load();
if (!empty($items[0])) {
    $items[0]['next_attempt'] = time() - 1;
    birkly_mail_queue_save($items);
}
$process = birkly_mail_queue_process(10);
assert_true('queue process second attempt', $process['processed'] === 1);
$counts = birkly_mail_queue_count();
assert_true('queue item failed after max attempts', $counts['failed'] === 1);

// Delivery log exists.
$deliveryLog = $testRoot . '/logs/mail-deliveries.jsonl';
assert_true('delivery log file exists', file_exists($deliveryLog) && filesize($deliveryLog) > 0);

// Remove suppression.
assert_true('suppression remove succeeds', birkly_mail_suppression_remove('bounced@example.com') === true);
assert_true('suppression removed', birkly_mail_suppression_check('bounced@example.com') === null);

rrmdir($testRoot);

echo "\nmail_queue_test: " . ($failures === 0 ? "PASS\n" : "FAIL ($failures)\n");
exit($failures === 0 ? 0 : 1);
