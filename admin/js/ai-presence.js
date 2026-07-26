(function (global) {
    'use strict';

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
        suggestions: []
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
        } else if (el.getAttribute('data-array') === 'lines' || el.getAttribute('data-array') === 'csv') {
            el.value = Array.isArray(val) ? (el.getAttribute('data-array') === 'csv' ? val.join(', ') : val.join('\n')) : '';
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

    function renderOverview(data) {
        var c = data.completeness || {};
        var bar = document.getElementById('ai-presence-progress');
        var label = document.getElementById('ai-presence-completeness-label');
        if (bar) bar.style.width = (c.percent || 0) + '%';
        if (label) label.textContent = (c.score || 0) + ' / ' + (c.max || 100) + ' (' + (c.percent || 0) + '%)';

        var cards = document.getElementById('ai-presence-status-cards');
        if (cards) {
            cards.innerHTML = '';
            (c.items || []).forEach(function (item) {
                var div = document.createElement('div');
                div.className = 'ai-presence-status-card' + (item.complete ? ' is-complete' : '');
                div.innerHTML = '<strong>' + escapeHtml(item.label) + '</strong><span>' + item.score + '/' + item.max + '</span>';
                cards.appendChild(div);
            });
        }

        var stepsEl = document.getElementById('ai-presence-next-steps');
        if (stepsEl) {
            stepsEl.innerHTML = '';
            (data.next_steps || []).forEach(function (step) {
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

        var urlsEl = document.getElementById('ai-presence-public-urls');
        var urls = data.public_urls || {};
        if (urlsEl) {
            urlsEl.innerHTML =
                '<dt>Well-known</dt><dd><code>' + escapeHtml(urls.well_known_url || '—') + '</code></dd>' +
                '<dt>Public API</dt><dd><code>' + escapeHtml(urls.api_url || '—') + '</code></dd>' +
                '<dt>Last published</dt><dd>' + escapeHtml((data.meta && data.meta.last_published_at) || 'Never') + '</dd>';
        }
    }

    function renderConnected() {
        var list = document.getElementById('ap-connected-list');
        if (!list) return;
        list.innerHTML = '';
        (state.draft.connected_content || []).forEach(function (ref, idx) {
            var li = document.createElement('li');
            li.innerHTML = escapeHtml(ref.label || ref.collection || ref.entry_id) +
                ' <span class="text-muted">(' + escapeHtml(ref.ref_type) + ')</span>' +
                (state.canEdit ? ' <button type="button" class="btn-link ap-remove-connected" data-idx="' + idx + '">Remove</button>' : '');
            list.appendChild(li);
        });
    }

    function renderClaims() {
        var list = document.getElementById('ap-claims-list');
        if (!list) return;
        list.innerHTML = '';
        (state.draft.claims || []).forEach(function (claim) {
            var li = document.createElement('li');
            li.className = state.selectedClaimId === claim.id ? 'selected' : '';
            li.innerHTML = '<strong>' + escapeHtml(claim.statement || 'Untitled') + '</strong>' +
                ' <span class="badge">' + escapeHtml(claim.status || 'pending_review') + '</span>';
            li.addEventListener('click', function () {
                state.selectedClaimId = claim.id;
                renderClaims();
            });
            list.appendChild(li);
        });
    }

    function renderEvidence() {
        var list = document.getElementById('ap-evidence-list');
        if (!list) return;
        list.innerHTML = '';
        (state.draft.evidence || []).forEach(function (ev, idx) {
            var li = document.createElement('li');
            li.innerHTML = escapeHtml(ev.title || ev.url || ev.id) +
                ' <span class="badge">' + escapeHtml(ev.verification_status || 'unverified') + '</span>' +
                (state.canEdit ? ' <button type="button" class="btn-link ap-verify-evidence" data-idx="' + idx + '">Verify</button>' : '');
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
                (state.canEdit ? ' <button type="button" class="btn-link ap-edit-media" data-idx="' + idx + '">Edit</button>' : '');
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
    }

    function renderSuggestions() {
        var panel = document.getElementById('ap-suggestions-panel');
        var list = document.getElementById('ap-suggestions-list');
        if (!panel || !list) return;
        if (!state.suggestions.length) {
            panel.hidden = true;
            return;
        }
        panel.hidden = false;
        list.innerHTML = '';
        state.suggestions.forEach(function (s) {
            var li = document.createElement('li');
            li.innerHTML = '<strong>' + escapeHtml(s.label || s.field) + '</strong><p>' + escapeHtml(String(s.value || '')) + '</p>' +
                '<button type="button" class="btn btn-sm btn-primary ap-accept-suggestion" data-id="' + escapeHtml(s.id) + '">Accept</button> ' +
                '<button type="button" class="btn btn-sm btn-secondary ap-reject-suggestion" data-id="' + escapeHtml(s.id) + '">Reject</button>';
            list.appendChild(li);
        });
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = String(text ?? '');
        return div.innerHTML;
    }

    function switchTab(tab) {
        if (!tab) {
            return;
        }
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
        if (!root) {
            return;
        }
        var nav = root.querySelector('.tabs');
        if (nav) {
            nav.setAttribute('role', 'tablist');
            nav.addEventListener('click', function (e) {
                var btn = e.target.closest('.tab[data-tab]');
                if (!btn || !nav.contains(btn)) {
                    return;
                }
                switchTab(btn.getAttribute('data-tab'));
            });
        }
        document.querySelectorAll('.ai-presence-tabs .tab-content').forEach(function (pane) {
            pane.hidden = !pane.classList.contains('active');
        });
    }

    function maybeShowSetup(data) {
        var draft = data.draft || {};
        var wizard = document.getElementById('ai-presence-setup-wizard');
        var overview = document.getElementById('ai-presence-overview');
        if (!wizard || !overview) return;
        if (draft.setup_completed_at || draft.setup_skipped_at) {
            wizard.hidden = true;
            overview.hidden = false;
            return;
        }
        var empty = !(draft.identity && draft.identity.official_name);
        if (!empty) {
            wizard.hidden = true;
            overview.hidden = false;
            return;
        }
        wizard.hidden = false;
        overview.hidden = true;
        wizard.innerHTML =
            '<div class="card"><h3>Get started with AI Presence</h3>' +
            '<p class="text-muted">Define brand context for AI systems — link existing CMS content, add claims with evidence, then publish.</p>' +
            '<ol class="ai-presence-setup-steps">' +
            '<li>Brand identity</li><li>Connect content</li><li>Known for</li><li>Language guidance</li><li>Review</li>' +
            '</ol>' +
            '<button type="button" class="btn btn-primary" id="ap-setup-start">Get started</button> ' +
            '<button type="button" class="btn btn-secondary" id="ap-setup-skip">Skip setup</button></div>';
        document.getElementById('ap-setup-start').addEventListener('click', function () {
            fetch(apiUrl('/api/ai_presence.php'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'setup_prefill', _csrf: state.csrfToken })
            }).then(function (r) { return r.json(); }).then(function (res) {
                if (res.status === 'ok' && res.data.prefill) {
                    state.draft = res.data.prefill;
                    bindFields();
                    switchTab('context');
                    wizard.hidden = true;
                    overview.hidden = false;
                }
            });
        });
        document.getElementById('ap-setup-skip').addEventListener('click', function () {
            fetch(apiUrl('/api/ai_presence.php'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'setup_skip', _csrf: state.csrfToken })
            }).then(function () {
                wizard.hidden = true;
                overview.hidden = false;
                load();
            });
        });
    }

    function loadCollections() {
        return fetch(apiUrl('/api/admin_collections.php?action=list'), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                state.collections = (res.data && res.data.collections) || res.collections || [];
                var sel = document.getElementById('ap-connect-collection');
                if (sel) {
                    state.collections.forEach(function (c) {
                        var opt = document.createElement('option');
                        opt.value = c.slug || c.handle || c.id;
                        opt.textContent = c.name || opt.value;
                        sel.appendChild(opt);
                    });
                }
            }).catch(function () { /* optional */ });
    }

    function loadEntries(collection) {
        if (!collection) return Promise.resolve();
        return fetch(apiUrl('/api/admin_entries.php?action=list&collection=' + encodeURIComponent(collection)), { credentials: 'same-origin' })
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
                bindFields();
                renderOverview(data);
                renderConnected();
                renderClaims();
                renderEvidence();
                renderMedia();
                renderPublishStatus(data);
                maybeShowSetup(data);
                if (!state.canEdit) {
                    var saveBtn = document.getElementById('ai-presence-save-draft');
                    var pubBtn = document.getElementById('ai-presence-publish-btn');
                    if (saveBtn) saveBtn.disabled = true;
                    if (pubBtn) pubBtn.disabled = true;
                }
            });
    }

    function saveDraft() {
        return fetch(apiUrl('/api/ai_presence.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'save_draft', draft: state.draft, _csrf: state.csrfToken })
        }).then(function (r) { return r.json(); }).then(function (res) {
            if (res.status !== 'ok') throw new Error(res.message || 'Save failed');
            state.draft = res.data.draft;
            state.dirty = false;
            toast('Draft saved', 'success');
            return load();
        });
    }

    function publish() {
        return saveDraft().then(function () {
            return fetch(apiUrl('/api/ai_presence.php'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'publish', _csrf: state.csrfToken })
            }).then(function (r) { return r.json(); }).then(function (res) {
                if (res.status !== 'ok') throw new Error(res.message || 'Publish failed');
                toast('Published for AI systems', 'success');
                return load();
            });
        });
    }

    function bindActions() {
        bindEl('ai-presence-save-draft', 'click', function () {
            saveDraft().catch(function (e) { toast(e.message, 'error'); });
        });
        bindEl('ai-presence-publish-btn', 'click', function () {
            if (global.BirklyFeedback && global.BirklyFeedback.confirm) {
                global.BirklyFeedback.confirm('Publish brand context to public endpoints?', function (ok) {
                    if (ok) publish().catch(function (e) { toast(e.message, 'error'); });
                });
            } else if (confirm('Publish brand context?')) {
                publish().catch(function (e) { toast(e.message, 'error'); });
            }
        });

        document.querySelectorAll('.ai-presence-goto-tab').forEach(function (btn) {
            btn.addEventListener('click', function () {
                switchTab(btn.getAttribute('data-tab'));
            });
        });

        var connectCollection = document.getElementById('ap-connect-collection');
        if (connectCollection) {
            connectCollection.addEventListener('change', function (e) {
                loadEntries(e.target.value);
            });
        }

        bindEl('ap-add-connected', 'click', function () {
            var col = document.getElementById('ap-connect-collection').value;
            var entryId = document.getElementById('ap-connect-entry').value;
            if (!col) return;
            var ref = {
                role: 'brand_description',
                ref_type: entryId ? 'entry' : 'collection',
                collection: col,
                entry_id: entryId || '',
                slug: entryId || col,
                label: entryId ? (document.getElementById('ap-connect-entry').selectedOptions[0].textContent) : col
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
            var id = 'claim_' + Math.random().toString(16).slice(2, 10);
            state.draft.claims = state.draft.claims || [];
            state.draft.claims.push({
                id: id,
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
            var id = 'evidence_' + Math.random().toString(16).slice(2, 10);
            state.draft.evidence = state.draft.evidence || [];
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
            if (url) {
                fetch(apiUrl('/api/ai_presence.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'fetch_url_meta', url: url, _csrf: state.csrfToken })
                }).then(function (r) { return r.json(); }).then(function (res) {
                    if (res.status === 'ok' && res.data.title) item.title = res.data.title;
                    state.draft.evidence.push(item);
                    if (state.selectedClaimId) {
                        var claim = (state.draft.claims || []).find(function (c) { return c.id === state.selectedClaimId; });
                        if (claim) {
                            claim.evidence_ids = claim.evidence_ids || [];
                            claim.evidence_ids.push(id);
                            if (claim.evidence_ids.length >= 2) claim.status = 'supported';
                        }
                    }
                    renderEvidence();
                    renderClaims();
                    state.dirty = true;
                });
            } else {
                state.draft.evidence.push(item);
                renderEvidence();
                state.dirty = true;
            }
        });

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
            var mediaId = document.getElementById('ap-media-id').value.trim();
            if (!mediaId) return;
            var desc = prompt('What does this media represent?') || '';
            state.draft.media_context = state.draft.media_context || [];
            state.draft.media_context.push({
                media_id: mediaId,
                represents: { type: 'brand', ref: '' },
                description: desc,
                intended_use: '',
                restrictions: ''
            });
            document.getElementById('ap-media-id').value = '';
            renderMedia();
            state.dirty = true;
        });

        var mediaList = document.getElementById('ap-media-list');
        if (mediaList) {
            mediaList.addEventListener('click', function (e) {
            if (e.target.classList.contains('ap-edit-media')) {
                var idx = parseInt(e.target.getAttribute('data-idx'), 10);
                var ctx = state.draft.media_context[idx];
                if (!ctx) return;
                ctx.description = prompt('Description', ctx.description || '') || ctx.description;
                ctx.intended_use = prompt('Intended use', ctx.intended_use || '') || ctx.intended_use;
                ctx.restrictions = prompt('Restrictions', ctx.restrictions || '') || ctx.restrictions;
                renderMedia();
                state.dirty = true;
            }
        });
        }

        bindEl('ap-suggest-btn', 'click', function () {
            fetch(apiUrl('/api/ai_presence.php'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'suggest', _csrf: state.csrfToken })
            }).then(function (r) { return r.json(); }).then(function (res) {
                if (res.status !== 'ok') throw new Error(res.message);
                state.suggestions = res.data.suggestions || [];
                renderSuggestions();
            }).catch(function (e) { toast(e.message, 'error'); });
        });

        var suggestionsList = document.getElementById('ap-suggestions-list');
        if (suggestionsList) {
            suggestionsList.addEventListener('click', function (e) {
            var id = e.target.getAttribute('data-id');
            if (!id) return;
            if (e.target.classList.contains('ap-accept-suggestion')) {
                var suggestion = state.suggestions.find(function (s) { return s.id === id; });
                if (!suggestion) return;
                fetch(apiUrl('/api/ai_presence.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'accept_suggestion', suggestion: suggestion, _csrf: state.csrfToken })
                }).then(function (r) { return r.json(); }).then(function (res) {
                    if (res.status === 'ok') {
                        state.draft = res.data.draft;
                        bindFields();
                        renderClaims();
                        state.suggestions = state.suggestions.filter(function (s) { return s.id !== id; });
                        renderSuggestions();
                        toast('Suggestion accepted', 'success');
                    }
                });
            }
            if (e.target.classList.contains('ap-reject-suggestion')) {
                state.suggestions = state.suggestions.filter(function (s) { return s.id !== id; });
                renderSuggestions();
            }
        });
        }

        function runPreview(q) {
            fetch(apiUrl('/api/ai_presence.php'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'preview_query', question: q, _csrf: state.csrfToken })
            }).then(function (r) { return r.json(); }).then(function (res) {
                if (res.status !== 'ok') return;
                document.getElementById('ap-preview-answer').textContent = res.data.answer || '';
                var src = document.getElementById('ap-preview-sources');
                src.innerHTML = '';
                (res.data.sources || []).forEach(function (s) {
                    var li = document.createElement('li');
                    li.textContent = s.label || s.field || s.type;
                    src.appendChild(li);
                });
            });
        }

        bindEl('ap-preview-ask', 'click', function () {
            runPreview(document.getElementById('ap-preview-question').value);
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
        return !!document.querySelector('.ai-presence-page');
    }

    global.initAiPresence = function (config) {
        if (!isAiPresencePage()) {
            return;
        }
        state.csrfToken = getCsrfToken() || (config && config.csrfToken) || '';
        state.canEdit = !config || config.canEdit !== false;
        initTabs();
        loadCollections().then(load).then(function () {
            bindActions();
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
