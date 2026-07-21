/**
 * Birkly Dashboard Widget API v2 — safe mount handlers (no eval).
 */
(function () {
    'use strict';

    window.Birkly = window.Birkly || {};

    var refreshTimers = [];
    var activityFilter = 'all';

    function apiUrl(path) {
        if (window.Birkly && typeof Birkly.url === 'function') {
            return Birkly.url(path);
        }
        return path;
    }

    function adminUrl(page, params) {
        if (window.Birkly && typeof Birkly.adminUrl === 'function') {
            return Birkly.adminUrl(page, params || {});
        }
        var q = new URLSearchParams(Object.assign({ page: page }, params || {}));
        return '/admin/index.php?' + q.toString();
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    function formatTimeAgo(timestamp) {
        if (!timestamp) return 'Just now';
        var date = new Date(timestamp);
        var now = new Date();
        var diff = Math.floor((now - date) / 1000);
        if (diff < 60) return 'Just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
        return date.toLocaleDateString();
    }

    function formatCents(cents) {
        var n = Number(cents) || 0;
        return (n / 100).toLocaleString(undefined, { style: 'currency', currency: 'EUR', minimumFractionDigits: 0 });
    }

    function fetchJson(url) {
        return fetch(apiUrl(url), { credentials: 'same-origin' }).then(function (r) { return r.json(); });
    }

    var mounts = {
        quickActions: function (el) {
            el.innerHTML =
                '<div class="dash-quick-actions">' +
                '<a class="btn btn-primary btn-small" href="' + escapeHtml(adminUrl('content')) + '">New content</a>' +
                '<a class="btn btn-secondary btn-small" href="' + escapeHtml(adminUrl('media')) + '">Media library</a>' +
                '<a class="btn btn-secondary btn-small" href="' + escapeHtml(adminUrl('settings')) + '">Settings</a>' +
                '<a class="btn btn-ghost btn-small" href="' + escapeHtml(adminUrl('help')) + '">Documentation</a>' +
                '</div>';
        },

        contentSnapshot: function (el, widget) {
            el.innerHTML = '<div class="dash-stats-inline loading">Loading…</div>';
            fetchJson(widget.endpoint || '/api/dashboard.php?action=stats').then(function (data) {
                var stats = (data.status === 'ok' && data.data && data.data.stats) ? data.data.stats : {};
                el.innerHTML =
                    '<div class="dash-stats-inline">' +
                    '<div class="dash-stat"><strong>' + (stats.collections ?? 0) + '</strong><span>Collections</span></div>' +
                    '<div class="dash-stat"><strong>' + (stats.total_entries ?? 0) + '</strong><span>Total entries</span></div>' +
                    '<div class="dash-stat"><strong>' + (stats.published ?? 0) + '</strong><span>Published</span></div>' +
                    '<div class="dash-stat"><strong>' + (stats.drafts ?? 0) + '</strong><span>Drafts</span></div>' +
                    '</div>';
            }).catch(function () {
                el.innerHTML = '<p class="empty-state">Unable to load stats</p>';
            });
        },

        analyticsMini: function (el, widget) {
            el.innerHTML = '<p class="empty-state loading">Loading…</p>';
            fetchJson(widget.endpoint || '/api/dashboard.php?action=analytics').then(function (data) {
                if (data.status !== 'ok' || !data.data) {
                    el.innerHTML = '<p class="empty-state">Analytics not available</p>';
                    return;
                }
                var analytics = data.data;
                if (!analytics.has_domains || !analytics.has_analytics) {
                    el.innerHTML =
                        '<p class="empty-state">Enable analytics in <a href="' + escapeHtml(adminUrl('settings') + '#domains') + '">domain settings</a>.</p>';
                    return;
                }
                var html = '<div class="dash-analytics-mini">' +
                    '<div class="dash-stat"><strong>' + (analytics.total_page_views || 0).toLocaleString() + '</strong><span>Page views (30d)</span></div>' +
                    '<div class="dash-stat"><strong>' + (analytics.total_visitors || 0).toLocaleString() + '</strong><span>Visitors (30d)</span></div>' +
                    '</div>';
                if (analytics.websites && analytics.websites.length) {
                    html += '<ul class="dash-list dash-list--compact">';
                    analytics.websites.slice(0, 3).forEach(function (site) {
                        html += '<li><span>' + escapeHtml(site.name || site.domain) + '</span>' +
                            '<small>' + (site.page_views || 0).toLocaleString() + ' views</small></li>';
                    });
                    html += '</ul>';
                }
                el.innerHTML = html;
            }).catch(function () {
                el.innerHTML = '<p class="empty-state">Analytics not available</p>';
            });
        },

        recentCollections: function (el) {
            el.innerHTML = '<p class="empty-state loading">Loading…</p>';
            fetchJson('/api/content.php?action=list_collections').then(function (data) {
                var collections = [];
                if (data.status === 'ok' && data.data && data.data.collections) {
                    collections = data.data.collections;
                } else if (data.status === 'ok' && Array.isArray(data.data)) {
                    collections = data.data;
                } else if (Array.isArray(data)) {
                    collections = data;
                }
                collections.sort(function (a, b) {
                    var dateA = a.modified_at || a.updated_at || a.created_at || 0;
                    var dateB = b.modified_at || b.updated_at || b.created_at || 0;
                    if (dateA && dateB) return new Date(dateB) - new Date(dateA);
                    return String(a.name || a.title || '').localeCompare(String(b.name || b.title || ''));
                });
                var recent = collections.slice(0, 5);
                if (!recent.length) {
                    el.innerHTML = '<p class="empty-state">No collections found</p>';
                    return;
                }
                el.innerHTML = '<ul class="dash-list">' + recent.map(function (c) {
                    var name = c.title || c.name || c.slug || 'Untitled';
                    var slug = c.slug || '';
                    var count = c.entries_count || c.total_entries || 0;
                    return '<li class="dash-list-item">' +
                        '<div><strong>' + escapeHtml(name) + '</strong><small>' + count + ' entries</small></div>' +
                        '<a class="btn btn-sm" href="' + escapeHtml(adminUrl('entries', { collection: slug })) + '">View</a>' +
                        '</li>';
                }).join('') + '</ul>';
            }).catch(function () {
                el.innerHTML = '<p class="empty-state error">Error loading collections</p>';
            });
        },

        recentEntries: function (el) {
            el.innerHTML = '<p class="empty-state loading">Loading…</p>';
            fetchJson('/api/content.php?action=list_collections').then(function (data) {
                var collections = (data.status === 'ok' && Array.isArray(data.data)) ? data.data : [];
                var recentEntries = [];
                var promises = collections.slice(0, 6).map(function (collection) {
                    var slug = collection.slug || '';
                    return fetchJson('/api/admin_entries.php?action=list&collection=' + encodeURIComponent(slug)).then(function (entryData) {
                        var entries = entryData.entries || (entryData.data && entryData.data.entries) || [];
                        entries.sort(function (a, b) {
                            return new Date(b.modified_at || b.updated_at || b.created_at || 0) -
                                new Date(a.modified_at || a.updated_at || a.created_at || 0);
                        });
                        entries.slice(0, 3).forEach(function (entry) {
                            recentEntries.push({
                                title: entry.title || 'Untitled',
                                status: entry.status || 'draft',
                                modified_at: entry.modified_at || entry.updated_at || entry.created_at,
                                collectionName: collection.name || collection.title || slug,
                                collectionSlug: slug,
                                entryId: entry.slug || entry.id || ''
                            });
                        });
                    }).catch(function () { /* skip collection */ });
                });
                return Promise.all(promises).then(function () {
                    recentEntries.sort(function (a, b) {
                        return new Date(b.modified_at || 0) - new Date(a.modified_at || 0);
                    });
                    var top = recentEntries.slice(0, 8);
                    if (!top.length) {
                        el.innerHTML = '<p class="empty-state">No recent entries</p>';
                        return;
                    }
                    el.innerHTML = '<ul class="dash-list">' + top.map(function (entry) {
                        var url = adminUrl('entry-editor', { collection: entry.collectionSlug, entry: entry.entryId });
                        var status = entry.status === 'published' ? 'published' : 'draft';
                        return '<li class="dash-list-item">' +
                            '<div><a href="' + escapeHtml(url) + '"><strong>' + escapeHtml(entry.title) + '</strong></a>' +
                            '<small>' + escapeHtml(entry.collectionName) + ' · ' + formatTimeAgo(entry.modified_at) + '</small></div>' +
                            '<span class="entry-status entry-status-' + status + '">' + (status === 'published' ? 'Published' : 'Draft') + '</span>' +
                            '</li>';
                    }).join('') + '</ul>';
                });
            }).catch(function () {
                el.innerHTML = '<p class="empty-state error">Error loading entries</p>';
            });
        },

        recentActivity: function (el, widget) {
            function render(activities) {
                var filtered = (activities || []).filter(function (activity) {
                    var event = activity.event || '';
                    return event !== 'update_check' && event !== 'system_update_check' &&
                        !(activity.message || '').toLowerCase().includes('update check');
                });
                if (!filtered.length) {
                    inner.innerHTML = '<p class="empty-state">No recent activity</p>';
                    return;
                }
                inner.innerHTML = filtered.map(function (activity) {
                    var details = activity.details || {};
                    var collection = details.collection || details.collection_slug || '';
                    var entryTitle = details.title || details.entry_title || '';
                    var msg = escapeHtml(activity.message || '');
                    if (collection && entryTitle) {
                        msg += ' <span class="activity-detail">in <strong>' + escapeHtml(collection) + '</strong>: ' + escapeHtml(entryTitle) + '</span>';
                    }
                    return '<div class="activity-item">' +
                        '<div class="activity-content">' +
                        '<div class="activity-message">' + msg + '</div>' +
                        '<div class="activity-meta"><span class="activity-user">' + escapeHtml(activity.user || 'System') + '</span>' +
                        '<span class="activity-separator">•</span><span class="activity-time">' + formatTimeAgo(activity.timestamp) + '</span></div>' +
                        '</div></div>';
                }).join('');
            }

            el.innerHTML =
                '<div class="activity-filters dash-activity-filters">' +
                '<button type="button" class="activity-filter-btn active" data-filter="all">All</button>' +
                '<button type="button" class="activity-filter-btn" data-filter="content">Content</button>' +
                '<button type="button" class="activity-filter-btn" data-filter="users">Users</button>' +
                '<button type="button" class="activity-filter-btn" data-filter="system">System</button>' +
                '</div><div class="activity-list-inner"><p class="empty-state loading">Loading…</p></div>';

            var inner = el.querySelector('.activity-list-inner');
            var filters = el.querySelector('.dash-activity-filters');

            function load() {
                inner.innerHTML = '<p class="empty-state loading">Loading…</p>';
                fetchJson('/api/dashboard.php?action=activity&limit=20&filter=' + encodeURIComponent(activityFilter)).then(function (data) {
                    if (data.status === 'ok' && data.data && data.data.activity) {
                        render(data.data.activity);
                    } else {
                        inner.innerHTML = '<p class="empty-state">No recent activity</p>';
                    }
                }).catch(function () {
                    inner.innerHTML = '<p class="empty-state error">Error loading activity</p>';
                });
            }

            filters.addEventListener('click', function (e) {
                var btn = e.target.closest('.activity-filter-btn');
                if (!btn) return;
                activityFilter = btn.dataset.filter || 'all';
                filters.querySelectorAll('.activity-filter-btn').forEach(function (b) {
                    b.classList.toggle('active', b === btn);
                });
                load();
            });

            load();
        },

        drafts: function (el, widget) {
            el.innerHTML = '<p class="empty-state loading">Loading…</p>';
            fetchJson(widget.endpoint || '/api/dashboard.php?action=stats').then(function (data) {
                var drafts = (data.status === 'ok' && data.data && data.data.stats) ? (data.data.stats.drafts || 0) : 0;
                el.innerHTML =
                    '<div class="dash-drafts-summary">' +
                    '<div class="dash-stat dash-stat--large"><strong>' + drafts + '</strong><span>Draft entries</span></div>' +
                    '<a class="btn btn-secondary btn-small" href="' + escapeHtml(adminUrl('content')) + '">Review content</a>' +
                    '</div>';
            }).catch(function () {
                el.innerHTML = '<p class="empty-state">Unable to load draft count</p>';
            });
        },

        systemStatus: function (el, widget) {
            el.innerHTML = '<p class="empty-state loading">Loading…</p>';
            fetchJson(widget.endpoint || '/api/dashboard.php?action=performance').then(function (data) {
                if (data.status !== 'ok' || !data.data) {
                    el.innerHTML = '<p class="empty-state">System metrics unavailable</p>';
                    return;
                }
                var m = data.data;
                var rtColor = m.api_response_time < 100 ? 'good' : (m.api_response_time < 500 ? 'warning' : 'error');
                var storageMb = (m.media && m.media.size_mb) || 0;
                el.innerHTML =
                    '<div class="health-metrics-grid health-metrics-grid--compact">' +
                    '<div class="health-metric-card"><div class="health-metric-value">' + storageMb.toFixed(1) + 'MB</div><div class="health-metric-label">Media</div></div>' +
                    '<div class="health-metric-card"><div class="health-metric-value">' + ((m.database && m.database.size_mb) || 0).toFixed(2) + 'MB</div><div class="health-metric-label">Database</div></div>' +
                    '<div class="health-metric-card"><div class="health-metric-value health-' + rtColor + '">' + (m.api_response_time || 0) + 'ms</div><div class="health-metric-label">API</div></div>' +
                    '</div>';
            }).catch(function () {
                el.innerHTML = '<p class="empty-state">Unable to load system health</p>';
            });
        },

        security: function (el, widget) {
            el.innerHTML = '<p class="empty-state loading">Loading…</p>';
            fetchJson(widget.endpoint || '/api/dashboard.php?action=activity&limit=10&filter=users').then(function (data) {
                var activities = (data.status === 'ok' && data.data && data.data.activity) ? data.data.activity : [];
                var authEvents = activities.filter(function (a) {
                    return (a.event || '').indexOf('login') !== -1;
                }).slice(0, 5);
                if (!authEvents.length) {
                    el.innerHTML = '<p class="empty-state">No recent auth events</p>';
                    return;
                }
                el.innerHTML = '<ul class="dash-list dash-list--compact">' + authEvents.map(function (a) {
                    return '<li><span>' + escapeHtml(a.message || a.event) + '</span><small>' + formatTimeAgo(a.timestamp) + '</small></li>';
                }).join('') + '</ul>';
            }).catch(function () {
                el.innerHTML = '<p class="empty-state">Security feed unavailable</p>';
            });
        },

        updates: function (el, widget) {
            el.innerHTML = '<p class="empty-state loading">Loading…</p>';
            fetchJson(widget.endpoint || '/api/dashboard.php?action=notifications').then(function (data) {
                var notes = (data.status === 'ok' && data.data && data.data.notifications) ? data.data.notifications : [];
                if (!notes.length) {
                    el.innerHTML = '<p class="empty-state">All clear — no alerts</p>';
                    return;
                }
                el.innerHTML = '<ul class="dash-list dash-list--alerts">' + notes.map(function (n) {
                    return '<li class="dash-alert dash-alert--' + escapeHtml(n.type || 'info') + '">' +
                        '<strong>' + escapeHtml(n.title || '') + '</strong>' +
                        '<span>' + escapeHtml(n.message || '') + '</span>' +
                        (n.link ? '<a href="' + escapeHtml(n.link) + '">View</a>' : '') +
                        '</li>';
                }).join('') + '</ul>';
            }).catch(function () {
                el.innerHTML = '<p class="empty-state">Alerts unavailable</p>';
            });
        },

        commerceStats: function (el) {
            el.innerHTML = '<p class="payments-dash-loading">Loading commerce stats…</p>';
            fetchJson('/api/index.php?route=commerce&action=analytics_summary').then(function (data) {
                if (data.status !== 'ok' || !data.data || !data.data.summary) {
                    el.innerHTML = '<p class="payments-dash-empty">No commerce orders yet.</p>';
                    return;
                }
                var s = data.data.summary;
                el.innerHTML =
                    '<div class="payments-dash-grid">' +
                    '<div class="payments-dash-stat"><strong>' + formatCents(s.revenue_cents || 0) + '</strong><span>Revenue</span></div>' +
                    '<div class="payments-dash-stat"><strong>' + (s.order_count || 0) + '</strong><span>Orders</span></div>' +
                    '</div>';
            }).catch(function () {
                el.innerHTML = '<p class="payments-dash-empty">Unable to load commerce stats.</p>';
            });
        },

        eventsUpcoming: function (el) {
            el.innerHTML = '<p class="empty-state loading">Loading…</p>';
            fetchJson('/api/index.php?route=events&action=list_upcoming&limit=5').then(function (data) {
                var events = (data.status === 'ok' && data.data && data.data.events) ? data.data.events : [];
                if (!events.length) {
                    el.innerHTML = '<p class="empty-state">No upcoming events</p>' +
                        '<a class="btn btn-sm" href="' + escapeHtml(adminUrl('events')) + '">Manage events</a>';
                    return;
                }
                el.innerHTML = '<ul class="dash-list">' + events.map(function (ev) {
                    return '<li><strong>' + escapeHtml(ev.title || 'Event') + '</strong><small>' + escapeHtml(ev.start || '') + '</small></li>';
                }).join('') + '</ul>' +
                    '<a class="payments-dash-link" href="' + escapeHtml(adminUrl('events')) + '">Manage events →</a>';
            }).catch(function () {
                el.innerHTML = '<p class="empty-state">Events summary unavailable</p>' +
                    '<a class="btn btn-sm" href="' + escapeHtml(adminUrl('events')) + '">Open Events</a>';
            });
        },

        studioProjects: function (el) {
            el.innerHTML = '<p class="empty-state loading">Loading…</p>';
            fetchJson('/api/index.php?route=studio&action=list_projects').then(function (data) {
                var projects = (data.status === 'ok' && data.data && data.data.projects) ? data.data.projects : [];
                if (!projects.length) {
                    el.innerHTML = '<p class="empty-state">No Studio projects yet</p>' +
                        '<a class="btn btn-sm" href="' + escapeHtml(adminUrl('studio')) + '">Open Studio</a>';
                    return;
                }
                el.innerHTML = '<ul class="dash-list">' + projects.slice(0, 5).map(function (p) {
                    return '<li><strong>' + escapeHtml(p.name || p.slug || 'Project') + '</strong></li>';
                }).join('') + '</ul>' +
                    '<a class="payments-dash-link" href="' + escapeHtml(adminUrl('studio')) + '">Open Studio →</a>';
            }).catch(function () {
                el.innerHTML = '<p class="empty-state">Studio unavailable</p>';
            });
        },

        automationRuns: function (el) {
            el.innerHTML = '<p class="empty-state loading">Loading…</p>';
            fetchJson('/api/automation.php?action=queue_status').then(function (data) {
                var q = (data.status === 'ok' && data.data) ? data.data : {};
                el.innerHTML =
                    '<div class="dash-stats-inline">' +
                    '<div class="dash-stat"><strong>' + (q.pending || q.queued || 0) + '</strong><span>Queued</span></div>' +
                    '<div class="dash-stat"><strong>' + (q.processed_today || q.completed || 0) + '</strong><span>Processed</span></div>' +
                    '</div>' +
                    '<a class="payments-dash-link" href="' + escapeHtml(adminUrl('automation')) + '">Manage automation →</a>';
            }).catch(function () {
                el.innerHTML = '<p class="empty-state">Automation unavailable</p>';
            });
        },

        legacyHtml: function (el, widget) {
            el.innerHTML = widget.html || '';
        },

        pluginHtml: function (el, widget) {
            el.innerHTML = widget.html || '<p class="empty-state">No content</p>';
            if (widget.mount === 'commerceStats' || widget.id === 'commerce-stats') {
                mounts.commerceStats(el, widget);
            }
        }
    };

    function buildWidgetCard(widget) {
        var size = widget.size || 'md';
        var card = document.createElement('article');
        card.className = 'card dash-widget dash-widget--' + size;
        card.dataset.widgetId = widget.id || '';
        if (widget.plugin_id) {
            card.dataset.pluginId = widget.plugin_id;
        }

        var headerHtml = widget.title
            ? '<header class="dash-widget-header"><h3>' + escapeHtml(widget.title) + '</h3></header>'
            : '';
        card.innerHTML = headerHtml + '<div class="dash-widget-body"></div>';
        return card;
    }

    function mountWidget(bodyEl, widget) {
        var mountName = widget.mount;
        if (mountName && mounts[mountName]) {
            mounts[mountName](bodyEl, widget);
            return;
        }
        if (widget.html) {
            mounts.pluginHtml(bodyEl, widget);
            return;
        }
        bodyEl.innerHTML = '<p class="empty-state">Widget not configured</p>';
    }

    function scheduleRefresh(bodyEl, widget) {
        var seconds = parseInt(widget.refresh, 10) || 0;
        if (seconds <= 0) return;
        var timer = setInterval(function () {
            mountWidget(bodyEl, widget);
        }, seconds * 1000);
        refreshTimers.push(timer);
    }

    function renderWidgetGrid(widgets, container) {
        if (!container) return;
        container.innerHTML = '';
        if (!widgets || !widgets.length) {
            container.innerHTML = '<p class="empty-state">No dashboard widgets available</p>';
            return;
        }
        widgets.forEach(function (widget) {
            var card = buildWidgetCard(widget);
            var body = card.querySelector('.dash-widget-body');
            mountWidget(body, widget);
            scheduleRefresh(body, widget);
            container.appendChild(card);
        });
    }

    function loadWidgets(container) {
        container.innerHTML = '<p class="empty-state loading dash-widget-grid-loading">Loading dashboard…</p>';
        fetchJson('/api/dashboard.php?action=widgets').then(function (data) {
            var widgets = (data.status === 'ok' && data.data && data.data.widgets) ? data.data.widgets : [];
            renderWidgetGrid(widgets, container);
        }).catch(function (err) {
            console.error('Error loading dashboard widgets:', err);
            container.innerHTML = '<p class="empty-state error">Error loading dashboard</p>';
        });
    }

    function init() {
        var grid = document.getElementById('dashboard-widget-grid');
        if (!grid) return;
        loadWidgets(grid);
    }

    function destroy() {
        refreshTimers.forEach(clearInterval);
        refreshTimers = [];
    }

    window.Birkly.Dashboard = {
        mountWidget: mountWidget,
        registerMount: function (name, fn) {
            if (name && typeof fn === 'function') {
                mounts[name] = fn;
            }
        },
        renderWidgetGrid: renderWidgetGrid,
        init: init,
        destroy: destroy
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.addEventListener('beforeunload', destroy);
})();
