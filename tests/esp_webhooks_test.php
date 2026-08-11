<?php
/**
 * ESP inbound webhook and DNS hints tests (Phase 65 M5).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/birkly_paths.php';

$testRoot = sys_get_temp_dir() . '/birkly-esp-test-' . uniqid();
mkdir($testRoot, 0755, true);
mkdir($testRoot . '/settings', 0755, true);
mkdir($testRoot . '/logs', 0755, true);

$GLOBALS['__birkly_project_root'] = $testRoot;
putenv('BIRKLY_SECRETS_KEY=' . bin2hex(random_bytes(32)));

$GLOBALS['__HARU_INTERNAL_CALL'] = true;
require_once dirname(__DIR__) . '/api/webhooks.php';
require_once dirname(__DIR__) . '/core/connections/registry.php';
require_once dirname(__DIR__) . '/core/mail/dns_hints.php';

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

birkly_connections_save(['connections' => []]);

// Resend bounce.
$resend = birkly_connection_create([
    'type' => 'email',
    'provider' => 'resend',
    'alias' => 'resend',
    'name' => 'Resend',
    'config' => ['from_email' => 'test@example.com'],
    'secrets' => ['api_key' => 're_', 'signing_secret' => 'resend-secret'],
]);
assert_true('resend connection created', $resend['ok'] === true);

$payload = json_encode(['type' => 'email.bounced', 'data' => ['email' => 'bad@example.com', 'reason' => 'bounce']]);
$headers = ['Resend-Signature' => hash_hmac('sha256', $payload, 'resend-secret')];
$result = birkly_webhook_process_request('resend', 'POST', $payload, $headers);
assert_true('resend bounce returns ok', $result['status'] === 'ok');
assert_true('resend bounce suppressed', birkly_mail_suppression_check('bad@example.com') !== null);

// Postmark complaint.
$postmark = birkly_connection_create([
    'type' => 'email',
    'provider' => 'postmark',
    'alias' => 'postmark',
    'name' => 'Postmark',
    'config' => ['from_email' => 'test@example.com'],
    'secrets' => ['api_key' => 'pm-', 'signing_secret' => 'postmark-secret'],
]);
assert_true('postmark connection created', $postmark['ok'] === true);

$payload = json_encode(['Type' => 'SpamComplaint', 'Email' => 'spammer@example.com']);
$headers = ['X-Postmark-Signature' => hash_hmac('sha256', $payload, 'postmark-secret')];
$result = birkly_webhook_process_request('postmark', 'POST', $payload, $headers);
assert_true('postmark complaint returns ok', $result['status'] === 'ok');
assert_true('postmark complaint suppressed', birkly_mail_suppression_check('spammer@example.com') !== null);

// SES bounce (SNS wrapped).
$ses = birkly_connection_create([
    'type' => 'email',
    'provider' => 'ses',
    'alias' => 'ses',
    'name' => 'SES',
    'config' => ['from_email' => 'test@example.com', 'region' => 'us-east-1'],
    'secrets' => ['access_key' => 'AKIA', 'secret_key' => 'secret', 'signing_secret' => 'ses-secret'],
]);
assert_true('ses connection created', $ses['ok'] === true);

$message = json_encode([
    'notificationType' => 'Bounce',
    'bounce' => ['bouncedRecipients' => [['emailAddress' => 'ses-bounce@example.com']]],
]);
$payload = json_encode(['Type' => 'Notification', 'Message' => $message]);
$headers = ['X-Birkly-Signature' => hash_hmac('sha256', $payload, 'ses-secret')];
$result = birkly_webhook_process_request('ses', 'POST', $payload, $headers);
assert_true('ses bounce returns ok', $result['status'] === 'ok');
assert_true('ses bounce suppressed', birkly_mail_suppression_check('ses-bounce@example.com') !== null);

// Mailgun unsubscribe.
$mailgun = birkly_connection_create([
    'type' => 'email',
    'provider' => 'mailgun',
    'alias' => 'mailgun',
    'name' => 'Mailgun',
    'config' => ['from_email' => 'test@example.com', 'domain' => 'example.com'],
    'secrets' => ['api_key' => 'key-', 'signing_secret' => 'mailgun-secret'],
]);
assert_true('mailgun connection created', $mailgun['ok'] === true);

$timestamp = (string) time();
$token = 'token123';
$signature = hash_hmac('sha256', $timestamp . $token, 'mailgun-secret');
$payload = json_encode(['event' => 'unsubscribed', 'recipient' => 'unsub@example.com', 'timestamp' => $timestamp, 'token' => $token, 'signature' => $signature]);
$headers = [];
$result = birkly_webhook_process_request('mailgun', 'POST', $payload, $headers);
assert_true('mailgun unsubscribe returns ok', $result['status'] === 'ok');
assert_true('mailgun unsubscribe suppressed', birkly_mail_suppression_check('unsub@example.com') !== null);

// Invalid Mailgun signature rejected.
$badPayload = json_encode(['event' => 'bounced', 'recipient' => 'bad@example.com', 'timestamp' => $timestamp, 'token' => $token, 'signature' => 'invalid']);
$result = birkly_webhook_process_request('mailgun', 'POST', $badPayload, $headers);
assert_true('invalid mailgun signature rejected', $result['status'] === 'error');

// DNS hints.
$hints = birkly_mail_dns_hints('resend', 'example.com');
assert_true('dns hints include records', !empty($hints['records']));
assert_true('dns hints include note', !empty($hints['note']));
assert_true('dns hints include spf', stripos(json_encode($hints), 'spf') !== false);

rrmdir($testRoot);

echo "\nesp_webhooks_test: " . ($failures === 0 ? "PASS\n" : "FAIL ($failures)\n");
exit($failures === 0 ? 0 : 1);
