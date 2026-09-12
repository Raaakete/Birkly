/**
 * Discovery admin (P67) — SEO · Agent Guide · WebMCP packs.
 */
(function (global) {
    'use strict';

    var DISC_ZONES = ['seo', 'agent-guide', 'webmcp'];
    var LEGACY_ZONE_MAP = { tools: 'webmcp', publish: 'agent-guide', guide: 'agent-guide' };
    var SEO_SURFACE_GROUPS = [
        {
            labelKey: 'discovery_seo_surfaces_onpage',
            label: 'On-page tags',
            items: [
                { key: 'meta_tags', labelKey: 'discovery_seo_surface_meta_tags', label: 'Meta tags (title, description)' },
                { key: 'canonical', labelKey: 'discovery_seo_surface_canonical', label: 'Canonical URLs' },
                { key: 'open_graph', labelKey: 'discovery_seo_surface_open_graph', label: 'Open Graph / social cards' },
                { key: 'twitter_cards', labelKey: 'discovery_seo_surface_twitter_cards', label: 'Twitter / X cards' },
            ],
        },
        {
            labelKey: 'discovery_seo_surfaces_structured',
            label: 'Structured data (JSON-LD)',
            items: [
                { key: 'json_ld_website', labelKey: 'discovery_seo_surface_json_ld_website', label: 'WebSite schema' },
                { key: 'json_ld_organization', labelKey: 'discovery_seo_surface_json_ld_organization', label: 'Organization schema' },
                { key: 'json_ld_article', labelKey: 'discovery_seo_surface_json_ld_article', label: 'Article on detail pages' },
            ],
        },
        {
            labelKey: 'discovery_seo_surfaces_crawl',
            label: 'Crawling & discovery',
            items: [
                { key: 'sitemap', labelKey: 'discovery_seo_surface_sitemap', label: 'Dynamic sitemap.xml' },
                { key: 'robots_sitemap_line', labelKey: 'discovery_seo_surface_robots_sitemap', label: 'robots.txt Sitemap: line' },
                { key: 'indexnow', labelKey: 'discovery_seo_surface_indexnow', label: 'IndexNow ping on publish' },
            ],
        },
    ];

    var state = {
        status: null,
        seo: null,
        seoBaseline: null,
        urlMapRows: [],
        urlMapFilter: 'all',
        activeBindingId: null,
        activeZone: 'seo',
        apReady: false,
    };

    function t(key, fallback, params) {
        var text;
        if (global.Birkly && global.Birkly.i18n && typeof global.Birkly.i18n.t === 'function') {
            text = global.Birkly.i18n.t('admin.' + key, fallback);
        } else {
            text = fallback || key;
        }
        if (params && typeof params === 'object') {
            Object.keys(params).forEach(function (name) {
                text = text.split('{' + name + '}').join(String(params[name]));
            });
        }
        return text;
    }

    function formatDateTime(iso) {
        if (!iso) return '—';
        try {
            return new Date(iso).toLocaleString();
        } catch (e) {
            return iso;
        }
    }

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
        SEO_SURFACE_GROUPS.forEach(function (group) {
            var heading = document.createElement('h4');
            heading.className = 'disc-surfaces-group-title u-m-0 u-mb-2 u-text-sm u-weight-semibold';
            heading.textContent = t(group.labelKey, group.label);
            box.appendChild(heading);
            group.items.forEach(function (item) {
                var label = document.createElement('label');
                label.className = 'checkbox-label u-block u-mb-2';
                var checked = surfaces[item.key] !== false;
                label.innerHTML = '<input type="checkbox" data-seo-surface="' + item.key + '"' + (checked ? ' checked' : '') + '> ' + escapeHtml(t(item.labelKey, item.label));
                box.appendChild(label);
            });
        });
    }

    function buildSeoPreviewTitle() {
        var siteTitle = ($('#disc-seo-site-title') || {}).value || '';
        var suffix = ($('#disc-seo-title-suffix') || {}).value || '';
        var title = siteTitle.trim() || t('discovery_seo_preview_site_title_fallback', 'Site title');
        if (suffix && title.indexOf(suffix) < 0) {
            title += suffix;
        }
        return title;
    }

    function renderSeoPreviews() {
        var title = buildSeoPreviewTitle();
        var desc = (($('#disc-seo-description') || {}).value || '').trim() || t('discovery_seo_preview_meta_desc_fallback', 'Meta description preview');
        var image = (($('#disc-seo-og-image') || {}).value || '').trim();
        var siteHost = 'yoursite.com';
        if (state.status && state.status.hosting && state.status.hosting.public_site_url) {
            try {
                siteHost = new URL(state.status.hosting.public_site_url).host || siteHost;
            } catch (e) { /* ignore */ }
        }

        var serpTitle = $('#disc-seo-serp-title');
        var serpUrl = $('#disc-seo-serp-url');
        var serpDesc = $('#disc-seo-serp-desc');
        if (serpTitle) serpTitle.textContent = title;
        if (serpUrl) serpUrl.textContent = siteHost;
        if (serpDesc) serpDesc.textContent = desc;

        var socialSite = $('#disc-seo-social-site');
        var socialTitle = $('#disc-seo-social-title');
        var socialDesc = $('#disc-seo-social-desc');
        var socialImage = $('#disc-seo-social-image');
        if (socialSite) socialSite.textContent = siteHost;
        if (socialTitle) socialTitle.textContent = title;
        if (socialDesc) socialDesc.textContent = desc;
        if (socialImage) {
            if (image) {
                socialImage.hidden = false;
                socialImage.style.backgroundImage = 'url("' + image.replace(/"/g, '\\"') + '")';
            } else {
                socialImage.hidden = true;
                socialImage.style.backgroundImage = '';
            }
        }
    }

    function collectSeoPayload() {
        var seo = Object.assign({}, state.seo || {});
        seo.defaults = seo.defaults || {};
        seo.defaults.site_title = ($('#disc-seo-site-title') || {}).value || '';
        seo.defaults.description = ($('#disc-seo-description') || {}).value || '';
        seo.defaults.title_suffix = ($('#disc-seo-title-suffix') || {}).value || '';
        seo.defaults.og_image = ($('#disc-seo-og-image') || {}).value || '';
        seo.defaults.twitter_card = ($('#disc-seo-twitter-card') || {}).value || 'summary_large_image';
        seo.advanced = seo.advanced || {};
        seo.advanced.robots = ($('#disc-seo-robots') || {}).value || 'index,follow';
        seo.advanced.google_site_verification = ($('#disc-seo-google-verify') || {}).value || '';
        seo.advanced.bing_site_verification = ($('#disc-seo-bing-verify') || {}).value || '';
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
        var msgEl = $('#disc-seo-publish-status');
        var publishedAt = state.seo && state.seo.published_at;

        if (pubBtn) {
            pubBtn.disabled = live && !dirty;
            pubBtn.textContent = live && dirty
                ? t('discovery_publish_updates', 'Publish updates')
                : t('discovery_publish', 'Publish');
        }
        if (unpubBtn) unpubBtn.hidden = !live;

        if (msgEl) {
            if (!live) {
                msgEl.hidden = true;
                msgEl.textContent = '';
                msgEl.className = 'main-actions-message message';
            } else if (dirty) {
                msgEl.hidden = false;
                msgEl.textContent = t('ag_sticky_unpublished', 'You have unpublished changes.');
                msgEl.className = 'main-actions-message message message-warning';
            } else {
                msgEl.hidden = false;
                msgEl.textContent = t('ag_sticky_published', 'Published at {time} — no unpublished changes.')
                    .replace('{time}', formatDateTime(publishedAt));
                msgEl.className = 'main-actions-message message message-success';
            }
        }
    }

    function renderSeoForm(data) {
        var seo = data.seo || {};
        state.seo = seo;
        var siteTitle = $('#disc-seo-site-title');
        var desc = $('#disc-seo-description');
        var suffix = $('#disc-seo-title-suffix');
        var ogImage = $('#disc-seo-og-image');
        var twitterCard = $('#disc-seo-twitter-card');
        var robots = $('#disc-seo-robots');
        var googleVerify = $('#disc-seo-google-verify');
        var bingVerify = $('#disc-seo-bing-verify');
        var mode = $('#disc-seo-delivery-mode');
        if (siteTitle) siteTitle.value = (seo.defaults && seo.defaults.site_title) || '';
        if (desc) desc.value = (seo.defaults && seo.defaults.description) || '';
        if (suffix) suffix.value = (seo.defaults && seo.defaults.title_suffix) || '';
        if (ogImage) ogImage.value = (seo.defaults && seo.defaults.og_image) || '';
        if (twitterCard) twitterCard.value = (seo.defaults && seo.defaults.twitter_card) || 'summary_large_image';
        if (robots) robots.value = (seo.advanced && seo.advanced.robots) || 'index,follow';
        if (googleVerify) googleVerify.value = (seo.advanced && seo.advanced.google_site_verification) || '';
        if (bingVerify) bingVerify.value = (seo.advanced && seo.advanced.bing_site_verification) || '';
        if (mode) mode.value = (seo.delivery && seo.delivery.mode) || 'auto_inject';
        renderSeoSurfaces(seo);
        renderSeoPreviews();
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
        renderUrlMap(data.seo || state.seo);
        renderDetailBindings(data.seo || state.seo);
        updateUrlMapToolbar();

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
                lr.textContent = t('discovery_js_last_run', 'Last run: {at}', { at: lastRun });
                lr.hidden = false;
            } else {
                lr.hidden = true;
            }
        }
    }

    function load() {
        return getJson().then(function (res) {
            if (res.status !== 'ok') throw new Error(res.message || t('discovery_js_load_failed', 'Load failed'));
            state.status = res.data;
            updatePackTabDots(res.data);
            renderPackToggles(res.data);
            renderVerifyChecks(res.data.verify_checks, res.data.verify_last_run);
            return res.data;
        });
    }

    function savePack(packId, patch) {
        return postJson({ action: 'save_pack', pack: packId, patch: patch }).then(function (res) {
            if (res.status !== 'ok') throw new Error(res.message || t('discovery_js_save_failed', 'Save failed'));
            state.status = res.data;
            updatePackTabDots(res.data);
            renderPackToggles(res.data);
            showMessage(t('discovery_js_saved', 'Saved'));
            return res.data;
        });
    }

    function countRowsByType(rows, type) {
        return (rows || []).filter(function (row) { return row.type === type; }).length;
    }

    function formatScanSummaryMessage(summary, rows) {
        var pages = summary && summary.pages != null ? summary.pages : countRowsByType(rows, 'page');
        var details = summary && summary.detail_urls != null ? summary.detail_urls : countRowsByType(rows, 'detail');
        var templates = summary && summary.templates != null ? summary.templates : 0;
        var parts = [t('discovery_js_scan_found_pages', 'Found {n} main page(s)', { n: pages })];
        if (details > 0) {
            parts.push(t('discovery_js_scan_posts_from_collections', '{n} post(s) from collections', { n: details }));
        }
        if (templates > 0) {
            parts.push(t('discovery_js_scan_templates_detected', '{n} repeating template(s) detected', { n: templates }));
        }
        return parts.join(' · ');
    }

    function rowTypeLabel(type) {
        if (type === 'page') return t('discovery_seo_type_page', 'Page');
        if (type === 'detail') return t('discovery_seo_type_detail', 'Collection entry');
        if (type === 'redirect') return t('discovery_seo_type_redirect', 'Redirect');
        return type || '';
    }

    function getUrlMapRows(seo) {
        return state.urlMapRows.length ? state.urlMapRows : buildUrlMapRowsFromSeo(seo);
    }

    function getUrlMapCounts(seo, summary) {
        var rows = getUrlMapRows(seo);
        var stats = (summary && typeof summary === 'object') ? summary
            : (seo && seo.scan && seo.scan.stats) ? seo.scan.stats : {};
        var pageCount = Math.max(stats.pages || 0, countRowsByType(rows, 'page'));
        var detailCount = Math.max(stats.detail_urls || 0, countRowsByType(rows, 'detail'));
        var redirectCount = countRowsByType(rows, 'redirect');
        var pageFiles = (stats.page_files && stats.page_files.length)
            ? stats.page_files.slice()
            : rows.filter(function (row) { return row.type === 'page'; })
                .map(function (row) { return row.project_file || row.file || ''; })
                .filter(Boolean);
        pageFiles = pageFiles.filter(function (file, index, list) { return list.indexOf(file) === index; });
        var templateFiles = (stats.template_files && stats.template_files.length)
            ? stats.template_files
            : rows.filter(function (row) { return row.type === 'detail'; })
                .map(function (row) { return row.project_file || row.template_path || ''; })
                .filter(Boolean)
                .filter(function (file, index, list) { return list.indexOf(file) === index; });
        return {
            rows: rows,
            pageCount: pageCount,
            detailCount: detailCount,
            redirectCount: redirectCount,
            pageFiles: pageFiles,
            templateFiles: templateFiles,
        };
    }

    function updateEmptyPagesHelp(seo, summary) {
        var help = $('#disc-seo-url-empty-help');
        if (!help) return;
        var counts = getUrlMapCounts(seo, summary);
        help.hidden = !(counts.pageCount === 0 && counts.detailCount > 0);
    }

    function formatScanMeta(seo, summary) {
        var parts = [];
        if (seo && seo.scan && seo.scan.last_at) {
            parts.push(t('discovery_seo_scan_last', 'Last scan: {at}', { at: seo.scan.last_at }));
            if (seo.scan.project_revision) {
                parts.push(t('discovery_seo_scan_rev', 'rev {rev}', { rev: seo.scan.project_revision }));
            }
        }
        var counts = getUrlMapCounts(seo, summary);
        var statBits = [];
        if (counts.pageCount > 0) {
            statBits.push(t('discovery_seo_scan_pages', '{count} page(s)', { count: counts.pageCount }));
        }
        if (counts.templateFiles.length) {
            statBits.push(t('discovery_seo_scan_templates_count', '{count} repeating template(s)', { count: counts.templateFiles.length }));
        }
        if (counts.detailCount > 0) {
            statBits.push(t('discovery_seo_scan_entries', '{count} collection entries', { count: counts.detailCount }));
        }
        if (counts.redirectCount > 0) {
            statBits.push(t('discovery_seo_scan_redirects', '{count} redirect(s)', { count: counts.redirectCount }));
        }
        var stats = (summary && typeof summary === 'object') ? summary
            : (seo && seo.scan && seo.scan.stats) ? seo.scan.stats : null;
        if (stats && stats.excluded > 0) {
            statBits.push(t('discovery_seo_scan_excluded', '{count} internal files skipped', { count: stats.excluded }));
        }
        if (statBits.length) parts.push(statBits.join(' · '));
        return parts.join(' — ');
    }

    function updateUrlMapToolbar() {
        var filter = state.urlMapFilter || 'all';
        var select = $('#disc-seo-url-filter');
        if (select && select.value !== filter) {
            select.value = filter;
        }
        var addRedirect = $('#disc-seo-add-redirect');
        if (addRedirect) {
            addRedirect.hidden = filter !== 'redirect';
        }
    }

    function openRedirectModal(editIndex) {
        var modal = $('#disc-seo-redirect-modal');
        if (!modal) return;
        var redirects = ((state.seo && state.seo.url_map && state.seo.url_map.redirects) || []).slice();
        var isEdit = typeof editIndex === 'number' && editIndex >= 0 && redirects[editIndex];
        var titleEl = modal.querySelector('.modal-header h3');
        if (titleEl) {
            titleEl.textContent = isEdit
                ? t('discovery_seo_redirect_modal_edit_title', 'Edit redirect')
                : t('discovery_seo_redirect_modal_title', 'Add redirect');
        }
        ($('#disc-redirect-index') || {}).value = isEdit ? String(editIndex) : '';
        ($('#disc-redirect-from') || {}).value = isEdit ? (redirects[editIndex].from || '') : '';
        ($('#disc-redirect-to') || {}).value = isEdit ? (redirects[editIndex].to || '') : '';
        ($('#disc-redirect-status') || {}).value = String(isEdit ? (redirects[editIndex].status || 301) : 301);
        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');
        requestAnimationFrame(function () {
            modal.classList.add('active');
            var first = $('#disc-redirect-from');
            if (first && first.focus) first.focus();
        });
    }

    function closeRedirectModal() {
        var modal = $('#disc-seo-redirect-modal');
        if (!modal) return;
        modal.classList.remove('active');
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
    }

    function saveRedirectFromModal(e) {
        if (e) e.preventDefault();
        var from = (($('#disc-redirect-from') || {}).value || '').trim();
        var to = (($('#disc-redirect-to') || {}).value || '').trim();
        var status = parseInt(($('#disc-redirect-status') || {}).value || '301', 10) || 301;
        if (!from || !to) {
            showMessage(t('discovery_js_redirect_paths_required', 'From and to paths are required'), true);
            return;
        }
        var redirects = ((state.seo && state.seo.url_map && state.seo.url_map.redirects) || []).slice();
        var indexRaw = ($('#disc-redirect-index') || {}).value;
        var index = indexRaw === '' ? -1 : parseInt(indexRaw, 10);
        var row = { from: from, to: to, status: status };
        if (index >= 0 && index < redirects.length) {
            redirects[index] = row;
        } else {
            redirects.push(row);
        }
        postJson({ action: 'save_url_map', url_map: { redirects: redirects } }).then(function (res) {
            if (res.status !== 'ok') throw new Error(res.message);
            closeRedirectModal();
            applyUrlMapResponse(res, t('discovery_js_redirect_saved', 'Redirect saved'));
            state.urlMapFilter = 'redirect';
            updateUrlMapToolbar();
            renderUrlMap(state.seo);
        }).catch(function (err) { showMessage(err.message, true); });
    }

    function deleteRedirectAt(index) {
        var redirects = ((state.seo && state.seo.url_map && state.seo.url_map.redirects) || []).slice();
        if (index < 0 || index >= redirects.length) return;
        redirects.splice(index, 1);
        postJson({ action: 'save_url_map', url_map: { redirects: redirects } }).then(function (res) {
            if (res.status !== 'ok') throw new Error(res.message);
            applyUrlMapResponse(res, t('discovery_js_redirect_removed', 'Redirect removed'));
        }).catch(function (err) { showMessage(err.message, true); });
    }

    function renderUrlMap(seo, summary) {
        var tbody = $('#disc-seo-url-tbody');
        var meta = $('#disc-seo-scan-meta');
        if (!tbody) return;
        var rows = getUrlMapRows(seo);
        var filter = state.urlMapFilter || 'all';
        var filtered = rows.filter(function (row) {
            return filter === 'all' || row.type === filter;
        });
        tbody.innerHTML = '';
        if (!filtered.length) {
            var emptyMsg = t('discovery_seo_url_map_empty', 'Nothing listed yet — use “Set up with AI” or “Refresh from site files”.');
            if (filter === 'redirect') {
                emptyMsg = t('discovery_seo_url_map_empty_redirects', 'No redirects yet.');
            }
            tbody.innerHTML = '<tr><td colspan="5" class="u-fg-secondary">' + escapeHtml(emptyMsg) + '</td></tr>';
        } else {
            filtered.forEach(function (row) {
                var tr = document.createElement('tr');
                var projectFile = row.project_file || row.file || '';
                var fileCell = row.type === 'redirect'
                    ? '<span class="u-fg-secondary">—</span>'
                    : (projectFile
                        ? '<span class="disc-site-file" title="' + escapeHtml(projectFile) + '">' + escapeHtml(projectFile) + '</span>'
                        : '<span class="u-fg-secondary">—</span>');
                var listingTitle = row.listing_title || row.label || row.url || '';
                var listingSub = row.listing_subtitle || row.url || '';
                var listingCell = '<div class="disc-url-listing-title">' + escapeHtml(listingTitle) + '</div>'
                    + '<div class="disc-url-listing-sub"><code>' + escapeHtml(listingSub) + '</code></div>';
                var googleTitle = row.type === 'redirect'
                    ? (row.redirect_status || row.title || '301')
                    : (row.title || '—');
                var actions = '';
                if (row.type === 'redirect') {
                    actions = '<button type="button" class="btn btn-ghost btn-sm" data-redirect-edit="'
                        + escapeHtml(String(row.redirect_index)) + '">'
                        + escapeHtml(t('discovery_seo_edit_title', 'Edit')) + '</button>'
                        + ' <button type="button" class="btn btn-ghost btn-sm" data-redirect-delete="'
                        + escapeHtml(String(row.redirect_index)) + '">'
                        + escapeHtml(t('discovery_seo_redirect_remove', 'Remove')) + '</button>';
                } else {
                    actions = '<button type="button" class="btn btn-ghost btn-sm" data-url-edit="' + escapeHtml(row.key || '') + '">'
                        + escapeHtml(t('discovery_seo_edit_title', 'Edit')) + '</button>';
                    if (row.live_url) {
                        actions += ' <a class="btn btn-ghost btn-sm" href="' + escapeHtml(row.live_url) + '" target="_blank" rel="noopener">'
                            + escapeHtml(t('discovery_seo_open_live', 'Open page')) + '</a>';
                    }
                }
                tr.innerHTML = '<td>' + fileCell + '</td>'
                    + '<td>' + listingCell + '</td>'
                    + '<td><span class="badge">' + escapeHtml(rowTypeLabel(row.type)) + '</span></td>'
                    + '<td>' + escapeHtml(googleTitle) + '</td>'
                    + '<td class="cell-actions">' + actions + '</td>';
                tbody.appendChild(tr);
            });
        }
        updateEmptyPagesHelp(seo, summary);
        updateUrlMapToolbar();
        if (meta) {
            var metaText = formatScanMeta(seo, summary);
            if (metaText) {
                meta.textContent = metaText;
                meta.hidden = false;
            } else {
                meta.hidden = true;
            }
        }
    }

    function buildUrlMapRowsFromSeo(seo) {
        if (!seo || !seo.url_map) return [];
        var rows = [];
        var pages = seo.url_map.pages || {};
        Object.keys(pages).forEach(function (path) {
            var page = pages[path] || {};
            var overrides = page.overrides || {};
            rows.push({
                key: path,
                type: 'page',
                url: path,
                project_file: page.file || '',
                label: page.label || path,
                listing_title: page.label || path,
                listing_subtitle: path,
                title: overrides.title || '',
                composition_sources: page.composition_sources || [],
            });
        });
        var details = seo.url_map.details || {};
        Object.keys(details).forEach(function (key) {
            var detail = details[key] || {};
            var overrides = detail.overrides || {};
            var template = detail.template_path || '';
            var collection = detail.collection || '';
            var slug = detail.slug || '';
            var label = detail.label || key;
            rows.push({
                key: key,
                type: 'detail',
                url: key,
                project_file: template,
                label: label,
                listing_title: label,
                listing_subtitle: template && collection && slug
                    ? template + ' → ' + collection + ' / ' + slug
                    : key,
                title: overrides.title || '',
                collection: collection,
                slug: slug,
                template_path: template,
            });
        });
        (seo.url_map.redirects || []).forEach(function (redirect, i) {
            rows.push({
                key: 'redirect:' + i,
                type: 'redirect',
                redirect_index: i,
                url: redirect.from || '',
                project_file: '',
                label: redirect.to || '',
                listing_title: redirect.from || '',
                listing_subtitle: '→ ' + (redirect.to || ''),
                title: String(redirect.status || 301),
                redirect_to: redirect.to || '',
                redirect_status: redirect.status || 301,
            });
        });
        return rows;
    }

    function fillBindingForm(binding) {
        var idEl = $('#disc-binding-id');
        if (idEl) idEl.value = (binding && binding.id) || '';
        var collectionEl = $('#disc-binding-collection');
        if (collectionEl) collectionEl.value = (binding && binding.collection) || '';
        var templateEl = $('#disc-binding-template');
        if (templateEl) templateEl.value = (binding && binding.template_path) || '';
        var excerptEl = $('#disc-binding-excerpt');
        if (excerptEl) excerptEl.value = (binding && binding.excerpt_field) || 'excerpt';
        var patternEl = $('#disc-binding-pattern');
        if (patternEl) patternEl.value = (binding && binding.url_pattern) || '';
    }

    function bindingEntryCount(seo, collection) {
        if (!seo || !seo.url_map || !seo.url_map.details || !collection) return 0;
        return Object.keys(seo.url_map.details).filter(function (key) {
            var row = seo.url_map.details[key] || {};
            return (row.collection || '') === collection;
        }).length;
    }

    function renderDetailBindings(seo) {
        var list = $('#disc-seo-bindings-list');
        if (!list) return;
        list.innerHTML = '';
        var bindings = (seo && seo.detail_bindings) || [];
        if (!bindings.length) {
            list.innerHTML = '<p class="form-help u-m-0">' + escapeHtml(
                t('discovery_seo_bindings_empty', 'No repeating pages yet. Templates with <meta name="birkly-collection"> are detected when you refresh from site files.')
            ) + '</p>';
            return;
        }
        bindings.forEach(function (binding) {
            var card = document.createElement('div');
            card.className = 'disc-binding-card' + (state.activeBindingId === binding.id ? ' is-active' : '');
            var excerpt = binding.excerpt_field || 'excerpt';
            var template = binding.template_path || '';
            var count = bindingEntryCount(seo, binding.collection || '');
            var countLabel = count > 0
                ? t('discovery_seo_bindings_published_count', '{count} published entries in search listings', { count: count })
                : t('discovery_seo_bindings_no_published', 'No published entries yet');
            var pattern = binding.url_pattern
                ? '<div class="u-text-xs u-fg-secondary u-mt-1">' + escapeHtml(t('discovery_seo_bindings_shorter_links', 'Shorter links:')) + ' <code>' + escapeHtml(binding.url_pattern) + '</code></div>'
                : '';
            card.innerHTML = '<button type="button" class="disc-binding-card__main" data-binding-edit="' + escapeHtml(binding.id || '') + '">'
                + '<div><strong>' + escapeHtml(binding.collection || '') + '</strong>'
                + ' <span class="u-fg-secondary">' + escapeHtml(t('discovery_seo_bindings_opens_in', 'opens in')) + '</span> <span class="disc-site-file" title="' + escapeHtml(template) + '">'
                + escapeHtml(template) + '</span></div>'
                + '<div class="u-text-sm u-fg-secondary u-mt-1">' + escapeHtml(t('discovery_seo_bindings_summary_field', 'Summary field:')) + ' ' + escapeHtml(excerpt) + ' · ' + escapeHtml(countLabel) + '</div>'
                + pattern
                + '</button>'
                + '<button type="button" class="btn btn-ghost btn-sm disc-binding-card__remove" data-binding-delete="'
                + escapeHtml(binding.id || '') + '">' + escapeHtml(t('discovery_seo_bindings_remove', 'Remove')) + '</button>';
            list.appendChild(card);
        });
    }

    function escapeHtml(str) {
        return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function applyUrlMapResponse(res, message) {
        state.seo = res.data.seo;
        state.urlMapRows = res.data.rows || [];
        state.status = res.data.status || state.status;
        renderUrlMap(state.seo, res.data.summary || null);
        renderDetailBindings(state.seo);
        if (message) showMessage(message);
    }

    function bindUrlMapActions() {
        var setupAi = $('#disc-seo-setup-ai');
        if (setupAi) {
            setupAi.addEventListener('click', function () {
                setupAi.disabled = true;
                postJson({ action: 'seo_url_map_setup' }).then(function (res) {
                    if (res.status !== 'ok') throw new Error(res.message);
                    applyUrlMapResponse(res, formatScanSummaryMessage(res.data.summary, res.data.rows));
                }).catch(function (e) { showMessage(e.message, true); })
                    .finally(function () { setupAi.disabled = false; });
            });
        }

        var rescan = $('#disc-seo-rescan');
        if (rescan) {
            rescan.addEventListener('click', function () {
                postJson({ action: 'scan_urls' }).then(function (res) {
                    if (res.status !== 'ok') throw new Error(res.message);
                    applyUrlMapResponse(res, formatScanSummaryMessage(res.data.summary, res.data.rows));
                }).catch(function (e) { showMessage(e.message, true); });
            });
        }
        var filterSelect = $('#disc-seo-url-filter');
        if (filterSelect) {
            filterSelect.addEventListener('change', function () {
                state.urlMapFilter = filterSelect.value || 'all';
                updateUrlMapToolbar();
                renderUrlMap(state.seo);
            });
        }
        var tbody = $('#disc-seo-url-tbody');
        if (tbody) {
            tbody.addEventListener('click', function (e) {
                var redirectEdit = e.target.closest('[data-redirect-edit]');
                if (redirectEdit) {
                    openRedirectModal(parseInt(redirectEdit.getAttribute('data-redirect-edit'), 10));
                    return;
                }
                var redirectDelete = e.target.closest('[data-redirect-delete]');
                if (redirectDelete) {
                    deleteRedirectAt(parseInt(redirectDelete.getAttribute('data-redirect-delete'), 10));
                    return;
                }
                var btn = e.target.closest('[data-url-edit]');
                if (!btn) return;
                var key = btn.getAttribute('data-url-edit');
                var row = (state.urlMapRows.length ? state.urlMapRows : buildUrlMapRowsFromSeo(state.seo))
                    .find(function (r) { return r.key === key; });
                if (!row) return;
                var title = window.prompt(t('discovery_seo_edit_title_prompt', 'Search title (leave blank for site default)'), row.title || '');
                if (title === null) return;
                var patch = { pages: {}, details: {} };
                if (row.type === 'page') {
                    patch.pages[row.key] = { overrides: { title: title } };
                } else if (row.type === 'detail') {
                    patch.details[row.key] = { overrides: { title: title } };
                } else {
                    return;
                }
                postJson({ action: 'save_url_map', url_map: patch }).then(function (res) {
                    if (res.status !== 'ok') throw new Error(res.message);
                    state.seo = res.data.seo;
                    state.urlMapRows = res.data.rows || buildUrlMapRowsFromSeo(state.seo);
                    renderUrlMap(state.seo);
                    showMessage(t('discovery_js_url_override_saved', 'URL override saved'));
                }).catch(function (err) { showMessage(err.message, true); });
            });
        }

        var propose = $('#disc-seo-propose');
        if (propose) {
            propose.addEventListener('click', function () {
                postJson({ action: 'seo_propose_all' }).then(function (res) {
                    if (res.status !== 'ok') throw new Error(res.message);
                    return postJson({ action: 'seo_apply_proposals', proposed: res.data.proposed });
                }).then(function (res) {
                    if (res.status !== 'ok') throw new Error(res.message);
                    state.seo = res.data.seo;
                    state.urlMapRows = res.data.rows || [];
                    renderUrlMap(state.seo);
                    var warns = (res.data.warnings || []).join('; ');
                    showMessage(warns
                        ? t('discovery_js_proposals_applied_warns', 'Proposals applied — {warns}', { warns: warns })
                        : t('discovery_js_proposals_applied_review', 'Proposals applied — review and publish'));
                }).catch(function (e) { showMessage(e.message, true); });
            });
        }

        var addRedirect = $('#disc-seo-add-redirect');
        if (addRedirect) {
            addRedirect.addEventListener('click', function () {
                openRedirectModal(-1);
            });
        }
        var redirectForm = $('#disc-seo-redirect-form');
        if (redirectForm) {
            redirectForm.addEventListener('submit', saveRedirectFromModal);
        }

        var bindingSave = $('#disc-binding-save');
        if (bindingSave) {
            bindingSave.addEventListener('click', function () {
                var binding = {
                    id: ($('#disc-binding-id') || {}).value || '',
                    collection: ($('#disc-binding-collection') || {}).value || '',
                    template_path: ($('#disc-binding-template') || {}).value || '',
                    excerpt_field: ($('#disc-binding-excerpt') || {}).value || '',
                    url_pattern: ($('#disc-binding-pattern') || {}).value || null,
                };
                postJson({ action: 'save_detail_binding', binding: binding }).then(function (res) {
                    if (res.status !== 'ok') throw new Error(res.message);
                    var savedBindings = (res.data.seo && res.data.seo.detail_bindings) || [];
                    var saved = savedBindings.find(function (b) {
                        return (b.collection || '') === binding.collection
                            && (b.template_path || '') === binding.template_path;
                    });
                    state.activeBindingId = saved ? (saved.id || null) : null;
                    if (saved) fillBindingForm(saved);
                    applyUrlMapResponse(res, t('discovery_js_connection_saved_listings', 'Connection saved — posts added to search listings'));
                }).catch(function (e) { showMessage(e.message, true); });
            });
        }

        var bindingsList = $('#disc-seo-bindings-list');
        if (bindingsList) {
            bindingsList.addEventListener('click', function (e) {
                var editBtn = e.target.closest('[data-binding-edit]');
                if (editBtn) {
                    var editId = editBtn.getAttribute('data-binding-edit');
                    var bindings = (state.seo && state.seo.detail_bindings) || [];
                    var binding = bindings.find(function (b) { return (b.id || '') === editId; });
                    if (!binding) return;
                    state.activeBindingId = binding.id || null;
                    fillBindingForm(binding);
                    renderDetailBindings(state.seo);
                    var details = $('#disc-seo-binding-details');
                    if (details) details.open = true;
                    return;
                }
                var btn = e.target.closest('[data-binding-delete]');
                if (!btn) return;
                var id = btn.getAttribute('data-binding-delete');
                postJson({ action: 'delete_detail_binding', binding_id: id }).then(function (res) {
                    if (res.status !== 'ok') throw new Error(res.message);
                    if (state.activeBindingId === id) {
                        state.activeBindingId = null;
                        fillBindingForm(null);
                    }
                    applyUrlMapResponse(res, t('discovery_js_connection_removed', 'Connection removed'));
                }).catch(function (err) { showMessage(err.message, true); });
            });
        }
    }

    function bindPackToggles() {
        document.querySelectorAll('.disc-hub input[type="checkbox"][data-pack]').forEach(function (input) {
            if (input.dataset.discBound === '1') return;
            input.dataset.discBound = '1';
            input.addEventListener('change', function () {
                var pack = input.getAttribute('data-pack');
                savePack(pack, { enabled: input.checked }).catch(function (e) {
                    showMessage(e.message || t('discovery_js_could_not_save', 'Could not save'), true);
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
                    showMessage(t('discovery_js_seo_settings_saved', 'SEO settings saved'));
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
                    if ($('#disc-seo-site-title')) $('#disc-seo-site-title').value = proposal.site_title || '';
                    if ($('#disc-seo-description')) $('#disc-seo-description').value = proposal.description || '';
                    if ($('#disc-seo-title-suffix')) $('#disc-seo-title-suffix').value = proposal.title_suffix || '';
                    renderSeoPreviews();
                    updateSeoStickyBar();
                    showMessage(t('discovery_js_proposed_defaults', 'Proposed defaults — review and save'));
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
                publishSeoFlow(live
                    ? t('discovery_js_seo_updates_published', 'SEO updates published')
                    : t('discovery_js_seo_published', 'SEO published'))
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
                    showMessage(t('discovery_js_seo_unpublished', 'SEO unpublished'));
                }).catch(function (e) { showMessage(e.message, true); });
            });
        }

        var seoConfig = $('#disc-seo-config');
        var seoFields = '#disc-seo-site-title, #disc-seo-description, #disc-seo-title-suffix, #disc-seo-og-image, #disc-seo-twitter-card, #disc-seo-robots, #disc-seo-google-verify, #disc-seo-bing-verify, #disc-seo-delivery-mode, [data-seo-surface]';
        if (seoConfig) {
            seoConfig.addEventListener('input', function (e) {
                if (e.target.matches(seoFields)) {
                    renderSeoPreviews();
                    updateSeoStickyBar();
                }
            });
            seoConfig.addEventListener('change', function (e) {
                if (e.target.matches(seoFields)) {
                    renderSeoPreviews();
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
                    showMessage(t('discovery_js_verify_updated', 'Verify checklist updated'));
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
        bindUrlMapActions();
        bindVerify();
        window.addEventListener('birkly:discovery-refresh', function () {
            load().catch(function () { /* ignore */ });
        });
        load().then(function () {
            initApEmbed();
        }).catch(function (e) {
            showMessage(e.message || t('discovery_js_load_discovery_failed', 'Could not load Discovery'), true);
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
