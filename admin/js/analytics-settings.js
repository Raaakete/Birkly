/**
 * Settings → Analytics (P61) — presets, consent copy, retention, report panel, CSV.
 */
(function () {
    'use strict';

    var state = {
        presets: [],
        settings: null,
        active: null,
        trackingId: null,
        initialized: false
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
                    '<p class="u-text-sm"><span class="badge badge-success">Upside</span> ' + escapeHtml(p.upside || '') + '</p>' +
                    '<p class="u-text-sm"><span class="badge badge-neutral">Downside</span> ' + escapeHtml(p.downside || '') + '</p>' +
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
            });
        });

        updateExportVisibility();
        updateConsentFieldsVisibility();
    }

    function renderList(title, rows, labelKey, valueKey) {
        rows = Array.isArray(rows) ? rows : [];
        if (!rows.length) {
            return '<div class="analytics-report__section"><h4>' + escapeHtml(title) + '</h4>' +
                '<p class="form-help">No data yet.</p></div>';
        }
        var items = rows.slice(0, 10).map(function (row) {
            return '<li><span class="analytics-report__label">' + escapeHtml(row[labelKey] || '—') + '</span>' +
                '<span class="analytics-report__value">' + escapeHtml(row[valueKey] || 0) + '</span></li>';
        }).join('');
        return '<div class="analytics-report__section"><h4>' + escapeHtml(title) + '</h4>' +
            '<ul class="analytics-report__list">' + items + '</ul></div>';
    }

    function renderTrends(daily) {
        daily = Array.isArray(daily) ? daily.slice() : [];
        if (!daily.length) {
            return '<div class="analytics-report__section"><h4>Trends (30d)</h4>' +
                '<p class="form-help">No daily data yet.</p></div>';
        }
        daily.sort(function (a, b) {
            return String(a.date || '').localeCompare(String(b.date || ''));
        });
        var max = 1;
        daily.forEach(function (row) {
            max = Math.max(max, Number(row.views) || 0);
        });
        var bars = daily.slice(-14).map(function (row) {
            var views = Number(row.views) || 0;
            var pct = Math.max(4, Math.round((views / max) * 100));
            return '<li class="analytics-trend-row">' +
                '<span class="analytics-report__label">' + escapeHtml(row.date || '') + '</span>' +
                '<span class="analytics-trend-bar" style="--trend-pct:' + pct + '%" title="' + escapeHtml(String(views)) + ' views"></span>' +
                '<span class="analytics-report__value">' + escapeHtml(String(views)) + '</span></li>';
        }).join('');
        return '<div class="analytics-report__section analytics-report__section--wide"><h4>Trends (last 14 days)</h4>' +
            '<ul class="analytics-report__list analytics-trend-list">' + bars + '</ul></div>';
    }

    function renderReport(report) {
        return '' +
            '<div class="u-grid-auto-sm">' +
            '<div class="stat"><span class="stat-value">' + escapeHtml(String(report.page_views || 0)) + '</span>' +
            '<span class="stat-label">Page views (30d)</span></div>' +
            '<div class="stat"><span class="stat-value">' + escapeHtml(String(report.unique_visitors || 0)) + '</span>' +
            '<span class="stat-label">Visitors (30d)</span></div>' +
            '</div>' +
            renderTrends(report.daily_stats) +
            '<div class="analytics-report__grid">' +
            renderList('Top pages', report.top_pages, 'path', 'views') +
            renderList('Referrers', report.referrers, 'domain', 'visits') +
            renderList('Devices', report.devices, 'device_type', 'visits') +
            renderList('Campaigns', report.campaigns, 'campaign', 'visits') +
            renderList('Traffic sources', report.traffic_sources, 'source', 'visits') +
            '</div>';
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
                                ids = Object.keys(listPayload.data);
                            }
                        }
                        state.trackingId = ids[0] || null;
                        return state.trackingId;
                    });
            })
            .catch(function () {
                state.trackingId = null;
                return null;
            });
    }

    function loadReport() {
        var panel = document.getElementById('analytics-report-panel');
        if (!panel) return Promise.resolve();

        if (!state.settings || state.settings.preset === 'off') {
            panel.innerHTML = '<div class="empty-state"><p class="empty-state__title">Analytics is Off</p>' +
                '<p class="empty-state__description">Nothing is collected. Choose Minimal or higher to start insights.</p></div>';
            updateExportVisibility();
            return Promise.resolve();
        }

        panel.innerHTML = '<div class="loading-state"><p>Loading report…</p></div>';
        return resolveTrackingId().then(function (trackingId) {
            updateExportVisibility();
            if (!trackingId) {
                panel.innerHTML = '<div class="empty-state"><p class="empty-state__title">No tracking domain yet</p>' +
                    '<p class="empty-state__description">Enable analytics on a domain under Settings → Site delivery.</p></div>';
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
                    panel.innerHTML = renderReport(payload.data || {});
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
        var saveBtn = document.getElementById('analytics-settings-save');
        if (saveBtn) saveBtn.disabled = true;

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
                toast('Analytics settings saved', 'success');
                renderPresets();
                return loadReport();
            })
            .catch(function (err) {
                toast(err.message || 'Save failed', 'error');
            })
            .finally(function () {
                if (saveBtn) saveBtn.disabled = false;
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
            var saveBtn = document.getElementById('analytics-settings-save');
            if (saveBtn) saveBtn.addEventListener('click', save);
            var exportBtn = document.getElementById('analytics-csv-export');
            if (exportBtn) exportBtn.addEventListener('click', exportCsv);
        }
        load();
    }

    window.initAnalyticsSettings = init;
})();
