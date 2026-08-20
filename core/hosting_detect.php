<?php
/**
 * Environment detection and delivery config proposals (P66-M2a).
 */

declare(strict_types=1);

require_once __DIR__ . '/hosting_urls.php';
require_once __DIR__ . '/birkly_paths.php';
require_once __DIR__ . '/project_site.php';

/**
 * @return array<string, mixed>
 */
function birkly_hosting_detect_environment(): array
{
    $envOverrides = birkly_hosting_env_overrides();
    $projectIndex = birkly_public_site_path() . '/index.html';

    return [
        'request_origin' => birkly_hosting_build_url_from_request(),
        'request_host' => birkly_cms_request_host(),
        'install_base_path' => birkly_base_path(),
        'has_project_index' => is_file($projectIndex),
        'has_project_dir' => is_dir(birkly_public_site_path()),
        'env_managed' => $envOverrides !== [],
        'env_override_keys' => array_keys($envOverrides),
        'trust_request_host' => birkly_hosting_trust_request_host(),
    ];
}

/**
 * @param array<string, mixed> $current
 * @param array<string, mixed> $proposal
 * @return list<array{field: string, from: mixed, to: mixed}>
 */
function birkly_hosting_config_diff(array $current, array $proposal): array
{
    $fields = [
        'cms_base_url',
        'cms_host_aliases',
        'public_site_mode',
        'public_site_url',
        'public_host_aliases',
        'same_host_landing',
        'strict_admin_host',
        'alias_redirect_to_primary',
    ];
    $changes = [];
    foreach ($fields as $field) {
        $from = $current[$field] ?? null;
        $to = $proposal[$field] ?? null;
        if ($from === $to) {
            continue;
        }
        if (is_array($from) && is_array($to) && $from == $to) {
            continue;
        }
        $changes[] = ['field' => $field, 'from' => $from, 'to' => $to];
    }

    return $changes;
}

/**
 * @return array{detect: array<string, mixed>, current: array<string, mixed>, proposal: array<string, mixed>, changes: list<array<string, mixed>>}
 */
function birkly_hosting_propose_config(): array
{
    $detect = birkly_hosting_detect_environment();
    $current = birkly_hosting_normalize_config(birkly_hosting_load());
    $proposal = $current;

    if ($detect['env_managed']) {
        return [
            'detect' => $detect,
            'current' => $current,
            'proposal' => $proposal,
            'changes' => [],
            'env_locked' => true,
            'message' => 'Delivery URLs are managed by environment variables.',
        ];
    }

    $origin = (string) ($detect['request_origin'] ?? '');
    if (trim((string) ($proposal['cms_base_url'] ?? '')) === '' && $origin !== '') {
        $proposal['cms_base_url'] = $origin;
    }

    $cmsHost = birkly_hosting_url_host((string) ($proposal['cms_base_url'] ?? ''));
    $mode = birkly_hosting_normalize_mode((string) ($proposal['public_site_mode'] ?? 'none'));

    if ($mode === 'none' && !empty($detect['has_project_index']) && $origin !== '') {
        $proposal['public_site_mode'] = 'project';
        if (str_starts_with($cmsHost, 'cms.') && strlen($cmsHost) > 4) {
            $apex = substr($cmsHost, 4);
            $scheme = parse_url((string) $proposal['cms_base_url'], PHP_URL_SCHEME) ?: 'https';
            $proposal['public_site_url'] = birkly_hosting_normalize_url($scheme . '://' . $apex, true);
            $proposal['same_host_landing'] = 'admin';
        } else {
            $proposal['public_site_url'] = (string) $proposal['cms_base_url'];
            $proposal['same_host_landing'] = 'public';
        }
        $mode = 'project';
    }

    if ($mode !== 'none') {
        $publicHost = birkly_hosting_url_host((string) ($proposal['public_site_url'] ?? ''));
        if ($publicHost !== '') {
            $pair = birkly_hosting_www_apex_pair($publicHost);
            $aliases = [];
            foreach ($pair as $host) {
                if ($host !== $publicHost) {
                    $aliases[] = $host;
                }
            }
            $proposal['public_host_aliases'] = birkly_hosting_normalize_alias_hosts(
                array_merge($proposal['public_host_aliases'] ?? [], $aliases),
                $publicHost
            );
        }
        if ($cmsHost !== '') {
            $cmsPair = birkly_hosting_www_apex_pair($cmsHost);
            $cmsAliases = [];
            foreach ($cmsPair as $host) {
                if ($host !== $cmsHost) {
                    $cmsAliases[] = $host;
                }
            }
            $proposal['cms_host_aliases'] = birkly_hosting_normalize_alias_hosts(
                array_merge($proposal['cms_host_aliases'] ?? [], $cmsAliases),
                $cmsHost
            );
        }
    }

    $proposal = birkly_hosting_normalize_config($proposal);

    return [
        'detect' => $detect,
        'current' => $current,
        'proposal' => $proposal,
        'changes' => birkly_hosting_config_diff($current, $proposal),
        'env_locked' => false,
        'message' => '',
    ];
}

