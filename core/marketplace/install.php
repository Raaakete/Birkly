<?php
/**
 * Marketplace plugin install and upgrade from verified zip artifacts.
 */

require_once dirname(__DIR__) . '/marketplace/http.php';

function birkly_marketplace_ensure_plugins_core(): void
{
    if (!function_exists('validate_plugin')) {
        require_once dirname(__DIR__) . '/plugins.php';
    }
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

    $download = birkly_marketplace_http_download_to_file(
        $downloadUrl,
        $tmp,
        ['User-Agent: Birkly-CMS'],
        120
    );
    if (!$download['ok']) {
        @unlink($tmp);
        return [
            'ok' => false,
            'error' => birkly_marketplace_transport_error_message('Failed to download artifact', $download['error'] ?? null),
        ];
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
 * Map marketplace offer slug → installed plugin directory id.
 */
function birkly_marketplace_plugin_id_from_slug(string $marketplaceSlug): ?string
{
    foreach (birkly_official_marketplace_slugs() as $pluginId => $slug) {
        if ($slug === $marketplaceSlug) {
            return $pluginId;
        }
    }

    return null;
}

/**
 * Read plugin.json id/name from a zip artifact.
 */
function birkly_marketplace_plugin_id_from_zip(string $zipPath): ?string
{
    if (!class_exists('ZipArchive')) {
        return null;
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        return null;
    }

    $pluginId = null;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (!is_string($name) || str_ends_with($name, '/') || basename($name) !== 'plugin.json') {
            continue;
        }
        $content = $zip->getFromIndex($i);
        if (!is_string($content)) {
            continue;
        }
        $json = json_decode($content, true);
        if (!is_array($json)) {
            continue;
        }
        if (!empty($json['id']) && is_string($json['id'])) {
            $pluginId = preg_replace('/[^a-z0-9_-]/i', '', strtolower($json['id'])) ?: null;
            break;
        }
        if (!empty($json['name']) && is_string($json['name'])) {
            $pluginId = preg_replace('/[^a-z0-9_-]/i', '-', strtolower($json['name']));
            $pluginId = trim((string) $pluginId, '-') ?: null;
            break;
        }
    }
    $zip->close();

    return $pluginId !== null && $pluginId !== '' ? $pluginId : null;
}

/**
 * Resolve plugin directory name from zip contents and marketplace slug.
 */
function birkly_marketplace_resolve_plugin_id(string $zipPath, string $marketplaceSlug): string
{
    $officialId = birkly_marketplace_plugin_id_from_slug($marketplaceSlug);
    if ($officialId !== null && $officialId !== '') {
        return $officialId;
    }

    $fromZip = birkly_marketplace_plugin_id_from_zip($zipPath);
    if ($fromZip !== null && $fromZip !== '') {
        return $fromZip;
    }

    $slug = preg_replace('/[^a-z0-9_-]/i', '', $marketplaceSlug) ?? '';
    return $slug !== '' ? strtolower($slug) : 'plugin';
}

/**
 * Ensure plugins/ exists and is writable by the PHP process.
 *
 * @return array{ok:bool,error?:string}
 */
function birkly_marketplace_ensure_plugins_root_writable(string $pluginsRoot): array
{
    if (!is_dir($pluginsRoot)) {
        if (!mkdir($pluginsRoot, 0775, true) && !is_dir($pluginsRoot)) {
            return ['ok' => false, 'error' => 'Cannot create plugins directory'];
        }
    }

    if (!is_writable($pluginsRoot)) {
        @chmod($pluginsRoot, 0775);
    }

    if (!is_writable($pluginsRoot)) {
        return [
            'ok' => false,
            'error' => 'The plugins/ directory is not writable by the web server. Adjust filesystem permissions (e.g. chown/chmod for your PHP user) and retry.',
        ];
    }

    return ['ok' => true];
}

/**
 * Extract plugin zip into plugins/{plugin_id}/.
 *
 * @return array{ok:bool,plugin_id?:string,path?:string,errors:array}
 */
/**
 * True when a plugin directory exists but is not a loadable plugin (e.g. empty
 * Docker placeholder dirs without plugin.php). Safe to replace on fresh install.
 */
function birkly_marketplace_plugin_dir_is_leftover(string $dest): bool
{
    if (!is_dir($dest)) {
        return false;
    }

    return !is_file($dest . '/plugin.php');
}

function birkly_marketplace_extract_plugin_zip(string $zipPath, string $pluginId, bool $replaceExisting = false): array
{
    birkly_marketplace_ensure_plugins_core();
    $errors = [];
    $pluginId = preg_replace('/[^a-z0-9_-]/i', '', $pluginId) ?? '';
    if ($pluginId === '') {
        return ['ok' => false, 'errors' => ['Invalid plugin id']];
    }

    if (!class_exists('ZipArchive')) {
        return ['ok' => false, 'errors' => ['ZipArchive extension not available']];
    }

    $pluginsRoot = function_exists('birkly_marketplace_plugins_dir')
        ? birkly_marketplace_plugins_dir()
        : dirname(__DIR__, 2) . '/plugins';
    $dest = $pluginsRoot . '/' . $pluginId;
    $preserveBackup = null;

    if (is_dir($dest)) {
        $isLeftover = birkly_marketplace_plugin_dir_is_leftover($dest);
        if (!$replaceExisting && !$isLeftover) {
            return ['ok' => false, 'errors' => ["Plugin directory '{$pluginId}' already exists"]];
        }
        // On upgrade, keep runtime data (connections, orders, keys) that is not in the zip.
        // Docker installs often colocate code + data under BIRKLY_DATA/plugins/{id}.
        if ($replaceExisting && !$isLeftover) {
            $preserveBackup = $pluginsRoot . '/.' . $pluginId . '-upgrade-' . bin2hex(random_bytes(4));
            if (!@rename($dest, $preserveBackup)) {
                return ['ok' => false, 'errors' => ["Failed to backup plugin directory '{$pluginId}' before upgrade"]];
            }
        } else {
            birkly_marketplace_remove_directory($dest);
        }
    }

    $writable = birkly_marketplace_ensure_plugins_root_writable($pluginsRoot);
    if (!$writable['ok']) {
        if ($preserveBackup !== null && is_dir($preserveBackup) && !is_dir($dest)) {
            @rename($preserveBackup, $dest);
        }
        return ['ok' => false, 'errors' => [$writable['error'] ?? 'plugins directory not writable']];
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        if ($preserveBackup !== null && is_dir($preserveBackup) && !is_dir($dest)) {
            @rename($preserveBackup, $dest);
        }
        return ['ok' => false, 'errors' => ['Cannot open zip archive']];
    }

    $prefix = birkly_marketplace_zip_root_prefix($zip);
    mkdir($dest, 0775, true);

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (!is_string($name) || str_ends_with($name, '/')) {
            continue;
        }
        if (birkly_marketplace_should_skip_zip_entry($name)) {
            continue;
        }
        if (str_contains($name, '..')) {
            $zip->close();
            birkly_marketplace_remove_directory($dest);
            if ($preserveBackup !== null && is_dir($preserveBackup)) {
                @rename($preserveBackup, $dest);
            }
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
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            $errors[] = 'Failed to create directory: ' . $relative;
            continue;
        }

        $content = $zip->getFromIndex($i);
        if ($content === false) {
            $errors[] = 'Failed to read from zip: ' . $relative;
            continue;
        }
        if (file_put_contents($target, $content) === false) {
            $errors[] = 'Failed to write: ' . $relative . ' (check plugins/ permissions)';
        }
    }

    $zip->close();

    if ($errors !== []) {
        birkly_marketplace_remove_directory($dest);
        if ($preserveBackup !== null && is_dir($preserveBackup)) {
            @rename($preserveBackup, $dest);
        }
        return ['ok' => false, 'errors' => $errors];
    }

    $validationErrors = validate_plugin($dest);
    if ($validationErrors !== []) {
        birkly_marketplace_remove_directory($dest);
        if ($preserveBackup !== null && is_dir($preserveBackup)) {
            @rename($preserveBackup, $dest);
        }
        return ['ok' => false, 'errors' => $validationErrors];
    }

    if ($preserveBackup !== null && is_dir($preserveBackup)) {
        birkly_marketplace_restore_plugin_runtime_paths($preserveBackup, $dest);
        birkly_marketplace_remove_directory($preserveBackup);
    }

    return ['ok' => true, 'plugin_id' => $pluginId, 'path' => $dest, 'errors' => []];
}

