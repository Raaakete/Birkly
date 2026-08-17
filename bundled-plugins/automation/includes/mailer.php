<?php
require_once __DIR__ . '/../cms_paths.php';

/**
 * Automation mail adapter.
 *
 * When the core Connections pipeline is present, emails route through
 * birkly_send_mail using the transactional_email alias. The legacy
 * mail/SMTP/log implementation remains as a fallback for pre-P65 cores.
 */

/**
 * Send an Automation email using configured transport.
 *
 * @return array{ok: bool, method: string, error?: string, to?: string, from?: string, logged?: bool, stage?: string}
 */
function automation_send_mail(string $to, string $subject, string $bodyHtml, ?array $settings = null): array
{
    automation_ensure_mail_pipeline();

    $settings = $settings ?? automation_get_mail_settings();
    $method = (string) ($settings['method'] ?? 'mail');
    $fromEmail = (string) ($settings['from_email'] ?? '');
    $fromName = (string) ($settings['from_name'] ?? 'Birkly');

    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'method' => $method, 'error' => 'Invalid recipient email address'];
    }
    if ($subject === '') {
        return ['ok' => false, 'method' => $method, 'error' => 'Email subject is required'];
    }

    // Prefer the core Connections pipeline; keep the legacy Automation transport
    // only when the core mailer is not present (e.g. fresh install or pre-P65 core).
    if (function_exists('birkly_send_mail')) {
        return automation_send_mail_via_core($to, $subject, $bodyHtml, $settings);
    }

    if ($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'method' => $method, 'error' => 'Configure a valid From email under Automation → Setup'];
    }

    if ($method === 'log') {
        automation_log_sent_mail($to, $subject, $bodyHtml, $fromEmail, $fromName);
        return [
            'ok' => true,
            'method' => 'log',
            'logged' => true,
            'to' => $to,
            'from' => $fromEmail,
        ];
    }

    if ($method === 'smtp') {
        if (trim((string) ($settings['smtp_host'] ?? '')) === '') {
            return [
                'ok' => false,
                'method' => 'smtp',
                'error' => 'SMTP host is missing. Open Automation → Setup, save SMTP settings, then try again.',
            ];
        }
        if (trim((string) ($settings['smtp_password'] ?? '')) === '' && trim((string) ($settings['smtp_username'] ?? '')) !== '') {
            return [
                'ok' => false,
                'method' => 'smtp',
                'error' => 'SMTP password is missing. Re-enter it under Automation → Setup and Save email settings.',
            ];
        }
        $result = automation_send_via_smtp($to, $subject, $bodyHtml, $settings);
        if (!empty($result['ok'])) {
            automation_log_sent_mail($to, $subject, $bodyHtml, $fromEmail, $fromName, 'smtp');
        }
        return $result;
    }

    // Default: PHP mail()
    if (!function_exists('mail')) {
        return [
            'ok' => false,
            'method' => 'mail',
            'error' => 'PHP mail() is not available. Switch transport to SMTP under Automation → Setup.',
        ];
    }

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . automation_mail_encode_address($fromName, $fromEmail),
        'Reply-To: ' . $fromEmail,
    ];

    $ok = @mail($to, $subject, $bodyHtml, implode("\r\n", $headers));
    if (!$ok) {
        return [
            'ok' => false,
            'method' => 'mail',
            'error' => 'PHP mail() returned false. On most cloud hosts this never delivers — configure SMTP under Automation → Setup.',
            'to' => $to,
            'from' => $fromEmail,
        ];
    }

    automation_log_sent_mail($to, $subject, $bodyHtml, $fromEmail, $fromName, 'mail');
    return ['ok' => true, 'method' => 'mail', 'to' => $to, 'from' => $fromEmail];
}

/**
 * @return array{ok: bool, method: string, error?: string, to?: string, from?: string, stage?: string}
 */
