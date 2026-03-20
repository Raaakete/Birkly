// Dashboard JavaScript - Enhanced dashboard functionality
(function() {
    'use strict';
    
    let activityFilter = 'all';
    let refreshInterval = null;
    
    // Initialize dashboard when DOM is ready
    function initDashboard() {
        if (document.querySelector('.dashboard-page, .stats-grid')) {
            loadAnalytics();
            loadActivityFeed();
            loadPerformanceMetrics();
            loadPluginWidgets();
            loadRecentEntries();
            
            // Set up auto-refresh every 30 seconds
            refreshInterval = setInterval(() => {
                loadActivityFeed();
                loadPerformanceMetrics();
            }, 30000);
        }
    }
    
    // Load analytics widget
    function loadAnalytics() {
        const section = document.getElementById('analytics-section');
        const container = document.getElementById('analytics-content');
        if (!section || !container) return;
        
        fetch('/api/dashboard.php?action=analytics')
            .then(response => {
                // Try to parse as JSON even if status is not ok
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        // If not JSON, return null
                        console.error('Analytics API returned non-JSON:', text.substring(0, 100));
                        return null;
                    }
                });
            })
            .then(data => {
                if (!data || data.status !== 'ok' || !data.data) {
                    section.style.display = 'none';
                    return;
                }
                
                const analytics = data.data;
                
                // Hide section if no domains or no analytics enabled
                if (!analytics.has_domains || !analytics.has_analytics) {
                    section.style.display = 'none';
                    return;
                }
                
                section.style.display = 'block';
                
                let html = `
                    <div class="analytics-metrics">
                        <div class="analytics-metric">
                            <div class="analytics-value">${(analytics.total_page_views || 0).toLocaleString()}</div>
                            <div class="analytics-label">Page Views (30d)</div>
                        </div>
                        <div class="analytics-metric">
                            <div class="analytics-value">${(analytics.total_visitors || 0).toLocaleString()}</div>
                            <div class="analytics-label">Unique Visitors (30d)</div>
                        </div>
                    </div>
                `;
                
                if (analytics.websites && analytics.websites.length > 0) {
                    html += '<div class="analytics-websites"><h3>Top Websites</h3><ul class="analytics-websites-list">';
                    analytics.websites.slice(0, 3).forEach(site => {
                        html += `
                            <li>
                                <span class="website-name">${escapeHtml(site.name || site.domain)}</span>
                                <span class="website-stats">${(site.page_views || 0).toLocaleString()} views, ${(site.visitors || 0).toLocaleString()} visitors</span>
                            </li>
                        `;
                    });
                    html += '</ul></div>';
                }
                
                container.innerHTML = html;
            })
            .catch(error => {
                // Silently hide section on error - don't spam console
                section.style.display = 'none';
            });
    }
    
    // Load real activity feed
    function loadActivityFeed() {
        const activityContainer = document.getElementById('activity-list');
        if (!activityContainer) return;
        
        activityContainer.innerHTML = '<p class="empty-state loading">Loading...</p>';
        
        fetch(`/api/dashboard.php?action=activity&limit=20&filter=${activityFilter}`)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'ok' && data.data && data.data.activity) {
                    renderActivityFeed(data.data.activity);
                } else {
                    activityContainer.innerHTML = '<p class="empty-state">No recent activity</p>';
                }
            })
            .catch(error => {
                console.error('Error loading activity:', error);
                activityContainer.innerHTML = '<p class="empty-state error">Error loading activity</p>';
            });
    }
    
    // Get activity icon SVG - filter out system update checks
    function getActivityIconSVG(event, icon) {
        // Filter out update_check events - they're not useful
        if (event === 'update_check' || event === 'system_update_check') {
            return null; // Return null to skip this activity
        }
        
        const iconMap = {
            'create_entry': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14,2 14,8 20,8"></polyline><line x1="12" y1="18" x2="12" y2="12"></line><line x1="9" y1="15" x2="15" y2="15"></line></svg>',
            'update_entry': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>',
            'publish_entry': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>',
            'unpublish_entry': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>',
            'create_collection': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>',
            'delete_entry': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>',
            'create_user': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>',
            'login_success': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><polyline points="10 17 15 12 10 7"></polyline><line x1="15" y1="12" x2="3" y2="12"></line></svg>',
            'login_failed': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>',
            'upload_media': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>'
        };
        
        return iconMap[event] || `<div class="activity-icon-emoji">${icon || '📌'}</div>`;
    }
    
    // Render activity feed with better details - filter out update checks
    function renderActivityFeed(activities) {
        const container = document.getElementById('activity-list');
        if (!container) return;
        
        // Filter out update_check events
        const filteredActivities = activities.filter(activity => {
            const event = activity.event || '';
            return event !== 'update_check' && event !== 'system_update_check' && 
                   !activity.message?.toLowerCase().includes('update check');
        });
        
        if (filteredActivities.length === 0) {
            container.innerHTML = '<p class="empty-state">No recent activity</p>';
            return;
        }
        
        container.innerHTML = filteredActivities.map(activity => {
            const timeAgo = formatTimeAgo(activity.timestamp);
            const details = activity.details || {};
            const user = activity.user || 'System';
            const collection = details.collection || details.collection_slug || '';
            const entryTitle = details.title || details.entry_title || '';
            const entryId = details.entry_id || details.id || '';
            
            // Build clearer message
            let detailedMessage = escapeHtml(activity.message || '');
            if (collection && entryTitle) {
                detailedMessage = `${detailedMessage} <span class="activity-detail">in <strong>${escapeHtml(collection)}</strong>: ${escapeHtml(entryTitle)}</span>`;
            } else if (collection) {
                detailedMessage = `${detailedMessage} <span class="activity-detail">in <strong>${escapeHtml(collection)}</strong></span>`;
            } else if (entryTitle) {
                detailedMessage = `${detailedMessage}: <span class="activity-detail"><strong>${escapeHtml(entryTitle)}</strong></span>`;
            }
            
            const iconSVG = getActivityIconSVG(activity.event, activity.icon);
            
            // Skip if icon is null (filtered out)
            if (iconSVG === null) {
                return '';
            }
            
            return `
                <div class="activity-item" data-category="${activity.category || 'other'}">
                    <div class="activity-icon-wrapper">${iconSVG}</div>
                    <div class="activity-content">
                        <div class="activity-message">${detailedMessage}</div>
                        <div class="activity-meta">
                            <span class="activity-user">${escapeHtml(user)}</span>
                            <span class="activity-separator">•</span>
                        <span class="activity-time">${timeAgo}</span>
                        </div>
                    </div>
                </div>
            `;
        }).filter(html => html !== '').join('');
    }
    
    // Load recent entries including sub-entries
    function loadRecentEntries() {
        const container = document.getElementById('recent-entries-list');
        if (!container) return;
        
        // Get all collections and their recent entries
        fetch('/api/content.php?action=list_collections')
            .then(response => response.json())
            .then(data => {
                if (data.status === 'ok' && data.data && Array.isArray(data.data)) {
                    const collections = data.data;
                    const recentEntries = [];
                    
                    // Get recent entries from each collection - read directly from storage
                    const entryPromises = collections.slice(0, 6).map(collection => {
                        const slug = collection.slug || '';
                        // Use admin_entries API which returns entries with status and dates
                        return fetch(`/api/admin_entries.php?action=list&collection=${encodeURIComponent(slug)}`)
                            .then(r => r.json())
                            .then(entryData => {
                                // Handle different response formats
                                let entries = [];
                                if (entryData.entries && Array.isArray(entryData.entries)) {
                                    entries = entryData.entries;
                                } else if (entryData.status === 'ok' && entryData.data && entryData.data.entries) {
                                    entries = entryData.data.entries;
                                } else if (Array.isArray(entryData)) {
                                    entries = entryData;
                                }
                                
                                // Sort by modified date (most recent first) and take first 3
                                entries.sort((a, b) => {
                                    const dateA = new Date(a.modified_at || a.updated_at || a.created_at || 0);
                                    const dateB = new Date(b.modified_at || b.updated_at || b.created_at || 0);
                                    return dateB - dateA;
                                });
                                
                                entries.slice(0, 3).forEach(entry => {
                                    recentEntries.push({
                                        id: entry.id || '',
                                        slug: entry.slug || entry.id || '',
                                        title: entry.title || 'Untitled',
                                        status: entry.status || 'draft',
                                        modified_at: entry.modified_at || entry.updated_at || entry.created_at,
                                        created_at: entry.created_at,
                                        collectionName: collection.name || collection.title || slug,
                                        collectionSlug: slug,
                                        isSubEntry: false
                                    });
                                });
                            })
                            .catch(err => {
                                console.error(`Error loading entries for collection ${slug}:`, err);
                            });
                    });
                    
                    Promise.all(entryPromises).then(() => {
                        // Sort by modified date (most recent first)
                        recentEntries.sort((a, b) => {
                            const dateA = new Date(a.modified_at || a.created_at || 0);
                            const dateB = new Date(b.modified_at || b.created_at || 0);
                            return dateB - dateA;
                        });
                        
                        renderRecentEntries(recentEntries.slice(0, 8));
                    });
                } else {
                    container.innerHTML = '<p class="empty-state">No recent entries</p>';
                }
            })
            .catch(error => {
                console.error('Error loading recent entries:', error);
                container.innerHTML = '<p class="empty-state">Error loading entries</p>';
            });
    }
    
    // Render recent entries including sub-entries
    function renderRecentEntries(entries) {
        const container = document.getElementById('recent-entries-list');
        if (!container) return;
        
        if (entries.length === 0) {
            container.innerHTML = '<p class="empty-state">No recent entries</p>';
            return;
        }
        
        container.innerHTML = entries.map(entry => {
            const modifiedDate = entry.modified_at || entry.updated_at || entry.created_at || '';
            const timeAgo = modifiedDate ? formatTimeAgo(modifiedDate) : '';
            const status = entry.status === 'published' ? 'published' : 'draft';
            const statusLabel = entry.status === 'published' ? 'Published' : 'Draft';
            const isSubEntry = entry.isSubEntry || false;
            const entryTitle = entry.title || entry.name || 'Untitled';
            const entryId = entry.slug || entry.id || '';
            const collectionSlug = entry.collectionSlug || '';
            
            // Build entry URL
            let entryUrl = '';
            if (isSubEntry && entry.parentEntryId) {
                entryUrl = `/admin/index.php?page=entry-detail&collection=${encodeURIComponent(collectionSlug)}&entry=${encodeURIComponent(entry.parentEntryId)}`;
            } else {
                entryUrl = `/admin/index.php?page=entry-editor&collection=${encodeURIComponent(collectionSlug)}&entry=${encodeURIComponent(entryId)}`;
            }
            
            return `
                <div class="entry-item ${isSubEntry ? 'entry-item-sub' : ''}">
                    <div class="entry-info">
                        <div class="entry-header">
                            <h4>
                                ${isSubEntry ? '<span class="entry-sub-indicator">↳</span>' : ''}
                                <a href="${entryUrl}">${escapeHtml(entryTitle)}</a>
                            </h4>
                            <span class="entry-status entry-status-${status}">${statusLabel}</span>
                        </div>
                        <p class="entry-meta">
                            <span class="entry-collection">${escapeHtml(entry.collectionName || collectionSlug)}</span>
                            ${isSubEntry && entry.parentEntryTitle ? `<span class="entry-parent">• ${escapeHtml(entry.parentEntryTitle)}</span>` : ''}
                            ${timeAgo ? `<span class="entry-time">• ${timeAgo}</span>` : ''}
                        </p>
                    </div>
            </div>
        `;
        }).join('');
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
        const container = document.getElementById('system-health-metrics');
        if (!container) return;
        
        const responseTimeColor = metrics.api_response_time < 100 ? 'good' : 
                                 metrics.api_response_time < 500 ? 'warning' : 'error';
        
        // Calculate storage percentage (assuming 10GB limit for now, can be made configurable)
        const storageLimitMB = 10240; // 10GB
        const storageUsedMB = metrics.media.size_mb || 0;
        const storagePercent = Math.min((storageUsedMB / storageLimitMB) * 100, 100);
        const storageStatus = storagePercent < 70 ? 'good' : storagePercent < 90 ? 'warning' : 'error';
        
        container.innerHTML = `
            <div class="health-metrics-grid">
                <div class="health-metric-card">
                    <div class="health-metric-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                            <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                            <line x1="12" y1="22.08" x2="12" y2="12"></line>
                        </svg>
                    </div>
                    <div class="health-metric-content">
                        <div class="health-metric-value health-${storageStatus}">${storageUsedMB.toFixed(1)}MB</div>
                        <div class="health-metric-label">Media Storage</div>
                        <div class="health-metric-progress">
                            <div class="health-progress-bar" style="width: ${storagePercent}%"></div>
                </div>
                        <div class="health-metric-detail">${metrics.media.count || 0} files</div>
                    </div>
                </div>
                
                <div class="health-metric-card">
                    <div class="health-metric-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                        </svg>
                    </div>
                    <div class="health-metric-content">
                        <div class="health-metric-value">${metrics.database.size_mb.toFixed(2)}MB</div>
                        <div class="health-metric-label">Database Size</div>
                        <div class="health-metric-detail">${metrics.database.exists ? 'Active' : 'Not found'}</div>
                    </div>
                </div>
                
                <div class="health-metric-card">
                    <div class="health-metric-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                    </div>
                    <div class="health-metric-content">
                        <div class="health-metric-value health-${responseTimeColor}">${metrics.api_response_time}ms</div>
                        <div class="health-metric-label">API Response</div>
                        <div class="health-metric-detail">Performance</div>
                    </div>
                </div>
                
                ${metrics.cache.enabled ? `
                <div class="health-metric-card">
                    <div class="health-metric-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"></path>
                        </svg>
                    </div>
                    <div class="health-metric-content">
                        <div class="health-metric-value health-good">${metrics.cache.files}</div>
                        <div class="health-metric-label">Cache Files</div>
                        <div class="health-metric-detail">${metrics.cache.size_mb.toFixed(2)}MB</div>
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
            .then(response => {
                if (!response.ok) {
                    // If response is not ok, show setup message
                    container.innerHTML = `
                        <div style="text-align: center; padding: 1.5rem;">
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;">📊</div>
                            <p style="color: var(--text-secondary, #6b7280); margin: 0;">Analytics not available</p>
                        </div>
                    `;
                    return null;
                }
                return response.json();
            })
            .then(data => {
                if (!data) return; // Response was not ok
                
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
                // Silently handle error - don't spam console
                const container = document.getElementById('analytics-widget-content');
                if (container) {
                    container.innerHTML = `
                        <div style="text-align: center; padding: 1.5rem;">
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;">📊</div>
                            <p style="color: #6b7280; font-size: 0.875rem; margin: 0;">Analytics not available</p>
                        </div>
                    `;
                }
            });
    };
    
    // Render plugin widgets
    function renderPluginWidgets(widgets) {
        const section = document.getElementById('plugin-widgets-section');
        const container = document.getElementById('plugin-widgets-content');
        if (!section || !container) return;
        
        if (!widgets || widgets.length === 0) {
            section.style.display = 'none';
                return;
        }
        
        section.style.display = 'block';
        
        container.innerHTML = `
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



