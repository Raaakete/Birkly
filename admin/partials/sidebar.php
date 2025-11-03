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
?>
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div id="brandingLogoSidebar" class="logo">
                    <img src="/admin/assets/haru-logo.svg" alt="Logo">
                </div>
            </div>
            <nav class="sidebar-nav">
                <!-- First Section: Main Navigation -->
                <ul class="nav-links" id="links-main">
                    <li class="nav-item">
                        <a href="<?= htmlspecialchars(admin_url('dashboard')) ?>" class="nav-link <?= is_nav_active('dashboard') ?>">
                            <img src="/admin/assets/Speedometer.svg" alt="Dashboard" class="nav-icon"> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= htmlspecialchars(admin_url('content')) ?>" class="nav-link <?= is_nav_active('content') || is_nav_active('entries') || is_nav_active('entry-editor') || is_nav_active('collection-editor') ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14,2 14,8 20,8"></polyline>
                                <line x1="16" y1="13" x2="8" y2="13"></line>
                                <line x1="16" y1="17" x2="8" y2="17"></line>
                                <polyline points="10,9 9,9 8,9"></polyline>
                            </svg>
                            Content
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= htmlspecialchars(admin_url('media')) ?>" class="nav-link <?= is_nav_active('media') ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                <polyline points="21,15 16,10 5,21"></polyline>
                            </svg>
                            Media
                        </a>
                    </li>
                </ul>

                <!-- Second Section: Plugins (only show if plugins exist) -->
                <?php if (!empty($activePluginNavigation)): ?>
                <div class="nav-section" id="nav-plugins-section">
                    <div class="nav-section-header">
                        <span class="nav-section-title">Plugins</span>
                    </div>
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

                <!-- Third Section: Settings, Help, Logout -->
                <ul class="nav-links" id="links-settings">
                    <li class="nav-item">
                        <a href="<?= htmlspecialchars(admin_url('settings')) ?>" class="nav-link <?= is_nav_active('settings') || is_nav_active('users') || is_nav_active('roles') || is_nav_active('plugins') || is_nav_active('templates') || is_nav_active('audit-log') || is_nav_active('activity') || is_nav_active('updates') ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="3"></circle>
                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1 1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                            </svg>
                            Settings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= htmlspecialchars(admin_url('help')) ?>" class="nav-link <?= is_nav_active('help') ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                                <line x1="12" y1="17" x2="12.01" y2="17"></line>
                            </svg>
                            Help
                        </a>
                    </li>
                    <li class="nav-item">
                        <div class="user-info">
                            <div class="user-avatar">
                                <?= strtoupper(substr(getCurrentUser()['email'] ?? 'U', 0, 1)) ?>
                            </div>
                            <div class="user-details">
                                <div class="user-name"><?= htmlspecialchars(getCurrentUser()['email'] ?? 'User') ?></div>
                                <div class="user-role"><?= htmlspecialchars(ucfirst(getCurrentUser()['role'] ?? 'viewer')) ?></div>
                            </div>
                        </div>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link logout-btn" id="logout-btn">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                <polyline points="16,17 21,12 16,7"></polyline>
                                <line x1="21" y1="12" x2="9" y2="12"></line>
                            </svg>
                            Logout
                        </button>
                    </li>
                </ul>
            </nav>
        </aside>
