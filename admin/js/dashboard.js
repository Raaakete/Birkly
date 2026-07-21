(function () {
    'use strict';

    const refreshTimers = [];

    function apiUrl(path) {
        return window.Birkly && typeof Birkly.url === 'function' ? Birkly.url(path) : path;
    }

    function escapeHtml(value) {
        const element = document.createElement('div');
        element.textContent = String(value ?? '');
        return element.innerHTML;
    }

    function adminUrl(page, params) {
        if (window.Birkly && typeof Birkly.adminUrl === 'function') {
            return Birkly.adminUrl(page, params);
        }
        return '/admin/index.php?' + new URLSearchParams({ page: page, ...(params || {}) }).toString();
    }

    function setContent(element, html) {
        if (element) element.innerHTML = html;
    }

    function empty(message) {
        return '<p class="empty-state">' + escapeHtml(message) + '</p>';
    }

    function registerCoreMounts() {
        const dashboard = window.Birkly && Birkly.Dashboard;
        if (!dashboard) return false;

        dashboard.registerMount('quickActions', function (element) {
            setContent(element,
                '<div class="dash-widget-actions">' +
                '<a class="btn btn-primary btn-small" href="' + escapeHtml(adminUrl('content')) + '">Manage content</a>' +
                '<a class="btn btn-secondary btn-small" href="' + escapeHtml(adminUrl('media')) + '">Open media</a>' +
                '<a class="btn btn-secondary btn-small" href="' + escapeHtml(adminUrl('settings')) + '">Settings</a>' +
                '</div>');
        });

        dashboard.registerMount('contentSnapshot', function (element, widget, data) {
            const stats = data && data.stats ? data.stats : {};
            setContent(element,
                '<div class="dash-widget-metrics">' +
                metric('Collections', stats.collections) + metric('Entries', stats.total_entries) +
                metric('Published', stats.published) + metric('Drafts', stats.drafts) +
                '</div>');
        });

        dashboard.registerMount('analyticsMini', function (element, widget, data) {
            if (!data || !data.has_domains || !data.has_analytics) {
                setContent(element, empty('Analytics is not configured yet.'));
                return;
            }
            setContent(element,
                '<div class="dash-widget-metrics">' +
                metric('Page views (30d)', Number(data.total_page_views || 0).toLocaleString()) +
                metric('Visitors (30d)', Number(data.total_visitors || 0).toLocaleString()) +
                '</div>');
        });

        dashboard.registerMount('recentCollections', function (element) {
            setContent(element, '<p class="empty-state loading">Loading collections…</p>');
            fetch(apiUrl('/api/content.php?action=list_collections'), { credentials: 'same-origin' })
                .then(response => response.json())
                .then(function (payload) {
                    const collections = payload?.data?.collections || payload?.data || payload;
                    if (!Array.isArray(collections) || collections.length === 0) {
                        setContent(element, empty('No collections found.'));
                        return;
                    }
                    const items = collections.slice().sort(sortByUpdated).slice(0, 5).map(function (collection) {
                        const name = collection.name || collection.title || collection.slug || 'Untitled';
                        const count = collection.total_entries ?? collection.entries_count ?? 0;
                        return '<div class="collection-item"><div class="collection-info"><h4>' + escapeHtml(name) +
                            '</h4><p>' + escapeHtml(count) + ' ' + (Number(count) === 1 ? 'entry' : 'entries') +
                            '</p></div><a class="btn btn-sm" href="' + escapeHtml(adminUrl('entries', { collection: collection.slug || '' })) +
                            '">View</a></div>';
                    });
                    setContent(element, items.join(''));
                })
                .catch(function () { setContent(element, empty('Unable to load collections.')); });
        });

        dashboard.registerMount('recentEntries', function (element) {
            setContent(element, '<p class="empty-state loading">Loading entries…</p>');
            fetch(apiUrl('/api/content.php?action=list_collections'), { credentials: 'same-origin' })
                .then(response => response.json())
                .then(function (payload) {
                    const collections = payload?.data?.collections || payload?.data || payload;
                    if (!Array.isArray(collections) || collections.length === 0) return [];
                    return Promise.all(collections.slice(0, 6).map(function (collection) {
                        const slug = collection.slug || '';
                        return fetch(apiUrl('/api/admin_entries.php?action=list&collection=' + encodeURIComponent(slug)), { credentials: 'same-origin' })
                            .then(response => response.json())
                            .then(function (entryPayload) {
                                const entries = entryPayload.entries || entryPayload?.data?.entries || entryPayload?.data || [];
                                return Array.isArray(entries) ? entries.map(entry => ({ ...entry, collection: slug })) : [];
                            })
                            .catch(function () { return []; });
                    }));
                })
                .then(function (groups) {
                    const entries = Array.isArray(groups) ? groups.flat().sort(sortByUpdated).slice(0, 8) : [];
                    if (entries.length === 0) {
                        setContent(element, empty('No recent entries.'));
                        return;
                    }
                    setContent(element, entries.map(function (entry) {
                        const title = entry.title || entry.name || 'Untitled';
                        return '<div class="entry-item"><div class="entry-info"><h4><a href="' +
                            escapeHtml(adminUrl('entry-editor', { collection: entry.collection, entry: entry.slug || entry.id || '' })) +
                            '">' + escapeHtml(title) + '</a></h4><p class="entry-meta">' + escapeHtml(entry.collection) + '</p></div></div>';
                    }).join(''));
                })
                .catch(function () { setContent(element, empty('Unable to load entries.')); });
        });

        dashboard.registerMount('recentActivity', function (element, widget, data) {
            const activity = data && Array.isArray(data.activity) ? data.activity : [];
            const rows = activity.filter(item => !['update_check', 'system_update_check'].includes(item.event)).map(function (item) {
                return '<div class="activity-item"><div class="activity-content"><div class="activity-message">' +
                    escapeHtml(item.message || item.event || 'Activity') + '</div><div class="activity-meta">' +
                    escapeHtml(item.user || 'System') + '</div></div></div>';
            });
            setContent(element, rows.length ? rows.join('') : empty('No recent activity.'));
        });

        dashboard.registerMount('drafts', function (element, widget, data) {
            const stats = data && data.stats ? data.stats : {};
            setContent(element, '<div class="dash-widget-metrics">' + metric('Drafts needing attention', stats.drafts || 0) + '</div>');
        });

        dashboard.registerMount('systemStatus', function (element, widget, data) {
            if (!data) return setContent(element, empty('System status is unavailable.'));
            setContent(element, '<div class="dash-widget-metrics">' +
                metric('API response', (data.api_response_time || 0) + 'ms') +
                metric('Media storage', (data.media?.size_mb || 0) + 'MB') +
                metric('Cache files', data.cache?.files || 0) + '</div>');
        });

        dashboard.registerMount('security', function (element, widget, data) {
            const activity = data && Array.isArray(data.activity) ? data.activity : [];
            setContent(element, '<div class="dash-widget-metrics">' + metric('Recent account events', activity.length) + '</div>');
        });

        dashboard.registerMount('updates', function (element, widget, data) {
            const notifications = data && Array.isArray(data.notifications) ? data.notifications : [];
            setContent(element, notifications.length
                ? '<ul class="dash-widget-list">' + notifications.map(item => '<li><strong>' + escapeHtml(item.title) + '</strong><span>' + escapeHtml(item.message) + '</span></li>').join('') + '</ul>'
                : empty('No updates or alerts.'));
        });

        dashboard.registerMount('commerceStats', renderCommerce);
        dashboard.registerMount('eventsUpcoming', renderEvents);
        dashboard.registerMount('studioPublishing', renderStudio);
        dashboard.registerMount('automationRuns', renderAutomation);
        return true;
    }

    function metric(label, value) {
        return '<div class="dash-widget-metric"><strong>' + escapeHtml(value ?? 0) + '</strong><span>' + escapeHtml(label) + '</span></div>';
    }

    function sortByUpdated(left, right) {
        return new Date(right.modified_at || right.updated_at || right.created_at || 0) - new Date(left.modified_at || left.updated_at || left.created_at || 0);
    }

    function renderCommerce(element, widget, data) {
        const summary = data && data.summary;
        setContent(element, summary
            ? '<div class="payments-dash-grid">' + metric('Revenue', formatCents(summary.revenue_cents)) + metric('Orders', summary.order_count) + '</div>'
            : empty('No commerce orders yet.'));
    }

    function renderEvents(element, widget, data) {
        const events = data && Array.isArray(data.upcoming) ? data.upcoming : [];
        setContent(element, events.length
            ? '<ul class="dash-widget-list">' + events.map(event => '<li><strong>' + escapeHtml(event.title || 'Untitled event') + '</strong></li>').join('') + '</ul>'
            : empty('No upcoming events.'));
    }

    function renderStudio(element, widget, data) {
        setContent(element, '<div class="dash-widget-metrics">' + metric('Published', data?.published || 0) + metric('Drafts', data?.drafts || 0) + '</div>');
    }

    function renderAutomation(element, widget, data) {
        setContent(element, '<div class="dash-widget-metrics">' + metric('Active', data?.enabled || 0) + metric('Queued', data?.queue?.pending || 0) + '</div>');
    }

    function formatCents(cents) {
        return (Number(cents || 0) / 100).toLocaleString(undefined, { style: 'currency', currency: 'EUR', minimumFractionDigits: 0 });
    }

    function loadAndMountWidget(body, widget) {
        return Birkly.Dashboard.fetchWidgetData(widget)
            .then(function (data) {
                return Birkly.Dashboard.mountWidget(body, widget, data);
            })
            .catch(function () {
                setContent(body, empty('Unable to load this widget.'));
                return false;
            });
    }

    function renderWidgets(widgets) {
        const grid = document.getElementById('dashboard-widget-grid');
        if (!grid || !window.Birkly?.Dashboard) return;
        grid.replaceChildren();
        widgets.slice().sort((left, right) => left.priority - right.priority || left.id.localeCompare(right.id)).forEach(function (widget) {
            const card = document.createElement('section');
            const size = ['sm', 'md', 'lg', 'full'].includes(widget.size) ? widget.size : 'md';
            card.className = 'card dash-widget dash-widget--size-' + size;
            card.dataset.dashboardWidget = widget.id;
            const heading = document.createElement('div');
            heading.className = 'dash-widget-header';
            const title = document.createElement('h2');
            title.className = 'dash-widget-title';
            title.textContent = widget.title || widget.id;
            heading.appendChild(title);
            const body = document.createElement('div');
            body.className = 'dash-widget-body dash-widget-content';
            body.innerHTML = '<p class="empty-state loading">Loading…</p>';
            card.append(heading, body);
            grid.appendChild(card);
            loadAndMountWidget(body, widget);
            if (Number(widget.refresh) > 0) {
                refreshTimers.push(window.setInterval(function () {
                    loadAndMountWidget(body, widget);
                }, Number(widget.refresh) * 1000));
            }
        });
    }

    function loadWidgetManifest() {
        const grid = document.getElementById('dashboard-widget-grid');
        if (!grid || !registerCoreMounts()) return;
        fetch(apiUrl('/api/dashboard.php?action=widgets'), { credentials: 'same-origin' })
            .then(response => response.json())
            .then(payload => renderWidgets(payload?.data?.widgets || []))
            .catch(function () { grid.innerHTML = empty('Unable to load dashboard widgets.'); });
    }

    function setupOnboardingHandlers() {
        const modal = document.getElementById('onboarding-complete-modal');
        const close = () => { if (modal) modal.style.display = 'none'; };
        document.getElementById('onboarding-complete-close')?.addEventListener('click', close);
        document.getElementById('onboarding-complete-close-btn')?.addEventListener('click', close);

        const params = new URLSearchParams(window.location.search);
        if (params.get('onboarding_complete') === 'true' && modal) {
            modal.style.display = 'flex';
            const demoUrl = params.get('demo_website_url');
            const demoCard = document.getElementById('onboarding-complete-demo-card');
            const demoLink = document.getElementById('onboarding-complete-demo-link');
            if (demoUrl && demoCard && demoLink) {
                demoCard.style.display = '';
                demoLink.href = demoUrl;
            }
        }

        async function postOnboardingAction(action, body) {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const response = await fetch(apiUrl('/api/onboarding.php?action=' + action), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ ...body, _csrf: csrf })
            });
            return response.json();
        }

        document.getElementById('hide-checklist-btn')?.addEventListener('click', async function () {
            await postOnboardingAction('hide_checklist', {});
            document.getElementById('onboarding-checklist-panel')?.remove();
        });

        document.querySelectorAll('.checklist-dismiss').forEach(function (button) {
            button.addEventListener('click', async function (event) {
                event.preventDefault();
                const id = button.getAttribute('data-dismiss-id');
                if (!id) return;
                await postOnboardingAction('dismiss_checklist_item', { item_id: id });
                button.closest('.checklist-item')?.remove();
            });
        });
    }

    function initDashboard() {
        setupOnboardingHandlers();
        loadWidgetManifest();
    }

    window.addEventListener('beforeunload', () => refreshTimers.forEach(timer => clearInterval(timer)));
    document.readyState === 'loading'
        ? document.addEventListener('DOMContentLoaded', initDashboard)
        : initDashboard();
}());
