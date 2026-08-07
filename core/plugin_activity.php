<?php
/**
 * Plugin activity bus (P61) — append-only attention/notify events for plugins.
 *
 * Storage under BIRKLY_DATA/activity (or project data/activity). Never secrets.
 */

declare(strict_types=1);

require_once __DIR__ . '/birkly_data_paths.php';

/**
 * Absolute directory for activity JSONL files.
 */
function birkly_plugin_activity_dir(): string
{
    $root = birkly_data_root();
    if ($root !== null) {
        return rtrim($root, '/\\') . '/activity';
    }

    return birkly_project_root() . '/data/activity';
}

/**
 * @return array{activity_notify: bool, activity_nav_dot: bool}
 */
function birkly_plugin_activity_default_settings(): array
{
    return [
        'activity_notify' => true,
        'activity_nav_dot' => true,
    ];
}

/**
 * Schema fields for Hub Plugins settings (and per-plugin UIs).
 * Auto-merged into every plugin so third-party plugins get toggles too.
 *
 * @return array<string, array{type: string, default: bool, label: string, description: string}>
 */
function birkly_plugin_activity_settings_schema(): array
{
    return [
        'activity_notify' => [
            'type' => 'boolean',
            'default' => true,
            'label' => 'Admin notifications',
            'description' => 'Show this plugin’s activity in the topbar notification bell',
        ],
        'activity_nav_dot' => [
            'type' => 'boolean',
            'default' => true,
            'label' => 'Sidebar visual indicator',
            'description' => 'Show a mint attention indicator on this plugin in the sidebar until opened',
        ],
    ];
}

/**
 * Merge activity toggles into a plugin settings_schema when missing.
 *
 * @param array<string, mixed> $schema
 * @return array<string, mixed>
 */
function birkly_plugin_merge_activity_settings_schema(array $schema): array
{
    foreach (birkly_plugin_activity_settings_schema() as $key => $field) {
        if (!isset($schema[$key]) || !is_array($schema[$key])) {
            $schema[$key] = $field;
        }
    }
    return $schema;
}

/**
 * Read plugin settings toggles for activity (from settings/plugins.json or plugin settings API store).
 *
 * @return array{activity_notify: bool, activity_nav_dot: bool}
 */
function birkly_plugin_activity_settings(string $pluginId): array
{
    $defaults = birkly_plugin_activity_default_settings();
    $pluginId = preg_replace('/[^a-z0-9_-]/', '', strtolower($pluginId)) ?? '';
    if ($pluginId === '') {
        return $defaults;
    }

    // Prefer live plugin settings API (settings/plugins.json via get_plugin_setting)
    if (!function_exists('get_plugin_setting')) {
        $pluginsCore = birkly_project_root() . '/core/plugins.php';
        if (is_file($pluginsCore)) {
            require_once $pluginsCore;
        }
    }
    if (function_exists('get_plugin_setting')) {
        return [
            'activity_notify' => (bool) get_plugin_setting($pluginId, 'activity_notify', $defaults['activity_notify']),
            'activity_nav_dot' => (bool) get_plugin_setting($pluginId, 'activity_nav_dot', $defaults['activity_nav_dot']),
        ];
    }

    $pluginsFile = birkly_project_root() . '/settings/plugins.json';
    if (is_readable($pluginsFile)) {
        $all = json_decode((string) file_get_contents($pluginsFile), true);
        if (is_array($all) && isset($all[$pluginId]['settings']) && is_array($all[$pluginId]['settings'])) {
            $stored = $all[$pluginId]['settings'];
            return [
                'activity_notify' => (bool) ($stored['activity_notify'] ?? $defaults['activity_notify']),
                'activity_nav_dot' => (bool) ($stored['activity_nav_dot'] ?? $defaults['activity_nav_dot']),
            ];
        }
    }

    return $defaults;
}

/**
 * @param array{
 *   plugin: string,
 *   event: string,
 *   title: string,
 *   body?: string,
 *   link?: string,
 *   notify?: bool,
 *   nav_attention?: bool,
 *   meta?: array<string, mixed>
 * } $payload
 * @return array<string, mixed>|null Stored activity or null on failure
 */
