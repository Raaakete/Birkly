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

$manifest = birkly_guide_load_manifest();
guide_assert('manifest journeys', count($manifest['journeys'] ?? []) >= 2);
guide_assert('manifest concepts', count($manifest['concepts'] ?? []) >= 3);

$journeys = birkly_guide_resolve_journeys($prefs);
guide_assert('resolve journeys', count($journeys) >= 2 && ($journeys[0]['id'] ?? '') === 'first-content');

$concepts = birkly_guide_resolve_concepts();
guide_assert('resolve concepts', count($concepts) >= 3);

$payload3 = birkly_guide_build_progress_payload($prefs, 'dashboard');
guide_assert('checklist counts', ($payload3['checklist_total'] ?? 0) === 4 && isset($payload3['checklist_done_count']));
guide_assert('payload journeys', count($payload3['journeys'] ?? []) >= 2);

$prefsSnooze = $prefs;
$prefsSnooze['tips_snoozed_until'] = date('c', time() + 86400);
guide_assert('snooze active', birkly_guide_is_tips_snoozed($prefsSnooze));
$payloadSnooze = birkly_guide_build_progress_payload($prefsSnooze, 'content');
guide_assert('snooze suppresses attention', empty($payloadSnooze['attention']));

$prefsNudge = birkly_guide_default_prefs();
$prefsNudge['first_admin_visit_ack_at'] = date('c');
$prefsNudge['post_onboarding_nudge_pending'] = true;
$payloadNudge = birkly_guide_build_progress_payload($prefsNudge, 'dashboard');
guide_assert('post onboarding nudge on dashboard', !empty($payloadNudge['show_post_onboarding_nudge']));
$payloadNudgeContent = birkly_guide_build_progress_payload($prefsNudge, 'content');
guide_assert('no nudge off dashboard', empty($payloadNudgeContent['show_post_onboarding_nudge']));

echo "guide_core_test passed.\n";
