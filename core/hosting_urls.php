<?php
/**
 * Configurable hosting URLs — CMS vs public site (P43, P66 schema v2).
 */

declare(strict_types=1);

require_once __DIR__ . '/birkly_paths.php';
require_once __DIR__ . '/site_domain.php';

const BIRKLY_HOSTING_SCHEMA_VERSION = 2;

/**
 * @return array<string, mixed>
 */
function birkly_hosting_defaults(): array
{
    return [
        'schema_version' => BIRKLY_HOSTING_SCHEMA_VERSION,
        'cms_base_url' => '',
        'cms_host_aliases' => [],
        'public_site_mode' => 'none',
        'public_site_url' => '',
        'public_host_aliases' => [],
        'alias_redirect_to_primary' => true,
        'same_host_landing' => 'admin',
        'strict_admin_host' => true,
    ];
}

/**
 * @param mixed $aliases
 * @return list<string>
 */
function birkly_hosting_normalize_alias_hosts($aliases, string $primaryHost = ''): array
{
    if (!is_array($aliases)) {
        $aliases = [];
    }

    $primaryHost = birkly_normalize_host($primaryHost);
    $normalized = [];
    foreach ($aliases as $alias) {
        $host = birkly_normalize_host((string) $alias);
        if ($host === '' || $host === $primaryHost) {
            continue;
        }
        $normalized[$host] = $host;
    }

    return array_values($normalized);
}

/**
 * @param array<string, mixed> $config
 * @return list<string>
 */
function birkly_hosting_cms_alias_hosts(array $config): array
{
    return birkly_hosting_normalize_alias_hosts(
        $config['cms_host_aliases'] ?? [],
        birkly_hosting_url_host((string) ($config['cms_base_url'] ?? ''))
    );
}

/**
 * @param array<string, mixed> $config
 * @return list<string>
 */
function birkly_hosting_public_alias_hosts(array $config): array
{
    if (($config['public_site_mode'] ?? 'none') === 'none') {
        return [];
    }

    return birkly_hosting_normalize_alias_hosts(
        $config['public_host_aliases'] ?? [],
        birkly_hosting_url_host((string) ($config['public_site_url'] ?? ''))
    );
}

function birkly_hosting_host_matches_cms(string $host): bool
{
    $host = birkly_normalize_host($host);
    if ($host === '') {
        return false;
    }

    $config = birkly_hosting_load();
    $primary = birkly_hosting_url_host((string) ($config['cms_base_url'] ?? ''));
    if ($primary !== '' && $host === $primary) {
        return true;
    }

    return in_array($host, birkly_hosting_cms_alias_hosts($config), true);
}

function birkly_hosting_host_matches_public(string $host): bool
{
    $host = birkly_normalize_host($host);
    if ($host === '') {
        return false;
    }

    $config = birkly_hosting_load();
    if (($config['public_site_mode'] ?? 'none') === 'none') {
        return false;
    }

    $primary = birkly_hosting_url_host((string) ($config['public_site_url'] ?? ''));
    if ($primary !== '' && $host === $primary) {
        return true;
    }

    return in_array($host, birkly_hosting_public_alias_hosts($config), true);
}

function birkly_hosting_is_cms_alias_host(string $host): bool
{
    $host = birkly_normalize_host($host);
    $primary = birkly_hosting_cms_host();

    return $host !== '' && $primary !== '' && $host !== $primary && birkly_hosting_host_matches_cms($host);
}

function birkly_hosting_is_public_alias_host(string $host): bool
{
    $host = birkly_normalize_host($host);
    $primary = birkly_hosting_public_host();

    return $host !== '' && $primary !== '' && $host !== $primary && birkly_hosting_host_matches_public($host);
}

/**
 * Hostnames listed as both CMS and public aliases (ambiguous routing).
 *
 * @return list<string>
 */
function birkly_hosting_overlapping_delivery_aliases(?array $config = null): array
{
    $config = $config ?? birkly_hosting_load();
    if (birkly_hosting_normalize_mode((string) ($config['public_site_mode'] ?? 'none')) === 'none') {
        return [];
    }

    $cmsAliases = birkly_hosting_cms_alias_hosts($config);
    $publicAliases = birkly_hosting_public_alias_hosts($config);
    $overlap = array_values(array_intersect($cmsAliases, $publicAliases));
    sort($overlap);

    return $overlap;
}

/**
 * Remove alias overlap — CMS role wins at request time, so drop duplicates from public aliases.
 *
 * @param array<string, mixed> $config
 * @return array{0: array<string, mixed>, 1: bool, 2: list<string>}
 */
function birkly_hosting_deduplicate_overlapping_aliases(array $config): array
{
    $overlap = birkly_hosting_overlapping_delivery_aliases($config);
    if ($overlap === []) {
        return [$config, false, []];
    }

    $publicPrimary = birkly_hosting_url_host((string) ($config['public_site_url'] ?? ''));
    $publicAliases = birkly_hosting_public_alias_hosts($config);
    $publicAliases = array_values(array_filter(
        $publicAliases,
        static fn (string $host): bool => !in_array($host, $overlap, true)
    ));
    $config['public_host_aliases'] = birkly_hosting_normalize_alias_hosts($publicAliases, $publicPrimary);

    return [$config, true, $overlap];
}

/**
 * Human-readable validation warnings for the current or draft hosting config.
 *
 * @param array<string, mixed> $config
 * @return list<array{code: string, message: string, severity: string}>
 */
function birkly_hosting_config_warnings(array $config): array
{
    $config = birkly_hosting_normalize_config($config);
    $warnings = [];
    $mode = birkly_hosting_normalize_mode((string) ($config['public_site_mode'] ?? 'none'));

    if ($mode !== 'none' && birkly_hosting_same_host()) {
        $warnings[] = [
            'code' => 'SAME_HOST_INFO',
            'severity' => 'info',
            'message' => 'CMS and public use the same domain ('
                . birkly_hosting_public_host()
                . '). With “Root / opens admin”, visitors reach the site at /project/.',
        ];
    }

    foreach (birkly_hosting_overlapping_delivery_aliases($config) as $host) {
        $warnings[] = [
            'code' => 'ALIAS_ON_BOTH_ROLES',
            'severity' => 'warn',
            'message' => '"' . $host . '" is listed as both a CMS and public alias. At runtime CMS wins — keep it on CMS aliases only.',
        ];
    }

    if (birkly_hosting_cms_url_has_admin_path((string) ($config['cms_base_url'] ?? ''))
        || !empty($config['_cms_url_admin_path_fixup_pending'])) {
        $warnings[] = [
            'code' => 'CMS_URL_ADMIN_PATH',
            'severity' => 'error',
            'message' => 'CMS URL must be the site origin only (https://example.com), not …/admin.',
        ];
    }

    if (birkly_hosting_public_url_has_project_path((string) ($config['public_site_url'] ?? ''))
        || !empty($config['_public_url_path_fixup_pending'])) {
        $warnings[] = [
            'code' => 'PUBLIC_URL_PROJECT_PATH',
            'severity' => 'error',
            'message' => 'Public URL must not include /project — use the hostname only.',
        ];
    }

    return $warnings;
}

