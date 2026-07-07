<?php
/**
 * Marketplace plugin install and upgrade from verified zip artifacts.
 */

if (!function_exists('validate_plugin')) {
    require_once dirname(__DIR__) . '/plugins.php';
}

/**
 * Download marketplace artifact to a temp file.
 *
 * @return array{ok:bool,path?:string,error?:string}
 */
function birkly_marketplace_download_artifact(string $downloadUrl, ?string $expectedSha256 = null): array
{
    $downloadUrl = trim($downloadUrl);
    if ($downloadUrl === '') {
        return ['ok' => false, 'error' => 'Download URL is required'];
    }

    $tmp = tempnam(sys_get_temp_dir(), 'birkly-mp-');
    if ($tmp === false) {
        return ['ok' => false, 'error' => 'Failed to create temp file'];
    }

    $context = stream_context_create([
        'http' => [
            'timeout' => 60,
            'ignore_errors' => true,
            'header' => "User-Agent: Birkly-CMS\r\n",
        ],
    ]);

    $content = @file_get_contents($downloadUrl, false, $context);
    if ($content === false) {
        @unlink($tmp);
        return ['ok' => false, 'error' => 'Failed to download artifact'];
    }

    if (file_put_contents($tmp, $content) === false) {
        @unlink($tmp);
        return ['ok' => false, 'error' => 'Failed to save artifact'];
    }

    if ($expectedSha256 !== null && $expectedSha256 !== '') {
        $actual = hash_file('sha256', $tmp);
        if (!hash_equals(strtolower($expectedSha256), strtolower($actual))) {
            @unlink($tmp);
            return ['ok' => false, 'error' => 'Checksum verification failed'];
        }
    }

    return ['ok' => true, 'path' => $tmp];
}

/**
 * Resolve plugin directory name from zip contents and marketplace slug.
 */
function birkly_marketplace_resolve_plugin_id(string $zipPath, string $marketplaceSlug): string
{
    $slug = preg_replace('/[^a-z0-9_-]/i', '', $marketplaceSlug) ?? '';
    if ($slug !== '') {
        return strtolower($slug);
    }

    if (!class_exists('ZipArchive')) {
        return 'plugin';
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        return 'plugin';
    }

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (!is_string($name) || str_ends_with($name, '/')) {
            continue;
        }
        if (basename($name) === 'plugin.json') {
            $content = $zip->getFromIndex($i);
            if (is_string($content)) {
                $json = json_decode($content, true);
                if (is_array($json) && !empty($json['name'])) {
                    $id = preg_replace('/[^a-z0-9_-]/i', '-', strtolower((string) $json['name']));
                    $zip->close();
                    return trim((string) $id, '-') ?: 'plugin';
                }
            }
        }
    }
    $zip->close();
    return 'plugin';
}

/**
 * Extract plugin zip into plugins/{plugin_id}/.
 *
 * @return array{ok:bool,plugin_id?:string,path?:string,errors:array}
 */
function birkly_marketplace_extract_plugin_zip(string $zipPath, string $pluginId, bool $replaceExisting = false): array
{
    $errors = [];
    $pluginId = preg_replace('/[^a-z0-9_-]/i', '', $pluginId) ?? '';
    if ($pluginId === '') {
        return ['ok' => false, 'errors' => ['Invalid plugin id']];
    }

    if (!class_exists('ZipArchive')) {
        return ['ok' => false, 'errors' => ['ZipArchive extension not available']];
    }

    $pluginsRoot = dirname(__DIR__, 2) . '/plugins';
    $dest = $pluginsRoot . '/' . $pluginId;

    if (is_dir($dest)) {
        if (!$replaceExisting) {
            return ['ok' => false, 'errors' => ["Plugin directory '{$pluginId}' already exists"]];
        }
        birkly_marketplace_remove_directory($dest);
    }

    if (!is_dir($pluginsRoot)) {
        mkdir($pluginsRoot, 0755, true);
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        return ['ok' => false, 'errors' => ['Cannot open zip archive']];
    }

    $prefix = birkly_marketplace_zip_root_prefix($zip);
    mkdir($dest, 0755, true);

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (!is_string($name) || str_ends_with($name, '/')) {
            continue;
        }
        if (str_contains($name, '..')) {
            $zip->close();
            birkly_marketplace_remove_directory($dest);
            return ['ok' => false, 'errors' => ['Unsafe path in zip: ' . $name]];
        }

        $relative = $prefix !== '' && str_starts_with($name, $prefix)
            ? substr($name, strlen($prefix))
            : $name;
        $relative = ltrim($relative, '/');
        if ($relative === '') {
            continue;
        }

        $target = $dest . '/' . $relative;
        $targetDir = dirname($target);
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $content = $zip->getFromIndex($i);
        if ($content === false || file_put_contents($target, $content) === false) {
            $errors[] = 'Failed to extract: ' . $relative;
        }
    }

    $zip->close();

    if ($errors !== []) {
        birkly_marketplace_remove_directory($dest);
        return ['ok' => false, 'errors' => $errors];
    }

    $validationErrors = validate_plugin($dest);
    if ($validationErrors !== []) {
        birkly_marketplace_remove_directory($dest);
        return ['ok' => false, 'errors' => $validationErrors];
    }

    return ['ok' => true, 'plugin_id' => $pluginId, 'path' => $dest, 'errors' => []];
}

