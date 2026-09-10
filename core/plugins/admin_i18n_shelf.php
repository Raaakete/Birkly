<?php
/**
 * Admin Plugin i18n Shelf (P85-M1)
 *
 * Aggregates plugin lang/*.json catalogs for active plugins.
 * Core does not hardcode plugin admin strings — plugins ship lang files.
 */

if (!isset($GLOBALS['birkly_plugin_i18n_registry']) || !is_array($GLOBALS['birkly_plugin_i18n_registry'])) {
    $GLOBALS['birkly_plugin_i18n_registry'] = [];
}

/**
 * Register a plugin language directory (relative or absolute).
 */
function register_plugin_i18n(string $plugin_id, string $lang_dir): bool
{
    if ($plugin_id === '' || !preg_match('/^[a-z0-9_-]+$/', $plugin_id)) {
        error_log('Invalid plugin i18n id: ' . $plugin_id);
        return false;
    }

    $lang_dir = rtrim(str_replace('\\', '/', $lang_dir), '/');
    if ($lang_dir === '' || !is_dir($lang_dir)) {
        error_log("Plugin i18n directory missing for '$plugin_id': $lang_dir");
        return false;
    }

    $GLOBALS['birkly_plugin_i18n_registry'][$plugin_id] = [
        'plugin_id' => $plugin_id,
        'lang_dir' => $lang_dir,
        'source' => 'register',
    ];

    return true;
}

/**
 * Ensure plugin metadata is available for i18n shelf reads.
 */
