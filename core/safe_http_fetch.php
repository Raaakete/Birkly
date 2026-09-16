<?php
/**
 * SSRF-resistant HTTP fetch for admin-only URL previews (P88).
 */
declare(strict_types=1);

require_once __DIR__ . '/ai_presence.php';

/**
 * Resolve a public IPv4/IPv6 for curl pinning. Returns null when not safe.
 */
function birkly_safe_http_resolve_public_ip(string $host): ?string
{
    $host = strtolower(trim($host));
    if ($host === '') {
        return null;
    }

    $records = @dns_get_record($host, DNS_A + DNS_AAAA);
    if (is_array($records) && $records !== []) {
        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if (!is_string($ip) || $ip === '') {
                continue;
            }
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return null;
            }
            return $ip;
        }
        return null;
    }

    $ip = gethostbyname($host);
    if ($ip === $host || !filter_var($ip, FILTER_VALIDATE_IP)) {
        return null;
    }
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return null;
    }

    return $ip;
}

/**
 * Fetch a URL body with DNS pinning and no redirects (SSRF hardening).
 *
 * @param array{timeout?: int, user_agent?: string, max_bytes?: int} $options
 * @return array{ok: bool, body?: string, http_code?: int, error?: string}
 */
function birkly_safe_http_fetch(string $url, array $options = []): array
{
    if (!birkly_ai_presence_is_safe_external_url($url)) {
        return ['ok' => false, 'error' => 'URL is not allowed'];
    }

    $parts = parse_url($url);
    if (!is_array($parts)) {
        return ['ok' => false, 'error' => 'Invalid URL'];
    }

    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = (string) ($parts['host'] ?? '');
    if ($host === '') {
        return ['ok' => false, 'error' => 'Invalid URL host'];
    }

    $ip = birkly_safe_http_resolve_public_ip($host);
    if ($ip === null) {
        return ['ok' => false, 'error' => 'URL host resolves to a blocked address'];
    }

    $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
    $timeout = (int) ($options['timeout'] ?? 10);
    $maxBytes = (int) ($options['max_bytes'] ?? 500000);
    $userAgent = (string) ($options['user_agent'] ?? 'Birkly-SafeFetch/1.0');

    $ch = curl_init($url);
    if ($ch === false) {
        return ['ok' => false, 'error' => 'Failed to initialize request'];
    }

    $resolve = [$host . ':' . $port . ':' . $ip];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
        CURLOPT_USERAGENT => $userAgent,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_RESOLVE => $resolve,
    ]);

    $body = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        return ['ok' => false, 'error' => $curlError !== '' ? $curlError : 'Request failed'];
    }

    if ($httpCode >= 400) {
        return ['ok' => false, 'error' => 'HTTP ' . $httpCode, 'http_code' => $httpCode];
    }

    if (strlen((string) $body) > $maxBytes) {
        $body = substr((string) $body, 0, $maxBytes);
    }

    return ['ok' => true, 'body' => (string) $body, 'http_code' => $httpCode];
}
