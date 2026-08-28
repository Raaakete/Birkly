<?php
// Helper function to determine if a nav link is active
function is_nav_active(string $page_name): string {
    global $page; // Access the global $page variable from admin/index.php
    if ($page_name === 'agent-guide' && $page === 'ai-presence') {
        return 'active';
    }
    if ($page_name === 'ai-presence' && $page === 'agent-guide') {
        return 'active';
    }
    if (in_array($page_name, ['discovery', 'agent-guide', 'ai-presence'], true)
        && in_array($page, ['discovery', 'agent-guide', 'ai-presence'], true)) {
        return 'active';
    }
    return ($page === $page_name) ? 'active' : '';
}

function is_plugins_tab_active(string $tab): bool {
    global $page;
    if ($page !== 'plugins') {
        return false;
    }
    $current = isset($_GET['tab']) ? (string) $_GET['tab'] : 'discover';
    if ($current === 'marketplace') {
        $current = 'discover';
    }
    // Sidebar Hub link stays active for every Hub IA tab.
    if ($tab === 'marketplace' || $tab === 'discover') {
        return in_array($current, ['discover', 'installed', 'updates', 'licenses', 'upload'], true);
    }
    return $current === $tab;
}

// Helper function to get active plugin navigation
function get_active_plugin_navigation(): array {
    if (function_exists('birkly_plugin_admin_sidebar_navigation')) {
        $shelfNav = birkly_plugin_admin_sidebar_navigation();
        if ($shelfNav !== []) {
            return $shelfNav;
        }
    }

    $navigation = [];
    
    if (!function_exists('birkly_is_plugin_active')) {
        require_once __DIR__ . '/../../core/plugins.php';
    }

    $pluginsSettingsFile = __DIR__ . '/../../settings/plugins.json';
    
    if (file_exists($pluginsSettingsFile)) {
        $pluginsSettings = json_decode(file_get_contents($pluginsSettingsFile), true);
        
        foreach ($pluginsSettings as $pluginId => $pluginConfig) {
            if (!birkly_is_plugin_active($pluginId)) {
                continue;
            }
            $pluginJsonFile = __DIR__ . '/../../plugins/' . $pluginId . '/plugin.json';
            if (file_exists($pluginJsonFile)) {
                $pluginData = json_decode(file_get_contents($pluginJsonFile), true);
                if (isset($pluginData['navigation'])) {
                    $navigation[] = [
                        'id' => $pluginId,
                        'name' => $pluginData['navigation']['name'] ?? $pluginData['name'],
                        'page' => $pluginData['navigation']['page'] ?? $pluginId,
                        'icon' => $pluginData['navigation']['icon'] ?? birkly_icon('gear', ['class' => 'nav-icon'])
                    ];
                }
            }
        }
    }
    
    return $navigation;
}

$activePluginNavigation = get_active_plugin_navigation();

// Load settings to get project name and logo
require_once __DIR__ . '/../../api/storage.php';
$storage = new Storage(__DIR__ . '/../../settings');
$adminSettings = $storage->read('admin.json') ?? [];
$brandingSettings = $storage->read('branding.json') ?? [];

$projectName = $adminSettings['project_name'] ?? 'Birkly';
$logoPath = $brandingSettings['logoPath'] ?? '';
$defaultLogo = birkly_url('/admin/assets/birkly-icon.svg');
$logoUrl = !empty($logoPath) ? $logoPath : $defaultLogo;

// Get current user information for sidebar footer
$currentUser = getCurrentUser();
if (!$currentUser) {
    $currentUser = ['email' => 'User', 'role' => 'viewer', 'name' => 'User', 'avatar' => ''];
}
$userEmail = $currentUser['email'] ?? 'User';
$userRole = ucfirst($currentUser['role'] ?? 'viewer');
$userName = $currentUser['name'] ?? $userEmail;
$userAvatar = $currentUser['avatar'] ?? '';

// Get current language for user
$currentLang = getCurrentLanguage();
$availableLangs = I18n::getAvailableLanguages();