/**
 * @return list<string>
 */
function birkly_hosting_www_apex_pair(string $host): array
{
    $host = birkly_normalize_host($host);
    if ($host === '') {
        return [];
    }

    if (str_starts_with($host, 'www.')) {
        $apex = substr($host, 4);
        return $apex !== '' ? [$host, $apex] : [$host];
    }

    return [$host, 'www.' . $host];
}

/**
 * @param array<string, mixed> $config
 * @return array<string, mixed>
 */
function birkly_hosting_normalize_config(array $config): array
{
    $merged = array_merge(birkly_hosting_defaults(), $config);
    $merged['schema_version'] = max(1, (int) ($merged['schema_version'] ?? 1));
    $merged['cms_base_url'] = birkly_hosting_strip_admin_path_from_cms_url(
        birkly_hosting_normalize_url((string) $merged['cms_base_url'], false)
    );
    $merged['public_site_url'] = birkly_hosting_normalize_url((string) $merged['public_site_url'], true);
    $merged['public_site_mode'] = birkly_hosting_normalize_mode((string) ($merged['public_site_mode'] ?? 'none'));
    if ($merged['public_site_mode'] === 'project') {
        $merged['public_site_url'] = birkly_hosting_strip_project_path_from_public_url((string) $merged['public_site_url']);
    }
    $merged['same_host_landing'] = in_array($merged['same_host_landing'], ['admin', 'public'], true)
        ? $merged['same_host_landing']
        : 'admin';
    $merged['strict_admin_host'] = (bool) $merged['strict_admin_host'];
    $merged['alias_redirect_to_primary'] = array_key_exists('alias_redirect_to_primary', $merged)
        ? (bool) $merged['alias_redirect_to_primary']
        : true;
    $merged['cms_host_aliases'] = birkly_hosting_normalize_alias_hosts(
        $merged['cms_host_aliases'] ?? [],
        birkly_hosting_url_host((string) $merged['cms_base_url'])
    );
    $merged['public_host_aliases'] = birkly_hosting_normalize_alias_hosts(
        $merged['public_host_aliases'] ?? [],
        birkly_hosting_url_host((string) $merged['public_site_url'])
    );

    return $merged;
}

/**
 * @param array<string, mixed> $config
 * @return array{0: array<string, mixed>, 1: bool}
 */
function birkly_hosting_migrate_schema_v2(array $config, bool $includeDomainSync = false): array
{
    $changed = false;
    $config = birkly_hosting_normalize_config($config);

    if ((int) ($config['schema_version'] ?? 1) >= BIRKLY_HOSTING_SCHEMA_VERSION) {
        return [$config, false];
    }

    $config['schema_version'] = BIRKLY_HOSTING_SCHEMA_VERSION;
    $config['cms_host_aliases'] = $config['cms_host_aliases'] ?? [];
    $config['public_host_aliases'] = $config['public_host_aliases'] ?? [];
    $config['alias_redirect_to_primary'] = (bool) ($config['alias_redirect_to_primary'] ?? true);
    $changed = true;

    if ($includeDomainSync) {
        $cmsPrimary = birkly_hosting_url_host((string) ($config['cms_base_url'] ?? ''));
        $publicPrimary = birkly_hosting_url_host((string) ($config['public_site_url'] ?? ''));
        $cmsAliases = $config['cms_host_aliases'];
        $publicAliases = $config['public_host_aliases'];

        foreach (birkly_hosting_collect_registered_domain_hosts() as $registeredHost) {
            if ($registeredHost === $cmsPrimary || $registeredHost === $publicPrimary) {
                continue;
            }
            if ($cmsPrimary !== '' && str_ends_with($registeredHost, $cmsPrimary)) {
                $cmsAliases[] = $registeredHost;
                $changed = true;
                continue;
            }
            if ($publicPrimary !== '' && ($registeredHost === $publicPrimary || str_ends_with($registeredHost, '.' . $publicPrimary))) {
                $publicAliases[] = $registeredHost;
                $changed = true;
                continue;
            }
            // Default: treat extra registered hosts as public aliases when a public site exists.
            if ($publicPrimary !== '' && $registeredHost !== $cmsPrimary) {
                $publicAliases[] = $registeredHost;
                $changed = true;
            } elseif ($cmsPrimary !== '') {
                $cmsAliases[] = $registeredHost;
                $changed = true;
            }
        }

        foreach (birkly_hosting_www_apex_pair($publicPrimary) as $pairHost) {
            if ($pairHost !== $publicPrimary) {
                $publicAliases[] = $pairHost;
                $changed = true;
            }
        }
        foreach (birkly_hosting_www_apex_pair($cmsPrimary) as $pairHost) {
            if ($pairHost !== $cmsPrimary) {
                $cmsAliases[] = $pairHost;
                $changed = true;
            }
        }

        $config['cms_host_aliases'] = birkly_hosting_normalize_alias_hosts($cmsAliases, $cmsPrimary);
        $config['public_host_aliases'] = birkly_hosting_normalize_alias_hosts($publicAliases, $publicPrimary);
    }

    return [$config, $changed];
}

/**
 * Ensure apex/www counterparts exist for CMS and public alias lists.
 *
 * @param array<string, mixed> $config
 * @return array{0: array<string, mixed>, 1: bool}
 */
