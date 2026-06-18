#!/usr/bin/env php
<?php
/**
 * Send the daily agent digest email (M1-T5).
 *
 * Always writes docs/agent-ops/daily-reports/YYYY-MM-DD.md.
 * Sends email when RESEND_API_KEY and AGENT_DIGEST_FROM are set.
 *
 * Usage:
 *   php scripts/send-agent-digest.php [--date YYYY-MM-DD] [--body-file path]
 *
 * Env (cloud dashboard secrets — never commit):
 *   AGENT_DIGEST_TO     default keven@protonmail.com
 *   RESEND_API_KEY      Resend HTTP API key
 *   AGENT_DIGEST_FROM   verified sender address (required with Resend)
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$date = date('Y-m-d');
$bodyFile = null;

for ($i = 1; $i < $argc; $i++) {
    if ($argv[$i] === '--date' && isset($argv[$i + 1])) {
        $date = $argv[++$i];
    } elseif ($argv[$i] === '--body-file' && isset($argv[$i + 1])) {
        $bodyFile = $argv[++$i];
    }
}

$to = getenv('AGENT_DIGEST_TO') ?: 'keven@protonmail.com';
$reportsDir = $root . '/docs/agent-ops/daily-reports';
if (!is_dir($reportsDir)) {
    mkdir($reportsDir, 0755, true);
}

$outPath = $reportsDir . '/' . $date . '.md';

if ($bodyFile && is_readable($bodyFile)) {
    $body = file_get_contents($bodyFile);
} elseif (is_readable($outPath)) {
    $body = file_get_contents($outPath);
} else {
    $body = <<<MD
# Birkly daily — {$date}

No agent activity was recorded for this date. The team will resume on the next active session.

---
**Values check:** ✅ aligned  
**Blocked / needs you:** none  
**Today:** awaiting next scheduled work block
MD;
}

file_put_contents($outPath, $body);
fwrite(STDOUT, "Wrote {$outPath}\n");

$resendKey = getenv('RESEND_API_KEY') ?: '';
$from = getenv('AGENT_DIGEST_FROM') ?: '';

if ($resendKey === '' || $from === '') {
    fwrite(STDERR, "Email not sent: set RESEND_API_KEY and AGENT_DIGEST_FROM in cloud env.\n");
    fwrite(STDERR, "Digest saved to {$outPath} — use chat fallback per REPORTING.md.\n");
    exit(2);
}

$subject = "Birkly daily — {$date}";
$payload = json_encode([
    'from' => $from,
    'to' => [$to],
    'subject' => $subject,
    'text' => $body,
], JSON_UNESCAPED_UNICODE);

$ch = curl_init('https://api.resend.com/emails');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $resendKey,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
]);
$response = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code >= 200 && $code < 300) {
    fwrite(STDOUT, "Email sent via Resend to {$to}\n");
    exit(0);
}

fwrite(STDERR, "Email failed (HTTP {$code}): {$response}\n");
fwrite(STDERR, "Digest saved to {$outPath}\n");
exit(1);
