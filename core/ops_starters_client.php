<?php
/**
 * P36-M10 — Ops-hosted starter pack client for CMS onboarding.
 */

declare(strict_types=1);

require_once __DIR__ . '/platform_ops.php';

function birkly_ops_starters_catalog_url(): string
{
    $config = birkly_platform_config();
    $fromConfig = trim((string) ($config['starters_catalog_url'] ?? ''));
    if ($fromConfig !== '') {
        return $fromConfig;
    }

    $env = trim((string) (getenv('BIRKLY_OPS_STARTERS_URL') ?: ''));
    if ($env !== '') {
        return $env;
    }

    $updates = birkly_ops_updates_check_url();
    if ($updates !== '' && str_contains($updates, '/api/ops/v1/releases/check')) {
        return str_replace('/api/ops/v1/releases/check', '/api/ops/v1/starters', $updates);
    }

    $relay = birkly_feedback_relay_url();
    if ($relay !== '' && str_contains($relay, '/api/ops/v1/feedback')) {
        return str_replace('/api/ops/v1/feedback', '/api/ops/v1/starters', $relay);
    }

    return 'https://ops.birkly.cloud/api/ops/v1/starters';
}

function birkly_ops_starters_download_endpoint(string $starterId): string
{
    $catalogUrl = birkly_ops_starters_catalog_url();
    return rtrim(str_replace('/starters', '/starters/' . rawurlencode($starterId) . '/download', $catalogUrl), '/');
}

/**
 * @return array<string, mixed>|null
 */
function birkly_ops_fetch_starters_catalog(): ?array
{
    if (!function_exists('curl_init')) {
        return null;
    }

    $url = birkly_ops_starters_catalog_url();
    $channel = birkly_ops_release_channel();
    $query = http_build_query(['channel' => $channel]);
    $fullUrl = str_contains($url, '?') ? $url . '&' . $query : $url . '?' . $query;

    $ch = curl_init($fullUrl);
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
    if (!is_array($data) || empty($data['ok']) || !is_array($data['starters'] ?? null)) {
        return null;
    }

    return $data;
}

/**
 * @return array<string, mixed>|null
 */
function birkly_ops_starter_download_info(string $starterId): ?array
{
    if (!function_exists('curl_init')) {
        return null;
    }

    $url = birkly_ops_starters_download_endpoint($starterId);
    $payload = json_encode([
        'channel' => birkly_ops_release_channel(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        return null;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
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

/**
 * @return array<string, mixed>|null
 */
function birkly_ops_download_starter_zip(string $starterId, string $destPath): ?array
{
    $info = birkly_ops_starter_download_info($starterId);
    if ($info === null) {
        return null;
    }

    $downloadUrl = trim((string) ($info['download_url'] ?? ''));
    if ($downloadUrl === '' || !function_exists('curl_init')) {
        return null;
    }
    if (str_starts_with($downloadUrl, 'http://')) {
        $downloadUrl = 'https://' . substr($downloadUrl, 7);
    }

    $fp = fopen($destPath, 'wb');
    if ($fp === false) {
        return null;
    }

    $ch = curl_init($downloadUrl);
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_HTTPHEADER => ['Accept: application/zip'],
    ]);
    $ok = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);

    if ($ok !== true || $code < 200 || $code >= 300 || !is_file($destPath) || filesize($destPath) === 0) {
        @unlink($destPath);
        return null;
    }

    $expected = strtolower(trim((string) ($info['checksum_sha256'] ?? '')));
    if ($expected !== '' && hash_file('sha256', $destPath) !== $expected) {
        @unlink($destPath);
        return null;
    }

    return $info;
}

/**
 * @return array{success:bool,installed_collections:list<string>,project_site:bool,message:string}
 */
function birkly_ops_extract_starter_zip(string $zipPath, string $projectRoot): array
{
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        return ['success' => false, 'installed_collections' => [], 'project_site' => false, 'message' => 'Cannot open starter zip'];
    }

    $contentRoot = $projectRoot . '/content/collections';
    $projectSiteRoot = $projectRoot . '/' . birkly_public_site_dir();
    $installed = [];
    $hasProject = false;

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = (string) $zip->getNameIndex($i);
        if ($name === 'manifest.json' || str_ends_with($name, '/')) {
            continue;
        }

        if (str_starts_with($name, 'collections/')) {
            $relative = substr($name, strlen('collections/'));
            if ($relative === '' || str_contains($relative, '..')) {
                continue;
            }
            $dest = $contentRoot . '/' . $relative;
            $destDir = dirname($dest);
            if (!is_dir($destDir) && !mkdir($destDir, 0755, true)) {
                continue;
            }
            $contents = $zip->getFromIndex($i);
            if ($contents !== false) {
                file_put_contents($dest, $contents);
                $slug = explode('/', $relative, 2)[0];
                $installed[$slug] = true;
            }
            continue;
        }

        if (str_starts_with($name, 'project/')) {
            $relative = substr($name, strlen('project/'));
            if ($relative === '' || str_contains($relative, '..')) {
                continue;
            }
            $dest = $projectSiteRoot . '/' . $relative;
            $destDir = dirname($dest);
            if (!is_dir($destDir) && !mkdir($destDir, 0755, true)) {
                continue;
            }
            $contents = $zip->getFromIndex($i);
            if ($contents !== false) {
                file_put_contents($dest, $contents);
                $hasProject = true;
            }
        }
    }

    $zip->close();

    return [
        'success' => $installed !== [] || $hasProject,
        'installed_collections' => array_keys($installed),
        'project_site' => $hasProject,
        'message' => ($installed !== [] || $hasProject) ? 'Starter template installed from Ops' : 'Starter zip had no installable files',
    ];
}

/**
 * @return array<string, array{enabled:bool,ready:bool,selectable:bool,source?:string}>
 */
function birkly_ops_starter_availability_overlay(string $projectRoot): array
{
    $catalog = birkly_ops_fetch_starters_catalog();
    if ($catalog === null) {
        return [];
    }

    $overlay = [];
    foreach ($catalog['starters'] as $starter) {
        if (!is_array($starter)) {
            continue;
        }
        $id = strtolower(trim((string) ($starter['id'] ?? '')));
        if ($id === '') {
            continue;
        }
        $enabled = (bool) ($starter['onboarding_enabled'] ?? false);
        $ready = trim((string) ($starter['latest_version'] ?? '')) !== '';
        $overlay[$id] = [
            'enabled' => $enabled,
            'ready' => $ready,
            'selectable' => $enabled && $ready,
            'source' => 'ops',
        ];
    }

    $overlay['blank'] = [
        'enabled' => true,
        'ready' => true,
        'selectable' => true,
        'source' => 'local',
    ];

    return $overlay;
}
