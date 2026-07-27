(function (global) {
    'use strict';

    var LS_AI_CONN = 'ap_ai_connection';
    var CLAIM_STATUSES = ['pending_review', 'supported', 'unsupported', 'conflicting', 'expired'];

    var state = {
        draft: null,
        published: null,
        meta: null,
        canEdit: true,
        csrfToken: '',
        collections: [],
        entriesByCollection: {},
        selectedClaimId: null,
        dirty: false,
        suggestions: [],
        studioSuggestions: [],
        aiConnections: [],
        cmsDiff: [],
        studioDraftPatch: null,
        versions: [],
        publicUrls: {},
        aiAbortController: null
    };

    function apiUrl(path) {
        if (!path) {
            path = '/api/ai_presence.php';
        } else if (path.indexOf('/api/') !== 0) {
            path = '/api/' + path.replace(/^\//, '');
        }
        if (global.Birkly && typeof global.Birkly.url === 'function') {
            return global.Birkly.url(path);
        }
        return path;
    }

    function getCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return (meta && meta.content) ? meta.content : '';
    }

    function bindEl(id, event, handler) {
        var el = document.getElementById(id);
        if (el) {
            el.addEventListener(event, handler);
        }
    }

    function toast(msg, type) {
        if (global.BirklyFeedback && global.BirklyFeedback.toast) {
            global.BirklyFeedback.toast(msg, type || 'info');
        }
    }

    function confirmAsync(message) {
        if (global.BirklyFeedback && global.BirklyFeedback.confirm) {
            return global.BirklyFeedback.confirm(message);
        }
        return Promise.resolve(confirm(message));
    }

    var modalState = {
        productIdx: null,
        claimIdx: null,
        evidenceIdx: null,
        mediaIdx: null,
        pickedMedia: null,
        productMedia: null,
        productSources: [],
        claimSources: []
    };

    var SOURCE_ROLES = ['canonical_detail', 'reference', 'marketplace', 'review', 'docs', 'proof', 'purchase', 'social', 'other'];

    function countProductSources(prod) {
        var n = (prod.sources && prod.sources.length) || 0;
        if (!n && prod.connected_entry && prod.connected_entry.entry_id) n = 1;
        return n;
    }

    function renderSourcesList(listEl, sources, onRemove) {
        if (!listEl) return;
        listEl.innerHTML = '';
        (sources || []).forEach(function (src, idx) {
            var li = document.createElement('li');
            li.className = 'ap-source-item';
            var label = src.label || src.url || (src.ref && src.ref.label) || 'Source';
            var meta = (src.role || '') + (src.type ? ' · ' + src.type : '');
            if (src.link_status && src.link_status !== 'unknown') meta += ' · ' + src.link_status;
            li.innerHTML = '<strong>' + escapeHtml(label) + '</strong> <span class="text-muted">' +
                escapeHtml(meta) + '</span>' +
                (src.url ? ' <a href="' + escapeHtml(src.url) + '" target="_blank" rel="noopener">↗</a>' : '') +
                (onRemove ? ' <button type="button" class="btn-link ap-remove-source" data-idx="' + idx + '">Remove</button>' : '');
            listEl.appendChild(li);
        });
        if (onRemove) {
            listEl.querySelectorAll('.ap-remove-source').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    onRemove(parseInt(btn.getAttribute('data-idx'), 10));
                });
            });
        }
    }

    function populateSourceCollectionSelect(selectEl) {
        if (!selectEl) return;
        var current = selectEl.value;
        selectEl.innerHTML = '<option value="">Collection</option>';
        (state.collections || []).forEach(function (col) {
            var opt = document.createElement('option');
            opt.value = col.dir || col.slug;
            opt.textContent = col.name || col.slug;
            selectEl.appendChild(opt);
        });
        if (current) selectEl.value = current;
    }

    function loadCollectionEntries(collection, selectEl) {
        if (!selectEl || !collection) {
            if (selectEl) selectEl.innerHTML = '<option value="">Entry</option>';
            return Promise.resolve();
        }
        if (state.entriesByCollection[collection]) {
            fillEntrySelect(selectEl, state.entriesByCollection[collection]);
            return Promise.resolve();
        }
        return fetch(apiUrl('/api/ai_presence.php?action=list_collection_entries&collection=' +
            encodeURIComponent(collection)), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                var entries = (res.status === 'ok' && res.data && res.data.entries) ? res.data.entries : [];
                state.entriesByCollection[collection] = entries;
                fillEntrySelect(selectEl, entries);
            });
    }

    function fillEntrySelect(selectEl, entries) {
        if (!selectEl) return;
        selectEl.innerHTML = '<option value="">Entry</option>';
        (entries || []).forEach(function (entry) {
            var opt = document.createElement('option');
            opt.value = entry.id;
            opt.textContent = entry.title || entry.id;
            opt.dataset.slug = entry.slug || entry.id;
            opt.dataset.collection = entry.collection || '';
            selectEl.appendChild(opt);
        });
    }

    function toggleProductSourceCmsRow() {
        var typeEl = document.getElementById('ap-product-source-type');
        var row = document.getElementById('ap-product-source-cms-row');
        if (!typeEl || !row) return;
        row.hidden = typeEl.value !== 'cms_entry';
    }

    function addProductSourceFromForm() {
        var role = document.getElementById('ap-product-source-role').value || 'reference';
        var type = document.getElementById('ap-product-source-type').value || 'public_page';
        var label = document.getElementById('ap-product-source-label').value.trim();
        var url = document.getElementById('ap-product-source-url').value.trim();
        var source = { id: uid('src'), role: role, type: type, label: label, url: url, link_status: 'unknown' };
        if (type === 'cms_entry') {
            var colSel = document.getElementById('ap-product-source-collection');
            var entSel = document.getElementById('ap-product-source-entry');
            var collection = colSel ? colSel.value : '';
            var entryId = entSel ? entSel.value : '';
            if (!collection || !entryId) {
                toast('Select collection and entry for CMS link', 'warning');
                return;
            }
            var opt = entSel.options[entSel.selectedIndex];
            source.ref = {
                collection: collection,
                entry_id: entryId,
                slug: (opt && opt.dataset.slug) || entryId,
                label: label || (opt ? opt.textContent : entryId)
            };
            source.label = source.label || source.ref.label;
            source.url = '';
        } else if (!url && !label) {
            toast('Enter a label or URL', 'warning');
            return;
        }
        modalState.productSources.push(source);
        refreshProductSourcesList();
        document.getElementById('ap-product-source-label').value = '';
        document.getElementById('ap-product-source-url').value = '';
    }

    function addClaimSourceFromForm() {
        var role = document.getElementById('ap-claim-source-role').value || 'proof';
        var label = document.getElementById('ap-claim-source-label').value.trim();
        var url = document.getElementById('ap-claim-source-url').value.trim();
        if (!url && !label) {
            toast('Enter a label or URL', 'warning');
            return;
        }
        modalState.claimSources.push({
            id: uid('src'),
            role: role,
            type: url.indexOf('amazon.') >= 0 ? 'marketplace_listing' : 'review_platform',
            label: label || url,
            url: url,
            link_status: 'unknown'
        });
        refreshClaimSourcesList();
        document.getElementById('ap-claim-source-label').value = '';
        document.getElementById('ap-claim-source-url').value = '';
    }

    function renderLinkHealth(results) {
        var list = document.getElementById('ap-link-health-list');
        if (!list) return;
        list.innerHTML = '';
        if (!results || !results.length) {
            list.innerHTML = '<li class="text-muted">No external links to check yet.</li>';
            return;
        }
        results.forEach(function (row) {
            var li = document.createElement('li');
            var ok = row.ok ? 'ok' : 'warn';
            li.className = 'ap-link-health-item ap-link-health-item--' + ok;
            li.textContent = (row.scope || 'link') + ': ' + (row.url || row.id) +
                ' — ' + (row.link_status || (row.ok ? 'ok' : 'failed'));
            list.appendChild(li);
        });
    }

    function runValidateLinks(updateStatus) {
        showAiLoading('Checking links…');
        return postJson({ action: 'validate_links', update_status: !!updateStatus }).then(function (res) {
            hideAiLoading();
            if (res.status !== 'ok') throw new Error(res.message || 'Link check failed');
            if (res.data && res.data.draft) {
                state.draft = res.data.draft;
                renderProducts();
                renderEvidence();
                renderClaims();
                state.dirty = !!updateStatus;
            }
            renderLinkHealth(res.data.results || []);
            toast('Link check complete', 'success');
        }).catch(function (e) {
            hideAiLoading();
            throw e;
        });
    }

    function runSuggestLinks() {
        showAiLoading('Suggesting cross-links…');
        return postJson({
            action: 'suggest_links',
            ai_connection_id: getSelectedAiConnection()
        }).then(function (res) {
            hideAiLoading();
            if (res.status !== 'ok') throw new Error(res.message || 'Suggest links failed');
            state.suggestions = (res.data.suggestions || []).concat(state.suggestions);
            var seen = {};
            state.suggestions = state.suggestions.filter(function (s) {
                var k = (s.field || '') + '|' + (s.id || '');
                if (seen[k]) return false;
                seen[k] = true;
                return true;
            });
            renderAssistSuggestions();
            openAssistSidebar();
            toast('Link suggestions ready — review in AI Assist', 'success');
        }).catch(function (e) {
            hideAiLoading();
            throw e;
        });
    }

    function openApModal(id) {
        var modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.add('active');
        modal.setAttribute('aria-hidden', 'false');
        var first = modal.querySelector('input, textarea, select, button:not(.modal-close)');
        if (first) first.focus();
    }

    function closeApModal(id) {
        var modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.remove('active');
        modal.setAttribute('aria-hidden', 'true');
    }

    function bindApModals() {
        document.querySelectorAll('[data-ap-modal-close]').forEach(function (el) {
            el.addEventListener('click', function () {
                closeApModal(el.getAttribute('data-ap-modal-close'));
            });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            document.querySelectorAll('.ap-modal.active').forEach(function (modal) {
                closeApModal(modal.id);
            });
        });
    }

    function linesFromTextarea(val) {
        return String(val || '').split('\n').map(function (s) { return s.trim(); }).filter(Boolean);
    }

    function collectionKey(c) {
        return c.dir || c.slug || c.handle || c.id || '';
    }

    function openAssistSidebar() {
        var sidebar = document.getElementById('ap-assist-sidebar');
        var fab = document.getElementById('ap-assist-fab');
        if (sidebar) sidebar.classList.add('is-open');
        if (fab) fab.setAttribute('aria-expanded', 'true');
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = String(text ?? '');
        return div.innerHTML;
    }

    function uid(prefix) {
        return (prefix || 'id') + '_' + Math.random().toString(16).slice(2, 10);
    }

    function postJson(body, options) {
        options = options || {};
        var fetchOpts = {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(Object.assign({ _csrf: state.csrfToken }, body))
        };
        if (options.signal) {
            fetchOpts.signal = options.signal;
        }
        return fetch(apiUrl('/api/ai_presence.php'), fetchOpts).then(function (r) {
            return r.text().then(function (text) {
                if (!text || !String(text).trim()) {
                    throw new Error('Empty server response. The AI request may have timed out — try again.');
                }
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error('Invalid server response. Hard-refresh the page and try again.');
                }
            });
        });
    }

    function showAiLoading(message) {
        state.aiAbortController = typeof AbortController !== 'undefined' ? new AbortController() : null;
        var el = document.getElementById('ap-ai-loading');
        var msgEl = document.getElementById('ap-ai-loading-msg');
        if (msgEl) msgEl.textContent = message || 'AI is working…';
        if (el) {
            el.hidden = false;
            el.classList.add('is-active');
        }
        return state.aiAbortController;
    }

    function hideAiLoading() {
        state.aiAbortController = null;
        var el = document.getElementById('ap-ai-loading');
        if (el) {
            el.hidden = true;
            el.classList.remove('is-active');
        }
    }

    function runWithAiLoading(message, fn) {
        var controller = showAiLoading(message);
        var signal = controller ? controller.signal : null;
        return Promise.resolve().then(function () {
            return fn(signal);
        }).catch(function (err) {
            if (err && err.name === 'AbortError') {
                toast('AI request cancelled', 'info');
                return;
            }
            throw err;
        }).finally(function () {
            hideAiLoading();
        });
    }

    function getSelectedAiConnection() {
        var sel = document.getElementById('ap-ai-connection');
        return sel ? sel.value : '';
    }

    function setNested(obj, path, value) {
        var parts = path.split('.');
        var cur = obj;
        for (var i = 0; i < parts.length - 1; i++) {
            if (!cur[parts[i]] || typeof cur[parts[i]] !== 'object') {
                cur[parts[i]] = {};
            }
            cur = cur[parts[i]];
        }
        cur[parts[parts.length - 1]] = value;
    }

    function getNested(obj, path) {
        return path.split('.').reduce(function (acc, key) {
            return acc && acc[key] !== undefined ? acc[key] : '';
        }, obj);
    }

    function readFieldEl(el) {
        var path = el.getAttribute('data-field');
        if (!path) return;
        var val;
        if (el.type === 'checkbox') {
            val = el.checked;
        } else if (el.getAttribute('data-json') === '1') {
            try {
                val = el.value.trim() ? JSON.parse(el.value) : [];
            } catch (e) {
                toast('Invalid JSON in ' + path, 'error');
                return;
            }
        } else if (el.getAttribute('data-array') === 'lines') {
            val = el.value.split('\n').map(function (s) { return s.trim(); }).filter(Boolean);
        } else if (el.getAttribute('data-array') === 'csv') {
            val = el.value.split(',').map(function (s) { return s.trim(); }).filter(Boolean);
        } else {
            val = el.value;
        }
        setNested(state.draft, path, val);
        state.dirty = true;
    }

    function writeFieldEl(el) {
        var path = el.getAttribute('data-field');
        if (!path) return;
        var val = getNested(state.draft, path);
        if (el.type === 'checkbox') {
            el.checked = !!val;
        } else if (el.getAttribute('data-json') === '1') {
            el.value = Array.isArray(val) || (val && typeof val === 'object')
                ? JSON.stringify(val, null, 2) : '[]';
        } else if (el.getAttribute('data-array') === 'lines' || el.getAttribute('data-array') === 'csv') {
            el.value = Array.isArray(val)
                ? (el.getAttribute('data-array') === 'csv' ? val.join(', ') : val.join('\n')) : '';
        } else {
            el.value = val || '';
        }
    }

    function bindFields() {
        document.querySelectorAll('[data-field]').forEach(function (el) {
            writeFieldEl(el);
            el.addEventListener('input', function () { readFieldEl(el); });
            el.addEventListener('change', function () { readFieldEl(el); });
        });
    }

    function switchTab(tab) {
        if (!tab) return;
        document.querySelectorAll('.ai-presence-tabs .tab[data-tab]').forEach(function (btn) {
            var active = btn.getAttribute('data-tab') === tab;
            btn.classList.toggle('active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        document.querySelectorAll('.ai-presence-tabs .tab-content').forEach(function (pane) {
            var active = pane.getAttribute('data-tab-content') === tab;
            pane.classList.toggle('active', active);
            pane.hidden = !active;
        });
    }

    function initTabs() {
        var root = document.querySelector('.ai-presence-tabs');
        if (!root) return;
        var nav = root.querySelector('.tabs');
        if (nav) {
            nav.setAttribute('role', 'tablist');
            nav.addEventListener('click', function (e) {
                var btn = e.target.closest('.tab[data-tab]');
                if (!btn || !nav.contains(btn)) return;
                switchTab(btn.getAttribute('data-tab'));
            });
        }
        document.querySelectorAll('.ai-presence-tabs .tab-content').forEach(function (pane) {
            pane.hidden = !pane.classList.contains('active');
        });
    }

    function renderOverview(data) {
        var c = data.completeness || {};
        var bar = document.getElementById('ai-presence-progress');
        var label = document.getElementById('ai-presence-completeness-label');
        if (bar) bar.style.width = (c.percent || 0) + '%';
        if (label) {
            label.textContent = (c.score || 0) + ' / ' + (c.max || 100) + ' (' + (c.percent || 0) + '%)';
        }

        var cards = document.getElementById('ai-presence-status-cards');
        if (cards) {
            cards.innerHTML = '';
            (c.items || []).forEach(function (item) {
                var div = document.createElement('div');
                div.className = 'ai-presence-status-card' + (item.complete ? ' is-complete' : '');
                div.innerHTML = '<strong>' + escapeHtml(item.label) + '</strong><span>' +
                    item.score + '/' + item.max + '</span>';
                cards.appendChild(div);
            });
        }

        var stepsEl = document.getElementById('ai-presence-next-steps');
        if (stepsEl) {
            stepsEl.innerHTML = '';
            var steps = data.next_steps || [];
            if (!steps.length) {
                var doneLi = document.createElement('li');
                doneLi.className = 'is-done';
                doneLi.textContent = 'All checklist items complete — ready to publish.';
                stepsEl.appendChild(doneLi);
            } else {
                steps.forEach(function (step) {
                    var li = document.createElement('li');
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'btn-link ai-presence-goto-tab';
                    btn.setAttribute('data-tab', step.tab);
                    btn.textContent = step.message;
                    li.appendChild(btn);
                    stepsEl.appendChild(li);
                });
            }
        }

        renderWorkflow(data);

        var urlsEl = document.getElementById('ai-presence-public-urls');
        var urlsEmptyEl = document.getElementById('ap-public-urls-empty');
        var urlsPanel = document.getElementById('ap-public-urls-panel');
        var urls = data.public_urls || state.publicUrls || {};
        if (urlsEl) {
            var hasBase = !!(urls.well_known_url || urls.api_url || urls.public_site_base);
            if (urlsEmptyEl && urlsPanel && !hasBase) {
                var settingsUrl = urlsPanel.getAttribute('data-settings-url') || '#';
                var noBaseMsg = urlsPanel.getAttribute('data-msg-no-base') ||
                    'Configure your public site URL to see live endpoints.';
                urlsEmptyEl.innerHTML = escapeHtml(noBaseMsg) +
                    ' <a href="' + escapeHtml(settingsUrl) + '">Settings → Site delivery</a>';
                urlsEmptyEl.hidden = false;
                urlsEl.hidden = true;
            } else {
                if (urlsEmptyEl) urlsEmptyEl.hidden = true;
                urlsEl.hidden = false;
                urlsEl.innerHTML =
                    '<dt>Well-known</dt><dd><code>' + escapeHtml(urls.well_known_url || '—') + '</code></dd>' +
                    '<dt>Public API</dt><dd><code>' + escapeHtml(urls.api_url || '—') + '</code></dd>' +
                    '<dt>Last published</dt><dd>' +
                    escapeHtml((data.meta && data.meta.last_published_at) || 'Never') + '</dd>';
            }
        }
    }

    function renderConnected() {
        var list = document.getElementById('ap-connected-list');
        if (!list) return;
        list.innerHTML = '';
        (state.draft.connected_content || []).forEach(function (ref, idx) {
            var li = document.createElement('li');
            li.innerHTML = '<strong>' + escapeHtml(ref.label || ref.collection || ref.entry_id) + '</strong> ' +
                '<span class="badge">' + escapeHtml(ref.role || 'reference') + '</span> ' +
                '<span class="text-muted">(' + escapeHtml(ref.ref_type) + ')</span>' +
                (state.canEdit ? ' <button type="button" class="btn-link ap-remove-connected" data-idx="' +
                    idx + '">Remove</button>' : '');
            list.appendChild(li);
        });
    }

    function renderClaimDetail() {
        var el = document.getElementById('ap-claim-detail');
        if (!el) return;
        if (!state.selectedClaimId) {
            el.textContent = 'Select a claim to view details.';
            el.className = 'text-muted';
            return;
        }
        var claim = (state.draft.claims || []).find(function (c) { return c.id === state.selectedClaimId; });
        if (!claim) {
            el.textContent = 'Claim not found.';
            return;
        }
        el.className = 'ap-claim-detail';
        var html = '<p><strong>' + escapeHtml(claim.statement) + '</strong></p>';
        if (state.canEdit) {
            html += '<label>Status <select class="input ap-claim-status" data-claim-id="' +
                escapeHtml(claim.id) + '">';
            CLAIM_STATUSES.forEach(function (st) {
                html += '<option value="' + st + '"' + (claim.status === st ? ' selected' : '') + '>' +
                    escapeHtml(st) + '</option>';
            });
            html += '</select></label>';
            html += ' <button type="button" class="btn btn-sm btn-secondary ap-edit-claim-btn" data-idx="' +
                (state.draft.claims || []).indexOf(claim) + '">Edit claim</button>';
        } else {
            html += '<p class="badge">' + escapeHtml(claim.status || 'pending_review') + '</p>';
        }
        if (claim.notes) html += '<p class="text-muted">' + escapeHtml(claim.notes) + '</p>';
        var srcCount = (claim.sources || []).length;
        if (srcCount) html += '<p class="text-muted">' + srcCount + ' direct source link(s)</p>';
        el.innerHTML = html;
        var sel = el.querySelector('.ap-claim-status');
        if (sel) {
            sel.addEventListener('change', function () {
                claim.status = sel.value;
                state.dirty = true;
                renderClaims();
            });
        }
    }

    function renderClaims() {
        var list = document.getElementById('ap-claims-list');
        if (!list) return;
        list.innerHTML = '';
        (state.draft.claims || []).forEach(function (claim, idx) {
            var li = document.createElement('li');
            li.className = state.selectedClaimId === claim.id ? 'selected' : '';
            li.innerHTML = '<strong>' + escapeHtml(claim.statement || 'Untitled') + '</strong> ' +
                '<span class="badge">' + escapeHtml(claim.status || 'pending_review') + '</span>' +
                (state.canEdit ?
                    '<div class="ap-item-actions">' +
                    '<button type="button" class="btn-link ap-edit-claim-btn" data-idx="' + idx + '">Edit</button>' +
                    '</div>' : '');
            li.addEventListener('click', function (e) {
                if (e.target.closest('.ap-edit-claim-btn')) return;
                state.selectedClaimId = claim.id;
                renderClaims();
                renderClaimDetail();
                renderEvidence();
            });
            list.appendChild(li);
        });
        renderClaimDetail();
    }

    function renderEvidence() {
        var list = document.getElementById('ap-evidence-list');
        if (!list) return;
        list.innerHTML = '';
        var items = state.draft.evidence || [];
        if (state.selectedClaimId) {
            items = items.filter(function (ev) {
                return (ev.claim_ids || []).indexOf(state.selectedClaimId) !== -1;
            });
        }
        items.forEach(function (ev) {
            var idx = (state.draft.evidence || []).indexOf(ev);
            var li = document.createElement('li');
            li.innerHTML = escapeHtml(ev.title || ev.url || ev.id) +
                ' <span class="badge">' + escapeHtml(ev.type || 'other') + '</span> ' +
                '<span class="badge">' + escapeHtml(ev.verification_status || 'unverified') + '</span>' +
                (ev.rating ? ' <span class="text-muted">' + escapeHtml(String(ev.rating)) + '★</span>' : '') +
                (ev.link_status && ev.link_status !== 'unknown' ?
                    ' <span class="badge">' + escapeHtml(ev.link_status) + '</span>' : '') +
                (state.canEdit ?
                    '<div class="ap-item-actions">' +
                    '<button type="button" class="btn-link ap-edit-evidence-btn" data-idx="' + idx + '">Edit</button> ' +
                    '<button type="button" class="btn-link ap-verify-evidence" data-idx="' + idx + '">Verify</button>' +
                    '</div>' : '');
            list.appendChild(li);
        });
    }

    function renderMedia() {
        var grid = document.getElementById('ap-media-grid');
        var emptyEl = document.getElementById('ap-media-empty');
        if (!grid) return;
        grid.innerHTML = '';
        var items = state.draft.media_context || [];
        if (emptyEl) emptyEl.hidden = items.length > 0;
        items.forEach(function (ctx, idx) {
            var rep = (ctx.represents && ctx.represents.type) || 'brand';
            var card = document.createElement('div');
            card.className = 'ap-media-card';
            var thumbUrl = ctx.media_url || (ctx.media_id ? '/media/' + encodeURIComponent(ctx.media_id) : '');
            card.innerHTML =
                (thumbUrl ? '<img class="ap-media-card-thumb" src="' + escapeHtml(thumbUrl) + '" alt="">' :
                    '<div class="ap-media-card-thumb"></div>') +
                '<div class="ap-media-card-body">' +
                '<strong>' + escapeHtml(ctx.media_name || ctx.media_id || 'Media') + '</strong> ' +
                '<span class="badge">' + escapeHtml(rep) + '</span>' +
                (ctx.description ? '<p>' + escapeHtml(ctx.description) + '</p>' : '') +
                (state.canEdit ?
                    '<div class="ap-item-actions">' +
                    '<button type="button" class="btn btn-sm btn-secondary ap-edit-media" data-idx="' + idx + '">Edit</button> ' +
                    '<button type="button" class="btn btn-sm btn-secondary ap-remove-media" data-idx="' + idx + '">Remove</button>' +
                    '</div>' : '') +
                '</div>';
            grid.appendChild(card);
        });
    }

    function renderPublishStatus(data) {
        var el = document.getElementById('ap-publish-status');
        var version = (data.meta && data.meta.published_version) || 0;
        if (el) {
            el.textContent = version > 0
                ? 'Published version ' + version + '. Draft changes require explicit publish.'
                : 'Not published yet.';
        }
        var warn = document.getElementById('ap-staleness-warnings');
        if (warn) {
            warn.innerHTML = '';
            (data.staleness || []).forEach(function (w) {
                var p = document.createElement('p');
                p.className = 'ai-presence-warning';
                p.textContent = w.message || 'Content may be outdated.';
                warn.appendChild(p);
            });
        }
        renderVersions();
        updatePublishApiUrls();
    }

    function updatePublishApiUrls() {
        var urls = state.publicUrls || {};
        var base = urls.api_url || '';
        var summary = document.getElementById('ap-api-summary');
        var products = document.getElementById('ap-api-products');
        var claims = document.getElementById('ap-api-claims');
        var suffix = function (view) {
            return base ? base + (base.indexOf('?') >= 0 ? '&' : '?') + 'view=' + view : '—';
        };
        if (summary) summary.textContent = suffix('summary');
        if (products) products.textContent = suffix('products');
        if (claims) claims.textContent = suffix('claims');
    }

    function renderVersions() {
        var list = document.getElementById('ap-version-list');
        if (!list) return;
        list.innerHTML = '';
        if (!state.versions.length) {
            list.innerHTML = '<li class="text-muted">No published versions yet.</li>';
            return;
        }
        state.versions.forEach(function (v) {
            var li = document.createElement('li');
            li.textContent = 'Version ' + v.version + ' (' + Math.round((v.size || 0) / 1024) + ' KB)';
            list.appendChild(li);
        });
    }

    function renderProducts() {
        var grid = document.getElementById('ap-products-list');
        var emptyEl = document.getElementById('ap-products-empty');
        if (!grid) return;
        grid.innerHTML = '';
        var items = state.draft.products_services || [];
        if (emptyEl) emptyEl.hidden = items.length > 0;
        items.forEach(function (prod, idx) {
            var card = document.createElement('div');
            card.className = 'ap-product-card card';
            card.innerHTML =
                '<h4>' + escapeHtml(prod.name || 'Unnamed') + '</h4>' +
                '<span class="badge">' + escapeHtml(prod.type || 'product') + '</span>' +
                (prod.category ? ' <span class="text-muted">' + escapeHtml(prod.category) + '</span>' : '') +
                ((prod.media_url || prod.external_image_url) ?
                    '<div class="ap-product-card-thumb-wrap"><img class="ap-product-card-thumb" src="' +
                    escapeHtml(prod.media_url || prod.external_image_url) + '" alt=""></div>' : '') +
                '<p class="ap-product-desc">' + escapeHtml(prod.short_description || prod.full_description || '') + '</p>' +
                (countProductSources(prod) ?
                    '<p class="ap-source-badge text-muted">' + countProductSources(prod) + ' linked source(s)</p>' : '') +
                (state.canEdit ?
                    '<div class="ap-product-actions">' +
                    '<button type="button" class="btn btn-sm btn-secondary ap-edit-product" data-idx="' + idx + '">Edit</button> ' +
                    '<button type="button" class="btn btn-sm btn-secondary ap-delete-product" data-idx="' + idx + '">Delete</button></div>' : '');
            grid.appendChild(card);
        });
    }

    function renderRelationships() {
        var list = document.getElementById('ap-relationships-list');
        if (!list) return;
        list.innerHTML = '';
        (state.draft.relationships || []).forEach(function (rel, idx) {
            var li = document.createElement('li');
            li.innerHTML = '<strong>' + escapeHtml(rel.name || 'Unnamed') + '</strong> ' +
                '<span class="badge">' + escapeHtml(rel.type || 'partner') + '</span>' +
                (rel.url ? ' <a href="' + escapeHtml(rel.url) + '" target="_blank" rel="noopener">Link</a>' : '') +
                (rel.description ? '<p class="text-muted">' + escapeHtml(rel.description) + '</p>' : '') +
                (state.canEdit ? ' <button type="button" class="btn-link ap-remove-relationship" data-idx="' +
                    idx + '">Remove</button>' : '');
            list.appendChild(li);
        });
    }

    function renderSocial() {
        var wrap = document.getElementById('ap-social-list');
        if (!wrap) return;
        wrap.innerHTML = '';
        var profiles = (state.draft.identity && state.draft.identity.social_profiles) || [];
        profiles.forEach(function (prof, idx) {
            var row = document.createElement('div');
            row.className = 'form-row ap-social-row';
            row.innerHTML =
                '<input type="text" class="input ap-social-platform" placeholder="Platform" value="' +
                escapeHtml(prof.platform || '') + '" data-idx="' + idx + '">' +
                '<input type="url" class="input ap-social-url" placeholder="URL" value="' +
                escapeHtml(prof.url || '') + '" data-idx="' + idx + '">' +
                (state.canEdit ? '<button type="button" class="btn btn-sm btn-secondary ap-remove-social" data-idx="' +
                    idx + '">Remove</button>' : '');
            wrap.appendChild(row);
        });
        wrap.querySelectorAll('.ap-social-platform, .ap-social-url').forEach(function (input) {
            input.addEventListener('input', syncSocialFromDom);
        });
    }

    function syncSocialFromDom() {
        if (!state.draft.identity) state.draft.identity = {};
        var rows = document.querySelectorAll('#ap-social-list .ap-social-row');
        var profiles = [];
        rows.forEach(function (row) {
            var platform = row.querySelector('.ap-social-platform');
            var url = row.querySelector('.ap-social-url');
            if (platform && url && (platform.value.trim() || url.value.trim())) {
                profiles.push({ platform: platform.value.trim(), url: url.value.trim() });
            }
        });
        state.draft.identity.social_profiles = profiles;
        state.dirty = true;
    }

    function renderCmsDiff() {
        var list = document.getElementById('ap-cms-diff-list');
        var emptyEl = document.getElementById('ap-cms-sync-empty');
        var panel = document.getElementById('ap-cms-sync-panel');
        if (!list) return;
        list.innerHTML = '';
        var linkedEntries = (state.draft.connected_content || []).filter(function (ref) {
            return ref && ref.ref_type === 'entry';
        });
        if (!linkedEntries.length) {
            if (emptyEl && panel) {
                var noLinksMsg = panel.getAttribute('data-msg-no-links') ||
                    'No CMS entries linked yet. Connect entries on the Context tab to track sync.';
                emptyEl.innerHTML = escapeHtml(noLinksMsg) +
                    ' <button type="button" class="btn-link ai-presence-goto-tab" data-tab="context">' +
                    escapeHtml(panel.getAttribute('data-goto-context-label') || 'Context') + '</button>';
                emptyEl.hidden = false;
            }
            list.hidden = true;
            return;
        }
        if (emptyEl) emptyEl.hidden = true;
        list.hidden = false;
        if (!state.cmsDiff.length) {
            var inSyncMsg = (panel && panel.getAttribute('data-msg-in-sync')) ||
                'All linked CMS entries match your draft fields.';
            list.innerHTML = '<li class="ap-panel-status ap-panel-status--ok">' + escapeHtml(inSyncMsg) + '</li>';
            return;
        }
        state.cmsDiff.forEach(function (diff) {
            var li = document.createElement('li');
            li.className = 'ap-cms-diff-item';
            var msg = diff.message || diff.type || 'Difference';
            li.innerHTML = '<strong>' + escapeHtml(diff.label || 'Entry') + '</strong> — ' +
                escapeHtml(msg) +
                (diff.field ? ' <code>' + escapeHtml(diff.field) + '</code>' : '') +
                (diff.similarity !== undefined ? ' (' + diff.similarity + '% match)' : '');
            if (state.canEdit && diff.ref_index !== undefined) {
                li.innerHTML += ' <button type="button" class="btn btn-sm btn-secondary ap-cms-refresh" data-ref-index="' +
                    diff.ref_index + '"' +
                    (diff.field ? ' data-field="' + escapeHtml(diff.field) + '"' : '') +
                    '>Refresh from CMS</button>';
            }
            list.appendChild(li);
        });
    }

    function renderWorkflow(data) {
        var c = data.completeness || {};
        var items = c.items || [];
        var byKey = {};
        items.forEach(function (item) { byKey[item.key] = item; });

        var map = {
            context: ['identity', 'description', 'connected_content', 'positioning', 'terminology'],
            products: ['products'],
            claims: ['claims', 'evidence'],
            publish: ['publishing']
        };

        document.querySelectorAll('.ap-workflow-step').forEach(function (step) {
            var wf = step.getAttribute('data-workflow');
            var keys = map[wf] || [];
            var done = keys.length > 0 && keys.every(function (k) {
                return byKey[k] && byKey[k].complete;
            });
            step.classList.toggle('is-done', done);
        });
    }

    function renderAssistSuggestions() {
        var list = document.getElementById('ap-assist-suggestions');
        var badge = document.getElementById('ap-suggestion-count');
        var badgeFab = document.getElementById('ap-suggestion-count-fab');
        var empty = document.getElementById('ap-assist-empty');
        if (!list) return;
        list.innerHTML = '';
        var count = state.suggestions.length;
        [badge, badgeFab].forEach(function (el) {
            if (!el) return;
            if (count) {
                el.hidden = false;
                el.textContent = String(count);
            } else {
                el.hidden = true;
            }
        });
        if (empty) empty.hidden = count > 0;
        state.suggestions.forEach(function (s) {
            var li = document.createElement('li');
            li.className = 'ap-suggestion-item';
            var valPreview = typeof s.value === 'object' ? JSON.stringify(s.value) : String(s.value || '');
            if (valPreview.length > 180) valPreview = valPreview.slice(0, 180) + '…';
            li.innerHTML =
                '<strong>' + escapeHtml(s.label || s.field) + '</strong> ' +
                '<span class="badge">' + escapeHtml(s.confidence || 'medium') + '</span>' +
                '<p>' + escapeHtml(valPreview) + '</p>' +
                (s.source ? '<div class="ap-suggestion-meta">Source: ' + escapeHtml(s.source) + '</div>' : '') +
                (state.canEdit ?
                    '<div class="ap-suggestion-actions">' +
                    '<button type="button" class="btn btn-sm btn-primary ap-accept-suggestion" data-id="' +
                    escapeHtml(s.id) + '">Accept</button> ' +
                    '<button type="button" class="btn btn-sm btn-secondary ap-reject-suggestion" data-id="' +
                    escapeHtml(s.id) + '">Reject</button></div>' : '');
            list.appendChild(li);
        });
    }


    function loadAiConnections() {
        return fetch(apiUrl('/api/ai_connections.php?action=list'), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.status === 'ok' && res.data && res.data.connections) {
                    var list = (res.data.connections || []).filter(function (c) {
                        return c.provider !== 'mcp';
                    }).map(function (c) {
                        return {
                            id: c.id,
                            name: c.name || c.id,
                            provider: c.provider || '',
                            model: c.model || ''
                        };
                    });
                    populateAiConnections(list);
                }
            }).catch(function () { /* optional */ });
    }

    function populateAiConnections(connections) {
        state.aiConnections = connections || [];
        var sel = document.getElementById('ap-ai-connection');
        if (!sel) return;
        var saved = '';
        try { saved = localStorage.getItem(LS_AI_CONN) || ''; } catch (e) { /* ignore */ }
        sel.innerHTML = '<option value="">Rules only (no AI)</option>';
        state.aiConnections.forEach(function (conn) {
            var opt = document.createElement('option');
            opt.value = conn.id;
            opt.textContent = conn.name + (conn.provider ? ' (' + conn.provider + ')' : '');
            sel.appendChild(opt);
        });
        if (saved && state.aiConnections.some(function (c) { return c.id === saved; })) {
            sel.value = saved;
        } else if (state.aiConnections.length >= 1) {
            sel.value = state.aiConnections[0].id;
        }
        if (!sel.dataset.apBound) {
            sel.dataset.apBound = '1';
            sel.addEventListener('change', function () {
                try { localStorage.setItem(LS_AI_CONN, sel.value); } catch (e) { /* ignore */ }
            });
        }
    }

    function applyCollections(collections) {
        state.collections = collections || [];
        var connectSel = document.getElementById('ap-connect-collection');
        if (connectSel) {
            while (connectSel.options.length > 1) connectSel.remove(1);
            state.collections.forEach(function (c) {
                var opt = document.createElement('option');
                opt.value = collectionKey(c);
                opt.textContent = c.name || opt.value;
                connectSel.appendChild(opt);
            });
        }
        populateAssistCollections();
    }

    function populateAssistCollections(filter) {
        var sel = document.getElementById('ap-assist-collections');
        var emptyEl = document.getElementById('ap-assist-collections-empty');
        if (!sel) return;
        var q = (filter || '').trim().toLowerCase();
        sel.innerHTML = '';
        var shown = 0;
        state.collections.forEach(function (c) {
            var key = collectionKey(c);
            var label = (c.name || key) + (c.entries_count != null ? ' (' + c.entries_count + ')' : '');
            if (q && label.toLowerCase().indexOf(q) === -1 && key.toLowerCase().indexOf(q) === -1) {
                return;
            }
            var opt = document.createElement('option');
            opt.value = key;
            opt.textContent = label;
            sel.appendChild(opt);
            shown++;
        });
        if (emptyEl) {
            emptyEl.hidden = state.collections.length > 0;
        }
        if (state.collections.length && shown === 0 && q) {
            var hint = document.createElement('option');
            hint.disabled = true;
            hint.textContent = 'No collections match your search';
            sel.appendChild(hint);
        }
    }

    function setAssistStatus(message) {
        var el = document.getElementById('ap-assist-auto-status');
        if (el) el.textContent = message || '';
    }

    function mergeSuggestions(incoming) {
        if (!incoming || !incoming.length) return;
        var seen = {};
        state.suggestions.forEach(function (s) {
            seen[(s.id || '') + '|' + (s.field || '')] = true;
        });
        incoming.forEach(function (s) {
            var key = (s.id || '') + '|' + (s.field || '');
            if (seen[key]) return;
            seen[key] = true;
            state.suggestions.push(s);
        });
    }

    function switchAssistTab(tab) {
        if (!tab) return;
        document.querySelectorAll('.ap-assist-tab[data-assist-tab]').forEach(function (btn) {
            var active = btn.getAttribute('data-assist-tab') === tab;
            btn.classList.toggle('active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        document.querySelectorAll('.ap-assist-panel[data-assist-panel]').forEach(function (panel) {
            var active = panel.getAttribute('data-assist-panel') === tab;
            panel.classList.toggle('active', active);
            panel.hidden = !active;
        });
    }

    function initAssistTabs() {
        var root = document.querySelector('.ap-assist-tabs');
        if (!root) return;
        root.addEventListener('click', function (e) {
            var btn = e.target.closest('.ap-assist-tab[data-assist-tab]');
            if (!btn || !root.contains(btn)) return;
            switchAssistTab(btn.getAttribute('data-assist-tab'));
        });
        document.querySelectorAll('.ap-assist-panel[data-assist-panel]').forEach(function (panel) {
            panel.hidden = !panel.classList.contains('active');
        });
    }

    function refreshAllRenders(data) {
        renderOverview(data);
        renderConnected();
        renderSocial();
        renderRelationships();
        renderProducts();
        renderClaims();
        renderEvidence();
        renderMedia();
        renderPublishStatus(data);
        renderCmsDiff();
        renderAssistSuggestions();
    }

    function initWorkflowNav() {
        document.querySelectorAll('.ap-workflow-step').forEach(function (step) {
            step.addEventListener('click', function () {
                var tab = step.getAttribute('data-workflow');
                if (tab) switchTab(tab);
            });
        });
    }

    function initIntroDismiss() {
        var intro = document.getElementById('ap-intro');
        var btn = document.getElementById('ap-intro-dismiss');
        if (!intro || !btn) return;
        try {
            if (localStorage.getItem('ap_intro_dismissed') === '1') {
                intro.classList.add('is-collapsed');
                btn.hidden = false;
                btn.textContent = 'Show introduction';
            } else {
                btn.hidden = false;
                btn.textContent = 'Hide introduction';
            }
        } catch (e) { /* ignore */ }
        btn.addEventListener('click', function () {
            var collapsed = intro.classList.toggle('is-collapsed');
            btn.textContent = collapsed ? 'Show introduction' : 'Hide introduction';
            try {
                localStorage.setItem('ap_intro_dismissed', collapsed ? '1' : '0');
            } catch (e) { /* ignore */ }
        });
    }

    function initAssistSidebar() {
        var sidebar = document.getElementById('ap-assist-sidebar');
        var fab = document.getElementById('ap-assist-fab');
        if (!fab || !sidebar) return;
        fab.addEventListener('click', function () {
            var open = sidebar.classList.toggle('is-open');
            fab.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.addEventListener('click', function (e) {
            if (!sidebar.classList.contains('is-open')) return;
            if (sidebar.contains(e.target) || fab.contains(e.target)) return;
            sidebar.classList.remove('is-open');
            fab.setAttribute('aria-expanded', 'false');
        });
    }

    function scrollAssistResults() {
        var el = document.getElementById('ap-assist-results');
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function maybeShowSetup(data) {
        /* Setup flow merged into intro + assist sidebar — no blocking wizard. */
        var draft = data.draft || {};
        if (!draft.setup_completed_at && !draft.setup_skipped_at) {
            var empty = !(draft.identity && draft.identity.official_name);
            if (empty) {
                scrollAssistResults();
            }
        }
    }

    function loadCollections() {
        return fetch(apiUrl('/api/ai_presence.php?action=list_collections'), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.status === 'ok' && res.data && res.data.collections) {
                    applyCollections(res.data.collections);
                }
            }).catch(function () { /* optional */ });
    }

    function loadEntries(collection) {
        if (!collection) return Promise.resolve();
        return fetch(apiUrl('/api/admin_entries.php?action=list&collection=' +
            encodeURIComponent(collection)), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                var entries = (res.data && res.data.entries) || res.entries || [];
                state.entriesByCollection[collection] = entries;
                var sel = document.getElementById('ap-connect-entry');
                if (sel) {
                    sel.innerHTML = '<option value="">Select entry</option>';
                    entries.forEach(function (e) {
                        var opt = document.createElement('option');
                        opt.value = e.id;
                        opt.textContent = e.title || e.name || e.id;
                        sel.appendChild(opt);
                    });
                }
            });
    }

    function loadVersions() {
        return fetch(apiUrl('/api/ai_presence.php?action=list_versions'), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.status === 'ok') {
                    state.versions = (res.data && res.data.versions) || [];
                }
            }).catch(function () { state.versions = []; });
    }

    function load() {
        return fetch(apiUrl('/api/ai_presence.php?action=get'), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.status !== 'ok') throw new Error(res.message || 'Load failed');
                var data = res.data;
                state.draft = data.draft;
                state.published = data.published;
                state.meta = data.meta;
                state.canEdit = !!data.can_edit;
                state.cmsDiff = data.cms_diff || [];
                state.publicUrls = data.public_urls || {};
                if (data.collections && data.collections.length) {
                    applyCollections(data.collections);
                }
                bindFields();
                refreshAllRenders(data);
                maybeShowSetup(data);
                if (!state.canEdit) {
                    var saveBtn = document.getElementById('ai-presence-save-draft');
                    var pubBtn = document.getElementById('ai-presence-publish-btn');
                    if (saveBtn) saveBtn.disabled = true;
                    if (pubBtn) pubBtn.disabled = true;
                }
                return loadVersions();
            });
    }

    function saveDraft() {
        return postJson({ action: 'save_draft', draft: state.draft }).then(function (res) {
            if (res.status !== 'ok') throw new Error(res.message || 'Save failed');
            state.draft = res.data.draft;
            state.dirty = false;
            toast('Draft saved', 'success');
            return load();
        });
    }

    function publish() {
        return saveDraft().then(function () {
            return postJson({ action: 'publish' }).then(function (res) {
                if (res.status !== 'ok') throw new Error(res.message || 'Publish failed');
                toast('Published for AI systems', 'success');
                return load();
            });
        });
    }

    function runCopilot(mode) {
        var connId = getSelectedAiConnection();
        if (!connId && mode !== 'fill_gaps') {
            toast('Select an AI connection for this action, or use Fill gaps for basic suggestions', 'warning');
            return Promise.resolve();
        }
        var statusMsg = mode === 'fill_complete' ? 'Generating complete profile…'
            : mode === 'fill_gaps' ? 'Filling gaps…' : 'Generating suggestions…';
        return runWithAiLoading(statusMsg, function (signal) {
            setAssistStatus(statusMsg);
            return postJson({
                action: 'copilot',
                ai_connection_id: connId,
                mode: mode || 'all',
                creative: mode === 'fill_complete'
            }, { signal: signal }).then(function (res) {
                if (res.status !== 'ok') throw new Error(res.message || 'Assist failed');
                state.suggestions = res.data.suggestions || [];
                state.studioDraftPatch = null;
                renderAssistSuggestions();
                scrollAssistResults();
                openAssistSidebar();
                if (res.data.ai_error && connId) {
                    toast(res.data.ai_error, 'warning');
                }
                if (state.suggestions.length) {
                    toast(state.suggestions.length + ' suggestion(s) to review', 'info');
                } else if (!res.data.ai_error) {
                    toast('No suggestions — try linking CMS content or running Auto-fill', 'info');
                }
            }).finally(function () { setAssistStatus(''); });
        });
    }

    function acceptSuggestion(id) {
        var suggestion = state.suggestions.find(function (s) { return s.id === id; });
        if (!suggestion) return Promise.resolve();
        return postJson({ action: 'accept_suggestion', suggestion: suggestion }).then(function (res) {
            if (res.status !== 'ok') throw new Error(res.message || 'Accept failed');
            state.draft = res.data.draft;
            state.suggestions = state.suggestions.filter(function (s) { return s.id !== id; });
            bindFields();
            refreshAllRenders({ draft: state.draft, meta: state.meta, staleness: [] });
            toast('Suggestion accepted', 'success');
        });
    }

    function acceptAllSuggestions() {
        if (!state.suggestions.length) return Promise.resolve();
        if (state.studioDraftPatch) {
            return postJson({
                action: 'wizard_apply',
                suggestions: state.suggestions,
                draft_patch: state.studioDraftPatch
            }).then(function (res) {
                if (res.status !== 'ok') throw new Error(res.message || 'Apply failed');
                state.draft = res.data.draft;
                state.suggestions = [];
                state.studioDraftPatch = null;
                bindFields();
                refreshAllRenders({ draft: state.draft, meta: state.meta, staleness: [] });
                toast('Draft proposal applied', 'success');
            });
        }
        return postJson({ action: 'accept_suggestions', suggestions: state.suggestions })
            .then(function (res) {
                if (res.status !== 'ok') throw new Error(res.message || 'Accept all failed');
                state.draft = res.data.draft;
                state.suggestions = [];
                bindFields();
                refreshAllRenders({ draft: state.draft, meta: state.meta, staleness: [] });
                toast('All suggestions accepted', 'success');
            });
    }

    function runFieldAi(field) {
        var connId = getSelectedAiConnection();
        if (!connId) {
            toast('Select an AI connection in the sidebar first', 'warning');
            openAssistSidebar();
            return Promise.resolve();
        }
        var btn = document.querySelector('.ap-field-ai[data-field="' + field + '"]');
        if (btn) btn.disabled = true;
        return postJson({ action: 'field_ai', field: field, ai_connection_id: connId, creative: true })
            .then(function (res) {
                if (res.status !== 'ok') throw new Error(res.message || 'Field AI failed');
                var suggestions = res.data.suggestions || [];
                if (res.data.error && !suggestions.length) {
                    throw new Error(res.data.error);
                }
                if (!suggestions.length) {
                    toast('No suggestions returned for this field', 'info');
                    return;
                }
                var suggestion = suggestions[0];
                return postJson({ action: 'accept_suggestion', suggestion: suggestion }).then(function (acceptRes) {
                    if (acceptRes.status !== 'ok') throw new Error(acceptRes.message || 'Apply failed');
                    state.draft = acceptRes.data.draft;
                    bindFields();
                    state.dirty = true;
                    toast('Field updated', 'success');
                });
            })
            .finally(function () {
                if (btn) btn.disabled = false;
            });
    }

    function assistAutoFill() {
        var connId = getSelectedAiConnection();
        if (!connId) {
            toast('Select an AI connection in the sidebar first', 'warning');
            openAssistSidebar();
            return Promise.resolve();
        }
        if (!state.collections.length) {
            toast('No CMS collections found — create content collections first', 'warning');
            return Promise.resolve();
        }
        var slugs = state.collections.map(collectionKey).filter(Boolean);
        var btn = document.getElementById('ap-assist-auto-fill');
        if (btn) btn.disabled = true;
        return runWithAiLoading('Reading CMS and generating draft…', function (signal) {
            setAssistStatus('Reading CMS collections and generating draft…');
            return postJson({
                action: 'wizard_generate',
                collections: slugs,
                ai_connection_id: connId
            }, { signal: signal }).then(function (res) {
                if (res.status !== 'ok') throw new Error(res.message || 'Generate failed');
                state.suggestions = res.data.suggestions || [];
                state.studioDraftPatch = res.data.draft_patch || null;
                renderAssistSuggestions();
                scrollAssistResults();
                openAssistSidebar();
                if (res.data.ai_error) {
                    toast(res.data.ai_error, 'error');
                }
                if (state.suggestions.length) {
                    toast(state.suggestions.length + ' proposal(s) ready to review', 'success');
                } else {
                    toast('No proposals generated', 'info');
                }
            }).finally(function () {
                if (btn) btn.disabled = false;
                setAssistStatus('');
            });
        });
    }

    function assistBuildFromCms() {
        var sel = document.getElementById('ap-assist-collections');
        if (!sel) return Promise.resolve();
        var collections = Array.from(sel.selectedOptions).map(function (o) { return o.value; });
        if (!collections.length) {
            toast('Select at least one collection', 'warning');
            return Promise.resolve();
        }
        var connId = getSelectedAiConnection();
        if (!connId) {
            toast('Select an AI connection for CMS generation', 'warning');
            return Promise.resolve();
        }
        var btn = document.getElementById('ap-assist-build');
        if (btn) btn.disabled = true;
        return runWithAiLoading('Generating from selected collections…', function (signal) {
            setAssistStatus('Generating from selected collections…');
            return postJson({
                action: 'wizard_generate',
                collections: collections,
                ai_connection_id: connId
            }, { signal: signal }).then(function (res) {
                if (res.status !== 'ok') throw new Error(res.message || 'Generate failed');
                state.suggestions = res.data.suggestions || [];
                state.studioDraftPatch = res.data.draft_patch || null;
                renderAssistSuggestions();
                scrollAssistResults();
                if (res.data.ai_error) {
                    toast(res.data.ai_error, 'error');
                }
                toast(state.suggestions.length + ' proposal(s) ready to review', 'success');
            }).finally(function () {
                if (btn) btn.disabled = false;
                setAssistStatus('');
            });
        });
    }

    function cmsRefresh(refIndex, field) {
        var body = { action: 'cms_refresh', ref_index: refIndex };
        if (field) body.field = field;
        return postJson(body).then(function (res) {
            if (res.status !== 'ok') throw new Error(res.message || 'Refresh failed');
            state.draft = res.data.draft;
            bindFields();
            return load();
        });
    }

    function assistFillComplete() {
        var connId = getSelectedAiConnection();
        if (!connId) {
            toast('Select an AI connection in the sidebar first', 'warning');
            openAssistSidebar();
            return Promise.resolve();
        }
        var slugs = state.collections.map(collectionKey).filter(Boolean);
        var btn = document.getElementById('ap-assist-fill-complete');
        if (btn) btn.disabled = true;
        return runWithAiLoading('Generating complete profile — this may take a minute…', function (signal) {
            setAssistStatus('Generating complete profile…');
            return postJson({
                action: 'wizard_generate',
                collections: slugs,
                ai_connection_id: connId,
                creative: true
            }, { signal: signal }).then(function (res) {
                if (res.status !== 'ok') throw new Error(res.message || 'Generate failed');
                state.suggestions = res.data.suggestions || [];
                state.studioDraftPatch = res.data.draft_patch || null;
                renderAssistSuggestions();
                scrollAssistResults();
                openAssistSidebar();
                if (res.data.ai_error) {
                    toast(res.data.ai_error, 'warning');
                }
                if (state.suggestions.length) {
                    toast(state.suggestions.length + ' proposal(s) for a complete profile — review and accept', 'success');
                } else {
                    toast('No proposals generated — try Fill gaps or add more context first', 'info');
                }
            }).finally(function () {
                if (btn) btn.disabled = false;
                setAssistStatus('');
            });
        });
    }

    function setProductMediaPreview(media) {
        modalState.productMedia = media || null;
        var preview = document.getElementById('ap-product-media-preview');
        var thumb = document.getElementById('ap-product-media-thumb');
        var nameEl = document.getElementById('ap-product-media-name');
        var clearBtn = document.getElementById('ap-product-clear-media');
        var idEl = document.getElementById('ap-product-media-id');
        var urlEl = document.getElementById('ap-product-media-url');
        if (!media) {
            if (preview) preview.hidden = true;
            if (clearBtn) clearBtn.hidden = true;
            if (idEl) idEl.value = '';
            if (urlEl) urlEl.value = '';
            return;
        }
        if (idEl) idEl.value = media.id || media.name || '';
        if (urlEl) urlEl.value = media.url || media.thumbnail || '';
        if (preview) preview.hidden = false;
        if (clearBtn) clearBtn.hidden = false;
        if (thumb) {
            thumb.src = media.url || media.thumbnail || '';
            thumb.alt = media.name || '';
        }
        if (nameEl) nameEl.textContent = media.name || media.id || '';
    }

    function openProductMediaPicker() {
        if (global.Birkly && global.Birkly.MediaModal) {
            global.Birkly.MediaModal.open(function (media) {
                if (media) setProductMediaPreview(media);
            });
        } else {
            toast('Media picker unavailable — reload the page', 'error');
        }
    }

    function refreshProductSourcesList() {
        renderSourcesList(document.getElementById('ap-product-sources-list'), modalState.productSources, function (idx) {
            modalState.productSources.splice(idx, 1);
            refreshProductSourcesList();
        });
    }

    function refreshClaimSourcesList() {
        renderSourcesList(document.getElementById('ap-claim-sources-list'), modalState.claimSources, function (idx) {
            modalState.claimSources.splice(idx, 1);
            refreshClaimSourcesList();
        });
    }

    function openProductModal(idx) {
        modalState.productIdx = idx == null ? null : idx;
        modalState.productMedia = null;
        var prod = idx != null ? (state.draft.products_services || [])[idx] : null;
        modalState.productSources = prod && Array.isArray(prod.sources) ?
            JSON.parse(JSON.stringify(prod.sources)) : [];
        if (prod && prod.connected_entry && prod.connected_entry.entry_id && !modalState.productSources.length) {
            modalState.productSources.push({
                id: uid('src'),
                role: 'canonical_detail',
                type: 'cms_entry',
                label: prod.connected_entry.label || prod.connected_entry.entry_id,
                url: '',
                ref: prod.connected_entry
            });
        }
        var titleEl = document.getElementById('ap-product-modal-title');
        if (titleEl) titleEl.textContent = prod ? 'Edit product or service' : 'Add product or service';
        document.getElementById('ap-product-name').value = prod ? (prod.name || '') : '';
        document.getElementById('ap-product-type').value = prod ? (prod.type || 'product') : 'product';
        document.getElementById('ap-product-short').value = prod ? (prod.short_description || '') : '';
        document.getElementById('ap-product-full').value = prod ? (prod.full_description || '') : '';
        document.getElementById('ap-product-category').value = prod ? (prod.category || '') : '';
        document.getElementById('ap-product-pricing').value = prod ? (prod.pricing_model || '') : '';
        document.getElementById('ap-product-best-for').value = prod && Array.isArray(prod.best_for) ? prod.best_for.join('\n') : '';
        document.getElementById('ap-product-not-for').value = prod && Array.isArray(prod.not_for) ? prod.not_for.join('\n') : '';
        document.getElementById('ap-product-external-url').value = prod ? (prod.external_image_url || '') : '';
        populateSourceCollectionSelect(document.getElementById('ap-product-source-collection'));
        toggleProductSourceCmsRow();
        refreshProductSourcesList();
        if (prod && (prod.media_id || prod.media_url)) {
            setProductMediaPreview({
                id: prod.media_id,
                name: prod.media_name || prod.media_id,
                url: prod.media_url || prod.external_image_url || ''
            });
        } else {
            setProductMediaPreview(null);
        }
        openApModal('ap-product-modal');
    }

    function saveProductFromModal(e) {
        e.preventDefault();
        var name = document.getElementById('ap-product-name').value.trim();
        if (!name) return;
        var media = modalState.productMedia;
        var externalUrl = document.getElementById('ap-product-external-url').value.trim();
        var connectedEntry = null;
        (modalState.productSources || []).forEach(function (src) {
            if (src.type === 'cms_entry' && src.ref && src.ref.entry_id && !connectedEntry) {
                connectedEntry = src.ref;
            }
        });
        var prod = {
            id: modalState.productIdx != null && state.draft.products_services[modalState.productIdx]
                ? state.draft.products_services[modalState.productIdx].id : uid('prod'),
            name: name,
            type: document.getElementById('ap-product-type').value || 'product',
            short_description: document.getElementById('ap-product-short').value.trim(),
            full_description: document.getElementById('ap-product-full').value.trim(),
            category: document.getElementById('ap-product-category').value.trim(),
            pricing_model: document.getElementById('ap-product-pricing').value.trim(),
            best_for: linesFromTextarea(document.getElementById('ap-product-best-for').value),
            not_for: linesFromTextarea(document.getElementById('ap-product-not-for').value),
            media_id: media ? (media.id || media.name || '') : '',
            media_url: media ? (media.url || media.thumbnail || '') : '',
            media_name: media ? (media.name || '') : '',
            external_image_url: externalUrl,
            sources: modalState.productSources || [],
            claim_ids: modalState.productIdx != null && state.draft.products_services[modalState.productIdx]
                ? (state.draft.products_services[modalState.productIdx].claim_ids || []) : [],
            connected_entry: connectedEntry,
            public: true
        };
        state.draft.products_services = state.draft.products_services || [];
        if (modalState.productIdx != null) {
            state.draft.products_services[modalState.productIdx] = prod;
        } else {
            state.draft.products_services.push(prod);
        }
        closeApModal('ap-product-modal');
        renderProducts();
        state.dirty = true;
        toast('Product saved — remember to save draft', 'success');
    }

    function addProduct() {
        openProductModal(null);
    }

    function editProduct(idx) {
        openProductModal(idx);
    }

    function deleteProduct(idx) {
        confirmAsync('Delete this product/service?').then(function (ok) {
            if (!ok) return;
            state.draft.products_services.splice(idx, 1);
            renderProducts();
            state.dirty = true;
        });
    }

    function openRelationshipModal() {
        document.getElementById('ap-rel-name').value = '';
        document.getElementById('ap-rel-type').value = 'partner';
        document.getElementById('ap-rel-desc').value = '';
        document.getElementById('ap-rel-url').value = '';
        openApModal('ap-relationship-modal');
    }

    function saveRelationshipFromModal(e) {
        e.preventDefault();
        var name = document.getElementById('ap-rel-name').value.trim();
        if (!name) return;
        state.draft.relationships = state.draft.relationships || [];
        state.draft.relationships.push({
            id: uid('rel'),
            type: document.getElementById('ap-rel-type').value || 'partner',
            name: name,
            description: document.getElementById('ap-rel-desc').value.trim(),
            url: document.getElementById('ap-rel-url').value.trim()
        });
        closeApModal('ap-relationship-modal');
        renderRelationships();
        state.dirty = true;
    }

    function addRelationship() {
        openRelationshipModal();
    }

    function openClaimModal(idx) {
        modalState.claimIdx = idx == null ? null : idx;
        var claim = idx != null ? (state.draft.claims || [])[idx] : null;
        modalState.claimSources = claim && Array.isArray(claim.sources) ?
            JSON.parse(JSON.stringify(claim.sources)) : [];
        var titleEl = document.getElementById('ap-claim-modal-title');
        if (titleEl) titleEl.textContent = claim ? 'Edit claim' : 'Add claim';
        document.getElementById('ap-claim-statement').value = claim ? (claim.statement || '') : '';
        document.getElementById('ap-claim-status').value = claim ? (claim.status || 'pending_review') : 'pending_review';
        document.getElementById('ap-claim-notes').value = claim ? (claim.notes || '') : '';
        refreshClaimSourcesList();
        openApModal('ap-claim-modal');
    }

    function saveClaimFromModal(e) {
        e.preventDefault();
        var statement = document.getElementById('ap-claim-statement').value.trim();
        if (!statement) return;
        state.draft.claims = state.draft.claims || [];
        if (modalState.claimIdx != null) {
            var existing = state.draft.claims[modalState.claimIdx];
            existing.statement = statement;
            existing.status = document.getElementById('ap-claim-status').value;
            existing.notes = document.getElementById('ap-claim-notes').value.trim();
            existing.sources = modalState.claimSources || [];
        } else {
            state.draft.claims.push({
                id: uid('claim'),
                statement: statement,
                status: document.getElementById('ap-claim-status').value,
                evidence_ids: [],
                related_content: [],
                sources: modalState.claimSources || [],
                last_reviewed_at: '',
                expires_at: '',
                notes: document.getElementById('ap-claim-notes').value.trim()
            });
        }
        closeApModal('ap-claim-modal');
        renderClaims();
        state.dirty = true;
    }

    function openEvidenceModal(idx) {
        modalState.evidenceIdx = idx == null ? null : idx;
        var ev = idx != null ? (state.draft.evidence || [])[idx] : null;
        var titleEl = document.getElementById('ap-evidence-modal-title');
        if (titleEl) titleEl.textContent = ev ? 'Edit evidence' : 'Add evidence';
        document.getElementById('ap-evidence-title').value = ev ? (ev.title || '') : '';
        document.getElementById('ap-evidence-url').value = ev ? (ev.url || '') : '';
        document.getElementById('ap-evidence-type').value = ev ? (ev.type || 'external_url') : 'external_url';
        document.getElementById('ap-evidence-notes').value = ev ? (ev.notes || '') : '';
        document.getElementById('ap-evidence-rating').value = ev && ev.rating != null ? String(ev.rating) : '';
        document.getElementById('ap-evidence-review-count').value = ev && ev.review_count != null ? String(ev.review_count) : '';
        openApModal('ap-evidence-modal');
    }

    function saveEvidenceFromModal(e) {
        e.preventDefault();
        var title = document.getElementById('ap-evidence-title').value.trim();
        if (!title) return;
        var url = document.getElementById('ap-evidence-url').value.trim();
        var item;
        if (modalState.evidenceIdx != null) {
            item = state.draft.evidence[modalState.evidenceIdx];
            item.title = title;
            item.url = url;
            item.type = document.getElementById('ap-evidence-type').value;
            item.notes = document.getElementById('ap-evidence-notes').value.trim();
            var ratingVal = document.getElementById('ap-evidence-rating').value.trim();
            var reviewVal = document.getElementById('ap-evidence-review-count').value.trim();
            item.rating = ratingVal !== '' ? parseFloat(ratingVal) : undefined;
            item.review_count = reviewVal !== '' ? parseInt(reviewVal, 10) : undefined;
            closeApModal('ap-evidence-modal');
            renderEvidence();
            renderClaims();
            state.dirty = true;
            return;
        }
        item = {
            id: uid('evidence'),
            type: url ? document.getElementById('ap-evidence-type').value : 'other',
            title: title,
            url: url,
            media_id: '',
            publisher: '',
            published_at: '',
            reviewed_at: '',
            verification_status: 'unverified',
            claim_ids: state.selectedClaimId ? [state.selectedClaimId] : [],
            notes: document.getElementById('ap-evidence-notes').value.trim(),
            link_status: 'unknown',
            last_verified_at: ''
        };
        var ratingVal = document.getElementById('ap-evidence-rating').value.trim();
        var reviewVal = document.getElementById('ap-evidence-review-count').value.trim();
        if (ratingVal !== '') item.rating = parseFloat(ratingVal);
        if (reviewVal !== '') item.review_count = parseInt(reviewVal, 10);
        var finish = function () {
            state.draft.evidence = state.draft.evidence || [];
            state.draft.evidence.push(item);
            if (state.selectedClaimId) {
                var claim = (state.draft.claims || []).find(function (c) {
                    return c.id === state.selectedClaimId;
                });
                if (claim) {
                    claim.evidence_ids = claim.evidence_ids || [];
                    claim.evidence_ids.push(item.id);
                    if (claim.evidence_ids.length >= 2) claim.status = 'supported';
                }
            }
            closeApModal('ap-evidence-modal');
            renderEvidence();
            renderClaims();
            state.dirty = true;
        };
        if (url) {
            postJson({ action: 'fetch_url_meta', url: url }).then(function (res) {
                if (res.status === 'ok' && res.data.title) item.title = res.data.title;
                finish();
            }).catch(finish);
        } else {
            finish();
        }
    }

    function openCmsEvidenceModal() {
        var refs = (state.draft.connected_content || []).filter(function (r) {
            return r.ref_type === 'entry' && r.entry_id;
        });
        if (!refs.length) {
            toast('Connect CMS entries first (Context tab)', 'warning');
            return;
        }
        var sel = document.getElementById('ap-cms-evidence-entry');
        if (!sel) return;
        sel.innerHTML = '';
        refs.forEach(function (ref, i) {
            var opt = document.createElement('option');
            opt.value = String(i);
            opt.textContent = ref.label || ref.entry_id;
            sel.appendChild(opt);
        });
        openApModal('ap-cms-evidence-modal');
    }

    function saveCmsEvidenceFromModal(e) {
        e.preventDefault();
        var sel = document.getElementById('ap-cms-evidence-entry');
        var idx = parseInt(sel ? sel.value : '-1', 10);
        var refs = (state.draft.connected_content || []).filter(function (r) {
            return r.ref_type === 'entry' && r.entry_id;
        });
        if (idx < 0 || idx >= refs.length) return;
        var ref = refs[idx];
        var id = uid('evidence');
        var item = {
            id: id,
            type: 'cms_content',
            title: ref.label || ref.entry_id,
            url: '',
            media_id: '',
            publisher: '',
            published_at: '',
            reviewed_at: '',
            verification_status: 'user_verified',
            claim_ids: state.selectedClaimId ? [state.selectedClaimId] : [],
            notes: ref.collection + '/' + ref.entry_id
        };
        state.draft.evidence = state.draft.evidence || [];
        state.draft.evidence.push(item);
        if (state.selectedClaimId) {
            var claim = (state.draft.claims || []).find(function (c) {
                return c.id === state.selectedClaimId;
            });
            if (claim) {
                claim.evidence_ids = claim.evidence_ids || [];
                claim.evidence_ids.push(id);
                if (claim.evidence_ids.length >= 2) claim.status = 'supported';
            }
        }
        closeApModal('ap-cms-evidence-modal');
        renderEvidence();
        renderClaims();
        state.dirty = true;
    }

    function addCmsEvidence() {
        openCmsEvidenceModal();
    }

    function clearPickedMedia() {
        modalState.pickedMedia = null;
        var idEl = document.getElementById('ap-media-id');
        if (idEl) idEl.value = '';
        var preview = document.getElementById('ap-media-picked-preview');
        if (preview) preview.hidden = true;
    }

    function setPickedMedia(media) {
        modalState.pickedMedia = media;
        var idEl = document.getElementById('ap-media-id');
        if (idEl) idEl.value = media.id || media.name || '';
        var preview = document.getElementById('ap-media-picked-preview');
        var thumb = document.getElementById('ap-media-picked-thumb');
        var nameEl = document.getElementById('ap-media-picked-name');
        if (preview) preview.hidden = false;
        if (thumb) {
            thumb.src = media.url || media.thumbnail || '';
            thumb.alt = media.name || '';
        }
        if (nameEl) nameEl.textContent = media.name || media.id || '';
    }

    function openMediaPicker() {
        if (global.Birkly && global.Birkly.MediaModal) {
            global.Birkly.MediaModal.open(function (media) {
                if (media) setPickedMedia(media);
            });
        } else {
            toast('Media picker unavailable — reload the page', 'error');
        }
    }

    function openMediaEditModal(idx) {
        modalState.mediaIdx = idx;
        var ctx = (state.draft.media_context || [])[idx];
        if (!ctx) return;
        var preview = document.getElementById('ap-media-modal-preview');
        var thumb = document.getElementById('ap-media-modal-thumb');
        var nameEl = document.getElementById('ap-media-modal-name');
        if (preview) preview.hidden = false;
        if (thumb) {
            thumb.src = ctx.media_url || '';
            thumb.alt = ctx.media_name || ctx.media_id || '';
        }
        if (nameEl) nameEl.textContent = ctx.media_name || ctx.media_id || '';
        document.getElementById('ap-media-desc').value = ctx.description || '';
        document.getElementById('ap-media-use').value = ctx.intended_use || '';
        document.getElementById('ap-media-restrictions').value = ctx.restrictions || '';
        document.getElementById('ap-media-represents-edit').value =
            (ctx.represents && ctx.represents.type) || 'brand';
        openApModal('ap-media-modal');
    }

    function saveMediaFromModal(e) {
        e.preventDefault();
        var idx = modalState.mediaIdx;
        var ctx = idx != null ? state.draft.media_context[idx] : null;
        if (!ctx) return;
        ctx.description = document.getElementById('ap-media-desc').value.trim();
        ctx.intended_use = document.getElementById('ap-media-use').value.trim();
        ctx.restrictions = document.getElementById('ap-media-restrictions').value.trim();
        ctx.represents = ctx.represents || {};
        ctx.represents.type = document.getElementById('ap-media-represents-edit').value;
        closeApModal('ap-media-modal');
        renderMedia();
        state.dirty = true;
    }

    function runPreview(q) {
        return postJson({
            action: 'preview_query',
            question: q,
            ai_connection_id: getSelectedAiConnection()
        }).then(function (res) {
            if (res.status !== 'ok') return;
            var answer = document.getElementById('ap-preview-answer');
            if (answer) answer.textContent = res.data.answer || '';
            var src = document.getElementById('ap-preview-sources');
            if (src) {
                src.innerHTML = '';
                (res.data.sources || []).forEach(function (s) {
                    var li = document.createElement('li');
                    li.textContent = s.label || s.field || s.type;
                    src.appendChild(li);
                });
            }
        });
    }

    function bindActions() {
        bindEl('ai-presence-save-draft', 'click', function () {
            saveDraft().catch(function (e) { toast(e.message, 'error'); });
        });
        bindEl('ai-presence-publish-btn', 'click', function () {
            confirmAsync('Publish brand context to public endpoints?').then(function (ok) {
                if (ok) publish().catch(function (e) { toast(e.message, 'error'); });
            });
        });

        bindEl('ap-accept-all-suggestions', 'click', function () {
            acceptAllSuggestions().catch(function (e) { toast(e.message, 'error'); });
        });

        document.querySelectorAll('[data-assist-mode]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                runCopilot(btn.getAttribute('data-assist-mode'))
                    .catch(function (e) { toast(e.message, 'error'); });
            });
        });

        bindEl('ap-assist-build', 'click', function () {
            assistBuildFromCms().catch(function (e) { toast(e.message, 'error'); });
        });

        bindEl('ap-assist-auto-fill', 'click', function () {
            assistAutoFill().catch(function (e) { toast(e.message, 'error'); });
        });

        bindEl('ap-assist-fill-complete', 'click', function () {
            assistFillComplete().catch(function (e) { toast(e.message, 'error'); });
        });

        bindEl('ap-assist-suggest-links', 'click', function () {
            runSuggestLinks().catch(function (e) { toast(e.message, 'error'); });
        });

        bindEl('ap-suggest-links', 'click', function () {
            runSuggestLinks().catch(function (e) { toast(e.message, 'error'); });
        });

        bindEl('ap-validate-links', 'click', function () {
            runValidateLinks(true).catch(function (e) { toast(e.message, 'error'); });
        });

        bindEl('ap-product-add-source', 'click', addProductSourceFromForm);
        bindEl('ap-claim-add-source', 'click', addClaimSourceFromForm);
        bindEl('ap-product-source-type', 'change', toggleProductSourceCmsRow);
        bindEl('ap-product-source-collection', 'change', function () {
            var col = document.getElementById('ap-product-source-collection').value;
            loadCollectionEntries(col, document.getElementById('ap-product-source-entry'));
        });

        bindEl('ap-ai-loading-cancel', 'click', function () {
            if (state.aiAbortController) {
                state.aiAbortController.abort();
            }
            hideAiLoading();
        });

        bindEl('ap-assist-collection-search', 'input', function (e) {
            populateAssistCollections(e.target.value);
        });

        var assistList = document.getElementById('ap-assist-suggestions');
        if (assistList) {
            assistList.addEventListener('click', function (e) {
                var id = e.target.getAttribute('data-id');
                if (!id) return;
                if (e.target.classList.contains('ap-accept-suggestion')) {
                    acceptSuggestion(id).catch(function (err) { toast(err.message, 'error'); });
                }
                if (e.target.classList.contains('ap-reject-suggestion')) {
                    state.suggestions = state.suggestions.filter(function (s) { return s.id !== id; });
                    renderAssistSuggestions();
                }
            });
        }

        document.querySelectorAll('.ap-field-ai').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var field = btn.getAttribute('data-field');
                if (field) runFieldAi(field).catch(function (e) { toast(e.message, 'error'); });
            });
        });

        bindApModals();
        var productForm = document.getElementById('ap-product-form');
        if (productForm) productForm.addEventListener('submit', saveProductFromModal);
        bindEl('ap-product-pick-media', 'click', openProductMediaPicker);
        bindEl('ap-product-clear-media', 'click', function () { setProductMediaPreview(null); });
        var relForm = document.getElementById('ap-relationship-form');
        if (relForm) relForm.addEventListener('submit', saveRelationshipFromModal);
        var claimForm = document.getElementById('ap-claim-form');
        if (claimForm) claimForm.addEventListener('submit', saveClaimFromModal);
        var evidenceForm = document.getElementById('ap-evidence-form');
        if (evidenceForm) evidenceForm.addEventListener('submit', saveEvidenceFromModal);
        var cmsEvidenceForm = document.getElementById('ap-cms-evidence-form');
        if (cmsEvidenceForm) cmsEvidenceForm.addEventListener('submit', saveCmsEvidenceFromModal);
        var mediaForm = document.getElementById('ap-media-form');
        if (mediaForm) mediaForm.addEventListener('submit', saveMediaFromModal);

        bindEl('ap-add-product', 'click', addProduct);

        var productsList = document.getElementById('ap-products-list');
        if (productsList) {
            productsList.addEventListener('click', function (e) {
                var idx = parseInt(e.target.getAttribute('data-idx'), 10);
                if (isNaN(idx)) return;
                if (e.target.classList.contains('ap-edit-product')) editProduct(idx);
                if (e.target.classList.contains('ap-delete-product')) deleteProduct(idx);
            });
        }

        bindEl('ap-add-relationship', 'click', addRelationship);
        var relList = document.getElementById('ap-relationships-list');
        if (relList) {
            relList.addEventListener('click', function (e) {
                if (e.target.classList.contains('ap-remove-relationship')) {
                    var idx = parseInt(e.target.getAttribute('data-idx'), 10);
                    state.draft.relationships.splice(idx, 1);
                    renderRelationships();
                    state.dirty = true;
                }
            });
        }

        bindEl('ap-add-social', 'click', function () {
            if (!state.draft.identity) state.draft.identity = {};
            state.draft.identity.social_profiles = state.draft.identity.social_profiles || [];
            state.draft.identity.social_profiles.push({ platform: '', url: '' });
            renderSocial();
            state.dirty = true;
        });
        var socialList = document.getElementById('ap-social-list');
        if (socialList) {
            socialList.addEventListener('click', function (e) {
                if (e.target.classList.contains('ap-remove-social')) {
                    var idx = parseInt(e.target.getAttribute('data-idx'), 10);
                    state.draft.identity.social_profiles.splice(idx, 1);
                    renderSocial();
                    state.dirty = true;
                }
            });
        }

        var connectCollection = document.getElementById('ap-connect-collection');
        if (connectCollection) {
            connectCollection.addEventListener('change', function (e) {
                loadEntries(e.target.value);
            });
        }

        bindEl('ap-add-connected', 'click', function () {
            var colEl = document.getElementById('ap-connect-collection');
            var entryEl = document.getElementById('ap-connect-entry');
            var roleEl = document.getElementById('ap-connect-role');
            var col = colEl ? colEl.value : '';
            var entryId = entryEl ? entryEl.value : '';
            var role = roleEl ? roleEl.value : 'brand_description';
            if (!col) return;
            var ref = {
                role: role,
                ref_type: entryId ? 'entry' : 'collection',
                collection: col,
                entry_id: entryId || '',
                slug: entryId || col,
                label: entryId && entryEl.selectedOptions.length
                    ? entryEl.selectedOptions[0].textContent : col
            };
            state.draft.connected_content = state.draft.connected_content || [];
            state.draft.connected_content.push(ref);
            renderConnected();
            state.dirty = true;
        });

        var connectedList = document.getElementById('ap-connected-list');
        if (connectedList) {
            connectedList.addEventListener('click', function (e) {
                if (e.target.classList.contains('ap-remove-connected')) {
                    var idx = parseInt(e.target.getAttribute('data-idx'), 10);
                    state.draft.connected_content.splice(idx, 1);
                    renderConnected();
                    state.dirty = true;
                }
            });
        }

        bindEl('ap-add-claim', 'click', function () { openClaimModal(null); });

        bindEl('ap-add-evidence', 'click', function () { openEvidenceModal(null); });

        bindEl('ap-add-cms-evidence', 'click', addCmsEvidence);

        var claimsList = document.getElementById('ap-claims-list');
        if (claimsList) {
            claimsList.addEventListener('click', function (e) {
                var btn = e.target.closest('.ap-edit-claim-btn');
                if (!btn) return;
                e.stopPropagation();
                var idx = parseInt(btn.getAttribute('data-idx'), 10);
                if (!isNaN(idx)) openClaimModal(idx);
            });
        }

        var claimDetail = document.getElementById('ap-claim-detail');
        if (claimDetail) {
            claimDetail.addEventListener('click', function (e) {
                var btn = e.target.closest('.ap-edit-claim-btn');
                if (!btn) return;
                var idx = parseInt(btn.getAttribute('data-idx'), 10);
                if (!isNaN(idx)) openClaimModal(idx);
            });
        }

        var evidenceList = document.getElementById('ap-evidence-list');
        if (evidenceList) {
            evidenceList.addEventListener('click', function (e) {
                if (e.target.classList.contains('ap-verify-evidence')) {
                    var idx = parseInt(e.target.getAttribute('data-idx'), 10);
                    if (state.draft.evidence[idx]) {
                        state.draft.evidence[idx].verification_status = 'user_verified';
                        state.draft.evidence[idx].reviewed_at = new Date().toISOString();
                        renderEvidence();
                        state.dirty = true;
                    }
                    return;
                }
                var editBtn = e.target.closest('.ap-edit-evidence-btn');
                if (editBtn) {
                    var evIdx = parseInt(editBtn.getAttribute('data-idx'), 10);
                    if (!isNaN(evIdx)) openEvidenceModal(evIdx);
                }
            });
        }

        bindEl('ap-pick-media', 'click', openMediaPicker);
        bindEl('ap-media-picked-clear', 'click', clearPickedMedia);

        bindEl('ap-add-media-context', 'click', function () {
            var mediaIdEl = document.getElementById('ap-media-id');
            var repEl = document.getElementById('ap-media-represents');
            var mediaId = mediaIdEl ? mediaIdEl.value.trim() : '';
            if (!mediaId) {
                toast('Pick a media file first', 'warning');
                return;
            }
            var repType = repEl ? repEl.value : 'brand';
            var picked = modalState.pickedMedia || {};
            state.draft.media_context = state.draft.media_context || [];
            state.draft.media_context.push({
                media_id: mediaId,
                media_name: picked.name || mediaId,
                media_url: picked.url || picked.thumbnail || '',
                represents: { type: repType, ref: '' },
                description: '',
                intended_use: '',
                restrictions: ''
            });
            clearPickedMedia();
            renderMedia();
            state.dirty = true;
            openMediaEditModal(state.draft.media_context.length - 1);
        });

        var mediaGrid = document.getElementById('ap-media-grid');
        if (mediaGrid) {
            mediaGrid.addEventListener('click', function (e) {
                var idx = parseInt(e.target.getAttribute('data-idx'), 10);
                if (isNaN(idx)) return;
                if (e.target.classList.contains('ap-edit-media')) {
                    openMediaEditModal(idx);
                }
                if (e.target.classList.contains('ap-remove-media')) {
                    confirmAsync('Remove this media context?').then(function (ok) {
                        if (!ok) return;
                        state.draft.media_context.splice(idx, 1);
                        renderMedia();
                        state.dirty = true;
                    });
                }
            });
        }

        var cmsDiffList = document.getElementById('ap-cms-diff-list');
        if (cmsDiffList) {
            cmsDiffList.addEventListener('click', function (e) {
                if (e.target.classList.contains('ap-cms-refresh')) {
                    var refIndex = parseInt(e.target.getAttribute('data-ref-index'), 10);
                    var field = e.target.getAttribute('data-field') || null;
                    cmsRefresh(refIndex, field).catch(function (err) { toast(err.message, 'error'); });
                }
            });
        }

        bindEl('ap-preview-ask', 'click', function () {
            var input = document.getElementById('ap-preview-question');
            runPreview(input ? input.value : '');
        });
        document.querySelectorAll('.ap-preview-q').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var q = btn.getAttribute('data-q');
                var input = document.getElementById('ap-preview-question');
                if (input) input.value = q;
                runPreview(q);
            });
        });

        document.body.addEventListener('click', function (e) {
            if (e.target.classList.contains('ai-presence-goto-tab')) {
                switchTab(e.target.getAttribute('data-tab'));
            }
        });
    }

    function isAiPresencePage() {
        return !!document.querySelector('.ap-page');
    }

    global.initAiPresence = function (config) {
        if (!isAiPresencePage()) return;
        state.csrfToken = getCsrfToken() || (config && config.csrfToken) || '';
        state.canEdit = !config || config.canEdit !== false;
        initTabs();
        initWorkflowNav();
        initIntroDismiss();
        initAssistSidebar();
        initAssistTabs();
        window.addEventListener('birkly:ai-connection-changed', function () {
            loadAiConnections();
        });
        load().then(function () {
            if (!state.collections.length) {
                return loadCollections();
            }
        }).then(function () {
            return loadAiConnections();
        }).then(function () {
            bindActions();
            renderAssistSuggestions();
        }).catch(function (e) {
            toast(e.message || 'Failed to load AI Presence', 'error');
            loadAiConnections().finally(function () { bindActions(); });
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            global.initAiPresence({});
        });
    } else if (isAiPresencePage()) {
        global.initAiPresence({});
    }
})(window);
