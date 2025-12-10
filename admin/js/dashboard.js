// Dashboard JavaScript - Enhanced dashboard functionality
(function() {
    'use strict';
    
    let activityFilter = 'all';
    let refreshInterval = null;
    
    // Initialize dashboard when DOM is ready
    function initDashboard() {
        if (document.querySelector('.dashboard-page, .stats-grid')) {
            loadActivityFeed();
            loadNotifications();
            loadPerformanceMetrics();
            loadPluginWidgets();
            
            // Set up auto-refresh every 30 seconds
            refreshInterval = setInterval(() => {
                loadActivityFeed();
                loadNotifications();
                loadPerformanceMetrics();
            }, 30000);
        }
    }
    
    // Load real activity feed
    function loadActivityFeed() {
        const activityContainer = document.querySelector('.activity-list');
        if (!activityContainer) return;
        
        fetch(`/api/dashboard.php?action=activity&limit=20&filter=${activityFilter}`)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'ok' && data.data.activity) {
                    renderActivityFeed(data.data.activity);
                }
            })
            .catch(error => {
                console.error('Error loading activity:', error);
            });
    }
    
    // Render activity feed
    function renderActivityFeed(activities) {
        const container = document.querySelector('.activity-list');
        if (!container) return;
        
        if (activities.length === 0) {
            container.innerHTML = '<p class="empty-state">No recent activity</p>';
            return;
        }
        
        container.innerHTML = activities.map(activity => {
            const timeAgo = formatTimeAgo(activity.timestamp);
            return `
                <div class="activity-item" data-category="${activity.category || 'other'}">
                    <div class="activity-icon">${activity.icon || '📌'}</div>
                    <div class="activity-content">
                        <p>${escapeHtml(activity.message || '')}</p>
                        <span class="activity-time">${timeAgo}</span>
                    </div>
                </div>
            `;
        }).join('');
    }
    
    // Load notifications/alerts
    function loadNotifications() {
        fetch('/api/dashboard.php?action=notifications')
            .then(response => response.json())
            .then(data => {
                if (data.status === 'ok' && data.data.notifications) {
                    renderNotifications(data.data.notifications);
                }
            })
            .catch(error => {
                console.error('Error loading notifications:', error);
            });
    }
    
    // Render notifications
    function renderNotifications(notifications) {
        let container = document.querySelector('.notifications-container');
        
        // Create container if it doesn't exist
        if (!container) {
            const statsGrid = document.querySelector('.stats-grid');
            if (statsGrid && statsGrid.parentNode) {
                container = document.createElement('div');
                container.className = 'notifications-container';
                statsGrid.parentNode.insertBefore(container, statsGrid);
            } else {
                return;
            }
        }
        
        if (notifications.length === 0) {
            container.style.display = 'none';
            return;
        }
        
        container.style.display = 'block';
        container.innerHTML = `
            <div class="notifications-header">
                <h2>Notifications</h2>
                <span class="notification-badge">${notifications.length}</span>
            </div>
            <div class="notifications-list">
                ${notifications.map(notif => `
                    <div class="notification-item notification-${notif.type}" data-priority="${notif.priority}">
                        <div class="notification-icon">${notif.icon || '🔔'}</div>
                        <div class="notification-content">
                            <h4>${escapeHtml(notif.title)}</h4>
                            <p>${escapeHtml(notif.message)}</p>
                        </div>
                        ${notif.link ? `<a href="${escapeHtml(notif.link)}" class="notification-link">View →</a>` : ''}
                    </div>
                `).join('')}
            </div>
        `;
    }
    
    // Load performance metrics
    function loadPerformanceMetrics() {
        fetch('/api/dashboard.php?action=performance')
            .then(response => response.json())
            .then(data => {
                if (data.status === 'ok' && data.data) {
                    renderPerformanceMetrics(data.data);
                }
            })
            .catch(error => {
                console.error('Error loading performance metrics:', error);
            });
    }
    
    // Render performance metrics
    function renderPerformanceMetrics(metrics) {
        let container = document.querySelector('.performance-metrics');
        
        // Create container if it doesn't exist
        if (!container) {
            const dashboardContent = document.querySelector('.dashboard-content');
            if (dashboardContent) {
                container = document.createElement('div');
                container.className = 'performance-metrics';
                dashboardContent.insertBefore(container, dashboardContent.firstChild);
            } else {
                return;
            }
        }
        
        const responseTimeColor = metrics.api_response_time < 100 ? 'good' : 
                                 metrics.api_response_time < 500 ? 'warning' : 'error';
        
        container.innerHTML = `
            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="metric-icon">⚡</div>
                    <div class="metric-content">
                        <h3 class="metric-value metric-${responseTimeColor}">${metrics.api_response_time}ms</h3>
                        <p>API Response Time</p>
                    </div>
                </div>
                <div class="metric-card">
                    <div class="metric-icon">💾</div>
                    <div class="metric-content">
                        <h3 class="metric-value">${metrics.memory.current_mb}MB</h3>
                        <p>Memory Usage (Peak: ${metrics.memory.peak_mb}MB)</p>
                    </div>
                </div>
                <div class="metric-card">
                    <div class="metric-icon">📦</div>
                    <div class="metric-content">
                        <h3 class="metric-value">${metrics.media.size_mb}MB</h3>
                        <p>Media Storage (${metrics.media.count} files)</p>
                    </div>
                </div>
                <div class="metric-card">
                    <div class="metric-icon">🗄️</div>
                    <div class="metric-content">
                        <h3 class="metric-value">${metrics.database.size_mb}MB</h3>
                        <p>Database Size</p>
                    </div>
                </div>
                ${metrics.cache.enabled ? `
                <div class="metric-card">
                    <div class="metric-icon">🚀</div>
                    <div class="metric-content">
                        <h3 class="metric-value">${metrics.cache.files}</h3>
                        <p>Cache Files (${metrics.cache.size_mb}MB)</p>
                    </div>
                </div>
                ` : ''}
            </div>
        `;
    }
    
    // Load plugin widgets
    function loadPluginWidgets() {
        fetch('/api/dashboard.php?action=widgets')
            .then(response => response.json())
            .then(data => {
                if (data.status === 'ok' && data.data.widgets && data.data.widgets.length > 0) {
                    renderPluginWidgets(data.data.widgets);
                }
            })
            .catch(error => {
                console.error('Error loading widgets:', error);
            });
    }
    
    // Load analytics widget
    window.loadAnalyticsWidget = function() {
        const container = document.getElementById('analytics-widget-content');
        if (!container) return;
        
        fetch('/api/dashboard.php?action=analytics')
            .then(response => response.json())
            .then(data => {
                if (data.status === 'ok' && data.data) {
                    const analytics = data.data;
                    
                    // Show setup message if no domains or no analytics enabled
                    if (!analytics.has_domains) {
                        container.innerHTML = `
                            <div style="text-align: center; padding: 1.5rem;">
                                <div style="font-size: 2rem; margin-bottom: 0.5rem;">📊</div>
                                <p style="color: #6b7280; font-size: 0.875rem; margin-bottom: 1rem;">
                                    No domains registered yet. Register a domain to enable analytics tracking.
                                </p>
                                <a href="/admin/index.php?page=settings#domains" class="btn btn-primary" style="font-size: 0.875rem; padding: 0.5rem 1rem;">
                                    Register Domain & Enable Analytics
                                </a>
                            </div>
                        `;
                        return;
                    }
                    
                    if (!analytics.has_analytics) {
                        container.innerHTML = `
                            <div style="text-align: center; padding: 1.5rem;">
                                <div style="font-size: 2rem; margin-bottom: 0.5rem;">📊</div>
                                <p style="color: #6b7280; font-size: 0.875rem; margin-bottom: 1rem;">
                                    Analytics tracking is available. Enable it for your registered domains to start tracking visitor statistics.
                                </p>
                                <a href="/admin/index.php?page=settings#domains" class="btn btn-primary" style="font-size: 0.875rem; padding: 0.5rem 1rem;">
                                    Enable Analytics
                                </a>
                            </div>
                        `;
                        return;
                    }
                    
                    // Show analytics data
                    let html = `
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-bottom: 1rem;">
                            <div style="padding: 0.75rem; background: #f3f4f6; border-radius: 0.5rem;">
                                <div style="font-size: 1.5rem; font-weight: 700; color: #111827;">${analytics.total_page_views.toLocaleString()}</div>
                                <div style="font-size: 0.875rem; color: #6b7280;">Page Views (30d)</div>
                            </div>
                            <div style="padding: 0.75rem; background: #f3f4f6; border-radius: 0.5rem;">
                                <div style="font-size: 1.5rem; font-weight: 700; color: #111827;">${analytics.total_visitors.toLocaleString()}</div>
                                <div style="font-size: 0.875rem; color: #6b7280;">Unique Visitors (30d)</div>
                            </div>
                        </div>
                    `;
                    
                    if (analytics.websites && analytics.websites.length > 0) {
                        html += '<div style="margin-top: 1rem;"><strong style="font-size: 0.875rem; color: #374151;">Top Websites:</strong></div>';
                        html += '<ul style="list-style: none; padding: 0; margin: 0.5rem 0 0 0;">';
                        analytics.websites.slice(0, 3).forEach(site => {
                            html += `
                                <li style="padding: 0.5rem 0; border-bottom: 1px solid #e5e7eb;">
                                    <div style="font-weight: 500; font-size: 0.875rem;">${escapeHtml(site.name)}</div>
                                    <div style="font-size: 0.75rem; color: #6b7280;">${site.page_views.toLocaleString()} views, ${site.visitors.toLocaleString()} visitors</div>
                                </li>
                            `;
                        });
                        html += '</ul>';
                    }
                    
                    html += `<div style="margin-top: 1rem;"><a href="/admin/index.php?page=settings#domains" style="font-size: 0.875rem; color: #3b82f6;">Manage domains & analytics →</a></div>`;
                    
                    container.innerHTML = html;
                } else {
                    container.innerHTML = '<p style="color: #6b7280; font-size: 0.875rem;">No analytics data available</p>';
                }
            })
            .catch(error => {
                console.error('Error loading analytics:', error);
                const container = document.getElementById('analytics-widget-content');
                if (container) {
                    container.innerHTML = '<p style="color: #dc2626; font-size: 0.875rem;">Error loading analytics</p>';
                }
            });
    };
    
    // Render plugin widgets
    function renderPluginWidgets(widgets) {
        let container = document.querySelector('.plugin-widgets-container');
        
        // Create container if it doesn't exist
        if (!container) {
            const dashboardContent = document.querySelector('.dashboard-content');
            if (dashboardContent) {
                container = document.createElement('div');
                container.className = 'plugin-widgets-container';
                dashboardContent.appendChild(container);
            } else {
                return;
            }
        }
        
        container.innerHTML = `
            <h2>Plugin Widgets</h2>
            <div class="widgets-grid">
                ${widgets.map(widget => `
                    <div class="widget-card" data-plugin="${widget.plugin_id || ''}">
                        ${widget.title ? `<div class="widget-header"><h3>${escapeHtml(widget.title)}</h3></div>` : ''}
                        <div class="widget-content">
                            ${widget.html || widget.content || '<p>No content</p>'}
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
        
        // Execute widget JavaScript if provided
        widgets.forEach(widget => {
            if (widget.javascript) {
                try {
                    eval(widget.javascript);
                } catch (e) {
                    console.error('Error executing widget JavaScript:', e);
                }
            }
        });
    }
    
    // Activity filter handler
    function setupActivityFilters() {
        const filterButtons = document.querySelectorAll('.activity-filter-btn');
        filterButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                activityFilter = this.dataset.filter || 'all';
                filterButtons.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                loadActivityFeed();
            });
        });
    }
    
    // Format time ago
    function formatTimeAgo(timestamp) {
        if (!timestamp) return 'Just now';
        
        const date = new Date(timestamp);
        const now = new Date();
        const diff = Math.floor((now - date) / 1000); // seconds
        
        if (diff < 60) return 'Just now';
        if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
        if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
        if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`;
        
        return date.toLocaleDateString();
    }
    
    // Escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Cleanup on page unload
    window.addEventListener('beforeunload', function() {
        if (refreshInterval) {
            clearInterval(refreshInterval);
        }
    });
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDashboard);
    } else {
        initDashboard();
    }
    
    // Setup activity filters after a short delay to ensure DOM is ready
    setTimeout(setupActivityFilters, 100);
})();

