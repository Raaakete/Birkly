<?php
/**
 * Dashboard widget helpers — core registry, schema normalization, role filtering.
 */

/**
 * Normalize a widget definition to Widget API v2 schema.
 *
 * @param array<string, mixed> $widget
 * @return array<string, mixed>
 */
function birkly_normalize_dashboard_widget(array $widget): array
{
    $id = (string) ($widget['id'] ?? '');
    $source = (string) ($widget['source'] ?? ($widget['plugin_id'] ?? '') !== '' ? 'plugin' : 'core');

    $normalized = [
        'id' => $id,
        'title' => (string) ($widget['title'] ?? ''),
        'size' => (string) ($widget['size'] ?? 'md'),
        'priority' => (int) ($widget['priority'] ?? 100),
        'endpoint' => $widget['endpoint'] ?? null,
        'mount' => $widget['mount'] ?? null,
        'refresh' => (int) ($widget['refresh'] ?? 0),
        'source' => $source,
        'html' => (string) ($widget['html'] ?? $widget['content'] ?? ''),
        'roles' => $widget['roles'] ?? null,
        'enabled' => !array_key_exists('enabled', $widget) || $widget['enabled'] !== false,
        'plugin_id' => $widget['plugin_id'] ?? null,
    ];

    $allowedSizes = ['sm', 'md', 'lg', 'full'];
    if (!in_array($normalized['size'], $allowedSizes, true)) {
        $normalized['size'] = 'md';
    }

    if ($normalized['endpoint'] === '') {
        $normalized['endpoint'] = null;
    }

    if ($normalized['mount'] === '') {
        $normalized['mount'] = null;
    }

    // Legacy javascript field maps to mount name when mount is absent.
    if ($normalized['mount'] === null && !empty($widget['javascript'])) {
        $normalized['mount'] = 'legacyHtml';
    }

    return $normalized;
}

/**
 * Core dashboard widgets shipped with Birkly.
 *
 * @return list<array<string, mixed>>
 */
function birkly_get_core_dashboard_widgets(): array
{
    $contentUrl = function_exists('admin_url') ? admin_url('content') : '/admin/index.php?page=content';
    $settingsUrl = function_exists('admin_url') ? admin_url('settings') : '/admin/index.php?page=settings';
    $mediaUrl = function_exists('admin_url') ? admin_url('media') : '/admin/index.php?page=media';

    $widgets = [
        [
            'id' => 'quick-actions',
            'title' => function_exists('__') ? __('admin.quick_actions') : 'Quick Actions',
            'size' => 'full',
            'priority' => 5,
            'mount' => 'quickActions',
            'source' => 'core',
            'roles' => ['admin', 'editor'],
        ],
        [
            'id' => 'content-snapshot',
            'title' => 'Content snapshot',
            'size' => 'full',
            'priority' => 10,
            'mount' => 'contentSnapshot',
            'endpoint' => '/api/dashboard.php?action=stats',
            'source' => 'core',
        ],
        [
            'id' => 'analytics-mini',
            'title' => function_exists('__') ? __('admin.website_analytics') : 'Website analytics',
            'size' => 'md',
            'priority' => 15,
            'mount' => 'analyticsMini',
            'endpoint' => '/api/dashboard.php?action=analytics',
            'source' => 'core',
            'roles' => ['admin'],
        ],
        [
            'id' => 'recent-collections',
            'title' => function_exists('__') ? __('admin.recent_collections') : 'Recent collections',
            'size' => 'md',
            'priority' => 20,
            'mount' => 'recentCollections',
            'source' => 'core',
        ],
        [
            'id' => 'recent-entries',
            'title' => function_exists('__') ? __('admin.recent_entries') : 'Recent entries',
            'size' => 'md',
            'priority' => 25,
            'mount' => 'recentEntries',
            'source' => 'core',
        ],
        [
            'id' => 'recent-activity',
            'title' => function_exists('__') ? __('admin.recent_activity') : 'Recent activity',
            'size' => 'lg',
            'priority' => 30,
            'mount' => 'recentActivity',
            'endpoint' => '/api/dashboard.php?action=activity&limit=20&filter=all',
            'refresh' => 30,
            'source' => 'core',
        ],
        [
            'id' => 'drafts',
            'title' => function_exists('__') ? __('admin.drafts') : 'Drafts',
            'size' => 'md',
            'priority' => 35,
            'mount' => 'drafts',
            'endpoint' => '/api/dashboard.php?action=stats',
            'source' => 'core',
        ],
        [
            'id' => 'system-status',
            'title' => function_exists('__') ? __('admin.system_health') : 'System health',
            'size' => 'md',
            'priority' => 40,
            'mount' => 'systemStatus',
            'endpoint' => '/api/dashboard.php?action=performance',
            'refresh' => 30,
            'source' => 'core',
            'roles' => ['admin'],
        ],
        [
            'id' => 'security',
            'title' => 'Security',
            'size' => 'sm',
            'priority' => 45,
            'mount' => 'security',
            'endpoint' => '/api/dashboard.php?action=activity&limit=10&filter=users',
            'source' => 'core',
            'roles' => ['admin'],
        ],
        [
            'id' => 'updates',
            'title' => 'Updates & alerts',
            'size' => 'sm',
            'priority' => 50,
            'mount' => 'updates',
            'endpoint' => '/api/dashboard.php?action=notifications',
            'source' => 'core',
            'roles' => ['admin'],
        ],
    ];

    return array_map('birkly_normalize_dashboard_widget', $widgets);
}

