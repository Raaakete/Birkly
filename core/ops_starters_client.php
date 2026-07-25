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
    $base = preg_replace('#/starters(\?.*)?$#', '', $catalogUrl) ?: $catalogUrl;
    return rtrim($base, '/') . '/starters/' . rawurlencode($starterId) . '/download';
}

/**
 * Normalize Ops artifact URLs to HTTPS (Ops may emit http:// behind a reverse proxy).
 */
function birkly_ops_normalize_download_url(string $url): string
{
    $url = trim($url);
    if (str_starts_with($url, 'http://')) {
        return 'https://' . substr($url, 7);
    }
    return $url;
}

/**
 * @return array{catalog_reachable:bool,catalog_url:string,channel:string,starter_id?:string,selectable?:bool,errors:list<string>}
 */
function birkly_ops_starters_diagnostics(?string $starterId = null): array
{
    $diagnostics = [
        'catalog_reachable' => false,
        'catalog_url' => birkly_ops_starters_catalog_url(),
        'channel' => birkly_ops_release_channel(),
        'errors' => [],
    ];

    if (!function_exists('curl_init')) {
        $diagnostics['errors'][] = 'PHP curl extension is not available';
        return $diagnostics;
    }

    $catalog = birkly_ops_fetch_starters_catalog($diagnostics['errors']);
    $diagnostics['catalog_reachable'] = $catalog !== null;
    if ($catalog === null) {
        if ($diagnostics['errors'] === []) {
            $diagnostics['errors'][] = 'Ops starter catalog request failed';
        }
        return $diagnostics;
    }

    if ($starterId === null || $starterId === '') {
        return $diagnostics;
    }

    $starterId = strtolower(trim($starterId));
    $diagnostics['starter_id'] = $starterId;
    $overlay = birkly_ops_starter_availability_overlay(birkly_project_root());
    $diagnostics['selectable'] = !empty($overlay[$starterId]['selectable']);

    if (empty($diagnostics['selectable'])) {
        $diagnostics['errors'][] = 'Starter is not published or not enabled for onboarding on Ops';
    }

    $info = birkly_ops_starter_download_info($starterId, $diagnostics['errors']);
    if ($info === null) {
        if ($diagnostics['errors'] === []) {
            $diagnostics['errors'][] = 'Ops starter download metadata request failed';
        }
        return $diagnostics;
    }

    $downloadUrl = birkly_ops_normalize_download_url((string) ($info['download_url'] ?? ''));
    if ($downloadUrl === '') {
        $diagnostics['errors'][] = 'Ops did not return a download URL';
    }

    return $diagnostics;
}

/**
 * @return array<string, mixed>|null
 */
function birkly_ops_fetch_starters_catalog(?array &$errors = null): ?array
{
    if (!function_exists('curl_init')) {
        $errors ??= [];
        $errors[] = 'PHP curl extension is not available';
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
        CURLOPT_TIMEOUT => 12,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $response = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if (!is_string($response) || $code < 200 || $code >= 300) {
        if ($errors !== null) {
            $errors[] = $curlError !== ''
                ? 'Catalog request error: ' . $curlError
                : 'Catalog request failed with HTTP ' . $code;
        }
        return null;
    }

    $data = json_decode($response, true);
    if (!is_array($data) || empty($data['ok']) || !is_array($data['starters'] ?? null)) {
        if ($errors !== null) {
            $errors[] = 'Catalog response was not valid JSON from Ops';
        }
        return null;
    }

    return $data;
}

/**
 * @return array<string, mixed>|null
 */
function birkly_ops_starter_download_info(string $starterId, ?array &$errors = null): ?array
{
    if (!function_exists('curl_init')) {
        $errors ??= [];
        $errors[] = 'PHP curl extension is not available';
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
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
    ]);
    $response = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if (!is_string($response) || $code < 200 || $code >= 300) {
        if ($errors !== null) {
            $errors[] = $curlError !== ''
                ? 'Download metadata error: ' . $curlError
                : 'Download metadata failed with HTTP ' . $code;
        }
        return null;
    }

    $data = json_decode($response, true);
    if (!is_array($data) || empty($data['ok'])) {
        if ($errors !== null) {
            $errors[] = 'Download metadata response was not valid JSON from Ops';
        }
        return null;
    }

    return $data;
}

/**
 * @return array<string, mixed>|null
 */
function birkly_ops_download_starter_zip(string $starterId, string $destPath, ?array &$errors = null): ?array
{
    $info = birkly_ops_starter_download_info($starterId, $errors);
    if ($info === null) {
        return null;
    }

    $downloadUrl = birkly_ops_normalize_download_url((string) ($info['download_url'] ?? ''));
    if ($downloadUrl === '' || !function_exists('curl_init')) {
        if ($errors !== null) {
            $errors[] = 'Ops did not return a download URL';
        }
        return null;
    }

    $fp = fopen($destPath, 'wb');
    if ($fp === false) {
        if ($errors !== null) {
            $errors[] = 'Cannot write starter zip to temp directory';
        }
        return null;
    }

    $ch = curl_init($downloadUrl);
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_HTTPHEADER => ['Accept: application/zip'],
    ]);
    $ok = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    fclose($fp);

    if ($ok !== true || $code < 200 || $code >= 300 || !is_file($destPath) || filesize($destPath) === 0) {
        @unlink($destPath);
        if ($errors !== null) {
            $errors[] = $curlError !== ''
                ? 'Artifact download error: ' . $curlError
                : 'Artifact download failed with HTTP ' . $code;
        }
        return null;
    }

    $expected = strtolower(trim((string) ($info['checksum_sha256'] ?? '')));
    if ($expected !== '' && hash_file('sha256', $destPath) !== $expected) {
        @unlink($destPath);
        if ($errors !== null) {
            $errors[] = 'Downloaded starter zip checksum mismatch';
        }
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
