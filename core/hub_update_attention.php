<?php
/**
 * Hub plugin update attention (P83-M9)
 *
 * Emits P61 activity bus events when marketplace updates are available so the
 * admin bell and sidebar dots match Commerce-style attention UX.
 */

declare(strict_types=1);

require_once __DIR__ . '/plugin_activity.php';
require_once __DIR__ . '/platform.php';

function birkly_hub_update_attention_event(): string
{
    return 'hub_plugin_update';
}

/**
 * @return array{hub_updates_notify: bool, hub_updates_nav_dot: bool}
 */
function birkly_hub_update_attention_settings(): array
{
    $config = birkly_platform_config();

    return [
        'hub_updates_notify' => ($config['hub_updates_notify'] ?? true) !== false,
        'hub_updates_nav_dot' => ($config['hub_updates_nav_dot'] ?? true) !== false,
    ];
}

function birkly_hub_update_attention_link(): string
{
    if (function_exists('birkly_admin_url')) {
        return birkly_admin_url('plugins') . '#updates';
    }

    return '/admin/?page=plugins#updates';
}

function birkly_hub_update_attention_state_path(): string
{
    require_once __DIR__ . '/birkly_data_paths.php';
    $root = birkly_data_root();
    $base = $root !== null
        ? rtrim($root, '/\\')
        : birkly_project_root() . '/data';

    return $base . '/hub_update_attention.json';
}

/**
 * @return array<string, array{latest_version: string, activity_id?: string, acknowledged?: bool}>
 */
function birkly_hub_update_attention_load_state(): array
{
    $path = birkly_hub_update_attention_state_path();
    if (!is_readable($path)) {
        return [];
    }

    $decoded = json_decode((string) file_get_contents($path), true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * @param array<string, array{latest_version: string, activity_id?: string, acknowledged?: bool}> $state
 */
function birkly_hub_update_attention_save_state(array $state): void
{
    $path = birkly_hub_update_attention_state_path();
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        return;
    }

    @file_put_contents(
        $path,
        json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
        LOCK_EX
    );
}

function birkly_hub_update_attention_register_formatters(): void
{
    static $registered = false;
    if ($registered) {
        return;
    }
    $registered = true;

    if (!function_exists('birkly_notification_register_core_formatter')) {
        require_once __DIR__ . '/plugins/notification_shelf.php';
    }

    birkly_notification_register_core_formatter(
        birkly_hub_update_attention_event(),
        static function (array $entry, string $currentUserEmail): array {
            $details = $entry['details'] ?? [];
            $pluginId = (string) ($details['plugin_id'] ?? $entry['user'] ?? 'plugin');
            $pluginName = (string) ($details['plugin_name'] ?? $pluginId);
            $latest = (string) ($details['latest_version'] ?? '');
            $current = (string) ($details['current_version'] ?? '');
            $message = (string) ($details['message'] ?? '');
            if ($message === '') {
                $message = $latest !== ''
                    ? sprintf('Update available: %s %s', $pluginName, $latest)
                    : sprintf('Update available: %s', $pluginName);
            }
            if ($current !== '' && $latest !== '') {
                $message .= sprintf(' (%s → %s)', $current, $latest);
            }

            return [
                'message' => $message,
                'icon' => '⬆️',
                'color' => 'blue',
                'category' => 'hub',
                'link' => (string) ($details['link'] ?? birkly_hub_update_attention_link()),
            ];
        }
    );
}

/**
 * Mark matching plugin activity rows as cleared/read.
 */
function birkly_hub_update_attention_clear_plugin(string $pluginId, bool $updateState = true): int
{
    $pluginId = preg_replace('/[^a-z0-9_-]/', '', strtolower($pluginId)) ?? '';
    if ($pluginId === '') {
        return 0;
    }

    $event = birkly_hub_update_attention_event();
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
            if (($decoded['plugin'] ?? '') === $pluginId && ($decoded['event'] ?? '') === $event) {
                if (empty($decoded['cleared_at'])) {
                    $decoded['cleared_at'] = $now;
                    $decoded['nav_attention'] = false;
                    $cleared++;
                    $changed = true;
                }
                if (empty($decoded['read_at'])) {
                    $decoded['read_at'] = $now;
                    $changed = true;
                }
            }
            $encoded = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $newLines[] = $encoded !== false ? $encoded : $trim;
        }
        if ($changed) {
            file_put_contents($file, implode("\n", $newLines) . "\n", LOCK_EX);
        }
    }

    if ($updateState) {
        $state = birkly_hub_update_attention_load_state();
        unset($state[$pluginId]);
        birkly_hub_update_attention_save_state($state);
    }

    return $cleared;
}