/**
 * @return 'cms_primary'|'cms'|'public_primary'|'public'|'api_only'|'unassigned'
 */
function birkly_hosting_domain_delivery_role(string $host): string
{
    $host = birkly_normalize_host($host);
    if ($host === '') {
        return 'unassigned';
    }

    $config = birkly_hosting_load();
    $cmsPrimary = birkly_hosting_url_host((string) ($config['cms_base_url'] ?? ''));
    $publicPrimary = birkly_hosting_url_host((string) ($config['public_site_url'] ?? ''));

    if ($cmsPrimary !== '' && $host === $cmsPrimary) {
        return 'cms_primary';
    }
    if (in_array($host, birkly_hosting_cms_alias_hosts($config), true)) {
        return 'cms';
    }
    if ($publicPrimary !== '' && $host === $publicPrimary) {
        return 'public_primary';
    }
    if (in_array($host, birkly_hosting_public_alias_hosts($config), true)) {
        return 'public';
    }

    if (in_array($host, birkly_hosting_collect_registered_domain_hosts(), true)) {
        return 'api_only';
    }

    return 'unassigned';
}

/**
 * @return array{status: string, message: string, config?: array<string, mixed>}
 */
function birkly_hosting_set_domain_delivery_role(string $host, string $role): array
{
    $host = birkly_normalize_host($host);
    $allowed = ['cms', 'public', 'api_only'];
    if ($host === '' || !in_array($role, $allowed, true)) {
        return ['status' => 'error', 'message' => 'Invalid host or role'];
    }

    $config = birkly_hosting_normalize_config(birkly_hosting_load());
    $cmsPrimary = birkly_hosting_url_host((string) ($config['cms_base_url'] ?? ''));
    $publicPrimary = birkly_hosting_url_host((string) ($config['public_site_url'] ?? ''));
    $cmsAliases = birkly_hosting_cms_alias_hosts($config);
    $publicAliases = birkly_hosting_public_alias_hosts($config);

    $cmsAliases = array_values(array_filter($cmsAliases, static fn (string $h): bool => $h !== $host));
    $publicAliases = array_values(array_filter($publicAliases, static fn (string $h): bool => $h !== $host));

    if ($role === 'cms' && $host !== $cmsPrimary) {
        $cmsAliases[] = $host;
    } elseif ($role === 'public' && $host !== $publicPrimary) {
        $publicAliases[] = $host;
    }

    $config['cms_host_aliases'] = birkly_hosting_normalize_alias_hosts($cmsAliases, $cmsPrimary);
    $config['public_host_aliases'] = birkly_hosting_normalize_alias_hosts($publicAliases, $publicPrimary);

    birkly_hosting_backup_config();
    if (!birkly_hosting_persist($config, true)) {
        return ['status' => 'error', 'message' => 'Failed to save delivery roles'];
    }

    return [
        'status' => 'ok',
        'message' => 'Domain delivery role updated',
        'config' => birkly_hosting_load(),
        'role' => birkly_hosting_domain_delivery_role($host),
    ];
}

