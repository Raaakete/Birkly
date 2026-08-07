/**
 * Settings → Analytics (P61) — presets, consent, retention, report panel, CSV.
 * Saves via the global Settings CTA (no in-pane Save button).
 */
(function () {
    'use strict';

    var state = {
        presets: [],
        settings: null,
        active: null,
        trackingId: null,
        initialized: false,
        lastReport: null
    };

    function apiUrl(path) {
        return (window.Birkly && typeof window.Birkly.url === 'function') ? window.Birkly.url(path) : path;
    }

    function getCsrf() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function escapeHtml(text) {
        var d = document.createElement('div');
        d.textContent = text == null ? '' : String(text);
        return d.innerHTML;
    }

    function toast(message, type) {
        if (window.BirklyFeedback && typeof BirklyFeedback.toast === 'function') {
            BirklyFeedback.toast(message, type || 'info');
            return;
        }
        if (typeof window.showMessage === 'function') {
            window.showMessage(message, type || 'info');
        }
    }

    function selectedPreset() {
        var input = document.querySelector('input[name="analytics-preset"]:checked');
        return input ? input.value : ((state.settings && state.settings.preset) || 'off');
    }

    function isAdvanced() {
        return selectedPreset() === 'advanced' || (state.active && state.active.id === 'advanced');
    }

    function markDirty() {
        if (typeof window.checkSettingsDirtyState === 'function') {
            window.checkSettingsDirtyState('analytics');
        }
    }

    function updateExportVisibility() {
        var exportBtn = document.getElementById('analytics-csv-export');
        if (!exportBtn) return;
        var preset = selectedPreset();
        exportBtn.hidden = preset === 'off' || !state.trackingId;
    }

    function updateConsentFieldsVisibility() {
        var wrap = document.getElementById('analytics-consent-fields');
        if (!wrap) return;
        var preset = selectedPreset();
        var active = state.presets.find(function (p) { return p.id === preset; });
        var needsConsent = !!(active && active.consent_required) || preset === 'standard' || preset === 'advanced';
        wrap.hidden = !needsConsent;
    }

    function renderPresets() {
        var mount = document.getElementById('analytics-preset-cards');
        if (!mount) return;
        var current = (state.settings && state.settings.preset) || 'off';
        if (!state.presets.length) {
            mount.innerHTML = '<div class="empty-state"><p>No presets available.</p></div>';
            return;
        }

        mount.innerHTML = '<div class="u-grid-auto-lg" role="radiogroup" aria-label="Analytics preset">' +
            state.presets.map(function (p) {
                var selected = p.id === current;
                return '' +
                    '<label class="card card--interactive analytics-preset-card' + (selected ? ' is-selected' : '') + '">' +
                    '<div class="card-body u-stack-2">' +
                    '<div class="u-cluster u-justify-between">' +
                    '<strong>' + escapeHtml(p.label) + '</strong>' +
                    '<input type="radio" name="analytics-preset" value="' + escapeHtml(p.id) + '"' +
                    (selected ? ' checked' : '') + ' aria-label="' + escapeHtml(p.label) + ' preset">' +
                    '</div>' +
                    '<p class="form-help">' + escapeHtml(p.summary || '') + '</p>' +
                    '<p class="u-text-sm"><span class="u-fg-secondary">Upside</span> — ' + escapeHtml(p.upside || '') + '</p>' +
                    '<p class="u-text-sm"><span class="u-fg-secondary">Downside</span> — ' + escapeHtml(p.downside || '') + '</p>' +
                    (p.consent_required
                        ? '<p class="form-help">Visitor consent banner is required for this preset.</p>'
                        : '') +
                    '<p class="form-help">Default retention: ' + escapeHtml(String(p.retention_days || 0)) + ' days</p>' +
                    '</div></label>';
            }).join('') +
            '</div>';

        mount.querySelectorAll('input[name="analytics-preset"]').forEach(function (input) {
            input.addEventListener('change', function () {
                mount.querySelectorAll('.analytics-preset-card').forEach(function (card) {
                    card.classList.remove('is-selected');
                });
                var card = input.closest('.analytics-preset-card');
                if (card) card.classList.add('is-selected');
                updateExportVisibility();
                updateConsentFieldsVisibility();
                markDirty();
                if (state.lastReport) {
                    var panel = document.getElementById('analytics-report-panel');
                    if (panel) panel.innerHTML = renderReport(state.lastReport);
                }
            });
        });

        updateExportVisibility();
        updateConsentFieldsVisibility();
    }

    function kpi(value, label, hint) {
        return '<div class="analytics-kpi">' +
            '<span class="analytics-kpi__value u-numeric">' + escapeHtml(String(value)) + '</span>' +
            '<span class="analytics-kpi__label">' + escapeHtml(label) + '</span>' +
            (hint ? '<span class="analytics-kpi__hint">' + escapeHtml(hint) + '</span>' : '') +
            '</div>';
    }

    function sectionHeading(title, subtitle) {
        return '<div class="analytics-section-head">' +
            '<h3 class="analytics-section-head__title">' + escapeHtml(title) + '</h3>' +
            (subtitle ? '<p class="analytics-section-head__sub">' + escapeHtml(subtitle) + '</p>' : '') +
            '</div>';
    }

    function renderBarList(title, rows, labelKey, valueKey) {
        rows = Array.isArray(rows) ? rows : [];
        if (!rows.length) {
            return '<section class="analytics-panel">' +
                '<h4 class="analytics-panel__title">' + escapeHtml(title) + '</h4>' +
                '<p class="form-help u-m-0">No data yet.</p></section>';
        }
        var max = 1;
        rows.forEach(function (row) {
            max = Math.max(max, Number(row[valueKey]) || 0);
        });
        var items = rows.slice(0, isAdvanced() ? 12 : 8).map(function (row) {
            var count = Number(row[valueKey]) || 0;
            var pct = Math.max(4, Math.round((count / max) * 100));
            var label = String(row[labelKey] || '—');
            return '<li class="analytics-bar-row">' +
                '<div class="analytics-bar-row__meta">' +
                '<span class="analytics-bar-row__label" title="' + escapeHtml(label) + '">' + escapeHtml(label) + '</span>' +
                '<span class="analytics-bar-row__value u-numeric">' + escapeHtml(String(count)) + '</span>' +
                '</div>' +
                '<div class="analytics-bar-row__track" aria-hidden="true">' +
                '<span class="analytics-bar-row__fill" style="width:' + pct + '%"></span>' +
                '</div></li>';
        }).join('');
        return '<section class="analytics-panel">' +
            '<h4 class="analytics-panel__title">' + escapeHtml(title) + '</h4>' +
            '<ul class="analytics-bar-list">' + items + '</ul></section>';
    }

    function renderTrends(daily) {
        daily = Array.isArray(daily) ? daily.slice() : [];
        if (!daily.length) {
            return '<section class="analytics-panel analytics-panel--wide analytics-panel--chart">' +
                '<h4 class="analytics-panel__title">Daily trend</h4>' +
                '<p class="form-help u-m-0">No daily data yet.</p></section>';
        }
        daily.sort(function (a, b) {
            return String(a.date || '').localeCompare(String(b.date || ''));
        });
        var windowDays = isAdvanced() ? 30 : 14;
        var slice = daily.slice(-windowDays);
        var max = 1;
        slice.forEach(function (row) {
            max = Math.max(max, Number(row.views) || 0, Number(row.visitors) || 0);
        });
        var bars = slice.map(function (row) {
            var views = Number(row.views) || 0;
            var visitors = Number(row.visitors) || 0;
            var viewsPct = Math.max(6, Math.round((views / max) * 100));
            var visitorsPct = Math.max(4, Math.round((visitors / max) * 100));
            return '<li class="analytics-trend-col" title="' + escapeHtml(String(row.date || '')) +
                ': ' + views + ' views, ' + visitors + ' visitors">' +
                '<span class="analytics-trend-col__stack" aria-hidden="true">' +
                '<span class="analytics-trend-col__bar analytics-trend-col__bar--views" style="height:' + viewsPct + '%"></span>' +
                (isAdvanced()
                    ? '<span class="analytics-trend-col__bar analytics-trend-col__bar--visitors" style="height:' + visitorsPct + '%"></span>'
                    : '') +
                '</span>' +
                '<span class="analytics-trend-col__label">' + escapeHtml(String(row.date || '').slice(5)) + '</span>' +
                '</li>';
        }).join('');
        var legend = isAdvanced()
            ? '<div class="analytics-trend-legend" aria-hidden="true">' +
                '<span><i class="analytics-swatch analytics-swatch--views"></i> Views</span>' +
                '<span><i class="analytics-swatch analytics-swatch--visitors"></i> Visitors</span>' +
                '</div>'
            : '';
        return '<section class="analytics-panel analytics-panel--wide analytics-panel--chart">' +
            '<div class="u-cluster u-justify-between u-gap-3">' +
            '<h4 class="analytics-panel__title">Daily trend (last ' + windowDays + ' days)</h4>' +
            legend +
            '</div>' +
            '<ul class="analytics-trend-cols">' + bars + '</ul></section>';
    }

    function renderReport(report) {
        report = report || {};
        var preset = selectedPreset();
        var help = document.getElementById('analytics-report-help');
        if (help) {
            help.textContent = isAdvanced()
                ? 'Advanced report — sessions, bounce, funnels, forms, outbound clicks, OS, and plugin events.'
                : 'Site-wide snapshot from enabled tracking. Depth follows your preset.';
        }

        var html = '<div class="analytics-report' + (isAdvanced() ? ' analytics-report--advanced' : '') + '">';

        html += sectionHeading('Overview', 'Last 30 days') +
            '<div class="analytics-kpi-grid">' +
            kpi(report.page_views || 0, 'Page views', 'All page loads') +
            kpi(report.unique_visitors || 0, 'Visitors', 'Unique visitors');

        if (preset !== 'minimal' && preset !== 'off') {
            html += kpi(report.sessions || 0, 'Sessions', 'With session id') +
                kpi(report.pages_per_visitor || 0, 'Pages / visitor', 'Engagement');
        }
        if (isAdvanced()) {
            html += kpi((report.bounce_rate || 0) + '%', 'Bounce rate', 'Single-page sessions') +
                kpi((report.event_counts && report.event_counts.form_submit) || 0, 'Form submits', 'Advanced events') +
                kpi((report.event_counts && report.event_counts.outbound_click) || 0, 'Outbound clicks', 'Advanced events') +
                kpi((report.event_counts && report.event_counts.plugin_event) || 0, 'Plugin events', 'Advanced events');
        }

        html += '</div>' + renderTrends(report.daily_stats);

        html += sectionHeading('Content & acquisition', 'Where traffic lands and comes from') +
            '<div class="analytics-panel-grid">' +
            renderBarList('Top pages', report.top_pages, 'path', 'views') +
            renderBarList('Traffic sources', report.traffic_sources, 'source', 'visits') +
            renderBarList('Referrers', report.referrers, 'domain', 'visits') +
            renderBarList('Devices', report.devices, 'device_type', 'visits');

        if (preset === 'standard' || isAdvanced()) {
            html += renderBarList('Entrance pages', report.entrance_pages, 'path', 'entrances') +
                renderBarList('Exit pages', report.exit_pages, 'path', 'exits') +
                renderBarList('Campaigns', report.campaigns, 'campaign', 'visits') +
                renderBarList('Browsers', report.browsers, 'browser', 'visits') +
                renderBarList('Locations', report.countries, 'country', 'visits');
        }
        html += '</div>';

        if (isAdvanced()) {
            html += sectionHeading('Audience & events', 'Advanced-only detail') +
                '<div class="analytics-panel-grid">' +
                renderBarList('Operating systems', report.operating_systems, 'os', 'visits') +
                renderBarList('Outbound links', report.outbound_targets, 'url', 'clicks') +
                renderBarList('Forms', report.forms, 'form', 'submits') +
                renderBarList('Plugin events', report.plugin_events, 'event', 'count') +
                '</div>';
        }

        html += '</div>';
        return html;
    }

    function resolveTrackingId() {
        return fetch(apiUrl('/api/domain_management.php?action=list_licenses'), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (payload) {
                var domains = [];
                if (payload && payload.status === 'ok' && payload.data) {
                    if (Array.isArray(payload.data.licenses)) {
                        domains = payload.data.licenses;
                    } else if (Array.isArray(payload.data)) {
                        domains = payload.data;
                    }
                } else if (payload && Array.isArray(payload.licenses)) {
                    domains = payload.licenses;
                }
                var enabled = domains.filter(function (d) { return !!d.analytics_enabled; });
                if (enabled.length) {
                    state.trackingId = enabled[0].domain || enabled[0].id || null;
                    return state.trackingId;
                }
                return fetch(apiUrl('/api/analytics.php?action=list'), { credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (listPayload) {
                        var ids = [];
                        if (listPayload.status === 'ok' && listPayload.data) {
                            if (Array.isArray(listPayload.data)) {
                                ids = listPayload.data.map(function (x) { return x.id || x.domain; });
                            } else if (typeof listPayload.data === 'object') {
                                ids = Object.keys(listPayload.data).filter(function (id) {
                                    var row = listPayload.data[id];
                                    return !row || row.enabled !== false;
                                });
                            }
                        }
                        // Prefer non-localhost when both exist.
                        var preferred = ids.find(function (id) { return id && id !== 'localhost'; });
                        state.trackingId = preferred || ids[0] || 'localhost';
                        return state.trackingId;
                    });
            })
            .catch(function () {
                state.trackingId = 'localhost';
                return state.trackingId;
            });
    }

    function loadReport() {
        var panel = document.getElementById('analytics-report-panel');
        if (!panel) return Promise.resolve();

        if (!state.settings || state.settings.preset === 'off') {
            state.lastReport = null;
            panel.innerHTML = '<div class="empty-state"><p class="empty-state__title">Analytics is Off</p>' +
                '<p class="empty-state__description">Nothing is collected. Choose Minimal or higher, then Save Settings.</p></div>';
            updateExportVisibility();
            return Promise.resolve();
        }

        panel.innerHTML = '<div class="loading-state"><p>Loading report…</p></div>';
        return resolveTrackingId().then(function (trackingId) {
            updateExportVisibility();
            if (!trackingId) {
                panel.innerHTML = '<div class="empty-state"><p class="empty-state__title">No tracking domain yet</p>' +
                    '<p class="empty-state__description">Save Settings with Minimal+ to enable localhost/site tracking.</p></div>';
                return;
            }
            var end = new Date();
            var start = new Date(end.getTime() - 30 * 86400000);
            var qs = 'action=data&tracking_id=' + encodeURIComponent(trackingId) +
                '&start_date=' + encodeURIComponent(start.toISOString().slice(0, 10)) +
                '&end_date=' + encodeURIComponent(end.toISOString().slice(0, 10));
            return fetch(apiUrl('/api/analytics.php?' + qs), { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (payload) {
                    if (payload.status !== 'ok') {
                        throw new Error(payload.message || 'Report failed');
                    }
                    state.lastReport = payload.data || {};
                    panel.innerHTML = renderReport(state.lastReport);
                });
        }).catch(function (err) {
            panel.innerHTML = '<div class="message message-error" role="alert">' + escapeHtml(err.message) + '</div>';
        });
    }

    function load() {
        return fetch(apiUrl('/api/analytics.php?action=get_settings'), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (payload) {
                if (payload.status !== 'ok') {
                    throw new Error(payload.message || 'Failed to load analytics settings');
                }
                state.presets = (payload.data && payload.data.presets) || [];
                state.settings = (payload.data && payload.data.settings) || {};
                state.active = (payload.data && payload.data.active) || null;

                var consent = document.getElementById('analytics-consent-text');
                var retention = document.getElementById('analytics-retention-days');
                if (consent) consent.value = state.settings.consent_text || '';
                if (retention) {
                    retention.value = state.settings.retention_days != null ? state.settings.retention_days : '';
                }
                renderPresets();
                if (typeof window.markSettingsClean === 'function') {
                    window.markSettingsClean('analytics');
                } else if (typeof window.checkSettingsDirtyState === 'function') {
                    // Snapshot after first paint of radios
                    setTimeout(function () {
                        if (typeof window.captureSettingsSnapshot === 'function') {
                            window.captureSettingsSnapshot('analytics');
                        }
                    }, 0);
                }
                return loadReport();
            })
            .catch(function (err) {
                var mount = document.getElementById('analytics-preset-cards');
                if (mount) {
                    mount.innerHTML = '<div class="message message-error" role="alert">' + escapeHtml(err.message) + '</div>';
                }
            });
    }

    function save() {
        var retentionEl = document.getElementById('analytics-retention-days');
        var consentEl = document.getElementById('analytics-consent-text');
        var retention = null;
        if (retentionEl && retentionEl.value !== '') {
            retention = parseInt(retentionEl.value, 10);
            if (isNaN(retention)) retention = null;
        }

        return fetch(apiUrl('/api/analytics.php?action=save_settings'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                preset: selectedPreset(),
                retention_days: retention,
                consent_text: consentEl ? consentEl.value : '',
                _csrf: getCsrf()
            })
        }).then(function (r) { return r.json(); })
            .then(function (payload) {
                if (payload.status !== 'ok') {
                    throw new Error(payload.message || 'Save failed');
                }
                state.settings = payload.data.settings;
                state.active = payload.data.active;
                renderPresets();
                if (typeof window.markSettingsClean === 'function') {
                    window.markSettingsClean('analytics');
                }
                return loadReport();
            });
    }

    function exportCsv() {
        if (!state.trackingId || selectedPreset() === 'off') {
            toast('Enable analytics and a domain first', 'error');
            return;
        }
        var end = new Date();
        var start = new Date(end.getTime() - 30 * 86400000);
        var qs = 'action=export_csv&tracking_id=' + encodeURIComponent(state.trackingId) +
            '&start_date=' + encodeURIComponent(start.toISOString().slice(0, 10)) +
            '&end_date=' + encodeURIComponent(end.toISOString().slice(0, 10));
        window.location.href = apiUrl('/api/analytics.php?' + qs);
    }

    function init() {
        if (!document.getElementById('analytics-settings-root')) return;
        if (!state.initialized) {
            state.initialized = true;
            var exportBtn = document.getElementById('analytics-csv-export');
            if (exportBtn) exportBtn.addEventListener('click', exportCsv);
            var form = document.getElementById('analytics-settings-form');
            if (form) {
                form.addEventListener('submit', function (e) { e.preventDefault(); });
                form.addEventListener('input', markDirty);
                form.addEventListener('change', markDirty);
            }
        }
        load();
    }

    window.initAnalyticsSettings = init;
    window.saveAnalyticsSettings = save;
})();
