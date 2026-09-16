/**
 * P87 — Admin Guide companion (launcher + panel).
 */
(function (global) {
    'use strict';

    let state = {
        open: false,
        data: null,
        area: '',
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

    async function fetchProgress() {
        const ctx = adminPageContext();
        const qs = new URLSearchParams({
            action: 'progress',
            page: ctx.page,
            tab: ctx.tab,
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

    function renderPanel(data) {
        const body = document.getElementById('guide-panel-body');
        if (!body || !data) return;

        const checklistHtml = (data.checklist || []).map(function (item) {
            const done = item.done ? ' is-done' : '';
            const mark = item.done ? '✓' : '○';
            const label = item.done
                ? escapeHtml(item.label || item.id)
                : '<a href="' + escapeHtml(item.href || '#') + '">' + escapeHtml(item.label || item.id) + '</a>';
            return '<li class="' + done + '"><span aria-hidden="true">' + mark + '</span><span>' + label + '</span></li>';
        }).join('');

        let tipHtml = '';
        if (data.tip) {
            const topic = data.tip.help_topic || 'getting-started';
            tipHtml = '<div class="guide-tip-block">' +
                '<h4>' + escapeHtml(tipTitle(data.tip)) + '</h4>' +
                '<p>' + escapeHtml(tipBody(data.tip)) + '</p>' +
                '<a class="btn btn-ghost btn-sm" href="' + escapeHtml(helpUrl(topic)) + '">' +
                escapeHtml(t('guide_learn_more', 'Learn more')) + '</a></div>';
        }

        body.innerHTML =
            '<p class="u-text-sm u-fg-secondary">' + escapeHtml(t('guide_panel_intro', 'Finish these steps to get your site live.')) + '</p>' +
            '<ul class="guide-checklist" role="list">' + checklistHtml + '</ul>' +
            tipHtml;
    }

    function applyProgress(data) {
        state.data = data;
        state.area = data.area || '';
        renderPanel(data);
        const showAttention = !!data.attention && !state.open;
        setLauncherAttention(showAttention);
        if (data.should_auto_open_panel) {
            setPanelOpen(true);
            postUpdate({ ack_first_visit: true }).catch(function () { /* ignore */ });
            if (data.tip && data.tip.area) {
                postUpdate({ mark_tip_seen: data.tip.area }).catch(function () { /* ignore */ });
            }
        }
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
            '</div><div class="guide-panel-body" id="guide-panel-body"></div>';
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
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && state.open) setPanelOpen(false);
        });
    }

    function shouldRun() {
        const page = document.body.getAttribute('data-admin-page') || '';
        return page !== 'onboarding' && page !== 'login';
    }

    function init() {
        if (!shouldRun()) return;
        mountLauncher();
        refresh();
    }

    global.BirklyGuide = {
        open: function () {
            setPanelOpen(true);
        },
        refresh: refresh,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Re-check attention when admin SPA-like navigation updates body dataset (best-effort).
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
