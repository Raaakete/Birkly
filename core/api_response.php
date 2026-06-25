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
 * Emit a JSON error response and exit.
 */
function api_json_error(string $message, int $httpCode = 400, array $extra = []): void
{
    http_response_code($httpCode);
    echo json_encode(array_merge(['status' => 'error', 'message' => $message], $extra));
    exit;
}
