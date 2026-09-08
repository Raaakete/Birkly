<?php
/**
 * WebMCP configuration store (P60-M3).
 * ------------------------------------
 * WebMCP lets AI helpers in a visitor's browser call clear "tools" a page
 * publishes (submit a form, add to cart, RSVP, …) instead of guessing from the
 * DOM. This file owns the *enablement* config only — the runtime lives in
 * `birkly-webmcp.js`, and the visitor-facing tools run on the page origin.
 *
 * Ground rules (see internal P60-M0 spec):
 *   - Default OFF. Nothing registers until the owner turns it on.
 *   - Public projection never leaks secrets — only `enabled`, the enabled tool
 *     ids, and the `form_allowlist`.
 *   - WebMCP is public-site actuation only. It never exposes admin/CMS writes.
 *
 * Storage: settings/webmcp.json (sibling to hosting.json / ai_presence).
 */

declare(strict_types=1);

require_once __DIR__ . '/birkly_paths.php';

/**
 * Ensure active plugins are loaded so they can register WebMCP tools via filter.
 */
function birkly_webmcp_ensure_plugins_loaded(): void
{
    if (!function_exists('load_plugins')) {
        $pluginsPath = __DIR__ . '/plugins.php';
        if (is_readable($pluginsPath)) {
            require_once $pluginsPath;
        }
    }
    if (function_exists('load_plugins')) {
        load_plugins();
    }
}

/**
 * Core WebMCP tools shipped with Birkly (always available when WebMCP is on).
 *
 * Plugin packs register additional tools via `birkly_webmcp_register_tools()` or
 * the `webmcp_tool_catalog` filter from their `plugin.php` when active.
 *
 * @return array<int, array{id:string,pack:string,kind:string,mutating:bool,consent:string,default:bool,plugin_id?:string,label?:string,description?:string}>
 */
function birkly_webmcp_core_tool_catalog(): array
{
    return [
        ['id' => 'birkly_get_page_context',        'pack' => 'core', 'kind' => 'read',  'mutating' => false, 'consent' => 'none',    'default' => true, 'label' => 'Read this page',            'description' => 'Lets an AI helper see the page title, address and language.'],
        ['id' => 'birkly_list_published_entries',  'pack' => 'core', 'kind' => 'read',  'mutating' => false, 'consent' => 'none',    'default' => true, 'label' => 'List published items',      'description' => 'Lets an AI helper read the published items a page shows (e.g. blog posts).'],
        ['id' => 'birkly_get_published_entry',     'pack' => 'core', 'kind' => 'read',  'mutating' => false, 'consent' => 'none',    'default' => true, 'label' => 'Read one published item',   'description' => 'Lets an AI helper open a single published item by its address.'],
        ['id' => 'birkly_describe_form',           'pack' => 'core', 'kind' => 'read',  'mutating' => false, 'consent' => 'none',    'default' => true, 'label' => 'Understand a form',         'description' => 'Lets an AI helper see a form’s fields so it can fill them correctly.'],
        ['id' => 'birkly_submit_public_form',      'pack' => 'core', 'kind' => 'write', 'mutating' => true,  'consent' => 'confirm', 'default' => true, 'label' => 'Submit a form',            'description' => 'Lets an AI helper submit an allowed form — always after the visitor confirms.'],
        ['id' => 'birkly_set_language',            'pack' => 'core', 'kind' => 'write', 'mutating' => true,  'consent' => 'none',    'default' => true, 'label' => 'Switch language',          'description' => 'Lets an AI helper change the site language, only where a language picker exists.'],
    ];
}

/**
 * Normalize one catalog entry from core or a plugin registration.
 *
 * @param array<string, mixed> $tool
 * @return array{id:string,pack:string,kind:string,mutating:bool,consent:string,default:bool,plugin_id?:string,label?:string,description?:string}|null
 */
