<?php
/**
 * Project settings (settings/project.json) — shared by admin API + SSR.
 *
 * Kept in core/ so public SSR does not depend on loading api/project_site.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/birkly_paths.php';
require_once __DIR__ . '/storage/StorageFactory.php';

function birkly_project_settings_storage(): Storage
{
    return new Storage(birkly_project_root() . '/settings');
}

/**
 * @return array<string, mixed>
 */
function birkly_project_settings_defaults(): array
{
    return [
        'show_visit_button' => false,
        // P51: server-side render controls.
        // P52-M3: server is the default for Birkly-hosted projects (PHP-only
        // in-process engine, no Node). Applies to missing/unset keys only —
        // a saved "client" in settings/project.json is always honored.
        'render_mode' => 'server',  // client | server | auto
        'hosting_type' => 'birkly', // birkly | external
        'ssr_cache_ttl' => 300,
    ];
}

/**
 * @return array<string, mixed>
 */
function birkly_load_project_settings(): array
{
    $settings = birkly_project_settings_storage()->read('project.json');
    if (!is_array($settings)) {
        $settings = [];
    }

    return array_merge(birkly_project_settings_defaults(), $settings);
}

/**
 * @param array<string, mixed> $patch
 */
function birkly_save_project_settings(array $patch): bool
{
    // Read raw file (not defaults-merged) so we never persist the default
    // render_mode over a missing key when the caller only patches another
    // field. A key stays absent until explicitly saved.
    $existing = birkly_project_settings_storage()->read('project.json');
    if (!is_array($existing)) {
        $existing = [];
    }

    $updated = array_merge($existing, $patch);
    $updated['_updated_at'] = date('c');
    if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user']['id'])) {
        $updated['_updated_by'] = $_SESSION['user']['id'];
    } elseif (!isset($updated['_updated_by'])) {
        $updated['_updated_by'] = 'system';
    }

    return birkly_project_settings_storage()->write('project.json', $updated);
}
