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
        lastReport: null,
        periodDays: 30,
        includeBots: false,
        reportTab: 'overview'
    };

    function apiUrl(path) {
        return (window.Birkly && typeof window.Birkly.url === 'function') ? window.Birkly.url(path) : path;
    }

    function getCsrf() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function t(key, fallback, params) {
        if (window.Birkly && window.Birkly.i18n) {
            return window.Birkly.i18n.t('admin.' + key, fallback || key, params);
        }
        var out = fallback || key;
        if (params && typeof params === 'object') {
            Object.keys(params).forEach(function (name) {
                var value = String(params[name]);
                out = String(out).split('{{' + name + '}}').join(value);
                out = out.split('{' + name + '}').join(value);
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
                    if (panel) {
                        panel.innerHTML = renderReport(state.lastReport);
                        bindReportChrome(panel);
                    }
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
            return '<li class="analytics-bar-row analytics-bar-row--interactive" tabindex="0" data-label="' + escapeHtml(label) + '" data-value="' + count + '" data-pct="' + pct + '">' +
                '<div class="analytics-bar-row__meta">' +
                '<span class="analytics-bar-row__label" title="' + escapeHtml(label) + '">' + escapeHtml(label) + '</span>' +
                '<span class="analytics-bar-row__value u-numeric">' + escapeHtml(String(count)) + '</span>' +
                '</div>' +
                '<div class="analytics-bar-row__track" aria-hidden="true">' +
                '<span class="analytics-bar-row__fill" style="width:' + pct + '%"></span>' +
                '</div></li>';
        }).join('');
        return '<section class="analytics-panel analytics-panel--interactive">' +
            '<h4 class="analytics-panel__title">' + escapeHtml(title) + '</h4>' +
            '<ul class="analytics-bar-list">' + items + '</ul></section>';
    }

    function selectedPeriodDays() {
        var select = document.getElementById('analytics-period-range');
        var days = select ? parseInt(select.value, 10) : state.periodDays;
        if (!days || days < 1) {
            days = 30;
        }
        return days;
    }

    function includeBotsInReport() {
        var input = document.getElementById('analytics-include-bots');
        return !!(input && input.checked);
    }

    function renderTrafficQuality(bot) {
        bot = bot || {};
        var humanViews = Number(bot.human_page_views) || 0;
        var botViews = Number(bot.bot_page_views) || 0;
        var suspected = Number(bot.suspected_bot_page_views) || 0;
        var unknownDev = Number(bot.unknown_device_views) || 0;
        var total = humanViews + botViews + suspected;
        if (total === 0) {
            return '';
        }

        function qualityBar(label, count, modifier) {
            var pct = Math.max(4, Math.round((count / Math.max(total, 1)) * 100));
            return '<li class="analytics-quality-row analytics-quality-row--' + modifier + '">' +
                '<div class="analytics-bar-row__meta">' +
                '<span class="analytics-bar-row__label">' + escapeHtml(label) + '</span>' +
                '<span class="analytics-bar-row__value u-numeric" data-count="' + count + '">' + escapeHtml(String(count)) + '</span>' +
                '</div>' +
                '<div class="analytics-bar-row__track"><span class="analytics-bar-row__fill" style="width:' + pct + '%"></span></div>' +
                '</li>';
        }

        var agents = Array.isArray(bot.top_bot_agents) ? bot.top_bot_agents : [];
        var agentList = agents.length
            ? renderBarList(t('analytics_bot_agents_title', 'Top bot user agents'), agents, 'agent', 'views')
            : '';

        return '<section class="analytics-panel analytics-panel--wide analytics-panel--interactive">' +
            '<h4 class="analytics-panel__title">' + escapeHtml(t('analytics_traffic_quality_title', 'Traffic quality')) + '</h4>' +
            '<p class="form-help">' + escapeHtml(t('analytics_traffic_quality_help', 'Human KPIs exclude confirmed and suspected automation. Enable “Include bot traffic” to count everything.')) + '</p>' +
            '<ul class="analytics-bar-list analytics-quality-list">' +
            qualityBar(t('analytics_kpi_human_views', 'Human page views'), humanViews, 'human') +
            qualityBar(t('analytics_kpi_suspected_views', 'Suspected automation'), suspected, 'suspected') +
            qualityBar(t('analytics_kpi_bot_views', 'Confirmed bots'), botViews, 'bot') +
            '</ul>' +
            (unknownDev > 0
                ? '<p class="form-help u-m-0">' + escapeHtml(t('analytics_unknown_device_hint', 'Unknown device context on {count} views (often privacy browsers or incomplete payloads).', { count: unknownDev })) + '</p>'
                : '') +
            agentList +
            '</section>';
    }

    function reportTabIds(preset) {
        var tabs = [
            { id: 'overview', label: t('analytics_tab_overview', 'Overview') },
            { id: 'acquisition', label: t('analytics_tab_acquisition', 'Acquisition') },
            { id: 'content', label: t('analytics_tab_content', 'Content') },
            { id: 'technology', label: t('analytics_tab_technology', 'Technology') }
        ];
        if (preset === 'standard' || preset === 'advanced') {
            tabs.push({ id: 'behavior', label: t('analytics_tab_behavior', 'Behavior') });
        }
        if (preset === 'advanced') {
            tabs.push({ id: 'sessions', label: t('analytics_tab_sessions', 'Sessions') });
        }
        return tabs;
    }

    function renderReportTabs(preset) {
        var tabs = reportTabIds(preset);
        var items = tabs.map(function (tab) {
            var active = state.reportTab === tab.id;
            return '<button type="button" class="tab' + (active ? ' active' : '') + '" role="tab" aria-selected="' + (active ? 'true' : 'false') + '" data-report-tab="' + escapeHtml(tab.id) + '">' +
                '<span class="nav-text">' + escapeHtml(tab.label) + '</span></button>';
        }).join('');

        return '<div class="analytics-report-tabs-wrap">' +
            '<nav class="tabs analytics-report-tabs" role="tablist" aria-label="' + escapeHtml(t('analytics_report_tabs_label', 'Report sections')) + '">' + items + '</nav>' +
            '<p class="form-help analytics-drill-hint">' + escapeHtml(t('analytics_drill_hint', 'Pick a section — details load here instead of one long page.')) + '</p></div>';
    }

    function renderTopPaths(paths) {
        paths = Array.isArray(paths) ? paths : [];
        if (!paths.length) {
            return '';
        }
        return sectionHeading(t('analytics_section_paths', 'Top paths'), t('analytics_section_paths_sub', 'Common 2–3 step journeys')) +
            '<div class="analytics-panel-grid">' +
            renderBarList(t('analytics_bar_paths', 'Path sequences'), paths, 'path', 'sessions') +
            '</div>';
    }

    function renderSessionTrail(trail) {
        trail = Array.isArray(trail) ? trail : [];
        if (!trail.length) {
            return '<p class="form-help u-m-0">' + escapeHtml(t('analytics_session_empty_trail', 'No trail steps recorded.')) + '</p>';
        }
        var items = trail.map(function (step) {
            var when = step.timestamp ? String(step.timestamp).replace('T', ' ').slice(0, 19) : '';
            return '<li class="analytics-trail-step">' +
                '<span class="analytics-trail-step__time">' + escapeHtml(when) + '</span>' +
                '<span class="analytics-trail-step__label">' + escapeHtml(step.label || step.path || step.type || '—') + '</span>' +
                '</li>';
        }).join('');
        return '<ol class="analytics-trail-list">' + items + '</ol>';
    }

    function renderJourney(journey) {
        journey = journey || {};
        var sessions = Array.isArray(journey.sessions) ? journey.sessions : [];
        var visitors = Array.isArray(journey.visitors) ? journey.visitors : [];
        if (!sessions.length && !visitors.length) {
            return '';
        }

        var sessionRows = sessions.map(function (s) {
            var id = escapeHtml(s.session_id || '');
            var visitor = escapeHtml(s.visitor_id || '—');
            var source = escapeHtml(s.source || 'direct');
            var meta = [
                escapeHtml(s.country || '—'),
                escapeHtml(s.device_type || '—'),
                escapeHtml(s.browser || '')
            ].filter(Boolean).join(' · ');
            return '<tr class="analytics-session-row" tabindex="0" role="button" data-session-id="' + id + '" aria-expanded="false">' +
                '<td><code>' + visitor + '</code></td>' +
                '<td>' + meta + (s.is_bot ? ' · bot' : '') + '</td>' +
                '<td>' + source + '</td>' +
                '<td>' + escapeHtml(String(s.page_views || 0)) + '</td>' +
                '<td>' + escapeHtml(String(s.started_at || '').replace('T', ' ').slice(0, 16)) + '</td>' +
                '</tr>' +
                '<tr class="analytics-session-detail" data-session-detail="' + id + '" hidden><td colspan="5">' +
                renderSessionTrail(s.trail) +
                '</td></tr>';
        }).join('');

        var visitorRows = visitors.slice(0, 15).map(function (v) {
            return '<tr><td><code>' + escapeHtml(v.visitor_id || '—') + '</code></td>' +
                '<td>' + escapeHtml(String(v.sessions || 0)) + '</td>' +
                '<td>' + escapeHtml(String(v.page_views || 0)) + '</td>' +
                '<td>' + escapeHtml(v.country || '—') + '</td>' +
                '<td>' + escapeHtml(v.source || 'direct') + '</td>' +
                '<td>' + escapeHtml(String(v.last_seen || '').replace('T', ' ').slice(0, 16)) + '</td></tr>';
        }).join('');

        return sectionHeading(t('analytics_section_visitors', 'Visitors & sessions'), t('analytics_section_visitors_sub', 'Pseudonymous footprints (Advanced)')) +
            '<div class="analytics-journey-grid">' +
            '<section class="analytics-panel analytics-panel--table">' +
            '<h4 class="analytics-panel__title">' + escapeHtml(t('analytics_sessions_title', 'Recent sessions')) + '</h4>' +
            '<div class="analytics-table-wrap"><table class="analytics-data-table">' +
            '<thead><tr><th>' + escapeHtml(t('analytics_col_visitor', 'Visitor')) + '</th><th>' + escapeHtml(t('analytics_col_context', 'Context')) + '</th><th>' + escapeHtml(t('analytics_col_source', 'Source')) + '</th><th>' + escapeHtml(t('analytics_col_pages', 'Pages')) + '</th><th>' + escapeHtml(t('analytics_col_started', 'Started')) + '</th></tr></thead>' +
            '<tbody>' + (sessionRows || '<tr><td colspan="5">' + escapeHtml(t('analytics_no_sessions', 'No sessions in this period yet.')) + '</td></tr>') + '</tbody></table></div>' +
            '</section>' +
            '<section class="analytics-panel analytics-panel--table">' +
            '<h4 class="analytics-panel__title">' + escapeHtml(t('analytics_visitors_title', 'Visitors')) + '</h4>' +
            '<div class="analytics-table-wrap"><table class="analytics-data-table">' +
            '<thead><tr><th>' + escapeHtml(t('analytics_col_visitor', 'Visitor')) + '</th><th>' + escapeHtml(t('analytics_col_sessions', 'Sessions')) + '</th><th>' + escapeHtml(t('analytics_col_pages', 'Pages')) + '</th><th>' + escapeHtml(t('analytics_col_country', 'Country')) + '</th><th>' + escapeHtml(t('analytics_col_source', 'Source')) + '</th><th>' + escapeHtml(t('analytics_col_last_seen', 'Last seen')) + '</th></tr></thead>' +
            '<tbody>' + (visitorRows || '<tr><td colspan="6">' + escapeHtml(t('analytics_no_visitors', 'No visitors yet.')) + '</td></tr>') + '</tbody></table></div>' +
            '</section></div>';
    }

    function bindSessionRowToggle(container) {
        if (!container) return;
        container.addEventListener('click', function (e) {
            var row = e.target && e.target.closest ? e.target.closest('.analytics-session-row') : null;
            if (!row) return;
            var sessionId = row.getAttribute('data-session-id');
            if (!sessionId) return;
            var detail = container.querySelector('[data-session-detail="' + sessionId + '"]');
            if (!detail) return;
            var open = detail.hidden === false;
            detail.hidden = open;
            row.setAttribute('aria-expanded', open ? 'false' : 'true');
            row.classList.toggle('is-open', !open);
        });
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
        var windowDays = Math.min(isAdvanced() ? 90 : 14, selectedPeriodDays());
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
            var tip = t('analytics_trend_tooltip', '{date}: {views} page views, {visitors} visitors', { date: row.date || '', views: views, visitors: visitors });
            return '<li class="analytics-trend-col analytics-trend-col--interactive" tabindex="0" role="button" title="' + escapeHtml(tip) + '" data-date="' + escapeHtml(String(row.date || '')) + '" data-views="' + views + '" data-visitors="' + visitors + '">' +
                '<span class="analytics-trend-col__stack" aria-hidden="true">' +
                '<span class="analytics-trend-col__bar analytics-trend-col__bar--views" style="height:' + viewsPct + '%"></span>' +
                (isAdvanced()
                    ? '<span class="analytics-trend-col__bar analytics-trend-col__bar--visitors" style="height:' + visitorsPct + '%"></span>'
                    : '') +
                '</span>' +
                '<span class="analytics-trend-col__label">' + escapeHtml(String(row.date || '').slice(5)) + '</span>' +
                '<span class="analytics-trend-col__tip" aria-hidden="true">' + escapeHtml(tip) + '</span>' +
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

    function renderReportTabBody(report, preset) {
        var scope = selectedScope();
        var scopeLabel = scope === 'all' ? t('analytics_scope_all', 'All enabled domains') : scope;
        var periodDays = selectedPeriodDays();
        var sub = t('analytics_section_overview_sub', 'Last {days} days · {scope}', { days: periodDays, scope: scopeLabel });
        var tab = state.reportTab;

        if (tab === 'overview') {
            var overviewKpis = '<div class="analytics-kpi-grid">' +
                kpi(report.page_views || 0, t('analytics_kpi_page_views', 'Page views'), t('analytics_kpi_page_views_hint', 'Human-focused totals')) +
                kpi(report.unique_visitors || 0, t('analytics_kpi_visitors', 'Visitors'), preset === 'minimal' ? t('analytics_kpi_visitors_minimal_hint', 'Daily privacy-safe estimate') : t('analytics_kpi_visitors_hint', 'Unique visitors'));
            if (preset !== 'minimal' && preset !== 'off') {
                overviewKpis += kpi(report.sessions || 0, t('analytics_kpi_sessions', 'Sessions'), t('analytics_kpi_sessions_hint', 'With session id')) +
                    kpi(report.pages_per_visitor || 0, t('analytics_kpi_pages_per_visitor', 'Pages / visitor'), t('analytics_kpi_engagement', 'Engagement'));
            }
            if (isAdvanced()) {
                overviewKpis += kpi((report.bounce_rate || 0) + '%', t('analytics_kpi_bounce_rate', 'Bounce rate'), t('analytics_kpi_bounce_hint', 'Single-page sessions'));
            }
            overviewKpis += '</div>';
            return sectionHeading(t('analytics_section_overview', 'Overview'), sub) +
                overviewKpis + renderTrafficQuality(report.bot_traffic) + renderTrends(report.daily_stats);
        }

        if (tab === 'acquisition') {
            return sectionHeading(t('analytics_tab_acquisition', 'Acquisition'), t('analytics_section_content_sub', 'Where traffic comes from')) +
                '<div class="analytics-panel-grid">' +
                renderBarList(t('analytics_bar_traffic_sources', 'Traffic sources'), report.traffic_sources, 'source', 'visits') +
                renderBarList(t('analytics_bar_referrers', 'Referrers'), report.referrers, 'domain', 'visits') +
                renderBarList(t('analytics_bar_campaigns', 'Campaigns'), report.campaigns, 'campaign', 'visits') +
                ((preset === 'standard' || isAdvanced())
                    ? renderBarList(t('analytics_bar_locations', 'Locations'), report.countries, 'country', 'visits')
                    : '') +
                '</div>';
        }

        if (tab === 'content') {
            return sectionHeading(t('analytics_tab_content', 'Content'), t('analytics_section_content_sub', 'Where traffic lands')) +
                '<div class="analytics-panel-grid">' +
                renderBarList(t('analytics_bar_top_pages', 'Top pages'), report.top_pages, 'path', 'views') +
                ((preset === 'standard' || isAdvanced())
                    ? renderBarList(t('analytics_bar_entrance_pages', 'Entrance pages'), report.entrance_pages, 'path', 'entrances') +
                    renderBarList(t('analytics_bar_exit_pages', 'Exit pages'), report.exit_pages, 'path', 'exits')
                    : '') +
                '</div>';
        }

        if (tab === 'technology') {
            return sectionHeading(t('analytics_tab_technology', 'Technology'), t('analytics_section_audience_sub', 'Devices and clients')) +
                '<div class="analytics-panel-grid">' +
                renderBarList(t('analytics_bar_devices', 'Devices'), report.devices, 'device_type', 'visits') +
                ((preset === 'standard' || isAdvanced())
                    ? renderBarList(t('analytics_bar_browsers', 'Browsers'), report.browsers, 'browser', 'visits') +
                    renderBarList(t('analytics_bar_operating_systems', 'Operating systems'), report.operating_systems, 'os', 'visits')
                    : '') +
                '</div>';
        }

        if (tab === 'behavior' && (preset === 'standard' || preset === 'advanced')) {
            var body = sectionHeading(t('analytics_tab_behavior', 'Behavior'), t('analytics_section_audience_sub', 'Actions on the site')) +
                '<div class="analytics-panel-grid">';
            if (isAdvanced()) {
                body += renderBarList(t('analytics_bar_outbound_links', 'Outbound links'), report.outbound_targets, 'url', 'clicks') +
                    renderBarList(t('analytics_bar_forms', 'Forms'), report.forms, 'form', 'submits') +
                    renderBarList(t('analytics_bar_plugin_events', 'Plugin events'), report.plugin_events, 'event', 'count') +
                    renderTopPaths(report.top_paths || (report.journey && report.journey.paths));
            }
            body += '</div>';
            return body;
        }

        if (tab === 'sessions' && preset === 'advanced') {
            return sectionHeading(t('analytics_tab_sessions', 'Sessions'), t('analytics_section_visitors_sub', 'Pseudonymous footprints (Advanced)')) +
                renderJourney(report.journey);
        }

        state.reportTab = 'overview';
        return renderReportTabBody(report, preset);
    }

    function renderReport(report) {
        report = report || {};
        var preset = selectedPreset();
        var scope = selectedScope();
        var scopeLabel = scope === 'all' ? t('analytics_scope_all', 'All enabled domains') : scope;
        var help = document.getElementById('analytics-report-help');
        if (help) {
            help.textContent = isAdvanced()
                ? t('analytics_report_help_advanced', 'Use the section tabs to drill into acquisition, behavior, and per-session footprints for {scope}.', { scope: scopeLabel })
                : t('analytics_report_help_standard', 'Combined snapshot for {scope}. Depth follows your preset.', { scope: scopeLabel });
        }

        var allowed = reportTabIds(preset).map(function (x) { return x.id; });
        if (allowed.indexOf(state.reportTab) === -1) {
            state.reportTab = 'overview';
        }

        return '<div class="analytics-report' + (isAdvanced() ? ' analytics-report--advanced' : '') + '">' +
            renderReportTabs(preset) +
            '<div class="analytics-report-stage" id="analytics-report-stage" role="tabpanel">' +
            renderReportTabBody(report, preset) +
            '</div>' +
            '<div id="analytics-hover-flyout" class="analytics-hover-flyout" hidden aria-live="polite"></div>' +
            '</div>';
    }

    function bindReportChrome(container) {
        if (!container) return;
        bindSessionRowToggle(container);

        container.querySelectorAll('[data-report-tab]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var next = btn.getAttribute('data-report-tab');
                if (!next || next === state.reportTab) return;
                state.reportTab = next;
                if (state.lastReport) {
                    var stage = container.querySelector('#analytics-report-stage');
                    var tabs = container.querySelector('.analytics-report-tabs');
                    if (stage) {
                        stage.innerHTML = renderReportTabBody(state.lastReport, selectedPreset());
                    }
                    if (tabs) {
                        tabs.querySelectorAll('[data-report-tab]').forEach(function (tbtn) {
                            var on = tbtn.getAttribute('data-report-tab') === state.reportTab;
                            tbtn.classList.toggle('active', on);
                            tbtn.setAttribute('aria-selected', on ? 'true' : 'false');
                        });
                    }
                    bindSessionRowToggle(container);
                    bindHoverFlyout(container);
                }
            });
        });

        bindHoverFlyout(container);
    }

    function bindHoverFlyout(container) {
        var flyout = container.querySelector('#analytics-hover-flyout');
        if (!flyout) return;

        function showFlyout(target, html, clientX, clientY) {
            flyout.innerHTML = html;
            flyout.hidden = false;
            flyout.style.left = Math.min(clientX + 12, window.innerWidth - 280) + 'px';
            flyout.style.top = Math.max(24, clientY - 8) + 'px';
            target.classList.add('is-hovered');
        }

        function hideFlyout(target) {
            flyout.hidden = true;
            if (target) target.classList.remove('is-hovered');
        }

        container.querySelectorAll('.analytics-trend-col--interactive').forEach(function (col) {
            col.addEventListener('mouseenter', function (e) {
                var html = '<strong>' + escapeHtml(col.getAttribute('data-date') || '') + '</strong><br>' +
                    escapeHtml(t('analytics_flyout_views', '{count} page views', { count: col.getAttribute('data-views') || '0' })) + '<br>' +
                    escapeHtml(t('analytics_flyout_visitors', '{count} visitors', { count: col.getAttribute('data-visitors') || '0' }));
                showFlyout(col, html, e.clientX, e.clientY);
            });
            col.addEventListener('mouseleave', function () { hideFlyout(col); });
        });

        container.querySelectorAll('.analytics-bar-row--interactive').forEach(function (row) {
            row.addEventListener('mouseenter', function (e) {
                var label = row.getAttribute('data-label') || '';
                var value = row.getAttribute('data-value') || '0';
                var pct = row.getAttribute('data-pct') || '0';
                var html = '<strong>' + escapeHtml(label) + '</strong><br>' +
                    escapeHtml(t('analytics_flyout_count', '{count} events', { count: value })) + '<br>' +
                    escapeHtml(t('analytics_flyout_share', '{pct}% of top in this list', { pct: pct }));
                showFlyout(row, html, e.clientX, e.clientY);
            });
            row.addEventListener('mouseleave', function () { hideFlyout(row); });
        });
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
            panel.innerHTML = '<div class="empty-state"><p class="empty-state__title">' + escapeHtml(t('analytics_report_off_title', 'Analytics is off')) + '</p>' +
                '<p class="empty-state__description">' + escapeHtml(t('analytics_report_off_desc', 'Nothing is collected. Choose Minimal or higher, then Save changes.')) + '</p></div>';
            updateExportVisibility();
            return Promise.resolve();
        }

        panel.innerHTML = '<div class="loading-state"><p>' + escapeHtml(t('analytics_report_loading', 'Loading report…')) + '</p></div>';
        var trackingId = selectedScope() || 'all';
        state.trackingId = trackingId;
        updateExportVisibility();

        var periodDays = selectedPeriodDays();
        state.periodDays = periodDays;
        state.includeBots = includeBotsInReport();
        var end = new Date();
        var start = new Date(end.getTime() - periodDays * 86400000);
        var qs = 'action=data&tracking_id=' + encodeURIComponent(trackingId) +
            '&start_date=' + encodeURIComponent(start.toISOString().slice(0, 10)) +
            '&end_date=' + encodeURIComponent(end.toISOString().slice(0, 10)) +
            '&include_bots=' + (state.includeBots ? '1' : '0');
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
                bindReportChrome(panel);
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
        var start = new Date(end.getTime() - selectedPeriodDays() * 86400000);
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
            var periodSelect = document.getElementById('analytics-period-range');
            if (periodSelect) {
                periodSelect.addEventListener('change', function () {
                    state.periodDays = selectedPeriodDays();
                    loadReport();
                });
            }
            var botsToggle = document.getElementById('analytics-include-bots');
            if (botsToggle) {
                botsToggle.addEventListener('change', function () {
                    state.includeBots = includeBotsInReport();
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
