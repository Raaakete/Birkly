<?php
/**
 * Storefront Plugin Script Shelf (P82)
 *
 * Aggregates plugin.json storefront.scripts[] entries for active plugins.
 * Core does not hardcode plugin behavior — only reads declared manifests.
 *
 * One-release back-compat: legacy commerce storefront path when manifest missing.
 */

/**
 * Ensure plugin metadata is available for shelf reads.
 */
function birkly_bootstrap_plugins_for_storefront(): void
{
    if (!function_exists('get_all_plugins')) {
        if (file_exists(dirname(__DIR__) . '/public_bootstrap.php')) {
            require_once dirname(__DIR__) . '/public_bootstrap.php';
        } elseif (file_exists(dirname(__DIR__) . '/plugins.php')) {
            require_once dirname(__DIR__) . '/plugins.php';
        }
    }

    if (!isset($GLOBALS['haru_plugins_loaded']) && function_exists('load_plugins')) {
        load_plugins();
        $GLOBALS['haru_plugins_loaded'] = true;
    }
}

/**
 * Whether a plugin is active for storefront shelf purposes.
 */
function birkly_is_storefront_plugin_active(string $plugin_id): bool
{
    if ($plugin_id === '' || !preg_match('/^[a-z0-9_-]+$/', $plugin_id)) {
        return false;
    }

    if (function_exists('birkly_is_plugin_active')) {
        return birkly_is_plugin_active($plugin_id);
    }

    if (function_exists('get_plugin_state')) {
        return (bool) get_plugin_state($plugin_id);
    }

    $all = function_exists('get_all_plugins') ? get_all_plugins() : [];
    return !empty($all[$plugin_id]['active']);
}

/**
 * Public URL path for a plugin storefront asset (relative to CMS origin).
 */
function birkly_storefront_script_public_path(string $plugin_id, string $relative_path): string
{
    $relative_path = ltrim(str_replace('\\', '/', $relative_path), '/');
    if ($relative_path === '' || str_contains($relative_path, '..')) {
        return '';
    }

    return '/plugins/' . $plugin_id . '/' . implode('/', array_map('rawurlencode', explode('/', $relative_path)));
}

/**
 * Parse storefront.scripts[] from a plugin's plugin.json on disk.
 *
 * @return list<array<string, mixed>>
 */
function birkly_read_plugin_storefront_manifest(string $plugin_id, array $plugin): array
{
    $entries = [];
    $manifest = [];

    if (isset($plugin['storefront']) && is_array($plugin['storefront'])) {
        $manifest = $plugin['storefront'];
    } else {
        $jsonPath = rtrim((string) ($plugin['path'] ?? ''), '/') . '/plugin.json';
        if ($jsonPath !== '/plugin.json' && is_file($jsonPath)) {
            $decoded = json_decode((string) file_get_contents($jsonPath), true);
            if (is_array($decoded['storefront'] ?? null)) {
                $manifest = $decoded['storefront'];
            }
        }
    }

    $scripts = $manifest['scripts'] ?? [];
    if (!is_array($scripts)) {
        return [];
    }

    $pluginRoot = rtrim((string) ($plugin['path'] ?? ''), '/');
    $version = (string) ($plugin['version'] ?? '');

    foreach ($scripts as $script) {
        if (!is_array($script)) {
            continue;
        }

        $path = trim((string) ($script['path'] ?? ''));
        if ($path === '' || str_contains($path, '..')) {
            continue;
        }

        if ($pluginRoot !== '') {
            $fullPath = $pluginRoot . '/' . ltrim(str_replace('\\', '/', $path), '/');
            if (!is_file($fullPath)) {
                continue;
            }
        }

        $publicPath = birkly_storefront_script_public_path($plugin_id, $path);
        if ($publicPath === '') {
            continue;
        }

        $provides = trim((string) ($script['provides'] ?? ''));
        $load = trim((string) ($script['load'] ?? 'always'));
        if ($load === '') {
            $load = 'always';
        }

        $entries[] = [
            'plugin_id' => $plugin_id,
            'path' => $path,
            'url' => $publicPath,
            'version' => $version,
            'provides' => $provides,
            'load' => $load,
        ];
    }

    return $entries;
}

