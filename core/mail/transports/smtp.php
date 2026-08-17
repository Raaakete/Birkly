<?php
/**
 * Dependency-free SMTP transport with STARTTLS / AUTH LOGIN support.
 */

declare(strict_types=1);

require_once __DIR__ . '/../mime.php';

function birkly_mail_transport_smtp(array $payload, array $connection): array
{
    $config = is_array($connection['config'] ?? null) ? $connection['config'] : [];
    $secrets = is_array($connection['secrets'] ?? null) ? $connection['secrets'] : [];

    $host = trim((string) ($config['host'] ?? ''));
    $port = (int) ($config['port'] ?? 587);
    $encryption = strtolower((string) ($config['encryption'] ?? 'tls'));
    $auth = strtolower((string) ($config['auth'] ?? 'login'));
    $user = (string) ($secrets['username'] ?? '');
    $pass = (string) ($secrets['password'] ?? '');
    $from = (string) ($payload['from'] ?? '');
    $fromName = trim((string) ($payload['from_name'] ?? ''));
    $to = $payload['to'] ?? [];

    $connectionId = $connection['id'] ?? null;
    $alias = $connection['alias'] ?? null;

    if ($host === '') {
        return birkly_mail_result('failed', 'smtp', $connectionId, $alias, 'SMTP host is missing.');
    }
    if ($port < 1 || $port > 65535) {
        return birkly_mail_result('failed', 'smtp', $connectionId, $alias, 'SMTP port is invalid.');
    }
    if ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
        return birkly_mail_result('failed', 'smtp', $connectionId, $alias, 'From email is invalid.');
    }

    $socket = birkly_smtp_connect($host, $port, $encryption);
    if (!is_array($socket) || empty($socket['ok'])) {
        return birkly_mail_result('failed', 'smtp', $connectionId, $alias, $socket['error'] ?? 'Could not connect to SMTP server.', ['stage' => 'connect']);
    }
    $fp = $socket['socket'];
    stream_set_timeout($fp, 30);

    $stage = 'connect';
    try {
        $ehloHost = birkly_smtp_ehlo_name($from);

        $stage = 'greeting';
        birkly_smtp_expect($fp, [220], $stage);

        $stage = 'ehlo';
        birkly_smtp_cmd($fp, 'EHLO ' . $ehloHost, [250], $stage);

        if ($encryption === 'tls') {
            $stage = 'starttls';
            birkly_smtp_cmd($fp, 'STARTTLS', [220], $stage);
            $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (!@stream_socket_enable_crypto($fp, true, $crypto)) {
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                    if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)) {
                        throw new RuntimeException('STARTTLS negotiation failed (TLS handshake)');
                    }
                } else {
                    throw new RuntimeException('STARTTLS negotiation failed (TLS handshake)');
                }
            }
            $stage = 'ehlo_tls';
            birkly_smtp_cmd($fp, 'EHLO ' . $ehloHost, [250], $stage);
        }

        if ($user !== '') {
            $stage = 'auth';
            if ($auth === 'plain') {
                birkly_smtp_cmd($fp, 'AUTH PLAIN ' . base64_encode("\0" . $user . "\0" . $pass), [235], $stage);
            } else {
                birkly_smtp_cmd($fp, 'AUTH LOGIN', [334], $stage);
                birkly_smtp_cmd($fp, base64_encode($user), [334], $stage);
                birkly_smtp_cmd($fp, base64_encode($pass), [235], $stage);
            }
        }

        $stage = 'mail_from';
        birkly_smtp_cmd($fp, 'MAIL FROM:<' . $from . '>', [250], $stage);

        $stage = 'rcpt_to';
        foreach ($to as $recipient) {
            birkly_smtp_cmd($fp, 'RCPT TO:<' . $recipient . '>', [250, 251], $stage);
        }

        $stage = 'data';
        birkly_smtp_cmd($fp, 'DATA', [354], $stage);

        $message = birkly_smtp_build_message(
            $fromName,
            $from,
            $to,
            $payload['subject'] ?? '',
            $payload['html'] ?? '',
            $payload['text'] ?? '',
            $payload['attachments'] ?? []
        );
        $written = fwrite($fp, $message);
        if ($written === false || $written < strlen($message)) {
            throw new RuntimeException('Failed to write SMTP DATA payload');
        }

        $stage = 'data_end';
        birkly_smtp_expect($fp, [250], $stage);
        @fwrite($fp, "QUIT\r\n");

        return birkly_mail_result('queued', 'smtp', $connectionId, $alias, null, ['stage' => 'done']);
    } catch (Throwable $e) {
        return birkly_mail_result('failed', 'smtp', $connectionId, $alias, $e->getMessage(), ['stage' => $stage]);
    } finally {
        if (isset($fp) && (is_resource($fp) || is_object($fp))) {
            @fclose($fp);
        }
    }
}

function birkly_smtp_connect(string $host, int $port, string $encryption): array
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
        $socket = @stream_socket_client($remote, $errno, $errstr, 12, STREAM_CLIENT_CONNECT, $context);
        if ($socket) {
            return ['ok' => true, 'socket' => $socket];
        }
        $errors[] = $target . ' (' . $errstr . ')';
    }

    return ['ok' => false, 'error' => 'Could not connect to SMTP server ' . $host . ':' . $port . ' — ' . implode('; ', $errors)];
}

function birkly_smtp_ehlo_name(string $fromEmail): string
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

function birkly_smtp_build_message(
    string $fromName,
    string $fromEmail,
    array $to,
    string $subject,
    string $bodyHtml,
    string $bodyText,
    array $attachments = []
): string {
    $mime = birkly_mail_build_mime_message($fromName, $fromEmail, $to, $subject, $bodyHtml, $bodyText, $attachments);
    return birkly_smtp_body_escape($mime) . "\r\n.\r\n";
}

function birkly_smtp_body_escape(string $body): string
{
    $body = str_replace(["\r\n", "\r"], "\n", $body);
    $lines = explode("\n", $body);
    $stuffed = [];
    foreach ($lines as $line) {
        if (isset($line[0]) && $line[0] === '.') {
            $stuffed[] = '.' . $line;
        } else {
            $stuffed[] = $line;
        }
    }
    return implode("\r\n", $stuffed);
}

function birkly_smtp_cmd($socket, string $command, array $okCodes, string $stage = ''): string
{
    $written = fwrite($socket, $command . "\r\n");
    if ($written === false) {
        throw new RuntimeException('SMTP write failed at ' . ($stage !== '' ? $stage : 'command'));
    }
    return birkly_smtp_expect($socket, $okCodes, $stage);
}

function birkly_smtp_expect($socket, array $okCodes, string $stage = ''): string
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
        throw new RuntimeException('SMTP timeout/empty response at ' . $where);
    }

    $code = (int) substr($response, 0, 3);
    if (!in_array($code, $okCodes, true)) {
        $where = $stage !== '' ? (' at ' . $stage) : '';
        throw new RuntimeException('SMTP unexpected response' . $where . ': ' . trim($response));
    }
    return $response;
}