/**
 * @param array<string, mixed> $proposal
 * @return array{status: string, message: string, data?: array<string, mixed>}
 */
function birkly_hosting_apply_proposal(array $proposal): array
{
    if (birkly_hosting_env_overrides() !== []) {
        return ['status' => 'error', 'message' => 'Cannot apply proposal while environment overrides are active'];
    }

    $current = birkly_hosting_load();
    $merged = array_merge($current, $proposal);
    $merged = birkly_hosting_normalize_config($merged);

    if ($merged['public_site_mode'] !== 'none' && $merged['public_site_url'] === '') {
        return ['status' => 'error', 'message' => 'Public site URL is required for the selected mode'];
    }
    if ($merged['cms_base_url'] === '') {
        return ['status' => 'error', 'message' => 'CMS URL is required'];
    }

    birkly_hosting_backup_config();
    if (!birkly_hosting_persist($merged, true)) {
        return ['status' => 'error', 'message' => 'Failed to apply delivery proposal'];
    }

    require_once __DIR__ . '/hosting_probe.php';
    require_once __DIR__ . '/project_site.php';
    if (($merged['public_site_mode'] ?? 'none') === 'project') {
        birkly_project_repair_legacy_paths(true);
    }

    return [
        'status' => 'ok',
        'message' => 'Delivery proposal applied',
        'data' => [
            'hosting' => birkly_hosting_load(),
            'delivery' => birkly_delivery_probe(['deep' => false]),
        ],
    ];
}

/**
 * @return array<string, string>
 */
function birkly_hosting_domain_roles_map(): array
{
    $map = [];
    foreach (birkly_hosting_collect_registered_domain_hosts() as $host) {
        $map[$host] = birkly_hosting_domain_delivery_role($host);
    }

    return $map;
}

/**
 * Map a full delivery role to the value accepted by set_domain_delivery_role().
 *
 * @return 'cms'|'public'|'api_only'|null null when the host is a primary URL
 */
function birkly_hosting_settable_delivery_role(string $fullRole): ?string
{
    return match ($fullRole) {
        'cms' => 'cms',
        'public' => 'public',
        'api_only' => 'api_only',
        'cms_primary', 'public_primary' => null,
        default => 'api_only',
    };
}

/**
 * @return array{role: 'cms'|'public'|'api_only', reason: string}
 */
function birkly_hosting_suggest_delivery_role(string $host): array
{
    $host = birkly_normalize_host($host);
    if ($host === '') {
        return ['role' => 'api_only', 'reason' => 'Enter a valid hostname.'];
    }

    $config = birkly_hosting_load();
    $current = birkly_hosting_domain_delivery_role($host);
    if (in_array($current, ['cms_primary', 'public_primary'], true)) {
        return [
            'role' => $current === 'cms_primary' ? 'cms' : 'public',
            'reason' => 'Already configured as a primary delivery URL.',
        ];
    }
    if ($current === 'cms') {
        return ['role' => 'cms', 'reason' => 'Already assigned as a CMS alias.'];
    }
    if ($current === 'public') {
        return ['role' => 'public', 'reason' => 'Already assigned as a public alias.'];
    }

    $cmsPrimary = birkly_hosting_url_host((string) ($config['cms_base_url'] ?? ''));
    $publicPrimary = birkly_hosting_url_host((string) ($config['public_site_url'] ?? ''));
    $mode = birkly_hosting_normalize_mode((string) ($config['public_site_mode'] ?? 'none'));

    if (str_starts_with($host, 'cms.')) {
        return ['role' => 'cms', 'reason' => 'cms.* hostnames usually serve admin and API.'];
    }

    if ($cmsPrimary !== '' && str_starts_with($cmsPrimary, 'cms.')) {
        $apex = substr($cmsPrimary, 4);
        if ($host === $apex || in_array($host, birkly_hosting_www_apex_pair($apex), true)) {
            if ($mode !== 'none') {
                return ['role' => 'public', 'reason' => 'Apex/www pair for a cms.* CMS URL usually serves the public site.'];
            }
        }
    }

    if ($publicPrimary !== '' && $mode !== 'none') {
        $publicPair = birkly_hosting_www_apex_pair($publicPrimary);
        if (in_array($host, $publicPair, true)) {
            return ['role' => 'public', 'reason' => 'Matches the public site hostname pattern.'];
        }
    }

    if ($cmsPrimary !== '') {
        $cmsPair = birkly_hosting_www_apex_pair($cmsPrimary);
        if (in_array($host, $cmsPair, true) && $host !== $cmsPrimary) {
            return ['role' => 'cms', 'reason' => 'Alternate hostname for the CMS URL.'];
        }
    }

    if ($mode === 'none') {
        return ['role' => 'api_only', 'reason' => 'No public site configured — CORS/API access only.'];
    }

    return ['role' => 'api_only', 'reason' => 'Default: allow Public API (CORS). Assign public or CMS if visitors should load this host.'];
}

