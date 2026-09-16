/**
 * P87 — Admin Guide companion (launcher + panel).
 */
(function (global) {
    'use strict';

    let state = {
        open: false,
        data: null,
        area: '',
        panelTab: 'setup',
        pendingJourneyFocus: null,
    };

    function t(key, fallback, params) {
        if (global.Birkly && global.Birkly.i18n && typeof global.Birkly.i18n.t === 'function') {
            return global.Birkly.i18n.t('admin.' + key, fallback, params);
        }
        return fallback || key;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
    }

    function csrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function adminPageContext() {
        const body = document.body;
        return {
            page: body.getAttribute('data-admin-page') || '',
            tab: body.getAttribute('data-admin-tab') || '',
        };
    }

    function apiUrl(path) {
        return global.Birkly && typeof global.Birkly.url === 'function' ? global.Birkly.url(path) : path;
    }

    function helpUrl(topic) {
        return apiUrl('/admin/index.php?page=help&topic=' + encodeURIComponent(topic));
    }

    async function fetchProgress(pageOverride, tabOverride) {
        const ctx = adminPageContext();
        const qs = new URLSearchParams({
            action: 'progress',
            page: pageOverride || ctx.page,
            tab: tabOverride || ctx.tab,
        });
        const res = await fetch(apiUrl('/api/guide.php?' + qs.toString()), { credentials: 'same-origin' });
        const json = await res.json();
        if (!json || json.status !== 'ok') {
            throw new Error(json?.message || 'Guide progress failed');
        }
        return json.data;
    }

    async function postUpdate(payload) {
        const ctx = adminPageContext();
        const res = await fetch(apiUrl('/api/guide.php'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(Object.assign({
                action: 'update',
                _csrf: csrfToken(),
                page: ctx.page,
                tab: ctx.tab,
            }, payload)),
        });
        const json = await res.json();
        if (!json || json.status !== 'ok') {
            throw new Error(json?.message || 'Guide update failed');
        }
        return json.data;
    }

    function tipTitle(tip) {
        if (!tip) return '';
        return t(tip.title_key, tip.title_key);
    }

    function tipBody(tip) {
        if (!tip) return '';
        return t(tip.body_key, tip.body_key);
    }

    function setLauncherAttention(on) {
        const btn = document.getElementById('guide-launcher-btn');
        if (!btn) return;
        btn.classList.toggle('is-attention', !!on);
    }

    function setPanelOpen(open) {
        state.open = open;
        const panel = document.getElementById('guide-panel');
        const btn = document.getElementById('guide-launcher-btn');
        if (panel) panel.classList.toggle('is-open', open);
        if (btn) {
            btn.classList.toggle('is-open', open);
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
        if (open && state.data?.attention) {
            setLauncherAttention(false);
        }
    }

    function renderChecklistHtml(checklist) {
        return (checklist || []).map(function (item) {
            const done = item.done ? ' is-done' : '';
            const mark = item.done ? '✓' : '○';
            const label = item.done
                ? escapeHtml(item.label || item.id)
                : '<a href="' + escapeHtml(item.href || '#') + '">' + escapeHtml(item.label || item.id) + '</a>';
            return '<li class="' + done + '"><span aria-hidden="true">' + mark + '</span><span>' + label + '</span></li>';
        }).join('');
    }

    function renderJourneysHtml(journeys) {
        return (journeys || []).map(function (journey) {
            const doneClass = journey.completed ? ' is-done' : '';
            const steps = (journey.steps || []).map(function (step, idx) {
                return '<li class="guide-journey-step">' +
                    '<p class="guide-journey-teach">' + escapeHtml(step.teach || '') + '</p>' +
                    (step.href ? '<a class="btn btn-secondary btn-sm" href="' + escapeHtml(step.href) + '">' +
                        escapeHtml(t('guide_journey_go', 'Go there')) + '</a> ' : '') +
                    '<a class="btn btn-ghost btn-sm" href="' + escapeHtml(helpUrl(step.help_topic || 'getting-started')) + '">' +
                        escapeHtml(t('guide_learn_more', 'Learn more')) + '</a>' +
                    '</li>';
            }).join('');
            const completeBtn = journey.completed ? '' :
                '<button type="button" class="btn btn-ghost btn-sm guide-journey-done" data-journey-id="' + escapeHtml(journey.id) + '">' +
                escapeHtml(t('guide_journey_mark_done', 'Mark journey done')) + '</button>';
            return '<details class="guide-journey' + doneClass + '" data-journey-id="' + escapeHtml(journey.id) + '"' + (journey.completed ? '' : ' open') + '>' +
                '<summary class="guide-journey-title">' + escapeHtml(journey.title || journey.id) + '</summary>' +
                '<ol class="guide-journey-steps">' + steps + '</ol>' + completeBtn + '</details>';
        }).join('');
    }

    function renderConceptsHtml(concepts) {
        return (concepts || []).map(function (c) {
            return '<article class="guide-concept-card">' +
                '<h4>' + escapeHtml(c.title || '') + '</h4>' +
                '<p>' + escapeHtml(c.body || '') + '</p>' +
                '<a class="btn btn-ghost btn-sm" href="' + escapeHtml(helpUrl(c.help_topic || 'getting-started')) + '">' +
                escapeHtml(t('guide_learn_more', 'Learn more')) + '</a></article>';
        }).join('');
    }

    function renderPanelBody(data) {
        const tab = state.panelTab;
        let inner = '';
        if (tab === 'setup') {
            let tipHtml = '';
            if (data.tip) {
                const topic = data.tip.help_topic || 'getting-started';
                tipHtml = '<div class="guide-tip-block">' +
                    '<h4>' + escapeHtml(tipTitle(data.tip)) + '</h4>' +
                    '<p>' + escapeHtml(tipBody(data.tip)) + '</p>' +
                    '<a class="btn btn-ghost btn-sm" href="' + escapeHtml(helpUrl(topic)) + '">' +
                    escapeHtml(t('guide_learn_more', 'Learn more')) + '</a></div>';
            }
            inner = '<p class="u-text-sm u-fg-secondary">' + escapeHtml(t('guide_panel_intro', 'Finish these steps to get your site live.')) + '</p>' +
                '<ul class="guide-checklist" role="list">' + renderChecklistHtml(data.checklist) + '</ul>' + tipHtml;
        } else if (tab === 'journeys') {
            inner = '<p class="u-text-sm u-fg-secondary">' + escapeHtml(t('guide_journeys_intro', 'Short paths with one action per step.')) + '</p>' +
                renderJourneysHtml(data.journeys);
        } else {
            inner = '<p class="u-text-sm u-fg-secondary">' + escapeHtml(t('guide_concepts_intro', 'Optional background — skip if you prefer learning by doing.')) + '</p>' +
                '<div class="guide-concepts">' + renderConceptsHtml(data.concepts) + '</div>';
        }
        return inner;
    }

    function bindPanelInteractions(body) {
        body.querySelectorAll('.guide-journey-done').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const id = btn.getAttribute('data-journey-id');
                if (!id) return;
                postUpdate({ mark_journey_complete: id }).then(applyProgress).catch(function () { /* ignore */ });
            });
        });
    }

    function renderPanel(data) {
        const body = document.getElementById('guide-panel-body');
        const tabs = document.getElementById('guide-panel-tabs');
        if (!body || !data) return;

        if (tabs) {
            tabs.querySelectorAll('[data-guide-tab]').forEach(function (btn) {
                const active = btn.getAttribute('data-guide-tab') === state.panelTab;
                btn.classList.toggle('is-active', active);
                btn.setAttribute('aria-selected', active ? 'true' : 'false');
            });
        }

        body.innerHTML = renderPanelBody(data);
        bindPanelInteractions(body);
        if (state.pendingJourneyFocus) {
            const focusId = state.pendingJourneyFocus;
            state.pendingJourneyFocus = null;
            const journeyEl = body.querySelector('.guide-journey[data-journey-id="' + focusId + '"]');
            if (journeyEl) {
                journeyEl.open = true;
                journeyEl.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            }
        }
    }

    function isOnboardingWelcomeVisible() {
        const modal = document.getElementById('onboarding-complete-modal');
        if (!modal) {
            return false;
        }
        if (modal.style.display === 'flex') {
            return true;
        }
        const params = new URLSearchParams(window.location.search);
        return modal.dataset.showWelcome === '1' || params.get('onboarding_complete') === 'true';
    }

    function runFirstVisitAutoOpen(data) {
        if (!data || !data.should_auto_open_panel || isOnboardingWelcomeVisible()) {
            return;
        }
        setPanelOpen(true);
        postUpdate({ ack_first_visit: true }).catch(function () { /* ignore */ });
        if (data.tip && data.tip.area) {
            postUpdate({ mark_tip_seen: data.tip.area }).catch(function () { /* ignore */ });
        }
    }

    function dismissPostOnboardingNudge() {
        return postUpdate({ dismiss_post_onboarding_nudge: true }).then(applyProgress);
    }

    function updatePostOnboardingBanner(data) {
        const mount = document.getElementById('guide-post-onboarding-mount');
        if (!mount || document.body.getAttribute('data-admin-page') !== 'dashboard') {
            return;
        }
        if (!data || !data.show_post_onboarding_nudge) {
            mount.hidden = true;
            mount.innerHTML = '';
            mount.dataset.bound = '';
            return;
        }
        mount.hidden = false;
        if (mount.dataset.bound === '1') {
            return;
        }
        mount.className = 'guide-post-onboarding-mount callout callout-info';
        mount.setAttribute('role', 'region');
        mount.setAttribute('aria-label', t('guide_post_onboarding_title', 'Continue in Guide'));
        mount.innerHTML =
            '<div class="u-split u-gap-3">' +
            '<div><p class="u-text-sm u-mb-0"><strong>' + escapeHtml(t('guide_post_onboarding_title', 'Continue in Guide')) + '</strong></p>' +
            '<p class="u-text-sm u-fg-secondary u-mb-0">' + escapeHtml(t('guide_post_onboarding_body', 'Use the checklist and journeys to publish your first content and explore Hub.')) + '</p></div>' +
            '<div class="u-cluster u-shrink-0">' +
            '<button type="button" class="btn btn-primary btn-sm" data-guide-nudge-open>' + escapeHtml(t('guide_open_panel', 'Open Guide')) + '</button>' +
            '<button type="button" class="btn btn-ghost btn-sm" data-guide-nudge-dismiss>' + escapeHtml(t('guide_post_onboarding_dismiss', 'Dismiss')) + '</button>' +
            '</div></div>';
        mount.dataset.bound = '1';
        mount.querySelector('[data-guide-nudge-open]')?.addEventListener('click', function () {
            openGuide({ tab: 'setup' });
            dismissPostOnboardingNudge().catch(function () { /* ignore */ });
        });
        mount.querySelector('[data-guide-nudge-dismiss]')?.addEventListener('click', function () {
            dismissPostOnboardingNudge().catch(function () { /* ignore */ });
        });
    }

    function applyProgress(data) {
        state.data = data;
        state.area = data.area || '';
        renderPanel(data);
        const showAttention = !!data.attention && !state.open;
        setLauncherAttention(showAttention);
        runFirstVisitAutoOpen(data);
        updatePostOnboardingBanner(data);
        global.dispatchEvent(new CustomEvent('birkly:guide-progress', { detail: data }));
    }

    function afterOnboardingWelcomeClosed() {
        fetchProgress().then(function (data) {
            applyProgress(data);
            if (data.should_auto_open_panel) {
                runFirstVisitAutoOpen(data);
            }
        }).catch(function () { /* ignore */ });
    }

    async function refresh() {
        try {
            const data = await fetchProgress();
            applyProgress(data);
        } catch (e) {
            console.warn('Guide:', e);
        }
    }

    function mountLauncher() {
        const mount = document.getElementById('guide-launcher-mount');
        if (!mount || mount.dataset.guideMounted === '1') return;

        mount.innerHTML =
            '<button type="button" id="guide-launcher-btn" class="guide-launcher-btn" aria-expanded="false" aria-controls="guide-panel" title="' +
            escapeHtml(t('guide_launcher_label', 'Guide')) + '">' +
            (global.Birkly && global.Birkly.icon ? global.Birkly.icon('question', { size: 18 }) : '?') +
            '</button>';

        const panel = document.createElement('div');
        panel.id = 'guide-panel';
        panel.className = 'guide-panel';
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-label', t('guide_panel_title', 'Guide'));
        panel.innerHTML =
            '<div class="guide-panel-header">' +
            '<h2 class="guide-panel-title">' + escapeHtml(t('guide_panel_title', 'Guide')) + '</h2>' +
            '<button type="button" class="btn btn-icon" id="guide-panel-close" aria-label="' + escapeHtml(t('close', 'Close')) + '">×</button>' +
            '</div>' +
            '<div class="guide-panel-tabs" id="guide-panel-tabs" role="tablist">' +
            '<button type="button" class="guide-panel-tab is-active" data-guide-tab="setup" role="tab" aria-selected="true">' +
            escapeHtml(t('guide_tab_setup', 'Setup')) + '</button>' +
            '<button type="button" class="guide-panel-tab" data-guide-tab="journeys" role="tab" aria-selected="false">' +
            escapeHtml(t('guide_tab_journeys', 'Journeys')) + '</button>' +
            '<button type="button" class="guide-panel-tab" data-guide-tab="concepts" role="tab" aria-selected="false">' +
            escapeHtml(t('guide_tab_concepts', 'How it works')) + '</button>' +
            '</div>' +
            '<div class="guide-panel-body" id="guide-panel-body"></div>';
        document.body.appendChild(panel);

        mount.dataset.guideMounted = '1';

        document.getElementById('guide-launcher-btn').addEventListener('click', function () {
            const next = !state.open;
            setPanelOpen(next);
            if (next && state.data?.tip?.area) {
                postUpdate({ mark_tip_seen: state.data.tip.area }).then(applyProgress).catch(function () { /* ignore */ });
            }
        });
        document.getElementById('guide-panel-close').addEventListener('click', function () {
            setPanelOpen(false);
        });
        document.getElementById('guide-panel-tabs').addEventListener('click', function (e) {
            const btn = e.target.closest('[data-guide-tab]');
            if (!btn) return;
            state.panelTab = btn.getAttribute('data-guide-tab') || 'setup';
            renderPanel(state.data);
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && state.open) setPanelOpen(false);
        });
    }

    function registerDashboardWidget() {
        const dash = global.Birkly && global.Birkly.Dashboard;
        if (!dash || typeof dash.registerMount !== 'function') return;
        dash.registerMount('guideSetup', function (element, widget, data) {
            if (!element) return;
            if (!data) {
                element.innerHTML = '<p class="empty-state-text">' + escapeHtml(t('loading', 'Loading…')) + '</p>';
                return;
            }
            const done = data.checklist_done_count || 0;
            const total = data.checklist_total || 0;
            if (total > 0 && done >= total) {
                element.innerHTML = '<p class="u-text-sm u-fg-secondary">' +
                    escapeHtml(t('guide_dashboard_all_done', 'Setup checklist complete. Open Guide anytime for journeys and help.')) +
                    '</p><button type="button" class="btn btn-secondary btn-sm u-mt-2" data-guide-open-widget>' +
                    escapeHtml(t('guide_launcher_label', 'Guide')) + '</button>';
            } else {
                element.innerHTML =
                    '<p class="u-text-sm u-fg-secondary">' + escapeHtml(t('guide_dashboard_progress', '{done} of {total} done', { done: done, total: total })) + '</p>' +
                    '<ul class="guide-checklist guide-checklist--compact" role="list">' + renderChecklistHtml(data.checklist) + '</ul>' +
                    '<button type="button" class="btn btn-primary btn-sm u-mt-2" data-guide-open-widget>' +
                    escapeHtml(t('guide_open_panel', 'Open Guide')) + '</button>';
            }
            element.querySelector('[data-guide-open-widget]')?.addEventListener('click', function () {
                global.BirklyGuide.open();
            });
        });
    }

    function initAccountGuidePrefs() {
        if (document.body.getAttribute('data-admin-page') !== 'account') return;
        const form = document.getElementById('account-guide-form');
        if (!form) return;
        fetchProgress('account', '').then(function (data) {
            const disable = document.getElementById('account-guide-disable-attention');
            if (disable) {
                disable.checked = !!(data.prefs && data.prefs.disable_attention);
            }
        }).catch(function () { /* ignore */ });

        document.getElementById('account-guide-snooze')?.addEventListener('click', function () {
            const msg = document.getElementById('account-guide-message');
            postUpdate({ snooze_tips_days: 7 }).then(function () {
                if (msg) {
                    msg.hidden = false;
                    msg.textContent = t('guide_snooze_done', 'Guide tips snoozed for 7 days.');
                }
            }).catch(function (err) {
                if (msg) {
                    msg.hidden = false;
                    msg.textContent = err.message || 'Error';
                }
            });
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const disable = document.getElementById('account-guide-disable-attention');
            const msg = document.getElementById('account-guide-message');
            const reset = e.submitter && e.submitter.getAttribute('data-guide-reset') === '1';
            const payload = reset
                ? { reset_progress: true }
                : { disable_attention: !!(disable && disable.checked) };
            postUpdate(payload).then(function () {
                if (msg) {
                    msg.hidden = false;
                    msg.textContent = t(reset ? 'guide_reset_done' : 'guide_prefs_saved', reset ? 'Guide progress reset.' : 'Guide preferences saved.');
                }
            }).catch(function (err) {
                if (msg) {
                    msg.hidden = false;
                    msg.textContent = err.message || 'Error';
                }
            });
        });
    }

    function shouldRun() {
        const page = document.body.getAttribute('data-admin-page') || '';
        return page !== 'onboarding' && page !== 'login';
    }

    function init() {
        registerDashboardWidget();
        if (!shouldRun()) return;
        mountLauncher();
        refresh();
        initAccountGuidePrefs();
    }

    function openGuide(options) {
        let journeyId = null;
        let tab = null;
        if (typeof options === 'string') {
            journeyId = options;
            tab = 'journeys';
        } else if (options && typeof options === 'object') {
            journeyId = options.journeyId || null;
            tab = options.tab || (journeyId ? 'journeys' : null);
        }
        if (tab) {
            state.panelTab = tab;
        }
        if (journeyId) {
            state.pendingJourneyFocus = journeyId;
        }
        setPanelOpen(true);
        if (state.data) {
            renderPanel(state.data);
        }
        refresh();
    }

    global.BirklyGuide = {
        open: openGuide,
        refresh: refresh,
        fetchProgress: fetchProgress,
        afterOnboardingWelcomeClosed: afterOnboardingWelcomeClosed,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    const observer = new MutationObserver(function (mutations) {
        for (const m of mutations) {
            if (m.type === 'attributes' && (m.attributeName === 'data-admin-page' || m.attributeName === 'data-admin-tab')) {
                refresh();
                break;
            }
        }
    });
    observer.observe(document.body, { attributes: true });
}(typeof window !== 'undefined' ? window : global));
