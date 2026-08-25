/**
 * Discovery admin shell (P67-M2) — SEO · Agent Guide · WebMCP packs.
 */
(function (global) {
    'use strict';

    var DISC_ZONES = ['seo', 'agent-guide', 'webmcp'];
    var LEGACY_ZONE_MAP = { tools: 'webmcp', publish: 'agent-guide', guide: 'agent-guide' };

    var state = {
        status: null,
        seo: null,
        dirty: false,
    };

    function $(sel, root) {
        return (root || document).querySelector(sel);
    }

    function apiUrl(path) {
        if (global.Birkly && typeof global.Birkly.url === 'function') {
            return global.Birkly.url(path);
        }
        return path;
    }

    function csrfToken() {
        return (global.Birkly && global.Birkly.csrf) || '';
    }

    function postJson(body) {
        return fetch(apiUrl('/api/discovery.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(Object.assign({ _csrf: csrfToken() }, body)),
        }).then(function (r) { return r.json(); });
    }

    function getJson() {
        return fetch(apiUrl('/api/discovery.php?action=get'), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); });
    }

    function showMessage(text, isError) {
        var el = $('#disc-message');
        if (!el) return;
        el.textContent = text;
        el.className = 'message' + (isError ? ' message-error' : ' message-success');
        el.hidden = false;
        setTimeout(function () { el.hidden = true; }, 4000);
    }

    function getInitialZone() {
        var params = new URLSearchParams(window.location.search);
        var zone = params.get('tab') || params.get('zone') || 'seo';
        if (LEGACY_ZONE_MAP[zone]) zone = LEGACY_ZONE_MAP[zone];
        return DISC_ZONES.indexOf(zone) >= 0 ? zone : 'seo';
    }

    function setZoneInUrl(zone) {
        var url = new URL(window.location.href);
        url.searchParams.set('tab', zone);
        url.searchParams.delete('zone');
        window.history.replaceState({}, '', url.toString());
    }

    function switchZone(zone) {
        if (DISC_ZONES.indexOf(zone) < 0) zone = 'seo';
        DISC_ZONES.forEach(function (z) {
            var tab = document.querySelector('[data-disc-zone="' + z + '"]');
            var panel = document.querySelector('[data-disc-panel="' + z + '"]');
            var active = z === zone;
            if (tab) {
                tab.classList.toggle('active', active);
                tab.setAttribute('aria-selected', active ? 'true' : 'false');
                tab.tabIndex = active ? 0 : -1;
            }
            if (panel) {
                panel.classList.toggle('active', active);
                panel.hidden = !active;
            }
        });
        setZoneInUrl(zone);
    }

    function initHubZones() {
        var tablist = $('#disc-hub-tablist');
        if (!tablist) return;
        tablist.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-disc-zone]');
            if (!btn) return;
            switchZone(btn.getAttribute('data-disc-zone'));
        });
        switchZone(getInitialZone());
    }

    function setTabDot(id, ok) {
        var dot = $('#disc-tab-dot-' + id);
        if (dot) dot.setAttribute('data-ok', ok ? '1' : '0');
    }

    function renderStatusStrip(data) {
        if (!data || !data.packs) return;
        Object.keys(data.packs).forEach(function (packId) {
            var pack = data.packs[packId];
            var badge = document.querySelector('.disc-pack-badge[data-pack="' + packId + '"] .disc-pack-label');
            if (badge) badge.textContent = pack.status_label || 'off';
            var dotId = packId === 'agent_guide' ? 'agent-guide' : packId;
            setTabDot(dotId, !!pack.live);
        });
        var hosting = data.hosting || {};
        var badge = $('#disc-hosting-badge');
        if (badge) {
            var mode = hosting.public_site_mode || 'none';
            badge.textContent = mode === 'project' ? 'Birkly-hosted site' :
                (mode === 'external' ? 'External site' : 'No public site');
        }
    }

    function renderPackToggles(data) {
        if (!data || !data.packs) return;
        var map = {
            seo: '#disc-seo-enabled',
            agent_guide: '#disc-agent-guide-enabled',
            webmcp: '#disc-webmcp-enabled',
        };
        var configMap = {
            seo: '#disc-seo-config',
            agent_guide: '#disc-agent-guide-config',
            webmcp: '#disc-webmcp-config',
        };
        Object.keys(map).forEach(function (packId) {
            var input = $(map[packId]);
            var config = $(configMap[packId]);
            var pack = data.packs[packId];
            if (input && pack) {
                input.checked = !!pack.enabled;
            }
            if (config) config.hidden = !(pack && pack.enabled);
        });

        var agStatus = $('#disc-agent-guide-status');
        if (agStatus && data.packs.agent_guide) {
            agStatus.textContent = data.packs.agent_guide.status_detail || '';
        }

        var seoPub = $('#disc-seo-publish-status');
        var seoPubBtn = $('#disc-seo-publish');
        var seoUnpubBtn = $('#disc-seo-unpublish');
        if (seoPub && data.packs.seo) {
            seoPub.textContent = data.packs.seo.live
                ? 'SEO is live on your public site.'
                : 'Publish to activate meta tags, sitemap, and robots directives.';
            if (seoPubBtn) seoPubBtn.hidden = !!data.packs.seo.live;
            if (seoUnpubBtn) seoUnpubBtn.hidden = !data.packs.seo.live;
        }
    }

    function renderVerifyChecks(checks) {
        var list = $('#disc-verify-checks');
        if (!list) return;
        list.innerHTML = '';
        (checks || []).forEach(function (check) {
            var li = document.createElement('li');
            var status = check.status || 'neutral';
            var pack = check.pack ? '[' + check.pack + '] ' : '';
            li.innerHTML = '<span class="disc-verify-status disc-verify-' + status + '">' + status + '</span> '
                + pack + '<strong>' + (check.label || '') + '</strong> — ' + (check.detail || '');
            list.appendChild(li);
        });
    }

    function load() {
        return getJson().then(function (res) {
            if (res.status !== 'ok') throw new Error(res.message || 'Load failed');
            state.status = res.data;
            renderStatusStrip(res.data);
            renderPackToggles(res.data);
            renderVerifyChecks(res.data.verify_checks);
            return res.data;
        });
    }

    function savePack(packId, patch) {
        return postJson({ action: 'save_pack', pack: packId, patch: patch }).then(function (res) {
            if (res.status !== 'ok') throw new Error(res.message || 'Save failed');
            state.status = res.data;
            renderStatusStrip(res.data);
            renderPackToggles(res.data);
            showMessage('Saved');
            return res.data;
        });
    }

    function bindPackToggles() {
        document.querySelectorAll('[data-pack]').forEach(function (input) {
            input.addEventListener('change', function () {
                var pack = input.getAttribute('data-pack');
                savePack(pack, { enabled: input.checked }).catch(function (e) {
                    showMessage(e.message || 'Could not save', true);
                    input.checked = !input.checked;
                });
            });
        });
    }

    function bindSeoActions() {
        var pub = $('#disc-seo-publish');
        var unpub = $('#disc-seo-unpublish');
        if (pub) {
            pub.addEventListener('click', function () {
                postJson({ action: 'publish_seo' }).then(function (res) {
                    if (res.status !== 'ok') throw new Error(res.message);
                    state.status = res.data;
                    renderStatusStrip(res.data);
                    renderPackToggles(res.data);
                    showMessage('SEO published');
                }).catch(function (e) { showMessage(e.message, true); });
            });
        }
        if (unpub) {
            unpub.addEventListener('click', function () {
                postJson({ action: 'unpublish_seo' }).then(function (res) {
                    if (res.status !== 'ok') throw new Error(res.message);
                    state.status = res.data;
                    renderStatusStrip(res.data);
                    renderPackToggles(res.data);
                    showMessage('SEO unpublished');
                }).catch(function (e) { showMessage(e.message, true); });
            });
        }
    }

    function bindZoneLinks() {
        document.querySelectorAll('[data-zone-link]').forEach(function (a) {
            var zone = a.getAttribute('data-zone-link');
            if (!zone) return;
            try {
                var url = new URL(a.href, window.location.origin);
                url.searchParams.set('zone', zone);
                a.href = url.pathname + url.search;
            } catch (e) { /* keep */ }
        });
    }

    function init() {
        if (!document.querySelector('.disc-hub')) return;
        initHubZones();
        bindPackToggles();
        bindSeoActions();
        bindZoneLinks();
        var refresh = $('#disc-verify-refresh');
        if (refresh) refresh.addEventListener('click', function () { load().catch(function () {}); });
        load().catch(function (e) { showMessage(e.message || 'Could not load Discovery', true); });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    global.initDiscovery = init;
})(window);
