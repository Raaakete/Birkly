<?php
/**
 * P87 — Admin Guide core helpers.
 */

require_once __DIR__ . '/../core/i18n.php';
require_once __DIR__ . '/../core/admin_guide.php';

function guide_assert(string $label, bool $ok): void
{
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    echo "OK: {$label}\n";
}

guide_assert('area dashboard', birkly_guide_area_from_request('dashboard', '') === 'dashboard');
guide_assert('area content', birkly_guide_area_from_request('content', '') === 'content');
guide_assert('area hub plugins page', birkly_guide_area_from_request('plugins', '') === 'hub');
guide_assert('area settings delivery', birkly_guide_area_from_request('settings', 'site-delivery') === 'settings-delivery');

$prefs = birkly_guide_default_prefs();
guide_assert('default first visit empty', array_key_exists('first_admin_visit_ack_at', $prefs) && $prefs['first_admin_visit_ack_at'] === null);

$payload = birkly_guide_build_progress_payload($prefs, 'dashboard');
guide_assert('auto open first visit', !empty($payload['should_auto_open_panel']));
guide_assert('checklist has items', count($payload['checklist'] ?? []) >= 4);

$prefs['first_admin_visit_ack_at'] = date('c');
$payload2 = birkly_guide_build_progress_payload($prefs, 'content');
guide_assert('no auto open after ack', empty($payload2['should_auto_open_panel']));
guide_assert('content tip', ($payload2['tip']['area'] ?? '') === 'content');

echo "guide_core_test passed.\n";
