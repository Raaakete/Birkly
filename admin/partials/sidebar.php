<?php
// Helper function to determine if a nav link is active
function is_nav_active(string $page_name): string {
    global $page; // Access the global $page variable from admin/index.php
    return ($page === $page_name) ? 'active' : '';
}

function is_plugins_tab_active(string $tab): bool {
    global $page;
    if ($page !== 'plugins') {
        return false;
    }
    $current = isset($_GET['tab']) ? (string) $_GET['tab'] : 'installed';
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
                        'icon' => $pluginData['navigation']['icon'] ?? '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path></svg>'
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
?><aside class="sidebar">
        <div>
            <div class="sidebar-header">
                <div id="brandingLogoSidebar" class="logo branding-logo">
                    <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo" class="logo-img">
                    <span class="logo-name"><?= htmlspecialchars($projectName) ?></span>
                </div>
            </div>
            <nav class="sidebar-nav">
                <!-- First Section: Main Navigation -->
                <ul class="nav-links" id="links-main">
                    <li class="nav-item">
                        <a href="<?= htmlspecialchars(admin_url('dashboard')) ?>" class="nav-link <?= is_nav_active('dashboard') ?>">
                            <img src="<?= htmlspecialchars(birkly_url('/admin/assets/Speedometer.svg')) ?>" alt="<?= __('admin.dashboard') ?>" class="nav-icon"> <?= __('admin.dashboard') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= htmlspecialchars(admin_url('content')) ?>" class="nav-link <?= is_nav_active('content') || is_nav_active('entries') || is_nav_active('entry-editor') || is_nav_active('collection-editor') ? 'active' : '' ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14,2 14,8 20,8"></polyline>
                                <line x1="16" y1="13" x2="8" y2="13"></line>
                                <line x1="16" y1="17" x2="8" y2="17"></line>
                                <polyline points="10,9 9,9 8,9"></polyline>
                            </svg>
                            <?= __('admin.content') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= htmlspecialchars(admin_url('media')) ?>" class="nav-link <?= is_nav_active('media') ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                <polyline points="21,15 16,10 5,21"></polyline>
                            </svg>
                            <?= __('admin.media') ?>
                        </a>
                    </li>
                </ul>

                <!-- UI Injection Point: admin_navigation_before_plugins -->
                <?php
                if (function_exists('render_ui_injections')) {
                    echo render_ui_injections('admin_navigation_before_plugins', []);
                }
                ?>
                
                <!-- Second Section: Plugins — Marketplace + installed plugin pages -->
                <div class="nav-section" id="nav-plugins-section">
                    <ul class="nav-links" id="links-plugins">
                        <li class="nav-item">
                            <a href="<?= htmlspecialchars(admin_url('plugins', ['tab' => 'marketplace'])) ?>" class="nav-link <?= is_plugins_tab_active('marketplace') ? 'active' : '' ?>">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                                    <line x1="3" y1="6" x2="21" y2="6"></line>
                                    <path d="M16 10a4 4 0 0 1-8 0"></path>
                                </svg>
                                <?= __('admin.marketplace') ?>
                            </a>
                        </li>
                        <?php foreach ($activePluginNavigation as $plugin): ?>
                        <li class="nav-item">
                            <a href="<?= htmlspecialchars(admin_url($plugin['page'])) ?>" class="nav-link <?= is_nav_active($plugin['page']) ?>">
                                <?= $plugin['icon'] ?>
                                <?= htmlspecialchars($plugin['name']) ?>
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
                        <a href="<?= htmlspecialchars(admin_url('settings')) ?>" class="nav-link <?= is_nav_active('settings') || is_nav_active('roles') || is_nav_active('plugins') || is_nav_active('templates') || is_nav_active('audit-log') || is_nav_active('activity') || is_nav_active('updates') || is_nav_active('webhooks') ? 'active' : '' ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="3"></circle>
                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1 1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                            </svg>
                            <?= __('admin.settings') ?>
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
                        <a href="<?= htmlspecialchars(admin_url('feedback')) ?>" class="nav-link <?= is_nav_active('feedback') ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                            </svg>
                            Alpha Feedback
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a href="<?= htmlspecialchars(admin_url('help')) ?>" class="nav-link <?= is_nav_active('help') ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                                <line x1="12" y1="17" x2="12.01" y2="17"></line>
                            </svg>
                            <?= __('admin.help') ?>
                        </a>
                    </li>
                </ul>
                </nav>
                </div>
            </aside>
