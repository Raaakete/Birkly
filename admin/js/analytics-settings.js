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
        trackingId: 'all',
        trackingDomains: [],
        health: null,
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

    function t(key, fallback, params) {
        var out = (window.Birkly && window.Birkly.i18n)
            ? window.Birkly.i18n.t('admin.' + key, fallback || key)
            : (fallback || key);
        if (params && typeof params === 'object') {
            Object.keys(params).forEach(function (name) {
                out = out.split('{' + name + '}').join(String(params[name]));
            });
        }
        return out;
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
        if (typeof window.getSettingsCurrentTab === 'function' && window.getSettingsCurrentTab() !== 'analytics') {
            return;
        }
        if (typeof window.checkSettingsDirtyState === 'function') {
            window.checkSettingsDirtyState('analytics');
        }
    }

    function selectedScope() {
        var select = document.getElementById('analytics-domain-scope');
        return select ? (select.value || 'all') : (state.trackingId || 'all');
    }

    function updateExportVisibility() {
        var exportBtn = document.getElementById('analytics-csv-export');
        if (!exportBtn) return;
        var preset = selectedPreset();
        var scope = selectedScope();
        exportBtn.hidden = preset === 'off' || !scope || scope === 'all';
    }

    function updateConsentFieldsVisibility() {
        var wrap = document.getElementById('analytics-consent-fields');
        if (!wrap) return;
        var preset = selectedPreset();
        var active = state.presets.find(function (p) { return p.id === preset; });
        var needsConsent = !!(active && active.consent_required) || preset === 'standard' || preset === 'advanced';
        wrap.hidden = !needsConsent;
    }

    function renderDomainScopeOptions() {
        var select = document.getElementById('analytics-domain-scope');
        if (!select) return;

        var current = selectedScope();
        var options = ['<option value="all">' + escapeHtml(t('analytics_scope_all', 'All enabled domains')) + '</option>'];
        state.trackingDomains.forEach(function (domain) {
            options.push('<option value="' + escapeHtml(domain) + '">' + escapeHtml(domain) + '</option>');
        });
        select.innerHTML = options.join('');
        if (current === 'all' || state.trackingDomains.indexOf(current) !== -1) {
            select.value = current;
        } else {
            select.value = 'all';
        }
        state.trackingId = select.value;
        updateExportVisibility();
    }

    function renderHealthBanner() {
        var banner = document.getElementById('analytics-health-banner');
        if (!banner) return;

        var health = state.health;
        if (!health || !health.collecting) {
            banner.hidden = true;
            banner.innerHTML = '';
            return;
        }

        var messages = [];
        if (health.consent_required) {
            messages.push(t('analytics_health_consent', 'Without consent, only anonymous page views are counted (path + daily visitor estimate). Detailed metrics require accepting the banner.'));
        }
        if (Array.isArray(health.capped_domains) && health.capped_domains.length) {
            messages.push(t('analytics_health_capped', 'Daily event cap reached for {count} domain/day pair(s). Older events may have been trimmed.', { count: health.capped_domains.length }));
        }
        if (!health.last_event_at) {
            messages.push(t('analytics_health_no_events', 'No events recorded yet. Visit your public site to start collecting.'));
        } else {
            messages.push(t('analytics_health_last_event', 'Last event: {time} · {count} enabled domain(s).', { time: health.last_event_at, count: health.enabled_domain_count || 0 }));
        }

        if (!messages.length) {
            banner.hidden = true;
            banner.innerHTML = '';
            return;
        }

        banner.hidden = false;
        banner.className = 'card-body';
        banner.innerHTML = '<div class="message message-info" role="status"><p class="u-m-0">' +
            messages.map(function (msg) { return escapeHtml(msg); }).join(' ') + '</p></div>';
    }

    function renderPresets() {
        var mount = document.getElementById('analytics-preset-cards');
        if (!mount) return;
        var current = (state.settings && state.settings.preset) || 'off';
        if (!state.presets.length) {
            mount.innerHTML = '<div class="empty-state"><p>' + escapeHtml(t('analytics_presets_empty', 'No presets available.')) + '</p></div>';
            return;
        }

        mount.innerHTML = '<div class="u-grid-auto-lg" role="radiogroup" aria-label="' + escapeHtml(t('analytics_preset_radiogroup', 'Analytics preset')) + '">' +
            state.presets.map(function (p) {
                var selected = p.id === current;
                return '' +
                    '<label class="card card--interactive analytics-preset-card' + (selected ? ' is-selected' : '') + '">' +
                    '<div class="card-body u-stack-2">' +
                    '<div class="u-cluster u-justify-between">' +
                    '<strong>' + escapeHtml(p.label) + '</strong>' +
                    '<input type="radio" name="analytics-preset" value="' + escapeHtml(p.id) + '"' +
                    (selected ? ' checked' : '') + ' aria-label="' + escapeHtml(p.label) + escapeHtml(t('analytics_preset_aria_suffix', ' preset')) + '">' +
                    '</div>' +
                    '<p class="form-help">' + escapeHtml(p.summary || '') + '</p>' +
                    '<p class="u-text-sm"><span class="u-fg-secondary">' + escapeHtml(t('analytics_label_upside', 'Upside')) + '</span> — ' + escapeHtml(p.upside || '') + '</p>' +
                    '<p class="u-text-sm"><span class="u-fg-secondary">' + escapeHtml(t('analytics_label_downside', 'Downside')) + '</span> — ' + escapeHtml(p.downside || '') + '</p>' +
                    (p.consent_required
                        ? '<p class="form-help">' + escapeHtml(t('analytics_consent_required_help', 'Visitor consent banner is required for this preset.')) + '</p>'
                        : '') +
                    '<p class="form-help">' + escapeHtml(t('analytics_default_retention', 'Default retention: {days} days', { days: p.retention_days || 0 })) + '</p>' +
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
                '<p class="form-help u-m-0">' + escapeHtml(t('analytics_no_data', 'No data yet.')) + '</p></section>';
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
                '<h4 class="analytics-panel__title">' + escapeHtml(t('analytics_daily_trend', 'Daily trend')) + '</h4>' +
                '<p class="form-help u-m-0">' + escapeHtml(t('analytics_no_daily_data', 'No daily data yet.')) + '</p></section>';
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
            return '<li class="analytics-trend-col" title="' + escapeHtml(t('analytics_trend_tooltip', '{date}: {views} page views, {visitors} visitors', { date: row.date || '', views: views, visitors: visitors })) + '">' +
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
                '<span><i class="analytics-swatch analytics-swatch--views"></i> ' + escapeHtml(t('analytics_legend_page_views', 'Page views')) + '</span>' +
                '<span><i class="analytics-swatch analytics-swatch--visitors"></i> ' + escapeHtml(t('analytics_legend_visitors', 'Visitors')) + '</span>' +
                '</div>'
            : '';
        return '<section class="analytics-panel analytics-panel--wide analytics-panel--chart">' +
            '<div class="u-cluster u-justify-between u-gap-3">' +
            '<h4 class="analytics-panel__title">' + escapeHtml(t('analytics_trend_title', 'Daily page views (last {days} days)', { days: windowDays })) + '</h4>' +
            legend +
            '</div>' +
            '<ul class="analytics-trend-cols">' + bars + '</ul></section>';
    }

    function renderReport(report) {
        report = report || {};
        var preset = selectedPreset();
        var scope = selectedScope();
        var scopeLabel = scope === 'all' ? t('analytics_scope_all', 'All enabled domains') : scope;
        var help = document.getElementById('analytics-report-help');
        if (help) {
            help.textContent = isAdvanced()
                ? t('analytics_report_help_advanced', 'Advanced report for {scope} — sessions, bounce, funnels, forms, outbound clicks, OS, and plugin events.', { scope: scopeLabel })
                : t('analytics_report_help_standard', 'Combined snapshot for {scope}. Depth follows your preset.', { scope: scopeLabel });
        }

        var html = '<div class="analytics-report' + (isAdvanced() ? ' analytics-report--advanced' : '') + '">';

        html += sectionHeading(t('analytics_section_overview', 'Overview'), t('analytics_section_overview_sub', 'Last 30 days · {scope}', { scope: scopeLabel })) +
            '<div class="analytics-kpi-grid">' +
            kpi(report.page_views || 0, t('analytics_kpi_page_views', 'Page views'), t('analytics_kpi_page_views_hint', 'Page loads only')) +
            kpi(report.unique_visitors || 0, t('analytics_kpi_visitors', 'Visitors'), preset === 'minimal' ? t('analytics_kpi_visitors_minimal_hint', 'Daily privacy-safe estimate') : t('analytics_kpi_visitors_hint', 'Unique visitors'));

        if (preset !== 'minimal' && preset !== 'off') {
            html += kpi(report.sessions || 0, t('analytics_kpi_sessions', 'Sessions'), t('analytics_kpi_sessions_hint', 'With session id')) +
                kpi(report.pages_per_visitor || 0, t('analytics_kpi_pages_per_visitor', 'Pages / visitor'), t('analytics_kpi_engagement', 'Engagement'));
        }
        if (isAdvanced()) {
            html += kpi((report.bounce_rate || 0) + '%', t('analytics_kpi_bounce_rate', 'Bounce rate'), t('analytics_kpi_bounce_hint', 'Single-page sessions')) +
                kpi((report.event_counts && report.event_counts.form_submit) || 0, t('analytics_kpi_form_submits', 'Form submits'), t('analytics_kpi_advanced_events', 'Advanced events')) +
                kpi((report.event_counts && report.event_counts.outbound_click) || 0, t('analytics_kpi_outbound_clicks', 'Outbound clicks'), t('analytics_kpi_advanced_events', 'Advanced events')) +
                kpi((report.event_counts && report.event_counts.plugin_event) || 0, t('analytics_kpi_plugin_events', 'Plugin events'), t('analytics_kpi_advanced_events', 'Advanced events'));
        }

        html += '</div>' + renderTrends(report.daily_stats);

        html += sectionHeading(t('analytics_section_content', 'Content & acquisition'), t('analytics_section_content_sub', 'Where traffic lands and comes from')) +
            '<div class="analytics-panel-grid">' +
            renderBarList(t('analytics_bar_top_pages', 'Top pages'), report.top_pages, 'path', 'views') +
            renderBarList(t('analytics_bar_traffic_sources', 'Traffic sources'), report.traffic_sources, 'source', 'visits') +
            renderBarList(t('analytics_bar_referrers', 'Referrers'), report.referrers, 'domain', 'visits') +
            renderBarList(t('analytics_bar_devices', 'Devices'), report.devices, 'device_type', 'visits');

        if (preset === 'standard' || isAdvanced()) {
            html += renderBarList(t('analytics_bar_entrance_pages', 'Entrance pages'), report.entrance_pages, 'path', 'entrances') +
                renderBarList(t('analytics_bar_exit_pages', 'Exit pages'), report.exit_pages, 'path', 'exits') +
                renderBarList(t('analytics_bar_campaigns', 'Campaigns'), report.campaigns, 'campaign', 'visits') +
                renderBarList(t('analytics_bar_browsers', 'Browsers'), report.browsers, 'browser', 'visits') +
                renderBarList(t('analytics_bar_locations', 'Locations'), report.countries, 'country', 'visits');
        }
        html += '</div>';

        if (isAdvanced()) {
            html += sectionHeading(t('analytics_section_audience', 'Audience & events'), t('analytics_section_audience_sub', 'Advanced-only detail')) +
                '<div class="analytics-panel-grid">' +
                renderBarList(t('analytics_bar_operating_systems', 'Operating systems'), report.operating_systems, 'os', 'visits') +
                renderBarList(t('analytics_bar_outbound_links', 'Outbound links'), report.outbound_targets, 'url', 'clicks') +
                renderBarList(t('analytics_bar_forms', 'Forms'), report.forms, 'form', 'submits') +
                renderBarList(t('analytics_bar_plugin_events', 'Plugin events'), report.plugin_events, 'event', 'count') +
                '</div>';
        }

        html += '</div>';
        return html;
    }

    function loadDomainsAndHealth() {
        return Promise.all([
            fetch(apiUrl('/api/analytics.php?action=domains'), { credentials: 'same-origin' }).then(function (r) { return r.json(); }),
            fetch(apiUrl('/api/analytics.php?action=health'), { credentials: 'same-origin' }).then(function (r) { return r.json(); })
        ]).then(function (results) {
            var domainsPayload = results[0];
            var healthPayload = results[1];
            if (domainsPayload.status === 'ok' && domainsPayload.data && Array.isArray(domainsPayload.data.domains)) {
                state.trackingDomains = domainsPayload.data.domains;
            } else {
                state.trackingDomains = [];
            }
            if (healthPayload.status === 'ok') {
                state.health = healthPayload.data || null;
            }
            renderDomainScopeOptions();
            renderHealthBanner();
        }).catch(function () {
            state.trackingDomains = [];
            renderDomainScopeOptions();
        });
    }

    function loadReport() {
        var panel = document.getElementById('analytics-report-panel');
        if (!panel) return Promise.resolve();

        if (!state.settings || state.settings.preset === 'off') {
            state.lastReport = null;
            panel.innerHTML = '<div class="empty-state"><p class="empty-state__title">' + escapeHtml(t('analytics_report_off_title', 'Analytics is Off')) + '</p>' +
                '<p class="empty-state__description">' + escapeHtml(t('analytics_report_off_desc', 'Nothing is collected. Choose Minimal or higher, then Save Settings.')) + '</p></div>';
            updateExportVisibility();
            return Promise.resolve();
        }

        panel.innerHTML = '<div class="loading-state"><p>' + escapeHtml(t('analytics_report_loading', 'Loading report…')) + '</p></div>';
        var trackingId = selectedScope() || 'all';
        state.trackingId = trackingId;
        updateExportVisibility();

        var end = new Date();
        var start = new Date(end.getTime() - 30 * 86400000);
        var qs = 'action=data&tracking_id=' + encodeURIComponent(trackingId) +
            '&start_date=' + encodeURIComponent(start.toISOString().slice(0, 10)) +
            '&end_date=' + encodeURIComponent(end.toISOString().slice(0, 10));
        return fetch(apiUrl('/api/analytics.php?' + qs), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (payload) {
                if (payload.status !== 'ok') {
                    throw new Error(payload.message || t('analytics_report_failed', 'Report failed'));
                }
                if (payload.meta && payload.meta.health) {
                    state.health = payload.meta.health;
                    renderHealthBanner();
                }
                state.lastReport = payload.data || {};
                panel.innerHTML = renderReport(state.lastReport);
            })
            .catch(function (err) {
                panel.innerHTML = '<div class="message message-error" role="alert">' + escapeHtml(err.message) + '</div>';
            });
    }

    function load() {
        return fetch(apiUrl('/api/analytics.php?action=get_settings'), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (payload) {
                if (payload.status !== 'ok') {
                    throw new Error(payload.message || t('analytics_load_settings_failed', 'Failed to load analytics settings'));
                }
                state.presets = (payload.data && payload.data.presets) || [];
                state.settings = (payload.data && payload.data.settings) || {};
                state.active = (payload.data && payload.data.active) || null;
                state.health = (payload.data && payload.data.health) || state.health;

                var consent = document.getElementById('analytics-consent-text');
                var retention = document.getElementById('analytics-retention-days');
                if (consent) consent.value = state.settings.consent_text || '';
                if (retention) {
                    retention.value = state.settings.retention_days != null ? state.settings.retention_days : '';
                }
                renderPresets();
                renderHealthBanner();
                if (typeof window.markSettingsClean === 'function') {
                    window.markSettingsClean('analytics');
                } else if (typeof window.checkSettingsDirtyState === 'function') {
                    setTimeout(function () {
                        if (typeof window.captureSettingsSnapshot === 'function') {
                            window.captureSettingsSnapshot('analytics');
                        }
                    }, 0);
                }
                return loadDomainsAndHealth().then(loadReport);
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
                    throw new Error(payload.message || t('analytics_save_failed', 'Save failed'));
                }
                state.settings = payload.data.settings;
                state.active = payload.data.active;
                state.health = payload.data.health || state.health;
                renderPresets();
                renderHealthBanner();
                if (typeof window.markSettingsClean === 'function') {
                    window.markSettingsClean('analytics');
                }
                return loadDomainsAndHealth().then(loadReport);
            });
    }

    function exportCsv() {
        var scope = selectedScope();
        if (!scope || scope === 'all' || selectedPreset() === 'off') {
            toast(t('analytics_export_domain_required', 'Choose a single domain to export CSV'), 'error');
            return;
        }
        var end = new Date();
        var start = new Date(end.getTime() - 30 * 86400000);
        var qs = 'action=export_csv&tracking_id=' + encodeURIComponent(scope) +
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
            var scopeSelect = document.getElementById('analytics-domain-scope');
            if (scopeSelect) {
                scopeSelect.addEventListener('change', function () {
                    state.trackingId = scopeSelect.value || 'all';
                    updateExportVisibility();
                    loadReport();
                });
            }
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