function birkly_hosting_expand_delivery_www_pairs(array $config): array
{
    $config = birkly_hosting_normalize_config($config);
    $cmsPrimary = birkly_hosting_url_host((string) ($config['cms_base_url'] ?? ''));
    $publicPrimary = birkly_hosting_url_host((string) ($config['public_site_url'] ?? ''));
    $cmsAliases = birkly_hosting_cms_alias_hosts($config);
    $publicAliases = birkly_hosting_public_alias_hosts($config);
    $changed = false;

    $expand = static function (array $aliases, string $primary) use (&$changed): array {
        $seeds = $aliases;
        foreach ($aliases as $alias) {
            foreach (birkly_hosting_www_apex_pair($alias) as $pairHost) {
                $seeds[] = $pairHost;
            }
        }
        $seeds = array_values(array_unique($seeds));
        $expanded = [];
        foreach ($seeds as $seed) {
            if ($seed === '' || $seed === $primary) {
                continue;
            }
            $expanded[] = $seed;
            foreach (birkly_hosting_www_apex_pair($seed) as $pairHost) {
                if ($pairHost !== '' && $pairHost !== $primary) {
                    $expanded[] = $pairHost;
                }
            }
        }
        $expanded = birkly_hosting_normalize_alias_hosts($expanded, $primary);
        if ($expanded != $aliases) {
            $changed = true;
        }

        return $expanded;
    };

    $config['cms_host_aliases'] = $expand($cmsAliases, $cmsPrimary);
    $config['public_host_aliases'] = $expand($publicAliases, $publicPrimary);

    return [$config, $changed];
}

/**
 * @return list<string>
 */
function birkly_hosting_collect_registered_domain_hosts(): array
{
    $file = birkly_project_root() . '/settings/domains.json';
    if (!is_readable($file)) {
        return [];
    }

    $decoded = json_decode((string) file_get_contents($file), true);
    if (!is_array($decoded)) {
        return [];
    }

    $hosts = [];
    foreach ($decoded as $domain => $license) {
        $domain = birkly_normalize_host((string) $domain);
        if ($domain !== '') {
            $hosts[$domain] = $domain;
        }
        if (!is_array($license)) {
            continue;
        }
        foreach ($license['allowed_domains'] ?? [] as $allowed) {
            $allowed = birkly_normalize_host((string) $allowed);
            if ($allowed !== '') {
                $hosts[$allowed] = $allowed;
            }
        }
    }

    return array_values($hosts);
}

function birkly_hosting_backup_config(): bool
{
    $path = birkly_hosting_settings_path();
    if (!is_readable($path)) {
        return true;
    }

    $backup = $path . '.bak.' . gmdate('YmdHis');
    return copy($path, $backup);
}

/**
 * Optional guidance when Repair delivery finds nothing to persist.
 */
function birkly_hosting_repair_noop_hint(): string
{
    require_once __DIR__ . '/hosting_probe.php';
    $delivery = birkly_delivery_probe(['deep' => false]);
    foreach ($delivery['checks'] ?? [] as $check) {
        $code = (string) ($check['code'] ?? '');
        if ($code === 'CMS_URL_ADMIN_PATH') {
            return 'Delivery config is already up to date in memory, but hosting.json still stores /admin in the CMS URL — save failed or pending deploy. Re-run Repair after updating the CMS files.';
        }
        if ($code === 'SAME_HOST_PROJECT_PREFIX') {
            return 'Hosting settings are valid. The yellow notice is optional: click Fix on that row to serve the public site at / instead of /project/.';
        }
        if ($code === 'ALIAS_ON_BOTH_ROLES') {
            return 'Hosting settings are otherwise valid. Run Repair delivery to remove aliases listed on both CMS and public roles (CMS wins at runtime).';
        }
    }

    return '';
}

/**
 * @return array{status: string, message: string, changed: bool, config?: array<string, mixed>}
 */
function birkly_hosting_set_same_host_landing(string $landing): array
{
    if (birkly_hosting_env_overrides() !== []) {
        return ['status' => 'error', 'message' => 'Cannot change same-host landing while environment overrides are active'];
    }

    $landing = in_array($landing, ['admin', 'public'], true) ? $landing : 'admin';
    $config = birkly_hosting_normalize_config(birkly_hosting_load());
    if (($config['same_host_landing'] ?? 'admin') === $landing) {
        return [
            'status' => 'ok',
            'message' => $landing === 'public'
                ? 'Root / already opens the public site.'
                : 'Root / already opens admin.',
            'changed' => false,
            'config' => $config,
        ];
    }

    if (!birkly_hosting_same_host()) {
        return ['status' => 'error', 'message' => 'Same-host landing applies only when CMS and public share one domain.'];
    }

    $config['same_host_landing'] = $landing;
    birkly_hosting_backup_config();
    if (!birkly_hosting_persist($config, true)) {
        return ['status' => 'error', 'message' => 'Failed to save same-host landing setting'];
    }

    require_once __DIR__ . '/project_site.php';
    $pathRepair = ['changed' => false, 'files' => []];
    if ($landing === 'public' && ($config['public_site_mode'] ?? 'none') === 'project') {
        $pathRepair = birkly_project_repair_legacy_paths(true);
    }

    $message = $landing === 'public'
        ? 'Root / now opens the public site' . ($pathRepair['changed'] ? ' and project HTML paths were updated' : '')
        : 'Root / now opens admin';

    return [
        'status' => 'ok',
        'message' => $message . '.',
        'changed' => true,
        'config' => birkly_hosting_load(),
        'path_repair' => $pathRepair,
    ];
}

/**
 * @return array{status: string, message: string, changed: bool, config?: array<string, mixed>}
 */
