<?php
/**
 * Shared HTTP helper for ESP adapters.
 */

declare(strict_types=1);

function birkly_mail_http_post(string $url, $payload, array $headers, bool $formEncoded = false, bool $rawBody = false): array
{
    if (!extension_loaded('curl')) {
        return ['http_code' => 0, 'response' => '', 'error' => 'cURL extension is not available.', 'message_id' => null];
    }

    $ch = curl_init($url);
    if ($ch === false) {
        return ['http_code' => 0, 'response' => '', 'error' => 'Could not initialize cURL.', 'message_id' => null];
    }

    if ($rawBody && is_string($payload)) {
        $body = $payload;
        $headers[] = 'Content-Length: ' . strlen($body);
    } elseif ($formEncoded) {
        $body = http_build_query(is_array($payload) ? $payload : []);
    } else {
        $body = json_encode(is_array($payload) ? $payload : []);
        $headers[] = 'Content-Type: application/json';
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

    if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
    }

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    $messageId = null;
    $decoded = null;
    if (is_string($response)) {
        $decoded = json_decode($response, true);
        if (is_array($decoded)) {
            $messageId = (string) ($decoded['id'] ?? $decoded['MessageId'] ?? $decoded['message_id'] ?? '');
        }
    }
    if ($messageId === '') {
        $messageId = null;
    }

    return [
        'http_code' => $httpCode,
        'response' => is_string($response) ? $response : '',
        'error' => $error,
        'message_id' => $messageId,
        'decoded' => $decoded,
    ];
}

/**
 * POST multipart/form-data with optional file attachments.
 *
 * @param array<string, scalar> $fields
 * @param list<array{field: string, filename: string, content: string, mime_type: string}> $files
 */
function birkly_mail_http_post_multipart(string $url, array $fields, array $files, array $headers = []): array
{
    if (!extension_loaded('curl')) {
        return ['http_code' => 0, 'response' => '', 'error' => 'cURL extension is not available.', 'message_id' => null];
    }

    $boundary = 'birkly_mail_' . bin2hex(random_bytes(12));
    $body = '';
    foreach ($fields as $key => $value) {
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="' . str_replace('"', '', (string) $key) . '"' . "\r\n\r\n";
        $body .= (string) $value . "\r\n";
    }
    foreach ($files as $file) {
        $field = (string) ($file['field'] ?? 'attachment');
        $filename = (string) ($file['filename'] ?? 'attachment.bin');
        $mime = (string) ($file['mime_type'] ?? 'application/octet-stream');
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="' . str_replace('"', '', $field) . '"; filename="' . str_replace('"', '', $filename) . '"' . "\r\n";
        $body .= 'Content-Type: ' . $mime . "\r\n\r\n";
        $body .= (string) ($file['content'] ?? '') . "\r\n";
    }
    $body .= '--' . $boundary . '--';

    $headers[] = 'Content-Type: multipart/form-data; boundary=' . $boundary;
    $headers[] = 'Content-Length: ' . strlen($body);

    return birkly_mail_http_post($url, $body, $headers, false, true);
}

function birkly_mail_map_http_status(array $result, string $provider, ?string $connectionId, ?string $connectionAlias): array
{
    $httpCode = $result['http_code'];
    $response = $result['response'] ?? '';
    $curlError = $result['error'] ?? '';

    if ($curlError !== '') {
        return birkly_mail_result('failed', $provider, $connectionId, $connectionAlias, 'Transport error: ' . $curlError, $result);
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        return birkly_mail_result('accepted', $provider, $connectionId, $connectionAlias, null, [
            'http_code' => $httpCode,
            'message_id' => $result['message_id'] ?? null,
        ]);
    }

    $lower = strtolower($response);
    if (str_contains($lower, 'suppressed') || str_contains($lower, 'bounce') || str_contains($lower, 'complaint')) {
        return birkly_mail_result('suppressed', $provider, $connectionId, $connectionAlias, 'Recipient suppressed by provider.', [
            'http_code' => $httpCode,
            'response' => $response,
        ]);
    }

    return birkly_mail_result('failed', $provider, $connectionId, $connectionAlias, 'Provider returned HTTP ' . $httpCode . ': ' . substr($response, 0, 200), [
        'http_code' => $httpCode,
        'response' => $response,
    ]);
}
