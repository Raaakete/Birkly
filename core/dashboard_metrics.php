<?php
/**
 * Dashboard metric bar (Birch v5, Foundations §06).
 *
 * The bar follows what is installed: plugin metrics lead (Commerce, User,
 * Events, then others), Published always keeps a slot, Analytics joins after
 * content, and content defaults fill the rest. Four slots, no scrolling; a
 * user's pins (Customize) come first.
 *
 * Plugins add metrics with a `dashboard_metrics_callback` returning a list of
 * ['id', 'label', 'icon', 'value', 'meta', 'format' => 'number'|'money',
 *  'currency', 'href'].
 */

const BIRKLY_DASHBOARD_METRIC_SLOTS = 4;

/** Content and analytics metrics after the plugin leads, in bar order. */
function birkly_dashboard_metric_core_order(): array
{
    return ['published', 'page_views', 'drafts', 'visitors', 'collections', 'media'];
}

/** Plugins that lead the bar, in order. Other plugins follow them. */
function birkly_dashboard_metric_lead_plugins(): array
{
    return ['commerce', 'user_management', 'events'];
}

/**
 * Pick the metric ids for the bar.
 *
 * @param list<string> $available Ids that have data on this install (core and plugin).
 * @param array<string, string> $pluginOf Metric id => plugin id, for plugin metrics.
 * @param list<string> $pinned User pins from Customize, in order.
 * @return list<string>
 */
function birkly_dashboard_metric_slots(array $available, array $pluginOf = [], array $pinned = [], int $slots = BIRKLY_DASHBOARD_METRIC_SLOTS): array
{
    $available = array_values(array_unique($available));
    $isAvailable = array_flip($available);

    $leadRank = array_flip(birkly_dashboard_metric_lead_plugins());
    $pluginMetrics = [];
    foreach ($available as $index => $id) {
        if (isset($pluginOf[$id])) {
            $rank = $leadRank[$pluginOf[$id]] ?? count($leadRank);
            $pluginMetrics[] = [$rank, $index, $id];
        }
    }
    usort($pluginMetrics, static fn ($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

    $sequence = [];
    foreach ($pinned as $id) {
        if (isset($isAvailable[$id])) {
            $sequence[] = $id;
        }
    }
    // One lead metric per plugin; extra plugin metrics are for Customize.
    $seenPlugins = [];
    foreach ($pluginMetrics as [, , $id]) {
        $plugin = $pluginOf[$id];
        if (!isset($seenPlugins[$plugin])) {
            $seenPlugins[$plugin] = true;
            $sequence[] = $id;
        }
    }
    foreach (birkly_dashboard_metric_core_order() as $id) {
        if (isset($isAvailable[$id])) {
            $sequence[] = $id;
        }
    }

    $out = [];
    foreach ($sequence as $id) {
        if (!in_array($id, $out, true)) {
            $out[] = $id;
        }
    }

    // Published always keeps a slot.
    if (isset($isAvailable['published']) && !in_array('published', array_slice($out, 0, $slots), true)) {
        $out = array_values(array_diff($out, ['published']));
        array_splice($out, $slots - 1, 0, ['published']);
    }

    return array_slice($out, 0, $slots);
}

/**
 * Entry counts across all collections.
 *
 * @return array{collections: int, total_entries: int, published: int, drafts: int}
 */
function birkly_dashboard_content_stats(): array
{
    require_once __DIR__ . '/storage/StorageFactory.php';
    $storage = get_storage();
    $collections = $storage->list('collections');
    $stats = ['collections' => count($collections), 'total_entries' => 0, 'published' => 0, 'drafts' => 0];
    foreach ($collections as $collectionDir) {
        foreach ($storage->list("collections/$collectionDir/entries") as $entryFile) {
            $entry = $storage->read("collections/$collectionDir/entries/$entryFile");
            if (!$entry) {
                continue;
            }
            $stats['total_entries']++;
            if (($entry['status'] ?? 'draft') === 'published') {
                $stats['published']++;
            } else {
                $stats['drafts']++;
            }
        }
    }
    return $stats;
}

function birkly_dashboard_media_count(): int
{
    $dir = dirname(__DIR__) . '/media';
    if (!is_dir($dir)) {
        return 0;
    }
    $count = 0;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->isFile() && $file->getFilename()[0] !== '.' && !str_contains($file->getPathname(), '/thumbnails/')) {
            $count++;
        }
    }
    return $count;
}

function birkly_dashboard_analytics_on(): bool
{
    if (!is_file(__DIR__ . '/analytics_presets.php')) {
        return false;
    }
    require_once __DIR__ . '/analytics_presets.php';
    return function_exists('birkly_analytics_collecting') && birkly_analytics_collecting();
}

/**
 * Metrics contributed by active plugins.
 *
 * @return list<array<string, mixed>>
 */