function birkly_hosting_repair_delivery(bool $persist = true, bool $syncDomains = true): array
{
    $config = birkly_hosting_load();
    unset($config['_migration_pending']);
    [$migrated, $schemaChanged] = birkly_hosting_migrate_schema_v2($config, $syncDomains);
    $config = $migrated;
    $changed = $schemaChanged;

    [$config, $pairChanged] = birkly_hosting_expand_delivery_www_pairs($config);
    if ($pairChanged) {
        $changed = true;
    }

    [$config, $aliasDeduped, $removedAliases] = birkly_hosting_deduplicate_overlapping_aliases($config);
    if ($aliasDeduped) {
        $changed = true;
    }

    $publicUrlFixed = false;
    if (!empty($config['_public_url_path_fixup_pending'])) {
        unset($config['_public_url_path_fixup_pending']);
        $config = birkly_hosting_normalize_config($config);
        $publicUrlFixed = true;
        $changed = true;
    }

    $cmsUrlFixed = false;
    if (!empty($config['_cms_url_admin_path_fixup_pending'])) {
        unset($config['_cms_url_admin_path_fixup_pending']);
        $config = birkly_hosting_normalize_config($config);
        $cmsUrlFixed = true;
        $changed = true;
    }

    require_once __DIR__ . '/project_site.php';
    $demoRepair = birkly_project_repair_legacy_demo_website($persist);
    if ($demoRepair['changed']) {
        $changed = true;
    }

    $pathRepair = birkly_project_repair_legacy_paths($persist);
    if ($pathRepair['changed']) {
        $changed = true;
    }

    $ssrInvalidated = false;
    if ($persist) {
        require_once __DIR__ . '/project_ssr.php';
        birkly_project_ssr_invalidate_all();
        $ssrInvalidated = true;
    }

    if (!$changed) {
        $hint = birkly_hosting_repair_noop_hint();
        if ($ssrInvalidated) {
            return [
                'status' => 'ok',
                'message' => ($hint !== '' ? $hint . ' ' : '')
                    . 'SSR cache cleared so project/ pages serve the current files on disk.',
                'changed' => true,
                'config' => $config,
                'path_repair' => $pathRepair,
                'demo_repair' => $demoRepair,
                'ssr_invalidated' => true,
            ];
        }
        return [
            'status' => 'ok',
            'message' => $hint !== '' ? $hint : 'Delivery config is already up to date.',
            'changed' => false,
            'config' => $config,
            'path_repair' => $pathRepair,
            'demo_repair' => $demoRepair,
        ];
    }

    $messages = [];
    if ($schemaChanged) {
        $messages[] = 'Delivery config upgraded';
    }
    if ($cmsUrlFixed) {
        $messages[] = 'Removed /admin from the saved CMS URL (use the site origin only)';
    }
    if ($publicUrlFixed) {
        $messages[] = 'Removed /project from the saved public site URL';
    }
    if ($pairChanged) {
        $messages[] = 'Synced www/apex domain pairs';
    }
    if ($aliasDeduped) {
        $messages[] = 'Removed ambiguous alias' . (count($removedAliases) === 1 ? '' : 'es')
            . ' from public routing (' . implode(', ', $removedAliases) . ') — CMS role takes precedence';
    }
    if ($demoRepair['changed']) {
        if (($demoRepair['action'] ?? '') === 'migrated') {
            $messages[] = 'Migrated legacy demo-website/ into project/';
        } else {
            $messages[] = 'Removed legacy demo-website/ folder';
        }
    }
    if ($pathRepair['changed']) {
        $count = count($pathRepair['files'] ?? []);
        $messages[] = 'Updated /project/ paths in ' . $count . ' HTML file' . ($count === 1 ? '' : 's');
    }
    if ($ssrInvalidated) {
        $messages[] = 'Cleared SSR cache';
    }

    $summary = $messages !== [] ? implode('. ', $messages) . '.' : 'Delivery repaired.';

    if (!$persist) {
        return [
            'status' => 'ok',
            'message' => 'Repair preview ready (not saved). ' . $summary,
            'changed' => true,
            'config' => $config,
            'path_repair' => $pathRepair,
            'demo_repair' => $demoRepair,
        ];
    }

    $configNeedsPersist = $schemaChanged || $publicUrlFixed || $pairChanged || $cmsUrlFixed || $aliasDeduped;
    if ($configNeedsPersist) {
        birkly_hosting_backup_config();
        if (!birkly_hosting_persist($config, true)) {
            return [
                'status' => 'error',
                'message' => 'Failed to save repaired delivery config.',
                'changed' => false,
            ];
        }

        if ($schemaChanged) {
            $log = is_array($config['_migration_log'] ?? null) ? $config['_migration_log'] : [];
            $log[] = [
                'at' => gmdate('c'),
                'from' => 1,
                'to' => BIRKLY_HOSTING_SCHEMA_VERSION,
                'actions' => ['repair_delivery', $syncDomains ? 'sync_domains' : 'schema_only'],
            ];
            $saved = birkly_hosting_load();
            $saved['_migration_log'] = $log;
            birkly_hosting_persist($saved, true);
        }
    }

    return [
        'status' => 'ok',
        'message' => $summary,
        'changed' => true,
        'config' => birkly_hosting_load(),
        'path_repair' => $pathRepair,
        'demo_repair' => $demoRepair,
        'ssr_invalidated' => $ssrInvalidated,
    ];
}

/**
 * Redirect alias hostnames to primary when enabled.
 */
function birkly_hosting_maybe_redirect_alias_to_primary(string $uri): bool
{
    $config = birkly_hosting_load();
    if (empty($config['alias_redirect_to_primary'])) {
        return false;
    }

    $host = birkly_cms_request_host();
    if ($host === '' || birkly_is_local_dev_host($host)) {
        return false;
    }

    if (birkly_hosting_is_cms_alias_host($host)) {
        $target = rtrim((string) $config['cms_base_url'], '/') . ($uri === '' ? '/' : $uri);
        header('Location: ' . $target, true, 301);
        exit;
    }

    if (birkly_hosting_is_public_alias_host($host)) {
        $publicBase = birkly_hosting_public_base_url();
        if ($publicBase === '') {
            return false;
        }
        $target = rtrim($publicBase, '/') . ($uri === '' ? '/' : $uri);
        header('Location: ' . $target, true, 301);
        exit;
    }

    return false;
}

function birkly_hosting_settings_path(): string
{
    return birkly_project_root() . '/settings/hosting.json';
}

function birkly_hosting_reset_cache(): void
{
    unset($GLOBALS['__birkly_hosting_config']);
}

