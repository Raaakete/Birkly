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
 * Reads settings/env only — must not bootstrap plugins or marketplace client.
 */
function birkly_csp_marketplace_frame_origin(): string
{
    $default = 'https://marketplace.birkly.cloud';
    $envUrl = trim((string) (getenv('BIRKLY_MARKETPLACE_URL') ?: ''));
    if ($envUrl !== '') {
        $url = rtrim($envUrl, '/');
    } else {
        require_once __DIR__ . '/birkly_paths.php';
        $url = $default;
        $settingsFile = birkly_project_root() . '/settings/marketplace.json';
        if (is_readable($settingsFile)) {
            $data = json_decode((string) file_get_contents($settingsFile), true);
            if (is_array($data) && !empty($data['marketplace_url']) && is_string($data['marketplace_url'])) {
                $candidate = trim($data['marketplace_url']);
                if ($candidate !== '') {
                    $url = rtrim($candidate, '/');
                }
            }
        }
    }

    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        return $default;
    }

    $origin = $parts['scheme'] . '://' . $parts['host'];
    if (!empty($parts['port'])) {
        $origin .= ':' . $parts['port'];
    }

    return $origin;
}

/**
 * Origins that core fieldtypes declare for their admin widgets (BaseField::adminCspSources()).
 * Only https origins (scheme://host[:port]) are accepted.
 *
 * @return array{frame-src: list<string>, connect-src: list<string>}
 */
function birkly_fieldtype_admin_csp_sources(): array
{
    static $sources = null;
    if ($sources !== null) {
        return $sources;
    }

    $sources = ['frame-src' => [], 'connect-src' => []];
    $dir = dirname(__DIR__) . '/fieldtypes';
    if (!is_file($dir . '/BaseField.php')) {
        return $sources;
    }
    require_once $dir . '/BaseField.php';

    foreach (glob($dir . '/*', GLOB_ONLYDIR) ?: [] as $typeDir) {
        $name = basename($typeDir);
        $file = $typeDir . '/' . $name . '.php';
        if (!is_file($file)) {
            continue;
        }
        require_once $file;
        $class = 'Birkly\\FieldTypes\\' . $name . '\\' . $name;
        if (!class_exists($class) || !method_exists($class, 'adminCspSources')) {
            continue;
        }
        foreach ((array) $class::adminCspSources() as $directive => $origins) {
            if (!isset($sources[$directive])) {
                continue;
            }
            foreach ((array) $origins as $origin) {
                if (is_string($origin) && preg_match('#^https://[a-z0-9.-]+(:[0-9]+)?$#i', $origin)) {
                    $sources[$directive][] = strtolower($origin);
                }
            }
        }
    }

    foreach ($sources as $directive => $origins) {
        $sources[$directive] = array_values(array_unique($origins));
    }

    return $sources;
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
    $fieldtypeSources = birkly_fieldtype_admin_csp_sources();
    set_security_headers('admin', birkly_csp_nonce(), [
        'frame_src' => array_merge(["'self'", birkly_csp_marketplace_frame_origin()], $fieldtypeSources['frame-src']),
        'connect_src' => array_merge(["'self'"], $fieldtypeSources['connect-src']),
    ]);
}
