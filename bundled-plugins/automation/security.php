<?php
/**
 * Automation security helpers.
 */

/**
 * Validate outbound HTTP(S) targets for webhook-like actions.
 *
 * @return array{ok: bool, error?: string}
 */
function automation_validate_outbound_url(string $url): array
{
    $url = trim($url);
    if ($url === '') {
        return ['ok' => false, 'error' => 'URL is required'];
    }

    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        return ['ok' => false, 'error' => 'URL must include a scheme and host'];
    }

    $scheme = strtolower((string) $parts['scheme']);
    if (!in_array($scheme, ['http', 'https'], true)) {
        return ['ok' => false, 'error' => 'Only http and https URLs are allowed'];
    }

    if (!empty($parts['user']) || !empty($parts['pass'])) {
        return ['ok' => false, 'error' => 'Credentials in URLs are not allowed'];
    }

    $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
    if (!in_array($port, [80, 443], true)) {
        return ['ok' => false, 'error' => 'Only ports 80 and 443 are allowed'];
    }

    $host = strtolower((string) $parts['host']);
    if (in_array($host, ['localhost', 'localhost.localdomain'], true) || str_ends_with($host, '.local')) {
        return ['ok' => false, 'error' => 'Local hosts are not allowed'];
    }

    $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
    if ($ips === []) {
        return ['ok' => false, 'error' => 'Host could not be resolved'];
    }

    foreach ($ips as $ip) {
        if (!automation_is_public_ip($ip)) {
            return ['ok' => false, 'error' => 'Private, reserved, and local IP targets are not allowed'];
        }
    }

    return ['ok' => true];
}

function automation_is_public_ip(string $ip): bool
{
    return (bool) filter_var(
        $ip,
        FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
    );
}

/**
 * Recursively redact secrets before logging or returning diagnostic payloads.
 *
 * @param mixed $value
 * @return mixed
 */
function automation_redact_sensitive_data($value)
{
    if (is_array($value)) {
        $redacted = [];
        foreach ($value as $key => $item) {
            if (automation_is_sensitive_key((string) $key)) {
                $redacted[$key] = automation_mask_secret($item);
            } else {
                $redacted[$key] = automation_redact_sensitive_data($item);
            }
        }
        return $redacted;
    }

    return $value;
}

function automation_is_sensitive_key(string $key): bool
{
    return (bool) preg_match('/(token|secret|password|passwd|authorization|api[_-]?key|webhook[_-]?url|hook[_-]?url|private[_-]?key)/i', $key);
}

/**
 * @param mixed $value
 */
function automation_mask_secret($value): string
{
    if (!is_scalar($value)) {
        return '[redacted]';
    }

    $string = (string) $value;
    if ($string === '') {
        return '';
    }

    if (strlen($string) <= 8) {
        return '[redacted]';
    }

    return substr($string, 0, 4) . '...' . substr($string, -4);
}
