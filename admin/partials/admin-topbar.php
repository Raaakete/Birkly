<?php
/**
 * Global admin top bar — user menu, search, notifications (always visible, including error pages).
 */
require_once __DIR__ . '/../../core/project_site.php';
require_once __DIR__ . '/../../api/storage.php';

if (!function_exists('birkly_icon')) {
    require_once __DIR__ . '/../../core/icons.php';
}

$projectSettingsStorage = new Storage(__DIR__ . '/../../settings');
$projectTabSettings = $projectSettingsStorage->read('project.json') ?? [];
$showVisitProjectButton = !empty($projectTabSettings['show_visit_button']);
$visitProjectUrl = '';
if ($showVisitProjectButton) {
    $visitProjectUrl = birkly_public_site_visit_url();
}
?>
<header class="admin-topbar" id="admin-topbar">
    <button class="topbar-icon-btn sidebar-toggle" type="button" id="sidebar-toggle" aria-label="Open menu" aria-expanded="false" aria-controls="admin-sidebar">
        <?= birkly_icon('list') ?>
    </button>
    <div class="admin-topbar-left">
        <nav class="admin-topbar-breadcrumbs" id="admin-topbar-breadcrumbs" aria-label="Breadcrumb"></nav>
    </div>
    <div class="admin-topbar-spacer"></div>
    <div class="page-header-actions admin-topbar-actions">
        <?php
        if (!isset($__birklyDeveloperModeOn)) {
            if (!function_exists('birkly_developer_mode_enabled') && is_file(__DIR__ . '/../../core/developer_mode.php')) {
                require_once __DIR__ . '/../../core/developer_mode.php';
            }
            $__birklyDeveloperModeOn = function_exists('birkly_developer_mode_enabled') && birkly_developer_mode_enabled();
        }
        ?>
        <span id="developer-mode-badge" class="badge badge-neutral badge-sm developer-mode-badge"<?= empty($__birklyDeveloperModeOn) ? ' hidden' : '' ?> title="Developer mode is on">Developer mode</span>
        <?php if ($showVisitProjectButton && $visitProjectUrl !== ''): ?>
        <a href="<?= htmlspecialchars($visitProjectUrl) ?>" id="admin-visit-project-btn" class="btn btn-secondary btn-sm admin-visit-project-btn" target="_blank" rel="noopener noreferrer">
            Visit project
        </a>
        <?php endif; ?>
        <div class="topbar-control-group" role="group" aria-label="Workspace controls">
            <?php include __DIR__ . '/theme-switcher.php'; ?>
            <button class="topbar-icon-btn" type="button" aria-label="Search" id="topbar-search-btn">
                <?= birkly_icon('magnifying-glass') ?>
            </button>
            <div class="dropdown notifications-wrapper">
                <button class="dropdown-trigger topbar-icon-btn notifications-btn" type="button" id="notifications-btn" aria-label="Notifications" aria-expanded="false" aria-haspopup="true">
                    <?= birkly_icon('bell') ?>
                    <span class="notification-badge" id="notification-badge" style="display: none;">0</span>
                </button>
            </div>
            <?php include __DIR__ . '/user-menu.php'; ?>
        </div>
    </div>
</header>