function birkly_plugin_activity_emit(array $payload): ?array
{
    $plugin = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) ($payload['plugin'] ?? ''))) ?? '';
    $event = preg_replace('/[^a-z0-9_.-]/', '', strtolower((string) ($payload['event'] ?? ''))) ?? '';
    $title = trim((string) ($payload['title'] ?? ''));
    if ($plugin === '' || $event === '' || $title === '') {
        return null;
    }

    $settings = birkly_plugin_activity_settings($plugin);
    $wantNotify = (bool) ($payload['notify'] ?? true);
    $wantDot = (bool) ($payload['nav_attention'] ?? true);

    $activity = [
        'id' => 'act_' . bin2hex(random_bytes(8)),
        'type' => 'plugin_activity',
        'plugin' => $plugin,
        'event' => $event,
        'title' => $title,
        'body' => trim((string) ($payload['body'] ?? '')),
        'link' => trim((string) ($payload['link'] ?? '')),
        'notify' => $wantNotify && $settings['activity_notify'],
        'nav_attention' => $wantDot && $settings['activity_nav_dot'],
        'created_at' => date('c'),
        'read_at' => null,
        'cleared_at' => null,
        'meta' => is_array($payload['meta'] ?? null) ? $payload['meta'] : [],
    ];

    // Strip obvious secrets from meta
    foreach (array_keys($activity['meta']) as $key) {
        $lk = strtolower((string) $key);
        if (str_contains($lk, 'password') || str_contains($lk, 'secret') || str_contains($lk, 'token') || str_contains($lk, 'api_key')) {
            unset($activity['meta'][$key]);
        }
    }

    $dir = birkly_plugin_activity_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        return null;
    }

    $file = $dir . '/' . date('Y-m-d') . '.jsonl';
    $line = json_encode($activity, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($line === false) {
        return null;
    }

    if (@file_put_contents($file, $line . "\n", FILE_APPEND | LOCK_EX) === false) {
        return null;
    }

    return $activity;
}

/**
 * @return list<array<string, mixed>>
 */
function birkly_plugin_activity_list(int $limit = 100, ?string $pluginId = null): array
{
    $dir = birkly_plugin_activity_dir();
    if (!is_dir($dir)) {
        return [];
    }

    $files = glob($dir . '/*.jsonl') ?: [];
    rsort($files);

    $out = [];
    foreach ($files as $file) {
        $fh = fopen($file, 'rb');
        if ($fh === false) {
            continue;
        }
        $lines = [];
        while (($line = fgets($fh)) !== false) {
            $lines[] = $line;
        }
        fclose($fh);
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $decoded = json_decode(trim($lines[$i]), true);
            if (!is_array($decoded)) {
                continue;
            }
            if ($pluginId !== null && ($decoded['plugin'] ?? '') !== $pluginId) {
                continue;
            }
            $out[] = $decoded;
            if (count($out) >= $limit) {
                return $out;
            }
        }
    }

    return $out;
}

/**
 * Plugins with uncleared nav attention.
 *
 * @return array<string, int> plugin_id => unread attention count
 */
function birkly_plugin_activity_nav_attention_counts(): array
{
    $counts = [];
    foreach (birkly_plugin_activity_list(500) as $item) {
        if (empty($item['nav_attention'])) {
            continue;
        }
        if (!empty($item['cleared_at'])) {
            continue;
        }
        $plugin = (string) ($item['plugin'] ?? '');
        if ($plugin === '') {
            continue;
        }
        $counts[$plugin] = ($counts[$plugin] ?? 0) + 1;
    }
    return $counts;
}

function birkly_plugin_activity_has_nav_attention(string $pluginId): bool
{
    $counts = birkly_plugin_activity_nav_attention_counts();
    return ($counts[$pluginId] ?? 0) > 0;
}

/**
 * Clear nav attention for a plugin (on page view).
 */
