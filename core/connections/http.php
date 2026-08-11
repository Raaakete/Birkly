<?php
/**
 * HTTP request helper for stored credential connections.
 *
 * Looks up an http_credential connection by alias, attaches the stored
 * credential, and performs an HTTP request. Returns an honest status array.
 */

declare(strict_types=1);

require_once __DIR__ . '/registry.php';

/**
 * Make an HTTP request using a stored credential connection.
 *
 * @param string $connectionAlias Alias of an http_credential connection.
 * @param string $method HTTP method.
 * @param string $url Request URL (absolute or relative to connection base_url).
 * @param string|array|null $body Request body (array is JSON-encoded).
 * @return array{status: string, http_code: int, response: string, error: string|null}
 */
function birkly_http_request(string $connectionAlias, string $method, string $url, $body = null): array
{
    $connection = birkly_connection_get_by_alias($connectionAlias, true);
    if ($connection === null) {
        return ['status' => 'failed', 'http_code' => 0, 'response' => '', 'error' => 'Connection not found: ' . $connectionAlias];
    }

    if (($connection['type'] ?? '') !== 'http_credential') {
        return ['status' => 'failed', 'http_code' => 0, 'response' => '', 'error' => 'Connection is not an HTTP credential: ' . $connectionAlias];
    }

    $connection = birkly_connection_decrypt_for_use($connection);
    $provider = (string) ($connection['provider'] ?? 'bearer');
    $config = is_array($connection['config'] ?? null) ? $connection['config'] : [];
    $secrets = is_array($connection['secrets'] ?? null) ? $connection['secrets'] : [];

    $baseUrl = rtrim((string) ($config['base_url'] ?? ''), '/');
    if ($baseUrl !== '' && !preg_match('/^https?:\/\//i', $url)) {
        $url = $baseUrl . '/' . ltrim($url, '/');
    }

    $timeout = (int) ($config['timeout'] ?? 30);
    if ($timeout <= 0 || $timeout > 300) {
        $timeout = 30;
    }

    $headers = [];
    if ($provider === 'basic_auth') {
        $user = (string) ($secrets['username'] ?? '');
        $pass = (string) ($secrets['password'] ?? '');
        if ($user === '' && $pass === '') {
            return ['status' => 'failed', 'http_code' => 0, 'response' => '', 'error' => 'Basic auth credentials missing.'];
        }
        $headers[] = 'Authorization: Basic ' . base64_encode($user . ':' . $pass);
    } elseif ($provider === 'bearer') {
        $token = (string) ($secrets['token'] ?? '');
        if ($token === '') {
            return ['status' => 'failed', 'http_code' => 0, 'response' => '', 'error' => 'Bearer token missing.'];
        }
        $headers[] = 'Authorization: Bearer ' . $token;
    } elseif ($provider === 'custom_header') {
        $headerName = trim((string) ($config['header_name'] ?? ($secrets['header_name'] ?? '')));
        $headerValue = (string) ($secrets['header_value'] ?? '');
        if ($headerName === '' || $headerValue === '') {
            return ['status' => 'failed', 'http_code' => 0, 'response' => '', 'error' => 'Custom header name or value missing.'];
        }
        $headers[] = $headerName . ': ' . $headerValue;
    }

    $method = strtoupper($method);
    $jsonBody = null;
    $rawBody = '';
    if ($body !== null) {
        if (is_array($body)) {
            $rawBody = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $headers[] = 'Content-Type: application/json';
        } else {
            $rawBody = (string) $body;
        }
    }

    if (extension_loaded('curl')) {
        return birkly_http_request_curl($url, $method, $rawBody, $headers, $timeout);
    }

    return birkly_http_request_streams($url, $method, $rawBody, $headers, $timeout);
}

function birkly_http_request_curl(string $url, string $method, string $body, array $headers, int $timeout): array
{
    if (isset($GLOBALS['__birkly_http_transport']) && is_callable($GLOBALS['__birkly_http_transport'])) {
        return call_user_func($GLOBALS['__birkly_http_transport'], $url, $method, $body, $headers, $timeout);
    }

    $ch = curl_init($url);
    if ($ch === false) {
        return ['status' => 'failed', 'http_code' => 0, 'response' => '', 'error' => 'Could not initialize cURL.'];
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, max(5, (int) ($timeout / 2)));
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

    if ($body !== '') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
    }

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return birkly_http_request_result($httpCode, is_string($response) ? $response : '', $error);
}

function birkly_http_request_streams(string $url, string $method, string $body, array $headers, int $timeout): array
{
    $opts = [
        'http' => [
            'method' => $method,
            'timeout' => $timeout,
            'ignore_errors' => true,
            'header' => $headers,
        ],
    ];
    if ($body !== '') {
        $opts['http']['content'] = $body;
    }

    $context = stream_context_create($opts);
    $response = @file_get_contents($url, false, $context);
    $error = error_get_last();
    $errorMessage = ($response === false && is_array($error)) ? $error['message'] : '';

    $httpCode = 0;
    if (isset($http_response_header) && is_array($http_response_header) && !empty($http_response_header)) {
        if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $http_response_header[0], $m)) {
            $httpCode = (int) $m[1];
        }
    }

    return birkly_http_request_result($httpCode, is_string($response) ? $response : '', $errorMessage);
}

function birkly_http_request_result(int $httpCode, string $response, string $error): array
{
    if ($error !== '') {
        return ['status' => 'failed', 'http_code' => $httpCode, 'response' => $response, 'error' => $error];
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        return ['status' => 'accepted', 'http_code' => $httpCode, 'response' => $response, 'error' => null];
    }

    if ($httpCode >= 400 && $httpCode < 500 && $httpCode !== 408) {
        return ['status' => 'failed', 'http_code' => $httpCode, 'response' => $response, 'error' => 'Client error HTTP ' . $httpCode];
    }

    return ['status' => 'failed', 'http_code' => $httpCode, 'response' => $response, 'error' => 'HTTP ' . $httpCode];
}
