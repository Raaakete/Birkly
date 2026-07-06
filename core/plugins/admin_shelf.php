<?php
/**
 * Plugin Admin Shelf — metadata for plugin admin pages (title, breadcrumbs, sub-pages).
 */

$birkly_plugin_admin_meta = [];

function register_plugin_admin_meta(string $plugin_id, array $config = []): bool
{
    global $birkly_plugin_admin_meta;

    if ($plugin_id === '' || !preg_match('/^[a-z0-9_]+$/', $plugin_id)) {
        error_log('Invalid plugin admin meta id: ' . $plugin_id);
        return false;
    }

    $title = trim((string) ($config['title'] ?? ''));
    if ($title === '') {
        error_log("Plugin admin meta for '$plugin_id' requires title");
        return false;
    }

    $subPages = $config['sub_pages'] ?? [];
    if (!is_array($subPages)) {
        $subPages = [];
    }

    $normalizedSubPages = [];
    foreach ($subPages as $slug => $label) {
        $slug = (string) $slug;
        $label = trim((string) $label);
        if ($slug === '' || $label === '') {
            continue;
        }
        $normalizedSubPages[$slug] = $label;
    }

    $birkly_plugin_admin_meta[$plugin_id] = [
        'plugin_id' => $plugin_id,
        'title' => $title,
        'page' => (string) ($config['page'] ?? ''),
        'sub_pages' => $normalizedSubPages,
        'registered_at' => date('c'),
    ];

    return true;
}

/** @return array<string, array<string, mixed>> */
function get_all_plugin_admin_meta(): array
{
    global $birkly_plugin_admin_meta;
    return is_array($birkly_plugin_admin_meta) ? $birkly_plugin_admin_meta : [];
}

/** @return array<string, mixed>|null */
function get_plugin_admin_meta(string $plugin_id): ?array
{
    return get_all_plugin_admin_meta()[$plugin_id] ?? null;
}

/** @return array<int, array{label: string, url?: string}> */
function build_plugin_admin_breadcrumbs(string $page_slug): array
{
    foreach (get_all_plugin_admin_meta() as $meta) {
        $title = (string) ($meta['title'] ?? '');
        $rootPage = (string) ($meta['page'] ?? '');
        $subPages = is_array($meta['sub_pages'] ?? null) ? $meta['sub_pages'] : [];

        if ($page_slug === $rootPage) {
            return [['label' => $title]];
        }
        if (isset($subPages[$page_slug]) && $rootPage !== '') {
            $rootUrl = function_exists('admin_url') ? admin_url($rootPage) : '/admin/index.php?page=' . rawurlencode($rootPage);
            return [
                ['label' => $title, 'url' => $rootUrl],
                ['label' => (string) $subPages[$page_slug]],
            ];
        }
    }

    return [];
}
