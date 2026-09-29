<?php
/**
 * P87 — Admin Guide core helpers.
 */

require_once __DIR__ . '/../core/i18n.php';
require_once __DIR__ . '/../core/admin_guide.php';
require_once __DIR__ . '/../api/auth.php';

I18n::init();

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
guide_assert('area discovery', birkly_guide_area_from_request('discovery', '') === 'discovery');
guide_assert('area settings project', birkly_guide_area_from_request('settings', 'project') === 'settings-project');
guide_assert('area settings ai', birkly_guide_area_from_request('settings', 'ai') === 'settings-ai');

$prefs = birkly_guide_default_prefs();
guide_assert('default first visit empty', array_key_exists('first_admin_visit_ack_at', $prefs) && $prefs['first_admin_visit_ack_at'] === null);

$payload = birkly_guide_build_progress_payload($prefs, 'dashboard');
guide_assert('auto open first visit', !empty($payload['should_auto_open_panel']));
guide_assert('checklist has items', count($payload['checklist'] ?? []) >= 5);
$firstLabel = $payload['checklist'][0]['label'] ?? '';
guide_assert(
    'checklist labels translated',
    $firstLabel !== '' && strpos($firstLabel, 'admin.guide_') !== 0
);

$prefs['first_admin_visit_ack_at'] = date('c');
$payload2 = birkly_guide_build_progress_payload($prefs, 'content');
guide_assert('no auto open after ack', empty($payload2['should_auto_open_panel']));
guide_assert('content tip', ($payload2['tip']['area'] ?? '') === 'content');
$payloadDiscovery = birkly_guide_build_progress_payload($prefs, 'discovery');
guide_assert('discovery tip', ($payloadDiscovery['tip']['area'] ?? '') === 'discovery');
guide_assert('ai nudge without user', !empty($payload2['show_ai_connection_nudge']));
guide_assert('ai connection href', strpos((string) ($payload2['ai_connection_href'] ?? ''), 'tab=ai') !== false);

$manifest = birkly_guide_load_manifest();
guide_assert('manifest journeys', count($manifest['journeys'] ?? []) >= 2);
guide_assert(
    'alpha plugin ids come from guide content',
    birkly_guide_alpha_plugin_ids() === ['user_management', 'commerce', 'automation']
);
$guidePhp = (string) file_get_contents(dirname(__DIR__) . '/core/admin_guide.php');
guide_assert(
    'guide php does not hardcode the alpha plugin list',
    !str_contains($guidePhp, "return ['user_management', 'commerce', 'automation']")
);
guide_assert('manifest concepts', count($manifest['concepts'] ?? []) >= 6);

$journeys = birkly_guide_resolve_journeys($prefs);
guide_assert('resolve journeys', count($journeys) >= 2 && ($journeys[0]['id'] ?? '') === 'first-content');
$firstJourneySteps = $journeys[0]['steps'] ?? [];
guide_assert('first content journey steps', count($firstJourneySteps) >= 5);
guide_assert('visit project step', ($firstJourneySteps[3]['open_in_new_tab'] ?? false) === true);

$concepts = birkly_guide_resolve_concepts();
guide_assert('resolve concepts', count($concepts) >= 6);
$firstConcept = $concepts[0] ?? [];
guide_assert('concept has href', ($firstConcept['href'] ?? '') !== '');

$payload3 = birkly_guide_build_progress_payload($prefs, 'dashboard');
guide_assert('checklist counts', ($payload3['checklist_total'] ?? 0) === 5 && isset($payload3['checklist_done_count']));
guide_assert('payload journeys', count($payload3['journeys'] ?? []) >= 2);

$prefsSnooze = $prefs;
$prefsSnooze['tips_snoozed_until'] = date('c', time() + 86400);
guide_assert('snooze active', birkly_guide_is_tips_snoozed($prefsSnooze));
$payloadSnooze = birkly_guide_build_progress_payload($prefsSnooze, 'content');
guide_assert('snooze suppresses attention', empty($payloadSnooze['attention']));

$prefsAutoOpen = birkly_guide_default_prefs();
$prefsAutoOpen['first_admin_visit_ack_at'] = date('c');
$prefsAutoOpen['guide_auto_open_pending'] = true;
$payloadAutoOpen = birkly_guide_build_progress_payload($prefsAutoOpen, 'dashboard');
guide_assert('auto open pending after onboarding', !empty($payloadAutoOpen['should_auto_open_panel']));
guide_assert('post onboarding banner off', empty($payloadAutoOpen['show_post_onboarding_nudge']));

$adminUser = ['role' => 'admin', 'permissions' => ['*']];
$viewerUser = ['role' => 'viewer', 'permissions' => ['read', 'read_media']];
guide_assert('admin sees full checklist', count(birkly_guide_checklist_items_for_user($adminUser)) === 5);
guide_assert('viewer sees no hub item', count(birkly_guide_checklist_items_for_user($viewerUser)) === 0);

echo "guide_core_test passed.\n";
