<?php
/**
 * Dashboard widget helpers — role and opt-in filtering for plugin widgets.
 */

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