// Generate avatar initials if no avatar
$avatarInitials = '';
if (empty($userAvatar)) {
    $nameParts = explode(' ', $userName);
    if (count($nameParts) >= 2) {
        $avatarInitials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
    } else {
        $avatarInitials = strtoupper(substr($userName, 0, 2));
    }
}
?><aside class="sidebar" id="admin-sidebar">
        <div class="sidebar-body">
            <div class="sidebar-header">
                <div id="brandingLogoSidebar" class="logo branding-logo">
                    <span class="logo-mark" aria-hidden="true">
                        <img src="<?= htmlspecialchars($logoUrl) ?>" alt="" class="logo-img">
                    </span>
                    <span class="logo-name"><?= htmlspecialchars($projectName) ?></span>
                </div>
            </div>
            <nav class="sidebar-nav">
                <!-- First Section: Main Navigation -->
                <ul class="nav-links" id="links-main">
                    <li class="nav-item">
                        <a href="<?= htmlspecialchars(admin_url('dashboard')) ?>" class="nav-link <?= is_nav_active('dashboard') ?>" title="<?= htmlspecialchars(__('admin.dashboard')) ?>">
                            <?= birkly_icon('gauge', ['class' => 'nav-icon']) ?><span class="nav-label"><?= __('admin.dashboard') ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= htmlspecialchars(admin_url('content')) ?>" class="nav-link <?= is_nav_active('content') || is_nav_active('entries') || is_nav_active('entry-editor') || is_nav_active('collection-editor') ? 'active' : '' ?>" title="<?= htmlspecialchars(__('admin.content')) ?>">
                            <?= birkly_icon('file-text', ['class' => 'nav-icon']) ?>
                            <span class="nav-label"><?= __('admin.content') ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= htmlspecialchars(admin_url('media')) ?>" class="nav-link <?= is_nav_active('media') ?>" title="<?= htmlspecialchars(__('admin.media')) ?>">
                            <?= birkly_icon('image', ['class' => 'nav-icon']) ?>
                            <span class="nav-label"><?= __('admin.media') ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= htmlspecialchars(admin_url('discovery')) ?>" class="nav-link <?= is_nav_active('discovery') ?>" title="<?= htmlspecialchars(__('admin.discovery')) ?>">
                            <?= birkly_icon('sparkle', ['class' => 'nav-icon']) ?>
                            <span class="nav-label"><?= __('admin.discovery') ?></span>
                        </a>
                    </li>
                </ul>

                <!-- UI Injection Point: admin_navigation_before_plugins -->
                <?php
                if (function_exists('render_ui_injections')) {
                    echo render_ui_injections('admin_navigation_before_plugins', []);
                }
                ?>
                
                <!-- Second Section: Plugins — Hub + installed plugin pages -->
                <div class="nav-section" id="nav-plugins-section">
                    <ul class="nav-links nav-links--plugin-rail" id="links-plugins-rail">
                        <li class="nav-item nav-plugins-flyout-wrap" id="nav-plugins-flyout-wrap">
                            <button type="button" class="nav-link nav-plugins-flyout-trigger" id="nav-plugins-flyout-trigger" aria-expanded="false" aria-controls="nav-plugins-flyout" title="<?= htmlspecialchars(__('admin.plugins')) ?>" data-sidebar-tooltip="<?= htmlspecialchars(__('admin.plugins')) ?>">
                                <?= birkly_icon('stack', ['class' => 'nav-icon']) ?>
                                <span class="nav-label"><?= __('admin.plugins') ?></span>
                                <span class="nav-attention-dot nav-plugins-flyout-dot" id="nav-plugins-flyout-dot" aria-hidden="true" hidden></span>
                            </button>
                            <div class="nav-plugins-flyout" id="nav-plugins-flyout" hidden>
                                <ul class="nav-links nav-plugins-flyout-list" id="links-plugins-flyout">
                                    <li class="nav-item nav-plugins-flyout-hub">
                                        <a href="<?= htmlspecialchars(admin_url('plugins', ['tab' => 'discover'])) ?>" class="nav-link <?= is_plugins_tab_active('discover') ? 'active' : '' ?>" title="<?= htmlspecialchars(__('admin.marketplace')) ?>">
                                            <?= birkly_icon('shopping-bag', ['class' => 'nav-icon']) ?>
                                            <span class="nav-label"><?= __('admin.marketplace') ?></span>
                                        </a>
                                    </li>
                                    <?php foreach ($activePluginNavigation as $plugin): ?>
                                    <?php
                                        $pluginAttention = !empty($plugin['attention']);
                                        $pluginTitle = $plugin['name'] . ($pluginAttention ? ' (new activity)' : '');
                                    ?>
                                    <li class="nav-item<?= $pluginAttention ? ' has-attention' : '' ?>" data-plugin-id="<?= htmlspecialchars((string) ($plugin['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                        <a href="<?= htmlspecialchars(admin_url($plugin['page'])) ?>" class="nav-link <?= is_nav_active($plugin['page']) ?>" title="<?= htmlspecialchars($pluginTitle) ?>"<?= $pluginAttention ? ' aria-label="' . htmlspecialchars($pluginTitle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                                            <?= $plugin['icon'] ?>
                                            <span class="nav-label"><?= htmlspecialchars($plugin['name']) ?></span>
                                            <span class="nav-attention-dot" aria-hidden="true"<?= $pluginAttention ? '' : ' hidden' ?>></span>
                                        </a>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </li>
                    </ul>
                    <ul class="nav-links" id="links-plugins">
                        <li class="nav-item">
                            <a href="<?= htmlspecialchars(admin_url('plugins', ['tab' => 'discover'])) ?>" class="nav-link <?= is_plugins_tab_active('discover') ? 'active' : '' ?>" title="<?= htmlspecialchars(__('admin.marketplace')) ?>">
                                <?= birkly_icon('shopping-bag', ['class' => 'nav-icon']) ?>
                                <span class="nav-label"><?= __('admin.marketplace') ?></span>
                            </a>
                        </li>
                        <?php foreach ($activePluginNavigation as $plugin): ?>
                        <?php
                            $pluginAttention = !empty($plugin['attention']);
                            $pluginTitle = $plugin['name'] . ($pluginAttention ? ' (new activity)' : '');
                        ?>
                        <li class="nav-item<?= $pluginAttention ? ' has-attention' : '' ?>" data-plugin-id="<?= htmlspecialchars((string) ($plugin['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            <a href="<?= htmlspecialchars(admin_url($plugin['page'])) ?>" class="nav-link <?= is_nav_active($plugin['page']) ?>" title="<?= htmlspecialchars($pluginTitle) ?>"<?= $pluginAttention ? ' aria-label="' . htmlspecialchars($pluginTitle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                                <?= $plugin['icon'] ?>
                                <span class="nav-label"><?= htmlspecialchars($plugin['name']) ?></span>
                                <span class="nav-attention-dot" aria-hidden="true"<?= $pluginAttention ? '' : ' hidden' ?>></span>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <!-- UI Injection Point: admin_navigation_after_plugins -->
                <?php
                if (function_exists('render_ui_injections')) {
                    echo render_ui_injections('admin_navigation_after_plugins', []);
                }
                ?>

                <!-- Third Section: Settings, Help, Logout -->
                <ul class="nav-links" id="links-settings">
                    <li class="nav-item">
                        <a href="<?= htmlspecialchars(admin_url('settings')) ?>" class="nav-link <?= is_nav_active('settings') || is_nav_active('redirects') || is_nav_active('roles') || is_nav_active('templates') || is_nav_active('audit-log') || is_nav_active('activity') || is_nav_active('updates') || is_nav_active('webhooks') ? 'active' : '' ?>" title="<?= htmlspecialchars(__('admin.settings')) ?>">
                            <?= birkly_icon('gear', ['class' => 'nav-icon']) ?>
                            <span class="nav-label"><?= __('admin.settings') ?></span>
                        </a>
                    </li>
                    <?php
                    require_once __DIR__ . '/../../core/platform.php';
                    $canManageFeedback = birkly_feedback_triage_enabled()
                        && isset($_SESSION['user']) && function_exists('checkPermission')
                        && checkPermission($_SESSION['user'], 'manage_settings');
                    if ($canManageFeedback):
                    ?>
                    <li class="nav-item">
                        <a href="<?= htmlspecialchars(admin_url('feedback')) ?>" class="nav-link <?= is_nav_active('feedback') ?>" title="Alpha Feedback">
                            <?= birkly_icon('chat-circle', ['class' => 'nav-icon']) ?>
                            <span class="nav-label"><?= __('admin.alpha_feedback') ?></span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a href="<?= htmlspecialchars(admin_url('help')) ?>" class="nav-link <?= is_nav_active('help') ?>" title="<?= htmlspecialchars(__('admin.help')) ?>">
                            <?= birkly_icon('question', ['class' => 'nav-icon']) ?>
                            <span class="nav-label"><?= __('admin.help') ?></span>
                        </a>
                    </li>
                </ul>
                </nav>
            <button type="button" class="sidebar-collapse-toggle" id="sidebar-collapse-toggle" aria-label="Collapse sidebar" aria-pressed="false" title="Collapse sidebar">
                <?= birkly_icon('caret-left') ?>
                <span class="sidebar-collapse-label">Collapse</span>
            </button>
        </div>
        <?php
        require_once __DIR__ . '/../../core/platform.php';
        if (birkly_alpha_program_enabled()):
        ?>
        <div class="sidebar-footer">
            <p class="sidebar-program-label">
                <span class="sidebar-program-label-full"><?= htmlspecialchars(birkly_program_status_label()) ?></span>
                <span class="sidebar-program-label-short" aria-hidden="true">v1</span>
            </p>
        </div>
        <?php endif; ?>
    </aside>
<div class="sidebar-backdrop" id="sidebar-backdrop" aria-hidden="true"></div>
