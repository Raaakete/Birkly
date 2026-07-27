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
        publicUrls: {}
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

    function postJson(body) {
        return fetch(apiUrl('/api/ai_presence.php'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(Object.assign({ _csrf: state.csrfToken }, body))
        }).then(function (r) { return r.json(); });
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
        } else {
            html += '<p class="badge">' + escapeHtml(claim.status || 'pending_review') + '</p>';
        }
        if (claim.notes) html += '<p class="text-muted">' + escapeHtml(claim.notes) + '</p>';
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
        (state.draft.claims || []).forEach(function (claim) {
            var li = document.createElement('li');
            li.className = state.selectedClaimId === claim.id ? 'selected' : '';
            li.innerHTML = '<strong>' + escapeHtml(claim.statement || 'Untitled') + '</strong> ' +
                '<span class="badge">' + escapeHtml(claim.status || 'pending_review') + '</span>';
            li.addEventListener('click', function () {
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
                (state.canEdit ? ' <button type="button" class="btn-link ap-verify-evidence" data-idx="' +
                    idx + '">Verify</button>' : '');
            list.appendChild(li);
        });
    }

    function renderMedia() {
        var list = document.getElementById('ap-media-list');
        if (!list) return;
        list.innerHTML = '';
        (state.draft.media_context || []).forEach(function (ctx, idx) {
            var li = document.createElement('li');
            var rep = (ctx.represents && ctx.represents.type) || 'brand';
            li.innerHTML = '<code>' + escapeHtml(ctx.media_id) + '</code> — ' + escapeHtml(rep) +
                '<div class="text-muted">' + escapeHtml(ctx.description || '') + '</div>' +
                (state.canEdit ? ' <button type="button" class="btn-link ap-edit-media" data-idx="' +
                    idx + '">Edit</button> ' +
                    '<button type="button" class="btn-link ap-remove-media" data-idx="' +
                    idx + '">Remove</button>' : '');
            list.appendChild(li);
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
        if (summary) {
            summary.textContent = base ? base + (base.indexOf('?') >= 0 ? '&' : '?') + 'view=summary' : '—';
        }
        if (products) {
            products.textContent = base ? base + (base.indexOf('?') >= 0 ? '&' : '?') + 'view=products' : '—';
        }
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
        if (!grid) return;
        grid.innerHTML = '';
        (state.draft.products_services || []).forEach(function (prod, idx) {
            var card = document.createElement('div');
            card.className = 'ap-product-card card';
            card.innerHTML =
                '<h4>' + escapeHtml(prod.name || 'Unnamed') + '</h4>' +
                '<span class="badge">' + escapeHtml(prod.type || 'product') + '</span>' +
                (prod.category ? ' <span class="text-muted">' + escapeHtml(prod.category) + '</span>' : '') +
                '<p>' + escapeHtml(prod.short_description || '') + '</p>' +
                (state.canEdit ?
                    '<div class="ap-product-actions">' +
                    '<button type="button" class="btn btn-sm btn-secondary ap-edit-product" data-idx="' +
                    idx + '">Edit</button> ' +
                    '<button type="button" class="btn btn-sm btn-secondary ap-delete-product" data-idx="' +
                    idx + '">Delete</button></div>' : '');
            grid.appendChild(card);
        });
        if (!(state.draft.products_services || []).length) {
            grid.innerHTML = '<p class="text-muted">No products or services yet.</p>';
        }
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
        } else if (state.aiConnections.length === 1) {
            sel.value = state.aiConnections[0].id;
        } else if (state.aiConnections.length > 0 && !saved) {
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
                populateAiConnections(data.ai_connections || []);
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
        setAssistStatus('Generating suggestions…');
        return postJson({ action: 'copilot', ai_connection_id: connId, mode: mode || 'all' })
            .then(function (res) {
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
            })
            .finally(function () { setAssistStatus(''); });
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
        return postJson({ action: 'field_ai', field: field, ai_connection_id: connId })
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
                mergeSuggestions(suggestions);
                renderAssistSuggestions();
                scrollAssistResults();
                openAssistSidebar();
                toast('AI suggestion added to Review — accept or reject below', 'success');
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
        setAssistStatus('Reading CMS collections and generating draft…');
        return postJson({
            action: 'wizard_generate',
            collections: slugs,
            ai_connection_id: connId
        }).then(function (res) {
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
                toast(state.suggestions.length + ' proposal(s) ready to review', 'success');
            } else {
                toast('No proposals generated', 'info');
            }
        }).finally(function () {
            if (btn) btn.disabled = false;
            setAssistStatus('');
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
        setAssistStatus('Generating from selected collections…');
        return postJson({
            action: 'wizard_generate',
            collections: collections,
            ai_connection_id: connId
        }).then(function (res) {
            if (res.status !== 'ok') throw new Error(res.message || 'Generate failed');
            state.suggestions = res.data.suggestions || [];
            state.studioDraftPatch = res.data.draft_patch || null;
            renderAssistSuggestions();
            scrollAssistResults();
            if (res.data.ai_error) {
                toast(res.data.ai_error, 'warning');
            }
            toast(state.suggestions.length + ' proposal(s) ready to review', 'success');
        }).finally(function () {
            if (btn) btn.disabled = false;
            setAssistStatus('');
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

    function addProduct() {
        var name = prompt('Product or service name');
        if (!name) return;
        var shortDesc = prompt('Short description') || '';
        state.draft.products_services = state.draft.products_services || [];
        state.draft.products_services.push({
            id: uid('prod'),
            name: name,
            type: 'product',
            short_description: shortDesc,
            full_description: '',
            category: '',
            pricing_model: '',
            best_for: [],
            not_for: [],
            claim_ids: [],
            connected_entry: null,
            public: true
        });
        renderProducts();
        state.dirty = true;
    }

    function editProduct(idx) {
        var prod = (state.draft.products_services || [])[idx];
        if (!prod) return;
        prod.name = prompt('Name', prod.name) || prod.name;
        prod.type = prompt('Type (product/service/bundle)', prod.type || 'product') || prod.type;
        prod.short_description = prompt('Short description', prod.short_description || '') || prod.short_description;
        prod.full_description = prompt('Full description', prod.full_description || '') || prod.full_description;
        prod.category = prompt('Category', prod.category || '') || prod.category;
        renderProducts();
        state.dirty = true;
    }

    function deleteProduct(idx) {
        if (!confirm('Delete this product/service?')) return;
        state.draft.products_services.splice(idx, 1);
        renderProducts();
        state.dirty = true;
    }

    function addRelationship() {
        var name = prompt('Relationship name (partner, parent company, etc.)');
        if (!name) return;
        var type = prompt('Type (partner, subsidiary, parent, competitor, other)', 'partner') || 'partner';
        var desc = prompt('Description (optional)') || '';
        var url = prompt('URL (optional)') || '';
        state.draft.relationships = state.draft.relationships || [];
        state.draft.relationships.push({ id: uid('rel'), type: type, name: name, description: desc, url: url });
        renderRelationships();
        state.dirty = true;
    }

    function addCmsEvidence() {
        var refs = (state.draft.connected_content || []).filter(function (r) {
            return r.ref_type === 'entry' && r.entry_id;
        });
        if (!refs.length) {
            toast('Connect CMS entries first (Context tab)', 'warning');
            return;
        }
        var labels = refs.map(function (r, i) { return (i + 1) + '. ' + (r.label || r.entry_id); }).join('\n');
        var pick = prompt('Pick connected entry number:\n' + labels);
        if (!pick) return;
        var idx = parseInt(pick, 10) - 1;
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
        renderEvidence();
        renderClaims();
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

        bindEl('ap-add-claim', 'click', function () {
            var statement = prompt('Claim statement');
            if (!statement) return;
            state.draft.claims = state.draft.claims || [];
            state.draft.claims.push({
                id: uid('claim'),
                statement: statement,
                status: 'pending_review',
                evidence_ids: [],
                related_content: [],
                last_reviewed_at: '',
                expires_at: '',
                notes: ''
            });
            renderClaims();
            state.dirty = true;
        });

        bindEl('ap-add-evidence', 'click', function () {
            var url = prompt('Evidence URL (optional)');
            var title = prompt('Evidence title') || 'Evidence';
            var id = uid('evidence');
            var item = {
                id: id,
                type: url ? 'external_url' : 'other',
                title: title,
                url: url || '',
                media_id: '',
                publisher: '',
                published_at: '',
                reviewed_at: '',
                verification_status: 'unverified',
                claim_ids: state.selectedClaimId ? [state.selectedClaimId] : [],
                notes: ''
            };
            var finish = function () {
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
        });

        bindEl('ap-add-cms-evidence', 'click', addCmsEvidence);

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
                }
            });
        }

        bindEl('ap-add-media-context', 'click', function () {
            var mediaIdEl = document.getElementById('ap-media-id');
            var repEl = document.getElementById('ap-media-represents');
            var mediaId = mediaIdEl ? mediaIdEl.value.trim() : '';
            if (!mediaId) return;
            var repType = repEl ? repEl.value : 'brand';
            var desc = prompt('What does this media represent?') || '';
            state.draft.media_context = state.draft.media_context || [];
            state.draft.media_context.push({
                media_id: mediaId,
                represents: { type: repType, ref: '' },
                description: desc,
                intended_use: '',
                restrictions: ''
            });
            if (mediaIdEl) mediaIdEl.value = '';
            renderMedia();
            state.dirty = true;
        });

        var mediaList = document.getElementById('ap-media-list');
        if (mediaList) {
            mediaList.addEventListener('click', function (e) {
                var idx = parseInt(e.target.getAttribute('data-idx'), 10);
                if (isNaN(idx)) return;
                if (e.target.classList.contains('ap-edit-media')) {
                    var ctx = state.draft.media_context[idx];
                    if (!ctx) return;
                    ctx.description = prompt('Description', ctx.description || '') || ctx.description;
                    ctx.intended_use = prompt('Intended use', ctx.intended_use || '') || ctx.intended_use;
                    ctx.restrictions = prompt('Restrictions', ctx.restrictions || '') || ctx.restrictions;
                    renderMedia();
                    state.dirty = true;
                }
                if (e.target.classList.contains('ap-remove-media')) {
                    state.draft.media_context.splice(idx, 1);
                    renderMedia();
                    state.dirty = true;
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
        load().then(function () {
            if (!state.collections.length) {
                return loadCollections();
            }
        }).then(function () {
            bindActions();
            renderAssistSuggestions();
        }).catch(function (e) {
            toast(e.message || 'Failed to load AI Presence', 'error');
            bindActions();
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
