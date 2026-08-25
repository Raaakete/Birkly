<?php
/**
 * Discovery packs — SEO, Agent Guide, WebMCP (P67-M1).
 *
 * Pack master toggles live in settings/discovery.json. Turning a pack off stops
 * public serve/inject but never deletes pack-specific data (seo.json,
 * ai_presence/, webmcp.json).
 */

declare(strict_types=1);

require_once __DIR__ . '/birkly_paths.php';

/** @return list<string> */
function birkly_discovery_pack_ids(): array
{
    return ['seo', 'agent_guide', 'webmcp'];
}

/**
 * @return array<string, mixed>
 */
function birkly_discovery_defaults(): array
{
    $packs = [];
    foreach (birkly_discovery_pack_ids() as $id) {
        $packs[$id] = [
            'enabled' => false,
            'delivery' => [
                'mode' => 'auto_inject',
            ],
        ];
    }

    return [
        'packs' => $packs,
        'verify_last_run' => null,
        '_schema_version' => 1,
    ];
}

function birkly_discovery_settings_path(): string
{
    return birkly_project_root() . '/settings/discovery.json';
}

function birkly_discovery_reset_cache(): void
{
    unset($GLOBALS['__birkly_discovery_config']);
}

/**
 * @return array<string, mixed>
 */
function birkly_discovery_load(): array
{
    if (isset($GLOBALS['__birkly_discovery_config']) && is_array($GLOBALS['__birkly_discovery_config'])) {
        return $GLOBALS['__birkly_discovery_config'];
    }

    static $migrating = false;
    if ($migrating) {
        return birkly_discovery_defaults();
    }

    $config = birkly_discovery_defaults();
    $path = birkly_discovery_settings_path();
    if (is_readable($path)) {
        $raw = file_get_contents($path);
        if ($raw !== false) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $config = birkly_discovery_normalize($decoded);
            }
        }
    } else {
        $migrating = true;
        $config = birkly_discovery_migrate_from_legacy($config);
        $migrating = false;
    }

    $GLOBALS['__birkly_discovery_config'] = $config;
    return $config;
}

/**
 * One-time inference when discovery.json is missing (preserve live sites).
 *
 * @param array<string, mixed> $config
 * @return array<string, mixed>
 */
function birkly_discovery_migrate_from_legacy(array $config): array
{
    require_once __DIR__ . '/ai_presence.php';

    $meta = birkly_ai_presence_load_meta();
    $published = birkly_ai_presence_load_published();
    $version = (int) ($meta['published_version'] ?? 0);

    if ($version >= 1 && birkly_ai_presence_published_snapshot_enabled($published, $meta)) {
        $config['packs']['agent_guide']['enabled'] = true;
    } elseif ($published !== null && !empty($published['publication_settings']['enabled'])) {
        $config['packs']['agent_guide']['enabled'] = true;
    }

    $pub = is_array($published['publication_settings'] ?? null) ? $published['publication_settings'] : [];
    if (!empty($pub['discovery_sitemap']) || !empty($pub['discovery_indexnow'])) {
        $config['packs']['seo']['enabled'] = true;
    }

    $webmcpPath = birkly_project_root() . '/settings/webmcp.json';
    if (is_readable($webmcpPath)) {
        $wm = json_decode((string) file_get_contents($webmcpPath), true);
        if (is_array($wm) && !empty($wm['enabled'])) {
            $config['packs']['webmcp']['enabled'] = true;
        }
    }

    return $config;
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function birkly_discovery_normalize(array $input): array
{
    $defaults = birkly_discovery_defaults();
    $out = array_replace_recursive($defaults, $input);

    foreach (birkly_discovery_pack_ids() as $id) {
        if (!isset($out['packs'][$id]) || !is_array($out['packs'][$id])) {
            $out['packs'][$id] = $defaults['packs'][$id];
        }
        $out['packs'][$id]['enabled'] = !empty($out['packs'][$id]['enabled']);
        $mode = (string) ($out['packs'][$id]['delivery']['mode'] ?? 'auto_inject');
        if (!in_array($mode, ['auto_inject', 'manual', 'download'], true)) {
            $mode = 'auto_inject';
        }
        $out['packs'][$id]['delivery']['mode'] = $mode;
    }

    return $out;
}

/**
 * @param array<string, mixed> $input
 * @return array{ok:bool,config?:array<string,mixed>,error?:string}
 */
function birkly_discovery_save(array $input): array
{
    $config = birkly_discovery_normalize($input);

    $dir = dirname(birkly_discovery_settings_path());
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return ['ok' => false, 'error' => 'Could not create settings directory'];
    }

    $stored = $config;
    $stored['_updated_at'] = date('c');
    $json = json_encode($stored, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return ['ok' => false, 'error' => 'Could not encode config'];
    }

    if (file_put_contents(birkly_discovery_settings_path(), $json . "\n") === false) {
        return ['ok' => false, 'error' => 'Could not write config'];
    }

    birkly_discovery_reset_cache();
    $GLOBALS['__birkly_discovery_config'] = $config;

    return ['ok' => true, 'config' => $config];
}