function birkly_webmcp_normalize_tool_entry(array $tool): ?array
{
    $id = trim((string) ($tool['id'] ?? ''));
    if ($id === '' || !preg_match('/^[a-z][a-z0-9_]*$/', $id)) {
        return null;
    }

    $pack = trim((string) ($tool['pack'] ?? ''));
    if ($pack === '') {
        $pack = trim((string) ($tool['plugin_id'] ?? 'plugin'));
    }

    $kind = (string) ($tool['kind'] ?? 'read');
    if (!in_array($kind, ['read', 'write'], true)) {
        $kind = 'read';
    }

    $consent = (string) ($tool['consent'] ?? 'none');
    if (!in_array($consent, ['none', 'confirm', 'pay', 'login'], true)) {
        $consent = $kind === 'write' ? 'confirm' : 'none';
    }

    $normalized = [
        'id' => $id,
        'pack' => $pack,
        'kind' => $kind,
        'mutating' => (bool) ($tool['mutating'] ?? ($kind === 'write')),
        'consent' => $consent,
        'default' => (bool) ($tool['default'] ?? false),
    ];

    $pluginId = trim((string) ($tool['plugin_id'] ?? ''));
    if ($pluginId !== '' && $pack !== 'core') {
        $normalized['plugin_id'] = $pluginId;
    }

    $label = trim((string) ($tool['label'] ?? ''));
    if ($label !== '') {
        $normalized['label'] = $label;
    }

    $description = trim((string) ($tool['description'] ?? ''));
    if ($description !== '') {
        $normalized['description'] = $description;
    }

    return $normalized;
}

/**
 * Register WebMCP tools from an active plugin.
 *
 * Call from `plugin.php` on load. Tools from inactive or uninstalled plugins are
 * never included in the catalog and are pruned from saved config on normalize.
 *
 * @param string $pluginId Plugin folder id (e.g. `commerce`).
 * @param list<array<string, mixed>> $tools Tool definitions (id, kind, mutating, consent, default; pack optional).
 */
function birkly_webmcp_register_tools(string $pluginId, array $tools): void
{
    if ($pluginId === '' || $pluginId === 'core') {
        return;
    }

    if (!function_exists('add_filter')) {
        birkly_webmcp_ensure_plugins_loaded();
    }
    if (!function_exists('add_filter')) {
        return;
    }

    add_filter('webmcp_tool_catalog', static function (array $catalog) use ($pluginId, $tools): array {
        foreach ($tools as $tool) {
            if (!is_array($tool)) {
                continue;
            }
            $tool['plugin_id'] = $pluginId;
            if (!isset($tool['pack']) || trim((string) $tool['pack']) === '') {
                $tool['pack'] = $pluginId;
            }
            $entry = birkly_webmcp_normalize_tool_entry($tool);
            if ($entry !== null) {
                $catalog[] = $entry;
            }
        }

        return $catalog;
    });
}

/**
 * Canonical catalog of core + active-plugin WebMCP tools.
 *
 * `default` is the value used when the master switch is on and the tool has no
 * explicit entry in the saved config: core tools default on, plugin packs
 * default off until the owner enables them.
 *
 * @return array<int, array{id:string,pack:string,kind:string,mutating:bool,consent:string,default:bool,plugin_id?:string,label?:string,description?:string}>
 */
function birkly_webmcp_tool_catalog(): array
{
    if (!empty($GLOBALS['__birkly_webmcp_catalog_reset'])) {
        unset($GLOBALS['__birkly_webmcp_catalog_cache']);
        unset($GLOBALS['__birkly_webmcp_catalog_reset']);
    }

    if (isset($GLOBALS['__birkly_webmcp_catalog_cache']) && is_array($GLOBALS['__birkly_webmcp_catalog_cache'])) {
        return $GLOBALS['__birkly_webmcp_catalog_cache'];
    }

    birkly_webmcp_ensure_plugins_loaded();

    $merged = [];
    $seen = [];
    foreach (birkly_webmcp_core_tool_catalog() as $tool) {
        $entry = birkly_webmcp_normalize_tool_entry($tool);
        if ($entry === null || isset($seen[$entry['id']])) {
            continue;
        }
        $seen[$entry['id']] = true;
        $merged[] = $entry;
    }

    $pluginTools = function_exists('apply_filters')
        ? apply_filters('webmcp_tool_catalog', [])
        : [];

    if (is_array($pluginTools)) {
        foreach ($pluginTools as $tool) {
            if (!is_array($tool)) {
                continue;
            }
            $entry = birkly_webmcp_normalize_tool_entry($tool);
            if ($entry === null || isset($seen[$entry['id']])) {
                continue;
            }
            $seen[$entry['id']] = true;
            $merged[] = $entry;
        }
    }

    $GLOBALS['__birkly_webmcp_catalog_cache'] = $merged;
    return $merged;
}

