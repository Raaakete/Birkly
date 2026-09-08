        </main>
    </div>

<?php
if (!function_exists('birkly_csp_nonce_attr')) {
    require_once __DIR__ . '/../../core/csp.php';
}
if (!function_exists('birkly_build_info')) {
    require_once __DIR__ . '/../../core/build_info.php';
}
$__birklyScriptNonceAttr = birkly_csp_nonce_attr();
$__birklyAssetVersion = htmlspecialchars(birkly_admin_asset_version('admin/js/tabs-v2.js'), ENT_QUOTES, 'UTF-8');
?>

<script src="<?= htmlspecialchars(birkly_url('/admin/js/icon.js')) ?>"<?= $__birklyScriptNonceAttr ?>></script>
<script src="<?= htmlspecialchars(birkly_url('/admin/js/utils.js')) ?>"<?= $__birklyScriptNonceAttr ?>></script>
<script src="<?= htmlspecialchars(birkly_url('/admin/js/activity-rows.js')) ?>"<?= $__birklyScriptNonceAttr ?>></script>
<script src="<?= htmlspecialchars(birkly_url('/admin/js/i18n.js')) ?>"<?= $__birklyScriptNonceAttr ?>></script>
<script src="<?= htmlspecialchars(birkly_url('/admin/js/ui-feedback.js')) ?>"<?= $__birklyScriptNonceAttr ?>></script>
<script src="<?= htmlspecialchars(birkly_url('/admin/js/delegated-actions.js')) ?>"<?= $__birklyScriptNonceAttr ?>></script>
<script src="<?= htmlspecialchars(birkly_url('/admin/js/fieldtype-config.js')) ?>"<?= $__birklyScriptNonceAttr ?>></script>
<script src="<?= htmlspecialchars(birkly_url('/admin/js/tabs-a11y.js')) ?>"<?= $__birklyScriptNonceAttr ?>></script>
<script src="<?= htmlspecialchars(birkly_url('/admin/js/sticky-save-bar.js')) ?>"<?= $__birklyScriptNonceAttr ?>></script>
<script src="<?= htmlspecialchars(birkly_url('/admin/js/tabs-v2.js')) ?>?v=<?= $__birklyAssetVersion ?>"<?= $__birklyScriptNonceAttr ?>></script>
<script src="<?= htmlspecialchars(birkly_url('/admin/js/theme.js')) ?>"<?= $__birklyScriptNonceAttr ?>></script>
<script src="<?= htmlspecialchars(birkly_url('/admin/js/dropdown.js')) ?>"<?= $__birklyScriptNonceAttr ?>></script>
<script src="<?= htmlspecialchars(birkly_url('/admin/js/custom-select.js')) ?>"<?= $__birklyScriptNonceAttr ?>></script>
<script src="<?= htmlspecialchars(birkly_url('/admin/js/feedback-button.js')) ?>"<?= $__birklyScriptNonceAttr ?>></script>
<script src="<?= htmlspecialchars(birkly_url('/admin/js/ai-chat-button.js')) ?>"<?= $__birklyScriptNonceAttr ?>></script>
<script src="<?= htmlspecialchars(birkly_url('/admin/js/contextual-help.js')) ?>"<?= $__birklyScriptNonceAttr ?>></script>
    <script src="<?= htmlspecialchars(birkly_url('/admin/js/fieldtypes-shim.js')) ?>"<?= $__birklyScriptNonceAttr ?>></script>
    <script src="<?= htmlspecialchars(birkly_url('/admin/js/media-modal.js?v=20260803-1')) ?>"<?= $__birklyScriptNonceAttr ?>></script>
    <script src="<?= htmlspecialchars(birkly_url('/admin/js/accessibility.js')) ?>"<?= $__birklyScriptNonceAttr ?>></script>
