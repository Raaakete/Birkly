<?php
require_once __DIR__ . '/../cms_paths.php';

/**
 * Dependency-free mail transport for Automation (mail / SMTP / log).
 */

/**
 * Send an Automation email using configured transport.
 *
 * @return array{ok: bool, method: string, error?: string, to?: string, from?: string, logged?: bool}
 */
function automation_send_mail(string $to, string $subject, string $bodyHtml, ?array $settings = null): array
{
    $settings = $settings ?? automation_get_mail_settings();
    $method = (string) ($settings['method'] ?? 'mail');
    $fromEmail = (string) ($settings['from_email'] ?? '');
    $fromName = (string) ($settings['from_name'] ?? 'Birkly');

    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'method' => $method, 'error' => 'Invalid recipient email address'];
    }
    if ($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'method' => $method, 'error' => 'Configure a valid From email under Automation → Setup'];
    }
    if ($subject === '') {
        return ['ok' => false, 'method' => $method, 'error' => 'Email subject is required'];
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
 * @return array{ok: bool, method: string, error?: string, to?: string, from?: string}
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

    if ($host === '') {
        return ['ok' => false, 'method' => 'smtp', 'error' => 'SMTP host is required'];
    }
    if ($port < 1 || $port > 65535) {
        return ['ok' => false, 'method' => 'smtp', 'error' => 'SMTP port is invalid'];
    }

    $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $errno = 0;
    $errstr = '';
    $socket = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        return [
            'ok' => false,
            'method' => 'smtp',
            'error' => 'Could not connect to SMTP server ' . $host . ':' . $port . ' (' . $errstr . ')',
        ];
    }
    stream_set_timeout($socket, 20);

    try {
        automation_smtp_expect($socket, [220]);
        automation_smtp_cmd($socket, 'EHLO birkly.local', [250]);

        if ($encryption === 'tls') {
            automation_smtp_cmd($socket, 'STARTTLS', [220]);
            $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $crypto = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            }
            if (!@stream_socket_enable_crypto($socket, true, $crypto)) {
                throw new RuntimeException('STARTTLS negotiation failed');
            }
            automation_smtp_cmd($socket, 'EHLO birkly.local', [250]);
        }

        if ($user !== '') {
            automation_smtp_cmd($socket, 'AUTH LOGIN', [334]);
            automation_smtp_cmd($socket, base64_encode($user), [334]);
            automation_smtp_cmd($socket, base64_encode($pass), [235]);
        }

        automation_smtp_cmd($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
        automation_smtp_cmd($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
        automation_smtp_cmd($socket, 'DATA', [354]);

        $message = [];
        $message[] = 'Date: ' . date('r');
        $message[] = 'From: ' . automation_mail_encode_address($fromName, $fromEmail);
        $message[] = 'To: <' . $to . '>';
        $message[] = 'Subject: ' . automation_mail_encode_header($subject);
        $message[] = 'MIME-Version: 1.0';
        $message[] = 'Content-Type: text/html; charset=UTF-8';
        $message[] = 'Content-Transfer-Encoding: 8bit';
        $message[] = '';
        $message[] = $bodyHtml;
        $message[] = '.';

        fwrite($socket, implode("\r\n", $message) . "\r\n");
        automation_smtp_expect($socket, [250]);
        @fwrite($socket, "QUIT\r\n");

        return ['ok' => true, 'method' => 'smtp', 'to' => $to, 'from' => $fromEmail];
    } catch (Throwable $e) {
        return ['ok' => false, 'method' => 'smtp', 'error' => $e->getMessage(), 'to' => $to, 'from' => $fromEmail];
    } finally {
        if (is_resource($socket)) {
            fclose($socket);
        }
    }
}

function automation_smtp_cmd($socket, string $command, array $okCodes): void
{
    fwrite($socket, $command . "\r\n");
    automation_smtp_expect($socket, $okCodes);
}

function automation_smtp_expect($socket, array $okCodes): string
{
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }
    $code = (int) substr($response, 0, 3);
    if (!in_array($code, $okCodes, true)) {
        throw new RuntimeException('SMTP unexpected response: ' . trim($response));
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
