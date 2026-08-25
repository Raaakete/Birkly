/**
 * Discovery admin (P67) — SEO · Agent Guide · WebMCP packs.
 */
(function (global) {
    'use strict';

    var DISC_ZONES = ['seo', 'agent-guide', 'webmcp'];
    var LEGACY_ZONE_MAP = { tools: 'webmcp', publish: 'agent-guide', guide: 'agent-guide' };
    var SEO_SURFACES = [
        { key: 'meta_tags', label: 'Meta tags (title, description)' },
        { key: 'canonical', label: 'Canonical URLs' },
        { key: 'open_graph', label: 'Open Graph / social cards' },
        { key: 'json_ld_website', label: 'JSON-LD WebSite schema' },
        { key: 'json_ld_article', label: 'JSON-LD Article on detail pages' },
        { key: 'sitemap', label: 'Dynamic sitemap.xml' },
        { key: 'robots_sitemap_line', label: 'robots.txt Sitemap: line' },
        { key: 'indexnow', label: 'IndexNow ping on publish' },
    ];

    var state = {
        status: null,
        seo: null,
        seoBaseline: null,
        activeZone: 'seo',
        apReady: false,
    };

    function toast(msg, type) {
        if (global.BirklyFeedback && global.BirklyFeedback.toast) {
            global.BirklyFeedback.toast(msg, type || 'info');
        }
    }

    function showMessage(text, isError) {
        toast(text, isError ? 'error' : 'success');
    }

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
        if (global.Birkly && global.Birkly.csrf) {
            return global.Birkly.csrf;
        }
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') || '' : '';
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

    function mapDiscZoneToAp(zone) {
        if (zone === 'webmcp') return 'tools';
        if (zone === 'agent-guide') return 'guide';
        return null;
    }

    function syncApEmbed(zone) {
        var embed = $('#disc-ap-embed');
        var sticky = document.querySelector('#disc-ap-embed .main-actions-bar--sticky');
        if (!embed) return;

        var agOn = state.status && state.status.packs && state.status.packs.agent_guide && state.status.packs.agent_guide.enabled;
        var wmOn = state.status && state.status.packs && state.status.packs.webmcp && state.status.packs.webmcp.enabled;
        var showEmbed = (zone === 'agent-guide' && agOn) || (zone === 'webmcp' && wmOn);

        embed.hidden = !showEmbed;
        // Agent Guide publish bar only on the Agent Guide tab — WebMCP saves inline via its own API.
        if (sticky) sticky.hidden = !showEmbed || zone !== 'agent-guide';

        if (!showEmbed || !state.apReady) return;

        var apZone = mapDiscZoneToAp(zone);
        if (apZone && typeof global.switchApZone === 'function') {
            global.switchApZone(apZone, { updateUrl: false });
        }

        var guidePanel = $('#ap-panel-guide');
        var toolsPanel = $('#ap-panel-tools');
        var publishPanel = $('#ap-panel-publish');
        if (zone === 'agent-guide') {
            if (guidePanel) guidePanel.hidden = false;
            if (publishPanel) publishPanel.hidden = true;
            if (toolsPanel) toolsPanel.hidden = true;
        } else if (zone === 'webmcp') {
            if (toolsPanel) toolsPanel.hidden = false;
            if (guidePanel) guidePanel.hidden = true;
            if (publishPanel) publishPanel.hidden = true;
        }
    }

    function switchZone(zone) {
        if (DISC_ZONES.indexOf(zone) < 0) zone = 'seo';
        state.activeZone = zone;
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
        syncApEmbed(zone);
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

    function updatePackTabDots(data) {
        if (!data || !data.packs) return;
        Object.keys(data.packs).forEach(function (packId) {
            var pack = data.packs[packId];
            var dotId = packId === 'agent_guide' ? 'agent-guide' : packId;
            setTabDot(dotId, !!pack.live);
        });
    }

    function renderSeoSurfaces(seo) {
        var box = $('#disc-seo-surfaces');
        if (!box) return;
        var surfaces = (seo && seo.delivery && seo.delivery.surfaces) || {};
        box.innerHTML = '';
        SEO_SURFACES.forEach(function (item) {
            var label = document.createElement('label');
            label.className = 'checkbox-label u-block u-mb-2';
            var checked = surfaces[item.key] !== false;
            label.innerHTML = '<input type="checkbox" data-seo-surface="' + item.key + '"' + (checked ? ' checked' : '') + '> ' + item.label;
            box.appendChild(label);
        });
    }

    function collectSeoPayload() {
        var seo = Object.assign({}, state.seo || {});
        seo.defaults = seo.defaults || {};
        seo.defaults.description = ($('#disc-seo-description') || {}).value || '';
        seo.defaults.title_suffix = ($('#disc-seo-title-suffix') || {}).value || '';
        seo.delivery = seo.delivery || { mode: 'auto_inject', surfaces: {} };
        seo.delivery.mode = ($('#disc-seo-delivery-mode') || {}).value || 'auto_inject';
        seo.delivery.surfaces = seo.delivery.surfaces || {};
        document.querySelectorAll('[data-seo-surface]').forEach(function (input) {
            seo.delivery.surfaces[input.getAttribute('data-seo-surface')] = input.checked;
        });
        return seo;
    }

    function seoFormFingerprint() {
        return JSON.stringify(collectSeoPayload());
    }

    function markSeoBaseline() {
        state.seoBaseline = seoFormFingerprint();
        updateSeoStickyBar();
    }

    function isSeoDirty() {
        if (state.seoBaseline === null) return false;
        return seoFormFingerprint() !== state.seoBaseline;
    }

    function updateSeoStickyBar() {
        var live = !!(state.status && state.status.packs && state.status.packs.seo && state.status.packs.seo.live);
        var dirty = isSeoDirty();
        var pubBtn = $('#disc-seo-publish-btn');
        var unpubBtn = $('#disc-seo-unpublish');
        var saveBtn = $('#disc-seo-save');
        var msgEl = $('#disc-seo-publish-status');

        if (pubBtn) {
            pubBtn.textContent = live ? 'Publish updates' : 'Publish';
            pubBtn.disabled = live && !dirty;
        }
        if (unpubBtn) unpubBtn.hidden = !live;
        if (saveBtn) saveBtn.disabled = live && !dirty;

        if (msgEl) {
            if (live && dirty) {
                msgEl.textContent = 'You have unpublished changes.';
                msgEl.className = 'main-actions-message message message-warning';
                msgEl.hidden = false;
            } else if (live) {
                msgEl.textContent = 'SEO is live on your public site.';
                msgEl.className = 'main-actions-message message message-success';
                msgEl.hidden = false;
            } else {
                msgEl.textContent = 'Publish to activate meta tags, sitemap, and robots directives.';
                msgEl.className = 'main-actions-message message';
                msgEl.hidden = false;
            }
        }
    }

    function renderSeoForm(data) {
        var seo = data.seo || {};
        state.seo = seo;
        var desc = $('#disc-seo-description');
        var suffix = $('#disc-seo-title-suffix');
        var mode = $('#disc-seo-delivery-mode');
        if (desc) desc.value = (seo.defaults && seo.defaults.description) || '';
        if (suffix) suffix.value = (seo.defaults && seo.defaults.title_suffix) || '';
        if (mode) mode.value = (seo.delivery && seo.delivery.mode) || 'auto_inject';
        renderSeoSurfaces(seo);
        markSeoBaseline();
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

        renderSeoForm(data);
        updateSeoStickyBar();

        syncApEmbed(state.activeZone);
    }

    function renderVerifyChecks(checks, lastRun) {
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
        var lr = $('#disc-verify-last-run');
        if (lr) {
            if (lastRun) {
                lr.textContent = 'Last run: ' + lastRun;
                lr.hidden = false;
            } else {
                lr.hidden = true;
            }
        }
    }

    function load() {
        return getJson().then(function (res) {
            if (res.status !== 'ok') throw new Error(res.message || 'Load failed');
            state.status = res.data;
            updatePackTabDots(res.data);
            renderPackToggles(res.data);
            renderVerifyChecks(res.data.verify_checks, res.data.verify_last_run);
            return res.data;
        });
    }

    function savePack(packId, patch) {
        return postJson({ action: 'save_pack', pack: packId, patch: patch }).then(function (res) {
            if (res.status !== 'ok') throw new Error(res.message || 'Save failed');
            state.status = res.data;
            updatePackTabDots(res.data);
            renderPackToggles(res.data);
            showMessage('Saved');
            return res.data;
        });
    }

    function bindPackToggles() {
        document.querySelectorAll('.disc-hub input[type="checkbox"][data-pack]').forEach(function (input) {
            if (input.dataset.discBound === '1') return;
            input.dataset.discBound = '1';
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
        var save = $('#disc-seo-save');
        if (save) {
            save.addEventListener('click', function () {
                postJson({ action: 'save_seo', config: collectSeoPayload() }).then(function (res) {
                    if (res.status !== 'ok') throw new Error(res.message);
                    state.status = res.data.status || state.status;
                    state.seo = res.data.seo;
                    renderPackToggles(state.status);
                    markSeoBaseline();
                    showMessage('SEO settings saved');
                }).catch(function (e) { showMessage(e.message, true); });
            });
        }

        var gen = $('#disc-seo-generate');
        if (gen) {
            gen.addEventListener('click', function () {
                postJson({ action: 'seo_generate_defaults' }).then(function (res) {
                    if (res.status !== 'ok') throw new Error(res.message);
                    var proposal = res.data && res.data.seo && res.data.seo.defaults;
                    if (!proposal) return;
                    if ($('#disc-seo-description')) $('#disc-seo-description').value = proposal.description || '';
                    if ($('#disc-seo-title-suffix')) $('#disc-seo-title-suffix').value = proposal.title_suffix || '';
                    updateSeoStickyBar();
                    showMessage('Proposed defaults — review and save');
                }).catch(function (e) { showMessage(e.message, true); });
            });
        }

        function publishSeoFlow(successMsg) {
            return postJson({ action: 'save_seo', config: collectSeoPayload() }).then(function () {
                return postJson({ action: 'publish_seo' });
            }).then(function (res) {
                if (res.status !== 'ok') throw new Error(res.message);
                state.status = res.data;
                updatePackTabDots(res.data);
                renderPackToggles(res.data);
                markSeoBaseline();
                showMessage(successMsg);
            });
        }

        var pub = $('#disc-seo-publish-btn');
        var unpub = $('#disc-seo-unpublish');
        if (pub) {
            pub.addEventListener('click', function () {
                var live = !!(state.status && state.status.packs && state.status.packs.seo && state.status.packs.seo.live);
                publishSeoFlow(live ? 'SEO updates published' : 'SEO published')
                    .catch(function (e) { showMessage(e.message, true); });
            });
        }
        if (unpub) {
            unpub.addEventListener('click', function () {
                postJson({ action: 'unpublish_seo' }).then(function (res) {
                    if (res.status !== 'ok') throw new Error(res.message);
                    state.status = res.data;
                    updatePackTabDots(res.data);
                    renderPackToggles(res.data);
                    showMessage('SEO unpublished');
                }).catch(function (e) { showMessage(e.message, true); });
            });
        }

        var seoConfig = $('#disc-seo-config');
        if (seoConfig) {
            seoConfig.addEventListener('input', function (e) {
                if (e.target.matches('#disc-seo-description, #disc-seo-title-suffix, #disc-seo-delivery-mode, [data-seo-surface]')) {
                    updateSeoStickyBar();
                }
            });
            seoConfig.addEventListener('change', function (e) {
                if (e.target.matches('#disc-seo-delivery-mode, [data-seo-surface]')) {
                    updateSeoStickyBar();
                }
            });
        }
    }

    function bindVerify() {
        var refresh = $('#disc-verify-refresh');
        if (refresh) {
            refresh.addEventListener('click', function () {
                postJson({ action: 'verify' }).then(function (res) {
                    if (res.status !== 'ok') throw new Error(res.message);
                    state.status = res.data;
                    renderVerifyChecks(res.data.verify_checks, res.data.verify_last_run);
                    showMessage('Verify checklist updated');
                }).catch(function (e) { showMessage(e.message, true); });
            });
        }
    }

    function initApEmbed() {
        if (!document.getElementById('disc-ap-embed')) return;
        if (typeof global.initAiPresence === 'function') {
            global.initAiPresence({ discoveryEmbed: true });
            state.apReady = true;
            syncApEmbed(state.activeZone);
        }
    }

    function init() {
        if (!document.querySelector('.disc-hub')) return;
        initHubZones();
        bindPackToggles();
        bindSeoActions();
        bindVerify();
        window.addEventListener('birkly:discovery-refresh', function () {
            load().catch(function () { /* ignore */ });
        });
        load().then(function () {
            initApEmbed();
        }).catch(function (e) {
            showMessage(e.message || 'Could not load Discovery', true);
            initApEmbed();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    global.initDiscovery = init;
})(window);