/**
 * Reset the in-request tool catalog cache (tests only).
 */
function birkly_webmcp_reset_catalog_cache(): void
{
    $GLOBALS['__birkly_webmcp_catalog_reset'] = true;
    unset($GLOBALS['__birkly_webmcp_catalog_cache']);
}

/**
 * @return list<string>
 */
function birkly_webmcp_tool_ids(): array
{
    return array_map(static fn (array $t): string => $t['id'], birkly_webmcp_tool_catalog());
}

/**
 * @return array{enabled:bool,tools:array<string,bool>,form_allowlist:list<string>}
 */
function birkly_webmcp_defaults(): array
{
    $tools = [];
    foreach (birkly_webmcp_tool_catalog() as $tool) {
        $tools[$tool['id']] = (bool) $tool['default'];
    }

    return [
        'enabled' => false,
        'tools' => $tools,
        'form_allowlist' => [],
    ];
}

function birkly_webmcp_settings_path(): string
{
    return birkly_project_root() . '/settings/webmcp.json';
}

function birkly_webmcp_reset_cache(): void
{
    unset($GLOBALS['__birkly_webmcp_config']);
}

/**
 * Load + normalize the saved config (merged over defaults).
 *
 * @return array{enabled:bool,tools:array<string,bool>,form_allowlist:list<string>}
 */
function birkly_webmcp_load(): array
{
    if (isset($GLOBALS['__birkly_webmcp_config']) && is_array($GLOBALS['__birkly_webmcp_config'])) {
        return $GLOBALS['__birkly_webmcp_config'];
    }

    $config = birkly_webmcp_defaults();
    $path = birkly_webmcp_settings_path();
    if (is_readable($path)) {
        $raw = file_get_contents($path);
        if ($raw !== false) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $config = birkly_webmcp_normalize($decoded);
            }
        }
    }

    $GLOBALS['__birkly_webmcp_config'] = $config;
    return $config;
}

/**
 * Normalize an arbitrary (possibly partial / untrusted) config array against the
 * defaults + known tool catalog. Unknown tool keys are dropped.
 *
 * @param array<string,mixed> $input
 * @return array{enabled:bool,tools:array<string,bool>,form_allowlist:list<string>}
 */
function birkly_webmcp_normalize(array $input): array
{
    $defaults = birkly_webmcp_defaults();

    $enabled = array_key_exists('enabled', $input)
        ? (bool) $input['enabled']
        : $defaults['enabled'];

    $tools = $defaults['tools'];
    if (isset($input['tools']) && is_array($input['tools'])) {
        foreach ($tools as $id => $_) {
            if (array_key_exists($id, $input['tools'])) {
                $tools[$id] = (bool) $input['tools'][$id];
            }
        }
    }

    $allowlist = [];
    if (isset($input['form_allowlist']) && is_array($input['form_allowlist'])) {
        foreach ($input['form_allowlist'] as $slug) {
            if (!is_string($slug)) {
                continue;
            }
            $slug = strtolower(trim($slug));
            if ($slug === '' || !preg_match('/^[a-z0-9_-]+$/', $slug)) {
                continue;
            }
            $allowlist[$slug] = true;
        }
    }

    return [
        'enabled' => $enabled,
        'tools' => $tools,
        'form_allowlist' => array_values(array_keys($allowlist)),
    ];
}

/**
 * Persist a normalized config.
 *
 * @param array<string,mixed> $input
 * @return array{ok:bool,config?:array<string,mixed>,error?:string}
 */
function birkly_webmcp_save(array $input): array
{
    $config = birkly_webmcp_normalize($input);

    $dir = dirname(birkly_webmcp_settings_path());
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return ['ok' => false, 'error' => 'Could not create settings directory'];
    }

    $stored = $config;
    $stored['_updated_at'] = date('c');
    $json = json_encode($stored, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return ['ok' => false, 'error' => 'Could not encode config'];
    }

    if (file_put_contents(birkly_webmcp_settings_path(), $json . "\n") === false) {
        return ['ok' => false, 'error' => 'Could not write config'];
    }

    birkly_webmcp_reset_cache();
    $GLOBALS['__birkly_webmcp_config'] = $config;

    return ['ok' => true, 'config' => $config];
}