/**
 * @return array{enabled:bool,delivery:array<string,mixed>}
 */
function birkly_discovery_pack_config(string $packId): array
{
    $config = birkly_discovery_load();
    $pack = $config['packs'][$packId] ?? [];

    return [
        'enabled' => !empty($pack['enabled']),
        'delivery' => is_array($pack['delivery'] ?? null) ? $pack['delivery'] : ['mode' => 'auto_inject'],
    ];
}

function birkly_discovery_pack_enabled(string $packId): bool
{
    return birkly_discovery_pack_config($packId)['enabled'];
}

/**
 * Canonical discovery endpoint URLs (wraps Agent Guide map + SEO additions).
 *
 * @return array<string, ?string>
 */
function birkly_discovery_url_map(): array
{
    require_once __DIR__ . '/ai_presence_discovery.php';
    require_once __DIR__ . '/hosting_urls.php';

    $map = birkly_ai_presence_discovery_url_map();
    $base = birkly_ai_presence_discovery_public_base();
    if ($base === '') {
        $hosting = birkly_hosting_load();
        $base = rtrim((string) ($hosting['public_site_url'] ?? $hosting['cms_base_url'] ?? ''), '/');
    }

    $map['homepage'] = $base !== '' ? $base . '/' : null;
    $map['for_agents_md'] = ($map['for_agents'] ?? null) !== null ? ($map['for_agents'] . '.md') : null;
    $map['for_agents_json'] = $map['agent_guide_json'] ?? null;
    $map['sitemap_xml'] = $map['sitemap'] ?? null;
    $map['robots_txt'] = $map['robots'] ?? null;

    return $map;
}

function birkly_discovery_sitemap_is_live(): bool
{
    require_once __DIR__ . '/site_seo.php';
    require_once __DIR__ . '/ai_presence_discovery.php';

    if (birkly_site_seo_is_published() && birkly_site_seo_surface_enabled('sitemap')) {
        return true;
    }

    $settings = birkly_ai_presence_discovery_publication_settings();

    return birkly_ai_presence_is_publicly_live() && !empty($settings['discovery_sitemap']);
}

/**
 * @return list<array{id:string,pack:string,label:string,status:string,detail:string}>
 */
function birkly_discovery_verify_checks(): array
{
    require_once __DIR__ . '/ai_presence_discovery.php';
    require_once __DIR__ . '/site_seo.php';

    $checks = [];
    $agDiscovery = birkly_ai_presence_discovery_status();
    foreach ($agDiscovery['checks'] ?? [] as $check) {
        if (!is_array($check)) {
            continue;
        }
        $check['pack'] = 'agent_guide';
        $checks[] = $check;
    }

    if (birkly_site_seo_is_published()) {
        $checks[] = [
            'id' => 'seo_meta_inject',
            'pack' => 'seo',
            'label' => 'SEO meta injection enabled',
            'status' => birkly_site_seo_surface_enabled('meta_tags') ? 'pass' : 'warn',
            'detail' => birkly_site_seo_surface_enabled('meta_tags')
                ? 'Title and description tags inject on project pages when missing.'
                : 'Meta tag injection is off in SEO delivery surfaces.',
        ];
        $checks[] = [
            'id' => 'seo_sitemap',
            'pack' => 'seo',
            'label' => 'Sitemap published',
            'status' => birkly_discovery_sitemap_is_live() ? 'pass' : 'fail',
            'detail' => birkly_discovery_sitemap_is_live()
                ? '/sitemap.xml is live when SEO or Agent Guide sitemap is on.'
                : 'Enable sitemap in SEO or Agent Guide delivery settings.',
        ];
    } else {
        $checks[] = [
            'id' => 'seo_published',
            'pack' => 'seo',
            'label' => 'SEO pack published',
            'status' => 'neutral',
            'detail' => 'Publish the SEO pack to activate meta tags and sitemap.',
        ];
    }

    if (birkly_discovery_pack_enabled('webmcp')) {
        require_once __DIR__ . '/webmcp.php';
        $checks[] = [
            'id' => 'webmcp_enabled',
            'pack' => 'webmcp',
            'label' => 'WebMCP active',
            'status' => birkly_webmcp_is_enabled() ? 'pass' : 'warn',
            'detail' => birkly_webmcp_is_enabled()
                ? 'WebMCP runtime injects on Birkly-hosted project pages when auto-inject is on.'
                : 'WebMCP pack is on — enable at least one tool to go live.',
        ];
    } else {
        $checks[] = [
            'id' => 'webmcp_enabled',
            'pack' => 'webmcp',
            'label' => 'WebMCP',
            'status' => 'neutral',
            'detail' => 'Enable the WebMCP pack and turn on tools to expose visitor browser actions.',
        ];
    }

    return $checks;
}

