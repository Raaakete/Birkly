<?php
/**
 * Analytics presets tests (P61).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/analytics_presets.php';

$failures = 0;
$path = birkly_analytics_settings_path();
$backup = is_readable($path) ? file_get_contents($path) : null;

function ap_assert(string $name, bool $ok, string $detail = ''): void
{
    global $failures;
    echo $name . ': ' . ($ok ? "PASS\n" : "FAIL\n");
    if (!$ok) {
        $failures++;
        if ($detail !== '') {
            echo '  ' . $detail . "\n";
        }
    }
}

try {
    ap_assert('save off', birkly_analytics_save_settings(['preset' => 'off']));
    ap_assert('off not collecting', birkly_analytics_collecting() === false);
    ap_assert('off drops event', birkly_analytics_apply_preset_to_event(['event_type' => 'page_view', 'path' => '/']) === null);

    ap_assert('save minimal', birkly_analytics_save_settings(['preset' => 'minimal']));
    $minimal = birkly_analytics_apply_preset_to_event([
        'event_type' => 'page_view',
        'path' => '/about',
        'session_id' => 'sess_1',
        'browser' => 'Chrome',
        'referrer' => 'https://google.com/search?q=x',
        'ip' => '1.2.3.4',
        'visitor_day_key' => 'vd_test_key',
    ]);
    ap_assert('minimal keeps path', is_array($minimal) && ($minimal['path'] ?? '') === '/about');
    ap_assert('minimal strips session', is_array($minimal) && !isset($minimal['session_id']));
    ap_assert('minimal strips browser', is_array($minimal) && !isset($minimal['browser']));
    ap_assert('minimal keeps visitor_day_key', is_array($minimal) && isset($minimal['visitor_day_key']));
    ap_assert('minimal coarse referrer', is_array($minimal) && ($minimal['referrer'] ?? '') === 'google.com');
    ap_assert('minimal no form events', birkly_analytics_allows_event('form_submit') === false);
    ap_assert('minimal consent not required', birkly_analytics_consent_required() === false);

    ap_assert('save standard', birkly_analytics_save_settings(['preset' => 'standard']));
    ap_assert('standard consent required', birkly_analytics_consent_required() === true);
    ap_assert('standard allows page_exit', birkly_analytics_allows_event('page_exit') === true);
    ap_assert('standard blocks outbound', birkly_analytics_allows_event('outbound_click') === false);

    ap_assert('save advanced', birkly_analytics_save_settings(['preset' => 'advanced']));
    ap_assert('advanced allows outbound', birkly_analytics_allows_event('outbound_click') === true);
    ap_assert('advanced allows form', birkly_analytics_allows_event('form_submit') === true);

    require_once dirname(__DIR__) . '/core/analytics_enrich.php';
    $built = birkly_analytics_build_event(
        ['tracking_id' => 'localhost', 'path' => '/', 'event_type' => 'form_submit'],
        '203.0.113.9',
        'Mozilla/5.0',
        time()
    );
    ap_assert('build keeps form_submit type', ($built['event_type'] ?? '') === 'form_submit');
    ap_assert('build sets ip_hash', isset($built['ip_hash']) && $built['ip_hash'] !== '');
    $filtered = birkly_analytics_apply_preset_to_event($built);
    ap_assert('advanced keeps ip_hash', is_array($filtered) && isset($filtered['ip_hash']));
    ap_assert('advanced strips raw ip field preference', is_array($filtered) && !isset($filtered['ip']));

    require_once dirname(__DIR__) . '/core/analytics_config.php';
    $enabled = birkly_analytics_ensure_site_tracking();
    ap_assert('ensure enables localhost', in_array('localhost', $enabled, true));
    $ids = birkly_analytics_load_tracking_ids();
    ap_assert('localhost tracking enabled', !empty($ids['localhost']['enabled']));

    $defs = birkly_analytics_preset_definitions();
    foreach (['off', 'minimal', 'standard', 'advanced'] as $id) {
        ap_assert('preset ' . $id . ' has tradeoff copy', isset($defs[$id]['upside'], $defs[$id]['downside']));
    }
} finally {
    if ($backup === null) {
        @unlink($path);
    } else {
        file_put_contents($path, $backup);
    }
}

exit($failures > 0 ? 1 : 0);
