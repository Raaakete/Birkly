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
            if ($cmsHost === '' || $cmsHost !== $publicHost) {
                foreach ($pair as $host) {
                    if ($host !== $publicHost) {
                        $aliases[] = $host;
                    }
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

    // Primaries come from the saved URLs. Alias lists must not hide either URL:
    // a public primary that was also copied into CMS aliases is still the public URL.
    if ($cmsPrimary !== '' && $host === $cmsPrimary && $host !== $publicPrimary) {
        return 'cms_primary';
    }
    if ($publicPrimary !== '' && $host === $publicPrimary && $host !== $cmsPrimary) {
        return 'public_primary';
    }
    if ($cmsPrimary !== '' && $host === $cmsPrimary) {
        return 'cms_primary';
    }
    if (in_array($host, birkly_hosting_cms_alias_hosts($config), true)) {
        return 'cms';
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
 * @param list<string> $aliases
 * @return list<string>
 */
function birkly_hosting_alias_list_without(array $aliases, string ...$hosts): array
{
    $drop = [];
    foreach ($hosts as $host) {
        $host = birkly_normalize_host($host);
        if ($host !== '') {
            $drop[$host] = true;
        }
    }

    $kept = [];
    foreach ($aliases as $alias) {
        $alias = birkly_normalize_host((string) $alias);
        if ($alias === '' || isset($drop[$alias])) {
            continue;
        }
        $kept[$alias] = $alias;
    }

    return array_values($kept);
}

function birkly_hosting_url_scheme(string $url, string $fallback = 'https'): string
{
    $scheme = parse_url($url, PHP_URL_SCHEME);
    if (!is_string($scheme) || $scheme === '') {
        return $fallback;
    }

    return strtolower($scheme);
}

/**
 * @param list<string> $aliases
 * @return list<string>
 */
function birkly_hosting_claim_alias_hosts(string $host, string $cmsPrimary, string $publicPrimary, array $aliases): array
{
    $claimed = [$host];
    foreach (birkly_hosting_www_apex_pair($host) as $pairHost) {
        if ($pairHost === '' || $pairHost === $cmsPrimary || $pairHost === $publicPrimary) {
            continue;
        }
        $claimed[] = $pairHost;
    }

    return array_values(array_unique(array_merge($aliases, $claimed)));
}

/**
 * @return array{status: string, message: string, config?: array<string, mixed>}
 */
function birkly_hosting_set_domain_delivery_role(string $host, string $role): array
{
    $host = birkly_normalize_host($host);
    if ($role === 'cms_primary') {
        return birkly_hosting_set_cms_primary($host);
    }
    if ($role === 'public_primary') {
        return birkly_hosting_set_public_primary($host);
    }

    $allowed = ['cms', 'public', 'api_only'];
    if ($host === '' || !in_array($role, $allowed, true)) {
        return ['status' => 'error', 'message' => 'Invalid host or role'];
    }

    if (birkly_hosting_env_overrides() !== []) {
        return ['status' => 'error', 'message' => 'Cannot change delivery roles while environment overrides are active'];
    }

    $config = birkly_hosting_normalize_config(birkly_hosting_load());
    $cmsPrimary = birkly_hosting_url_host((string) ($config['cms_base_url'] ?? ''));
    $publicPrimary = birkly_hosting_url_host((string) ($config['public_site_url'] ?? ''));

    if ($role === 'public' && $host === $cmsPrimary) {
        return ['status' => 'error', 'message' => 'Choose Public (primary) to move the CMS hostname onto the public URL'];
    }
    if ($role === 'cms' && $host === $publicPrimary) {
        return ['status' => 'error', 'message' => 'Choose CMS (primary) to move the public hostname onto the CMS URL'];
    }
    if ($role === 'api_only' && ($host === $cmsPrimary || ($publicPrimary !== '' && $host === $publicPrimary))) {
        return ['status' => 'error', 'message' => 'Promote another hostname before removing this primary URL'];
    }
    if ($role === 'cms' && $host === $cmsPrimary) {
        return ['status' => 'error', 'message' => 'Choose another hostname as CMS (primary) before demoting this one'];
    }
    if ($role === 'public' && $host === $publicPrimary) {
        return ['status' => 'error', 'message' => 'Choose another hostname as Public (primary) before demoting this one'];
    }

    $cmsAliases = birkly_hosting_alias_list_without(birkly_hosting_cms_alias_hosts($config), $host);
    $publicAliases = birkly_hosting_alias_list_without(birkly_hosting_public_alias_hosts($config), $host);

    if ($role === 'cms') {
        $cmsAliases = birkly_hosting_claim_alias_hosts($host, $cmsPrimary, $publicPrimary, $cmsAliases);
        $publicAliases = birkly_hosting_alias_list_without($publicAliases, ...$cmsAliases);
    } elseif ($role === 'public') {
        $publicAliases = birkly_hosting_claim_alias_hosts($host, $cmsPrimary, $publicPrimary, $publicAliases);
        $cmsAliases = birkly_hosting_alias_list_without($cmsAliases, ...$publicAliases);
    }

    $config['cms_host_aliases'] = birkly_hosting_normalize_alias_hosts($cmsAliases, $cmsPrimary);
    $config['public_host_aliases'] = birkly_hosting_normalize_alias_hosts($publicAliases, $publicPrimary);
    $config = birkly_hosting_detach_primaries_from_aliases($config);

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
 * @return 'cms_primary'|'cms'|'public_primary'|'public'|'api_only'|null
 */
function birkly_hosting_settable_delivery_role(string $fullRole): ?string
{
    return match ($fullRole) {
        'cms_primary', 'cms', 'public_primary', 'public', 'api_only' => $fullRole,
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
    $mode = birkly_hosting_normalize_mode((string) ($config['public_site_mode'] ?? 'none'));
    $aliasRedirect = !empty($config['alias_redirect_to_primary']);
    foreach (array_keys($hosts) as $host) {
        if ($host === '') {
            continue;
        }
        $role = birkly_hosting_domain_delivery_role($host);
        $suggest = birkly_hosting_suggest_delivery_role($host);
        $isPrimary = in_array($role, ['cms_primary', 'public_primary'], true);
        $isAlias = in_array($role, ['cms', 'public'], true);
        $redirectTarget = '';
        if ($role === 'cms') {
            $redirectTarget = $cmsPrimary;
        } elseif ($role === 'public') {
            $redirectTarget = $publicPrimary;
        }
        $rows[] = [
            'host' => $host,
            'role' => $role,
            'cors_registered' => isset($registeredLookup[$host]),
            'suggested_role' => $suggest['role'],
            'suggest_reason' => $suggest['reason'],
            'is_primary' => $isPrimary,
            'settable_role' => birkly_hosting_settable_delivery_role($role),
            'is_alias' => $isAlias,
            'redirects_to_primary' => $isAlias && $aliasRedirect && $redirectTarget !== '',
            'redirect_target_host' => $redirectTarget,
            'can_make_public_primary' => $mode !== 'none' && $publicPrimary !== '' && $host !== $publicPrimary,
            'can_make_cms_primary' => $cmsPrimary !== '' && $host !== $cmsPrimary,
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
 * @return array{status: string, message: string, config?: array<string, mixed>}
 */
function birkly_hosting_set_alias_redirect(bool $enabled): array
{
    if (birkly_hosting_env_overrides() !== []) {
        return ['status' => 'error', 'message' => 'Cannot change alias redirect while environment overrides are active'];
    }

    $config = birkly_hosting_normalize_config(birkly_hosting_load());
    $config['alias_redirect_to_primary'] = $enabled;

    birkly_hosting_backup_config();
    if (!birkly_hosting_persist($config, true)) {
        return ['status' => 'error', 'message' => 'Failed to save alias redirect setting'];
    }

    return [
        'status' => 'ok',
        'message' => $enabled
            ? 'Alias hostnames will redirect to primary URLs'
            : 'Alias hostnames will serve content directly',
        'config' => birkly_hosting_load(),
    ];
}

/**
 * Drop either primary hostname out of the opposite alias list.
 *
 * @param array<string, mixed> $config
 * @return array<string, mixed>
 */
function birkly_hosting_detach_primaries_from_aliases(array $config): array
{
    $cmsPrimary = birkly_hosting_url_host((string) ($config['cms_base_url'] ?? ''));
    $publicPrimary = birkly_hosting_url_host((string) ($config['public_site_url'] ?? ''));
    $config['cms_host_aliases'] = birkly_hosting_normalize_alias_hosts(
        birkly_hosting_alias_list_without(
            birkly_hosting_cms_alias_hosts($config),
            $cmsPrimary,
            $publicPrimary
        ),
        $cmsPrimary
    );
    $config['public_host_aliases'] = birkly_hosting_normalize_alias_hosts(
        birkly_hosting_alias_list_without(
            birkly_hosting_public_alias_hosts($config),
            $publicPrimary,
            $cmsPrimary
        ),
        $publicPrimary
    );

    return $config;
}

/**
 * Promote a registered host to the primary CMS URL.
 * The previous CMS host stays on the domain list as a CMS alias, unless it is
 * still the public URL. Promoting the public URL swaps the two primaries.
 *
 * @return array{status: string, message: string, config?: array<string, mixed>}
 */
function birkly_hosting_set_cms_primary(string $host): array
{
    if (birkly_hosting_env_overrides() !== []) {
        return ['status' => 'error', 'message' => 'Cannot change primary URL while environment overrides are active'];
    }

    $host = birkly_normalize_host($host);
    if ($host === '') {
        return ['status' => 'error', 'message' => 'Invalid host'];
    }

    $config = birkly_hosting_normalize_config(birkly_hosting_load());
    $cmsPrimary = birkly_hosting_url_host((string) ($config['cms_base_url'] ?? ''));
    $publicPrimary = birkly_hosting_url_host((string) ($config['public_site_url'] ?? ''));
    $mode = birkly_hosting_normalize_mode((string) ($config['public_site_mode'] ?? 'none'));
    $cmsAliases = birkly_hosting_cms_alias_hosts($config);
    $publicAliases = birkly_hosting_public_alias_hosts($config);

    if ($cmsPrimary === $host) {
        $config['cms_host_aliases'] = birkly_hosting_normalize_alias_hosts(
            birkly_hosting_alias_list_without($cmsAliases, $host),
            $cmsPrimary
        );
        $config['public_host_aliases'] = birkly_hosting_normalize_alias_hosts(
            birkly_hosting_alias_list_without($publicAliases, $host),
            $publicPrimary
        );
        $config = birkly_hosting_detach_primaries_from_aliases($config);
        if ($config['cms_host_aliases'] !== $cmsAliases || $config['public_host_aliases'] !== $publicAliases) {
            birkly_hosting_backup_config();
            if (!birkly_hosting_persist($config, true)) {
                return ['status' => 'error', 'message' => 'Failed to set primary CMS URL'];
            }
            $config = birkly_hosting_load();
        }

        return [
            'status' => 'ok',
            'message' => 'Host is already the primary CMS URL',
            'config' => $config,
        ];
    }

    $cmsScheme = birkly_hosting_url_scheme((string) ($config['cms_base_url'] ?? ''));
    $publicScheme = birkly_hosting_url_scheme((string) ($config['public_site_url'] ?? ''), $cmsScheme);
    $cmsAliases = birkly_hosting_alias_list_without($cmsAliases, $host);
    $publicAliases = birkly_hosting_alias_list_without($publicAliases, $host);
    $newPublicPrimary = $publicPrimary;

    if ($host === $publicPrimary && $cmsPrimary !== '' && $cmsPrimary !== $host && $mode !== 'none') {
        $config['cms_base_url'] = birkly_hosting_normalize_url($cmsScheme . '://' . $host, false);
        $config['public_site_url'] = birkly_hosting_normalize_url($publicScheme . '://' . $cmsPrimary, true);
        $newPublicPrimary = $cmsPrimary;
        $cmsAliases = birkly_hosting_alias_list_without($cmsAliases, $cmsPrimary, $host);
        $publicAliases = birkly_hosting_alias_list_without($publicAliases, $cmsPrimary, $host);
    } else {
        $config['cms_base_url'] = birkly_hosting_normalize_url($cmsScheme . '://' . $host, false);
        if ($cmsPrimary !== '' && $cmsPrimary !== $host && $cmsPrimary !== $publicPrimary) {
            $cmsAliases[] = $cmsPrimary;
            $publicAliases = birkly_hosting_alias_list_without($publicAliases, $cmsPrimary);
        }
        $cmsAliases = birkly_hosting_alias_list_without($cmsAliases, $host, $publicPrimary);
    }

    $publicAliases = birkly_hosting_alias_list_without($publicAliases, $host, ...$cmsAliases);
    $config['cms_host_aliases'] = birkly_hosting_normalize_alias_hosts($cmsAliases, $host);
    $config['public_host_aliases'] = birkly_hosting_normalize_alias_hosts($publicAliases, $newPublicPrimary);
    $config = birkly_hosting_detach_primaries_from_aliases($config);

    birkly_hosting_backup_config();
    if (!birkly_hosting_persist($config, true)) {
        return ['status' => 'error', 'message' => 'Failed to set primary CMS URL'];
    }

    return [
        'status' => 'ok',
        'message' => 'Primary CMS URL updated to ' . $host,
        'config' => birkly_hosting_load(),
    ];
}

/**
 * Promote a host to the primary public URL.
 * The previous public host stays on the domain list as a public alias.
 * Promoting the CMS URL swaps the two primaries so neither host is dropped
 * and neither stays listed on both roles.
 *
 * @return array{status: string, message: string, config?: array<string, mixed>}
 */
function birkly_hosting_set_public_primary(string $host): array
{
    if (birkly_hosting_env_overrides() !== []) {
        return ['status' => 'error', 'message' => 'Cannot change primary URL while environment overrides are active'];
    }

    $host = birkly_normalize_host($host);
    if ($host === '') {
        return ['status' => 'error', 'message' => 'Invalid host'];
    }

    $config = birkly_hosting_normalize_config(birkly_hosting_load());
    $mode = birkly_hosting_normalize_mode((string) ($config['public_site_mode'] ?? 'none'));
    if ($mode === 'none') {
        return ['status' => 'error', 'message' => 'Configure a public site before setting a primary URL'];
    }

    $currentPrimary = birkly_hosting_url_host((string) ($config['public_site_url'] ?? ''));
    $cmsPrimary = birkly_hosting_url_host((string) ($config['cms_base_url'] ?? ''));
    $cmsAliases = birkly_hosting_cms_alias_hosts($config);
    $aliases = birkly_hosting_public_alias_hosts($config);

    if ($currentPrimary === $host) {
        $config['cms_host_aliases'] = birkly_hosting_normalize_alias_hosts(
            birkly_hosting_alias_list_without($cmsAliases, $host),
            $cmsPrimary
        );
        $config['public_host_aliases'] = birkly_hosting_normalize_alias_hosts(
            birkly_hosting_alias_list_without($aliases, $host),
            $currentPrimary
        );
        $config = birkly_hosting_detach_primaries_from_aliases($config);
        if ($config['cms_host_aliases'] !== $cmsAliases || $config['public_host_aliases'] !== $aliases) {
            birkly_hosting_backup_config();
            if (!birkly_hosting_persist($config, true)) {
                return ['status' => 'error', 'message' => 'Failed to set primary public URL'];
            }
            $config = birkly_hosting_load();
        }

        return ['status' => 'ok', 'message' => 'Host is already the primary public URL', 'config' => $config];
    }

    $publicScheme = birkly_hosting_url_scheme((string) ($config['public_site_url'] ?? ''));
    $cmsScheme = birkly_hosting_url_scheme((string) ($config['cms_base_url'] ?? ''), $publicScheme);
    $cmsAliases = birkly_hosting_alias_list_without($cmsAliases, $host);
    $aliases = birkly_hosting_alias_list_without($aliases, $host);
    $newCmsPrimary = $cmsPrimary;

    if ($cmsPrimary === $host && $currentPrimary !== '' && $currentPrimary !== $host) {
        $config['public_site_url'] = birkly_hosting_normalize_url($publicScheme . '://' . $host, true);
        $config['cms_base_url'] = birkly_hosting_normalize_url($cmsScheme . '://' . $currentPrimary, false);
        $newCmsPrimary = $currentPrimary;
        $cmsAliases = birkly_hosting_alias_list_without($cmsAliases, $host, $currentPrimary);
        $aliases = birkly_hosting_alias_list_without($aliases, $host, $currentPrimary);
    } else {
        if ($currentPrimary !== '' && $currentPrimary !== $cmsPrimary && $currentPrimary !== $host) {
            $aliases[] = $currentPrimary;
        }
        $config['public_site_url'] = birkly_hosting_normalize_url($publicScheme . '://' . $host, true);
    }

    foreach (birkly_hosting_www_apex_pair($host) as $pairHost) {
        if ($pairHost === '' || $pairHost === $host || $pairHost === $newCmsPrimary) {
            continue;
        }
        $aliases[] = $pairHost;
        $cmsAliases = birkly_hosting_alias_list_without($cmsAliases, $pairHost);
    }

    $cmsAliases = birkly_hosting_alias_list_without($cmsAliases, $host, $newCmsPrimary);
    $aliases = birkly_hosting_alias_list_without($aliases, $host, $newCmsPrimary, ...$cmsAliases);
    $config['cms_host_aliases'] = birkly_hosting_normalize_alias_hosts($cmsAliases, $newCmsPrimary);
    $config['public_host_aliases'] = birkly_hosting_normalize_alias_hosts($aliases, $host);
    $config = birkly_hosting_detach_primaries_from_aliases($config);

    birkly_hosting_backup_config();
    if (!birkly_hosting_persist($config, true)) {
        return ['status' => 'error', 'message' => 'Failed to set primary public URL'];
    }

    return [
        'status' => 'ok',
        'message' => 'Primary public URL updated to ' . $host,
        'config' => birkly_hosting_load(),
    ];
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
