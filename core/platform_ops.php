<?php
/**
 * P34-M18 — Outbound Ops client (feedback relay already in api/feedback.php).
 */

declare(strict_types=1);

require_once __DIR__ . '/platform.php';
require_once __DIR__ . '/outbound_http.php';

function birkly_ops_installations_register_url(): string
{
    $config = birkly_platform_config();
    $fromConfig = trim((string) ($config['installations_register_url'] ?? ''));
    if ($fromConfig !== '') {
        return $fromConfig;
    }

    $env = trim((string) (getenv('BIRKLY_OPS_INSTALLATIONS_REGISTER_URL') ?: ''));
    if ($env !== '') {
        return $env;
    }

    $relay = birkly_feedback_relay_url();
    if ($relay !== '' && str_contains($relay, '/api/ops/v1/feedback')) {
        return str_replace('/api/ops/v1/feedback', '/api/ops/v1/installations/register', $relay);
    }

    return '';
}

/**
 * @return list<string>
 */
function birkly_ops_local_instance_base_urls(): array
{
    return [
        'http://127.0.0.1:8090',
        'http://localhost:8090',
        'http://host.docker.internal:8090',
    ];
}

function birkly_ops_probe_health(string $baseUrl, int $timeout = 2): bool
{
    $baseUrl = rtrim(trim($baseUrl), '/');
    if ($baseUrl === '' || !function_exists('curl_init')) {
        return false;
    }

    $ch = curl_init($baseUrl . '/health');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => max(1, $timeout),
        CURLOPT_CONNECTTIMEOUT => max(1, min(3, $timeout)),
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    birkly_outbound_curl_apply_transport_defaults($ch);
    $response = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!is_string($response) || $code < 200 || $code >= 300) {
        return false;
    }

    $data = json_decode($response, true);

    return is_array($data) && (string) ($data['status'] ?? '') === 'ok';
}

function birkly_ops_discover_local_updates_check_url(): string
{
    foreach (birkly_ops_local_instance_base_urls() as $base) {
        if (birkly_ops_probe_health($base, 2)) {
            return rtrim($base, '/') . '/api/ops/v1/releases/check';
        }
    }

    return '';
}

function birkly_ops_updates_check_url(): string
{
    $config = birkly_platform_config();
    $fromConfig = trim((string) ($config['updates_url'] ?? ''));
    if ($fromConfig !== '') {
        return $fromConfig;
    }

    $env = trim((string) (getenv('BIRKLY_OPS_UPDATES_URL') ?: ''));
    if ($env !== '') {
        return $env;
    }

    $relay = birkly_feedback_relay_url();
    if ($relay !== '' && str_contains($relay, '/api/ops/v1/feedback')) {
        return str_replace('/api/ops/v1/feedback', '/api/ops/v1/releases/check', $relay);
    }

    if (!function_exists('birkly_is_local_dev_request')) {
        require_once __DIR__ . '/birkly_paths.php';
    }
    if (birkly_is_local_dev_request() || birkly_is_platform_owner_install()) {
        $local = birkly_ops_discover_local_updates_check_url();
        if ($local !== '') {
            return $local;
        }
    }

    if (birkly_is_platform_owner_install()) {
        return 'https://ops.birkly.cloud/api/ops/v1/releases/check';
    }

    return '';
}

function birkly_ops_release_channel(): string
{
    $config = birkly_platform_config();
    $channel = strtolower(trim((string) ($config['release_channel'] ?? getenv('BIRKLY_RELEASE_CHANNEL') ?: 'stable')));
    return in_array($channel, ['alpha', 'beta', 'stable'], true) ? $channel : 'stable';
}

function birkly_ops_request_signature(string $body): string
{
    $secret = birkly_feedback_relay_secret();
    return $secret !== '' ? hash_hmac('sha256', $body, $secret) : '';
}

/**
 * Register or refresh this CMS install on Ops (best-effort).
 * Persists per-install feedback_relay_secret when Ops returns one.
 *
 * Throttled by default so admin HTML is not blocked on every navigation.
 * Pass $force=true from explicit admin actions (e.g. feedback) when needed.
 */