function automation_send_via_smtp(string $to, string $subject, string $bodyHtml, array $settings): array
{
    $host = trim((string) ($settings['smtp_host'] ?? ''));
    $port = (int) ($settings['smtp_port'] ?? 587);
    $user = (string) ($settings['smtp_username'] ?? '');
    $pass = (string) ($settings['smtp_password'] ?? '');
    $encryption = strtolower((string) ($settings['smtp_encryption'] ?? 'tls'));
    $fromEmail = (string) ($settings['from_email'] ?? '');
    $fromName = (string) ($settings['from_name'] ?? 'Birkly');
    $stage = 'connect';

    if ($host === '') {
        return ['ok' => false, 'method' => 'smtp', 'error' => 'SMTP host is required', 'stage' => $stage];
    }
    if ($port < 1 || $port > 65535) {
        return ['ok' => false, 'method' => 'smtp', 'error' => 'SMTP port is invalid', 'stage' => $stage];
    }

    $socket = automation_smtp_connect($host, $port, $encryption);
    if (!is_array($socket) || empty($socket['ok'])) {
        return [
            'ok' => false,
            'method' => 'smtp',
            'error' => $socket['error'] ?? ('Could not connect to SMTP server ' . $host . ':' . $port),
            'stage' => 'connect',
            'to' => $to,
            'from' => $fromEmail,
        ];
    }
    $fp = $socket['socket'];
    stream_set_timeout($fp, 30);

    try {
        $ehloHost = automation_smtp_ehlo_name($fromEmail);

        $stage = 'greeting';
        automation_smtp_expect($fp, [220], $stage);

        $stage = 'ehlo';
        automation_smtp_cmd($fp, 'EHLO ' . $ehloHost, [250], $stage);

        if ($encryption === 'tls') {
            $stage = 'starttls';
            automation_smtp_cmd($fp, 'STARTTLS', [220], $stage);
            $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (!@stream_socket_enable_crypto($fp, true, $crypto)) {
                // Retry with broader crypto flags used by older PHP builds.
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                    if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)) {
                        throw new RuntimeException('STARTTLS negotiation failed (TLS handshake)');
                    }
                } else {
                    throw new RuntimeException('STARTTLS negotiation failed (TLS handshake)');
                }
            }
            $stage = 'ehlo_tls';
            automation_smtp_cmd($fp, 'EHLO ' . $ehloHost, [250], $stage);
        }

        if ($user !== '') {
            $stage = 'auth';
            automation_smtp_cmd($fp, 'AUTH LOGIN', [334], $stage);
            automation_smtp_cmd($fp, base64_encode($user), [334], $stage);
            automation_smtp_cmd($fp, base64_encode($pass), [235], $stage);
        }

        $stage = 'mail_from';
        automation_smtp_cmd($fp, 'MAIL FROM:<' . $fromEmail . '>', [250], $stage);
        $stage = 'rcpt_to';
        automation_smtp_cmd($fp, 'RCPT TO:<' . $to . '>', [250, 251], $stage);
        $stage = 'data';
        automation_smtp_cmd($fp, 'DATA', [354], $stage);

        $message = automation_smtp_build_message($fromName, $fromEmail, $to, $subject, $bodyHtml);
        $written = fwrite($fp, $message);
        if ($written === false || $written < strlen($message)) {
            throw new RuntimeException('Failed to write SMTP DATA payload');
        }

        $stage = 'data_end';
        automation_smtp_expect($fp, [250], $stage);
        @fwrite($fp, "QUIT\r\n");

        return ['ok' => true, 'method' => 'smtp', 'to' => $to, 'from' => $fromEmail, 'stage' => 'done'];
    } catch (Throwable $e) {
        return [
            'ok' => false,
            'method' => 'smtp',
            'error' => $e->getMessage(),
            'stage' => $stage,
            'to' => $to,
            'from' => $fromEmail,
        ];
    } finally {
        if (isset($fp) && (is_resource($fp) || is_object($fp))) {
            @fclose($fp);
        }
    }
}

/**
 * Connect preferring IPv4 to avoid Docker IPv6 blackhole hangs.
 *
 * @return array{ok: bool, socket?: resource, error?: string}
 */
