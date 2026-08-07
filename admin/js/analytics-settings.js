/**
 * Settings → Analytics (P61) — presets + report panel.
 */
(function () {
    'use strict';

    var state = { presets: [], settings: null, active: null };

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

    function renderPresets() {
        var mount = document.getElementById('analytics-preset-cards');
        if (!mount) return;
        var current = (state.settings && state.settings.preset) || 'off';
        mount.innerHTML = state.presets.map(function (p) {
            var selected = p.id === current;
            return '' +
                '<label class="card card--sunken analytics-preset-card' + (selected ? ' is-selected' : '') + '">' +
                '<div class="card-body u-stack-2">' +
                '<div class="u-cluster u-justify-between">' +
                '<strong>' + escapeHtml(p.label) + '</strong>' +
                '<input type="radio" name="analytics-preset" value="' + escapeHtml(p.id) + '"' + (selected ? ' checked' : '') + '>' +
                '</div>' +
                '<p class="form-help">' + escapeHtml(p.summary || '') + '</p>' +
                '<p><span class="badge badge-success">Upside</span> ' + escapeHtml(p.upside || '') + '</p>' +
                '<p><span class="badge badge-neutral">Downside</span> ' + escapeHtml(p.downside || '') + '</p>' +
                (p.consent_required ? '<p class="form-help">Consent banner required for visitors.</p>' : '') +
                '</div></label>';
        }).join('');

        mount.querySelectorAll('input[name="analytics-preset"]').forEach(function (input) {
            input.addEventListener('change', function () {
                mount.querySelectorAll('.analytics-preset-card').forEach(function (card) {
                    card.classList.remove('is-selected');
                });
                var card = input.closest('.analytics-preset-card');
                if (card) card.classList.add('is-selected');
                var exportBtn = document.getElementById('analytics-csv-export');
                if (exportBtn) exportBtn.hidden = input.value === 'off' || input.value === 'minimal';
            });
        });

        var exportBtn = document.getElementById('analytics-csv-export');
        if (exportBtn) exportBtn.hidden = current === 'off' || current === 'minimal';
    }

    function load() {
        return fetch(apiUrl('/api/analytics.php?action=get_settings'), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (payload) {
                if (payload.status !== 'ok') throw new Error(payload.message || 'Failed to load');
                state.presets = (payload.data && payload.data.presets) || [];
                state.settings = (payload.data && payload.data.settings) || {};
                state.active = (payload.data && payload.data.active) || null;
                var consent = document.getElementById('analytics-consent-text');
                var retention = document.getElementById('analytics-retention-days');
                if (consent) consent.value = state.settings.consent_text || '';
                if (retention) retention.value = state.settings.retention_days != null ? state.settings.retention_days : '';
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
        var selected = document.querySelector('input[name="analytics-preset"]:checked');
        var retentionEl = document.getElementById('analytics-retention-days');
        var consentEl = document.getElementById('analytics-consent-text');
        var retention = retentionEl && retentionEl.value !== '' ? parseInt(retentionEl.value, 10) : null;
        return fetch(apiUrl('/api/analytics.php?action=save_settings'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'save_settings',
                preset: selected ? selected.value : 'off',
                retention_days: retention,
                consent_text: consentEl ? consentEl.value : '',
                _csrf: getCsrf()
            })
        }).then(function (r) { return r.json(); })
            .then(function (payload) {
                if (payload.status !== 'ok') throw new Error(payload.message || 'Save failed');
                state.settings = payload.data.settings;
                state.active = payload.data.active;
                if (window.BirklyFeedback) BirklyFeedback.toast('Analytics settings saved', 'success');
                renderPresets();
                return loadReport();
            })
            .catch(function (err) {
                if (window.BirklyFeedback) BirklyFeedback.toast(err.message || 'Save failed', 'error');
            });
    }

    function loadReport() {
        var panel = document.getElementById('analytics-report-panel');
        if (!panel) return Promise.resolve();
        if (!state.settings || state.settings.preset === 'off') {
            panel.innerHTML = '<div class="empty-state"><p>Analytics is Off — nothing is collected.</p></div>';
            return Promise.resolve();
        }
        panel.innerHTML = '<div class="loading-state"><p>Loading report…</p></div>';
        // Prefer first tracking id from domains list endpoint used elsewhere
        return fetch(apiUrl('/api/analytics.php?action=list'), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (listPayload) {
                var ids = [];
                if (listPayload.status === 'ok' && listPayload.data) {
                    if (Array.isArray(listPayload.data)) ids = listPayload.data.map(function (x) { return x.id || x.domain; });
                    else if (typeof listPayload.data === 'object') ids = Object.keys(listPayload.data);
                }
                var trackingId = ids[0];
                if (!trackingId) {
                    panel.innerHTML = '<div class="empty-state"><p>No analytics-enabled domains yet. Enable tracking under Site delivery → Domains.</p></div>';
                    return;
                }
                var end = new Date();
                var start = new Date(end.getTime() - 30 * 86400000);
                var qs = 'action=data&tracking_id=' + encodeURIComponent(trackingId) +
                    '&start=' + start.toISOString().slice(0, 10) +
                    '&end=' + end.toISOString().slice(0, 10);
                return fetch(apiUrl('/api/analytics.php?' + qs), { credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (payload) {
                        if (payload.status !== 'ok') throw new Error(payload.message || 'Report failed');
                        var report = payload.data || payload.report || {};
                        panel.innerHTML = renderReport(report);
                    });
            })
            .catch(function (err) {
                panel.innerHTML = '<div class="message message-error" role="alert">' + escapeHtml(err.message) + '</div>';
            });
    }

    function renderList(title, rows, labelKey, valueKey) {
        rows = Array.isArray(rows) ? rows : [];
        if (!rows.length) {
            return '<div class="analytics-report__section"><h4>' + escapeHtml(title) + '</h4><p class="form-help">No data yet.</p></div>';
        }
        var items = rows.slice(0, 8).map(function (row) {
            return '<li><span class="analytics-report__label">' + escapeHtml(row[labelKey] || '—') + '</span>' +
                '<span class="analytics-report__value">' + escapeHtml(row[valueKey] || 0) + '</span></li>';
        }).join('');
        return '<div class="analytics-report__section"><h4>' + escapeHtml(title) + '</h4><ul class="analytics-report__list">' + items + '</ul></div>';
    }

    function renderReport(report) {
        var totals = report.totals || report.summary || {};
        return '' +
            '<div class="kv">' +
            '<span class="kv-key">Page views</span><span class="kv-value">' + escapeHtml(totals.page_views || totals.views || 0) + '</span>' +
            '<span class="kv-key">Sessions</span><span class="kv-value">' + escapeHtml(totals.sessions || 0) + '</span>' +
            '</div>' +
            '<div class="analytics-report__grid">' +
            renderList('Top pages', report.top_pages, 'path', 'views') +
            renderList('Referrers', report.referrers, 'domain', 'visits') +
            renderList('Devices', report.devices, 'device_type', 'visits') +
            renderList('Campaigns / sources', report.traffic_sources || report.campaigns, 'source', 'visits') +
            '</div>';
    }

    function init() {
        if (!document.getElementById('analytics-settings-root')) return;
        var saveBtn = document.getElementById('analytics-settings-save');
        if (saveBtn) saveBtn.addEventListener('click', save);
        load();
    }

    window.initAnalyticsSettings = init;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
