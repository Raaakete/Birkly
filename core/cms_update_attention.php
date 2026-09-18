<?php
/**
 * CMS core update attention — bell, Settings sidebar dot, optional modal (P90).
 *
 * Mirrors hub_update_attention for Birkly system updates (not Hub plugins).
 */

declare(strict_types=1);

require_once __DIR__ . '/plugin_activity.php';
require_once __DIR__ . '/birkly_data_paths.php';

function birkly_cms_update_attention_plugin_id(): string
{
    return 'birkly';
}

function birkly_cms_update_attention_event(): string
{
    return 'cms_update';
}

function birkly_cms_update_attention_cache_ttl_seconds(?array $status = null): int
{
    if (is_array($status) && !empty($status['update_available'])) {
        return 3600;
    }

    // Short TTL when up to date so new Ops/GitHub releases surface quickly.
    return 300;
}

function birkly_cms_update_attention_link(): string
{
    if (function_exists('birkly_admin_url')) {
        return birkly_admin_url('settings', ['tab' => 'system']);
    }

    return '/admin/?page=settings&tab=system';
}

function birkly_cms_update_attention_state_path(): string
{
    $root = birkly_data_root();
    $base = $root !== null
        ? rtrim($root, '/\\')
        : birkly_project_root() . '/data';

    return $base . '/cms_update_attention.json';
}

function birkly_cms_update_attention_cache_path(): string
{
    $root = birkly_data_root();
    $base = $root !== null
        ? rtrim($root, '/\\')
        : birkly_project_root() . '/data';

    return $base . '/cms_update_check_cache.json';
}

/**
 * @return array{latest_version?: string, activity_id?: string, acknowledged?: bool, modal_dismissed_version?: string}
 */