function birkly_hub_update_attention_acknowledge_all(): int
{
    $state = birkly_hub_update_attention_load_state();
    $cleared = 0;
    foreach (array_keys($state) as $pluginId) {
        $cleared += birkly_hub_update_attention_clear_plugin((string) $pluginId, false);
        if (isset($state[$pluginId]) && is_array($state[$pluginId])) {
            $state[$pluginId]['acknowledged'] = true;
        }
    }

    birkly_hub_update_attention_save_state($state);
    return $cleared;
}

/**
 * Scan installed plugin updates and emit activity bus attention rows.
 *
 * @param array<string, array<string, mixed>>|null $updates
 * @return array{emitted: int, cleared: int}
 */
function birkly_hub_update_attention_sync(?array $updates = null): array
{
    birkly_hub_update_attention_register_formatters();

    if (!function_exists('birkly_marketplace_installed_plugin_updates')) {
        require_once __DIR__ . '/marketplace/install.php';
    }
    if (!function_exists('get_all_plugins')) {
        require_once __DIR__ . '/plugins.php';
    }

    $updates = $updates ?? birkly_marketplace_installed_plugin_updates();
    $plugins = get_all_plugins();
    $hubSettings = birkly_hub_update_attention_settings();
    $state = birkly_hub_update_attention_load_state();
    $emitted = 0;
    $cleared = 0;

    foreach ($updates as $pluginId => $row) {
        if (!is_array($row)) {
            continue;
        }
        $pluginId = (string) $pluginId;
        if (!empty($row['update_available'])) {
            $latest = (string) ($row['latest_version'] ?? '');
            if ($latest === '') {
                continue;
            }
            if (($state[$pluginId]['latest_version'] ?? '') === $latest) {
                if (!empty($state[$pluginId]['acknowledged'])) {
                    continue;
                }
                continue;
            }

            birkly_hub_update_attention_clear_plugin($pluginId, false);

            $current = (string) ($row['current_version'] ?? '');
            $pluginName = (string) ($plugins[$pluginId]['name'] ?? $pluginId);
            $pluginSettings = birkly_plugin_activity_settings($pluginId);
            $activity = birkly_plugin_activity_emit([
                'plugin' => $pluginId,
                'event' => birkly_hub_update_attention_event(),
                'title' => sprintf('Update available: %s %s', $pluginName, $latest),
                'body' => $current !== '' ? sprintf('%s → %s', $current, $latest) : $latest,
                'link' => birkly_hub_update_attention_link(),
                'notify' => $hubSettings['hub_updates_notify'] && $pluginSettings['activity_notify'],
                'nav_attention' => $hubSettings['hub_updates_nav_dot'] && $pluginSettings['activity_nav_dot'],
                'meta' => [
                    'hub_update' => true,
                    'plugin_id' => $pluginId,
                    'plugin_name' => $pluginName,
                    'current_version' => $current,
                    'latest_version' => $latest,
                    'marketplace_slug' => (string) ($row['marketplace_slug'] ?? ''),
                    'link' => birkly_hub_update_attention_link(),
                ],
            ]);

            if (is_array($activity)) {
                $state[$pluginId] = [
                    'latest_version' => $latest,
                    'activity_id' => (string) ($activity['id'] ?? ''),
                    'acknowledged' => false,
                ];
                $emitted++;
            }
            continue;
        }

        if (isset($state[$pluginId])) {
            $cleared += birkly_hub_update_attention_clear_plugin($pluginId);
        }
    }

    foreach (array_keys($state) as $pluginId) {
        if (!isset($updates[$pluginId]) || empty($updates[$pluginId]['update_available'])) {
            $cleared += birkly_hub_update_attention_clear_plugin($pluginId);
        }
    }

    birkly_hub_update_attention_save_state($state);

    return ['emitted' => $emitted, 'cleared' => $cleared];
}