function birkly_hosting_trust_request_host(): bool
{
    $value = strtolower(trim((string) (getenv('BIRKLY_TRUST_REQUEST_HOST') ?: '')));
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

/**
 * Optional Docker/staging overrides (do not set on production unless intentional).
 *
 * @return array{cms_base_url?:string,public_site_url?:string,public_site_mode?:string}
 */
function birkly_hosting_env_overrides(): array
{
    $overrides = [];

    $cms = trim((string) (getenv('BIRKLY_CMS_BASE_URL') ?: ''));
    if ($cms !== '') {
        $overrides['cms_base_url'] = birkly_hosting_normalize_url($cms, false);
    }

    $public = trim((string) (getenv('BIRKLY_PUBLIC_SITE_URL') ?: ''));
    if ($public !== '') {
        $overrides['public_site_url'] = birkly_hosting_normalize_url($public, true);
    }

    $mode = trim((string) (getenv('BIRKLY_PUBLIC_SITE_MODE') ?: ''));
    if ($mode !== '') {
        $overrides['public_site_mode'] = birkly_hosting_normalize_mode($mode);
    }

    return $overrides;
}

/**
 * @param array<string, mixed> $config
 * @return array<string, mixed>
 */
function birkly_hosting_apply_env_overrides(array $config): array
{
    $overrides = birkly_hosting_env_overrides();
    if ($overrides === []) {
        return $config;
    }

    return array_merge($config, $overrides);
}

/**
 * @return array{cms_base_url: string, public_site_mode: string, public_site_url: string, same_host_landing: string, strict_admin_host: bool}
 */
function birkly_hosting_load(): array
{
    if (isset($GLOBALS['__birkly_hosting_config']) && is_array($GLOBALS['__birkly_hosting_config'])) {
        return $GLOBALS['__birkly_hosting_config'];
    }

    $config = birkly_hosting_defaults();
    $path = birkly_hosting_settings_path();
    $rawPublicSiteUrl = '';
    $rawCmsBaseUrl = '';
    if (is_readable($path)) {
        $raw = file_get_contents($path);
        if ($raw !== false) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $config = array_merge($config, $decoded);
                $rawPublicSiteUrl = (string) ($decoded['public_site_url'] ?? '');
                $rawCmsBaseUrl = (string) ($decoded['cms_base_url'] ?? '');
            }
        }
    }

    if (trim((string) $config['cms_base_url']) === '') {
        $config = birkly_hosting_migrate_legacy($config);
    }

    $config = birkly_hosting_normalize_config($config);
    if ($rawCmsBaseUrl !== '' && birkly_hosting_cms_url_has_admin_path($rawCmsBaseUrl)) {
        $config['_cms_url_admin_path_fixup_pending'] = true;
    }
    if ($rawPublicSiteUrl !== ''
        && ($config['public_site_mode'] ?? 'none') === 'project'
        && birkly_hosting_public_url_has_project_path($rawPublicSiteUrl)) {
        $config['_public_url_path_fixup_pending'] = true;
    }
    [$config, $schemaChanged] = birkly_hosting_migrate_schema_v2($config, false);
    if ($schemaChanged) {
        $config['_migration_pending'] = true;
    }

    $config = birkly_hosting_apply_env_overrides($config);
    $config = birkly_hosting_normalize_config($config);

    [$config, $repairedLocal] = birkly_hosting_repair_local_dev_urls($config);

    $GLOBALS['__birkly_hosting_config'] = $config;

    // Persist port fixes so /project → apex redirects stop using http://localhost (no port).
    if (
        $repairedLocal
        && trim((string) $config['cms_base_url']) !== ''
        && empty($GLOBALS['__birkly_hosting_persisting'])
    ) {
        $GLOBALS['__birkly_hosting_persisting'] = true;
        birkly_hosting_persist($config, false);
        unset($GLOBALS['__birkly_hosting_persisting']);
        $GLOBALS['__birkly_hosting_config'] = $config;

        // Existing local installs that already have a project site but never
        // toggled Visit project — enable the header button once.
        require_once __DIR__ . '/project_site.php';
        require_once __DIR__ . '/project_settings.php';
        if (is_file(birkly_public_site_path() . '/index.html')) {
            $rawProject = birkly_project_settings_storage()->read('project.json');
            if (!is_array($rawProject) || !array_key_exists('show_visit_button', $rawProject)) {
                birkly_save_project_settings(['show_visit_button' => true]);
            }
        }
    }

    return $config;
}

function birkly_hosting_normalize_mode(string $mode): string
{
    return in_array($mode, ['none', 'external', 'project'], true) ? $mode : 'none';
}

/**
 * True when cms_base_url incorrectly includes an /admin path segment.
 */
function birkly_hosting_cms_url_has_admin_path(string $url): bool
{
    $path = rtrim((string) (parse_url($url, PHP_URL_PATH) ?? ''), '/');
    if ($path === '') {
        return false;
    }

    return $path === '/admin' || str_ends_with($path, '/admin');
}

/**
 * CMS base URL must be the site origin (admin lives at /admin on that host).
 */
function birkly_hosting_strip_admin_path_from_cms_url(string $url): string
{
    if ($url === '' || !birkly_hosting_cms_url_has_admin_path($url)) {
        return $url;
    }

    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['host'])) {
        return $url;
    }

    $path = rtrim((string) ($parts['path'] ?? ''), '/');
    if ($path === '/admin') {
        $path = '';
    } elseif (str_ends_with($path, '/admin')) {
        $path = substr($path, 0, -strlen('/admin'));
    }

    $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
    $host = birkly_normalize_host((string) $parts['host']);
    $port = isset($parts['port']) ? (int) $parts['port'] : null;
    $normalized = $scheme . '://' . $host;
    if ($port !== null && !in_array($port, [80, 443], true)) {
        $normalized .= ':' . $port;
    }
    if ($path !== '') {
        $normalized .= $path;
    }

    return rtrim($normalized, '/');
}

/**
 * Build an absolute URL for in-app paths (Visit project, redirects).
 */
function birkly_hosting_absolute_app_url(string $path): string
{
    if ($path === '' || preg_match('#^https?://#i', $path)) {
        return $path;
    }

    require_once __DIR__ . '/birkly_paths.php';
    $origin = birkly_cms_web_base_url();
    if ($origin === '' || $origin[0] === '/') {
        return birkly_url($path);
    }

    if ($path[0] !== '/') {
        $path = '/' . $path;
    }

    return rtrim($origin, '/') . birkly_url($path);
}

/**
 * True when the configured public site URL incorrectly includes a /project path.
 */
function birkly_hosting_public_url_has_project_path(string $url): bool
{
    $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
    $path = rtrim($path, '/');
    if ($path === '') {
        return false;
    }

    return $path === '/project' || str_starts_with($path, '/project/');
}

/**
 * Public site URL must be the visitor-facing host (no /project — that is on-disk only).
 */
function birkly_hosting_strip_project_path_from_public_url(string $url): string
{
    if ($url === '' || !birkly_hosting_public_url_has_project_path($url)) {
        return $url;
    }

    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['host'])) {
        return $url;
    }

    $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
    $host = birkly_normalize_host((string) $parts['host']);
    $port = isset($parts['port']) ? (int) $parts['port'] : null;
    $normalized = $scheme . '://' . $host;
    if ($port !== null && !in_array($port, [80, 443], true)) {
        $normalized .= ':' . $port;
    }

    return rtrim($normalized, '/');
}