function birkly_marketplace_zip_root_prefix(ZipArchive $zip): string
{
    $topLevels = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (!is_string($name) || $name === '') {
            continue;
        }
        $parts = explode('/', rtrim($name, '/'));
        if ($parts[0] !== '') {
            $topLevels[$parts[0]] = ($topLevels[$parts[0]] ?? 0) + 1;
        }
    }

    if (count($topLevels) === 1) {
        $only = array_key_first($topLevels);
        $hasRootFiles = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (is_string($name) && !str_contains(rtrim($name, '/'), '/')) {
                $hasRootFiles = true;
                break;
            }
        }
        if (!$hasRootFiles && is_string($only)) {
            return $only . '/';
        }
    }

    return '';
}

function birkly_marketplace_remove_directory(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = array_diff(scandir($dir) ?: [], ['.', '..']);
    foreach ($items as $item) {
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            birkly_marketplace_remove_directory($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

/**
 * Install or upgrade a marketplace plugin from slug + version.
 *
 * @return array{ok:bool,plugin_id?:string,version?:string,errors:array,preflight?:array}
 */
function birkly_marketplace_install_plugin(
    string $slug,
    string $version,
    string $licenseKey,
    bool $upgrade = false
): array {
    $release = birkly_marketplace_release_download($slug, $version);
    if (!$release['ok']) {
        return ['ok' => false, 'errors' => [$release['error'] ?? 'Release lookup failed']];
    }

    $download = birkly_marketplace_download_artifact(
        (string) ($release['download_url'] ?? ''),
        $release['checksum_sha256'] ?? null
    );
    if (!$download['ok'] || empty($download['path'])) {
        return ['ok' => false, 'errors' => [$download['error'] ?? 'Download failed']];
    }

    $zipPath = $download['path'];
    $preview = birkly_marketplace_preview_zip_capabilities($zipPath);
    $pluginId = birkly_marketplace_resolve_plugin_id($zipPath, $slug);

    if ($upgrade) {
        global $birkly_plugins;
        if (!isset($birkly_plugins[$pluginId])) {
            @unlink($zipPath);
            return ['ok' => false, 'errors' => ['Plugin not installed']];
        }
    }

    $extract = birkly_marketplace_extract_plugin_zip($zipPath, $pluginId, $upgrade);
    @unlink($zipPath);

    if (!$extract['ok']) {
        return ['ok' => false, 'errors' => $extract['errors'], 'preflight' => $preview];
    }

    load_plugins();
    $depErrors = check_plugin_dependencies($pluginId);
    if ($depErrors !== []) {
        return ['ok' => false, 'errors' => $depErrors, 'preflight' => $preview];
    }

    birkly_marketplace_save_plugin_source($pluginId, $slug, $version, $licenseKey);

    return [
        'ok' => true,
        'plugin_id' => $pluginId,
        'version' => $version,
        'errors' => [],
        'preflight' => $preview,
    ];
}

function birkly_marketplace_save_plugin_source(string $pluginId, string $slug, string $version, string $licenseKey): void
{
    global $birkly_plugins;
    if (!isset($birkly_plugins[$pluginId])) {
        load_plugins();
    }

    if (isset($birkly_plugins[$pluginId])) {
        $birkly_plugins[$pluginId]['marketplace_slug'] = $slug;
        $birkly_plugins[$pluginId]['marketplace_version'] = $version;
        if ($licenseKey !== '' && !str_starts_with($licenseKey, 'BIRKLY-LOCAL-')) {
            $birkly_plugins[$pluginId]['license_ref'] = birkly_marketplace_license_ref($licenseKey);
        }
        if (isset($birkly_plugins[$pluginId]['version'])) {
            $birkly_plugins[$pluginId]['version'] = $version;
        }
        save_plugin_states();
    }
}

/**
 * @return array<string, array<string, mixed>>
 */
function birkly_marketplace_installed_plugin_updates(): array
{
    $plugins = get_all_plugins();
    $updates = [];

    foreach ($plugins as $id => $plugin) {
        $slug = birkly_marketplace_plugin_offer_slug((string) $id, $plugin);
        if ($slug === '') {
            continue;
        }
        $current = (string) ($plugin['version'] ?? $plugin['marketplace_version'] ?? '0.0.0');
        $licenseRef = trim((string) ($plugin['license_ref'] ?? ''));
        $check = birkly_marketplace_check_plugin_update($slug, $current, $licenseRef !== '' ? $licenseRef : null);
        $updates[$id] = [
            'plugin_id' => $id,
            'marketplace_slug' => $slug,
            'current_version' => $current,
            'latest_version' => $check['latest_version'] ?? $current,
            'update_available' => !empty($check['update_available']),
            'license_lapsed' => !empty($check['license_lapsed']),
            'license_warning' => $check['license_warning'] ?? null,
            'check_ok' => !empty($check['ok']),
            'error' => $check['error'] ?? null,
        ];
    }

    return $updates;
}

function birkly_marketplace_themes_dir(): string
{
    if (isset($GLOBALS['__birkly_themes_dir'])) {
        return (string) $GLOBALS['__birkly_themes_dir'];
    }
    return dirname(__DIR__, 2) . '/themes';
}

function birkly_marketplace_resolve_theme_id(string $zipPath, string $marketplaceSlug, string $offerType = 'theme'): string
{
    $slug = preg_replace('/[^a-z0-9_-]/i', '', $marketplaceSlug) ?? '';
    if ($slug !== '') {
        return strtolower($slug);
    }

    if (!class_exists('ZipArchive')) {
        return $offerType === 'template_pack' ? 'template-pack' : 'theme';
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        return 'theme';
    }

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (!is_string($name) || str_ends_with($name, '/')) {
            continue;
        }
        if (basename($name) === 'theme.json') {
            $content = $zip->getFromIndex($i);
            if (is_string($content)) {
                $json = json_decode($content, true);
                if (is_array($json) && !empty($json['id'])) {
                    $id = preg_replace('/[^a-z0-9_-]/i', '-', strtolower((string) $json['id']));
                    $zip->close();
                    return trim((string) $id, '-') ?: 'theme';
                }
            }
        }
    }
    $zip->close();
    return 'theme';
}

/**
 * @return array{ok:bool,theme_id?:string,path?:string,errors:array}
 */
function birkly_marketplace_extract_theme_zip(string $zipPath, string $themeId, bool $replaceExisting = false): array
{
    $errors = [];
    $themeId = preg_replace('/[^a-z0-9_-]/i', '', $themeId) ?? '';
    if ($themeId === '') {
        return ['ok' => false, 'errors' => ['Invalid theme id']];
    }

    if (!class_exists('ZipArchive')) {
        return ['ok' => false, 'errors' => ['ZipArchive extension not available']];
    }

    $themesRoot = birkly_marketplace_themes_dir();
    $dest = $themesRoot . '/' . $themeId;

    if (is_dir($dest)) {
        if (!$replaceExisting) {
            return ['ok' => false, 'errors' => ["Theme directory '{$themeId}' already exists"]];
        }
        birkly_marketplace_remove_directory($dest);
    }

    if (!is_dir($themesRoot)) {
        mkdir($themesRoot, 0755, true);
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        return ['ok' => false, 'errors' => ['Cannot open zip archive']];
    }

    $prefix = birkly_marketplace_zip_root_prefix($zip);
    $hasTemplate = false;
    mkdir($dest, 0755, true);

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (!is_string($name) || str_ends_with($name, '/')) {
            continue;
        }
        if (str_contains($name, '..')) {
            $zip->close();
            birkly_marketplace_remove_directory($dest);
            return ['ok' => false, 'errors' => ['Unsafe path in zip: ' . $name]];
        }

        $relative = $prefix !== '' && str_starts_with($name, $prefix)
            ? substr($name, strlen($prefix))
            : $name;
        $relative = ltrim($relative, '/');
        if ($relative === '') {
            continue;
        }

        if (preg_match('/\.(html|htm|css|js|json|md|svg|png|jpg|jpeg|webp)$/i', $relative)) {
            $hasTemplate = true;
        }

        $target = $dest . '/' . $relative;
        $targetDir = dirname($target);
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $content = $zip->getFromIndex($i);
        if ($content === false || file_put_contents($target, $content) === false) {
            $errors[] = 'Failed to extract: ' . $relative;
        }
    }

    $zip->close();

    if ($errors !== []) {
        birkly_marketplace_remove_directory($dest);
        return ['ok' => false, 'errors' => $errors];
    }

    if (!$hasTemplate) {
        birkly_marketplace_remove_directory($dest);
        return ['ok' => false, 'errors' => ['Theme zip must contain at least one template asset']];
    }

    return ['ok' => true, 'theme_id' => $themeId, 'path' => $dest, 'errors' => []];
}

function birkly_marketplace_save_theme_source(string $themeId, string $slug, string $version, string $offerType): void
{
    require_once dirname(__DIR__, 2) . '/api/storage.php';
    $storage = new Storage(birkly_marketplace_settings_dir());
    $data = $storage->read('installed_themes.json') ?? [];
    if (!is_array($data)) {
        $data = [];
    }
    $data[$themeId] = [
        'theme_id' => $themeId,
        'marketplace_slug' => $slug,
        'offer_type' => $offerType,
        'version' => $version,
        'installed_at' => gmdate('Y-m-d\TH:i:s\Z'),
        'path' => birkly_marketplace_themes_dir() . '/' . $themeId,
    ];
    $storage->write('installed_themes.json', $data);
}

/**
 * @return array{ok:bool,theme_id?:string,version?:string,offer_type?:string,path?:string,errors:array}
 */
function birkly_marketplace_install_theme(string $slug, string $version, string $licenseKey, string $offerType = 'theme'): array
{
    $release = birkly_marketplace_release_download($slug, $version);
    if (!$release['ok']) {
        return ['ok' => false, 'errors' => [$release['error'] ?? 'Release lookup failed']];
    }

    $download = birkly_marketplace_download_artifact(
        (string) ($release['download_url'] ?? ''),
        $release['checksum_sha256'] ?? null
    );
    if (!$download['ok'] || empty($download['path'])) {
        return ['ok' => false, 'errors' => [$download['error'] ?? 'Download failed']];
    }

    $zipPath = $download['path'];
    $themeId = birkly_marketplace_resolve_theme_id($zipPath, $slug, $offerType);
    $extract = birkly_marketplace_extract_theme_zip($zipPath, $themeId, false);
    @unlink($zipPath);

    if (!$extract['ok']) {
        return ['ok' => false, 'errors' => $extract['errors']];
    }

    birkly_marketplace_save_theme_source($themeId, $slug, $version, $offerType);

    return [
        'ok' => true,
        'theme_id' => $themeId,
        'version' => $version,
        'offer_type' => $offerType,
        'path' => $extract['path'] ?? '',
        'errors' => [],
    ];
}

/**
 * @return array<string, mixed>
 */
function birkly_marketplace_install_offer(
    string $slug,
    string $version,
    string $licenseKey,
    string $offerType = 'plugin',
    bool $upgrade = false
): array {
    if (in_array($offerType, ['theme', 'template_pack'], true)) {
        return birkly_marketplace_install_theme($slug, $version, $licenseKey, $offerType);
    }

    return birkly_marketplace_install_plugin($slug, $version, $licenseKey, $upgrade);
}
