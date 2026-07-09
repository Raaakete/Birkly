<?php
/**
 * P19-M6 — Dashboard checklist v2: essential items only, dismiss/hide
 */

$GLOBALS['__HARU_INTERNAL_CALL'] = true;
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../api/storage.php';
require_once __DIR__ . '/../api/onboarding.php';

test_reset_failures();
echo "Onboarding checklist tests\n\n";

$root = dirname(__DIR__);
$adminTemplates = file_get_contents($root . '/api/admin_templates.php');
$dashboardHtml = file_get_contents($root . '/admin/templates/dashboard.html');
$dashboardJs = file_get_contents($root . '/admin/js/dashboard.js');

test_assert('checklist uses first_collection id', strpos($adminTemplates, "'id' => 'first_collection'") !== false);
test_assert('checklist uses first_entry id', strpos($adminTemplates, "'id' => 'first_entry'") !== false);
test_assert('removed setup_complete from checklist', strpos($adminTemplates, "'id' => 'setup_complete'") === false);
test_assert('removed configure_settings from checklist', strpos($adminTemplates, "'id' => 'configure_settings'") === false);
test_assert('removed upload_media checklist item', strpos($adminTemplates, "'id' => 'upload_media'") === false);
test_assert('marketplace plugin item when empty', strpos($adminTemplates, 'install_marketplace_plugins') !== false);
test_assert('checklist_dismissed filter present', strpos($adminTemplates, 'checklist_dismissed') !== false);
test_assert('checklist_hidden respected', strpos($adminTemplates, 'checklist_hidden') !== false);
test_assert('install path hints in checklist', strpos($adminTemplates, 'install_path') !== false);

test_assert('dashboard has hide checklist button', strpos($dashboardHtml, 'hide-checklist-btn') !== false);
test_assert('dashboard has dismiss per item', strpos($dashboardHtml, 'checklist-dismiss') !== false);
test_assert('dashboard hide_checklist API call', strpos($dashboardJs, 'hide_checklist') !== false);
test_assert('dashboard dismiss_checklist_item API call', strpos($dashboardJs, 'dismiss_checklist_item') !== false);

// dismiss/hide API helpers exist
test_assert('onboarding.php has dismiss_checklist_item action', strpos(file_get_contents($root . '/api/onboarding.php'), "case 'dismiss_checklist_item'") !== false);
test_assert('onboarding.php has hide_checklist action', strpos(file_get_contents($root . '/api/onboarding.php'), "case 'hide_checklist'") !== false);

exit(test_finish('onboarding_checklist_test'));
