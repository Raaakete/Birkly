<?php
/**
 * Dashboard widget registry, Widget API v2 schema, and access filtering.
 *
 * Widget API v2 accepts: id, title, size, priority, endpoint, mount, refresh,
 * roles, enabled, plugin_id, and legacy html. Endpoints must be same-origin
 * API routes; mounts name a registered client renderer and are never executed
 * by PHP or evaluated from widget data.
 */

/**
 * Normalize a dashboard widget to the public Widget API v2 schema.
 *
 * Invalid widget identifiers are rejected. Invalid optional values fall back
 * to safe defaults or null so a plugin cannot make the dashboard fetch an
 * arbitrary origin or invoke an arbitrary JavaScript expression.
 *
 * @param array<string, mixed> $widget
 * @return array<string, mixed>|null
 */
function birkly_normalize_dashboard_widget(array $widget): ?array
{
    $id = trim((string) ($widget['id'] ?? ''));
    if (!preg_match('/^[a-z0-9][a-z0-9-]{0,79}$/', $id)) {
        return null;
    }

    $pluginId = trim((string) ($widget['plugin_id'] ?? ''));
    if ($pluginId !== '' && !preg_match('/^[a-z0-9][a-z0-9_-]{0,79}$/', $pluginId)) {
        return null;
    }

    $size = (string) ($widget['size'] ?? 'md');
    if (!in_array($size, ['sm', 'md', 'lg', 'full'], true)) {
        $size = 'md';
    }

    $priority = filter_var($widget['priority'] ?? 100, FILTER_VALIDATE_INT);
    $priority = $priority === false ? 100 : max(0, min(100000, $priority));

    $refresh = filter_var($widget['refresh'] ?? 0, FILTER_VALIDATE_INT);
    $refresh = $refresh === false ? 0 : max(0, min(86400, $refresh));

    $endpoint = birkly_normalize_dashboard_widget_endpoint($widget['endpoint'] ?? null);
    $mount = birkly_normalize_dashboard_widget_mount($widget['mount'] ?? null);
    $roles = birkly_normalize_dashboard_widget_roles($widget['roles'] ?? null);

    return [
        'id' => $id,
        'title' => trim((string) ($widget['title'] ?? '')),
        'size' => $size,
        'priority' => $priority,
        'endpoint' => $endpoint,
        'mount' => $mount,
        'refresh' => $refresh,
        'source' => $pluginId === '' ? 'core' : 'plugin',
        'roles' => $roles,
        'enabled' => !array_key_exists('enabled', $widget) || $widget['enabled'] !== false,
        'plugin_id' => $pluginId === '' ? null : $pluginId,
        // Legacy plugin widgets remain supported while clients migrate to mount.
        'html' => (string) ($widget['html'] ?? $widget['content'] ?? ''),
    ];
}

/**
 * Accept only local API endpoints for a widget data fetch.
 *
 * @param mixed $endpoint
 */
function birkly_normalize_dashboard_widget_endpoint($endpoint): ?string
{
    if (!is_string($endpoint)) {
        return null;
    }

    $endpoint = trim($endpoint);
    if ($endpoint === '' || preg_match('/[\x00-\x1F\x7F]/', $endpoint)) {
        return null;
    }

    $parts = parse_url($endpoint);
    if ($parts === false || isset($parts['scheme'], $parts['host'], $parts['user'], $parts['pass'], $parts['port'])
        || !isset($parts['path']) || !str_starts_with($parts['path'], '/api/')) {
        return null;
    }

    foreach (explode('/', trim($parts['path'], '/')) as $segment) {
        $segment = rawurldecode($segment);
        if ($segment === '.' || $segment === '..') {
            return null;
        }
    }

    return isset($parts['fragment']) ? null : $endpoint;
}

/**
 * Accept a JavaScript identifier path, not executable source.
 *
 * @param mixed $mount
 */
function birkly_normalize_dashboard_widget_mount($mount): ?string
{
    if (!is_string($mount)) {
        return null;
    }

    $mount = trim($mount);
    return preg_match('/^[A-Za-z_$][A-Za-z0-9_$]*(?:\.[A-Za-z_$][A-Za-z0-9_$]*)*$/', $mount)
        ? $mount
        : null;
}