function birkly_bootstrap_plugins_for_admin_i18n(): void
{
    if (function_exists('birkly_bootstrap_plugins_for_admin_assets')) {
        birkly_bootstrap_plugins_for_admin_assets();
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
 * Parse admin.i18n from plugin.json on disk.
 *
 * @return array{path?: string, namespace?: string}|array{}
 */
function birkly_read_plugin_i18n_manifest(string $plugin_id, array $plugin): array
{
    $manifest = [];

    if (isset($plugin['admin']) && is_array($plugin['admin'])) {
        $raw = $plugin['admin']['i18n'] ?? null;
        if (is_array($raw)) {
            $manifest = $raw;
        } elseif ($raw === true) {
            $manifest = ['path' => 'lang'];
        }
    }

    if ($manifest === []) {
        $jsonPath = rtrim((string) ($plugin['path'] ?? ''), '/') . '/plugin.json';
        if ($jsonPath !== '/plugin.json' && is_file($jsonPath)) {
            $decoded = json_decode((string) file_get_contents($jsonPath), true);
            if (is_array($decoded['admin']['i18n'] ?? null)) {
                $manifest = $decoded['admin']['i18n'];
            } elseif (($decoded['admin']['i18n'] ?? null) === true) {
                $manifest = ['path' => 'lang'];
            }
        }
    }

    return is_array($manifest) ? $manifest : [];
}

/**
 * Resolve lang directory for a plugin from manifest + plugin root.
 */
function birkly_plugin_i18n_lang_dir(string $plugin_id, array $plugin, array $manifest): string
{
    $relative = trim((string) ($manifest['path'] ?? 'lang'));
    if ($relative === '' || str_contains($relative, '..')) {
        $relative = 'lang';
    }

    $pluginRoot = rtrim(str_replace('\\', '/', (string) ($plugin['path'] ?? '')), '/');
    if ($pluginRoot === '') {
        return '';
    }

    $full = $pluginRoot . '/' . ltrim($relative, '/');
    return is_dir($full) ? $full : '';
}

/**
 * Register i18n dirs from admin.i18n manifests for active plugins.
 */
function birkly_register_plugin_i18n_from_manifests(): void
{
    if (!empty($GLOBALS['birkly_load_plugins_depth'])) {
        // Avoid re-entering load_plugins() from shelf bootstrap mid-load.
    } elseif (empty($GLOBALS['birkly_plugins'])) {
        birkly_bootstrap_plugins_for_admin_i18n();
    }

    $all = function_exists('get_all_plugins') ? get_all_plugins() : [];
    ksort($all);

    foreach ($all as $pluginId => $plugin) {
        if (!is_array($plugin)) {
            continue;
        }
        $pluginId = (string) $pluginId;
        if (!function_exists('birkly_is_admin_asset_plugin_active')) {
            if (!function_exists('birkly_is_plugin_active') || !birkly_is_plugin_active($pluginId)) {
                continue;
            }
        } elseif (!birkly_is_admin_asset_plugin_active($pluginId)) {
            continue;
        }

        if (isset($GLOBALS['birkly_plugin_i18n_registry'][$pluginId])) {
            continue;
        }

        $manifest = birkly_read_plugin_i18n_manifest($pluginId, $plugin);
        if ($manifest === [] && !is_dir(rtrim((string) ($plugin['path'] ?? ''), '/') . '/lang')) {
            continue;
        }

        $langDir = birkly_plugin_i18n_lang_dir($pluginId, $plugin, $manifest !== [] ? $manifest : ['path' => 'lang']);
        if ($langDir !== '') {
            register_plugin_i18n($pluginId, $langDir);
            $GLOBALS['birkly_plugin_i18n_registry'][$pluginId]['source'] = 'manifest';
        }
    }
}

/**
 * @return list<array{plugin_id: string, lang_dir: string, source: string}>
 */
function birkly_get_registered_plugin_i18n_catalogs(): array
{
    birkly_register_plugin_i18n_from_manifests();

    $catalogs = [];
    foreach ($GLOBALS['birkly_plugin_i18n_registry'] as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $pluginId = (string) ($entry['plugin_id'] ?? '');
        $langDir = (string) ($entry['lang_dir'] ?? '');
        if ($pluginId === '' || $langDir === '' || !is_dir($langDir)) {
            continue;
        }
        if (function_exists('birkly_is_admin_asset_plugin_active')
            && !birkly_is_admin_asset_plugin_active($pluginId)) {
            continue;
        }
        $catalogs[] = [
            'plugin_id' => $pluginId,
            'lang_dir' => $langDir,
            'source' => (string) ($entry['source'] ?? 'register'),
        ];
    }

    usort($catalogs, static fn(array $a, array $b): int => strcmp($a['plugin_id'], $b['plugin_id']));

    if (function_exists('apply_filters')) {
        $filtered = apply_filters('birkly_plugin_i18n_catalogs', $catalogs);
        if (is_array($filtered)) {
            $catalogs = $filtered;
        }
    }

    return array_values($catalogs);
}

/**
 * Load one plugin locale file as decoded array (without _meta).
 */
function birkly_load_plugin_i18n_file(string $lang_dir, string $language): ?array
{
    $language = strtolower(trim($language));
    if ($language === '' || preg_match('/[^a-z0-9_-]/', $language)) {
        return null;
    }

    $file = rtrim(str_replace('\\', '/', $lang_dir), '/') . '/' . $language . '.json';
    if (!is_file($file)) {
        return null;
    }

    $decoded = json_decode((string) file_get_contents($file), true);
    if (!is_array($decoded)) {
        return null;
    }

    unset($decoded['_meta']);

    return $decoded;
}

/**
 * Deep-merge translation trees (overlay wins on scalar conflicts).
 *
 * @param array<string, mixed> $base
 * @param array<string, mixed> $overlay
 * @return array<string, mixed>
 */
function birkly_i18n_deep_merge(array $base, array $overlay): array
{
    foreach ($overlay as $key => $value) {
        if ($key === '_meta') {
            continue;
        }
        if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
            $base[$key] = birkly_i18n_deep_merge($base[$key], $value);
        } else {
            $base[$key] = $value;
        }
    }

    return $base;
}

/**
 * Merge active plugin catalogs into I18n for the current and fallback languages.
 */
function birkly_merge_active_plugin_i18n_into_i18n(): void
{
    if (!class_exists('I18n')) {
        return;
    }

    I18n::mergePluginCatalogs();
}

function birkly_boot_admin_i18n_for_api(): void
{
    require_once dirname(__DIR__) . '/i18n.php';
    if (!function_exists('load_plugins')) {
        require_once dirname(__DIR__) . '/plugins.php';
    }
    if (function_exists('load_plugins') && empty($GLOBALS['birkly_plugins'])) {
        load_plugins();
    }
    I18n::init();
}


/**
 * Resolve @i18n:key references (P85-M3).
 */
function birkly_i18n_resolve_string(string $value): string
{
    if ($value === '' || !str_starts_with($value, '@i18n:')) {
        return $value;
    }

    $key = substr($value, 6);
    if ($key === '') {
        return $value;
    }

    return __($key);
}

/**
 * Localize settings_schema label/description fields for admin display.
 *
 * @param array<string, mixed> $schema
 * @return array<string, mixed>
 */
function birkly_i18n_localize_settings_schema(array $schema): array
{
    foreach ($schema as $key => $field) {
        if (!is_array($field)) {
            continue;
        }
        if (isset($field['label']) && is_string($field['label'])) {
            $field['label'] = birkly_i18n_resolve_string($field['label']);
        }
        if (isset($field['description']) && is_string($field['description'])) {
            $field['description'] = birkly_i18n_resolve_string($field['description']);
        }
        $schema[$key] = $field;
    }

    return $schema;
}