/**
 * One-release back-compat for plugins that expose storefront_script via legacy config
 * but have not yet migrated to plugin.json storefront.scripts[].
 *
 * @param array<string, true> $coveredProvides globals already declared in manifest
 * @return list<array<string, mixed>>
 */
function birkly_storefront_legacy_manifest_entries(array $coveredProvides): array
{
    $legacy = [];

    foreach (['commerce' => [
        'path' => 'assets/commerce-storefront.js',
        'provides' => 'BirklyCommerce',
    ]] as $pluginId => $spec) {
        if (!birkly_is_storefront_plugin_active($pluginId)) {
            continue;
        }

        $all = function_exists('get_all_plugins') ? get_all_plugins() : [];
        $plugin = $all[$pluginId] ?? null;
        if (!is_array($plugin)) {
            continue;
        }

        $manifestEntries = birkly_read_plugin_storefront_manifest($pluginId, $plugin);
        if ($manifestEntries !== []) {
            continue;
        }

        $provides = (string) ($spec['provides'] ?? '');
        if ($provides !== '' && isset($coveredProvides[$provides])) {
            continue;
        }

        $path = (string) ($spec['path'] ?? '');
        $pluginRoot = rtrim((string) ($plugin['path'] ?? ''), '/');
        if ($pluginRoot === '' || !is_file($pluginRoot . '/' . $path)) {
            continue;
        }

        $legacy[] = [
            'plugin_id' => $pluginId,
            'path' => $path,
            'url' => birkly_storefront_script_public_path($pluginId, $path),
            'version' => (string) ($plugin['version'] ?? ''),
            'provides' => $provides,
            'load' => 'always',
            'legacy' => true,
        ];
    }

    return $legacy;
}

/**
 * Aggregate storefront scripts for all active plugins.
 *
 * @return list<array<string, mixed>>
 */
function birkly_get_storefront_scripts(): array
{
    birkly_bootstrap_plugins_for_storefront();

    $scripts = [];
    $coveredProvides = [];

    $all = function_exists('get_all_plugins') ? get_all_plugins() : [];
    ksort($all);

    foreach ($all as $pluginId => $plugin) {
        if (!is_array($plugin) || !birkly_is_storefront_plugin_active((string) $pluginId)) {
            continue;
        }

        foreach (birkly_read_plugin_storefront_manifest((string) $pluginId, $plugin) as $entry) {
            $provides = (string) ($entry['provides'] ?? '');
            if ($provides !== '') {
                if (isset($coveredProvides[$provides])) {
                    continue;
                }
                $coveredProvides[$provides] = true;
            }
            $scripts[] = $entry;
        }
    }

    foreach (birkly_storefront_legacy_manifest_entries($coveredProvides) as $entry) {
        $provides = (string) ($entry['provides'] ?? '');
        if ($provides !== '' && isset($coveredProvides[$provides])) {
            continue;
        }
        if ($provides !== '') {
            $coveredProvides[$provides] = true;
        }
        $scripts[] = $entry;
    }

    if (function_exists('apply_filters')) {
        $filtered = apply_filters('birkly_storefront_scripts', $scripts);
        if (is_array($filtered)) {
            $scripts = $filtered;
        }
    }

    return array_values($scripts);
}

/**
 * Deep-merge associative arrays for site_client_config contributions.
 *
 * @param array<string, mixed> $base
 * @param array<string, mixed> $chunk
 * @return array<string, mixed>
 */
function birkly_merge_site_client_config(array $base, array $chunk): array
{
    foreach ($chunk as $key => $value) {
        if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
            $base[$key] = birkly_merge_site_client_config($base[$key], $value);
        } else {
            $base[$key] = $value;
        }
    }

    return $base;
}

/**
 * Aggregate site client configuration from active plugins via filter.
 *
 * @return array<string, mixed>
 */
function birkly_get_site_client_config(): array
{
    birkly_bootstrap_plugins_for_storefront();

    if (function_exists('birkly_public_load_active_plugins')) {
        require_once dirname(__DIR__) . '/public_bootstrap.php';
        birkly_public_load_active_plugins();
    }

    $config = [];
    if (function_exists('apply_filters')) {
        $filtered = apply_filters('birkly_site_client_config', $config);
        if (is_array($filtered)) {
            $config = $filtered;
        }
    }

    return $config;
}