/**
 * @param mixed $roles
 * @return list<string>|null
 */
function birkly_normalize_dashboard_widget_roles($roles): ?array
{
    if (!is_array($roles)) {
        return null;
    }

    $normalized = [];
    foreach ($roles as $role) {
        if (!is_string($role)) {
            continue;
        }
        $role = trim($role);
        if ($role !== '' && preg_match('/^[a-z0-9][a-z0-9_-]{0,79}$/', $role)) {
            $normalized[$role] = true;
        }
    }

    return array_keys($normalized);
}

/**
 * First-party dashboard widgets, registered alongside plugin callbacks.
 *
 * @return list<array<string, mixed>>
 */
function birkly_get_core_dashboard_widgets(): array
{
    $widgets = [
        ['id' => 'content-snapshot', 'title' => 'Content overview', 'size' => 'full', 'priority' => 5, 'endpoint' => '/api/dashboard.php?action=stats', 'mount' => 'contentSnapshot'],
        ['id' => 'analytics-mini', 'title' => 'Website analytics', 'size' => 'md', 'priority' => 10, 'endpoint' => '/api/dashboard.php?action=analytics', 'mount' => 'analyticsMini', 'roles' => ['admin']],
        ['id' => 'recent-collections', 'title' => 'Recent collections', 'size' => 'md', 'priority' => 20, 'mount' => 'recentCollections'],
        ['id' => 'recent-entries', 'title' => 'Recent entries', 'size' => 'md', 'priority' => 25, 'mount' => 'recentEntries'],
        ['id' => 'pending-review', 'title' => 'Pending review', 'size' => 'lg', 'priority' => 28, 'endpoint' => '/api/dashboard.php?action=pending_review&limit=12', 'mount' => 'pendingReview', 'roles' => ['admin', 'editor']],
        ['id' => 'recent-activity', 'title' => 'Recent activity', 'size' => 'md', 'priority' => 30, 'endpoint' => '/api/dashboard.php?action=activity&limit=12&filter=all', 'mount' => 'recentActivity', 'refresh' => 30],
        ['id' => 'drafts', 'title' => 'Drafts', 'size' => 'sm', 'priority' => 35, 'endpoint' => '/api/dashboard.php?action=stats', 'mount' => 'drafts'],
        ['id' => 'system-status', 'title' => 'System health', 'size' => 'md', 'priority' => 40, 'endpoint' => '/api/dashboard.php?action=performance', 'mount' => 'systemStatus', 'refresh' => 30, 'roles' => ['admin']],
        ['id' => 'security', 'title' => 'Security', 'size' => 'sm', 'priority' => 45, 'endpoint' => '/api/dashboard.php?action=activity&limit=10&filter=users', 'mount' => 'security', 'roles' => ['admin']],
        ['id' => 'updates', 'title' => 'Updates & alerts', 'size' => 'sm', 'priority' => 50, 'endpoint' => '/api/dashboard.php?action=notifications', 'mount' => 'updates', 'roles' => ['admin']],
    ];

    return array_values(array_filter(array_map('birkly_normalize_dashboard_widget', $widgets)));
}

/**
 * Compose the core registry and plugin callbacks in deterministic display order.
 *
 * Core widget IDs are reserved. The first valid widget for any other ID wins,
 * preventing duplicate DOM identifiers when plugins conflict.
 *
 * @param list<array<string, mixed>> $pluginWidgets
 * @return list<array<string, mixed>>
 */
function birkly_merge_dashboard_widgets(array $pluginWidgets): array
{
    $widgets = [];
    $seenIds = [];

    foreach (array_merge(birkly_get_core_dashboard_widgets(), $pluginWidgets) as $widget) {
        if (!is_array($widget)) {
            continue;
        }
        $normalized = birkly_normalize_dashboard_widget($widget);
        if ($normalized === null || !$normalized['enabled'] || isset($seenIds[$normalized['id']])) {
            continue;
        }
        $seenIds[$normalized['id']] = true;
        $widgets[] = $normalized;
    }

    usort($widgets, static function (array $left, array $right): int {
        return $left['priority'] === $right['priority']
            ? strcmp($left['id'], $right['id'])
            : $left['priority'] <=> $right['priority'];
    });

    return $widgets;
}

