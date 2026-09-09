<?php
/**
 * Outbound HTTP transport for marketplace registry and artifact downloads.
 *
 * Prefers cURL (common on localhost when allow_url_fopen is disabled) and falls
 * back to PHP stream wrappers when cURL is unavailable.
 */

require_once __DIR__ . '/../outbound_http.php';

/**
 * @param resource|\CurlHandle $ch
 */
function birkly_marketplace_curl_apply_transport_defaults($ch): void
{
    birkly_outbound_curl_apply_transport_defaults($ch);
}

function birkly_marketplace_http_is_retryable_error(string $error): bool
{
    $error = strtolower($error);
    foreach ([
        'timed out',
        'timeout',
        'could not resolve',
        'connection refused',
        'failed to connect',
        'network is unreachable',
        'no route to host',
    ] as $needle) {
        if (str_contains($error, $needle)) {
            return true;
        }
    }

    return false;
}

/**
 * @return array{ok:bool,status:int,body:string,error:?string}
 */
function birkly_marketplace_http_fetch(
    string $url,
    string $method = 'GET',
    array $headers = [],
    ?string $body = null,
    int $timeout = 20,
    bool $followRedirects = false
): array {
    if (isset($GLOBALS['birkly_marketplace_http_transport']) && is_callable($GLOBALS['birkly_marketplace_http_transport'])) {
        return ($GLOBALS['birkly_marketplace_http_transport'])($url, $method, $headers, $body, $timeout, $followRedirects);
    }

    if (extension_loaded('curl')) {
        $result = birkly_marketplace_http_fetch_curl($url, $method, $headers, $body, $timeout, $followRedirects);
        if ($result['ok'] || !birkly_marketplace_http_is_retryable_error((string) ($result['error'] ?? ''))) {
            return $result;
        }

        // One quick retry — covers transient DNS / routing hiccups on dev machines.
        return birkly_marketplace_http_fetch_curl($url, $method, $headers, $body, $timeout, $followRedirects, true);
    }

    return birkly_marketplace_http_fetch_streams($url, $method, $headers, $body, $timeout);
}

/**
 * @return array{ok:bool,status:int,body:string,error:?string}
 */
function birkly_marketplace_http_fetch_curl(
    string $url,
    string $method,
    array $headers,
    ?string $body,
    int $timeout,
    bool $followRedirects,
    bool $isRetry = false
): array {
    $ch = curl_init($url);
    if ($ch === false) {
        return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'Could not initialize cURL'];
    }

    $method = strtoupper($method);
    $headerLines = $headers;
    if ($body !== null && $body !== '') {
        $hasContentType = false;
        foreach ($headerLines as $line) {
            if (stripos($line, 'content-type:') === 0) {
                $hasContentType = true;
                break;
            }
        }
        if (!$hasContentType) {
            $headerLines[] = 'Content-Type: application/json';
        }
    }

    $connectTimeout = $isRetry ? min(8, max(3, (int) ceil($timeout / 3))) : max(5, (int) min($timeout, 10));

    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headerLines,
        CURLOPT_TIMEOUT => max(1, $timeout),
        CURLOPT_CONNECTTIMEOUT => $connectTimeout,
        CURLOPT_FOLLOWLOCATION => $followRedirects,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    birkly_marketplace_curl_apply_transport_defaults($ch);

    if ($body !== null && $body !== '') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
    }

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return [
            'ok' => false,
            'status' => $httpCode,
            'body' => '',
            'error' => $curlError !== '' ? $curlError : 'cURL request failed',
        ];
    }

    return [
        'ok' => true,
        'status' => $httpCode,
        'body' => is_string($response) ? $response : '',
        'error' => null,
    ];
}

/**
 * @return array{ok:bool,status:int,body:string,error:?string}
 */
function birkly_marketplace_http_fetch_streams(
    string $url,
    string $method,
    array $headers,
    ?string $body,
    int $timeout
): array {
    if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOL)) {
        return [
            'ok' => false,
            'status' => 0,
            'body' => '',
            'error' => 'allow_url_fopen is disabled and the cURL extension is not available',
        ];
    }

    $opts = [
        'http' => [
            'method' => strtoupper($method),
            'timeout' => max(1, $timeout),
            'ignore_errors' => true,
            'header' => implode("\r\n", $headers),
        ],
    ];

    if ($body !== null && $body !== '') {
        $opts['http']['content'] = $body;
    }

    $context = stream_context_create($opts);
    $response = @file_get_contents($url, false, $context);
    $lastError = error_get_last();
    $errorMessage = ($response === false && is_array($lastError))
        ? (string) ($lastError['message'] ?? '')
        : '';

    $httpCode = 0;
    if (isset($http_response_header) && is_array($http_response_header) && $http_response_header !== []) {
        if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', (string) $http_response_header[0], $matches)) {
            $httpCode = (int) $matches[1];
        }
    }

    if ($response === false) {
        return [
            'ok' => false,
            'status' => $httpCode,
            'body' => '',
            'error' => $errorMessage !== '' ? $errorMessage : 'HTTP request failed',
        ];
    }

    return [
        'ok' => true,
        'status' => $httpCode,
        'body' => is_string($response) ? $response : '',
        'error' => null,
    ];
}

/**
 * Download a remote file to a local path (used for marketplace zip artifacts).
 *
 * @return array{ok:bool,error:?string}
 */
function birkly_marketplace_http_download_to_file(
    string $url,
    string $destinationPath,
    array $headers = [],
    int $timeout = 120
): array {
    if (isset($GLOBALS['birkly_marketplace_http_download']) && is_callable($GLOBALS['birkly_marketplace_http_download'])) {
        return ($GLOBALS['birkly_marketplace_http_download'])($url, $destinationPath, $headers, $timeout);
    }

    if (extension_loaded('curl')) {
        $fp = fopen($destinationPath, 'wb');
        if ($fp === false) {
            return ['ok' => false, 'error' => 'Failed to open destination file'];
        }

        $ch = curl_init($url);
        if ($ch === false) {
            fclose($fp);
            return ['ok' => false, 'error' => 'Could not initialize cURL'];
        }

        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => max(1, $timeout),
            CURLOPT_CONNECTTIMEOUT => max(5, (int) min($timeout, 15)),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        birkly_marketplace_curl_apply_transport_defaults($ch);

        if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
            curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
            curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        }

        $ok = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if ($ok === false) {
            @unlink($destinationPath);
            return ['ok' => false, 'error' => $curlError !== '' ? $curlError : 'cURL download failed'];
        }

        return ['ok' => true, 'error' => null];
    }

    $fetch = birkly_marketplace_http_fetch_streams($url, 'GET', $headers, null, $timeout);
    if (!$fetch['ok']) {
        return ['ok' => false, 'error' => $fetch['error'] ?? 'Failed to download artifact'];
    }

    if (file_put_contents($destinationPath, $fetch['body']) === false) {
        return ['ok' => false, 'error' => 'Failed to save artifact'];
    }

    return ['ok' => true, 'error' => null];
}

function birkly_marketplace_transport_error_message(string $base, ?string $detail): string
{
    $detail = trim((string) $detail);
    if ($detail === '') {
        return $base;
    }

    return $base . ': ' . $detail;
}
