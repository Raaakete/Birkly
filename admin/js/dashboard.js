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
        return '<div class="empty-state empty-state--compact"><p class="empty-state-text">' +
            escapeHtml(message) + '</p></div>';
    }

    function loading(message) {
        return '<div class="loading-state empty-state--compact"><p class="empty-state-text">' +
            escapeHtml(message) + '</p></div>';
    }

    function errorState(message) {
        return '<div class="error-state empty-state--compact"><p class="empty-state-title">' +
            escapeHtml(message) + '</p></div>';
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

    const STAT_TONE_CLASS = {
        good: ' u-fg-success',
        published: ' u-fg-success',
        warning: ' u-fg-warning',
        drafts: ' u-fg-warning',
        error: ' u-fg-danger',
    };

    function birklyIcon(name, opts) {
        return (window.Birkly && typeof Birkly.icon === 'function') ? Birkly.icon(name, opts) : '';
    }

    function statTile(value, label, tone, iconName) {
        const toneClass = STAT_TONE_CLASS[tone] || '';
        const icon = iconName ? birklyIcon(iconName, { size: 14, class: 'u-fg-tertiary' }) : '';
        return '<div class="stat"><span class="stat-label u-row-1">' + icon + escapeHtml(label) +
            '</span><span class="stat-value u-numeric' + toneClass + '">' + escapeHtml(value ?? 0) + '</span></div>';
    }

    function statGrid(tiles) {
        return '<div class="dash-metrics">' + tiles.join('') + '</div>';
    }

    function feedList(items) {
        return '<div class="data-list">' + items.join('') + '</div>';
    }

    // Whole row is the click target (not just the title) — the row itself
    // is an <a class="data-list-item"> per the .data-list component contract
    // (components/cards.css), which already carries the full-row hover tint.
    // A chevron fades in on hover as the "creative but subtle" affordance.
    function feedItem(options) {
        const title = '<span class="data-list-title">' + escapeHtml(options.title) + '</span>';
        const meta = options.meta ? '<span class="data-list-meta">' + options.meta + '</span>' : '';
        const aside = options.aside ? '<div class="data-list-actions">' + options.aside + '</div>' : '';
        const chevron = options.href
            ? '<span class="dash-row-arrow" aria-hidden="true">' + birklyIcon('caret-right', { size: 14 }) + '</span>'
            : '';
        const tag = options.href ? 'a' : 'div';
        const hrefAttr = options.href ? ' href="' + escapeHtml(options.href) + '"' : '';
        return '<' + tag + ' class="data-list-item"' + hrefAttr + '><div class="data-list-main">' + title + meta + '</div>' + aside + chevron + '</' + tag + '>';
    }

    function statusBadge(status) {
        if (status === 'pending_review') {
            return '<span class="badge badge-warning">Pending review</span>';
        }
        const published = status === 'published';
        return published
            ? '<span class="badge badge-success">Published</span>'
            : '<span class="badge badge-neutral">Draft</span>';
    }

    const CALLOUT_TONE_CLASS = {
        warning: 'callout-warning',
        info: 'callout-info',
        muted: 'callout-neutral',
    };

    function callout(value, title, description, href, hrefLabel, tone) {
        const link = href
            ? '<a href="' + escapeHtml(href) + '">' + escapeHtml(hrefLabel) + '</a>'
            : '';
        return '<div class="callout ' + (CALLOUT_TONE_CLASS[tone] || 'callout-neutral') + '">' +
            '<span class="u-text-2xl u-weight-semibold u-numeric u-leading-tight">' + escapeHtml(value) + '</span>' +
            '<div class="callout-body"><p class="callout-title">' + escapeHtml(title) + '</p>' +
            (description ? '<p>' + escapeHtml(description) + '</p>' : '') + link + '</div></div>';
    }

    function alertItem(title, message, tone) {
        return '<div class="callout ' + (CALLOUT_TONE_CLASS[tone] || 'callout-info') + '">' +
            '<div class="callout-body"><p class="callout-title">' + escapeHtml(title) + '</p>' +
            '<p>' + escapeHtml(message) + '</p></div></div>';
    }

    function footerLinks(links) {
        return links ? '<div class="u-cluster-3 u-text-sm u-mt-3">' + links + '</div>' : '';
    }

    function registerCoreMounts() {
        const dashboard = window.Birkly && Birkly.Dashboard;
        if (!dashboard) return false;

        dashboard.registerMount('contentSnapshot', function (element, widget, data) {
            const stats = data && data.stats ? data.stats : {};
            const collections = Number(stats.collections || 0);
            const total = Number(stats.total_entries || 0);
            const published = Number(stats.published || 0);
            const drafts = Number(stats.drafts || 0);
            const publishedPct = total > 0 ? Math.round((published / total) * 100) : 0;

            setContent(element,
                '<div class="card-section">' +
                '<div class="u-grid-2">' +
                statTile(collections, 'Collections', null, 'stack') +
                statTile(total, 'Entries', null, 'article') +
                '</div>' +
                '</div>' +
                '<div class="card-section">' +
                '<div class="u-split">' +
                '<span class="stat-label">Published</span>' +
                '<span class="badge badge-success u-numeric">' + publishedPct + '%</span>' +
                '</div>' +
                '<div class="progress u-mt-2"><div class="progress-bar" style="width:' + publishedPct + '%"></div></div>' +
                '<div class="u-split u-mt-2">' +
                '<span class="u-text-xs u-fg-secondary">' + birklyIcon('check-square', { size: 12 }) + ' <span class="u-numeric">' + published + '</span> published</span>' +
                '<span class="u-text-xs u-fg-secondary">' + birklyIcon('file-text', { size: 12 }) + ' <span class="u-numeric">' + drafts + '</span> draft' + (drafts === 1 ? '' : 's') + '</span>' +
                '</div>' +
                '</div>'
            );
        });

        dashboard.registerMount('analyticsMini', function (element, widget, data) {
            var analyticsSettingsUrl = adminUrl('settings', { tab: 'analytics' });
            if (!data) {
                setContent(element, callout('—', 'Analytics not configured', 'Choose a preset under Settings → Analytics.', analyticsSettingsUrl, 'Open Analytics →', 'muted'));
                return;
            }
            if (data.analytics_collecting === false || data.analytics_preset === 'off') {
                setContent(element, callout('—', 'Analytics is Off', 'Turn on Minimal, Standard, or Advanced under Settings → Analytics.', analyticsSettingsUrl, 'Open Analytics →', 'muted'));
                return;
            }
            if (!data.has_analytics) {
                setContent(element, callout('—', 'Tracking not enabled yet', 'Re-save your Analytics preset to enable site tracking, or enable a domain under Site delivery.', analyticsSettingsUrl, 'Open Analytics →', 'muted'));
                return;
            }
            var pageViews = Number(data.total_page_views || 0);
            var visitors = Number(data.total_visitors || 0);
            if (pageViews === 0 && visitors === 0) {
                var waitMsg = data.analytics_consent_required
                    ? 'Anonymous page views are counted without consent. Accept the banner for detailed metrics.'
                    : 'Preset is on. Visit your public site (or localhost preview) to record the first page views.';
                setContent(element, callout('0', 'Waiting for traffic', waitMsg, analyticsSettingsUrl, 'View Analytics →', 'muted'));
                return;
            }

            function formatCountry(code) {
                if (!code || code === 'unknown') return 'Not detected';
                if (code === 'LOCAL') return 'Local network';
                try {
                    if (typeof Intl !== 'undefined' && Intl.DisplayNames) {
                        var name = new Intl.DisplayNames([navigator.language || 'en'], { type: 'region' }).of(code);
                        if (name) return name;
                    }
                } catch (_) { /* ignore */ }
                return code;
            }

            function formatLabel(raw, type) {
                var value = String(raw || '').trim();
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
                    return '<div class="u-stack-2">' +
                        '<h4 class="u-label">' + escapeHtml(title) + '</h4>' +
                        '<p class="u-text-sm u-fg-tertiary">No data yet</p></div>';
                }

                var max = Math.max.apply(null, rows.map(function (row) { return Number(row[valueKey] || 0); }).concat([1]));
                var items = rows.map(function (row) {
                    var count = Number(row[valueKey] || 0);
                    var width = Math.max(8, Math.round((count / max) * 100));
                    var label = formatLabel(row[labelKey], labelType);
                    return '<li class="u-stack-1">' +
                        '<div class="u-split u-gap-3">' +
                        '<span class="u-text-sm u-truncate" title="' + escapeHtml(String(row[labelKey] || '')) + '">' + escapeHtml(label) + '</span>' +
                        '<span class="u-text-sm u-numeric u-fg-secondary u-shrink-0">' + escapeHtml(count.toLocaleString()) + '</span>' +
                        '</div>' +
                        '<div class="progress" aria-hidden="true">' +
                        '<div class="progress-bar" style="width:' + width + '%"></div>' +
                        '</div></li>';
                }).join('');

                return '<div class="u-stack-2">' +
                    '<h4 class="u-label">' + escapeHtml(title) + '</h4>' +
                    '<ul class="u-stack-3">' + items + '</ul></div>';
            }

            var insights = data.insights || {};
            var viewsPerVisitor = visitors > 0 ? (pageViews / visitors).toFixed(1) : '—';
            var health = data.collection_health || {};
            var healthNote = '';
            if (Array.isArray(health.capped_domains) && health.capped_domains.length) {
                healthNote = '<p class="u-text-sm u-fg-secondary u-m-0">Daily cap reached for some domains — counts may be understated.</p>';
            } else if (health.consent_required) {
                healthNote = '<p class="u-text-sm u-fg-secondary u-m-0">Anonymous visits count without consent; detailed metrics need banner acceptance.</p>';
            }
            if ((data.tracking_count || 0) > 1) {
                healthNote += '<p class="u-text-sm u-fg-secondary u-m-0">Combined across ' + Number(data.tracking_count).toLocaleString() + ' enabled domains.</p>';
            }

            function kpi(value, label, hint) {
                return '<div class="stat">' +
                    '<span class="stat-label">' + escapeHtml(label) + '</span>' +
                    '<span class="stat-value u-numeric">' + escapeHtml(value) + '</span>' +
                    '<span class="stat-meta">' + escapeHtml(hint) + '</span>' +
                    '</div>';
            }

            // A titled group of bar charts. Previously wrapped in a nested
            // `.card` inside this widget's own card body (box-in-box) — now a
            // `.card-section`, so the divider comes from spacing, not a
            // second border (shared rules: no box-in-box).
            function panel(title, groups) {
                return '<div class="u-stack-4">' +
                    '<h3 class="card-section-title">' + escapeHtml(title) + '</h3>' +
                    groups + '</div>';
            }

            setContent(element,
                '<div class="card-section">' +
                '<div class="u-grid-3">' +
                kpi(pageViews.toLocaleString(), 'Page views', 'Last 30 days · all domains') +
                kpi(visitors.toLocaleString(), 'Visitors', 'Deduped across domains') +
                kpi(String(viewsPerVisitor), 'Pages / visitor', 'Engagement') +
                '</div>' +
                (healthNote ? '<div class="u-stack-2">' + healthNote + '</div>' : '') +
                '</div>' +
                '<div class="card-section">' +
                '<div class="u-grid-2">' +
                panel('Visitor journey',
                    renderBarGroup('Entrance pages', insights.entrance_pages, 'path', 'entrances', 'path') +
                    renderBarGroup('Exit pages', insights.exit_pages, 'path', 'exits', 'path') +
                    renderBarGroup('Traffic sources', insights.traffic_sources, 'source', 'visits', 'source')) +
                panel('Audience',
                    renderBarGroup('Devices', insights.devices, 'device_type', 'visits', 'device') +
                    renderBarGroup('Locations', insights.countries, 'country', 'visits', 'country') +
                    renderBarGroup('Browsers', insights.browsers, 'browser', 'visits', 'browser')) +
                '</div>' +
                '</div>' +
                footerLinks('<a href="' + escapeHtml(adminUrl('settings') + '#domains') + '">Per-domain reports in Settings → Domains</a>')
            );
        });

        dashboard.registerMount('recentCollections', function (element) {
            setContent(element, loading('Loading collections…'));
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
                            meta: '<span class="u-numeric">' + escapeHtml(count) + '</span> ' + (Number(count) === 1 ? 'entry' : 'entries'),
                        });
                    });
                    setContent(element, feedList(items));
                })
                .catch(function () { setContent(element, errorState('Unable to load collections.')); });
        });

        dashboard.registerMount('recentEntries', function (element) {
            setContent(element, loading('Loading entries…'));
            fetch(apiUrl('/api/content.php?action=list_collections'), { credentials: 'same-origin' })
                .then(response => response.json())
                .then(function (payload) {
                    const collections = payload?.data?.collections || payload?.data || payload;
                    if (!Array.isArray(collections) || collections.length === 0) return [];
                    return Promise.all(collections.slice(0, 6).map(function (collection) {
                        const slug = collection.slug || '';
                        const collectionName = collection.name || collection.title || slug;
                        return fetch(
                            apiUrl('/api/admin_entries.php?action=list&collection=' + encodeURIComponent(slug) + '&per_page=8&sort=updated_at&order=desc'),
                            { credentials: 'same-origin' }
                        )
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
                .catch(function () { setContent(element, errorState('Unable to load entries.')); });
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
                return '<a href="' + escapeHtml(adminUrl('entries', { collection: queue.slug, status: 'pending_review' })) + '">' +
                    escapeHtml(label) + ' →</a>';
            }).join('');
            setContent(element, feedList(items) + footerLinks(queueLinks));
        });

        dashboard.registerMount('recentActivity', function (element, widget, data) {
            const activity = data && Array.isArray(data.activity) ? data.activity : [];
            const api = window.BirklyActivityRows;
            const items = activity
                .filter(item => !['update_check', 'system_update_check'].includes(item.event))
                .slice(0, 8);

            if (api && items.length) {
                setContent(element, api.renderList(items, { compact: true, expandable: false, timeStyle: 'ago' }));
                return;
            }

            const legacyItems = items.map(function (item) {
                const when = formatTimeAgo(item.timestamp);
                const metaParts = [];
                const actor = String(item.user || '').trim();
                if (actor && actor.toLowerCase() !== 'unknown') {
                    metaParts.push(escapeHtml(actor));
                }
                if (when) metaParts.push(escapeHtml(when));
                const category = String(item.category || '').trim();
                const aside = category && category !== 'other'
                    ? '<span class="badge badge-neutral">' + escapeHtml(category) + '</span>'
                    : '';
                const rowHref = item.href ? (api ? api.resolveHref(item) : adminUrl('activity')) : adminUrl('activity');
                return feedItem({
                    title: item.message || item.event || 'Activity',
                    href: rowHref,
                    meta: metaParts.join(' · '),
                    aside: aside,
                });
            });
            setContent(element, legacyItems.length
                ? feedList(legacyItems)
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
            if (!data) return setContent(element, errorState('System status is unavailable.'));
            const responseMs = Number(data.api_response_time || 0);
            const responseTone = responseMs < 100 ? 'success' : responseMs < 500 ? 'warning' : 'danger';
            const responseLabel = responseMs < 100 ? 'Fast' : responseMs < 500 ? 'Moderate' : 'Slow';
            const mediaMb = Number(data.media?.size_mb || 0);
            // No storage quota is exposed by the API — this reads as a soft
            // reference band (500 MB), not a literal "% of your plan".
            const mediaPct = Math.min(100, Math.round((mediaMb / 500) * 100));
            const cacheEnabled = !!(data.cache && data.cache.enabled);
            const cacheFiles = Number(data.cache?.files || 0);

            setContent(element,
                '<div class="card-section u-stack-1">' +
                '<div class="u-split">' +
                '<span class="stat-label">API response</span>' +
                '<span class="badge badge-' + responseTone + '">' + escapeHtml(responseLabel) + '</span>' +
                '</div>' +
                '<span class="stat-value u-numeric">' + responseMs + 'ms</span>' +
                '</div>' +
                '<div class="card-section">' +
                '<div class="u-split">' +
                '<span class="stat-label">Media storage</span>' +
                '<span class="stat-meta u-numeric">' + mediaMb + ' MB</span>' +
                '</div>' +
                '<div class="progress u-mt-2"><div class="progress-bar" style="width:' + mediaPct + '%"></div></div>' +
                '</div>' +
                '<div class="card-section">' +
                '<div class="u-split">' +
                '<span class="stat-label">Cache files</span>' +
                '<span class="badge ' + (cacheEnabled ? 'badge-neutral' : 'badge-warning') + ' u-numeric">' + cacheFiles + '</span>' +
                '</div>' +
                '</div>'
            );
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
            setContent(element, '<div class="u-stack-2">' + notifications.map(function (item) {
                return alertItem(item.title, item.message, item.type || 'info');
            }).join('') + '</div>');
        });

        return true;
    }

    function sortByUpdated(left, right) {
        return new Date(right.modified_at || right.updated_at || right.created_at || 0) -
            new Date(left.modified_at || left.updated_at || left.created_at || 0);
    }

    function loadAndMountWidget(body, widget) {
        return Birkly.Dashboard.fetchWidgetData(widget)
            .then(function (data) {
                return Birkly.Dashboard.mountWidget(body, widget, data);
            })
            .catch(function () {
                setContent(body, errorState('Unable to load this widget.'));
                return false;
            });
    }

    function widgetsForDisplay() {
        return dashboardState.widgets.filter(widget => !widget.hidden);
    }

    function widgetSizeClass(size) {
        // Public sizes: 1× (sm/md) and 2× (lg/full).
        if (size === 'lg' || size === 'full' || size === '2x') {
            return 'dash-widget--2x';
        }
        return 'dash-widget--1x';
    }

    function buildWidgetCard(widget) {
        const card = document.createElement('section');
        card.className = 'card dash-widget ' + widgetSizeClass(widget.size);
        card.dataset.dashboardWidget = widget.id;
        card.dataset.widgetSize = (widget.size === 'lg' || widget.size === 'full' || widget.size === '2x')
            ? '2x'
            : '1x';

        const heading = document.createElement('div');
        heading.className = 'card-header';

        const title = document.createElement('h2');
        title.className = 'card-title';
        title.textContent = widget.title || widget.id;
        heading.appendChild(title);

        const body = document.createElement('div');
        body.className = 'card-body dash-widget-body';
        body.innerHTML = loading('Loading…');
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
            .catch(function () { grid.innerHTML = errorState('Unable to load dashboard widgets.'); });
    }

    function setupOnboardingHandlers() {
        const modal = document.getElementById('onboarding-complete-modal');
        const close = async () => {
            if (modal) modal.style.display = 'none';
            if (modal?.dataset?.showWelcome === '1') {
                await postOnboardingAction('dismiss_welcome_modal', {});
                modal.dataset.showWelcome = '0';
            }
            const url = new URL(window.location.href);
            url.searchParams.delete('demo_website_url');
            url.searchParams.delete('starter_template');
            url.searchParams.delete('onboarding_complete');
            window.history.replaceState({}, '', url);
        };
        document.getElementById('onboarding-complete-close')?.addEventListener('click', close);
        document.getElementById('onboarding-complete-close-btn')?.addEventListener('click', close);

        const params = new URLSearchParams(window.location.search);
        const shouldShowWelcome = modal?.dataset?.showWelcome === '1'
            || params.get('onboarding_complete') === 'true';
        if (shouldShowWelcome && modal) {
            modal.style.display = 'flex';
            const demoUrl = params.get('demo_website_url');
            const demoCard = document.getElementById('onboarding-complete-demo-card');
            const demoLink = document.getElementById('onboarding-complete-demo-link');
            if (demoUrl && demoCard && demoLink) {
                demoCard.hidden = false;
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