/**
 * @return array{ok:bool,checks:list<array<string,mixed>>,ran_at:string}
 */
function birkly_discovery_run_verify(): array
{
    $checks = birkly_discovery_verify_checks();
    $ranAt = date('c');
    $config = birkly_discovery_load();
    $config['verify_last_run'] = $ranAt;
    birkly_discovery_save($config);

    return ['ok' => true, 'checks' => $checks, 'ran_at' => $ranAt];
}

/**
 * @param array<string, mixed> $patch Partial pack update: enabled, delivery.mode, …
 * @return array{ok:bool,config?:array<string,mixed>,error?:string}
 */
function birkly_discovery_save_pack(string $packId, array $patch): array
{
    if (!in_array($packId, birkly_discovery_pack_ids(), true)) {
        return ['ok' => false, 'error' => 'unknown_pack'];
    }

    $config = birkly_discovery_load();
    $pack = is_array($config['packs'][$packId] ?? null) ? $config['packs'][$packId] : [];
    if (array_key_exists('enabled', $patch)) {
        $pack['enabled'] = (bool) $patch['enabled'];
    }
    if (isset($patch['delivery']) && is_array($patch['delivery'])) {
        $pack['delivery'] = array_merge(
            is_array($pack['delivery'] ?? null) ? $pack['delivery'] : [],
            $patch['delivery']
        );
    }
    $config['packs'][$packId] = $pack;

    return birkly_discovery_save($config);
}

/**
 * Aggregated pack status for the Discovery admin strip.
 *
 * @return array<string, mixed>
 */
function birkly_discovery_status(): array
{
    require_once __DIR__ . '/ai_presence.php';
    require_once __DIR__ . '/webmcp.php';
    require_once __DIR__ . '/site_seo.php';
    require_once __DIR__ . '/hosting_urls.php';

    $discovery = birkly_discovery_load();
    $hosting = birkly_hosting_load();
    $mode = (string) ($hosting['public_site_mode'] ?? 'none');

    $agMeta = birkly_ai_presence_load_meta();
    $agPublished = birkly_ai_presence_load_published();
    $agLive = birkly_ai_presence_is_publicly_live($agPublished, $agMeta);
    $agVersion = (int) ($agMeta['published_version'] ?? 0);

    $seo = birkly_site_seo_load();
    $webmcp = birkly_webmcp_load();

    $packs = [];
    foreach (birkly_discovery_pack_ids() as $id) {
        $master = !empty($discovery['packs'][$id]['enabled']);
        $live = false;
        $label = 'off';
        $detail = '';

        if ($id === 'seo') {
            $live = $master && !empty($seo['published']);
            $label = !$master ? 'off' : ($live ? 'live' : 'on');
            $detail = $live ? 'SEO pack published' : ($master ? 'Enabled — not published' : 'Disabled');
        } elseif ($id === 'agent_guide') {
            $live = $master && $agLive;
            $label = !$master ? 'off' : ($live ? 'live v' . $agVersion : 'on');
            $detail = $live
                ? 'Agent Guide v' . $agVersion . ' is public'
                : ($master ? 'Enabled — publish to go live' : 'Disabled');
        } elseif ($id === 'webmcp') {
            $live = $master && !empty($webmcp['enabled']);
            $label = !$master ? 'off' : ($live ? 'live' : 'on');
            $detail = $live ? 'WebMCP active on public site' : ($master ? 'Enabled — turn on tools below' : 'Disabled');
        }

        $packs[$id] = [
            'enabled' => $master,
            'live' => $live,
            'status_label' => $label,
            'status_detail' => $detail,
            'delivery' => $discovery['packs'][$id]['delivery'] ?? ['mode' => 'auto_inject'],
        ];
    }

    $verify = birkly_discovery_verify_checks();

    $seoConfig = birkly_site_seo_load();

    return [
        'packs' => $packs,
        'hosting' => [
            'public_site_mode' => $mode,
            'public_site_url' => (string) ($hosting['public_site_url'] ?? ''),
            'cms_base_url' => (string) ($hosting['cms_base_url'] ?? ''),
        ],
        'discovery' => $discovery,
        'seo' => $seoConfig,
        'verify_checks' => $verify,
        'verify_last_run' => $discovery['verify_last_run'] ?? null,
    ];
}