/**
 * Merge core and plugin widgets, sorted by priority ascending.
 *
 * @param list<array<string, mixed>> $pluginWidgets
 * @return list<array<string, mixed>>
 */
function birkly_merge_dashboard_widgets(array $pluginWidgets): array
{
    $core = birkly_get_core_dashboard_widgets();
    $merged = [];

    foreach (array_merge($core, $pluginWidgets) as $widget) {
        if (!is_array($widget)) {
            continue;
        }
        $normalized = birkly_normalize_dashboard_widget($widget);
        if ($normalized['id'] === '' || $normalized['enabled'] === false) {
            continue;
        }
        $merged[] = $normalized;
    }

    usort($merged, static function (array $a, array $b): int {
        $prio = ($a['priority'] ?? 100) <=> ($b['priority'] ?? 100);
        if ($prio !== 0) {
            return $prio;
        }
        return strcmp((string) ($a['id'] ?? ''), (string) ($b['id'] ?? ''));
    });

    return $merged;
}

/**
 * Filter plugin dashboard widgets for the current CMS user.
 *
 * Widgets may specify `roles` (allowed role ids). Plugins may opt in via
 * settings `enable_dashboard_widget` (default: no filter unless explicitly false)
 * and `dashboard_widget_roles`.
 *
 * @param list<array<string, mixed>> $widgets
 * @return list<array<string, mixed>>
 */
function birkly_filter_dashboard_widgets_for_user(array $widgets): array
{
    if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
        return [];
    }

    $userRole = (string) ($_SESSION['user']['role'] ?? '');
    $filtered = [];

    foreach ($widgets as $widget) {
        if (!is_array($widget)) {
            continue;
        }

        $pluginId = (string) ($widget['plugin_id'] ?? '');

        if ($pluginId !== '' && function_exists('get_plugin_setting')) {
            $optIn = get_plugin_setting($pluginId, 'enable_dashboard_widget', null);
            if ($optIn === false) {
                continue;
            }
        }

        if (array_key_exists('enabled', $widget) && $widget['enabled'] === false) {
            continue;
        }

        $roles = $widget['roles'] ?? null;
        if ($roles === null && $pluginId !== '' && function_exists('get_plugin_setting')) {
            $roles = get_plugin_setting($pluginId, 'dashboard_widget_roles', null);
        }

        if (is_array($roles) && $roles !== [] && !in_array($userRole, $roles, true)) {
            continue;
        }

        $filtered[] = $widget;
    }

    return $filtered;
}