function automation_smtp_connect(string $host, int $port, string $encryption): array
{
    $prefix = ($encryption === 'ssl') ? 'ssl://' : '';
    $targets = [];

    if (filter_var($host, FILTER_VALIDATE_IP)) {
        $targets[] = $host;
    } else {
        $aRecords = @dns_get_record($host, DNS_A) ?: [];
        foreach ($aRecords as $row) {
            if (!empty($row['ip'])) {
                $targets[] = (string) $row['ip'];
            }
        }
        // Fall back to hostname (may use IPv6) only if no A record.
        if ($targets === []) {
            $targets[] = $host;
        }
    }

    $errors = [];
    foreach (array_values(array_unique($targets)) as $target) {
        $remote = $prefix . $target . ':' . $port;
        $errno = 0;
        $errstr = '';
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'peer_name' => $host,
                'SNI_enabled' => true,
            ],
        ]);
        $socket = @stream_socket_client(
            $remote,
            $errno,
            $errstr,
            12,
            STREAM_CLIENT_CONNECT,
            $context
        );
        if ($socket) {
            return ['ok' => true, 'socket' => $socket];
        }
        $errors[] = $target . ' (' . $errstr . ')';
    }

    return [
        'ok' => false,
        'error' => 'Could not connect to SMTP server ' . $host . ':' . $port . ' — ' . implode('; ', $errors),
    ];
}

function automation_smtp_ehlo_name(string $fromEmail): string
{
    $domain = '';
    if (str_contains($fromEmail, '@')) {
        $domain = strtolower((string) substr(strrchr($fromEmail, '@'), 1));
    }
    if ($domain !== '' && preg_match('/^[a-z0-9.-]+$/', $domain)) {
        return $domain;
    }
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'birkly.local');
    $host = preg_replace('/:\d+$/', '', $host) ?: 'birkly.local';
    return $host;
}

function automation_smtp_build_message(
    string $fromName,
    string $fromEmail,
    string $to,
    string $subject,
    string $bodyHtml
): string {
    $headers = [
        'Date: ' . date('r'),
        'From: ' . automation_mail_encode_address($fromName, $fromEmail),
        'To: <' . $to . '>',
        'Subject: ' . automation_mail_encode_header($subject),
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];

    // Normalize body newlines and SMTP-dot-stuff lines that start with ".".
    $body = str_replace(["\r\n", "\r"], "\n", $bodyHtml);
    $lines = explode("\n", $body);
    $stuffed = [];
    foreach ($lines as $line) {
        if (isset($line[0]) && $line[0] === '.') {
            $stuffed[] = '.' . $line;
        } else {
            $stuffed[] = $line;
        }
    }

    return implode("\r\n", $headers) . "\r\n\r\n" . implode("\r\n", $stuffed) . "\r\n.\r\n";
}

function automation_smtp_cmd($socket, string $command, array $okCodes, string $stage = ''): string
{
    $written = fwrite($socket, $command . "\r\n");
    if ($written === false) {
        throw new RuntimeException('SMTP write failed at ' . ($stage !== '' ? $stage : 'command'));
    }
    return automation_smtp_expect($socket, $okCodes, $stage);
}

function automation_smtp_expect($socket, array $okCodes, string $stage = ''): string
{
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }

    $meta = stream_get_meta_data($socket);
    if ($response === '' || !empty($meta['timed_out'])) {
        $where = $stage !== '' ? $stage : 'response';
        throw new RuntimeException(
            'SMTP timeout/empty response at ' . $where
            . '. Check host/port/encryption, firewall egress, and that the SMTP password is an app/SMTP token (not your login password).'
        );
    }

    $code = (int) substr($response, 0, 3);
    if (!in_array($code, $okCodes, true)) {
        $where = $stage !== '' ? (' at ' . $stage) : '';
        throw new RuntimeException('SMTP unexpected response' . $where . ': ' . trim($response));
    }
    return $response;
}

function automation_mail_encode_address(string $name, string $email): string
{
    $name = trim(str_replace(["\r", "\n"], '', $name));
    if ($name === '') {
        return '<' . $email . '>';
    }
    if (preg_match('/^[A-Za-z0-9 _.-]+$/', $name)) {
        return $name . ' <' . $email . '>';
    }
    return '"=?UTF-8?B?' . base64_encode($name) . '?=" <' . $email . '>';
}