/**
 * Resolved list of enabled tool ids (respecting the master switch).
 * When the master switch is off, returns an empty list.
 *
 * @return list<string>
 */
function birkly_webmcp_enabled_tool_ids(): array
{
    $config = birkly_webmcp_load();
    if (!$config['enabled']) {
        return [];
    }

    $ids = [];
    foreach ($config['tools'] as $id => $on) {
        if ($on) {
            $ids[] = $id;
        }
    }
    return $ids;
}

function birkly_webmcp_is_enabled(): bool
{
    if (!function_exists('birkly_discovery_pack_enabled')) {
        require_once __DIR__ . '/discovery.php';
    }
    if (!birkly_discovery_pack_enabled('webmcp')) {
        return false;
    }

    return birkly_webmcp_load()['enabled'] === true;
}

/**
 * Public-form collections eligible for the submit-tool allowlist — i.e. those
 * whose `entry_method` is `public_api`. Used by the Tools admin UI to offer a
 * concrete allowlist rather than free-text.
 *
 * @return list<array{slug:string,name:string}>
 */
function birkly_webmcp_public_form_collections(): array
{
    $contentRoot = birkly_project_root() . '/content/collections';
    $out = [];
    if (!is_dir($contentRoot)) {
        return $out;
    }

    foreach (scandir($contentRoot) ?: [] as $dir) {
        if ($dir === '.' || $dir === '..' || !is_dir($contentRoot . '/' . $dir)) {
            continue;
        }
        $metaPath = $contentRoot . '/' . $dir . '/collection.json';
        if (!is_readable($metaPath)) {
            continue;
        }
        $raw = file_get_contents($metaPath);
        if ($raw === false) {
            continue;
        }
        $meta = json_decode($raw, true);
        if (!is_array($meta)) {
            continue;
        }
        if (($meta['entry_method'] ?? 'admin_only') !== 'public_api') {
            continue;
        }
        $slug = (string) ($meta['slug'] ?? $dir);
        $out[] = [
            'slug' => $slug,
            'name' => (string) ($meta['title'] ?? $meta['name'] ?? $slug),
        ];
    }

    usort($out, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

    return $out;
}

/**
 * Public, secret-free projection of the config for the browser runtime.
 * Returns `{enabled:false}` when the master switch is off so callers can
 * treat an off site as indistinguishable from one that never heard of WebMCP.
 *
 * @return array{enabled:bool,tools?:list<string>,form_allowlist?:list<string>}
 */
function birkly_webmcp_public_config(): array
{
    $config = birkly_webmcp_load();
    if (!$config['enabled']) {
        return ['enabled' => false];
    }

    return [
        'enabled' => true,
        'tools' => birkly_webmcp_enabled_tool_ids(),
        'form_allowlist' => $config['form_allowlist'],
        'runtime_packs' => birkly_webmcp_public_runtime_packs(),
    ];
}

/**
 * Register a browser runtime pack for WebMCP (P83-M4).
 *
 * @param array{
 *   pack: string,
 *   label?: string,
 *   note?: string,
 *   global?: string,
 *   surface?: string,
 *   script?: string,
 *   tools?: list<string>
 * } $config
 */
function birkly_webmcp_register_runtime_pack(string $pluginId, array $config): void
{
    if ($pluginId === '' || $pluginId === 'core') {
        return;
    }

    $pack = trim((string) ($config['pack'] ?? $pluginId));
    if ($pack === '' || !preg_match('/^[a-z][a-z0-9_-]*$/', $pack)) {
        return;
    }

    if (!function_exists('add_filter')) {
        birkly_webmcp_ensure_plugins_loaded();
    }
    if (!function_exists('add_filter')) {
        return;
    }

    add_filter('webmcp_runtime_packs', static function (array $packs) use ($pluginId, $config, $pack): array {
        if (!function_exists('birkly_is_plugin_active') || !birkly_is_plugin_active($pluginId)) {
            return $packs;
        }

        $tools = [];
        foreach (($config['tools'] ?? []) as $toolId) {
            $toolId = trim((string) $toolId);
            if ($toolId !== '' && preg_match('/^[a-z][a-z0-9_]*$/', $toolId)) {
                $tools[] = $toolId;
            }
        }

        $entry = [
            'pack' => $pack,
            'plugin_id' => $pluginId,
            'label' => trim((string) ($config['label'] ?? '')),
            'note' => trim((string) ($config['note'] ?? '')),
            'global' => trim((string) ($config['global'] ?? '')),
            'surface' => trim((string) ($config['surface'] ?? '')),
            'script' => trim((string) ($config['script'] ?? '')),
            'tools' => $tools,
        ];

        $packs[$pack] = $entry;
        return $packs;
    });
}

/**
 * @return array<string, array<string, mixed>>
 */
function birkly_webmcp_runtime_packs(): array
{
    birkly_webmcp_ensure_plugins_loaded();

    $packs = function_exists('apply_filters')
        ? apply_filters('webmcp_runtime_packs', [])
        : [];

    if (!is_array($packs)) {
        return [];
    }

    ksort($packs);
    return $packs;
}

/**
 * Secret-free runtime pack descriptors for the browser (enabled tools only).
 *
 * @return list<array<string, mixed>>
 */
function birkly_webmcp_public_runtime_packs(): array
{
    if (!birkly_webmcp_is_enabled()) {
        return [];
    }

    $config = birkly_webmcp_load();
    $enabled = [];
    foreach ($config['tools'] as $id => $on) {
        if ($on) {
            $enabled[$id] = true;
        }
    }

    $out = [];
    foreach (birkly_webmcp_runtime_packs() as $pack) {
        if (!is_array($pack)) {
            continue;
        }
        $toolIds = [];
        foreach (($pack['tools'] ?? []) as $toolId) {
            if (isset($enabled[(string) $toolId])) {
                $toolIds[] = (string) $toolId;
            }
        }
        if ($toolIds === []) {
            continue;
        }

        $entry = [
            'pack' => (string) ($pack['pack'] ?? ''),
            'tools' => $toolIds,
        ];
        if (!empty($pack['global'])) {
            $entry['global'] = (string) $pack['global'];
        }
        if (!empty($pack['surface'])) {
            $entry['surface'] = (string) $pack['surface'];
        }
        $out[] = $entry;
    }

    return $out;
}

/**
 * Admin/API metadata for runtime pack grouping labels.
 *
 * @return list<array{pack:string,label:string,note:string,plugin_id?:string}>
 */
function birkly_webmcp_api_runtime_packs(): array
{
    $out = [];
    foreach (birkly_webmcp_runtime_packs() as $pack) {
        if (!is_array($pack)) {
            continue;
        }
        $id = (string) ($pack['pack'] ?? '');
        if ($id === '') {
            continue;
        }
        $entry = [
            'pack' => $id,
            'label' => (string) (($pack['label'] ?? '') !== '' ? $pack['label'] : $id),
            'note' => (string) ($pack['note'] ?? ''),
        ];
        if (!empty($pack['plugin_id'])) {
            $entry['plugin_id'] = (string) $pack['plugin_id'];
        }
        $out[] = $entry;
    }

    return $out;
}

/**
 * Render deferred script tags for plugin WebMCP runtime packs.
 */
function birkly_webmcp_render_pack_script_tags(): string
{
    if (!birkly_webmcp_is_enabled()) {
        return '';
    }

    $publicPacks = birkly_webmcp_public_runtime_packs();
    if ($publicPacks === []) {
        return '';
    }

    $runtimeByPack = [];
    foreach (birkly_webmcp_runtime_packs() as $pack) {
        if (!is_array($pack)) {
            continue;
        }
        $runtimeByPack[(string) ($pack['pack'] ?? '')] = $pack;
    }

    $html = '';
    $seen = [];
    foreach ($publicPacks as $pack) {
        $id = (string) ($pack['pack'] ?? '');
        if ($id === '' || isset($seen[$id])) {
            continue;
        }
        $meta = $runtimeByPack[$id] ?? null;
        if (!is_array($meta)) {
            continue;
        }
        $script = trim((string) ($meta['script'] ?? ''));
        if ($script === '' || !str_starts_with($script, '/plugins/')) {
            continue;
        }
        $seen[$id] = true;
        $src = function_exists('birkly_url') ? birkly_url($script) : $script;
        $html .= '<script src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" defer></script>' . "\n";
    }

    return $html;
}
