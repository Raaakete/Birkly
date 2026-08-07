<?php
/**
 * P36-M10 — Ops-hosted starter pack client for CMS onboarding.
 */

declare(strict_types=1);

require_once __DIR__ . '/platform_ops.php';
require_once __DIR__ . '/project_site.php';

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
function birkly_ops_fetch_starters_catalog(?array &$errors = null, int $timeoutSeconds = 12, int $connectTimeoutSeconds = 6): ?array
{
    if (!function_exists('curl_init')) {
        $errors ??= [];
        $errors[] = 'PHP curl extension is not available';
        return null;
    }

    $timeoutSeconds = max(1, $timeoutSeconds);
    $connectTimeoutSeconds = max(1, min($connectTimeoutSeconds, $timeoutSeconds));

    $url = birkly_ops_starters_catalog_url();
    $channel = birkly_ops_release_channel();
    $query = http_build_query(['channel' => $channel]);
    $fullUrl = str_contains($url, '?') ? $url . '&' . $query : $url . '?' . $query;

    $ch = curl_init($fullUrl);
    curl_setopt_array($ch, [
        CURLOPT_HTTPGET => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeoutSeconds,
        CURLOPT_CONNECTTIMEOUT => $connectTimeoutSeconds,
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
 * Absolute path to content/collections (resolves symlinks when possible).
 */
function birkly_content_collections_dir(string $projectRoot): string
{
    $path = rtrim($projectRoot, '/\\') . '/content/collections';
    $resolved = realpath(dirname($path));
    if ($resolved !== false) {
        return $resolved . '/collections';
    }
    return $path;
}

/**
 * Check whether PHP can write to a path (probe file when is_writable() is unreliable).
 */
function birkly_path_is_writable(string $path): bool
{
    if ($path === '') {
        return false;
    }

    if (is_writable($path)) {
        return true;
    }

    if (!is_dir($path) && !is_file($path)) {
        return false;
    }

    $probe = rtrim($path, '/\\') . '/.birkly-write-probe-' . getmypid() . '-' . bin2hex(random_bytes(4));
    if (@file_put_contents($probe, '') === false) {
        return false;
    }

    @unlink($probe);
    return true;
}

/**
 * Ensure content/collections exists and is writable before starter install.
 *
 * @return array{ok:bool,path:string,error?:string}
 */
function birkly_ensure_content_collections_dir(string $projectRoot): array
{
    $contentDir = rtrim($projectRoot, '/\\') . '/content';
    $collectionsDir = birkly_content_collections_dir($projectRoot);

    if (is_link($collectionsDir) || (file_exists($collectionsDir) && !is_dir($collectionsDir))) {
        @unlink($collectionsDir);
    }

    if (!is_dir($contentDir) && !@mkdir($contentDir, 0777, true)) {
        return [
            'ok' => false,
            'path' => $collectionsDir,
            'error' => 'Cannot create content directory at ' . $contentDir,
        ];
    }

    if (!birkly_path_is_writable($contentDir)) {
        @chmod($contentDir, 0777);
    }

    if (!is_dir($collectionsDir) && !@mkdir($collectionsDir, 0777, true)) {
        return [
            'ok' => false,
            'path' => $collectionsDir,
            'error' => 'Cannot create collections directory at ' . $collectionsDir,
        ];
    }

    if (!birkly_path_is_writable($collectionsDir)) {
        @chmod($collectionsDir, 0777);
        @chmod(dirname($collectionsDir), 0777);
    }

    if (!birkly_path_is_writable($collectionsDir)) {
        return [
            'ok' => false,
            'path' => $collectionsDir,
            'error' => 'Collections directory is not writable by the web server',
        ];
    }

    return [
        'ok' => true,
        'path' => realpath($collectionsDir) ?: $collectionsDir,
    ];
}

/**
 * Dry-run probe: download Marsk zip and count extractable collection slugs (no user data changes).
 *
 * @return array<string, mixed>
 */
function birkly_ops_starters_install_probe(string $projectRoot, string $starterId = 'marsk'): array
{
    $probe = [
        'starter_id' => $starterId,
        'collections_dir' => birkly_ensure_content_collections_dir($projectRoot),
        'collections_write_probe' => false,
        'project_site_dir' => birkly_public_site_path(),
        'project_site_writable' => birkly_path_is_writable(birkly_public_site_path())
            || birkly_path_is_writable(dirname(birkly_public_site_path())),
        'zip_download' => false,
        'zip_collection_slugs' => [],
        'errors' => [],
    ];

    $tmpZip = sys_get_temp_dir() . '/birkly-starter-probe-' . $starterId . '-' . getmypid() . '.zip';
    $downloadErrors = [];
    $download = birkly_ops_download_starter_zip($starterId, $tmpZip, $downloadErrors);
    if ($download === null) {
        $probe['errors'] = array_merge($probe['errors'], $downloadErrors);
        @unlink($tmpZip);
        return $probe;
    }

    $probe['zip_download'] = true;
    $zip = new ZipArchive();
    if ($zip->open($tmpZip) === true) {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (str_starts_with($name, 'collections/') && !str_ends_with($name, '/')) {
                $relative = substr($name, strlen('collections/'));
                $slug = explode('/', $relative, 2)[0];
                if ($slug !== '') {
                    $probe['zip_collection_slugs'][$slug] = true;
                }
            }
        }
        $zip->close();
    } else {
        $probe['errors'][] = 'Cannot open downloaded starter zip';
    }
    $probe['zip_collection_slugs'] = array_keys($probe['zip_collection_slugs']);
    @unlink($tmpZip);

    if (($probe['collections_dir']['ok'] ?? false) === true) {
        $collectionsPath = (string) ($probe['collections_dir']['path'] ?? '');
        $probe['collections_write_probe'] = $collectionsPath !== ''
            && birkly_path_is_writable($collectionsPath);
    }

    return $probe;
}

/**
 * Extract Ops starter / site-pack zip into content/collections + project/.
 *
 * Options (P53-M6 / M7):
 * - include_demo_entries (bool, default true): when false, skip collection entry JSON files
 * - zip_prefix (string): optional wrapper directory prefix ending with /
 * - skip_pack_paths (list<string>): pack-relative paths to leave on disk (e.g. project/index.html)
 *
 * Default include_demo_entries=true preserves onboarding / Ops starter behavior.
 *
 * @param array<string,mixed> $options
 * @return array{success:bool,installed_collections:list<string>,project_site:bool,message:string,errors?:list<string>,include_demo_entries?:bool,written_paths?:list<string>,skipped_paths?:list<string>}
 */
function birkly_ops_extract_starter_zip(string $zipPath, string $projectRoot, array $options = []): array
{
    $errors = [];
    $includeDemoEntries = array_key_exists('include_demo_entries', $options)
        ? (bool) $options['include_demo_entries']
        : true;
    $zipPrefix = (string) ($options['zip_prefix'] ?? '');
    if ($zipPrefix !== '' && !str_ends_with($zipPrefix, '/')) {
        $zipPrefix .= '/';
    }
    $skipPackPaths = [];
    foreach ((array) ($options['skip_pack_paths'] ?? []) as $skipPath) {
        if (!is_string($skipPath) || $skipPath === '') {
            continue;
        }
        $normalizedSkip = ltrim(str_replace('\\', '/', $skipPath), '/');
        if ($normalizedSkip !== '' && !str_contains($normalizedSkip, '..')) {
            $skipPackPaths[$normalizedSkip] = true;
        }
    }

    $collectionsReady = birkly_ensure_content_collections_dir($projectRoot);
    if (!$collectionsReady['ok']) {
        return [
            'success' => false,
            'installed_collections' => [],
            'project_site' => false,
            'message' => $collectionsReady['error'] ?? 'Collections directory is not ready',
            'errors' => [$collectionsReady['error'] ?? 'Collections directory is not ready'],
            'include_demo_entries' => $includeDemoEntries,
        ];
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        return [
            'success' => false,
            'installed_collections' => [],
            'project_site' => false,
            'message' => 'Cannot open starter zip',
            'include_demo_entries' => $includeDemoEntries,
        ];
    }

    // Auto-detect wrapper prefix when not provided (manifest.json in a single top folder).
    if ($zipPrefix === '' && function_exists('birkly_marketplace_template_pack_zip_prefix')) {
        $zipPrefix = birkly_marketplace_template_pack_zip_prefix($zip);
    }

    $contentRoot = $collectionsReady['path'];
    $projectSiteRoot = rtrim($projectRoot, '/\\') . '/' . birkly_public_site_dir();
    if (!is_dir($projectSiteRoot) && !@mkdir($projectSiteRoot, 0775, true)) {
        $errors[] = 'Cannot create project site directory at ' . $projectSiteRoot;
    }

    $installed = [];
    $hasProject = false;
    $skippedEntries = 0;
    $writtenPaths = [];
    $skippedPaths = [];

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = (string) $zip->getNameIndex($i);
        $normalized = str_replace('\\', '/', $name);
        if (str_contains($normalized, '..')) {
            $errors[] = 'Unsafe path in zip: ' . $normalized;
            continue;
        }
        if (str_ends_with($normalized, '/')) {
            continue;
        }

        $relativeName = $zipPrefix !== '' && str_starts_with($normalized, $zipPrefix)
            ? substr($normalized, strlen($zipPrefix))
            : $normalized;
        $relativeName = ltrim($relativeName, '/');
        if ($relativeName === '' || $relativeName === 'manifest.json') {
            continue;
        }

        if (isset($skipPackPaths[$relativeName])) {
            $skippedPaths[] = $relativeName;
            if (str_starts_with($relativeName, 'collections/')) {
                $slug = explode('/', substr($relativeName, strlen('collections/')), 2)[0] ?? '';
                if ($slug !== '') {
                    $installed[$slug] = true;
                }
            }
            if (str_starts_with($relativeName, 'project/')) {
                $hasProject = true;
            }
            continue;
        }

        if (str_starts_with($relativeName, 'collections/')) {
            $relative = substr($relativeName, strlen('collections/'));
            if ($relative === '' || str_contains($relative, '..')) {
                continue;
            }
            // P53-M6: demo/sample entries are opt-in — skip collections/*/entries/**
            if (!$includeDemoEntries && preg_match('#(^|/)entries(/|$)#', $relative)) {
                $skippedEntries++;
                $slug = explode('/', $relative, 2)[0];
                if ($slug !== '') {
                    $installed[$slug] = true;
                }
                continue;
            }
            $dest = $contentRoot . '/' . $relative;
            $realContentRoot = realpath($contentRoot) ?: $contentRoot;
            $destDir = dirname($dest);
            if (!is_dir($destDir) && !@mkdir($destDir, 0775, true)) {
                $errors[] = 'Cannot create directory ' . $destDir;
                continue;
            }
            $resolvedDest = realpath($destDir);
            if ($resolvedDest === false || !str_starts_with($resolvedDest, rtrim($realContentRoot, '/\\'))) {
                $errors[] = 'Refusing path outside collections: ' . $relative;
                continue;
            }
            $contents = $zip->getFromIndex($i);
            if ($contents === false) {
                $errors[] = 'Cannot read zip entry ' . $name;
                continue;
            }
            if (@file_put_contents($dest, $contents) === false) {
                $errors[] = 'Cannot write ' . $dest;
                continue;
            }
            $slug = explode('/', $relative, 2)[0];
            $installed[$slug] = true;
            $writtenPaths[] = $relativeName;
            continue;
        }

        if (str_starts_with($relativeName, 'project/')) {
            $relative = substr($relativeName, strlen('project/'));
            if ($relative === '' || str_contains($relative, '..')) {
                continue;
            }
            $dest = $projectSiteRoot . '/' . $relative;
            $destDir = dirname($dest);
            if (!is_dir($destDir) && !@mkdir($destDir, 0775, true)) {
                $errors[] = 'Cannot create directory ' . $destDir;
                continue;
            }
            $realProjectRoot = realpath($projectSiteRoot) ?: $projectSiteRoot;
            $resolvedDest = realpath($destDir);
            if ($resolvedDest === false || !str_starts_with($resolvedDest, rtrim($realProjectRoot, '/\\'))) {
                $errors[] = 'Refusing path outside project: ' . $relative;
                continue;
            }
            $contents = $zip->getFromIndex($i);
            if ($contents === false) {
                $errors[] = 'Cannot read zip entry ' . $name;
                continue;
            }
            if (@file_put_contents($dest, $contents) === false) {
                $errors[] = 'Cannot write ' . $dest;
                continue;
            }
            $hasProject = true;
            $writtenPaths[] = $relativeName;
        }
    }

    $zip->close();

    $installedSlugs = array_keys($installed);
    $success = $installedSlugs !== [] && $hasProject;

    return [
        'success' => $success,
        'installed_collections' => $installedSlugs,
        'project_site' => $hasProject,
        'include_demo_entries' => $includeDemoEntries,
        'skipped_entry_files' => $skippedEntries,
        'written_paths' => array_values(array_unique($writtenPaths)),
        'skipped_paths' => array_values(array_unique($skippedPaths)),
        'message' => $success
            ? 'Starter template installed from Ops'
            : ($errors !== []
                ? 'Starter extract failed: ' . implode('; ', array_slice($errors, 0, 3))
                : 'Starter zip had no installable files'),
        'errors' => $errors,
    ];
}

/**
 * @return array<string, array{enabled:bool,ready:bool,selectable:bool,source?:string}>
 */
function birkly_ops_starter_availability_overlay(string $projectRoot, int $timeoutSeconds = 4, int $connectTimeoutSeconds = 2): array
{
    // Onboarding loads this on first paint — keep the Ops probe short so a
    // blocked/slow outbound network does not make admin setup feel hung.
    $errors = [];
    $catalog = birkly_ops_fetch_starters_catalog($errors, $timeoutSeconds, $connectTimeoutSeconds);
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
