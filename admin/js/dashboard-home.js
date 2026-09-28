/**
 * Birch Dashboard home (P96-M8): greeting, status, metric bar, inbox, traffic.
 * Plugin widgets keep rendering below through dashboard.js.
 */
(function () {
    'use strict';

    const root = document.querySelector('.dashboard-home');
    if (!root) {
        return;
    }

    const api = (path) => (window.Birkly && typeof Birkly.url === 'function' ? Birkly.url(path) : path);
    const adminUrl = (page, params) => (window.Birkly && typeof Birkly.adminUrl === 'function')
        ? Birkly.adminUrl(page, params)
        : '/admin/index.php?' + new URLSearchParams({ page: page, ...(params || {}) }).toString();
    const icon = (name, opts) => (window.Birkly && typeof Birkly.icon === 'function' ? Birkly.icon(name, opts) : '');
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    const locale = document.documentElement.lang || navigator.language || 'en';

    function t(key, fallback, params) {
        let text = (window.Birkly && Birkly.i18n) ? Birkly.i18n.t('admin.' + key, fallback) : fallback;
        Object.keys(params || {}).forEach((name) => {
            text = text.split('{' + name + '}').join(String(params[name]));
        });
        return text;
    }

    function esc(value) {
        const el = document.createElement('div');
        el.textContent = String(value ?? '');
        return el.innerHTML;
    }

    function getJson(path) {
        return fetch(api(path), { credentials: 'same-origin' }).then((r) => r.json()).then((p) => p?.data ?? p);
    }

    function number(value) {
        return Number(value || 0).toLocaleString(locale);
    }

    function ago(stamp) {
        if (!stamp) return '';
        const diff = Math.floor((Date.now() - new Date(stamp).getTime()) / 1000);
        if (diff < 60) return t('dash_time_just_now', 'Just now');
        if (diff < 3600) return t('dash_time_minutes_ago', '{n}m ago', { n: Math.floor(diff / 60) });
        if (diff < 86400) return t('dash_time_hours_ago', '{n}h ago', { n: Math.floor(diff / 3600) });
        if (diff < 604800) return t('dash_time_days_ago', '{n}d ago', { n: Math.floor(diff / 86400) });
        return new Date(stamp).toLocaleDateString(locale);
    }

    /* ---------- Greeting and status ---------- */
    function renderGreeting() {
        const el = document.getElementById('dashboard-greeting');
        const hour = new Date().getHours();
        const key = hour < 12 ? 'dash_greeting_morning' : hour < 18 ? 'dash_greeting_afternoon' : 'dash_greeting_evening';
        const fallback = hour < 12 ? 'Good morning, {name}' : hour < 18 ? 'Good afternoon, {name}' : 'Good evening, {name}';
        const name = el?.dataset.name || '';
        if (el && name) {
            el.textContent = t(key, fallback, { name: name });
        }
        const date = document.getElementById('dashboard-date');
        if (date) {
            date.textContent = new Intl.DateTimeFormat(locale, { weekday: 'long', day: 'numeric', month: 'long' }).format(new Date());
        }
    }

    function renderStatus() {
        const el = document.getElementById('dashboard-status');
        if (!el) return;
        const domains = Number(el.dataset.domains || 0);
        const chips = [];
        if (domains > 0) {
            chips.push('<span class="status is-published">' + esc(domains === 1
                ? t('dash_live_on_one_domain', 'Live on 1 domain')
                : t('dash_live_on_domains', 'Live on {count} domains', { count: domains })) + '</span>');
        }
        el.innerHTML = chips.join('');
        if (el.dataset.admin !== '1') return;
        getJson('/api/dashboard.php?action=performance').then((data) => {
            const ms = Number(data?.api_response_time || 0);
            const healthy = ms < 500;
            el.insertAdjacentHTML('beforeend', '<span class="status is-plain dashboard-health">' + icon(healthy ? 'check-circle' : 'alert') + esc(healthy
                ? t('dash_system_healthy', 'System healthy')
                : t('dash_system_slow', 'System slow')) + '</span>');
        }).catch(() => {});
    }

    /* ---------- Metric bar ---------- */
    const metricsEl = document.getElementById('dashboard-metrics');
    const metricsMenu = document.getElementById('dashboard-metrics-menu');
    const customizeBtn = document.getElementById('dashboard-metrics-customize');
    let bar = { slots: [], available: [], pinned: [] };
    let analytics = null;

    const METRIC_META = {
        drafts: () => t('dash_metric_drafts_meta', 'waiting to be published'),
        collections: () => t('dash_metric_collections_meta', 'content types'),
        media: () => t('dash_metric_media_meta', 'files in the library'),
        page_views: () => t('dash_metric_page_views_meta', 'last 30 days'),
        visitors: () => t('dash_metric_visitors_meta', 'last 30 days'),
    };

    function metricValue(metric) {
        if (metric.source === 'analytics') {
            if (!analytics) return '—';
            return number(metric.id === 'page_views' ? analytics.total_page_views : analytics.total_visitors);
        }
        if (metric.format === 'money') {
            return esc(Birkly.money(Number(metric.value || 0), metric.currency || 'EUR'));
        }
        return number(metric.value);
    }

    function metricHtml(metric) {
        let value = metricValue(metric);
        let meta = metric.meta ? esc(metric.meta) : (METRIC_META[metric.id] ? esc(METRIC_META[metric.id]()) : '');
        let meter = '';
        if (metric.id === 'published') {
            const total = Number(metric.total || 0);
            const percent = total > 0 ? Math.round((Number(metric.value || 0) / total) * 100) : 0;
            value += ' <small>/ ' + number(total) + '</small>';
            meta = esc(t('dash_metric_published_meta', '{percent}% of all entries', { percent: percent }));
            meter = '<div class="meter" style="--value: ' + percent + '"><span></span></div>';
        }
        const label = t('dash_metric_' + metric.id, metric.label);
        return '<div class="metric" data-metric="' + esc(metric.id) + '">' +
            '<span class="metric-label">' + icon(metric.icon || 'dashboard') + esc(label) + '</span>' +
            '<span class="metric-value">' + value + '</span>' +
            (meta ? '<span class="metric-meta">' + meta + '</span>' : '') + meter + '</div>';
    }

    function renderMetrics() {
        if (!metricsEl) return;
        metricsEl.querySelectorAll('.metric').forEach((m) => m.remove());
        const custom = metricsEl.querySelector('.metrics-customize');
        custom.insertAdjacentHTML('beforebegin', bar.slots.map(metricHtml).join(''));
        metricsEl.setAttribute('aria-busy', 'false');
        renderMetricsMenu();
    }

    function renderMetricsMenu() {
        if (!metricsMenu) return;
        const pinned = bar.pinned || [];
        const shown = bar.slots.map((m) => m.id);
        metricsMenu.innerHTML = '<p class="menu-note">' + esc(t('dash_metrics_pick_hint', 'Pin up to four. The rest follow your plugins.')) + '</p>' +
            bar.available.map((m) => {
                const on = pinned.includes(m.id);
                return '<label class="menu-item" role="menuitemcheckbox" aria-checked="' + (on ? 'true' : 'false') + '">' +
                    '<input type="checkbox" class="check" value="' + esc(m.id) + '"' + (on ? ' checked' : '') + (!on && pinned.length >= 4 ? ' disabled' : '') + '>' +
                    icon(m.icon || 'dashboard') + esc(t('dash_metric_' + m.id, m.label)) +
                    (shown.includes(m.id) && !on ? '<span class="count">' + (shown.indexOf(m.id) + 1) + '</span>' : '') + '</label>';
            }).join('') +
            '<div class="menu-rule" role="separator"></div>' +
            '<button type="button" class="menu-item" data-metrics-reset>' + icon('refresh') + esc(t('dash_metrics_automatic', 'Automatic order')) + '</button>';
    }

    function saveMetricPins(pins) {
        return fetch(api('/api/dashboard.php?action=save_metrics'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ pinned: pins, _csrf: csrf() }),
        }).then((r) => r.json()).then((payload) => {
            if (payload?.data?.slots) {
                bar = payload.data;
                renderMetrics();
            }
        });
    }

    function setMenuOpen(open) {
        if (!metricsMenu || !customizeBtn) return;
        metricsMenu.hidden = !open;
        customizeBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    customizeBtn?.addEventListener('click', (event) => {
        event.stopPropagation();
        setMenuOpen(metricsMenu.hidden);
    });
    metricsMenu?.addEventListener('click', (event) => event.stopPropagation());
    metricsMenu?.addEventListener('change', (event) => {
        const box = event.target.closest('input[type="checkbox"]');
        if (!box) return;
        const pins = (bar.pinned || []).filter((id) => id !== box.value);
        if (box.checked) pins.push(box.value);
        saveMetricPins(pins);
    });
    metricsMenu?.addEventListener('click', (event) => {
        if (event.target.closest('[data-metrics-reset]')) saveMetricPins([]);
    });
    document.addEventListener('click', () => setMenuOpen(false));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') setMenuOpen(false);
    });

    /* ---------- Tabs ---------- */
    function bindTabs(list, onSelect) {
        if (!list) return;
        const tabs = Array.from(list.querySelectorAll('[role="tab"]'));
        const select = (tab) => {
            tabs.forEach((other) => {
                const on = other === tab;
                other.setAttribute('aria-selected', on ? 'true' : 'false');
                other.tabIndex = on ? 0 : -1;
                const pane = other.getAttribute('aria-controls');
                if (pane) document.getElementById(pane).hidden = !on;
            });
            onSelect?.(tab);
        };
        list.addEventListener('click', (event) => {
            const tab = event.target.closest('[role="tab"]');
            if (tab) select(tab);
        });
        list.addEventListener('keydown', (event) => {
            const i = tabs.indexOf(document.activeElement);
            if (i < 0 || !['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
            const next = tabs[(i + (event.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length];
            next.focus();
            select(next);
        });
    }

    /* ---------- Inbox ---------- */
    function row(options) {
        const tag = options.href && !options.action ? 'a' : 'div';
        const href = tag === 'a' ? ' href="' + esc(options.href) + '"' : '';
        const action = options.action
            ? '<a class="button is-small" href="' + esc(options.href) + '">' + esc(options.action) + '</a>'
            : (options.aside || '');
        return '<' + tag + ' class="task-row' + (options.attention ? ' is-attention' : '') + '"' + href + '>' +
            (options.icon ? '<span class="row-icon' + (options.tone ? ' is-' + options.tone : '') + '">' + icon(options.icon) + '</span>' : '') +
            '<span class="row-body"><span class="row-title">' + esc(options.title) + '</span>' +
            (options.meta ? '<span class="row-meta">' + options.meta + '</span>' : '') + '</span>' + action + '</' + tag + '>';
    }

    function paneFoot(text, linkLabel, href) {
        return '<div class="list-foot"><span>' + esc(text) + '</span>' +
            (linkLabel ? '<a href="' + esc(href) + '">' + esc(linkLabel) + ' ' + icon('arrow-r') + '</a>' : '') + '</div>';
    }

    function statusChip(status) {
        if (status === 'published') return '<span class="status is-published">' + esc(t('published', 'Published')) + '</span>';
        if (status === 'pending_review') return '<span class="status is-warning">' + esc(t('dash_pending_review_badge', 'Pending review')) + '</span>';
        if (status === 'scheduled') return '<span class="status is-scheduled">' + esc(t('scheduled', 'Scheduled')) + '</span>';
        return '<span class="status">' + esc(t('draft', 'Draft')) + '</span>';
    }

    async function renderAttention(drafts) {
        const pane = document.getElementById('dashboard-pane-attention');
        const isAdmin = document.getElementById('dashboard-status')?.dataset.admin === '1';
        const [notices, pending, security] = await Promise.all([
            isAdmin ? getJson('/api/dashboard.php?action=notifications').catch(() => null) : null,
            getJson('/api/dashboard.php?action=pending_review&limit=50').catch(() => null),
            isAdmin ? getJson('/api/dashboard.php?action=activity&limit=50&filter=users').catch(() => null) : null,
        ]);
        const rows = [];
        (notices?.notifications || []).forEach((n) => {
            const warn = n.type === 'warning' || n.type === 'error' || n.type === 'danger';
            rows.push(row({
                title: n.title || n.message,
                meta: n.title ? esc(n.message) : '',
                icon: n.type === 'ai' || /ai/i.test(n.title || '') ? 'sparkle' : warn ? 'lock' : 'bell',
                tone: warn ? 'warning' : 'info',
                attention: warn,
                action: n.action_label || t('dash_action_review', 'Review'),
                href: n.href || adminUrl('settings', { tab: /ai/i.test(n.title || n.type || '') ? 'ai' : 'system' }),
            }));
        });
        const pendingCount = Number(pending?.total || 0);
        if (pendingCount > 0) {
            rows.push(row({
                title: pendingCount === 1
                    ? t('dash_review_waiting_one', '1 entry waiting for review')
                    : t('dash_review_waiting', '{count} entries waiting for review', { count: number(pendingCount) }),
                meta: esc(t('dash_review_waiting_help', 'Submitted by editors and ready for a decision.')),
                icon: 'inbox', tone: 'info',
                action: t('dash_action_review', 'Review'),
                href: adminUrl('content'),
            }));
        }
        if (drafts > 0) {
            rows.push(row({
                title: drafts === 1
                    ? t('dash_drafts_to_finish_one', '1 draft to finish')
                    : t('dash_drafts_to_finish', '{count} drafts to finish', { count: number(drafts) }),
                meta: esc(t('dash_drafts_to_finish_help', 'Unpublished entries across your collections.')),
                icon: 'pencil',
                action: t('dash_action_open', 'Open'),
                href: adminUrl('content'),
            }));
        }
        const week = Date.now() - 7 * 86400000;
        const accountEvents = (security?.activity || []).filter((a) => new Date(a.timestamp).getTime() >= week).length;
        if (accountEvents > 0) {
            rows.push(row({
                title: accountEvents === 1
                    ? t('dash_account_event_one', '1 account event')
                    : t('dash_account_events', '{count} account events', { count: number(accountEvents) }),
                meta: esc(t('dash_account_events_help', 'Recent sign-ins and account changes.')),
                icon: 'shield',
                action: t('dash_action_review', 'Review'),
                href: adminUrl('audit-log'),
            }));
        }
        const count = document.getElementById('dashboard-attention-count');
        if (count) {
            count.textContent = String(rows.length);
            count.hidden = rows.length === 0;
        }
        pane.innerHTML = rows.length
            ? '<div class="dashboard-rows">' + rows.join('') + '</div>' + paneFoot(t('dash_nothing_waiting', 'Nothing else is waiting for you'), t('dash_all_activity', 'All activity'), adminUrl('activity'))
            : '<div class="empty"><span class="empty-icon">' + icon('check-circle') + '</span><p class="empty-title">' + esc(t('dash_all_clear', 'You are all caught up.')) + '</p></div>';
    }

    function renderActivity() {
        const pane = document.getElementById('dashboard-pane-activity');
        getJson('/api/dashboard.php?action=activity&limit=12&filter=all').then((data) => {
            const items = (data?.activity || []).filter((a) => !['update_check', 'system_update_check'].includes(a.event)).slice(0, 8);
            pane.innerHTML = items.length
                ? '<div class="dashboard-rows">' + items.map((a) => row({
                    title: a.message || a.event,
                    meta: esc([String(a.user || '').toLowerCase() === 'unknown' ? '' : a.user, ago(a.timestamp)].filter(Boolean).join(' · ')),
                    icon: a.category === 'users' ? 'user' : a.category === 'system' ? 'sliders' : 'content',
                    href: window.BirklyActivityRows && a.href ? window.BirklyActivityRows.resolveHref(a) : adminUrl('activity'),
                })).join('') + '</div>' + paneFoot('', t('dash_all_activity', 'All activity'), adminUrl('activity'))
                : '<div class="empty"><p class="empty-title">' + esc(t('dash_no_recent_activity', 'No recent activity.')) + '</p></div>';
        }).catch(() => { pane.innerHTML = ''; });
    }

    function renderRecentEntries() {
        const pane = document.getElementById('dashboard-pane-entries');
        getJson('/api/content.php?action=list_collections').then((data) => {
            const collections = Array.isArray(data?.collections) ? data.collections : (Array.isArray(data) ? data : []);
            return Promise.all(collections.slice(0, 8).map((c) => getJson('/api/admin_entries.php?action=list&collection=' + encodeURIComponent(c.slug) + '&per_page=6&sort=updated_at&order=desc')
                .then((p) => (p?.entries || (Array.isArray(p) ? p : [])).map((e) => ({ ...e, collection: c.slug, collectionName: c.name || c.slug })))
                .catch(() => [])));
        }).then((groups) => {
            const stamp = (e) => new Date(e.modified_at || e.updated_at || e.created_at || 0).getTime();
            const entries = groups.flat().sort((a, b) => stamp(b) - stamp(a)).slice(0, 8);
            pane.innerHTML = entries.length
                ? '<div class="dashboard-rows">' + entries.map((e) => row({
                    title: e.title || e.name || t('dash_untitled', 'Untitled'),
                    meta: esc([e.collectionName, ago(e.modified_at || e.updated_at || e.created_at)].filter(Boolean).join(' · ')),
                    href: adminUrl('entry-editor', { collection: e.collection, entry: e.slug || e.id || '' }),
                    aside: statusChip(e.status),
                })).join('') + '</div>'
                : '<div class="empty"><p class="empty-title">' + esc(t('dash_no_recent_entries', 'No recent entries.')) + '</p></div>';
        }).catch(() => { pane.innerHTML = ''; });
    }

    /* ---------- Traffic ---------- */
    const TRAFFIC = {
        pages: { rows: 'entrance_pages', label: 'path', value: 'entrances', mono: true },
        sources: { rows: 'traffic_sources', label: 'source', value: 'visits' },
        countries: { rows: 'countries', label: 'country', value: 'visits' },
        devices: { rows: 'devices', label: 'device_type', value: 'visits' },
    };

    function trafficLabel(kind, raw) {
        const value = String(raw || '').trim();
        if (!value || value.toLowerCase() === 'unknown') return t('dash_not_detected', 'Not detected');
        if (kind === 'sources') {
            if (value === 'direct') return t('dash_traffic_direct', 'Direct');
            return value.replace(/^(referral|campaign):/, '');
        }
        if (kind === 'countries') {
            if (value === 'LOCAL') return t('dash_local_network', 'Local network');
            try { return new Intl.DisplayNames([locale], { type: 'region' }).of(value) || value; } catch (e) { return value; }
        }
        if (kind === 'devices') return value.charAt(0).toUpperCase() + value.slice(1);
        return value;
    }

    function renderTraffic(kind) {
        const body = document.getElementById('dashboard-traffic-body');
        const foot = document.getElementById('dashboard-traffic-foot');
        const settings = adminUrl('settings', { tab: 'analytics' });
        if (!analytics || analytics.analytics_collecting === false || analytics.analytics_preset === 'off') {
            body.innerHTML = '<div class="advice dashed dashboard-advice"><p class="empty-title">' + esc(t('dash_analytics_off', 'Analytics is off')) + '</p>' +
                '<p class="empty-text">' + esc(t('dash_analytics_off_help', 'Turn on Minimal, Standard, or Advanced under Settings → Analytics.')) + '</p>' +
                '<a class="button is-small" href="' + esc(settings) + '">' + esc(t('dash_open_analytics', 'Open analytics')) + '</a></div>';
            foot.innerHTML = '';
            return;
        }
        const spec = TRAFFIC[kind] || TRAFFIC.pages;
        const rows = (analytics.insights || {})[spec.rows] || [];
        const max = Math.max(1, ...rows.map((r) => Number(r[spec.value] || 0)));
        body.innerHTML = rows.length
            ? '<ul class="ranked">' + rows.slice(0, 6).map((r) => {
                const count = Number(r[spec.value] || 0);
                return '<li><span class="ranked-label' + (spec.mono ? '' : ' is-text') + '">' + esc(trafficLabel(kind, r[spec.label])) + '</span>' +
                    '<span class="ranked-value">' + number(count) + '</span>' +
                    '<div class="meter" style="--value: ' + Math.round((count / max) * 100) + '"><span></span></div></li>';
            }).join('') + '</ul>'
            : '<p class="kit-note">' + esc(t('dash_no_data_yet', 'No data yet')) + '</p>';
        foot.innerHTML = paneFoot(analytics.analytics_consent_required ? t('dash_counted_without_consent', 'Visits are counted without consent') : '', t('dash_domain_reports', 'Domain reports'), adminUrl('settings', { tab: 'site-delivery' }));
    }

    bindTabs(document.getElementById('dashboard-inbox-tabs'));
    bindTabs(document.getElementById('dashboard-traffic-tabs'), (tab) => renderTraffic(tab.dataset.traffic));

    /* ---------- Load ---------- */
    renderGreeting();
    renderStatus();
    const analyticsLoad = getJson('/api/dashboard.php?action=analytics').then((data) => { analytics = data; }).catch(() => { analytics = null; });
    getJson('/api/dashboard.php?action=metrics').then((data) => {
        bar = data || bar;
        renderMetrics();
        const drafts = (bar.available || []).find((m) => m.id === 'drafts');
        renderAttention(Number(drafts?.value || 0));
    }).catch(() => renderAttention(0));
    analyticsLoad.then(() => {
        renderTraffic('pages');
        if (bar.slots.some((m) => m.source === 'analytics')) renderMetrics();
    });
    renderActivity();
    renderRecentEntries();
})();