/**
 * Same host + admin at / serves the bundled site under /project/ in the browser.
 */
function birkly_hosting_same_host_uses_project_url_prefix(): bool
{
    $config = birkly_hosting_load();
    if (($config['public_site_mode'] ?? 'none') !== 'project') {
        return false;
    }
    if (!birkly_hosting_same_host()) {
        return false;
    }

    return ($config['same_host_landing'] ?? 'admin') === 'admin';
}

function birkly_hosting_normalize_url(string $url, bool $allowEmpty = true): string
{
    $url = trim($url);
    if ($url === '') {
        return $allowEmpty ? '' : '';
    }

    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . ltrim($url, '/');
    }

    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['host'])) {
        return '';
    }

    $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
    $host = birkly_normalize_host((string) $parts['host']);
    $port = isset($parts['port']) ? (int) $parts['port'] : null;
    $path = isset($parts['path']) ? rtrim((string) $parts['path'], '/') : '';

    $normalized = $scheme . '://' . $host;
    if ($port !== null && !in_array($port, [80, 443], true)) {
        $normalized .= ':' . $port;
    }
    $normalized .= $path;

    return rtrim($normalized, '/');
}

function birkly_hosting_url_host(string $url): string
{
    if ($url === '') {
        return '';
    }
    return birkly_normalize_host((string) parse_url($url, PHP_URL_HOST));
}

function birkly_hosting_request_scheme(): string
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $proto = strtolower(trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]));
        if (in_array($proto, ['http', 'https'], true)) {
            return $proto;
        }
    }
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return 'https';
    }
    return 'http';
}

/**
 * Current request origin including non-default port (e.g. http://localhost:8080).
 * Uses HTTP_HOST as sent by the browser — do not strip the port first.
 */
function birkly_hosting_request_origin_url(): string
{
    $httpHost = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($httpHost === '') {
        return '';
    }

    return birkly_hosting_normalize_url(birkly_hosting_request_scheme() . '://' . $httpHost, false);
}

function birkly_hosting_build_url_from_request(): string
{
    return birkly_hosting_request_origin_url();
}

/**
 * Local php -S / reverse-proxy installs often persist public_site_url as
 * http://localhost (port dropped). Repair when the live request has a port.
 *
 * @param array<string, mixed> $config
 * @return array{0: array<string, mixed>, 1: bool} [config, changed]
 */
function birkly_hosting_repair_local_dev_urls(array $config): array
{
    require_once __DIR__ . '/birkly_paths.php';
    require_once __DIR__ . '/site_domain.php';

    if (!birkly_is_local_dev_request()) {
        return [$config, false];
    }

    $origin = birkly_hosting_request_origin_url();
    if ($origin === '') {
        return [$config, false];
    }

    $originParts = parse_url($origin);
    $originHost = birkly_normalize_host((string) ($originParts['host'] ?? ''));
    $originPort = isset($originParts['port']) ? (int) $originParts['port'] : null;
    if ($originHost === '' || $originPort === null) {
        return [$config, false];
    }

    // B10: never rewrite an external public URL to the local CMS port.
    $keys = ['cms_base_url'];
    $mode = birkly_hosting_normalize_mode((string) ($config['public_site_mode'] ?? 'none'));
    if ($mode !== 'external') {
        $keys[] = 'public_site_url';
    }

    $changed = false;
    foreach ($keys as $key) {
        $url = trim((string) ($config[$key] ?? ''));
        if ($url === '') {
            continue;
        }
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            continue;
        }
        $host = birkly_normalize_host((string) $parts['host']);
        $port = isset($parts['port']) ? (int) $parts['port'] : null;
        if ($host !== $originHost) {
            continue;
        }
        if ($port === $originPort) {
            continue;
        }
        // Same local host, wrong/missing port → rewrite to the live origin (+ path).
        $path = isset($parts['path']) ? rtrim((string) $parts['path'], '/') : '';
        if ($path === '/') {
            $path = '';
        }
        $config[$key] = rtrim($origin, '/') . $path;
        $changed = true;
    }

    return [$config, $changed];
}

/**
 * Infer hosting settings when hosting.json is empty (P43-D9).
 *
 * @param array<string, mixed> $partial
 * @return array<string, mixed>
 */
function birkly_hosting_migrate_legacy(array $partial = []): array
{
    $config = array_merge(birkly_hosting_defaults(), $partial);
    $requestHost = birkly_cms_request_host();

    $adminFile = birkly_project_root() . '/settings/admin.json';
    if (is_readable($adminFile)) {
        $admin = json_decode((string) file_get_contents($adminFile), true);
        if (is_array($admin) && !empty($admin['cms_url'])) {
            $config['cms_base_url'] = birkly_hosting_normalize_url((string) $admin['cms_url'], false);
        }
    }

    if ($config['cms_base_url'] === '' && $requestHost !== '') {
        if (str_starts_with($requestHost, 'cms.')) {
            $config['cms_base_url'] = birkly_hosting_build_url_from_request();
        }
    }

    $projectIndex = birkly_project_root() . '/project/index.html';
    $hasProject = is_file($projectIndex) || is_dir(birkly_project_root() . '/project');
    $cmsHost = birkly_hosting_url_host((string) $config['cms_base_url']);

    if ($config['cms_base_url'] === '' && $requestHost !== '') {
        $config['cms_base_url'] = birkly_hosting_build_url_from_request();
        $cmsHost = $requestHost;
    }

    if ($hasProject && $requestHost !== '' && $cmsHost !== '' && $requestHost !== $cmsHost) {
        $config['public_site_mode'] = 'project';
        $config['public_site_url'] = birkly_hosting_build_url_from_request();
    } elseif ($hasProject && $requestHost !== '' && $cmsHost === $requestHost && $config['public_site_mode'] === 'none') {
        // Legacy single-host + project folder — keep admin landing unless configured later.
        $config['public_site_mode'] = 'project';
        $config['public_site_url'] = $config['cms_base_url'];
        $config['same_host_landing'] = 'admin';
    }

    if ($config['cms_base_url'] !== '') {
        birkly_hosting_persist($config, false);
        birkly_hosting_sync_admin_cms_url((string) $config['cms_base_url']);
    }

    return $config;
}

/**
 * @param array<string, mixed> $config
 */