/**
 * Copy paths from a pre-upgrade plugin tree that the new package did not ship.
 * Preserves connections, encryption keys, orders, and other runtime data.
 */
function birkly_marketplace_restore_plugin_runtime_paths(string $backupDir, string $destDir): void
{
    if (!is_dir($backupDir) || !is_dir($destDir)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($backupDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        if (!$item instanceof SplFileInfo) {
            continue;
        }
        $source = $item->getPathname();
        $relative = substr($source, strlen(rtrim($backupDir, '/')) + 1);
        if ($relative === false || $relative === '') {
            continue;
        }
        // Never restore path traversal or VCS noise.
        if (str_contains($relative, '..') || str_starts_with($relative, '.git')) {
            continue;
        }
        $target = $destDir . '/' . $relative;
        if (file_exists($target) || is_dir($target)) {
            continue;
        }
        if ($item->isDir()) {
            if (!is_dir($target)) {
                @mkdir($target, 0775, true);
            }
            continue;
        }
        $parent = dirname($target);
        if (!is_dir($parent) && !mkdir($parent, 0775, true) && !is_dir($parent)) {
            continue;
        }
        @copy($source, $target);
    }
}

function birkly_marketplace_should_skip_zip_entry(string $name): bool
{
    $normalized = str_replace('\\', '/', $name);
    if ($normalized === '' || $normalized === '.DS_Store') {
        return true;
    }
    if (str_starts_with($normalized, '__MACOSX/') || $normalized === '__MACOSX') {
        return true;
    }
    $basename = basename(rtrim($normalized, '/'));
    return str_starts_with($basename, '._');
}

function birkly_marketplace_zip_root_prefix(ZipArchive $zip): string
{
    $pluginJsonDirs = [];
    $topLevels = [];
    $hasRootFiles = false;

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (!is_string($name) || $name === '' || birkly_marketplace_should_skip_zip_entry($name)) {
            continue;
        }

        $trimmed = rtrim(str_replace('\\', '/', $name), '/');
        if ($trimmed === '') {
            continue;
        }

        if (!str_contains($trimmed, '/')) {
            $hasRootFiles = true;
            continue;
        }

        $parts = explode('/', $trimmed);
        $top = $parts[0] ?? '';
        if ($top !== '') {
            $topLevels[$top] = true;
        }

        if (basename($trimmed) === 'plugin.json') {
            array_pop($parts);
            $pluginJsonDirs[implode('/', $parts)] = true;
        }
    }

    if (count($pluginJsonDirs) === 1) {
        $dir = array_key_first($pluginJsonDirs);
        return is_string($dir) && $dir !== '' ? $dir . '/' : '';
    }

    if (count($topLevels) === 1 && !$hasRootFiles) {
        $only = array_key_first($topLevels);
        if (is_string($only) && $only !== '') {
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
    bool $upgrade = false,
    bool $consentAccepted = false,
    string $entitlementToken = '',
    array $entitlement = []
): array {
    birkly_marketplace_ensure_plugins_core();
    $consentCheck = birkly_marketplace_require_install_consent($slug, $version, $consentAccepted);
    if (!$consentCheck['ok']) {
        return [
            'ok' => false,
            'errors' => [$consentCheck['error'] ?? 'Permission consent required'],
            'code' => $consentCheck['code'] ?? 'consent_required',
        ];
    }

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

    birkly_marketplace_save_plugin_source($pluginId, $slug, $version, $licenseKey, $entitlementToken, $entitlement);

    if ($consentAccepted) {
        $permInfo = birkly_marketplace_install_permissions($slug, $version);
        if (!empty($permInfo['ok']) && is_array($permInfo['permissions_manifest'] ?? null)) {
            birkly_marketplace_record_install_consent(
                $slug,
                $version,
                $permInfo['permissions_manifest']
            );
        }
    }

    return [
        'ok' => true,
        'plugin_id' => $pluginId,
        'version' => $version,
        'errors' => [],
        'preflight' => $preview,
    ];
}

function birkly_marketplace_save_plugin_source(
    string $pluginId,
    string $slug,
    string $version,
    string $licenseKey,
    string $entitlementToken = '',
    array $entitlement = []
): void
{
    birkly_marketplace_ensure_plugins_core();
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
        if ($entitlementToken !== '') {
            $birkly_plugins[$pluginId]['entitlement_token'] = $entitlementToken;
        }
        if ($entitlement !== []) {
            $birkly_plugins[$pluginId]['entitlement'] = $entitlement;
            unset($birkly_plugins[$pluginId]['entitlement_grace_started_at']);
        }
        if (isset($birkly_plugins[$pluginId]['version'])) {
            $birkly_plugins[$pluginId]['version'] = $version;
        }
        save_plugin_states();

        $licenseRef = trim((string) ($birkly_plugins[$pluginId]['license_ref'] ?? ''));
        birkly_marketplace_attest_installation_offer($slug, $licenseRef !== '' ? $licenseRef : null);

        if ($licenseKey !== '' && function_exists('birkly_marketplace_remember_hub_license')) {
            birkly_marketplace_remember_hub_license(
                $slug,
                $licenseKey,
                $entitlementToken,
                $entitlement,
                (string) ($birkly_plugins[$pluginId]['name'] ?? $slug),
                $version,
                $pluginId
            );
        }
    }
}

/**
 * @return array<string, array<string, mixed>>
 */
function birkly_marketplace_installed_plugin_updates(): array
{
    birkly_marketplace_ensure_plugins_core();
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
        $entitlementStatus = null;
        if (!empty($plugin['entitlement']) || !empty($plugin['entitlement_token'])) {
            if (!function_exists('birkly_plugin_entitlement_status')) {
                require_once dirname(__DIR__) . '/entitlement_helper.php';
            }
            $entitlementStatus = birkly_plugin_entitlement_status((string) $id);
            if (empty($entitlementStatus['ok'])) {
                $check['license_lapsed'] = true;
                $check['license_warning'] = $entitlementStatus['message'] ?? 'Marketplace entitlement is inactive.';
                $check['update_available'] = false;
            }
        }
        $updates[$id] = [
            'plugin_id' => $id,
            'marketplace_slug' => $slug,
            'current_version' => $current,
            'latest_version' => $check['latest_version'] ?? $current,
            'update_available' => !empty($check['update_available']),
            'license_lapsed' => !empty($check['license_lapsed']),
            'license_warning' => $check['license_warning'] ?? null,
            'entitlement_status' => $entitlementStatus,
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
        if (birkly_marketplace_should_skip_zip_entry($name)) {
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
 * Persist Hub/site-pack install metadata (P53-M2 / M3–M8).
 * Meta may include file_hashes (P53-M7), skipped_files, cached_zip (P53-M8).
 *
 * @param list<string> $installedCollections
 * @param array<string,mixed> $meta
 */
function birkly_marketplace_save_template_pack_source(
    string $slug,
    string $version,
    array $installedCollections,
    bool $projectSite,
    string $projectRoot,
    array $meta = []
): void {
    require_once dirname(__DIR__, 2) . '/api/storage.php';
    $storage = new Storage(birkly_marketplace_settings_dir());
    $data = $storage->read('installed_template_packs.json') ?? [];
    if (!is_array($data)) {
        $data = [];
    }

    $previous = isset($data[$slug]) && is_array($data[$slug]) ? $data[$slug] : [];
    $installedAt = (string) ($previous['installed_at'] ?? gmdate('Y-m-d\TH:i:s\Z'));

    $row = array_merge($previous, [
        'marketplace_slug' => $slug,
        'offer_type' => 'template_pack',
        'version' => $version,
        'source' => (string) ($meta['source'] ?? $previous['source'] ?? 'marketplace'),
        'installed_collections' => array_values($installedCollections),
        'project_site' => $projectSite,
        'project_root' => rtrim($projectRoot, '/\\') . '/' . (function_exists('birkly_public_site_dir') ? birkly_public_site_dir() : 'project'),
        'installed_at' => $installedAt,
        'updated_at' => gmdate('Y-m-d\TH:i:s\Z'),
    ], $meta);
    $data[$slug] = $row;
    $storage->write('installed_template_packs.json', $data);
}

/**
 * Install a site pack (offer_type template_pack) into collections + project/.
 * Uses the same shared engine as manual ZIP upload — never themes/ (P53-D3).
 *
 * @param array<string,mixed> $options overwrite_mode, include_demo_entries, confirm_required_plugins, …
 * @return array<string, mixed>
 */
function birkly_marketplace_install_template_pack(
    string $slug,
    string $version,
    string $licenseKey = '',
    array $options = []
): array {
    if (!function_exists('birkly_marketplace_install_template_pack_from_zip')) {
        require_once __DIR__ . '/template_pack.php';
    }

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
    $options = array_merge([
        'source' => 'marketplace',
        'slug' => $slug,
        'version' => $version,
        // Hub / existing sites: demo entries OFF by default (P53-D5 / P53-M6)
        'include_demo_entries' => false,
        'overwrite_mode' => 'merge',
    ], $options);
    $options['source'] = (string) ($options['source'] ?? 'marketplace');
    $options['slug'] = $slug;
    $options['version'] = $version;

    $result = birkly_marketplace_install_template_pack_from_zip($zipPath, $options);
    @unlink($zipPath);

    return $result;
}

/**
 * @param array<string,mixed> $options
 * @return array<string, mixed>
 */
function birkly_marketplace_install_offer(
    string $slug,
    string $version,
    string $licenseKey,
    string $offerType = 'plugin',
    bool $upgrade = false,
    bool $consentAccepted = false,
    array $options = []
): array {
    if ($offerType === 'template_pack') {
        $options['consent_accepted'] = $consentAccepted || !empty($options['consent_accepted']);
        return birkly_marketplace_install_template_pack($slug, $version, $licenseKey, $options);
    }

    if ($offerType === 'theme') {
        return birkly_marketplace_install_theme($slug, $version, $licenseKey, $offerType);
    }

    return birkly_marketplace_install_plugin(
        $slug,
        $version,
        $licenseKey,
        $upgrade,
        $consentAccepted,
        (string) ($options['entitlement_token'] ?? ''),
        is_array($options['entitlement'] ?? null) ? $options['entitlement'] : []
    );
}
