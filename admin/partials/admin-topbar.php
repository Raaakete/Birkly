<?php
/**
 * Global admin top bar — user menu, search, notifications (always visible, including error pages).
 */
?>
<header class="admin-topbar" id="admin-topbar">
    <div class="admin-topbar-spacer"></div>
    <div class="page-header-actions admin-topbar-actions">
        <button class="topbar-icon-btn" type="button" aria-label="Search" id="topbar-search-btn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="7"></circle>
                <line x1="16.5" y1="16.5" x2="21" y2="21"></line>
            </svg>
        </button>
        <div class="dropdown notifications-wrapper">
            <button class="dropdown-trigger topbar-icon-btn notifications-btn" type="button" id="notifications-btn" aria-label="Notifications" aria-expanded="false" aria-haspopup="true">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 8a6 6 0 10-12 0c0 7-3 9-3 9h18s-3-2-3-9"></path>
                    <path d="M13.73 21a2 2 0 01-3.46 0"></path>
                </svg>
                <span class="notification-badge" id="notification-badge" style="display: none;">0</span>
            </button>
        </div>
        <?php include __DIR__ . '/user-menu.php'; ?>
    </div>
</header>