/**
 * Filter normalized dashboard widgets for the current CMS user.
 *
 * Plugin widgets may opt out with `enable_dashboard_widget` and use
 * `dashboard_widget_roles` when their definition omits roles.
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
        $widget = birkly_normalize_dashboard_widget($widget);
        if ($widget === null || !$widget['enabled']) {
            continue;
        }

        $pluginId = $widget['plugin_id'] ?? '';
        if ($pluginId !== '' && function_exists('get_plugin_setting')
            && get_plugin_setting($pluginId, 'enable_dashboard_widget', null) === false) {
            continue;
        }

        $roles = $widget['roles'];
        if ($roles === null && $pluginId !== '' && function_exists('get_plugin_setting')) {
            $roles = birkly_normalize_dashboard_widget_roles(
                get_plugin_setting($pluginId, 'dashboard_widget_roles', null)
            );
        }

        if ($roles !== null && $roles !== [] && !in_array($userRole, $roles, true)) {
            continue;
        }

        $filtered[] = $widget;
    }

    return $filtered;
}

/**
 * Build a lookup index for resolving user ids and emails to display labels.
 *
 * @return array<string, array{id: string, name: string, email: string}>
 */
function birkly_build_user_display_index(): array
{
    require_once __DIR__ . '/birkly_users.php';

    $index = [];
    foreach (birkly_users_search_dirs() as $dir) {
        foreach (scandir($dir) ?: [] as $file) {
            if (!birkly_is_user_account_filename($file)) {
                continue;
            }
            $user = json_decode((string) file_get_contents($dir . '/' . $file), true);
            if (!is_array($user)) {
                continue;
            }
            $id = trim((string) ($user['id'] ?? str_replace('.json', '', $file)));
            $email = strtolower(trim((string) ($user['email'] ?? '')));
            $name = trim((string) ($user['name'] ?? ''));
            $label = $name !== '' ? $name : ($email !== '' ? $email : $id);
            $info = ['id' => $id, 'name' => $label, 'email' => $email];
            if ($id !== '') {
                $index[$id] = $info;
            }
            if ($email !== '') {
                $index[$email] = $info;
            }
            $fileId = str_replace('.json', '', $file);
            if ($fileId !== '' && !isset($index[$fileId])) {
                $index[$fileId] = $info;
            }
        }
    }

    return $index;
}

/**
 * Resolve a stored user id or email to a display label.
 *
 * @param array<string, array{id: string, name: string, email: string}> $index
 */
function birkly_resolve_user_display(string $reference, array $index): string
{
    $reference = trim($reference);
    if ($reference === '') {
        return 'Unassigned';
    }

    $normalized = strtolower($reference);
    if (isset($index[$reference])) {
        return $index[$reference]['name'];
    }
    if (isset($index[$normalized])) {
        return $index[$normalized]['name'];
    }
    if (strpos($reference, '@') !== false) {
        return $reference;
    }

    return $reference;
}

/**
 * Collect entries awaiting review across all collections.
 *
 * @param \StorageInterface|null $storage
 * @param array<string, array{id: string, name: string, email: string}>|null $userIndex
 * @return list<array<string, mixed>>
 */
