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

/**
 * @return array{ok:bool,code:int,output:string}
 */
function birkly_digest_run_production_smoke(string $root): array
{
    $script = $root . '/scripts/production-smoke.sh';
    if (!is_file($script)) {
        return ['ok' => false, 'code' => 127, 'output' => 'production-smoke.sh not found'];
    }

    if (!is_executable($script)) {
        @chmod($script, 0755);
    }

    $baseUrl = getenv('BIRKLY_SMOKE_BASE_URL') ?: 'http://127.0.0.1:8080';
    $skipMarketplace = getenv('BIRKLY_SMOKE_SKIP_MARKETPLACE') ?: '1';
    $cmd = 'cd ' . escapeshellarg($root)
        . ' && BIRKLY_SMOKE_SKIP_MARKETPLACE=' . escapeshellarg($skipMarketplace)
        . ' BIRKLY_SMOKE_BASE_URL=' . escapeshellarg($baseUrl)
        . ' ' . escapeshellarg($script)
        . ' 2>&1';

    $output = [];
    $code = 0;
    exec($cmd, $output, $code);

    return [
        'ok' => $code === 0,
        'code' => $code,
        'output' => implode("\n", $output),
    ];
}

/**
 * @return string
 */
function birkly_digest_last_smoke_section(string $root): string
{
    $metaFile = $root . '/logs/last-production-smoke.json';
    if (!is_readable($metaFile)) {
        return '';
    }

    $meta = json_decode((string) file_get_contents($metaFile), true);
    if (!is_array($meta)) {
        return '';
    }

    $lastRun = trim((string) ($meta['last_run'] ?? ''));
    if ($lastRun === '') {
        return '';
    }

    $result = (string) ($meta['result'] ?? 'unknown');
    $service = (string) ($meta['service'] ?? 'birkly-cms');
    $exitCode = (int) ($meta['exit_code'] ?? -1);

    return implode("\n", [
        '',
        '## Last production smoke',
        '',
        "- **Service:** {$service}",
        "- **Last run (UTC):** {$lastRun}",
        "- **Result:** {$result} (exit {$exitCode})",
        '',
    ]);
}

/**
 * @return string
 */
function birkly_digest_ops_failure_section(array $smoke): string
{
    if (!empty($smoke['ok'])) {
        return '';
    }

    $lines = [
        '',
        '## Ops failure (production smoke)',
        '',
        'Production smoke did not pass. Review health endpoints and writable directories before deploy.',
        '',
        '```text',
        (string) ($smoke['output'] ?? ''),
        '```',
        '',
    ];

    return implode("\n", $lines);
}

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

$smoke = birkly_digest_run_production_smoke($root);
$opsSection = birkly_digest_ops_failure_section($smoke);
$smokeMetaSection = birkly_digest_last_smoke_section($root);
if ($opsSection !== '') {
    $body .= $opsSection;
}
if ($smokeMetaSection !== '') {
    $body .= $smokeMetaSection;
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
