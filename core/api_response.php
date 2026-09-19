<?php
/**
 * Sanitized JSON API responses — avoid leaking internal exception details to clients.
 */

/**
 * Return a safe user-facing error message; log the real detail server-side.
 */
function api_sanitize_error_message(Throwable $e, string $fallback = 'An internal error occurred'): string
{
    error_log('API error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    return $fallback;
}

/**
 * Strip invalid UTF-8 sequences so json_encode does not fail on release notes / error text.
 */
function api_utf8_sanitize(string $value): string
{
    if ($value === '') {
        return '';
    }

    if (function_exists('mb_convert_encoding')) {
        $clean = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        if (is_string($clean) && $clean !== '') {
            return $clean;
        }
    }

    $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
    return is_string($clean) ? $clean : '';
}

/**
 * @return int JSON_* flags for API responses
 */
function api_json_encode_flags(): int
{
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }

    return $flags;
}

/**
 * Echo a JSON-encoded API payload; never returns an empty body on encoding failure.
 *
 * @param mixed $data
 */
function api_json_echo($data): void
{
    $json = json_encode($data, api_json_encode_flags());
    if ($json === false) {
        error_log('api_json_echo failed: ' . json_last_error_msg());
        $json = json_encode(
            ['status' => 'error', 'message' => 'Response encoding failed'],
            api_json_encode_flags()
        );
        if ($json === false) {
            $json = '{"status":"error","message":"Response encoding failed"}';
        }
    }

    echo $json;
}

/**
 * Emit a JSON error response and exit.
 */
function api_json_error(string $message, int $httpCode = 400, array $extra = []): void
{
    http_response_code($httpCode);
    api_json_echo(array_merge(['status' => 'error', 'message' => $message], $extra));
    exit;
}