function birkly_collect_pending_review_entries(
    int $limit = 12,
    string $assignedTo = 'all',
    ?string $currentUserId = null,
    $storage = null,
    ?array $userIndex = null
): array {
    require_once __DIR__ . '/storage/StorageFactory.php';

    $limit = max(1, min(50, $limit));
    $assignedTo = $assignedTo === 'me' ? 'me' : 'all';
    $storage = $storage ?? get_storage();
    $userIndex = $userIndex ?? birkly_build_user_display_index();
    $pending = [];

    foreach ($storage->list('collections') as $collectionSlug) {
        foreach ($storage->list("collections/$collectionSlug/entries") as $entryFile) {
            $entry = $storage->read("collections/$collectionSlug/entries/$entryFile");
            if (!is_array($entry) || ($entry['status'] ?? '') !== 'pending_review') {
                continue;
            }

            $reviewerRef = trim((string) ($entry['reviewer'] ?? ''));
            if ($assignedTo === 'me' && $currentUserId !== null && $currentUserId !== '') {
                $matchesReviewer = $reviewerRef !== '' && (
                    $reviewerRef === $currentUserId
                    || strtolower($reviewerRef) === strtolower($currentUserId)
                    || (isset($userIndex[$currentUserId]['email'])
                        && strtolower($reviewerRef) === $userIndex[$currentUserId]['email'])
                );
                if (!$matchesReviewer) {
                    continue;
                }
            }

            $creatorRef = trim((string) (
                $entry['author_id']
                ?? $entry['author_user_id']
                ?? $entry['created_by']
                ?? ''
            ));
            $entryId = trim((string) ($entry['id'] ?? pathinfo($entryFile, PATHINFO_FILENAME)));
            $updatedAt = (string) ($entry['updated_at'] ?? $entry['modified_at'] ?? $entry['created_at'] ?? '');

            $pending[] = [
                'id' => $entryId,
                'title' => trim((string) ($entry['title'] ?? $entry['name'] ?? 'Untitled')),
                'collection' => $collectionSlug,
                'status' => 'pending_review',
                'reviewer' => birkly_resolve_user_display($reviewerRef, $userIndex),
                'reviewer_id' => $reviewerRef,
                'submitted_by' => birkly_resolve_user_display($creatorRef, $userIndex),
                'submitted_by_id' => $creatorRef,
                'updated_at' => $updatedAt,
            ];
        }
    }

    usort($pending, static function (array $left, array $right): int {
        return strcmp($right['updated_at'] ?? '', $left['updated_at'] ?? '');
    });

    return array_slice($pending, 0, $limit);
}

/**
 * Default per-user dashboard layout preferences.
 *
 * @return array{widget_order: list<string>, hidden_widgets: list<string>, widget_sizes: array<string, string>}
 */
function birkly_default_dashboard_layout(): array
{
    return [
        'widget_order' => [],
        'hidden_widgets' => [],
        'widget_sizes' => [],
    ];
}

/**
 * Normalize dashboard layout preferences against known widget ids.
 *
 * @param mixed $layout
 * @param list<string> $widgetIds
 * @return array{widget_order: list<string>, hidden_widgets: list<string>, widget_sizes: array<string, string>, customized: bool}
 */
function birkly_normalize_dashboard_layout($layout, array $widgetIds): array
{
    $defaults = birkly_default_dashboard_layout();
    if (!is_array($layout)) {
        return array_merge($defaults, ['customized' => false]);
    }

    $allowed = array_fill_keys($widgetIds, true);
    $widgetOrder = [];
    foreach ((array) ($layout['widget_order'] ?? []) as $widgetId) {
        if (!is_string($widgetId)) {
            continue;
        }
        $widgetId = trim($widgetId);
        if ($widgetId !== '' && isset($allowed[$widgetId]) && !in_array($widgetId, $widgetOrder, true)) {
            $widgetOrder[] = $widgetId;
        }
    }

    $hiddenWidgets = [];
    foreach ((array) ($layout['hidden_widgets'] ?? []) as $widgetId) {
        if (!is_string($widgetId)) {
            continue;
        }
        $widgetId = trim($widgetId);
        if ($widgetId !== '' && isset($allowed[$widgetId]) && !in_array($widgetId, $hiddenWidgets, true)) {
            $hiddenWidgets[] = $widgetId;
        }
    }

    $widgetSizes = [];
    $sizes = $layout['widget_sizes'] ?? [];
    if (is_array($sizes)) {
        foreach ($sizes as $widgetId => $size) {
            if (!is_string($widgetId) || !is_string($size)) {
                continue;
            }
            $widgetId = trim($widgetId);
            $size = trim($size);
            if ($widgetId !== '' && isset($allowed[$widgetId]) && in_array($size, ['sm', 'md', 'lg', 'full'], true)) {
                $widgetSizes[$widgetId] = $size;
            }
        }
    }

    $customized = !empty($widgetOrder) || !empty($hiddenWidgets) || !empty($widgetSizes);

    return [
        'widget_order' => $widgetOrder,
        'hidden_widgets' => $hiddenWidgets,
        'widget_sizes' => $widgetSizes,
        'customized' => $customized,
    ];
}

