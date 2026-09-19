<?php
/**
 * Shared outbound HTTP transport defaults for CMS → cloud requests.
 *
 * Clears broken HTTP(S)_PROXY env vars and prefers IPv4 to avoid common
 * localhost/macOS/Docker failure modes where shell curl works but PHP times out.
 */

function birkly_outbound_curl_ca_bundle_path(): ?string
{
    if (!function_exists('birkly_project_root')) {
        $pathsFile = __DIR__ . '/birkly_paths.php';
        if (is_readable($pathsFile)) {
            require_once $pathsFile;
        }
    }
    $reqFile = __DIR__ . '/php_runtime_requirements.php';
    if (is_readable($reqFile)) {
        require_once $reqFile;
    }
    if (function_exists('birkly_php_ca_bundle_path')) {
        return birkly_php_ca_bundle_path();
    }
    if (!function_exists('birkly_project_root')) {
        return null;
    }
    $root = birkly_project_root();
    $candidate = $root . '/runtime/php/extras/ssl/cacert.pem';
    if (is_readable($candidate)) {
        return $candidate;
    }

    return null;
}

/**
 * @param resource|\CurlHandle $ch
 */
function birkly_outbound_curl_apply_transport_defaults($ch): void
{
    curl_setopt($ch, CURLOPT_PROXY, '');

    $caBundle = birkly_outbound_curl_ca_bundle_path();
    if ($caBundle !== null) {
        if (defined('CURLOPT_CAINFO')) {
            curl_setopt($ch, CURLOPT_CAINFO, $caBundle);
        }
        if (defined('CURLOPT_SSL_CERTTYPE')) {
            // no-op; keeps older PHP happy when CAINFO is set
        }
    }

    if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')) {
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    }

    if (defined('CURLOPT_HTTP_VERSION') && defined('CURL_HTTP_VERSION_1_1')) {
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    }
}