function birkly_dashboard_plugin_metrics(): array
{
    $out = [];
    $plugins = function_exists('get_active_plugins') ? get_active_plugins() : [];
    global $birkly_plugins;
    foreach ($plugins as $pluginId => $plugin) {
        $callback = $plugin['dashboard_metrics_callback'] ?? ($birkly_plugins[$pluginId]['dashboard_metrics_callback'] ?? null);
        if (!is_callable($callback)) {
            continue;
        }
        try {
            $metrics = call_user_func($callback);
        } catch (Throwable $e) {
            error_log('dashboard_metrics_callback failed for ' . $pluginId . ': ' . $e->getMessage());
            continue;
        }
        foreach (is_array($metrics) ? $metrics : [] as $metric) {
            $id = is_array($metric) ? trim((string) ($metric['id'] ?? '')) : '';
            if ($id === '' || !preg_match('/^[a-z0-9_-]+$/', $id)) {
                continue;
            }
            $out[] = [
                'id' => $id,
                'plugin' => (string) $pluginId,
                'label' => (string) ($metric['label'] ?? $id),
                'icon' => (string) ($metric['icon'] ?? 'dashboard'),
                'value' => $metric['value'] ?? 0,
                'meta' => (string) ($metric['meta'] ?? ''),
                'format' => ($metric['format'] ?? 'number') === 'money' ? 'money' : 'number',
                'currency' => strtoupper((string) ($metric['currency'] ?? '')),
                'href' => (string) ($metric['href'] ?? ''),
            ];
        }
    }
    return $out;
}

/**
 * The bar for one user: the chosen slots with values, plus every available
 * metric for Customize.
 *
 * @param array<string, mixed> $user
 * @return array{slots: list<array>, available: list<array>, pinned: list<string>}
 */
function birkly_dashboard_metrics(array $user): array
{
    $content = birkly_dashboard_content_stats();
    $metrics = [
        'published' => ['id' => 'published', 'label' => 'Published', 'icon' => 'check-circle', 'value' => $content['published'],
            'total' => $content['total_entries'], 'format' => 'number', 'href' => 'content'],
        'drafts' => ['id' => 'drafts', 'label' => 'Drafts', 'icon' => 'pencil', 'value' => $content['drafts'], 'format' => 'number', 'href' => 'content'],
        'collections' => ['id' => 'collections', 'label' => 'Collections', 'icon' => 'layers', 'value' => $content['collections'], 'format' => 'number', 'href' => 'content'],
        'media' => ['id' => 'media', 'label' => 'Media', 'icon' => 'media', 'value' => birkly_dashboard_media_count(), 'format' => 'number', 'href' => 'media'],
    ];
    if (birkly_dashboard_analytics_on()) {
        // Values come from ?action=analytics, which the Traffic panel loads anyway.
        $metrics['page_views'] = ['id' => 'page_views', 'label' => 'Page views', 'icon' => 'eye', 'value' => null, 'source' => 'analytics', 'format' => 'number'];
        $metrics['visitors'] = ['id' => 'visitors', 'label' => 'Visitors', 'icon' => 'users', 'value' => null, 'source' => 'analytics', 'format' => 'number'];
    }

    $pluginOf = [];
    foreach (birkly_dashboard_plugin_metrics() as $metric) {
        if (isset($metrics[$metric['id']])) {
            continue;
        }
        $metrics[$metric['id']] = $metric;
        $pluginOf[$metric['id']] = $metric['plugin'];
    }

    $pinned = array_values(array_filter(
        (array) ($user['ui_preferences']['dashboard_metrics'] ?? []),
        static fn ($id) => is_string($id) && $id !== ''
    ));
    $slots = birkly_dashboard_metric_slots(array_keys($metrics), $pluginOf, $pinned);

    return [
        'slots' => array_map(static fn ($id) => $metrics[$id], $slots),
        'available' => array_values($metrics),
        'pinned' => $pinned,
    ];
}

/**
 * Save a user's pinned metrics (Customize). Unknown ids are dropped; at most
 * four are kept. An empty list returns the bar to the automatic order.
 *
 * @param array<string, mixed> $sessionUser
 * @param list<string> $pins
 */
function birkly_save_dashboard_metric_pins(array $sessionUser, array $pins, array $availableIds): bool
{
    require_once __DIR__ . '/birkly_users.php';
    $path = birkly_resolve_user_account_path((string) ($sessionUser['id'] ?? ''), (string) ($sessionUser['email'] ?? ''));
    if ($path === null || !is_file($path)) {
        return false;
    }
    $clean = array_slice(array_values(array_unique(array_filter(
        $pins,
        static fn ($id) => is_string($id) && in_array($id, $availableIds, true)
    ))), 0, BIRKLY_DASHBOARD_METRIC_SLOTS);

    $user = json_decode((string) file_get_contents($path), true);
    if (!is_array($user)) {
        return false;
    }
    $prefs = is_array($user['ui_preferences'] ?? null) ? $user['ui_preferences'] : [];
    $prefs['dashboard_metrics'] = $clean;
    $user['ui_preferences'] = $prefs;
    $user['updated_at'] = date('c');
    $encoded = json_encode($user, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($encoded === false || file_put_contents($path, $encoded) === false) {
        return false;
    }
    if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
        $_SESSION['user']['ui_preferences'] = $prefs;
    }
    return true;
}