<script src="<?= htmlspecialchars(birkly_url('/admin/js/breadcrumb-truncate.js')) ?>?v=<?= $__birklyAssetVersion ?>"<?= $__birklyScriptNonceAttr ?>></script>
    <?php
    if (!function_exists('birkly_render_admin_script_tags')) {
        require_once __DIR__ . '/../../core/plugins/admin_asset_shelf.php';
    }
    $birklyAdminPage = isset($page) ? (string) $page : null;
    if ($birklyAdminPage === 'dashboard') {
        echo '<script src="' . htmlspecialchars(birkly_url('/admin/js/dashboard-mount-helpers.js'), ENT_QUOTES, 'UTF-8') . '"' . $__birklyScriptNonceAttr . '></script>' . "\n";
        echo '<script src="' . htmlspecialchars(birkly_url('/admin/js/dashboard-widgets.js'), ENT_QUOTES, 'UTF-8') . '?v=' . $__birklyAssetVersion . '"' . $__birklyScriptNonceAttr . '></script>' . "\n";
        echo birkly_render_admin_script_tags($birklyAdminPage);
        echo '<script src="' . htmlspecialchars(birkly_url('/admin/js/dashboard.js'), ENT_QUOTES, 'UTF-8') . '?v=' . $__birklyAssetVersion . '"' . $__birklyScriptNonceAttr . '></script>' . "\n";
    } else {
        echo birkly_render_admin_script_tags($birklyAdminPage);
    }
    ?>
    <?php if (isset($page) && $page === 'media'): ?><script src="<?= htmlspecialchars(birkly_url('/admin/js/media-page.js')) ?>?v=<?= $__birklyAssetVersion ?>"<?= $__birklyScriptNonceAttr ?>></script><?php endif; ?>

    <script<?= $__birklyScriptNonceAttr ?>>
    // If user was redirected due to missing permission, show unified modal.
    (function() {
        try {
            const params = new URLSearchParams(window.location.search);
            if (params.get('permission_denied') === '1') {
                const from = params.get('from') || '';
                const needed = params.get('needed') || '';
                if (window.Birkly && window.Birkly.Utils && typeof window.Birkly.Utils.showPermissionDenied === 'function') {
                    window.Birkly.Utils.showPermissionDenied({
                        action: from ? `Open ${from}` : 'Open page',
                        url: needed ? `Requires: ${needed}` : ''
                    });
                }
                // Clean URL (remove params)
                params.delete('permission_denied');
                params.delete('from');
                params.delete('needed');
                const newQuery = params.toString();
                const newUrl = window.location.pathname + (newQuery ? `?${newQuery}` : '') + window.location.hash;
                window.history.replaceState({}, '', newUrl);
            }
        } catch (_) {}
    })();
    </script>
    
    <!-- Logout functionality -->
    <script<?= $__birklyScriptNonceAttr ?>>
    document.addEventListener('DOMContentLoaded', function() {
        const logoutBtn = document.getElementById('logout-btn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', function() {
                if (confirm('Are you sure you want to logout?')) {
                    logoutBtn.textContent = 'Logging out...';
                    logoutBtn.disabled = true;
                    
                    fetch(Birkly.url('/admin/logout.php'), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: '_csrf=' + encodeURIComponent(document.querySelector('meta[name="csrf-token"]').getAttribute('content'))
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'ok') {
                            window.location.href = Birkly.url('/admin/login.php');
                        } else {
                            alert('Error logging out: ' + data.message);
                            logoutBtn.textContent = 'Logout';
                            logoutBtn.disabled = false;
                        }
                    })
                    .catch(error => {
                        console.error('Logout error:', error);
                        alert('Error logging out. Please try again.');
                        logoutBtn.textContent = 'Logout';
                        logoutBtn.disabled = false;
                    });
                }
            });
        }
    });
    
    // User menu lives in admin-topbar.php — legacy move only when topbar is absent.
    (function() {
        function moveBreadcrumbsToTopbar() {
            const topbar = document.getElementById('admin-topbar');
            const topbarSlot = document.getElementById('admin-topbar-breadcrumbs');
            if (!topbar || !topbarSlot || topbarSlot.childElementCount > 0) {
                return;
            }

            const source = document.querySelector(
                '.content-page .page-header-left .breadcrumbs, ' +
                '.page-header-left .breadcrumbs:not(#admin-topbar-breadcrumbs), ' +
                '.content-header .breadcrumbs'
            );

            if (!source || source === topbarSlot || source.closest('#admin-topbar')) {
                return;
            }

            const pageHeaderLeft = source.parentElement;
            const pageHeader = pageHeaderLeft ? pageHeaderLeft.closest('.page-header') : null;

            while (source.firstChild) {
                const child = source.firstChild;
                if (child.nodeType === Node.ELEMENT_NODE && child.classList.contains('contextual-help-link')) {
                    child.remove();
                    continue;
                }
                topbarSlot.appendChild(child);
            }
            topbarSlot.querySelectorAll('.contextual-help-link').forEach((link) => link.remove());
            topbarSlot.classList.add('breadcrumbs');
            source.remove();

            if (pageHeaderLeft && pageHeaderLeft.classList.contains('page-header-left') && pageHeaderLeft.childElementCount === 0) {
                pageHeaderLeft.style.display = 'none';
            }

            if (pageHeader) {
                const left = pageHeader.querySelector('.page-header-left');
                const actions = pageHeader.querySelector('.page-header-actions');
                const leftHidden = !left || left.style.display === 'none' || left.childElementCount === 0;
                const actionsEmpty = !actions || actions.childElementCount === 0;
                if (leftHidden && actionsEmpty) {
                    pageHeader.classList.add('page-header--hoisted-empty');
                    pageHeader.style.display = 'none';
                    pageHeader.style.marginBottom = '0';
                    pageHeader.style.height = '0';
                    pageHeader.style.minHeight = '0';
                }
            }

            if (window.Birkly && window.Birkly.BreadcrumbTruncate && typeof window.Birkly.BreadcrumbTruncate.init === 'function') {
                window.Birkly.BreadcrumbTruncate.init();
            }
        }

        function moveUserMenuToHeader() {
            if (document.getElementById('admin-topbar')) {
                return;
            }
            const userMenuWrapper = document.querySelector('.sidebar-footer .user-menu-wrapper');
            const pageHeaderActions = document.querySelector('.page-header-actions');

            if (userMenuWrapper && pageHeaderActions) {
                // Move the existing user menu into the header
                pageHeaderActions.appendChild(userMenuWrapper);
                userMenuWrapper.classList.add('topbar-user-menu');

                // Add search and notification buttons to the left of the avatar
                const fragment = document.createDocumentFragment();

                const searchBtn = document.createElement('button');
                searchBtn.className = 'topbar-icon-btn';
                searchBtn.type = 'button';
                searchBtn.setAttribute('aria-label', 'Search');
                searchBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><line x1="16.5" y1="16.5" x2="21" y2="21"></line></svg>';

                // Create wrapper for notifications button to position dropdown - Unified Dropdown System
                const notificationsWrapper = document.createElement('div');
                notificationsWrapper.className = 'dropdown notifications-wrapper';
                
                const notificationsBtn = document.createElement('button');
                notificationsBtn.className = 'dropdown-trigger topbar-icon-btn notifications-btn';
                notificationsBtn.type = 'button';
                notificationsBtn.id = 'notifications-btn';
                notificationsBtn.setAttribute('aria-label', 'Notifications');
                notificationsBtn.setAttribute('aria-expanded', 'false');
                notificationsBtn.setAttribute('aria-haspopup', 'true');
                notificationsBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8a6 6 0 10-12 0c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 01-3.46 0"></path></svg><span class="notification-badge" id="notification-badge" style="display: none;">0</span>';
                
                notificationsWrapper.appendChild(notificationsBtn);

                fragment.appendChild(searchBtn);
                fragment.appendChild(notificationsWrapper);
                pageHeaderActions.insertBefore(fragment, userMenuWrapper);
                
                // Trigger notifications setup after button is created
                if (window.setupNotifications) {
                    setTimeout(window.setupNotifications, 100);
                }
            } else {
                console.warn('[UI Fix] Could not find userMenuWrapper or pageHeaderActions');
            }

            const userMenuTrigger = document.getElementById('user-menu-trigger');
            const userMenuDropdown = document.getElementById('user-menu-dropdown');
            
            // User menu is now handled by unified dropdown.js
            // Just ensure it has the right classes
            if (userMenuTrigger && userMenuDropdown) {
                // Make sure trigger has dropdown-trigger class
                if (!userMenuTrigger.classList.contains('dropdown-trigger')) {
                    userMenuTrigger.classList.add('dropdown-trigger');
                }
            }
        }
        
        // Run immediately if DOM is ready, otherwise wait for DOMContentLoaded
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                moveBreadcrumbsToTopbar();
                moveUserMenuToHeader();
            });
        } else {
            moveBreadcrumbsToTopbar();
            moveUserMenuToHeader();
        }
    })();
    
    // Language switcher (user-level)
    const languageSelect = document.getElementById('user-language-select');
    if (languageSelect) {
        languageSelect.addEventListener('change', function() {
            const language = this.value;
            
            fetch(Birkly.url('/api/users.php'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'set_language',
                    language: language,
                    _csrf: document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'ok') {
                    // Reload page to apply new language
                    window.location.reload();
                } else {
                    alert('Error changing language: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Language change error:', error);
                alert('Error changing language. Please try again.');
            });
        });
    }
    
    </script>
    
    <?php include __DIR__ . '/global-search-modal.php'; ?>
    <script src="<?= htmlspecialchars(birkly_url('/admin/js/global-search.js')) ?>?v=<?= $__birklyAssetVersion ?>"<?= $__birklyScriptNonceAttr ?>></script>
    
    <!-- Notifications Dropdown (will be moved to notifications-wrapper by JS) - Unified Dropdown System -->
    <div class="dropdown-menu dropdown-menu-right notifications-dropdown" id="notifications-dropdown">
        <div class="notifications-header">
            <h3>Notifications</h3>
            <button type="button" class="btn btn-ghost btn-sm mark-all-read-btn" id="mark-all-read-btn" hidden>Mark all as read</button>
        </div>
        <div class="notifications-list" id="notifications-list">
            <div class="notifications-loading">Loading notifications...</div>
        </div>
        <div class="notifications-footer">
            <a href="<?= htmlspecialchars(birkly_admin_url('activity')) ?>">View all activity</a>
        </div>
    </div>
    
    <!-- Notifications Functionality -->
    <script<?= $__birklyScriptNonceAttr ?>>
    (function() {
        let notifications = [];
        let unreadCount = 0;
        let pendingCount = 0;
        let badgeState = 'none';
        let isDropdownOpen = false;
        
        const notificationsBtn = document.getElementById('notifications-btn');
        const notificationsDropdown = document.getElementById('notifications-dropdown');
        const notificationsList = document.getElementById('notifications-list');
        const notificationBadge = document.getElementById('notification-badge');
        const markAllReadBtn = document.getElementById('mark-all-read-btn');
        
        // Expose loadNotifications globally for test button
        window.loadNotifications = loadNotifications;
        
        // Load notifications
        function loadNotifications() {
            fetch(Birkly.url('/api/notifications.php?action=list_recent&limit=20'))
                .then(response => {
                    if (!response.ok) {
                        throw new Error('HTTP error! status: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.status === 'ok') {
                        notifications = data.notifications || [];
                        unreadCount = data.unread_count || 0;
                        pendingCount = data.pending_count || 0;
                        badgeState = data.badge_state || (pendingCount > 0 ? 'unread' : 'none');
                        updateBadge();
                        renderNotifications();
                        markVisibleNotificationsSeen();
                    } else {
                        console.error('Failed to load notifications:', data.message);
                        showError('Failed to load notifications: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Notifications error:', error);
                    showError('Error loading notifications: ' + error.message);
                });
        }
        
        function updateBadge() {
            const badge = document.getElementById('notification-badge');
            if (!badge) {
                return;
            }
            badge.classList.remove('notification-badge--seen', 'notification-badge--unread');
            if (badgeState === 'none' || pendingCount <= 0) {
                badge.style.display = 'none';
                return;
            }
            badge.textContent = pendingCount > 99 ? '99+' : pendingCount.toString();
            badge.style.display = 'flex';
            badge.classList.add(badgeState === 'unread' ? 'notification-badge--unread' : 'notification-badge--seen');
        }

        function markVisibleNotificationsSeen() {
            const unseenIds = notifications
                .filter(function (n) { return !n.read && !n.seen; })
                .map(function (n) { return n.id; })
                .filter(Boolean);
            if (!unseenIds.length) {
                if (pendingCount > 0 && badgeState === 'unread') {
                    badgeState = 'seen';
                    updateBadge();
                }
                return;
            }
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            if (!csrf) {
                return;
            }
            fetch(Birkly.url('/api/notifications.php'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'mark_seen', ids: unseenIds, _csrf: csrf })
            }).then(function (response) { return response.json(); }).then(function (data) {
                if (data.status !== 'ok') {
                    return;
                }
                notifications.forEach(function (n) {
                    if (unseenIds.indexOf(n.id) !== -1) {
                        n.seen = true;
                    }
                });
                badgeState = pendingCount > 0 ? 'seen' : 'none';
                unreadCount = 0;
                updateBadge();
            }).catch(function () { /* non-critical */ });
        }
        
        // The API tags each notification with a colour word; map it onto the
        // admin's semantic tone helpers rather than a raw colour.
        const NOTIFICATION_TONES = {
            blue: 'u-bg-info-soft',
            green: 'u-bg-success-soft',
            orange: 'u-bg-warning-soft',
            red: 'u-bg-danger-soft',
            purple: 'u-bg-neutral-soft',
            gray: 'u-bg-neutral-soft',
            mint: 'u-bg-accent-soft'
        };

        function notificationToneClass(color) {
            return NOTIFICATION_TONES[color] || 'u-bg-neutral-soft';
        }

        // Render notifications
        function renderNotifications() {
            if (!notificationsList) return;
            
            if (notifications.length === 0) {
                notificationsList.innerHTML = '<div class="notifications-empty">No notifications</div>';
                if (markAllReadBtn) markAllReadBtn.hidden = true;
                return;
            }
            
            const hasUnread = notifications.some(n => !n.read);
            if (markAllReadBtn) {
                markAllReadBtn.hidden = !hasUnread;
            }
            
            const html = notifications.map(notif => {
                const timeAgo = formatTimeAgo(notif.timestamp);
                const readClass = notif.read ? 'read' : 'unread';
                // Use data attributes and event delegation instead of inline onclick
                const dataAttrs = `data-notification-id="${notif.id}" ${notif.link ? `data-notification-link="${notif.link}"` : ''}`;
                
                return `
                    <div class="notification-item ${readClass}" ${dataAttrs}>
                        <div class="notification-icon ${notificationToneClass(notif.color)}">
                            ${notif.icon || '📢'}
                        </div>
                        <div class="notification-content">
                            <div class="notification-message">${escapeHtml(notif.message)}</div>
                            <div class="notification-meta">
                                <span class="notification-user">${escapeHtml(notif.user)}</span>
                                <span class="notification-time">${timeAgo}</span>
                            </div>
                        </div>
                        ${!notif.read ? '<div class="notification-dot"></div>' : ''}
                    </div>
                `;
            }).join('');
            
            notificationsList.innerHTML = html;
            
            // Add click handlers using event delegation
            notificationsList.querySelectorAll('.notification-item').forEach(item => {
                item.style.cursor = 'pointer';
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const notificationId = this.getAttribute('data-notification-id');
                    const link = this.getAttribute('data-notification-link');
                    
                    if (notificationId) {
                        // Mark as read first, then navigate
                        markAsRead(notificationId).then(() => {
                            if (link) {
                                window.location.href = link;
                            }
                        }).catch(error => {
                            console.error('Error marking notification as read:', error);
                            // Still navigate even if marking as read fails
                            if (link) {
                                window.location.href = link;
                            }
                        });
                    } else if (link) {
                        window.location.href = link;
                    }
                });
            });
        }
        
        // Format time ago
        function formatTimeAgo(timestamp) {
            const now = new Date();
            const time = new Date(timestamp);
            const diffMs = now - time;
            const diffMins = Math.floor(diffMs / 60000);
            const diffHours = Math.floor(diffMs / 3600000);
            const diffDays = Math.floor(diffMs / 86400000);
            
            if (diffMins < 1) return 'Just now';
            if (diffMins < 60) return `${diffMins}m ago`;
            if (diffHours < 24) return `${diffHours}h ago`;
            if (diffDays < 7) return `${diffDays}d ago`;
            return time.toLocaleDateString();
        }
        
        // Escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Show error
        function showError(message) {
            if (notificationsList) {
                notificationsList.innerHTML = `<div class="notifications-error">${escapeHtml(message)}</div>`;
            }
        }
        
        // Mark notification as read - returns a Promise
        window.markAsRead = function(notificationId) {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            if (!csrf) {
                console.error('CSRF token not found');
                return Promise.reject('CSRF token not found');
            }
            
            return fetch(Birkly.url('/api/notifications.php'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'mark_read',
                    id: notificationId,
                    _csrf: csrf
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP error! status: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.status === 'ok') {
                    // Update local state
                    const notif = notifications.find(n => n.id === notificationId);
                    if (notif) {
                        notif.read = true;
                        notif.seen = true;
                        pendingCount = Math.max(0, pendingCount - 1);
                        unreadCount = 0;
                        badgeState = pendingCount > 0 ? 'seen' : 'none';
                        updateBadge();
                        renderNotifications();
                    }
                    return data;
                } else {
                    throw new Error(data.message || 'Failed to mark notification as read');
                }
            })
            .catch(error => {
                console.error('Error marking notification as read:', error);
                throw error;
            });
        };
        
        // Mark all as read
        if (markAllReadBtn) {
            markAllReadBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                if (!csrf) {
                    console.error('CSRF token not found');
                    return;
                }
                
                fetch(Birkly.url('/api/notifications.php'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'mark_all_read',
                        _csrf: csrf
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'ok') {
                        notifications.forEach(function (n) {
                            n.read = true;
                            n.seen = true;
                        });
                        unreadCount = 0;
                        pendingCount = 0;
                        badgeState = 'none';
                        updateBadge();
                        renderNotifications();
                    }
                })
                .catch(error => {
                    console.error('Error marking all as read:', error);
                });
            });
        }
        
        // Toggle dropdown - Now handled by unified dropdown.js, but we need to load notifications
        function toggleNotifications() {
            const dropdown = document.getElementById('notifications-dropdown');
            const btn = document.getElementById('notifications-btn');
            if (!dropdown || !btn) return;
            
            // Ensure dropdown is in the right place
            const notificationsWrapper = document.querySelector('.notifications-wrapper');
            if (notificationsWrapper && dropdown.parentNode !== notificationsWrapper) {
                notificationsWrapper.appendChild(dropdown);
            }
            
            // Check if opening (not closing)
            const isOpen = dropdown.classList.contains('show') || dropdown.classList.contains('active') || dropdown.style.display === 'flex';
            if (!isOpen) {
                // Only load when opening
                loadNotifications();
            }
        }
        
        // Expose toggleNotifications globally
        window.toggleNotifications = toggleNotifications;
        
        // Hook into unified dropdown system to load notifications when opened
        document.addEventListener('click', function(e) {
            const btn = document.getElementById('notifications-btn');
            if (btn && btn.contains(e.target)) {
                setTimeout(toggleNotifications, 10);
            }
        });
        
        // Move dropdown to wrapper - must happen after DOM is ready
        function initializeDropdown() {
            const notificationsWrapper = document.querySelector('.notifications-wrapper');
            if (notificationsWrapper && notificationsDropdown) {
                // Only move if not already a child
                if (notificationsDropdown.parentNode !== notificationsWrapper) {
                    notificationsWrapper.appendChild(notificationsDropdown);
                }
            } else {
                console.warn('Notifications wrapper or dropdown not found', {
                    wrapper: !!notificationsWrapper,
                    dropdown: !!notificationsDropdown
                });
            }
        }
        
        // Initialize dropdown and button
        let setupNotificationsAttempts = 0;
        const maxSetupAttempts = 20; // Max 10 seconds (20 * 500ms)
        
        function setupNotifications() {
            // Re-find button in case it was created dynamically
            const btn = document.getElementById('notifications-btn');
            const dropdown = document.getElementById('notifications-dropdown');
            
            if (!btn) {
                setupNotificationsAttempts++;
                if (setupNotificationsAttempts < maxSetupAttempts) {
                    console.warn('Notifications button not found yet, will retry... (' + setupNotificationsAttempts + '/' + maxSetupAttempts + ')');
                    // Retry after a delay
                    setTimeout(setupNotifications, 500);
                } else {
                    console.warn('Notifications button not found after ' + maxSetupAttempts + ' attempts. Giving up.');
                }
                return;
            }
            
            // Reset attempts counter on success
            setupNotificationsAttempts = 0;
            
            if (!dropdown) {
                console.error('Notifications dropdown not found');
                return;
            }
            
            // Ensure dropdown is hidden on initialization
            dropdown.classList.remove('show', 'active');
            dropdown.style.display = 'none';
            
            // Update references
            if (btn !== notificationsBtn) {
                // Button was recreated, update reference
                window.notificationsBtn = btn;
            }
            if (dropdown !== notificationsDropdown) {
                window.notificationsDropdown = dropdown;
            }
            
            // Move dropdown to wrapper FIRST - this is critical for dropdown.js to find it
            initializeDropdown();
            
            // Ensure dropdown is in the wrapper
            const wrapper = document.querySelector('.notifications-wrapper');
            if (wrapper && dropdown.parentNode !== wrapper) {
                wrapper.appendChild(dropdown);
            }
            
            // Re-initialize dropdown.js for this dynamically created button
            // Wait a bit for DOM to settle, then trigger re-initialization
            setTimeout(function() {
                if (window.initDropdowns) {
                    window.initDropdowns();
                }
            }, 50);
            
            // Hook into dropdown open to load notifications
            // Watch for when dropdown becomes visible
            const checkIfOpen = function() {
                const isOpen = dropdown.classList.contains('show') || dropdown.classList.contains('active') || dropdown.style.display === 'flex';
                if (isOpen && !isDropdownOpen) {
                    isDropdownOpen = true;
                    loadNotifications();
                } else if (!isOpen) {
                    isDropdownOpen = false;
                }
            };
            
            // Watch for class/style changes
            const observer = new MutationObserver(checkIfOpen);
            observer.observe(dropdown, {
                attributes: true,
                attributeFilter: ['class', 'style']
            });
            
            // Also check immediately on button click (before dropdown.js toggles)
            btn.addEventListener('click', function() {
                setTimeout(checkIfOpen, 5);
            }, { once: false });
            
        }
        
        // Expose setupNotifications globally so it can be called from button creation code
        window.setupNotifications = setupNotifications;
        
        // Initialize - wait for button to be created
        function initNotifications() {
            // Wait for the button to be created by the other script
            let attempts = 0;
            const maxAttempts = 5; // Reduced since button creation now triggers setup
            
            const checkAndSetup = () => {
                const btn = document.getElementById('notifications-btn');
                if (btn) {
                    // Button found, setup notifications
                    setupNotifications();
                } else if (attempts < maxAttempts) {
                    // Button not found yet, retry
                    attempts++;
                    setTimeout(checkAndSetup, 300);
                }
                // Silently give up if max attempts reached
            };
            
            // Only check if DOM is already loaded, otherwise wait for DOMContentLoaded
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', checkAndSetup);
            } else {
                // DOM already loaded, check immediately
                checkAndSetup();
            }
        }
        
        initNotifications();
        
        // Load unread count on page load
        function loadUnreadCount() {
            fetch(Birkly.url('/api/notifications.php?action=list_recent&limit=20'))
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'ok') {
                        unreadCount = data.unread_count || 0;
                        pendingCount = data.pending_count || 0;
                        badgeState = data.badge_state || (pendingCount > 0 ? 'unread' : 'none');
                        updateBadge();
                    }
                })
                .catch(error => {
                    console.error('Error loading unread count:', error);
                });
        }

        // P61 — live sidebar mint dots (SSR seeds; poll keeps them fresh without reload)
        function applyNavAttention(counts) {
            counts = counts && typeof counts === 'object' ? counts : {};
            document.querySelectorAll('#links-plugins [data-plugin-id], #links-plugins-flyout [data-plugin-id]').forEach(function (item) {
                var pluginId = item.getAttribute('data-plugin-id') || '';
                var has = pluginId !== '' && Number(counts[pluginId] || 0) > 0;
                item.classList.toggle('has-attention', has);
                var link = item.querySelector('.nav-link');
                var label = item.querySelector('.nav-label');
                var name = label ? (label.textContent || '').trim() : pluginId;
                var title = has ? (name + ' (new activity)') : name;
                if (link) {
                    link.setAttribute('title', title);
                    if (has) {
                        link.setAttribute('aria-label', title);
                    } else {
                        link.removeAttribute('aria-label');
                    }
                }
                var dot = item.querySelector('.nav-attention-dot');
                if (dot) {
                    dot.hidden = !has;
                }
            });
            var flyoutDot = document.getElementById('nav-plugins-flyout-dot');
            if (flyoutDot) {
                var any = Object.keys(counts).some(function (id) { return Number(counts[id] || 0) > 0; });
                flyoutDot.hidden = !any;
            }
        }

        function loadNavAttention() {
            fetch(Birkly.url('/api/plugin_activity.php?action=nav_attention'), { credentials: 'same-origin' })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (data && data.status === 'ok') {
                        applyNavAttention((data.data && data.data.counts) || data.counts || {});
                    }
                })
                .catch(function () { /* ignore — non-critical */ });
        }
        
        // Load unread count on page load
        loadUnreadCount();
        loadNavAttention();
        
        // Refresh unread count + nav attention every 30 seconds
        setInterval(function () {
            loadUnreadCount();
            loadNavAttention();
        }, 30000);
        
        // Expose loadUnreadCount globally
        window.loadUnreadCount = loadUnreadCount;
        window.loadNavAttention = loadNavAttention;
    })();
    </script>
    
    <!-- No SPA router/scripts in MPA mode -->
<?php
require_once __DIR__ . '/../../core/platform.php';
if (birkly_alpha_program_enabled()) {
    include __DIR__ . '/feedback-widget.php';
}
?>
</body>
</html>

