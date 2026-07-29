<?php
/**
 * Marketplace install permission consent (P35-M17).
 */

declare(strict_types=1);

/**
 * @return array<string, array<string, array<string, mixed>>>
 */
function birkly_marketplace_load_consent(): array
{
    if (isset($GLOBALS['__birkly_marketplace_consent'])) {
        return $GLOBALS['__birkly_marketplace_consent'];
    }

    require_once dirname(__DIR__, 2) . '/api/storage.php';
    $storage = new Storage(birkly_marketplace_settings_dir());
    $data = $storage->read('marketplace_consent.json');
    if (!is_array($data)) {
        $data = [];
    }

    $GLOBALS['__birkly_marketplace_consent'] = $data;
    return $data;
}

/**
 * @param array<string, array<string, array<string, mixed>>> $data
 */
function birkly_marketplace_save_consent(array $data): void
{
    require_once dirname(__DIR__, 2) . '/api/storage.php';
    $storage = new Storage(birkly_marketplace_settings_dir());
    $storage->write('marketplace_consent.json', $data);
    $GLOBALS['__birkly_marketplace_consent'] = $data;
}

function birkly_marketplace_consent_reset(): void
{
    unset($GLOBALS['__birkly_marketplace_consent']);
}

/**
 * Local-only lookup for a marketplace offer installed on this CMS (no registry calls).
 *
 * @return array{plugin_id:string,version:string}|null
 */
function birkly_marketplace_local_installed_offer(string $slug): ?array
{
    $slug = trim($slug);
    if ($slug === '') {
        return null;
    }

    if (!function_exists('get_all_plugins')) {
        require_once dirname(__DIR__) . '/plugins.php';
    }

    foreach (get_all_plugins() as $pluginId => $plugin) {
        if (!is_array($plugin)) {
            continue;
        }
        $pluginSlug = birkly_marketplace_plugin_offer_slug((string) $pluginId, $plugin);
        if ($pluginSlug !== $slug) {
            continue;
        }

        return [
            'plugin_id' => (string) $pluginId,
            'version' => trim((string) ($plugin['version'] ?? $plugin['marketplace_version'] ?? '')),
        ];
    }

    return null;
}

function birkly_marketplace_is_offer_version_installed(string $slug, string $version): bool
{
    $slug = trim($slug);
    $version = trim($version);
    if ($slug === '' || $version === '') {
        return false;
    }

    $installed = birkly_marketplace_local_installed_offer($slug);
    if ($installed === null) {
        return false;
    }

    return $installed['version'] === $version;
}

/**
 * Whether the normalized manifest declares capabilities that require user consent.
 *
 * @param array{permissions?:array,hooks?:array,data_access?:array} $manifest
 */
function birkly_marketplace_manifest_requires_consent(array $manifest): bool
{
    $normalized = birkly_marketplace_normalize_permissions_manifest($manifest);
    if ($normalized['permissions'] !== [] || $normalized['hooks'] !== []) {
        return true;
    }

    foreach ($normalized['data_access'] as $level) {
        if ($level !== 'none') {
            return true;
        }
    }

    return false;
}

function birkly_marketplace_has_install_consent(string $slug, string $version): bool
{
    $slug = trim($slug);
    $version = trim($version);
    if ($slug === '' || $version === '') {
        return false;
    }

    if (!birkly_marketplace_is_offer_version_installed($slug, $version)) {
        return false;
    }

    $consent = birkly_marketplace_load_consent();
    $entry = $consent[$slug][$version] ?? null;

    return is_array($entry) && !empty($entry['accepted_at']);
}

/**
 * @param array{permissions?:array,hooks?:array,data_access?:array} $manifest
 */
function birkly_marketplace_record_install_consent(
    string $slug,
    string $version,
    array $manifest,
    ?string $acceptedBy = null
): void {
    $slug = trim($slug);
    $version = trim($version);
    if ($slug === '' || $version === '') {
        return;
    }

    $consent = birkly_marketplace_load_consent();
    if (!isset($consent[$slug]) || !is_array($consent[$slug])) {
        $consent[$slug] = [];
    }

    $consent[$slug][$version] = [
        'accepted_at' => gmdate('c'),
        'accepted_by' => $acceptedBy ?? ($_SESSION['user']['email'] ?? 'unknown'),
        'permissions_manifest' => birkly_marketplace_normalize_permissions_manifest($manifest),
    ];

    birkly_marketplace_save_consent($consent);
}

/**
 * @return array{permissions:array,hooks:array,data_access:array}
 */
function birkly_marketplace_normalize_permissions_manifest(array $manifest): array
{
    $permissions = [];
    if (is_array($manifest['permissions'] ?? null)) {
        foreach ($manifest['permissions'] as $perm) {
            if (is_string($perm) && $perm !== '') {
                $permissions[] = $perm;
            }
        }
    }
    sort($permissions);

    $hooks = [];
    if (is_array($manifest['hooks'] ?? null)) {
        foreach ($manifest['hooks'] as $hook) {
            if (is_string($hook) && $hook !== '') {
                $hooks[] = $hook;
            }
        }
    }
    sort($hooks);

    $domains = ['entries', 'media', 'users', 'settings'];
    $levels = ['none', 'read', 'read_write'];
    $dataAccess = [];
    $raw = is_array($manifest['data_access'] ?? null) ? $manifest['data_access'] : [];
    foreach ($domains as $domain) {
        $level = strtolower(trim((string) ($raw[$domain] ?? 'none')));
        $dataAccess[$domain] = in_array($level, $levels, true) ? $level : 'none';
    }

    return [
        'permissions' => array_values(array_unique($permissions)),
        'hooks' => array_values(array_unique($hooks)),
        'data_access' => $dataAccess,
    ];
}

