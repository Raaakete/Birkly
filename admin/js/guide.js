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

    function icon(name, size) {
        if (global.Birkly && typeof global.Birkly.icon === 'function') {
            return global.Birkly.icon(name, { size: size || 16 });
        }
        return '';
    }

    function renderChecklistHtml(checklist) {
        return (checklist || []).map(function (item, index) {
            const done = item.done ? ' is-done' : ' is-pending';
            const step = index + 1;
            const label = item.done
                ? escapeHtml(item.label || item.id)
                : '<a class="guide-checklist__link" href="' + escapeHtml(item.href || '#') + '">' +
                    escapeHtml(item.label || item.id) + '</a>';
            const marker = item.done
                ? icon('check', 14)
                : '<span class="guide-checklist__step-num">' + step + '</span>';
            return '<li class="guide-checklist__item' + done + '">' +
                '<span class="guide-checklist__marker" aria-hidden="true">' + marker + '</span>' +
                '<div class="guide-checklist__copy">' + label + '</div></li>';
        }).join('');
    }

    function renderSetupProgress(data) {
        const done = data.checklist_done_count || 0;
        const total = data.checklist_total || 0;
        if (total <= 0) return '';
        const pct = Math.min(100, Math.round((done / total) * 100));
        return '<div class="guide-setup-progress" role="status">' +
            '<div class="guide-setup-progress__meta">' +
            '<span class="guide-setup-progress__label">' + escapeHtml(t('guide_setup_progress', 'Setup progress')) + '</span>' +
            '<span class="guide-setup-progress__count">' + done + ' / ' + total + '</span>' +
            '</div>' +
            '<div class="guide-setup-progress__track" aria-hidden="true">' +
            '<div class="guide-setup-progress__fill" style="width:' + pct + '%"></div></div></div>';
    }

    function renderJourneysHtml(journeys) {
        return (journeys || []).map(function (journey) {
            const doneClass = journey.completed ? ' is-done' : '';
            const steps = (journey.steps || []).map(function (step, idx) {
                const stepNum = idx + 1;
                return '<li class="guide-journey-step">' +
                    '<div class="guide-journey-step__head">' +
                    '<span class="guide-journey-step__index">' + stepNum + '</span>' +
                    '<p class="guide-journey-teach">' + escapeHtml(step.teach || '') + '</p></div>' +
                    '<div class="guide-journey-step__actions">' +
                    (step.href ? '<a class="btn btn-secondary btn-sm" href="' + escapeHtml(step.href) + '"' +
                        (step.open_in_new_tab ? ' target="_blank" rel="noopener noreferrer"' : '') + '>' +
                        escapeHtml(t('guide_journey_go', 'Go there')) + '</a>' : '') +
                    '<a class="btn btn-ghost btn-sm" href="' + escapeHtml(helpUrl(step.help_topic || 'getting-started')) + '">' +
                        escapeHtml(t('guide_learn_more', 'Learn more')) + '</a></div></li>';
            }).join('');
            const completeBtn = journey.completed ? '' :
                '<div class="guide-journey-footer">' +
                '<button type="button" class="btn btn-ghost btn-sm guide-journey-done" data-journey-id="' + escapeHtml(journey.id) + '">' +
                escapeHtml(t('guide_journey_mark_done', 'Mark journey done')) + '</button></div>';
            const status = journey.completed
                ? '<span class="guide-journey-badge is-done">' + escapeHtml(t('guide_journey_done_badge', 'Done')) + '</span>'
                : '<span class="guide-journey-badge">' + escapeHtml(t('guide_journey_active_badge', 'In progress')) + '</span>';
            return '<details class="guide-journey' + doneClass + '" data-journey-id="' + escapeHtml(journey.id) + '"' + (journey.completed ? '' : ' open') + '>' +
                '<summary class="guide-journey-title">' +
                '<span class="guide-journey-title__text">' + escapeHtml(journey.title || journey.id) + '</span>' + status +
                '</summary>' +
                '<ol class="guide-journey-steps">' + steps + '</ol>' + completeBtn + '</details>';
        }).join('');
    }

    function renderAiConnectionNudge(data) {
        if (!data || !data.show_ai_connection_nudge) {
            return '';
        }
        const href = data.ai_connection_href || '';
        const action = href
            ? '<a class="guide-concept-card__link" href="' + escapeHtml(href) + '">' +
                escapeHtml(t('guide_ai_nudge_action', 'Set up in Settings → AI')) + ' →</a>'
            : '';
        return '<section class="guide-ai-nudge" role="note">' +
            '<h4 class="guide-ai-nudge__title">' + escapeHtml(t('guide_ai_nudge_title', 'Connect AI for advanced guidance')) + '</h4>' +
            '<p class="guide-ai-nudge__body">' + escapeHtml(t('guide_ai_nudge_body', 'Add an AI connection in Settings so the assistant can give deeper, context-aware help while you work.')) + '</p>' +
            action + '</section>';
    }

    function renderConceptsHtml(concepts) {
        return (concepts || []).map(function (c) {
            const goLink = c.href
                ? '<a class="guide-concept-card__link" href="' + escapeHtml(c.href) + '">' +
                    escapeHtml(t('guide_journey_go', 'Go there')) + ' →</a>'
                : '';
            const learnLink = '<a class="guide-concept-card__link" href="' + escapeHtml(helpUrl(c.help_topic || 'getting-started')) + '">' +
                escapeHtml(t('guide_learn_more', 'Learn more')) + ' →</a>';
            return '<article class="guide-concept-card">' +
                '<h4 class="guide-concept-card__title">' + escapeHtml(c.title || '') + '</h4>' +
                '<p class="guide-concept-card__body">' + escapeHtml(c.body || '') + '</p>' +
                '<div class="guide-concept-card__actions">' + goLink + learnLink + '</div></article>';
        }).join('');
    }

    function renderPanelBody(data) {
        const tab = state.panelTab;
        const nudge = renderAiConnectionNudge(data);
        let inner = '';
        if (tab === 'setup') {
            let tipHtml = '';
            if (data.tip) {
                const topic = data.tip.help_topic || 'getting-started';
                tipHtml = '<section class="guide-tip-block guide-tip-block--lead">' +
                    '<p class="guide-section-eyebrow">' + escapeHtml(t('guide_tip_eyebrow', 'Tip for this page')) + '</p>' +
                    '<h4 class="guide-tip-block__title">' + escapeHtml(tipTitle(data.tip)) + '</h4>' +
                    '<p class="guide-tip-block__body">' + escapeHtml(tipBody(data.tip)) + '</p>' +
                    '<a class="guide-concept-card__link" href="' + escapeHtml(helpUrl(topic)) + '">' +
                    escapeHtml(t('guide_learn_more', 'Learn more')) + ' →</a></section>';
            }
            inner = nudge + tipHtml +
                '<p class="guide-section-lede">' + escapeHtml(t('guide_panel_intro', 'Finish these steps to get your site live.')) + '</p>' +
                renderSetupProgress(data) +
                '<ul class="guide-checklist" role="list">' + renderChecklistHtml(data.checklist) + '</ul>';
        } else if (tab === 'journeys') {
            inner = nudge +
                '<p class="guide-section-lede">' + escapeHtml(t('guide_journeys_intro', 'Short paths with one action per step.')) + '</p>' +
                '<div class="guide-journey-list">' + renderJourneysHtml(data.journeys) + '</div>';
        } else {
            inner = nudge +
                '<p class="guide-section-lede guide-section-lede--concepts">' +
                escapeHtml(t('guide_concepts_intro', 'Quick orientation for Content, Media, Discovery, and key Settings tabs.')) + '</p>' +
                '<div class="guide-concepts guide-concepts--expanded">' + renderConceptsHtml(data.concepts) + '</div>';
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

    function isOnboardingWelcomeBlocking() {
        const modal = document.getElementById('onboarding-complete-modal');
        if (!modal) {
            return false;
        }
        if (modal.dataset.showWelcome === '1') {
            return true;
        }
        return modal.style.display === 'flex';
    }

    function runFirstVisitAutoOpen(data) {
        if (!data || !data.should_auto_open_panel || isOnboardingWelcomeBlocking()) {
            return;
        }
        setPanelOpen(true);
        postUpdate({ ack_first_visit: true }).catch(function () { /* ignore */ });
        if (data.tip && data.tip.area) {
            postUpdate({ mark_tip_seen: data.tip.area }).catch(function () { /* ignore */ });
        }
    }

    function applyProgress(data) {
        state.data = data;
        state.area = data.area || '';
        renderPanel(data);
        const showAttention = !!data.attention && !state.open;
        setLauncherAttention(showAttention);
        runFirstVisitAutoOpen(data);
        global.dispatchEvent(new CustomEvent('birkly:guide-progress', { detail: data }));
    }

    function afterOnboardingWelcomeClosed() {
        fetchProgress().then(function (data) {
            applyProgress(data);
            if (data.should_auto_open_panel || data.guide_auto_open_pending) {
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
            '<span class="guide-launcher-btn__halo" aria-hidden="true"></span>' +
            '<span class="guide-launcher-btn__focus-ring" aria-hidden="true"></span>' +
            '<span class="guide-launcher-btn__inner" aria-hidden="true">' +
            icon('map-pin', 20) +
            '</span></button>';

        const panel = document.createElement('div');
        panel.id = 'guide-panel';
        panel.className = 'guide-panel';
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-label', t('guide_panel_title', 'Guide'));
        panel.innerHTML =
            '<div class="guide-panel-header">' +
            '<div class="guide-panel-brand">' +
            '<span class="guide-panel-brand__icon" aria-hidden="true">' + icon('map-pin', 18) + '</span>' +
            '<div class="guide-panel-brand__text">' +
            '<h2 class="guide-panel-title">' + escapeHtml(t('guide_panel_title', 'Guide')) + '</h2>' +
            '<p class="guide-panel-subtitle">' + escapeHtml(t('guide_panel_subtitle', 'Setup, journeys, and concepts')) + '</p>' +
            '</div></div>' +
            '<button type="button" class="btn btn-icon guide-panel-close" id="guide-panel-close" aria-label="' +
            escapeHtml(t('close', 'Close')) + '">' + icon('x', 18) + '</button>' +
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

    function startGuideChrome() {
        if (!shouldRun()) return;
        mountLauncher();
        if (!document.getElementById('guide-launcher-btn')) {
            return false;
        }
        refresh();
        initAccountGuidePrefs();
        return true;
    }

    function init() {
        if (startGuideChrome()) {
            return;
        }
        if (!shouldRun()) return;
        document.addEventListener('birkly:assist-chrome-ready', function () {
            startGuideChrome();
        }, { once: true });
        var attempts = 0;
        (function retryMount() {
            if (startGuideChrome() || attempts++ > 50) {
                return;
            }
            setTimeout(retryMount, 50);
        })();
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