/**
 * Load saved dashboard layout for a user account.
 *
 * @return array{widget_order: list<string>, hidden_widgets: list<string>, widget_sizes: array<string, string>, customized: bool}
 */
function birkly_load_user_dashboard_layout(string $userId, array $widgetIds): array
{
    require_once __DIR__ . '/birkly_users.php';

    $userId = trim($userId);
    if ($userId === '') {
        return birkly_normalize_dashboard_layout(null, $widgetIds);
    }

    foreach (birkly_users_search_dirs() as $dir) {
        $path = $dir . '/' . $userId . '.json';
        if (!is_file($path)) {
            continue;
        }
        $user = json_decode((string) file_get_contents($path), true);
        if (!is_array($user)) {
            break;
        }
        return birkly_normalize_dashboard_layout($user['dashboard_layout'] ?? null, $widgetIds);
    }

    return birkly_normalize_dashboard_layout(null, $widgetIds);
}

/**
 * Persist dashboard layout on the user account.
 *
 * @param array<string, mixed> $layout
 */
function birkly_save_user_dashboard_layout(string $userId, array $layout, array $widgetIds): bool
{
    require_once __DIR__ . '/birkly_users.php';

    $userId = trim($userId);
    if ($userId === '') {
        return false;
    }

    $normalized = birkly_normalize_dashboard_layout($layout, $widgetIds);
    unset($normalized['customized']);

    foreach (birkly_users_search_dirs() as $dir) {
        $path = $dir . '/' . $userId . '.json';
        if (!is_file($path)) {
            continue;
        }
        $user = json_decode((string) file_get_contents($path), true);
        if (!is_array($user)) {
            return false;
        }
        $user['dashboard_layout'] = $normalized;
        $user['updated_at'] = date('c');
        $encoded = json_encode($user, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            return false;
        }
        if (file_put_contents($path, $encoded) === false) {
            return false;
        }
        if (isset($_SESSION['user']) && is_array($_SESSION['user']) && ($_SESSION['user']['id'] ?? '') === $userId) {
            $_SESSION['user']['dashboard_layout'] = $normalized;
        }
        return true;
    }

    return false;
}

/**
 * Apply user layout preferences to normalized widgets.
 *
 * @param list<array<string, mixed>> $widgets
 * @param array{widget_order?: list<string>, hidden_widgets?: list<string>, widget_sizes?: array<string, string>} $layout
 * @return list<array<string, mixed>>
 */
function birkly_apply_dashboard_layout(array $widgets, array $layout): array
{
    $widgetIds = array_column($widgets, 'id');
    $normalizedLayout = birkly_normalize_dashboard_layout($layout, $widgetIds);
    $hidden = array_fill_keys($normalizedLayout['hidden_widgets'], true);
    $order = $normalizedLayout['widget_order'];
    $sizes = $normalizedLayout['widget_sizes'];

    $byId = [];
    foreach ($widgets as $widget) {
        if (!is_array($widget) || !isset($widget['id'])) {
            continue;
        }
        $id = (string) $widget['id'];
        if (isset($sizes[$id])) {
            $widget['size'] = $sizes[$id];
        }
        $widget['hidden'] = isset($hidden[$id]);
        $byId[$id] = $widget;
    }

    $ordered = [];
    foreach ($order as $widgetId) {
        if (isset($byId[$widgetId])) {
            $ordered[] = $byId[$widgetId];
            unset($byId[$widgetId]);
        }
    }

    foreach ($widgets as $widget) {
        if (!is_array($widget) || !isset($widget['id'])) {
            continue;
        }
        $id = (string) $widget['id'];
        if (isset($byId[$id])) {
            $ordered[] = $byId[$id];
            unset($byId[$id]);
        }
    }

    return $ordered;
}