/**
 * Resolve permission manifest for an offer version from marketplace API or zip preview.
 *
 * @return array{ok:bool,version?:string,permissions_manifest?:array,consent_required?:bool,consent_record?:mixed,error?:string}
 */
function birkly_marketplace_install_permissions(string $slug, string $version = ''): array
{
    $slug = trim($slug);
    if ($slug === '') {
        return ['ok' => false, 'error' => 'Offer slug required'];
    }

    $detail = birkly_marketplace_offer_detail($slug);
    if (!$detail['ok']) {
        return ['ok' => false, 'error' => $detail['error'] ?? 'Offer not found'];
    }

    $releases = is_array($detail['releases'] ?? null) ? $detail['releases'] : [];
    $targetVersion = trim($version);
    if ($targetVersion === '') {
        $latest = '0.0.0';
        foreach ($releases as $release) {
            if (!is_array($release)) {
                continue;
            }
            $ver = (string) ($release['version'] ?? '');
            if ($ver !== '' && version_compare($ver, $latest, '>')) {
                $latest = $ver;
            }
        }
        if ($latest === '0.0.0') {
            return ['ok' => false, 'error' => 'No releases available for this offer'];
        }
        $targetVersion = $latest;
    }

    $manifest = null;
    foreach ($releases as $release) {
        if (!is_array($release) || (string) ($release['version'] ?? '') !== $targetVersion) {
            continue;
        }
        if (is_array($release['permissions_manifest'] ?? null)) {
            $manifest = $release['permissions_manifest'];
            break;
        }
        $pluginJson = is_array($release['plugin_json'] ?? null) ? $release['plugin_json'] : null;
        if ($pluginJson !== null) {
            $manifest = birkly_marketplace_permissions_manifest_from_plugin_json($pluginJson);
            break;
        }
    }

    if ($manifest === null && empty($detail['fallback'])) {
        $releaseInfo = birkly_marketplace_release_download($slug, $targetVersion);
        if ($releaseInfo['ok'] && !empty($releaseInfo['download_url'])) {
            $download = birkly_marketplace_download_artifact(
                (string) $releaseInfo['download_url'],
                $releaseInfo['checksum_sha256'] ?? null
            );
            if ($download['ok'] && !empty($download['path'])) {
                $preview = birkly_marketplace_preview_zip_capabilities($download['path']);
                @unlink($download['path']);
                if ($preview['ok'] && is_array($preview['plugin_json'] ?? null)) {
                    $manifest = birkly_marketplace_permissions_manifest_from_plugin_json($preview['plugin_json']);
                }
            }
        }
    }

    if ($manifest === null) {
        $manifest = [
            'permissions' => [],
            'hooks' => [],
            'data_access' => [
                'entries' => 'none',
                'media' => 'none',
                'users' => 'none',
                'settings' => 'none',
            ],
        ];
    }

    $permissionsManifest = birkly_marketplace_normalize_permissions_manifest($manifest);

    return [
        'ok' => true,
        'version' => $targetVersion,
        'permissions_manifest' => $permissionsManifest,
        'consent_required' => birkly_marketplace_manifest_requires_consent($permissionsManifest)
            && !birkly_marketplace_has_install_consent($slug, $targetVersion),
        'consent_record' => birkly_marketplace_load_consent()[$slug][$targetVersion] ?? null,
    ];
}

/**
 * @return array{permissions:array,hooks:array,data_access:array}
 */
function birkly_marketplace_permissions_manifest_from_plugin_json(array $pluginJson): array
{
    return birkly_marketplace_normalize_permissions_manifest([
        'permissions' => $pluginJson['permissions'] ?? [],
        'hooks' => $pluginJson['hooks'] ?? [],
        'data_access' => $pluginJson['data_access'] ?? [],
    ]);
}

/**
 * @param array{permissions?:array,hooks?:array,data_access?:array}|null $permissionsManifest
 * @return array{ok:bool,error?:string,code?:string}
 */
function birkly_marketplace_require_install_consent(
    string $slug,
    string $version,
    bool $consentAccepted = false,
    ?array $permissionsManifest = null
): array {
    if ($consentAccepted) {
        return ['ok' => true];
    }

    if (birkly_marketplace_has_install_consent($slug, $version)) {
        return ['ok' => true];
    }

    if ($permissionsManifest === null) {
        $permInfo = birkly_marketplace_install_permissions($slug, $version);
        if (empty($permInfo['ok'])) {
            return [
                'ok' => false,
                'error' => $permInfo['error'] ?? 'Failed to load permissions',
                'code' => 'permissions_failed',
            ];
        }
        $permissionsManifest = is_array($permInfo['permissions_manifest'] ?? null)
            ? $permInfo['permissions_manifest']
            : [];
        $version = (string) ($permInfo['version'] ?? $version);
    }

    if (!birkly_marketplace_manifest_requires_consent($permissionsManifest)) {
        return ['ok' => true];
    }

    return [
        'ok' => false,
        'error' => 'Permission consent is required before installing this plugin',
        'code' => 'consent_required',
    ];
}
