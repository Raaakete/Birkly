<?php

declare(strict_types=1);

require_once __DIR__ . '/analytics_presets.php';

/**
 * Granular consent categories (Advanced preset UI).
 *
 * @return list<array{id: string, label_key: string, required: bool, default: bool}>
 */
function birkly_analytics_consent_categories(): array
{
    return [
        ['id' => 'essential', 'label_key' => 'analytics_consent_cat_essential', 'required' => true, 'default' => true],
        ['id' => 'sessions', 'label_key' => 'analytics_consent_cat_sessions', 'required' => false, 'default' => true],
        ['id' => 'devices', 'label_key' => 'analytics_consent_cat_devices', 'required' => false, 'default' => true],
        ['id' => 'campaigns', 'label_key' => 'analytics_consent_cat_campaigns', 'required' => false, 'default' => true],
        ['id' => 'events', 'label_key' => 'analytics_consent_cat_events', 'required' => false, 'default' => false],
    ];
}

/**
 * @return array{sessions: bool, devices: bool, campaigns: bool, events: bool}
 */
function birkly_analytics_consent_prefs_defaults(): array
{
    $preset = birkly_analytics_active_preset();
    $allOn = birkly_analytics_visitor_has_consent('granted');
    if (($preset['id'] ?? '') === 'advanced') {
        return [
            'sessions' => true,
            'devices' => true,
            'campaigns' => true,
            'events' => !empty($preset['events']),
        ];
    }

    return [
        'sessions' => !empty($preset['sessions']),
        'devices' => true,
        'campaigns' => !empty($preset['utm']),
        'events' => !empty($preset['events']),
    ];
}

/**
 * @param array<string, mixed>|null $raw
 * @return array{sessions: bool, devices: bool, campaigns: bool, events: bool}
 */
function birkly_analytics_normalize_consent_prefs(?array $raw): array
{
    $defaults = birkly_analytics_consent_prefs_defaults();
    if (!is_array($raw)) {
        return $defaults;
    }
    $out = $defaults;
    foreach (array_keys($defaults) as $key) {
        if (array_key_exists($key, $raw)) {
            $out[$key] = filter_var($raw[$key], FILTER_VALIDATE_BOOLEAN);
        }
    }

    return $out;
}

/**
 * @return array{mode: string, prefs: array<string, bool>}
 */
function birkly_analytics_resolve_visitor_consent(?string $consentValue, ?array $inputPrefs = null): array
{
    $consent = strtolower(trim((string) ($consentValue ?? '')));
    $prefs = birkly_analytics_consent_prefs_defaults();

    if ($consent === 'granted' || $consent === '1' || $consent === 'accepted') {
        return ['mode' => 'granted', 'prefs' => array_map(static fn (): bool => true, $prefs)];
    }
    if ($consent === 'denied' || $consent === '0' || $consent === 'declined') {
        return ['mode' => 'denied', 'prefs' => array_map(static fn (): bool => false, $prefs)];
    }
    if ($consent === 'custom') {
        return ['mode' => 'custom', 'prefs' => birkly_analytics_normalize_consent_prefs($inputPrefs)];
    }

    if (!birkly_analytics_consent_required()) {
        return ['mode' => 'not_required', 'prefs' => $prefs];
    }

    return ['mode' => 'anonymous', 'prefs' => array_map(static fn (): bool => false, $prefs)];
}

function birkly_analytics_consent_allows_full_tracking(array $resolved): bool
{
    $mode = $resolved['mode'] ?? 'anonymous';
    if ($mode === 'granted' || $mode === 'not_required') {
        return true;
    }
    if ($mode === 'custom') {
        $prefs = $resolved['prefs'] ?? [];

        return !empty($prefs['sessions']) || !empty($prefs['devices']) || !empty($prefs['campaigns']) || !empty($prefs['events']);
    }

    return false;
}

/**
 * Strip fields the visitor did not consent to (after full event build).
 *
 * @param array<string, mixed> $event
 * @return array<string, mixed>
 */
function birkly_analytics_apply_consent_prefs_to_event(array $event, array $resolved): array
{
    $mode = $resolved['mode'] ?? 'anonymous';
    if ($mode === 'granted' || $mode === 'not_required') {
        return $event;
    }

    $prefs = $resolved['prefs'] ?? [];
    if (empty($prefs['sessions'])) {
        unset($event['session_id'], $event['visitor_token_hash']);
    }
    if (empty($prefs['devices'])) {
        unset(
            $event['user_agent'],
            $event['device_type'],
            $event['browser'],
            $event['os'],
            $event['screen_width'],
            $event['screen_height'],
            $event['viewport_width'],
            $event['viewport_height'],
            $event['language'],
            $event['timezone'],
            $event['country'],
            $event['region'],
            $event['location_source']
        );
    }
    if (empty($prefs['campaigns'])) {
        unset($event['utm_source'], $event['utm_medium'], $event['utm_campaign'], $event['utm_term'], $event['utm_content']);
        // Keep referrer_host for anonymous AI/referral attribution when essential counts run.
    }
    if (empty($prefs['events'])) {
        $type = strtolower((string) ($event['event_type'] ?? 'page_view'));
        if ($type !== 'page_view') {
            $event['event_type'] = 'page_view';
            $event['event_name'] = '';
            $event['event_data'] = [];
        }
    }

    return $event;
}

function birkly_analytics_consent_ui_mode(): string
{
    $id = (string) (birkly_analytics_active_preset()['id'] ?? 'off');

    return $id === 'advanced' ? 'advanced' : 'simple';
}

/**
 * @return array<string, string>
 */
function birkly_analytics_consent_category_labels_en(): array
{
    return [
        'essential' => 'Essential counts (pages visited)',
        'sessions' => 'Sessions and return visits',
        'devices' => 'Device, browser, and screen size',
        'campaigns' => 'Campaign tags and referring site',
        'events' => 'Clicks, forms, and other events',
    ];
}

function birkly_analytics_consent_is_anonymous_only(array $resolved): bool
{
    $mode = $resolved['mode'] ?? 'anonymous';
    if ($mode === 'granted' || $mode === 'not_required') {
        return false;
    }
    if ($mode === 'denied' || $mode === 'anonymous') {
        return true;
    }
    if ($mode === 'custom') {
        return !birkly_analytics_consent_allows_full_tracking($resolved);
    }

    return true;
}