function birkly_hosting_persist(array $config, bool $registerDomains = true): bool
{
    $merged = birkly_hosting_normalize_config(array_merge(birkly_hosting_defaults(), $config));
    unset($merged['_migration_pending'], $merged['_cms_url_admin_path_fixup_pending'], $merged['_public_url_path_fixup_pending']);

    if ($merged['public_site_mode'] === 'none') {
        $merged['public_site_url'] = '';
        $merged['public_host_aliases'] = [];
    } elseif ($merged['public_site_url'] === '') {
        return false;
    }

    if ($merged['cms_base_url'] === '') {
        return false;
    }

    $merged['schema_version'] = BIRKLY_HOSTING_SCHEMA_VERSION;

    $dir = dirname(birkly_hosting_settings_path());
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        return false;
    }

    $merged['_updated_at'] = date('c');
    $ok = file_put_contents(
        birkly_hosting_settings_path(),
        json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
    ) !== false;

    if ($ok) {
        birkly_hosting_reset_cache();
        birkly_hosting_sync_admin_cms_url((string) $merged['cms_base_url']);
        birkly_hosting_sync_project_hosting_type((string) $merged['public_site_mode']);
        if ($registerDomains) {
            birkly_hosting_register_domains($merged);
        }
    }

    return $ok;
}

/**
 * Keep project.json hosting_type aligned with Site delivery mode (P61-B1).
 * public_site_mode remains SoT: external→external, project→birkly.
 */
function birkly_hosting_sync_project_hosting_type(string $publicSiteMode): void
{
    $mode = birkly_hosting_normalize_mode($publicSiteMode);
    if ($mode === 'none') {
        return;
    }

    $hostingType = $mode === 'external' ? 'external' : 'birkly';

    try {
        require_once __DIR__ . '/project_settings.php';
        $current = birkly_load_project_settings();
        if (($current['hosting_type'] ?? '') === $hostingType) {
            return;
        }
        birkly_save_project_settings(['hosting_type' => $hostingType]);
    } catch (Throwable $e) {
        error_log('birkly_hosting_sync_project_hosting_type: ' . $e->getMessage());
    }
}

/**
 * @param array<string, mixed> $config
 */
function birkly_hosting_register_domains(array $config): void
{
    require_once __DIR__ . '/site_domain.php';

    $hosts = [];
    $cmsHost = birkly_hosting_url_host((string) ($config['cms_base_url'] ?? ''));
    if ($cmsHost !== '') {
        $hosts[$cmsHost] = true;
    }
    foreach (birkly_hosting_cms_alias_hosts($config) as $alias) {
        $hosts[$alias] = true;
    }

    if (($config['public_site_mode'] ?? 'none') !== 'none') {
        $publicHost = birkly_hosting_url_host((string) ($config['public_site_url'] ?? ''));
        if ($publicHost !== '') {
            $hosts[$publicHost] = true;
        }
        foreach (birkly_hosting_public_alias_hosts($config) as $alias) {
            $hosts[$alias] = true;
        }
    }

    foreach (array_keys($hosts) as $host) {
        birkly_ensure_site_domain_registered($host);
    }
}