function birkly_cms_update_attention_load_state(): array
{
    $path = birkly_cms_update_attention_state_path();
    if (!is_readable($path)) {
        return [];
    }

    $decoded = json_decode((string) file_get_contents($path), true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * @param array{latest_version?: string, activity_id?: string, acknowledged?: bool, modal_dismissed_version?: string} $state
 */
function birkly_cms_update_attention_save_state(array $state): void
{
    $path = birkly_cms_update_attention_state_path();
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

/**
 * Cached update status to avoid remote checks on every admin page load.
 *
 * @return array<string, mixed>
 */
function birkly_cms_update_attention_cached_status(bool $forceRefresh = false): array
{
    $cachePath = birkly_cms_update_attention_cache_path();
    $now = time();

    if (!$forceRefresh && is_readable($cachePath)) {
        $cached = json_decode((string) file_get_contents($cachePath), true);
        if (is_array($cached)) {
            $checkedAt = (int) ($cached['checked_at'] ?? 0);
            $status = $cached['status'] ?? null;
            $ttl = is_array($status)
                ? birkly_cms_update_attention_cache_ttl_seconds($status)
                : 300;
            if ($checkedAt > 0 && ($now - $checkedAt) < $ttl && is_array($status)) {
                return $status;
            }
        }
    }

    if (!class_exists('BirklyVersion')) {
        require_once __DIR__ . '/version.php';
    }

    $status = BirklyVersion::getUpdateStatus();
    $dir = dirname($cachePath);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        return $status;
    }

    @file_put_contents(
        $cachePath,
        json_encode([
            'checked_at' => $now,
            'status' => $status,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
        LOCK_EX
    );

    return $status;
}

function birkly_cms_update_attention_register_formatters(): void
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
        birkly_cms_update_attention_event(),
        static function (array $entry, string $currentUserEmail): array {
            $details = $entry['details'] ?? [];
            $current = (string) ($details['current_version'] ?? '');
            $latest = (string) ($details['latest_version'] ?? '');
            $message = (string) ($details['message'] ?? '');
            if ($message === '') {
                $message = $latest !== ''
                    ? sprintf('Birkly CMS update available: %s', $latest)
                    : 'Birkly CMS update available';
            }
            if ($current !== '' && $latest !== '') {
                $message .= sprintf(' (%s → %s)', $current, $latest);
            }

            return [
                'message' => $message,
                'icon' => '⬆️',
                'color' => 'blue',
                'category' => 'system',
                'link' => (string) ($details['link'] ?? birkly_cms_update_attention_link()),
            ];
        }
    );
}

function birkly_cms_update_attention_clear_activity(bool $updateState = true): int
{
    $pluginId = birkly_cms_update_attention_plugin_id();
    $event = birkly_cms_update_attention_event();
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
        birkly_cms_update_attention_save_state([]);
    }

    return $cleared;
}

function birkly_cms_update_attention_acknowledge_all(): int
{
    $state = birkly_cms_update_attention_load_state();
    $cleared = birkly_cms_update_attention_clear_activity(false);
    if ($state !== []) {
        $state['acknowledged'] = true;
        birkly_cms_update_attention_save_state($state);
    }

    return $cleared;
}

function birkly_cms_update_attention_dismiss_modal(string $version): void
{
    $version = trim($version);
    if ($version === '') {
        return;
    }
    $state = birkly_cms_update_attention_load_state();
    $state['modal_dismissed_version'] = $version;
    birkly_cms_update_attention_save_state($state);
}

function birkly_cms_update_attention_has_active_attention(): bool
{
    return birkly_plugin_activity_has_nav_attention(birkly_cms_update_attention_plugin_id());
}

function birkly_cms_update_attention_should_show_modal(array $status, array $state): bool
{
    if (empty($status['update_available'])) {
        return false;
    }
    $latest = (string) ($status['latest_version'] ?? '');
    if ($latest === '') {
        return false;
    }
    $dismissed = (string) ($state['modal_dismissed_version'] ?? '');
    return $dismissed !== $latest;
}

/**
 * @param array<string, mixed>|null $status Pre-fetched status from cache
 * @return array{emitted: int, cleared: int, status: array<string, mixed>, show_modal: bool}
 */
function birkly_cms_update_attention_sync(?array $status = null, bool $forceRefresh = false): array
{
    birkly_cms_update_attention_register_formatters();

    $status = $status ?? birkly_cms_update_attention_cached_status($forceRefresh);
    $state = birkly_cms_update_attention_load_state();
    $pluginId = birkly_cms_update_attention_plugin_id();
    $emitted = 0;
    $cleared = 0;

    if (!empty($status['update_available'])) {
        $latest = (string) ($status['latest_version'] ?? '');
        $current = (string) ($status['current_version'] ?? '');
        if ($latest !== '') {
            $sameVersion = (string) ($state['latest_version'] ?? '') === $latest;
            $hasActive = birkly_cms_update_attention_has_active_attention();

            if ($sameVersion && !empty($state['acknowledged'])) {
                return [
                    'emitted' => 0,
                    'cleared' => 0,
                    'status' => $status,
                    'show_modal' => birkly_cms_update_attention_should_show_modal($status, $state),
                ];
            }

            if ($sameVersion && $hasActive) {
                return [
                    'emitted' => 0,
                    'cleared' => 0,
                    'status' => $status,
                    'show_modal' => birkly_cms_update_attention_should_show_modal($status, $state),
                ];
            }

            if (!$sameVersion) {
                birkly_cms_update_attention_clear_activity(false);
                $state = birkly_cms_update_attention_load_state();
                unset($state['acknowledged']);
            }

            $activity = birkly_plugin_activity_emit([
                'plugin' => $pluginId,
                'event' => birkly_cms_update_attention_event(),
                'title' => sprintf('Birkly CMS update available: %s', $latest),
                'body' => $current !== '' ? sprintf('%s → %s', $current, $latest) : $latest,
                'link' => birkly_cms_update_attention_link(),
                'notify' => true,
                'nav_attention' => true,
                'meta' => [
                    'cms_update' => true,
                    'current_version' => $current,
                    'latest_version' => $latest,
                    'link' => birkly_cms_update_attention_link(),
                ],
            ]);

            if (is_array($activity)) {
                $state['latest_version'] = $latest;
                $state['activity_id'] = (string) ($activity['id'] ?? '');
                $state['acknowledged'] = false;
                birkly_cms_update_attention_save_state($state);
                $emitted++;
            }
        }
    } elseif ($state !== []) {
        $cleared = birkly_cms_update_attention_clear_activity();
    }

    return [
        'emitted' => $emitted,
        'cleared' => $cleared,
        'status' => $status,
        'show_modal' => birkly_cms_update_attention_should_show_modal($status, $state),
    ];
}
