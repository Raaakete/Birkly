<?php
/**
 * CSP nonce helpers (P16-M5) — per-request nonce for admin shell.
 */

/**
 * Return the CSP nonce for this request (generated once).
 */
function birkly_csp_nonce(): string
{
    if (!isset($GLOBALS['__birkly_csp_nonce'])) {
        $GLOBALS['__birkly_csp_nonce'] = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
    }

    return (string) $GLOBALS['__birkly_csp_nonce'];
}

/**
 * HTML attribute for script/style nonce tags.
 */
function birkly_csp_nonce_attr(): string
{
    return ' nonce="' . htmlspecialchars(birkly_csp_nonce(), ENT_QUOTES, 'UTF-8') . '"';
}

/**
 * Set admin CSP headers using the per-request nonce.
 */
function birkly_set_admin_csp_headers(): void
{
    if (headers_sent()) {
        return;
    }

    require_once __DIR__ . '/../api/validate.php';
    set_security_headers('admin', birkly_csp_nonce());
}
