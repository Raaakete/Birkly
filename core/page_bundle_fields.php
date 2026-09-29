<?php
/**
 * Lean page-bundle keep-keys. Core owns system fields.
 * Plugins declare extra keys; those keys are included only while the plugin is active.
 */

$birkly_page_bundle_field_registry = [];

function register_page_bundle_fields(string $plugin_id, array $keys): bool
{
    global $birkly_page_bundle_field_registry;

    if ($plugin_id === '' || !preg_match('/^[a-z0-9_-]+$/', $plugin_id)) {
        return false;
    }

    $clean = birkly_page_bundle_clean_field_keys($keys);
    if ($clean === []) {
        return false;
    }

    $existing = $birkly_page_bundle_field_registry[$plugin_id] ?? [];
    $birkly_page_bundle_field_registry[$plugin_id] = array_values(array_unique(array_merge($existing, $clean)));

    return true;
}

/**
 * @return list<string>
 */
function birkly_page_bundle_system_keep_keys(): array
{
    return [
        'id',
        'slug',
        'status',
        'title',
        'created_at',
        'updated_at',
        'published_at',
        'excerpt',
        'content',
        'description',
        'icon',
        'featured',
        'tags',
        'category',
        'author',
        'date',
    ];
}

/**
 * @return array<string, list<string>>
 */
function birkly_page_bundle_declared_fields(): array
{
    global $birkly_page_bundle_field_registry;

    $declared = [];
    foreach ($birkly_page_bundle_field_registry as $pluginId => $keys) {
        $declared[(string) $pluginId] = birkly_page_bundle_clean_field_keys(is_array($keys) ? $keys : []);
    }

    $dir = birkly_page_bundle_plugins_dir();
    if (!is_dir($dir)) {
        return $declared;
    }

    foreach (scandir($dir) ?: [] as $pluginId) {
        if ($pluginId === '.' || $pluginId === '..') {
            continue;
        }
        $file = $dir . '/' . $pluginId . '/plugin.json';
        if (!is_file($file)) {
            continue;
        }
        $json = json_decode((string) file_get_contents($file), true);
        $fields = is_array($json) ? ($json['page_bundle']['fields'] ?? []) : [];
        $clean = birkly_page_bundle_clean_field_keys(is_array($fields) ? $fields : []);
        if ($clean === []) {
            continue;
        }
        $declared[$pluginId] = array_values(array_unique(array_merge($declared[$pluginId] ?? [], $clean)));
    }

    return $declared;
}

/**
 * @return list<string>
 */
function birkly_page_bundle_keep_keys(): array
{
    $keep = birkly_page_bundle_system_keep_keys();
    foreach (birkly_page_bundle_declared_fields() as $pluginId => $keys) {
        if (!birkly_page_bundle_plugin_is_active($pluginId)) {
            continue;
        }
        $keep = array_merge($keep, $keys);
    }

    return array_values(array_unique($keep));
}

/**
 * Keys a plugin owns that must not leak into a lean bundle while that plugin is inactive.
 *
 * @return list<string>
 */
function birkly_page_bundle_withheld_keys(): array
{
    $withheld = [];
    foreach (birkly_page_bundle_declared_fields() as $pluginId => $keys) {
        if (birkly_page_bundle_plugin_is_active($pluginId)) {
            continue;
        }
        $withheld = array_merge($withheld, $keys);
    }

    return array_values(array_unique($withheld));
}

function birkly_page_bundle_plugins_dir(): string
{
    if (isset($GLOBALS['birkly_page_bundle_plugins_dir'])) {
        return (string) $GLOBALS['birkly_page_bundle_plugins_dir'];
    }

    return dirname(__DIR__) . '/plugins';
}

function birkly_page_bundle_plugin_is_active(string $pluginId): bool
{
    if (isset($GLOBALS['birkly_page_bundle_active']) && is_array($GLOBALS['birkly_page_bundle_active'])
        && array_key_exists($pluginId, $GLOBALS['birkly_page_bundle_active'])) {
        return (bool) $GLOBALS['birkly_page_bundle_active'][$pluginId];
    }
    if (function_exists('birkly_is_plugin_active')) {
        return birkly_is_plugin_active($pluginId);
    }

    return false;
}

/**
 * @param array<mixed> $keys
 * @return list<string>
 */
function birkly_page_bundle_clean_field_keys(array $keys): array
{
    $clean = [];
    foreach ($keys as $key) {
        if (!is_string($key) || !preg_match('/^[a-z0-9_]+$/i', $key)) {
            continue;
        }
        $clean[] = $key;
    }

    return array_values(array_unique($clean));
}