/**
 * @return list<array{host: string, role: string, cors_registered: bool, suggested_role: string, suggest_reason: string, is_primary: bool, settable_role: string|null}>
 */
function birkly_hosting_delivery_matrix(): array
{
    $config = birkly_hosting_load();
    $registered = birkly_hosting_collect_registered_domain_hosts();
    $registeredLookup = array_fill_keys($registered, true);

    $hosts = [];
    $cmsPrimary = birkly_hosting_url_host((string) ($config['cms_base_url'] ?? ''));
    $publicPrimary = birkly_hosting_url_host((string) ($config['public_site_url'] ?? ''));
    if ($cmsPrimary !== '') {
        $hosts[$cmsPrimary] = true;
    }
    foreach (birkly_hosting_cms_alias_hosts($config) as $alias) {
        $hosts[$alias] = true;
    }
    if ($publicPrimary !== '') {
        $hosts[$publicPrimary] = true;
    }
    foreach (birkly_hosting_public_alias_hosts($config) as $alias) {
        $hosts[$alias] = true;
    }
    foreach ($registered as $host) {
        $hosts[$host] = true;
    }

    $rows = [];
    foreach (array_keys($hosts) as $host) {
        if ($host === '') {
            continue;
        }
        $role = birkly_hosting_domain_delivery_role($host);
        $suggest = birkly_hosting_suggest_delivery_role($host);
        $isPrimary = in_array($role, ['cms_primary', 'public_primary'], true);
        $rows[] = [
            'host' => $host,
            'role' => $role,
            'cors_registered' => isset($registeredLookup[$host]),
            'suggested_role' => $suggest['role'],
            'suggest_reason' => $suggest['reason'],
            'is_primary' => $isPrimary,
            'settable_role' => $isPrimary ? null : birkly_hosting_settable_delivery_role($role),
        ];
    }

    $priority = [
        'cms_primary' => 0,
        'public_primary' => 1,
        'cms' => 2,
        'public' => 3,
        'api_only' => 4,
        'unassigned' => 5,
    ];
    usort($rows, static function (array $a, array $b) use ($priority): int {
        $pa = $priority[$a['role']] ?? 99;
        $pb = $priority[$b['role']] ?? 99;
        if ($pa !== $pb) {
            return $pa <=> $pb;
        }

        return strcmp($a['host'], $b['host']);
    });

    return $rows;
}

/**
 * @return list<string>
 */
function birkly_hosting_public_delivery_hosts(): array
{
    $config = birkly_hosting_load();
    if (birkly_hosting_normalize_mode((string) ($config['public_site_mode'] ?? 'none')) === 'none') {
        return [];
    }

    $hosts = [];
    $primary = birkly_hosting_url_host((string) ($config['public_site_url'] ?? ''));
    if ($primary !== '') {
        $hosts[] = $primary;
    }
    foreach (birkly_hosting_public_alias_hosts($config) as $alias) {
        $hosts[] = $alias;
    }

    return array_values(array_unique($hosts));
}
