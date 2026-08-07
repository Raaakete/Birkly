<?php
/**
 * P34-M18 — Outbound Ops client (feedback relay already in api/feedback.php).
 */

declare(strict_types=1);

require_once __DIR__ . '/platform.php';

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

function birkly_ops_updates_check_url(): string
{
    $config = birkly_platform_config();
    $fromConfig = trim((string) ($config['updates_url'] ?? ''));
    if ($fromConfig !== '') {
        return $fromConfig;
    }

    return trim((string) (getenv('BIRKLY_OPS_UPDATES_URL') ?: ''));
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

    $payload = [
        'installation_id' => birkly_platform_install_id(),
        'cms_version' => class_exists('BirklyVersion') ? BirklyVersion::getCurrentVersion() : '0.0.0',
        'channel' => birkly_ops_release_channel(),
        'program_tier' => trim((string) (birkly_platform_config()['program_tier'] ?? 'closed_alpha')),
        'host' => trim((string) ($_SERVER['HTTP_HOST'] ?? '')),
        'last_seen' => gmdate('c'),
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
    $response = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!is_string($response) || $code < 200 || $code >= 300) {
        // Avoid hammering a dead endpoint on every page load.
        if (!$force) {
            @touch($stampFile);
        }
        return;
    }

    $data = json_decode($response, true);
    if (!is_array($data) || empty($data['ok'])) {
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
    }
}

/**
 * @return array<string, mixed>|null Ops release check payload or null when not configured / failed
 */
function birkly_ops_check_updates(string $currentVersion): ?array
{
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
