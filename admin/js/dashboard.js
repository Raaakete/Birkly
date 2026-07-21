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
        return '<p class="dash-empty">' + escapeHtml(message) + '</p>';
    }

    function formatTimeAgo(timestamp) {
        if (!timestamp) return '';
        const date = new Date(timestamp);
        const diff = Math.floor((Date.now() - date.getTime()) / 1000);
        if (diff < 60) return 'Just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
        return date.toLocaleDateString();
    }

    function statTile(value, label, modifier) {
        const mod = modifier ? ' dash-stat-tile--' + modifier : '';
        return '<div class="dash-stat-tile' + mod + '"><span class="dash-stat-tile__value">' +
            escapeHtml(value ?? 0) + '</span><span class="dash-stat-tile__label">' + escapeHtml(label) + '</span></div>';
    }

    function statGrid(tiles) {
        return '<div class="dash-stat-grid">' + tiles.join('') + '</div>';
    }

    function feedList(items) {
        return '<ul class="dash-feed">' + items.join('') + '</ul>';
    }

    function feedItem(options) {
        const title = options.href
            ? '<a class="dash-feed__title" href="' + escapeHtml(options.href) + '">' + escapeHtml(options.title) + '</a>'
            : '<span class="dash-feed__title">' + escapeHtml(options.title) + '</span>';
        const meta = options.meta ? '<span class="dash-feed__meta">' + options.meta + '</span>' : '';
        const aside = options.aside ? '<div class="dash-feed__aside">' + options.aside + '</div>' : '';
        return '<li class="dash-feed__item"><div class="dash-feed__main">' + title + meta + '</div>' + aside + '</li>';
    }

    function statusBadge(status) {
        const published = status === 'published';
        const label = published ? 'Published' : 'Draft';
        const className = published ? 'dash-badge dash-badge--published' : 'dash-badge dash-badge--draft';
        return '<span class="' + className + '">' + label + '</span>';
    }

    function callout(value, title, description, href, hrefLabel, tone) {
        const link = href
            ? '<a class="dash-callout__link" href="' + escapeHtml(href) + '">' + escapeHtml(hrefLabel) + '</a>'
            : '';
        return '<div class="dash-callout dash-callout--' + tone + '">' +
            '<span class="dash-callout__value">' + escapeHtml(value) + '</span>' +
            '<div class="dash-callout__copy"><strong>' + escapeHtml(title) + '</strong>' +
            (description ? '<span>' + escapeHtml(description) + '</span>' : '') + link + '</div></div>';
    }

    function alertItem(title, message, tone) {
        return '<li class="dash-alert dash-alert--' + (tone || 'info') + '">' +
            '<div class="dash-alert__copy"><strong>' + escapeHtml(title) + '</strong>' +
            '<span>' + escapeHtml(message) + '</span></div></li>';
    }

    function registerCoreMounts() {
        const dashboard = window.Birkly && Birkly.Dashboard;
        if (!dashboard) return false;

        dashboard.registerMount('contentSnapshot', function (element, widget, data) {
            const stats = data && data.stats ? data.stats : {};
            setContent(element, statGrid([
                statTile(stats.collections, 'Collections', 'collections'),
                statTile(stats.total_entries, 'Entries', 'entries'),
                statTile(stats.published, 'Published', 'published'),
                statTile(stats.drafts, 'Drafts', 'drafts'),
            ]));
        });

        dashboard.registerMount('analyticsMini', function (element, widget, data) {
            if (!data || !data.has_domains) {
                setContent(element, callout('—', 'Analytics not configured', 'Register a domain to start tracking visitors.', adminUrl('settings') + '#domains', 'Set up analytics →', 'muted'));
                return;
            }
            if (!data.has_analytics) {
                setContent(element, callout('—', 'Analytics ready', 'Enable tracking for your registered domains.', adminUrl('settings') + '#domains', 'Enable analytics →', 'muted'));
                return;
            }
            setContent(element, statGrid([
                statTile(Number(data.total_page_views || 0).toLocaleString(), 'Page views (30d)', 'analytics'),
                statTile(Number(data.total_visitors || 0).toLocaleString(), 'Visitors (30d)', 'analytics'),
            ]));
        });

        dashboard.registerMount('recentCollections', function (element) {
            setContent(element, '<p class="dash-empty dash-empty--loading">Loading collections…</p>');
            fetch(apiUrl('/api/content.php?action=list_collections'), { credentials: 'same-origin' })
                .then(response => response.json())
                .then(function (payload) {
                    const collections = payload?.data?.collections || payload?.data || payload;
                    if (!Array.isArray(collections) || collections.length === 0) {
                        setContent(element, empty('No collections yet.'));
                        return;
                    }
                    const items = collections.slice().sort(sortByUpdated).slice(0, 6).map(function (collection) {
                        const name = collection.name || collection.title || collection.slug || 'Untitled';
                        const count = collection.total_entries ?? collection.entries_count ?? 0;
                        const slug = collection.slug || '';
                        return feedItem({
                            title: name,
                            href: adminUrl('entries', { collection: slug }),
                            meta: escapeHtml(count) + ' ' + (Number(count) === 1 ? 'entry' : 'entries'),
                            aside: '<span class="dash-feed__chevron" aria-hidden="true">→</span>',
                        });
                    });
                    setContent(element, feedList(items));
                })
                .catch(function () { setContent(element, empty('Unable to load collections.')); });
        });

        dashboard.registerMount('recentEntries', function (element) {
            setContent(element, '<p class="dash-empty dash-empty--loading">Loading entries…</p>');
            fetch(apiUrl('/api/content.php?action=list_collections'), { credentials: 'same-origin' })
                .then(response => response.json())
                .then(function (payload) {
                    const collections = payload?.data?.collections || payload?.data || payload;
                    if (!Array.isArray(collections) || collections.length === 0) return [];
                    return Promise.all(collections.slice(0, 6).map(function (collection) {
                        const slug = collection.slug || '';
                        const collectionName = collection.name || collection.title || slug;
                        return fetch(apiUrl('/api/admin_entries.php?action=list&collection=' + encodeURIComponent(slug)), { credentials: 'same-origin' })
                            .then(response => response.json())
                            .then(function (entryPayload) {
                                const entries = entryPayload.entries || entryPayload?.data?.entries || entryPayload?.data || [];
                                return Array.isArray(entries)
                                    ? entries.map(entry => ({ ...entry, collection: slug, collectionName: collectionName }))
                                    : [];
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
                    const items = entries.map(function (entry) {
                        const title = entry.title || entry.name || 'Untitled';
                        const modified = formatTimeAgo(entry.modified_at || entry.updated_at || entry.created_at);
                        const metaParts = [escapeHtml(entry.collectionName || entry.collection)];
                        if (modified) metaParts.push(escapeHtml(modified));
                        return feedItem({
                            title: title,
                            href: adminUrl('entry-editor', { collection: entry.collection, entry: entry.slug || entry.id || '' }),
                            meta: metaParts.join(' · '),
                            aside: statusBadge(entry.status),
                        });
                    });
                    setContent(element, feedList(items));
                })
                .catch(function () { setContent(element, empty('Unable to load entries.')); });
        });

        dashboard.registerMount('recentActivity', function (element, widget, data) {
            const activity = data && Array.isArray(data.activity) ? data.activity : [];
            const rows = activity
                .filter(item => !['update_check', 'system_update_check'].includes(item.event))
                .slice(0, 10)
                .map(function (item) {
                    const time = formatTimeAgo(item.timestamp);
                    return '<div class="dash-activity-row">' +
                        '<span class="dash-activity-row__message">' + escapeHtml(item.message || item.event || 'Activity') + '</span>' +
                        '<span class="dash-activity-row__user">' + escapeHtml(item.user || 'System') + '</span>' +
                        '<span class="dash-activity-row__time">' + escapeHtml(time || '—') + '</span>' +
                        '</div>';
                });
            setContent(element, rows.length
                ? '<div class="dash-activity-list">' + rows.join('') + '</div>'
                : empty('No recent activity.'));
        });

        dashboard.registerMount('drafts', function (element, widget, data) {
            const count = Number(data?.stats?.drafts || 0);
            if (count === 0) {
                setContent(element, empty('No drafts waiting for review.'));
                return;
            }
            setContent(element, callout(
                count,
                'Drafts needing attention',
                'Review unpublished entries and finish them when ready.',
                adminUrl('content'),
                'Open content →',
                'warning'
            ));
        });

        dashboard.registerMount('systemStatus', function (element, widget, data) {
            if (!data) return setContent(element, empty('System status is unavailable.'));
            const responseMs = Number(data.api_response_time || 0);
            const responseTone = responseMs < 100 ? 'good' : responseMs < 500 ? 'warning' : 'error';
            setContent(element, statGrid([
                statTile(responseMs + 'ms', 'API response', responseTone),
                statTile((data.media?.size_mb || 0) + ' MB', 'Media storage', 'storage'),
                statTile(data.cache?.files || 0, 'Cache files', 'cache'),
            ]));
        });

        dashboard.registerMount('security', function (element, widget, data) {
            const activity = data && Array.isArray(data.activity) ? data.activity : [];
            setContent(element, callout(
                activity.length,
                'Account events',
                activity.length === 1 ? 'Recent sign-in or account change.' : 'Recent sign-ins and account changes.',
                adminUrl('settings'),
                'Security settings →',
                activity.length > 0 ? 'info' : 'muted'
            ));
        });

        dashboard.registerMount('updates', function (element, widget, data) {
            const notifications = data && Array.isArray(data.notifications) ? data.notifications : [];
            if (!notifications.length) {
                setContent(element, empty('No updates or alerts.'));
                return;
            }
            setContent(element, '<ul class="dash-alert-list">' + notifications.map(function (item) {
                return alertItem(item.title, item.message, item.type || 'info');
            }).join('') + '</ul>');
        });

        dashboard.registerMount('commerceStats', renderCommerce);
        dashboard.registerMount('eventsUpcoming', renderEvents);
        dashboard.registerMount('studioPublishing', renderStudio);
        dashboard.registerMount('automationRuns', renderAutomation);
        return true;
    }

    function sortByUpdated(left, right) {
        return new Date(right.modified_at || right.updated_at || right.created_at || 0) -
            new Date(left.modified_at || left.updated_at || left.created_at || 0);
    }

    function renderCommerce(element, widget, data) {
        const summary = data && data.summary;
        if (!summary) {
            setContent(element, empty('No commerce orders yet.'));
            return;
        }
        setContent(element, statGrid([
            statTile(formatCents(summary.revenue_cents), 'Revenue', 'commerce'),
            statTile(summary.order_count || 0, 'Orders', 'commerce'),
        ]));
    }

    function renderEvents(element, widget, data) {
        const events = data && Array.isArray(data.upcoming) ? data.upcoming : [];
        if (!events.length) {
            setContent(element, empty('No upcoming events.'));
            return;
        }
        const items = events.slice(0, 5).map(function (event) {
            const starts = event.start ? formatTimeAgo(event.start) : '';
            return feedItem({
                title: event.title || 'Untitled event',
                meta: starts ? escapeHtml(starts) : '',
            });
        });
        const footer = data.events_url
            ? '<a class="dash-widget-footer-link" href="' + escapeHtml(data.events_url) + '">Manage events →</a>'
            : '';
        setContent(element, feedList(items) + footer);
    }

    function renderStudio(element, widget, data) {
        setContent(element, statGrid([
            statTile(data?.published || 0, 'Published', 'studio'),
            statTile(data?.drafts || 0, 'Drafts', 'studio'),
        ]));
    }

    function renderAutomation(element, widget, data) {
        setContent(element, statGrid([
            statTile(data?.enabled || 0, 'Active', 'automation'),
            statTile(data?.queue?.pending || 0, 'Queued', 'automation'),
        ]));
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
            body.innerHTML = '<p class="dash-empty dash-empty--loading">Loading…</p>';
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
