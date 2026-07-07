<?php
/**
 * Birkly Marketplace — testable API payload builders (no HTTP side effects).
 */

/**
 * Build the health payload.
 */
function marketplace_health_payload(): array
{
    return [
        'status' => 'ok',
        'service' => 'birkly-marketplace',
        'version' => (string) marketplace_config('version', '0.1.0-m0'),
        'php' => PHP_VERSION,
        'timestamp' => gmdate('c'),
    ];
}

/**
 * Build the catalog list payload (M0 stub).
 */
function marketplace_catalog_payload(): array
{
    return [
        'offers' => [],
        'meta' => [
            'count' => 0,
            'page' => 1,
            'per_page' => 20,
        ],
    ];
}
