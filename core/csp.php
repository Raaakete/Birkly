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
 * Marketplace origin allowed in admin iframes (offer embed modal).
 */
function birkly_csp_marketplace_frame_origin(): string
{
    require_once __DIR__ . '/marketplace.php';
    $url = birkly_marketplace_url();
    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        return 'https://marketplace.birkly.cloud';
    }

    $origin = $parts['scheme'] . '://' . $parts['host'];
    if (!empty($parts['port'])) {
        $origin .= ':' . $parts['port'];
    }

    return $origin;
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
    set_security_headers('admin', birkly_csp_nonce(), [
        'frame_src' => ["'self'", birkly_csp_marketplace_frame_origin()],
    ]);
}
