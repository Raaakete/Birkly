<?php
/**
 * P106 — Settings presentation keeps the existing controls and uses Birch pieces.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$failed = 0;

function p106_assert(string $label, bool $condition): void
{
    global $failed;
    if (!$condition) {
        echo "FAIL: $label\n";
        $failed++;
        return;
    }
    echo "OK: $label\n";
}

$html = (string) file_get_contents($root . '/admin/templates/settings.html');
$settingsJs = (string) file_get_contents($root . '/admin/js/settings-page.js');
$analyticsJs = (string) file_get_contents($root . '/admin/js/analytics-settings.js');
$deliveryJs = (string) file_get_contents($root . '/admin/js/delivery-status.js');
$projectJs = (string) file_get_contents($root . '/admin/js/project-settings.js');

p106_assert('tab breadcrumb', str_contains($html, 'id="settings-tab-crumb"'));
p106_assert('how it works link', str_contains($html, 'id="settings-how-it-works"'));
p106_assert('analytics preset radios stay in the form', str_contains($html, 'id="analytics-settings-form"') && str_contains($html, 'id="analytics-preset-cards"'));
p106_assert('analytics sheet', str_contains($html, 'id="analytics-preset-sheet"') && str_contains($html, 'is-sheet'));
p106_assert('consent and retention fields remain', str_contains($html, 'id="analytics-consent-text"') && str_contains($html, 'id="analytics-retention-days"'));
p106_assert('csv export remains', str_contains($html, 'id="analytics-csv-export"'));
p106_assert('domain list and report mount', str_contains($html, 'id="domains-list"') && str_contains($html, 'id="domains-report-mount"'));
p106_assert('edit domain still saves role limits and analytics', str_contains($html, 'id="edit-domain-delivery-role"') && str_contains($html, 'id="edit-domain-read-limit"') && str_contains($html, 'id="edit-domain-enable-analytics"'));
p106_assert('scheduled publish url remains', str_contains($html, 'birkly_scheduled_publish_admin_url'));
p106_assert('rebuild control remains', str_contains($html, 'id="open-rebuild-system-btn"'));
p106_assert('update controls remain', str_contains($html, 'id="update-status"') && str_contains($html, 'id="check-updates-btn"') && str_contains($html, 'id="download-update-btn"'));
p106_assert('project file filter', str_contains($html, 'id="project-file-filter"'));
p106_assert('preset name is unchanged', str_contains($analyticsJs, 'name="analytics-preset"'));
p106_assert('report uses metrics and ranked', str_contains($analyticsJs, 'class="metrics"') && str_contains($analyticsJs, 'class="ranked"'));
p106_assert('domain table keeps edit delete and analytics toggle', str_contains($settingsJs, 'data-action="edit"') && str_contains($settingsJs, 'data-action="delete"') && str_contains($settingsJs, 'domain-analytics-toggle'));
p106_assert('permission all checkbox is not submitted', str_contains($settingsJs, 'data-all') && !str_contains($settingsJs, 'name="permissions-all"'));
p106_assert('delivery checks can be shown', str_contains($deliveryJs, 'delivery-status-toggle-checks'));
p106_assert('project filter binds', str_contains($projectJs, 'bindFileFilter'));
$gitJs = (string) file_get_contents($root . '/admin/js/project-git.js');
$hostingJs = (string) file_get_contents($root . '/admin/js/hosting-wizard.js');
$connJs = (string) file_get_contents($root . '/admin/js/connections-settings.js');
p106_assert('git form keeps connect and auth fields', str_contains($gitJs, 'id="project-git-form"') && str_contains($gitJs, 'id="project-git-token"') && str_contains($gitJs, 'id="project-git-connect-btn"') && str_contains($gitJs, 'id="project-git-push-btn"'));
p106_assert('hosting modes stay radios inside choice cards', str_contains($hostingJs, 'choice-card') && str_contains($hostingJs, '-public-mode" value="project"'));
p106_assert('connection rows keep actions', str_contains($connJs, 'list-row sys-conn-card') && str_contains($connJs, 'data-action="set-default"') && str_contains($connJs, 'data-action="test"'));
p106_assert('closing the analytics sheet restores the saved preset', str_contains($analyticsJs, 'function restoreSavedAnalytics'));

foreach (['en', 'de', 'fr', 'ja', 'es'] as $locale) {
    $catalog = json_decode((string) file_get_contents($root . '/lang/' . $locale . '.json'), true);
    $admin = is_array($catalog['admin'] ?? null) ? $catalog['admin'] : [];
    foreach ([
        'settings_how_it_works',
        'settings_plugin_webhooks_note',
        'settings_project_filter_files',
        'settings_project_file_count',
        'settings_domains_summary',
        'analytics_collecting',
        'analytics_change_collecting',
        'delivery_btn_show_checks',
        'delivery_btn_hide_checks',
        'delivery_checks_summary',
    ] as $key) {
        p106_assert($locale . ' has ' . $key, isset($admin[$key]) && is_string($admin[$key]) && $admin[$key] !== '');
    }
}

exit($failed > 0 ? 1 : 0);
