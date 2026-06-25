<?php
// Helper function to determine if a nav link is active
function is_nav_active(string $page_name): string {
    global $page; // Access the global $page variable from admin/index.php
    return ($page === $page_name) ? 'active' : '';
}

// Helper function to get active plugin navigation
function get_active_plugin_navigation(): array {
    $navigation = [];
    
    // Load plugin settings to check which plugins are active
    $pluginsSettingsFile = __DIR__ . '/../../settings/plugins.json';
    
    if (file_exists($pluginsSettingsFile)) {
        $pluginsSettings = json_decode(file_get_contents($pluginsSettingsFile), true);
        
        foreach ($pluginsSettings as $pluginId => $pluginConfig) {
            if ($pluginConfig['active'] ?? false) {
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
$defaultLogo = '/admin/assets/birkly-icon.svg';
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
                <div id="brandingLogoSidebar" class="logo" style="display: flex; align-items: center; gap: 0.75rem;">
                    <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo" style="width: 42px; height: 42px; flex-shrink: 0;">
                    <span style="color: white; font-weight: 600; font-size: 1rem;"><?= htmlspecialchars($projectName) ?></span>
                </div>
            </div>
            <nav class="sidebar-nav">
                <!-- First Section: Main Navigation -->
                <ul class="nav-links" id="links-main">
                    <li class="nav-item">
                        <a href="<?= htmlspecialchars(admin_url('dashboard')) ?>" class="nav-link <?= is_nav_active('dashboard') ?>">
                            <img src="/admin/assets/Speedometer.svg" alt="<?= __('admin.dashboard') ?>" class="nav-icon mr-xs"> <?= __('admin.dashboard') ?>
                        </a>
                    </li>
                    <li class="nav-item <?= is_nav_active('content') || is_nav_active('entries') || is_nav_active('entry-editor') || is_nav_active('collection-editor') ? 'active' : '' ?>">
                        <a href="<?= htmlspecialchars(admin_url('content')) ?>" class="nav-link">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="mr-xs">
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
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="mr-xs">
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
                
                <!-- Second Section: Plugins (only show if plugins exist) -->
                <?php if (!empty($activePluginNavigation)): ?>
                <div class="nav-section" id="nav-plugins-section">
                    <ul class="nav-links" id="links-plugins">
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
                <?php endif; ?>
                
                <!-- UI Injection Point: admin_navigation_after_plugins -->
                <?php
                if (function_exists('render_ui_injections')) {
                    echo render_ui_injections('admin_navigation_after_plugins', []);
                }
                ?>

                <!-- Third Section: Settings, Help, Logout -->
                <ul class="nav-links" id="links-settings">
                    <li class="nav-item <?= is_nav_active('settings') || is_nav_active('roles') || is_nav_active('plugins') || is_nav_active('templates') || is_nav_active('audit-log') || is_nav_active('activity') || is_nav_active('updates') || is_nav_active('webhooks') ? 'active' : '' ?>">
                        <a href="<?= htmlspecialchars(admin_url('settings')) ?>" class="nav-link">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="mr-xs">
                                <circle cx="12" cy="12" r="3"></circle>
                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1 1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                            </svg>
                            <?= __('admin.settings') ?>
                        </a>
                    </li>
                    <li class="nav-item <?= is_nav_active('automation') ? 'active' : '' ?>">
                        <a href="<?= htmlspecialchars(admin_url('automation')) ?>" class="nav-link">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="mr-xs">
                                <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                            </svg>
                            Automation
                        </a>
                    </li>
                    <?php
                    $canManageFeedback = isset($_SESSION['user']) && function_exists('checkPermission')
                        && checkPermission($_SESSION['user'], 'manage_settings');
                    if ($canManageFeedback):
                    ?>
                    <li class="nav-item">
                        <a href="<?= htmlspecialchars(admin_url('feedback')) ?>" class="nav-link <?= is_nav_active('feedback') ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="mr-xs">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                            </svg>
                            Alpha Feedback
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a href="<?= htmlspecialchars(admin_url('help')) ?>" class="nav-link <?= is_nav_active('help') ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="mr-xs">
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
                <div class="sidebar-footer">
                    <div class="dropdown user-menu-wrapper">
                        <button class="dropdown-trigger user-menu-trigger" id="user-menu-trigger" aria-label="User menu" aria-expanded="false" aria-haspopup="true">
                            <?php if (!empty($userAvatar)): ?>
                                <img src="<?= htmlspecialchars($userAvatar) ?>" alt="<?= htmlspecialchars($userName) ?>" class="user-avatar">
                            <?php else: ?>
                                <div class="user-avatar-initials"><?= htmlspecialchars($avatarInitials) ?></div>
                            <?php endif; ?>
                            <div class="user-menu-info">
                                <div class="user-menu-name"><?= htmlspecialchars($userName) ?></div>
                            </div>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="user-menu-chevron">
                                <polyline points="6,9 12,15 18,9"></polyline>
                            </svg>
                        </button>
                        <div class="dropdown-menu dropdown-menu-right user-menu-dropdown" id="user-menu-dropdown">
                            <div class="user-menu-header">
                                <div class="user-menu-avatar">
                                    <?php if (!empty($userAvatar)): ?>
                                        <img src="<?= htmlspecialchars($userAvatar) ?>" alt="<?= htmlspecialchars($userName) ?>">
                                    <?php else: ?>
                                        <div class="user-avatar-initials"><?= htmlspecialchars($avatarInitials) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="user-menu-info">
                                    <div class="user-menu-name"><?= htmlspecialchars($userName) ?></div>
                                    <div class="user-menu-email"><?= htmlspecialchars($userEmail) ?></div>
                                    <div class="user-menu-role"><?= htmlspecialchars($userRole) ?></div>
                                </div>
                            </div>
                            <div class="user-menu-divider"></div>
                            <div class="user-menu-items">
                                <a href="<?= htmlspecialchars(admin_url('account')) ?>" class="user-menu-item">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="12" cy="7" r="4"></circle>
                                    </svg>
                                    <span>Account Settings</span>
                                </a>
                                <div class="user-menu-item user-menu-language">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <line x1="2" y1="12" x2="22" y2="12"></line>
                                        <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                                    </svg>
                                    <select id="user-language-select" class="user-language-select" aria-label="Select language">
                                        <?php foreach ($availableLangs as $langCode => $langInfo): ?>
                                            <option value="<?= htmlspecialchars($langCode) ?>" <?= $langCode === $currentLang ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($langInfo['native_name']) ?> <?= htmlspecialchars($langInfo['flag']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="user-menu-divider"></div>
                                <button class="user-menu-item user-menu-logout" id="logout-btn">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                        <polyline points="16,17 21,12 16,7"></polyline>
                                        <line x1="21" y1="12" x2="9" y2="12"></line>
                                    </svg>
                                    <span><?= __('admin.logout') ?></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
        </aside>
