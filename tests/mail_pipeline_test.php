<?php
/**
 * Central mail pipeline tests (Phase 65).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/birkly_paths.php';

$testRoot = sys_get_temp_dir() . '/birkly-mail-test-' . uniqid();
mkdir($testRoot, 0755, true);
mkdir($testRoot . '/settings', 0755, true);
mkdir($testRoot . '/logs', 0755, true);

$GLOBALS['__birkly_project_root'] = $testRoot;
putenv('BIRKLY_SECRETS_KEY=' . bin2hex(random_bytes(32)));

require_once dirname(__DIR__) . '/core/connections/registry.php';
require_once dirname(__DIR__) . '/core/mail/birkly_send_mail.php';
require_once dirname(__DIR__) . '/core/mail/mime.php';

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

birkly_connections_save(['connections' => []]);

// Log transport.
$logConn = birkly_connection_create([
    'type' => 'email',
    'provider' => 'log',
    'alias' => 'mail-log',
    'name' => 'Mail Log',
    'from_email' => 'log@example.com',
]);
assert_true('log connection created', $logConn['ok'] === true);

$result = birkly_send_mail([
    'to' => 'user@example.com',
    'subject' => 'Log test',
    'html' => '<p>Hello</p>',
], 'mail-log');
assert_true('log transport returns logged', $result['status'] === 'logged');
assert_true('log transport provider is log', $result['provider'] === 'log');
$mailLog = $testRoot . '/logs/mail.jsonl';
assert_true('mail log file created', file_exists($mailLog) && filesize($mailLog) > 0);

// Default fallback log with no explicit alias.
$result2 = birkly_send_mail([
    'to' => 'default@example.com',
    'subject' => 'Default test',
    'html' => '<p>Default</p>',
]);
assert_true('default connection returns logged', $result2['status'] === 'logged');

// Missing recipient validation.
$bad = birkly_send_mail(['to' => '', 'subject' => 'x', 'html' => 'y']);
assert_true('missing recipient fails', $bad['status'] === 'failed');

// Invalid email validation.
$badEmail = birkly_send_mail(['to' => 'not-an-email', 'subject' => 'x', 'html' => 'y']);
assert_true('invalid email fails', $badEmail['status'] === 'failed');

// SMTP transport fails to connect to non-routable host quickly.
$smtpConn = birkly_connection_create([
    'type' => 'email',
    'provider' => 'smtp',
    'alias' => 'bad-smtp',
    'name' => 'Bad SMTP',
    'from_email' => 'smtp@example.com',
    'config' => ['host' => '127.0.0.1', 'port' => 65530, 'encryption' => 'none'],
]);
assert_true('smtp connection created', $smtpConn['ok'] === true);
$smtpResult = birkly_send_mail([
    'to' => 'user@example.com',
    'subject' => 'SMTP test',
    'html' => '<p>SMTP</p>',
], 'bad-smtp');
assert_true('smtp transport reports failure honestly', $smtpResult['status'] === 'failed');
assert_true('smtp failure includes stage', !empty($smtpResult['details']['stage']));

// Resend adapter fails with missing key.
$resendConn = birkly_connection_create([
    'type' => 'email',
    'provider' => 'resend',
    'alias' => 'resend-test',
    'name' => 'Resend Test',
    'from_email' => 'resend@example.com',
]);
assert_true('resend connection created', $resendConn['ok'] === true);
$resendResult = birkly_send_mail([
    'to' => 'user@example.com',
    'subject' => 'Resend test',
    'html' => '<p>Resend</p>',
], 'resend-test');
assert_true('resend adapter reports failure honestly', $resendResult['status'] === 'failed');
assert_true('resend failure mentions api key', str_contains(strtolower($resendResult['error'] ?? ''), 'api key'));

// Postmark adapter fails with missing key.
$postmarkConn = birkly_connection_create([
    'type' => 'email',
    'provider' => 'postmark',
    'alias' => 'postmark-test',
    'name' => 'Postmark Test',
    'from_email' => 'postmark@example.com',
]);
assert_true('postmark connection created', $postmarkConn['ok'] === true);
$postmarkResult = birkly_send_mail([
    'to' => 'user@example.com',
    'subject' => 'Postmark test',
    'html' => '<p>Postmark</p>',
], 'postmark-test');
assert_true('postmark adapter reports failure honestly', $postmarkResult['status'] === 'failed');

// SMTP transport builds multipart messages with attachments.
$smtpMime = birkly_mail_build_mime_message('Shop', 'shop@example.com', ['buyer@example.com'], 'Invoice', '<p>Hi</p>', 'Hi', [
    ['filename' => 'invoice.html', 'content' => '<h1>Invoice</h1>', 'mime_type' => 'text/html; charset=UTF-8'],
]);
assert_true('smtp mime includes attachment', str_contains($smtpMime, 'Content-Disposition: attachment'));
assert_true('smtp mime includes invoice filename', str_contains($smtpMime, 'invoice.html'));

// Attachment normalization rejects empty filenames.
$normalized = birkly_mail_normalize_attachments([
    ['filename' => 'ok.pdf', 'content' => '%PDF-1.4'],
    ['filename' => '', 'content' => 'skip'],
]);
assert_true('attachment normalization keeps valid items', count($normalized) === 1);
assert_true('attachment normalization preserves filename', $normalized[0]['filename'] === 'ok.pdf');

// Log transport with attachment metadata.
$result3 = birkly_send_mail([
    'to' => 'attach@example.com',
    'subject' => 'With attachment',
    'html' => '<p>See attached</p>',
    'attachments' => [
        ['filename' => 'note.txt', 'content' => 'Hello attachment'],
    ],
], 'mail-log');
assert_true('log transport with attachment succeeds', $result3['status'] === 'logged');

// Cleanup.
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
rrmdir($testRoot);

echo "\nmail_pipeline_test: " . ($failures === 0 ? "PASS\n" : "FAIL ($failures)\n");
exit($failures === 0 ? 0 : 1);
