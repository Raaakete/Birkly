<?php
/**
 * Shared outbound HTTP transport defaults for CMS → cloud requests.
 *
 * Clears broken HTTP(S)_PROXY env vars and prefers IPv4 to avoid common
 * localhost/macOS/Docker failure modes where shell curl works but PHP times out.
 */

/**
 * @param resource|\CurlHandle $ch
 */
function birkly_outbound_curl_apply_transport_defaults($ch): void
{
    curl_setopt($ch, CURLOPT_PROXY, '');

    if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')) {
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    }

    if (defined('CURLOPT_HTTP_VERSION') && defined('CURL_HTTP_VERSION_1_1')) {
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    }
}