function automation_mail_encode_header(string $value): string
{
    $value = str_replace(["\r", "\n"], '', $value);
    if (preg_match('/^[\x20-\x7E]*$/', $value)) {
        return $value;
    }
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function automation_log_sent_mail(
    string $to,
    string $subject,
    string $bodyHtml,
    string $fromEmail,
    string $fromName,
    string $method = 'log'
): void {
    try {
        $storage = automation_storage();
        $messages = $storage->read('automation_sent_mail.json') ?? [];
        if (!is_array($messages)) {
            $messages = [];
        }
        $messages[] = [
            'to' => $to,
            'subject' => $subject,
            'from_email' => $fromEmail,
            'from_name' => $fromName,
            'method' => $method,
            'body_preview' => substr(strip_tags($bodyHtml), 0, 240),
            'sent_at' => date('c'),
        ];
        if (count($messages) > 50) {
            $messages = array_slice($messages, -50);
        }
        $storage->write('automation_sent_mail.json', $messages);
    } catch (Throwable $e) {
        error_log('[Automation] failed to log sent mail: ' . $e->getMessage());
    }
}

/**
 * True when an identical test/automation send was logged very recently.
 */
function automation_recent_send_exists(string $to, string $subject, int $withinSeconds = 15): bool
{
    $to = strtolower(trim($to));
    $subject = trim($subject);
    $cutoff = time() - max(1, $withinSeconds);
    foreach (automation_get_sent_mail_log(10) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $rowTo = strtolower(trim((string) ($row['to'] ?? '')));
        $rowSubject = trim((string) ($row['subject'] ?? ''));
        $sentAt = strtotime((string) ($row['sent_at'] ?? ''));
        if ($rowTo === $to && $rowSubject === $subject && $sentAt !== false && $sentAt >= $cutoff) {
            return true;
        }
    }
    return false;
}

/**
 * @return list<array<string, mixed>>
 */
function automation_get_sent_mail_log(int $limit = 10): array
{
    $storage = automation_storage();
    $messages = $storage->read('automation_sent_mail.json') ?? [];
    if (!is_array($messages)) {
        return [];
    }
    return array_slice(array_reverse($messages), 0, max(1, $limit));
}

/**
 * Send via the core Connections pipeline and map the result back to the
 * Automation-shaped response so existing callers keep working.
 *
 * @return array{ok: bool, method: string, error?: string, to?: string, from?: string, logged?: bool, stage?: string}
 */
function automation_send_mail_via_core(string $to, string $subject, string $bodyHtml, array $settings): array
{
    $fromEmail = (string) ($settings['from_email'] ?? '');
    $fromName = (string) ($settings['from_name'] ?? 'Birkly');

    $opts = [
        'text' => automation_email_html_to_text($bodyHtml),
        'alias' => trim((string) ($settings['connection_alias'] ?? $settings['alias'] ?? '')) ?: null,
        'idempotency_key' => 'automation_' . md5(strtolower(trim($to)) . '|' . trim($subject) . '|' . microtime(true)),
    ];

    $coreResult = birkly_send_mail($to, $subject, $bodyHtml, $opts);
    if (!is_array($coreResult)) {
        return [
            'ok' => false,
            'method' => 'core',
            'error' => 'Core mailer returned an invalid response',
            'to' => $to,
            'from' => $fromEmail,
        ];
    }

    $result = $coreResult;
    $status = (string) ($result['status'] ?? '');
    if (!isset($result['method'])) {
        $result['method'] = ($status === 'logged') ? 'log' : 'core';
    }
    if (!isset($result['from'])) {
        $result['from'] = trim((string) ($coreResult['from'] ?? $fromEmail));
    }
    // Be honest about delivery: log transport is accepted but not delivered.
    if ($status === 'logged') {
        $result['logged'] = true;
    }

    if (!empty($result['ok'])) {
        automation_log_sent_mail($to, $subject, $bodyHtml, $fromEmail, $fromName, $result['method']);
    } elseif (in_array($status, ['accepted', 'queued', 'logged'], true)) {
        $result['ok'] = true;
        automation_log_sent_mail($to, $subject, $bodyHtml, $fromEmail, $fromName, $result['method'] ?? 'core');
    }

    return $result;
}

/**
 * Generate a plain-text body from a simple HTML email.
 */
function automation_email_html_to_text(string $html): string
{
    $text = preg_replace('/\s+/', ' ', strip_tags($html));
    $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim((string) $text);
}
