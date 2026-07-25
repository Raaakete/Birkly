(function () {
    'use strict';

    const refreshTimers = [];
    const dashboardState = {
        widgets: [],
        layout: {
            widget_order: [],
            hidden_widgets: [],
            widget_sizes: {},
            customized: false,
        },
        pickerDraft: null,
        draggedPickerIndex: null,
    };

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
        if (status === 'pending_review') {
            return '<span class="dash-badge dash-badge--review">Pending review</span>';
        }
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

            function formatCountry(code) {
                if (!code || code === 'unknown') return 'Not detected';
                if (code === 'LOCAL') return 'Local network';
                try {
                    if (typeof Intl !== 'undefined' && Intl.DisplayNames) {
                        const name = new Intl.DisplayNames([navigator.language || 'en'], { type: 'region' }).of(code);
                        if (name) return name;
                    }
                } catch (_) { /* ignore */ }
                return code;
            }

            function formatLabel(raw, type) {
                const value = String(raw || '').trim();
                if (!value || value.toLowerCase() === 'unknown') {
                    return 'Not detected';
                }
                if (type === 'device') {
                    return { mobile: 'Mobile', tablet: 'Tablet', desktop: 'Desktop', bot: 'Bot' }[value.toLowerCase()] || value.charAt(0).toUpperCase() + value.slice(1);
                }
                if (type === 'source') {
                    if (value === 'direct') return 'Direct';
                    if (value.indexOf('referral:') === 0) return value.slice(9);
                    if (value.indexOf('campaign:') === 0) return value.slice(9).replace('/', ' · ');
                }
                if (type === 'path') {
                    return value.length > 42 ? '…' + value.slice(-39) : value;
                }
                if (type === 'country') {
                    return formatCountry(value);
                }
                return value;
            }

            function renderBarGroup(title, rows, labelKey, valueKey, labelType) {
                if (!Array.isArray(rows) || rows.length === 0) {
                    return '<div class="analytics-bar-group analytics-bar-group--empty">' +
                        '<h4 class="analytics-bar-group__title">' + escapeHtml(title) + '</h4>' +
                        '<p class="analytics-bar-group__empty">No data yet</p></div>';
                }

                const max = Math.max.apply(null, rows.map(function (row) { return Number(row[valueKey] || 0); }).concat([1]));
                const items = rows.map(function (row) {
                    const count = Number(row[valueKey] || 0);
                    const width = Math.max(8, Math.round((count / max) * 100));
                    const label = formatLabel(row[labelKey], labelType);
                    return '<li class="analytics-bar">' +
                        '<div class="analytics-bar__meta">' +
                        '<span class="analytics-bar__label" title="' + escapeHtml(String(row[labelKey] || '')) + '">' + escapeHtml(label) + '</span>' +
                        '<span class="analytics-bar__value">' + escapeHtml(count.toLocaleString()) + '</span>' +
                        '</div>' +
                        '<div class="analytics-bar__track" aria-hidden="true">' +
                        '<span class="analytics-bar__fill" style="width:' + width + '%"></span>' +
                        '</div></li>';
                }).join('');

                return '<div class="analytics-bar-group">' +
                    '<h4 class="analytics-bar-group__title">' + escapeHtml(title) + '</h4>' +
                    '<ul class="analytics-bars">' + items + '</ul></div>';
            }

            const insights = data.insights || {};
            const pageViews = Number(data.total_page_views || 0);
            const visitors = Number(data.total_visitors || 0);
            const viewsPerVisitor = visitors > 0 ? (pageViews / visitors).toFixed(1) : '—';

            setContent(element,
                '<div class="analytics-widget">' +
                '<div class="analytics-widget__summary">' +
                '<div class="analytics-kpi analytics-kpi--primary">' +
                '<span class="analytics-kpi__value">' + escapeHtml(pageViews.toLocaleString()) + '</span>' +
                '<span class="analytics-kpi__label">Page views</span>' +
                '<span class="analytics-kpi__hint">Last 30 days</span>' +
                '</div>' +
                '<div class="analytics-kpi">' +
                '<span class="analytics-kpi__value">' + escapeHtml(visitors.toLocaleString()) + '</span>' +
                '<span class="analytics-kpi__label">Visitors</span>' +
                '<span class="analytics-kpi__hint">Last 30 days</span>' +
                '</div>' +
                '<div class="analytics-kpi analytics-kpi--muted">' +
                '<span class="analytics-kpi__value">' + escapeHtml(String(viewsPerVisitor)) + '</span>' +
                '<span class="analytics-kpi__label">Pages / visitor</span>' +
                '<span class="analytics-kpi__hint">Engagement</span>' +
                '</div>' +
                '</div>' +
                '<div class="analytics-widget__panels">' +
                '<section class="analytics-panel">' +
                '<h3 class="analytics-panel__title">Visitor journey</h3>' +
                renderBarGroup('Entrance pages', insights.entrance_pages, 'path', 'entrances', 'path') +
                renderBarGroup('Exit pages', insights.exit_pages, 'path', 'exits', 'path') +
                renderBarGroup('Traffic sources', insights.traffic_sources, 'source', 'visits', 'source') +
                '</section>' +
                '<section class="analytics-panel">' +
                '<h3 class="analytics-panel__title">Audience</h3>' +
                renderBarGroup('Devices', insights.devices, 'device_type', 'visits', 'device') +
                renderBarGroup('Locations', insights.countries, 'country', 'visits', 'country') +
                renderBarGroup('Browsers', insights.browsers, 'browser', 'visits', 'browser') +
                '</section>' +
                '</div>' +
                '<div class="dash-widget-footer-links">' +
                '<a class="dash-widget-footer-link" href="' + escapeHtml(adminUrl('settings') + '#domains') + '">Per-domain reports in Settings → Domains</a>' +
                '</div>' +
                '</div>'
            );
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

        dashboard.registerMount('pendingReview', function (element, widget, data) {
            const entries = data && Array.isArray(data.entries) ? data.entries : [];
            if (!entries.length) {
                setContent(element, empty('No entries are waiting for review.'));
                return;
            }
            const items = entries.map(function (entry) {
                const modified = formatTimeAgo(entry.updated_at);
                const metaParts = [
                    'By ' + escapeHtml(entry.submitted_by || 'Unknown'),
                    'Reviewer: ' + escapeHtml(entry.reviewer || 'Unassigned'),
                ];
                if (modified) metaParts.push(escapeHtml(modified));
                return feedItem({
                    title: entry.title || 'Untitled',
                    href: adminUrl('entry-editor', { collection: entry.collection, entry: entry.id || '' }),
                    meta: metaParts.join(' · '),
                    aside: statusBadge('pending_review'),
                });
            });
            const queueLinks = Object.values(entries.reduce(function (acc, entry) {
                const slug = entry.collection || '';
                if (!slug) return acc;
                if (!acc[slug]) {
                    acc[slug] = {
                        slug: slug,
                        name: entry.collection_name || entry.collection || slug,
                        count: 0,
                    };
                }
                acc[slug].count += 1;
                return acc;
            }, {})).map(function (queue) {
                const label = queue.name + ' (' + queue.count + ')';
                return '<a class="dash-widget-footer-link" href="' + escapeHtml(adminUrl('entries', { collection: queue.slug, status: 'pending_review' })) + '">' +
                    escapeHtml(label) + ' →</a>';
            }).join('');
            const footer = '<div class="dash-widget-footer-links">' + queueLinks + '</div>';
            setContent(element, feedList(items) + footer);
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

    function widgetsForDisplay() {
        return dashboardState.widgets.filter(widget => !widget.hidden);
    }

    function buildWidgetCard(widget) {
        const card = document.createElement('section');
        card.className = 'card dash-widget';
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
        return { card: card, body: body };
    }

    function renderWidgets() {
        const grid = document.getElementById('dashboard-widget-grid');
        if (!grid || !window.Birkly?.Dashboard) return;

        refreshTimers.splice(0).forEach(timer => clearInterval(timer));
        grid.replaceChildren();

        widgetsForDisplay().forEach(function (widget) {
            const built = buildWidgetCard(widget);
            grid.appendChild(built.card);
            loadAndMountWidget(built.body, widget);
            if (Number(widget.refresh) > 0) {
                refreshTimers.push(window.setInterval(function () {
                    loadAndMountWidget(built.body, widget);
                }, Number(widget.refresh) * 1000));
            }
        });
    }

    function getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    }

    async function persistLayout(layout, reloadAfterSave) {
        const response = await fetch(apiUrl('/api/dashboard.php?action=save_layout'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ layout: layout, _csrf: getCsrfToken() }),
        });
        const payload = await response.json();
        if (payload?.data?.layout) {
            dashboardState.layout = payload.data.layout;
        }
        if (reloadAfterSave) {
            await loadWidgetManifest();
        }
        return payload;
    }

    async function resetLayout() {
        const response = await fetch(apiUrl('/api/dashboard.php?action=save_layout'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ reset: true, _csrf: getCsrfToken() }),
        });
        const payload = await response.json();
        if (payload?.data?.layout) {
            dashboardState.layout = payload.data.layout;
        }
        await loadWidgetManifest();
        return payload;
    }

    function buildPickerDraft() {
        const hidden = new Set(dashboardState.layout.hidden_widgets || []);
        const order = dashboardState.layout.widget_order || [];
        const widgetsById = Object.fromEntries(dashboardState.widgets.map(widget => [widget.id, widget]));
        const ordered = [];

        order.forEach(function (widgetId) {
            if (widgetsById[widgetId]) {
                ordered.push(widgetsById[widgetId]);
                delete widgetsById[widgetId];
            }
        });
        dashboardState.widgets.forEach(function (widget) {
            if (widgetsById[widget.id]) {
                ordered.push(widget);
            }
        });

        return ordered.map(function (widget) {
            return {
                id: widget.id,
                title: widget.title || widget.id,
                visible: !hidden.has(widget.id),
            };
        });
    }

    function renderWidgetPicker() {
        const list = document.getElementById('dashboard-widget-picker');
        if (!list || !dashboardState.pickerDraft) return;
        list.replaceChildren();

        dashboardState.pickerDraft.forEach(function (item, index) {
            const row = document.createElement('li');
            row.className = 'dashboard-widget-picker__item';
            row.draggable = true;
            row.dataset.index = String(index);

            const handle = document.createElement('span');
            handle.className = 'dashboard-widget-picker__handle';
            handle.setAttribute('aria-hidden', 'true');
            handle.textContent = '⋮⋮';

            const label = document.createElement('label');
            label.className = 'dashboard-widget-picker__label';
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.checked = item.visible;
            checkbox.addEventListener('change', function () {
                dashboardState.pickerDraft[index].visible = checkbox.checked;
            });
            label.append(checkbox, document.createTextNode(' ' + item.title));

            row.append(handle, label);
            list.appendChild(row);
        });

        setupPickerDragAndDrop(list);
    }

    function setupPickerDragAndDrop(list) {
        let draggedIndex = null;

        list.querySelectorAll('.dashboard-widget-picker__item').forEach(function (row) {
            row.addEventListener('dragstart', function (event) {
                draggedIndex = Number(row.dataset.index);
                row.classList.add('dashboard-widget-picker__item--dragging');
                if (event.dataTransfer) {
                    event.dataTransfer.effectAllowed = 'move';
                    event.dataTransfer.setData('text/plain', String(draggedIndex));
                }
            });

            row.addEventListener('dragend', function () {
                row.classList.remove('dashboard-widget-picker__item--dragging');
                list.querySelectorAll('.dashboard-widget-picker__item--drag-over').forEach(function (node) {
                    node.classList.remove('dashboard-widget-picker__item--drag-over');
                });
            });

            row.addEventListener('dragover', function (event) {
                event.preventDefault();
                row.classList.add('dashboard-widget-picker__item--drag-over');
            });

            row.addEventListener('dragleave', function () {
                row.classList.remove('dashboard-widget-picker__item--drag-over');
            });

            row.addEventListener('drop', function (event) {
                event.preventDefault();
                row.classList.remove('dashboard-widget-picker__item--drag-over');
                const sourceIndex = draggedIndex ?? Number(event.dataTransfer?.getData('text/plain'));
                const targetIndex = Number(row.dataset.index);
                if (!Number.isInteger(sourceIndex) || !Number.isInteger(targetIndex) || sourceIndex === targetIndex) {
                    return;
                }
                const draft = dashboardState.pickerDraft.slice();
                const [moved] = draft.splice(sourceIndex, 1);
                draft.splice(targetIndex, 0, moved);
                dashboardState.pickerDraft = draft;
                renderWidgetPicker();
            });
        });
    }

    function openCustomizeModal() {
        dashboardState.pickerDraft = buildPickerDraft();
        renderWidgetPicker();
        document.getElementById('dashboard-customize-modal')?.classList.add('active');
    }

    function closeCustomizeModal() {
        document.getElementById('dashboard-customize-modal')?.classList.remove('active');
        dashboardState.pickerDraft = null;
    }

    async function applyPickerDraft() {
        if (!dashboardState.pickerDraft) return;
        const hiddenWidgets = dashboardState.pickerDraft
            .filter(item => !item.visible)
            .map(item => item.id);
        const widgetOrder = dashboardState.pickerDraft.map(item => item.id);
        dashboardState.layout = {
            widget_order: widgetOrder,
            hidden_widgets: hiddenWidgets,
            widget_sizes: {},
            customized: true,
        };
        closeCustomizeModal();
        await persistLayout(dashboardState.layout, true);
    }

    function setupCustomizeUi() {
        document.getElementById('dashboard-customize-btn')?.addEventListener('click', openCustomizeModal);
        document.getElementById('dashboard-customize-close')?.addEventListener('click', closeCustomizeModal);
        document.getElementById('dashboard-customize-cancel')?.addEventListener('click', closeCustomizeModal);
        document.getElementById('dashboard-customize-save')?.addEventListener('click', applyPickerDraft);
        document.getElementById('dashboard-reset-layout-btn')?.addEventListener('click', async function () {
            await resetLayout();
            closeCustomizeModal();
        });
        document.getElementById('dashboard-customize-modal')?.addEventListener('click', function (event) {
            if (event.target === event.currentTarget) {
                closeCustomizeModal();
            }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeCustomizeModal();
            }
        });
    }

    function loadWidgetManifest() {
        const grid = document.getElementById('dashboard-widget-grid');
        if (!grid || !registerCoreMounts()) return;
        return fetch(apiUrl('/api/dashboard.php?action=widgets'), { credentials: 'same-origin' })
            .then(response => response.json())
            .then(function (payload) {
                dashboardState.widgets = payload?.data?.widgets || [];
                dashboardState.layout = payload?.data?.layout || dashboardState.layout;
                renderWidgets();
            })
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
            const csrf = getCsrfToken();
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
        setupCustomizeUi();
        loadWidgetManifest();
    }

    window.addEventListener('beforeunload', () => refreshTimers.forEach(timer => clearInterval(timer)));
    document.readyState === 'loading'
        ? document.addEventListener('DOMContentLoaded', initDashboard)
        : initDashboard();
}());