function birkly_hosting_sync_admin_cms_url(string $cmsBaseUrl): void
{
    $adminFile = birkly_project_root() . '/settings/admin.json';
    $admin = [];
    if (is_readable($adminFile)) {
        $decoded = json_decode((string) file_get_contents($adminFile), true);
        if (is_array($decoded)) {
            $admin = $decoded;
        }
    }
    $admin['cms_url'] = $cmsBaseUrl;
    $admin['_updated_at'] = date('c');
    file_put_contents($adminFile, json_encode($admin, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
}

function birkly_hosting_cms_host(): string
{
    $config = birkly_hosting_load();
    return birkly_hosting_url_host((string) $config['cms_base_url']);
}

function birkly_hosting_public_host(): string
{
    $config = birkly_hosting_load();
    if ($config['public_site_mode'] === 'none') {
        return '';
    }
    return birkly_hosting_url_host((string) $config['public_site_url']);
}

function birkly_hosting_same_host(): bool
{
    $cms = birkly_hosting_cms_host();
    $public = birkly_hosting_public_host();
    return $cms !== '' && $public !== '' && $cms === $public;
}

/**
 * @return 'cms'|'public'|'local'|'unknown'
 */
function birkly_request_host_role(): string
{
    $host = birkly_cms_request_host();
    if ($host === '') {
        return 'unknown';
    }
    if (birkly_is_local_dev_host($host)) {
        return 'local';
    }

    if (birkly_hosting_host_matches_cms($host)) {
        return 'cms';
    }
    if (birkly_hosting_host_matches_public($host)) {
        return 'public';
    }

    if (birkly_hosting_trust_request_host() && $host !== '') {
        return 'cms';
    }

    return 'unknown';
}

function birkly_cms_base_url(): string
{
    $config = birkly_hosting_load();
    if ($config['cms_base_url'] !== '') {
        return $config['cms_base_url'];
    }
    return birkly_hosting_build_url_from_request();
}

/**
 * Full CMS web base URL including subdirectory install path when applicable.
 * Used by birkly-client.js (data-cms-url / meta) so API calls hit the right prefix.
 */
function birkly_cms_web_base_url(): string
{
    require_once __DIR__ . '/birkly_paths.php';

    $origin = birkly_hosting_strip_admin_path_from_cms_url(birkly_cms_base_url());
    if ($origin === '') {
        return birkly_base_path();
    }

    $path = parse_url($origin, PHP_URL_PATH);
    if (is_string($path) && $path !== '' && $path !== '/') {
        return rtrim($origin, '/');
    }

    $base = birkly_base_path();
    if ($base === '') {
        return rtrim($origin, '/');
    }

    return rtrim($origin, '/') . $base;
}

function birkly_hosting_public_base_url(): string
{
    $config = birkly_hosting_load();
    if ($config['public_site_mode'] === 'none') {
        return '';
    }
    return (string) $config['public_site_url'];
}

function birkly_hosting_admin_url(string $page = 'dashboard', array $params = []): string
{
    $query = http_build_query(array_merge(['page' => $page], $params));
    $path = '/admin/index.php' . ($query !== '' ? '?' . $query : '');
    return birkly_cms_base_url() . birkly_url($path);
}

function birkly_hosting_should_redirect_admin(): bool
{
    $config = birkly_hosting_load();
    if (!$config['strict_admin_host']) {
        return false;
    }
    $role = birkly_request_host_role();
    return $role === 'public' || ($role === 'unknown' && birkly_hosting_cms_host() !== '');
}

function birkly_hosting_root_redirect_url(): ?string
{
    $config = birkly_hosting_load();
    $role = birkly_request_host_role();

    if ($role === 'local') {
        return birkly_hosting_admin_url('dashboard');
    }

    if ($config['public_site_mode'] === 'none') {
        return birkly_hosting_admin_url('dashboard');
    }

    if ($role === 'cms') {
        if (birkly_hosting_same_host() && $config['same_host_landing'] === 'public' && $config['public_site_mode'] === 'project') {
            return rtrim(birkly_hosting_public_base_url(), '/') . '/';
        }
        return birkly_hosting_admin_url('dashboard');
    }

    if ($role === 'public') {
        if ($config['public_site_mode'] === 'project') {
            return rtrim(birkly_hosting_public_base_url(), '/') . '/';
        }
        if ($config['public_site_mode'] === 'external') {
            return birkly_hosting_public_base_url() !== '' ? birkly_hosting_public_base_url() . '/' : null;
        }
    }

    if ($role === 'unknown' && $config['cms_base_url'] !== '') {
        return birkly_hosting_admin_url('dashboard');
    }

    return birkly_hosting_admin_url('dashboard');
}

/**
 * Whether the current host role may load files from project/ (clean or /project/ paths).
 */
function birkly_hosting_role_can_serve_public_project(string $role, string $uri = '/'): bool
{
    if ($role === 'public' || $role === 'local') {
        return true;
    }

    if ($role !== 'cms') {
        return false;
    }

    $config = birkly_hosting_load();
    if (($config['public_site_mode'] ?? 'none') !== 'project') {
        return false;
    }

    if (birkly_hosting_same_host() && ($config['same_host_landing'] ?? 'admin') === 'public') {
        return true;
    }

    return str_starts_with($uri, '/project/') || $uri === '/project';
}

function birkly_hosting_public_project_path(string $uri): string
{
    $uri = $uri === '' ? '/' : $uri;
    if ($uri[0] !== '/') {
        $uri = '/' . $uri;
    }
    if ($uri === '/') {
        return '/index.html';
    }
    return $uri;
}

/**
 * Resolve a site favicon from project/ for public hosts (clean URL paths).
 */
function birkly_hosting_resolve_favicon_file(): ?string
{
    $candidates = [
        '/favicon.ico',
        '/favicon.svg',
        '/favicon.png',
        '/apple-touch-icon.png',
        '/assets/favicon.ico',
        '/assets/favicon.svg',
        '/assets/favicon.png',
    ];

    foreach ($candidates as $path) {
        $file = birkly_hosting_resolve_public_file($path);
        if ($file !== null) {
            return $file;
        }
    }

    return null;
}

function birkly_hosting_resolve_public_file(string $uri): ?string
{
    $config = birkly_hosting_load();
    if ($config['public_site_mode'] !== 'project') {
        return null;
    }

    require_once __DIR__ . '/project_site.php';
    if (birkly_is_git_metadata_path($uri)) {
        return null;
    }

    $role = birkly_request_host_role();
    if (!birkly_hosting_role_can_serve_public_project($role, $uri)) {
        return null;
    }

    $projectRoot = birkly_project_root() . '/project';
    if (!is_dir($projectRoot)) {
        return null;
    }

    if (str_starts_with($uri, '/project/')) {
        $relative = substr($uri, strlen('/project'));
    } elseif (str_starts_with($uri, '/project')) {
        $relative = substr($uri, strlen('/project'));
    } else {
        $relative = birkly_hosting_public_project_path($uri);
    }

    if ($relative === '' || $relative === '/') {
        $relative = '/index.html';
    }

    $candidate = realpath($projectRoot . $relative);
    if ($candidate === false) {
        if (!str_contains(basename($relative), '.') && is_file($projectRoot . rtrim($relative, '/') . '/index.html')) {
            $candidate = realpath($projectRoot . rtrim($relative, '/') . '/index.html');
        }
    }

    $projectReal = realpath($projectRoot);
    if ($candidate === false || $projectReal === false || !str_starts_with($candidate, $projectReal) || !is_file($candidate)) {
        return null;
    }

    return $candidate;
}

function birkly_hosting_clean_public_url(string $uri): string
{
    if (str_starts_with($uri, '/project/')) {
        $clean = substr($uri, strlen('/project'));
        return $clean === '' ? '/' : $clean;
    }
    if ($uri === '/project') {
        return '/';
    }
    return $uri;
}

/**
 * Client snippet for external sites.
 *
 * @param bool $includeWebmcp When true (and WebMCP is enabled), also emit the
 *   `birkly-webmcp.js` tag so an external page gains visitor AI tools. Defaults
 *   to honouring the current WebMCP enablement so callers can pass `true` and
 *   get the right snippet without re-checking config.
 */
function birkly_hosting_client_snippet(bool $includeWebmcp = false): string
{
    $cms = birkly_cms_web_base_url();
    if ($cms === '') {
        return '';
    }
    $cmsAttr = htmlspecialchars($cms, ENT_QUOTES, 'UTF-8');
    require_once __DIR__ . '/build_info.php';
    $clientSrc = htmlspecialchars(rtrim($cms, '/') . birkly_public_runtime_asset_url('birkly-client.js'), ENT_QUOTES, 'UTF-8');
    $snippet = '<script src="' . $clientSrc . '" data-cms-url="' . $cmsAttr . '"></script>';

    if ($includeWebmcp) {
        $webmcp = birkly_hosting_webmcp_snippet();
        if ($webmcp !== '') {
            $snippet .= "\n" . $webmcp;
        }
    }

    return $snippet;
}

/**
 * WebMCP-only client snippet (for external sites). Empty string when WebMCP is
 * disabled or no CMS base URL is configured — an off site advertises nothing.
 * Loads after `birkly-client.js`, which owns page context + public API access.
 */
function birkly_hosting_webmcp_snippet(): string
{
    require_once __DIR__ . '/webmcp.php';
    if (!birkly_webmcp_is_enabled()) {
        return '';
    }
    $cms = birkly_cms_base_url();
    if ($cms === '') {
        return '';
    }
    $cmsAttr = htmlspecialchars($cms, ENT_QUOTES, 'UTF-8');
    $webmcpSrc = htmlspecialchars(rtrim($cms, '/') . birkly_url('/birkly-webmcp.js'), ENT_QUOTES, 'UTF-8');
    return '<script src="' . $webmcpSrc . '" data-cms-url="' . $cmsAttr . '"></script>';
}
