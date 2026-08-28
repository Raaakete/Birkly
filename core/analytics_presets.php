<?php
/**
 * Analytics collection presets (P61) — Off / Minimal / Standard / Advanced.
 */

declare(strict_types=1);

require_once __DIR__ . '/birkly_paths.php';

/**
 * @return list<string>
 */
function birkly_analytics_preset_ids(): array
{
    return ['off', 'minimal', 'standard', 'advanced'];
}

/**
 * Normative preset definitions (product copy + collection rules).
 *
 * @return array<string, array<string, mixed>>
 */
function birkly_analytics_preset_definitions(): array
{
    return [
        'off' => [
            'id' => 'off',
            'label' => 'Off',
            'summary' => 'No visitor analytics.',
            'upside' => 'Full privacy — nothing is collected from visitors.',
            'downside' => 'No traffic or usage insights in Birkly.',
            'consent_required' => false,
            'retention_days' => 0,
            'collect' => [],
            'store_ip' => false,
            'hash_ip' => false,
            'sessions' => false,
            'utm' => false,
            'events' => false,
        ],
        'minimal' => [
            'id' => 'minimal',
            'label' => 'Minimal',
            'summary' => 'Privacy-forward page view totals.',
            'upside' => 'See which paths are popular without fingerprinting visitors.',
            'downside' => 'Least detail — no sessions, devices, or campaigns. Still not legal advice for your region.',
            'consent_required' => false,
            'retention_days' => 30,
            'collect' => ['page_view'],
            'store_ip' => false,
            'hash_ip' => false,
            'sessions' => false,
            'utm' => false,
            'events' => false,
        ],
        'standard' => [
            'id' => 'standard',
            'label' => 'Standard',
            'summary' => 'Useful site insights with consent for detailed data.',
            'upside' => 'Anonymous page-view totals always; sessions, devices, UTM, and geography after consent.',
            'downside' => 'Consent banner required for detailed tracking. Anonymous counts are path + daily visitor estimate only.',
            'consent_required' => true,
            'anonymous_counts' => true,
            'retention_days' => 90,
            'collect' => ['page_view', 'page_exit'],
            'store_ip' => false,
            'hash_ip' => true,
            'sessions' => true,
            'utm' => true,
            'events' => false,
        ],
        'advanced' => [
            'id' => 'advanced',
            'label' => 'Advanced',
            'summary' => 'Funnel-ready events and longer retention.',
            'upside' => 'Form submits, outbound clicks, plugin funnel hooks, CSV export.',
            'downside' => 'Strongest consent and disclosure duty. Highest detail and retention.',
            'consent_required' => true,
            'anonymous_counts' => true,
            'retention_days' => 180,
            'collect' => ['page_view', 'page_exit', 'form_submit', 'outbound_click', 'plugin_event'],
            'store_ip' => false,
            'hash_ip' => true,
            'sessions' => true,
            'utm' => true,
            'events' => true,
        ],
    ];
}

function birkly_analytics_settings_path(): string
{
    return birkly_project_root() . '/settings/analytics.json';
}

/**
 * @return array{preset: string, retention_days: int|null, consent_text: string}
 */
function birkly_analytics_settings_defaults(): array
{
    return [
        'preset' => 'off',
        'retention_days' => null,
        'consent_text' => 'We use privacy-friendly analytics to understand how the site is used. You can accept or decline.',
    ];
}

/**
 * @return array{preset: string, retention_days: int|null, consent_text: string}
 */
function birkly_analytics_load_settings(): array
{
    $defaults = birkly_analytics_settings_defaults();
    $path = birkly_analytics_settings_path();
    if (!is_readable($path)) {
        return $defaults;
    }
    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded)) {
        return $defaults;
    }
    $preset = birkly_analytics_normalize_preset((string) ($decoded['preset'] ?? 'off'));
    $retention = $decoded['retention_days'] ?? null;
    if ($retention !== null) {
        $retention = max(1, min(730, (int) $retention));
    }

    return [
        'preset' => $preset,
        'retention_days' => $retention,
        'consent_text' => trim((string) ($decoded['consent_text'] ?? $defaults['consent_text'])),
    ];
}

/**
 * @param array<string, mixed> $settings
 */
function birkly_analytics_save_settings(array $settings): bool
{
    $current = birkly_analytics_load_settings();
    $preset = birkly_analytics_normalize_preset((string) ($settings['preset'] ?? $current['preset']));
    $retention = array_key_exists('retention_days', $settings) ? $settings['retention_days'] : $current['retention_days'];
    if ($retention !== null && $retention !== '') {
        $retention = max(1, min(730, (int) $retention));
    } else {
        $retention = null;
    }
    $payload = [
        'preset' => $preset,
        'retention_days' => $retention,
        'consent_text' => trim((string) ($settings['consent_text'] ?? $current['consent_text'])),
        'updated_at' => date('c'),
    ];
    $dir = dirname(birkly_analytics_settings_path());
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        return false;
    }
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }
    return file_put_contents(birkly_analytics_settings_path(), $json, LOCK_EX) !== false;
}

