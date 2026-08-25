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
        $config = birkly_discovery_migrate_from_legacy($config);
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

    if ($version >= 1 && birkly_ai_presence_is_publicly_live($published, $meta)) {
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

    $verify = [];
    if (function_exists('birkly_ai_presence_discovery_status')) {
        require_once __DIR__ . '/ai_presence_discovery.php';
        $agDiscovery = birkly_ai_presence_discovery_status();
        foreach ($agDiscovery['checks'] ?? [] as $check) {
            if (!is_array($check)) {
                continue;
            }
            $check['pack'] = 'agent_guide';
            $verify[] = $check;
        }
    }

    return [
        'packs' => $packs,
        'hosting' => [
            'public_site_mode' => $mode,
            'public_site_url' => (string) ($hosting['public_site_url'] ?? ''),
            'cms_base_url' => (string) ($hosting['cms_base_url'] ?? ''),
        ],
        'discovery' => $discovery,
        'verify_checks' => $verify,
    ];
}
