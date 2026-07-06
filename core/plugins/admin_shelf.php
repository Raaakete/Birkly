<?php
/**
 * Plugin Admin Shelf — unified admin page metadata (breadcrumbs, nav, permissions).
 *
 * Mirrors the docs_shelf pattern. Plugins register admin meta once; core renders
 * breadcrumbs and sidebar from the shelf.
 */

$birkly_plugin_admin_meta = [];

/**
 * @param array<string, mixed> $config
 *   - title (string): Plugin display name — breadcrumb root on tabbed pages
 *   - root_page (string): Primary admin slug (e.g. commerce, site_users)
 *   - permission (string|null): Capability for page view (e.g. manage_commerce)
 *   - sidebar (array): show (bool), icon (string HTML), order (int), label (string)
 *   - sub_pages (array): slug => ['title' => string, 'permission' => ?string]
 *   - page_slugs (array): All slugs that map to this plugin (includes deprecated aliases)
 */
function register_plugin_admin_meta(string $plugin_id, array $config = []): bool
{
    global $birkly_plugin_admin_meta;

    if ($plugin_id === '' || !preg_match('/^[a-z0-9_]+$/', $plugin_id)) {
        error_log('Invalid plugin admin meta id: ' . $plugin_id);
        return false;
    }

    if (!function_exists('birkly_is_plugin_active') || !birkly_is_plugin_active($plugin_id)) {
        return false;
    }

    $rootPage = (string) ($config['root_page'] ?? $plugin_id);
    $pageSlugs = $config['page_slugs'] ?? [$rootPage];
    if (!is_array($pageSlugs)) {
        $pageSlugs = [$rootPage];
    }
    if (!in_array($rootPage, $pageSlugs, true)) {
        $pageSlugs[] = $rootPage;
    }

    $subPages = $config['sub_pages'] ?? [];
    if (is_array($subPages)) {
        foreach (array_keys($subPages) as $slug) {
            if (!in_array($slug, $pageSlugs, true)) {
                $pageSlugs[] = $slug;
            }
        }
    }

    $birkly_plugin_admin_meta[$plugin_id] = [
        'plugin_id' => $plugin_id,
        'title' => (string) ($config['title'] ?? ucfirst(str_replace('_', ' ', $plugin_id))),
        'root_page' => $rootPage,
        'permission' => $config['permission'] ?? null,
        'sidebar' => is_array($config['sidebar'] ?? null) ? $config['sidebar'] : [],
        'sub_pages' => is_array($subPages) ? $subPages : [],
        'page_slugs' => array_values(array_unique($pageSlugs)),
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

/** @return array<string, array<string, mixed>> */
function get_active_plugin_admin_meta(): array
{
    $active = [];
    foreach (get_all_plugin_admin_meta() as $plugin_id => $meta) {
        if (function_exists('birkly_is_plugin_active') && birkly_is_plugin_active($plugin_id)) {
            $active[$plugin_id] = $meta;
        }
    }
    return $active;
}

/** @return array<string, mixed>|null */
function get_plugin_admin_meta(string $plugin_id): ?array
{
    $all = get_all_plugin_admin_meta();
    if (!isset($all[$plugin_id])) {
        return null;
    }
    if (function_exists('birkly_is_plugin_active') && !birkly_is_plugin_active($plugin_id)) {
        return null;
    }
    return $all[$plugin_id];
}

/** Resolve admin meta by admin page slug. */
function get_plugin_admin_meta_for_page(string $page_slug): ?array
{
    foreach (get_active_plugin_admin_meta() as $meta) {
        $slugs = $meta['page_slugs'] ?? [];
        if (in_array($page_slug, $slugs, true)) {
            return $meta;
        }
    }
    return null;
}

/**
 * Build breadcrumb trail for a plugin admin page.
 *
 * @return list<array{label: string, url: string|null}>
 */
function birkly_plugin_admin_breadcrumb_trail(array $meta, string $current_page): array
{
    $rootTitle = (string) ($meta['title'] ?? 'Plugin');
    $rootPage = (string) ($meta['root_page'] ?? '');
    $subPages = $meta['sub_pages'] ?? [];

    if (isset($subPages[$current_page]) && $current_page !== $rootPage) {
        $sub = $subPages[$current_page];
        $trail = [];
        if ($rootPage !== '') {
            $trail[] = [
                'label' => $rootTitle,
                'url' => function_exists('admin_url') ? admin_url($rootPage) : ('?page=' . $rootPage),
            ];
        }
        $trail[] = [
            'label' => (string) ($sub['title'] ?? $current_page),
            'url' => null,
        ];
        return $trail;
    }

    return [['label' => $rootTitle, 'url' => null]];
}

/** Render plugin page breadcrumb HTML (entries.html contract). */
function birkly_render_plugin_page_breadcrumbs(array $meta, string $current_page): string
{
    $trail = birkly_plugin_admin_breadcrumb_trail($meta, $current_page);
    $parts = [];
    foreach ($trail as $i => $crumb) {
        $label = htmlspecialchars((string) ($crumb['label'] ?? ''), ENT_QUOTES, 'UTF-8');
        $url = $crumb['url'] ?? null;
        if ($url && $i < count($trail) - 1) {
            $parts[] = '<a href="' . htmlspecialchars((string) $url, ENT_QUOTES, 'UTF-8') . '">' . $label . '</a>';
        } else {
            $parts[] = '<span class="current">' . $label . '</span>';
        }
    }

    $inner = implode('<span class="separator">›</span>', $parts);

    return '<div class="page-header pt-m pb-m plugin-page-header">'
        . '<div class="page-header-left"><div class="breadcrumbs">' . $inner . '</div></div>'
        . '<div class="page-header-actions"></div></div>';
}

/** Sidebar navigation entries from admin shelf (active plugins only). */
function birkly_plugin_admin_sidebar_navigation(): array
{
    $nav = [];
    foreach (get_active_plugin_admin_meta() as $plugin_id => $meta) {
        $sidebar = $meta['sidebar'] ?? [];
        if (array_key_exists('show', $sidebar) && $sidebar['show'] === false) {
            continue;
        }
        $nav[] = [
            'id' => $plugin_id,
            'name' => (string) ($sidebar['label'] ?? $meta['title'] ?? $plugin_id),
            'page' => (string) ($meta['root_page'] ?? $plugin_id),
            'icon' => (string) ($sidebar['icon'] ?? ''),
            'order' => (int) ($sidebar['order'] ?? 100),
        ];
    }

    usort($nav, fn ($a, $b) => ($a['order'] <=> $b['order']) ?: strcmp($a['name'], $b['name']));

    return $nav;
}

/** Permission required to view a plugin admin page slug, or null. */
function birkly_plugin_admin_page_permission(string $page_slug): ?string
{
    $meta = get_plugin_admin_meta_for_page($page_slug);
    if ($meta === null) {
        return null;
    }

    $subPages = $meta['sub_pages'] ?? [];
    if (isset($subPages[$page_slug]['permission'])) {
        return (string) $subPages[$page_slug]['permission'];
    }

    $perm = $meta['permission'] ?? null;
    return is_string($perm) && $perm !== '' ? $perm : null;
}