function birkly_ops_register_installation(bool $force = false): void
{
    $url = birkly_ops_installations_register_url();
    if ($url === '' || !function_exists('curl_init')) {
        return;
    }

    require_once __DIR__ . '/birkly_paths.php';
    $stampFile = rtrim(birkly_project_root(), '/\\') . '/settings/.ops_register_stamp';
    $ttlSeconds = 6 * 60 * 60; // 6 hours
    if (!$force && is_file($stampFile) && (time() - (int) filemtime($stampFile)) < $ttlSeconds) {
        return;
    }

    require_once __DIR__ . '/version.php';
    require_once __DIR__ . '/hosting_urls.php';
    require_once __DIR__ . '/hosting_probe.php';

    $hostingConfig = birkly_hosting_load();
    $deliveryProbe = birkly_delivery_probe(['deep' => false]);

    $payload = [
        'installation_id' => birkly_platform_install_id(),
        'cms_version' => class_exists('BirklyVersion') ? BirklyVersion::getCurrentVersion() : '0.0.0',
        'channel' => birkly_ops_release_channel(),
        'program_tier' => trim((string) (birkly_platform_config()['program_tier'] ?? 'closed_alpha')),
        'host' => trim((string) ($_SERVER['HTTP_HOST'] ?? '')),
        'last_seen' => gmdate('c'),
        'cms_base_url' => (string) ($hostingConfig['cms_base_url'] ?? ''),
        'public_site_mode' => (string) ($hostingConfig['public_site_mode'] ?? 'none'),
        'delivery_health_summary' => (string) ($deliveryProbe['overall'] ?? 'unknown'),
    ];
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($body === false) {
        return;
    }

    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    $sig = birkly_ops_request_signature($body);
    if ($sig !== '') {
        $headers[] = 'X-Birkly-Ops-Signature: ' . $sig;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        // Keep short: this runs on the admin request path when the stamp expires.
        CURLOPT_TIMEOUT => $force ? 6 : 3,
        CURLOPT_CONNECTTIMEOUT => $force ? 3 : 2,
    ]);
    birkly_outbound_curl_apply_transport_defaults($ch);
    $response = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if (!is_string($response) || $code < 200 || $code >= 300) {
        if ($force) {
            error_log(
                'ops register failed HTTP ' . $code .
                ' for install ' . birkly_platform_install_id() .
                ($curlErr !== '' ? (' curl=' . $curlErr) : '') .
                (is_string($response) ? (' body=' . substr($response, 0, 200)) : '')
            );
        }
        // Avoid hammering a dead endpoint on every page load.
        if (!$force) {
            @touch($stampFile);
        }
        return;
    }

    $data = json_decode($response, true);
    if (!is_array($data) || empty($data['ok'])) {
        if ($force) {
            error_log(
                'ops register returned non-ok payload for install ' . birkly_platform_install_id() .
                (is_string($response) ? (' body=' . substr($response, 0, 200)) : '')
            );
        }
        if (!$force) {
            @touch($stampFile);
        }
        return;
    }

    @touch($stampFile);

    $installation = is_array($data['installation'] ?? null) ? $data['installation'] : [];
    $relaySecret = trim((string) ($installation['feedback_relay_secret'] ?? ''));
    if ($relaySecret !== '' && birkly_feedback_relay_secret() === '') {
        birkly_platform_persist_config(['feedback_relay_secret' => $relaySecret]);
    } elseif ($force && $relaySecret === '' && birkly_feedback_relay_secret() === '') {
        error_log('ops register ok but no feedback_relay_secret for install ' . birkly_platform_install_id());
    }
}

/**
 * @return array<string, mixed>|null Ops release check payload or null when not configured / failed
 */
function birkly_ops_check_updates(string $currentVersion): ?array
{
    if (isset($GLOBALS['birkly_ops_check_updates_override']) && is_callable($GLOBALS['birkly_ops_check_updates_override'])) {
        return ($GLOBALS['birkly_ops_check_updates_override'])($currentVersion);
    }

    $baseUrl = birkly_ops_updates_check_url();
    if ($baseUrl === '' || !function_exists('curl_init')) {
        return null;
    }

    $query = http_build_query([
        'installation_id' => birkly_platform_install_id(),
        'channel' => birkly_ops_release_channel(),
        'version' => $currentVersion,
    ]);
    $url = str_contains($baseUrl, '?') ? $baseUrl . '&' . $query : $baseUrl . '?' . $query;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPGET => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    birkly_outbound_curl_apply_transport_defaults($ch);
    $response = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!is_string($response) || $code < 200 || $code >= 300) {
        return null;
    }

    $data = json_decode($response, true);
    if (!is_array($data) || empty($data['ok'])) {
        return null;
    }

    return $data;
}