function birkly_analytics_normalize_preset(string $preset): string
{
    $preset = strtolower(trim($preset));
    return in_array($preset, birkly_analytics_preset_ids(), true) ? $preset : 'off';
}

/**
 * @return array<string, mixed>
 */
function birkly_analytics_active_preset(): array
{
    $settings = birkly_analytics_load_settings();
    $defs = birkly_analytics_preset_definitions();
    $preset = $defs[$settings['preset']] ?? $defs['off'];
    if ($settings['retention_days'] !== null) {
        $preset['retention_days'] = $settings['retention_days'];
    }
    $preset['consent_text'] = $settings['consent_text'];
    return $preset;
}

function birkly_analytics_collecting(): bool
{
    return (birkly_analytics_active_preset()['id'] ?? 'off') !== 'off';
}

function birkly_analytics_consent_required(): bool
{
    return !empty(birkly_analytics_active_preset()['consent_required']);
}

/**
 * Standard/Advanced: count page views without consent (path + daily visitor estimate only).
 */
function birkly_analytics_anonymous_counts_enabled(): bool
{
    if (!birkly_analytics_consent_required()) {
        return false;
    }

    return !empty(birkly_analytics_active_preset()['anonymous_counts']);
}

function birkly_analytics_visitor_has_consent(mixed $consent): bool
{
    return $consent === true || $consent === 1 || $consent === '1' || $consent === 'granted';
}

/**
 * @param array<string, mixed> $event
 * @return array<string, mixed>
 */
function birkly_analytics_apply_anonymous_page_view_filter(
    array $event,
    string $trackingId,
    string $ip,
    int $timestamp
): array {
    $path = trim((string) ($event['path'] ?? '/'));
    if ($path === '') {
        $path = '/';
    }

    return [
        'timestamp' => $timestamp,
        'event_type' => 'page_view',
        'path' => $path,
        'domain' => trim((string) ($event['domain'] ?? '')),
        'visitor_day_key' => hash('sha256', 'vd:' . $trackingId . ':' . date('Y-m-d', $timestamp) . ':' . $ip),
        'consent_state' => 'anonymous',
    ];
}

/**
 * Whether an event type may be stored under the active preset.
 */
function birkly_analytics_allows_event(string $eventType): bool
{
    $preset = birkly_analytics_active_preset();
    $collect = $preset['collect'] ?? [];
    if (!is_array($collect) || $collect === []) {
        return false;
    }
    $eventType = strtolower(trim($eventType));
    if ($eventType === '' || $eventType === 'pageview') {
        $eventType = 'page_view';
    }
    return in_array($eventType, $collect, true);
}

/**
 * Filter a built analytics event according to the active preset.
 *
 * @param array<string, mixed> $event
 * @return array<string, mixed>|null Null = drop (do not store)
 */
function birkly_analytics_apply_preset_to_event(array $event): ?array
{
    $preset = birkly_analytics_active_preset();
    if (($preset['id'] ?? 'off') === 'off') {
        return null;
    }

    $type = strtolower((string) ($event['event_type'] ?? $event['type'] ?? 'page_view'));
    if ($type === 'pageview') {
        $type = 'page_view';
    }
    if (!birkly_analytics_allows_event($type)) {
        return null;
    }

    $event['event_type'] = $type;

    if (empty($preset['sessions'])) {
        unset($event['session_id']);
    }
    if (empty($preset['utm'])) {
        unset($event['utm_source'], $event['utm_medium'], $event['utm_campaign'], $event['utm_term'], $event['utm_content']);
    }
    if (empty($preset['store_ip'])) {
        unset($event['ip']);
    }
    // Keep privacy-safe visitor key when hash_ip is on (used for unique visitors).
    if (empty($preset['hash_ip'])) {
        unset($event['ip_hash'], $event['ip']);
    } elseif (!isset($event['ip_hash']) && isset($event['ip'])) {
        $event['ip_hash'] = $event['ip'];
    }

    // Minimal: strip device/browser/geo enrichment but keep daily visitor key.
    if (($preset['id'] ?? '') === 'minimal') {
        unset(
            $event['device_type'],
            $event['browser'],
            $event['os'],
            $event['country'],
            $event['screen_width'],
            $event['screen_height'],
            $event['language'],
            $event['timezone'],
            $event['session_id']
        );
        // Keep path + coarse referrer host only
        if (isset($event['referrer']) && is_string($event['referrer']) && $event['referrer'] !== '') {
            $host = parse_url($event['referrer'], PHP_URL_HOST);
            $event['referrer'] = is_string($host) ? $host : '';
        }
    }

    return $event;
}