function birkly_plugin_activity_clear_nav(string $pluginId): int
{
    $pluginId = preg_replace('/[^a-z0-9_-]/', '', strtolower($pluginId)) ?? '';
    if ($pluginId === '') {
        return 0;
    }

    $dir = birkly_plugin_activity_dir();
    if (!is_dir($dir)) {
        return 0;
    }

    $cleared = 0;
    $now = date('c');
    foreach (glob($dir . '/*.jsonl') ?: [] as $file) {
        $raw = file_get_contents($file);
        if ($raw === false || $raw === '') {
            continue;
        }
        $lines = explode("\n", $raw);
        $changed = false;
        $newLines = [];
        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === '') {
                continue;
            }
            $decoded = json_decode($trim, true);
            if (!is_array($decoded)) {
                $newLines[] = $trim;
                continue;
            }
            if (
                ($decoded['plugin'] ?? '') === $pluginId
                && !empty($decoded['nav_attention'])
                && empty($decoded['cleared_at'])
            ) {
                $decoded['cleared_at'] = $now;
                $decoded['nav_attention'] = false;
                $cleared++;
                $changed = true;
            }
            $encoded = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $newLines[] = $encoded !== false ? $encoded : $trim;
        }
        if ($changed) {
            file_put_contents($file, implode("\n", $newLines) . "\n", LOCK_EX);
        }
    }

    return $cleared;
}

/**
 * Count unread plugin-activity items that should appear in the admin bell.
 *
 * @param list<string> $extraReadIds IDs already marked read in the user notifications store
 */
function birkly_plugin_activity_unread_notify_count(array $extraReadIds = []): int
{
    $extra = array_fill_keys(array_filter(array_map('strval', $extraReadIds)), true);
    $count = 0;
    foreach (birkly_plugin_activity_list(200) as $item) {
        if (empty($item['notify'])) {
            continue;
        }
        $id = (string) ($item['id'] ?? '');
        if ($id === '' || !empty($item['read_at']) || isset($extra[$id])) {
            continue;
        }
        $count++;
    }

    return $count;
}

/**
 * IDs of notify-flagged plugin activity items (optionally unread only).
 *
 * @param list<string> $extraReadIds
 * @return list<string>
 */
function birkly_plugin_activity_notify_ids(bool $unreadOnly = false, array $extraReadIds = []): array
{
    $extra = array_fill_keys(array_filter(array_map('strval', $extraReadIds)), true);
    $ids = [];
    foreach (birkly_plugin_activity_list(200) as $item) {
        if (empty($item['notify'])) {
            continue;
        }
        $id = (string) ($item['id'] ?? '');
        if ($id === '') {
            continue;
        }
        if ($unreadOnly && (!empty($item['read_at']) || isset($extra[$id]))) {
            continue;
        }
        $ids[] = $id;
    }

    return $ids;
}

/**
 * Mark activity items as read (for bell integration).
 *
 * @param list<string> $ids
 */
function birkly_plugin_activity_mark_read(array $ids): int
{
    $want = array_fill_keys(array_filter(array_map('strval', $ids)), true);
    if ($want === []) {
        return 0;
    }

    $dir = birkly_plugin_activity_dir();
    if (!is_dir($dir)) {
        return 0;
    }

    $marked = 0;
    $now = date('c');
    foreach (glob($dir . '/*.jsonl') ?: [] as $file) {
        $raw = file_get_contents($file);
        if ($raw === false || $raw === '') {
            continue;
        }
        $lines = explode("\n", $raw);
        $changed = false;
        $newLines = [];
        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === '') {
                continue;
            }
            $decoded = json_decode($trim, true);
            if (!is_array($decoded)) {
                $newLines[] = $trim;
                continue;
            }
            $id = (string) ($decoded['id'] ?? '');
            if ($id !== '' && isset($want[$id]) && empty($decoded['read_at'])) {
                $decoded['read_at'] = $now;
                $marked++;
                $changed = true;
            }
            $encoded = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $newLines[] = $encoded !== false ? $encoded : $trim;
        }
        if ($changed) {
            file_put_contents($file, implode("\n", $newLines) . "\n", LOCK_EX);
        }
    }

    return $marked;
}
