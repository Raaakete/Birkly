<?php
/**
 * Admin Plugin Asset Shelf (P83-M1)
 *
 * Aggregates plugin.json admin.scripts[] entries for active plugins.
 * Core does not hardcode plugin admin JS — only reads declared manifests.
 */

/**
 * Ensure plugin metadata is available for admin shelf reads.
 */
function birkly_bootstrap_plugins_for_admin_assets(): void
{
    if (function_exists('birkly_bootstrap_plugins_for_storefront')) {
        birkly_bootstrap_plugins_for_storefront();
        return;
    }

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
 * Whether a plugin is active for admin asset shelf purposes.
 */
function birkly_is_admin_asset_plugin_active(string $plugin_id): bool
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
 * Public URL path for a plugin admin asset (relative to CMS origin).
 */
function birkly_admin_script_public_path(string $plugin_id, string $relative_path): string
{
    $relative_path = ltrim(str_replace('\\', '/', $relative_path), '/');
    if ($relative_path === '' || str_contains($relative_path, '..')) {
        return '';
    }

    return '/plugins/' . $plugin_id . '/' . implode('/', array_map('rawurlencode', explode('/', $relative_path)));
}

/**
 * Parse admin.scripts[] from a plugin's plugin.json on disk.
 *
 * @return list<array<string, mixed>>
 */
function birkly_read_plugin_admin_manifest(string $plugin_id, array $plugin): array
{
    $manifest = [];

    if (isset($plugin['admin']) && is_array($plugin['admin'])) {
        $manifest = $plugin['admin'];
    } else {
        $jsonPath = rtrim((string) ($plugin['path'] ?? ''), '/') . '/plugin.json';
        if ($jsonPath !== '/plugin.json' && is_file($jsonPath)) {
            $decoded = json_decode((string) file_get_contents($jsonPath), true);
            if (is_array($decoded['admin'] ?? null)) {
                $manifest = $decoded['admin'];
            }
        }
    }

    $scripts = $manifest['scripts'] ?? [];
    if (!is_array($scripts)) {
        return [];
    }

    $pluginRoot = rtrim((string) ($plugin['path'] ?? ''), '/');
    $version = (string) ($plugin['version'] ?? '');
    $entries = [];

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

        $publicPath = birkly_admin_script_public_path($plugin_id, $path);
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
 * Whether a script entry should load on the current admin page.
 *
 * @param array<string, mixed> $entry
 */
function birkly_admin_script_matches_page(array $entry, ?string $page): bool
{
    $load = trim((string) ($entry['load'] ?? 'always'));
    if ($load === '' || $load === 'always') {
        return true;
    }

    if ($page === null || $page === '') {
        return $load === 'always';
    }

    if ($load === 'dashboard') {
        return $page === 'dashboard';
    }

    return $load === $page;
}

/**
 * Aggregate admin scripts for all active plugins.
 *
 * @return list<array<string, mixed>>
 */
function birkly_get_admin_scripts(?string $admin_page = null): array
{
    birkly_bootstrap_plugins_for_admin_assets();

    $scripts = [];
    $coveredProvides = [];

    $all = function_exists('get_all_plugins') ? get_all_plugins() : [];
    ksort($all);

    foreach ($all as $pluginId => $plugin) {
        if (!is_array($plugin) || !birkly_is_admin_asset_plugin_active((string) $pluginId)) {
            continue;
        }

        foreach (birkly_read_plugin_admin_manifest((string) $pluginId, $plugin) as $entry) {
            if (!birkly_admin_script_matches_page($entry, $admin_page)) {
                continue;
            }

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

    if (function_exists('apply_filters')) {
        $filtered = apply_filters('birkly_admin_scripts', $scripts, $admin_page);
        if (is_array($filtered)) {
            $scripts = $filtered;
        }
    }

    return array_values($scripts);
}

/**
 * Build versioned script URL for an admin shelf entry.
 *
 * @param array<string, mixed> $entry
 */
function birkly_admin_script_src(array $entry): string
{
    $url = (string) ($entry['url'] ?? '');
    if ($url === '') {
        return '';
    }

    $version = trim((string) ($entry['version'] ?? ''));
    if ($version === '') {
        return $url;
    }

    return $url . (str_contains($url, '?') ? '&' : '?') . 'v=' . rawurlencode($version);
}

/**
 * Render HTML script tags for active plugin admin assets.
 */
function birkly_render_admin_script_tags(?string $admin_page = null): string
{
    if (!function_exists('birkly_csp_nonce_attr')) {
        require_once dirname(__DIR__) . '/csp.php';
    }

    $nonceAttr = function_exists('birkly_csp_nonce_attr') ? birkly_csp_nonce_attr() : '';
    $html = '';

    foreach (birkly_get_admin_scripts($admin_page) as $entry) {
        $src = birkly_admin_script_src($entry);
        if ($src === '') {
            continue;
        }

        $fullSrc = function_exists('birkly_url') ? birkly_url($src) : $src;
        $html .= '<script src="' . htmlspecialchars($fullSrc, ENT_QUOTES, 'UTF-8') . '"' . $nonceAttr . '></script>' . "\n";
    }

    if ($html !== '') {
        $html .= '<script' . $nonceAttr . '>document.dispatchEvent(new CustomEvent(\'birkly:admin-plugins-ready\'));</script>' . "\n";
    }

    return $html;
}
