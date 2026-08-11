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

function birkly_mail_map_http_status(array $result, string $provider, ?string $connectionId, ?string $connectionAlias): array
{
    $httpCode = $result['http_code'];
    $response = $result['response'] ?? '';
    $curlError = $result['error'] ?? '';

    if ($curlError !== '') {
        return birkly_mail_result('failed', $provider, $connectionId, $connectionAlias, 'Transport error: ' . $curlError, $result);
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        return birkly_mail_result('queued', $provider, $connectionId, $connectionAlias, null, [
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
