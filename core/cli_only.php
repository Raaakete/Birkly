<?php
/**
 * Maintenance scripts are for the shell. HTTP must not run them.
 */

declare(strict_types=1);

function birkly_is_cli_sapi(?string $sapi = null): bool
{
    $sapi = $sapi ?? PHP_SAPI;

    return $sapi === 'cli' || $sapi === 'phpdbg';
}

function birkly_require_cli(): void
{
    if (birkly_is_cli_sapi()) {
        return;
    }

    if (!headers_sent()) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo "Not found\n";
    exit;
}
