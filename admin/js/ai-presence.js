(function (global) {
    'use strict';

    var LS_AI_CONN = 'ap_ai_connection';
    var CLAIM_STATUSES = ['pending_review', 'supported', 'unsupported', 'conflicting', 'expired'];
    var ABOUT_MODAL_ID = 'ap-about-modal';
    var ABOUT_MODAL_SEEN_KEY = 'birkly_agent_guide_intro_seen';

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
        aiAbortController: null,
        publishState: null,
        publishingInProgress: false,
        wizard: null,
        pendingWizardAfterIntro: false,
        hubZone: 'guide',
        webmcpConfig: null,
        webmcpCatalog: [],
        webmcpRuntimePacks: [],
        webmcpPublicForms: [],
        webmcpSupported: null,
        webmcpLoaded: false,
        webmcpSaving: false
    };

      var HUB_ZONES = ['guide', 'tools', 'publish'];
    var discoveryEmbed = false;

    function settingsAiUrl() {
        if (global.Birkly && typeof global.Birkly.adminUrl === 'function') {
            return global.Birkly.adminUrl('settings', { tab: 'ai' });
        }
        return '/admin/index.php?page=settings&tab=ai';
    }

    function webmcpPackMeta(pack) {
        if (pack === 'core') {
            return { label: t('ag_webmcp_pack_core', 'Core actions'), note: '' };
        }
        var packs = state.webmcpRuntimePacks || [];
        for (var i = 0; i < packs.length; i++) {
            if (packs[i].pack === pack) {
                return {
                    label: packs[i].label || pack,
                    note: packs[i].note || ''
                };
            }
        }
        return {
            label: String(pack || 'plugin').replace(/_/g, ' ').replace(/\b\w/g, function (ch) { return ch.toUpperCase(); }),
            note: ''
        };
    }

    function webmcpConsentNote(tool) {
        if (!tool || !tool.mutating) return '';
        if (tool.consent === 'pay') return t('ag_tools_consent_pay', 'Highest consent — asks the visitor to confirm before paying.');
        if (tool.consent === 'login') return t('ag_tools_consent_login', 'Highest consent — asks the visitor to confirm before signing in.');
        return t('ag_tools_consent_confirm', 'Asks the visitor to confirm.');
    }

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

    function notifyDiscoveryRefresh() {
        try {
            window.dispatchEvent(new CustomEvent('birkly:discovery-refresh'));
        } catch (e) { /* ignore */ }
    }

    function confirmAsync(message, options) {
        if (global.BirklyFeedback && global.BirklyFeedback.confirm) {
            return global.BirklyFeedback.confirm(message, options);
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

    var SOURCE_TRUTH_TYPES = ['cms_entry', 'cms_collection', 'website', 'document', 'media_asset', 'marketplace', 'review_platform', 'other'];
    var SOURCE_TRUTH_ROLES = ['narrative', 'proof', 'reference', 'archive'];

    var AP_ICONS = {
        sparkle: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l1.912 5.813a2 2 0 0 0 1.272 1.272L21 12l-5.816 1.912a2 2 0 0 0-1.272 1.272L12 21l-1.912-5.816a2 2 0 0 0-1.272-1.272L3 12l5.816-1.912a2 2 0 0 0 1.272-1.272L12 3z"/></svg>',
        pencil: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg>',
        trash: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>'
    };

    function sourceTypeLabel(type) {
        var map = {
            cms_entry: 'CMS entry',
            cms_collection: 'CMS collection',
            website: 'Website',
            document: 'Document',
            media_asset: 'Media',
            marketplace: 'Marketplace',
            review_platform: 'Reviews',
            other: 'Other'
        };
        return map[type] || type || 'Source';
    }

    function sourceTypeIcon(type) {
        var map = {
            cms_entry: 'E',
            cms_collection: 'C',
            website: 'W',
            document: 'D',
            media_asset: 'M',
            marketplace: '$',
            review_platform: '★',
            other: '•'
        };
        return map[type] || '•';
    }

    function initFieldAiButtons() {
        document.querySelectorAll('.ap-field-ai').forEach(function (btn) {
            if (btn.dataset.iconInit === '1') return;
            btn.dataset.iconInit = '1';
            var label = btn.getAttribute('title') || btn.textContent.trim();
            btn.setAttribute('aria-label', label);
            btn.innerHTML = AP_ICONS.sparkle;
            btn.classList.add('btn-icon');
        });
    }

    function switchSourceMode(mode) {
        document.querySelectorAll('.ap-source-mode-tab').forEach(function (tab) {
            var active = tab.getAttribute('data-source-mode') === mode;
            tab.classList.toggle('active', active);
        });
        document.querySelectorAll('.ap-source-mode-panel').forEach(function (panel) {
            var active = panel.getAttribute('data-source-mode-panel') === mode;
            panel.hidden = !active;
            panel.classList.toggle('active', active);
        });
        var typeEl = document.getElementById('ap-source-type');
        if (typeEl) {
            typeEl.value = mode === 'cms' ? 'cms_entry' : (mode === 'document' ? 'document' : 'website');
        }
    }

    function uploadSourceDocument(file) {
        var formData = new FormData();
        formData.append('file', file);
        formData.append('folder', 'agent-guide-sources');
        var csrf = getCsrfToken();
        if (csrf) formData.append('_csrf', csrf);
        var statusEl = document.getElementById('ap-source-upload-status');
        if (statusEl) {
            statusEl.hidden = false;
            statusEl.textContent = t('ag_js_uploading', 'Uploading {name}…', { name: file.name });
        }
        var mediaUrl = global.Birkly && typeof global.Birkly.url === 'function'
            ? global.Birkly.url('/api/admin_media.php?action=upload')
            : '/api/admin_media.php?action=upload';
        return fetch(mediaUrl, { method: 'POST', credentials: 'same-origin', body: formData })
            .then(function (r) { return r.json(); })
            .then(function (result) {
                if (result.status !== 'ok' || !result.data) {
                    throw new Error(result.message || t('ag_js_upload_failed', 'Upload failed'));
                }
                ensureSourcesArray();
                var roleEl = document.getElementById('ap-source-role');
                state.draft.sources_of_truth.push({
                    id: newSourceId(),
                    type: 'document',
                    role: roleEl ? roleEl.value : 'reference',
                    label: result.data.name || file.name,
                    url: result.data.url || '',
                    media_id: result.data.id || result.data.filename || ''
                });
                syncLegacyConnectedFromSources();
                renderSources();
                state.dirty = true;
                toast(t('ag_js_doc_added_source', 'Document added as source of truth'), 'success');
            })
            .catch(function (err) {
                toast(err.message || t('ag_js_upload_failed', 'Upload failed'), 'error');
            })
            .finally(function () {
                if (statusEl) statusEl.hidden = true;
            });
    }

    function toggleProductPriceSourceUi() {
        var typeEl = document.getElementById('ap-product-price-source-type');
        var cmsRow = document.getElementById('ap-product-price-cms-row');
        var urlRow = document.getElementById('ap-product-price-url-row');
        if (!typeEl) return;
        var type = typeEl.value || 'manual';
        if (cmsRow) cmsRow.hidden = type !== 'cms_field';
        if (urlRow) urlRow.hidden = type !== 'external';
    }

    function ensureSourcesArray() {
        if (!state.draft) return;
        state.draft.sources_of_truth = state.draft.sources_of_truth || [];
    }

    function newSourceId() {
        return 'truth_' + Math.random().toString(16).slice(2, 10);
    }

    function syncLegacyConnectedFromSources() {
        if (!state.draft) return;
        var connected = [];
        (state.draft.sources_of_truth || []).forEach(function (source) {
            if (!source || (source.type !== 'cms_entry' && source.type !== 'cms_collection')) return;
            var ref = source.ref || {};
            connected.push({
                role: source.role || 'reference',
                ref_type: source.type === 'cms_collection' ? 'collection' : 'entry',
                collection: ref.collection || '',
                entry_id: ref.entry_id || '',
                slug: ref.slug || ref.collection || '',
                label: source.label || ref.label || ref.collection || ''
            });
        });
        state.draft.connected_content = connected;
    }

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
            var label = src.label || src.url || (src.ref && src.ref.label) || 'Source';
            var meta = (src.role || '') + (src.type ? ' · ' + src.type : '');
            if (src.link_status && src.link_status !== 'unknown') meta += ' · ' + src.link_status;
            li.innerHTML = '<strong>' + escapeHtml(label) + '</strong> <span class="u-fg-secondary">' +
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
                toast(t('ag_js_select_collection_entry', 'Select collection and entry for CMS link'), 'warning');
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
            toast(t('ag_js_enter_label_or_url', 'Enter a label or URL'), 'warning');
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
            toast(t('ag_js_enter_label_or_url', 'Enter a label or URL'), 'warning');
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
            list.innerHTML = '<li class="u-fg-secondary">No external links to check yet.</li>';
            return;
        }
        results.forEach(function (row) {
            var li = document.createElement('li');
            li.className = row.ok ? '' : 'u-fg-warning';
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
            toast(t('ag_js_link_check_complete', 'Link check complete'), 'success');
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
            toast(t('ag_js_link_suggestions_ready', 'Link suggestions ready — review in AI Assist'), 'success');
        }).catch(function (e) {
            hideAiLoading();
            throw e;
        });
    }

    var modalFocusTrap = null;
    var modalPreviousFocus = null;

    function getModalFocusable(modal) {
        return Array.from(modal.querySelectorAll(
            'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'
        )).filter(function (el) { return el.offsetParent !== null; });
    }

    function releaseModalFocusTrap() {
        if (modalFocusTrap) {
            document.removeEventListener('keydown', modalFocusTrap);
            modalFocusTrap = null;
        }
    }

    function trapModalFocus(modal) {
        releaseModalFocusTrap();
        modalFocusTrap = function (e) {
            if (e.key !== 'Tab' || !modal.classList.contains('active')) return;
            var focusable = getModalFocusable(modal);
            if (!focusable.length) return;
            var first = focusable[0];
            var last = focusable[focusable.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        };
        document.addEventListener('keydown', modalFocusTrap);
    }

    function openApModal(id) {
        var modal = document.getElementById(id);
        if (!modal) return;
        modalPreviousFocus = document.activeElement;
        modal.classList.add('active');
        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');
        var first = modal.querySelector('input, textarea, select, button:not(.modal-close)');
        if (first) first.focus();
        trapModalFocus(modal);
    }

    function closeApModal(id) {
        var modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.remove('active');
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
        releaseModalFocusTrap();
        if (id === ABOUT_MODAL_ID) {
            markAboutModalSeen();
            if (state.pendingWizardAfterIntro) {
                state.pendingWizardAfterIntro = false;
                setTimeout(function () {
                    openSetupWizard();
                }, 300);
            }
        }
        if (modalPreviousFocus && modalPreviousFocus.focus) {
            modalPreviousFocus.focus();
            modalPreviousFocus = null;
        }
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

    // P60 IA: AI Assist is a card in the Guide → Overview tab (no drawer/FAB).
    // "Opening" it now means revealing the Overview tab and scrolling the
    // suggestions into view.
    function openAssistSidebar() {
        showGuideTab('overview');
        scrollAssistResults();
    }

    function t(key, fallback, params) {
        if (global.Birkly && global.Birkly.i18n && typeof global.Birkly.i18n.t === 'function') {
            return global.Birkly.i18n.t('admin.' + key, fallback, params);
        }
        var text = fallback || key;
        if (params && typeof params === 'object') {
            Object.keys(params).forEach(function (name) {
                var value = String(params[name]);
                text = String(text).split('{{' + name + '}}').join(value);
                text = text.split('{' + name + '}').join(value);
            });
        }
        return text;
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = String(text ?? '');
        return div.innerHTML;
    }

    function formatDateTime(iso) {
        if (!iso) return '—';
        try {
            return new Date(iso).toLocaleString();
        } catch (e) {
            return iso;
        }
    }

    function getPublishState(data) {
        // publication_settings.enabled reflects the draft toggle; is_live must
        // follow the published snapshot (what MCP + public endpoints serve).
        var enabledNow = !!(state.draft && state.draft.publication_settings && state.draft.publication_settings.enabled);
        var snapshotEnabled = !!(state.published && state.published.publication_settings && state.published.publication_settings.enabled);
        if (data && data.publish_state) {
            var ps = Object.assign({}, data.publish_state, { publishing_enabled: enabledNow, snapshot_enabled: snapshotEnabled });
            if (state.dirty) {
                ps = Object.assign(ps, { has_unpublished_changes: true });
            }
            return ps;
        }
        var meta = state.meta || {};
        var version = (meta.published_version || 0);
        var neverPublished = !state.published || version < 1;
        var isLive = !neverPublished && snapshotEnabled;
        return {
            is_live: isLive,
            version: version,
            last_published_at: meta.last_published_at || null,
            publishing_enabled: enabledNow,
            snapshot_enabled: snapshotEnabled,
            has_unpublished_changes: isLive && state.dirty,
            never_published: neverPublished,
            needs_republish: !neverPublished && enabledNow && !snapshotEnabled
        };
    }

    function renderStatusStrip(data) {
        var ps = getPublishState(data);
        state.publishState = ps;
        var badgesEl = document.getElementById('ap-status-badges');
        var metaEl = document.getElementById('ap-status-meta');
        var setupBtn = document.getElementById('ap-setup-guide-btn');
        var statusPubBtn = document.getElementById('ap-status-publish-btn');
        if (!badgesEl) return;

        var badges = '';
        if (!ps.publishing_enabled) {
            badges += '<span class="badge badge-neutral">' + escapeHtml(t('ag_badge_publishing_off', 'Publishing off')) + '</span>';
        } else if (ps.never_published) {
            badges += '<span class="badge badge-warning">' + escapeHtml(t('ag_badge_not_live', 'Not live')) + '</span>';
        } else if (ps.needs_republish) {
            badges += '<span class="badge badge-warning">' + escapeHtml(t('ag_badge_republish', 'Republish required')) + '</span>';
        } else {
            badges += '<span class="badge badge-success">' + escapeHtml(t('ag_badge_live', 'Live · v{version}').replace('{version}', String(ps.version))) + '</span>';
            if (ps.has_unpublished_changes || state.dirty) {
                badges += ' <span class="badge badge-warning">' + escapeHtml(t('ag_badge_unpublished', 'Unpublished changes')) + '</span>';
            }
        }
        badgesEl.innerHTML = badges;

        if (metaEl) {
            var urls = data.public_urls || state.publicUrls || {};
            var parts = [];
            if (ps.last_published_at) {
                parts.push(t('ag_last_published', 'Last published') + ': ' + formatDateTime(ps.last_published_at));
            }
            if (urls.well_known_url) {
                parts.push('<code class="ap-status-url">' + escapeHtml(urls.well_known_url) + '</code>');
            }
            metaEl.innerHTML = parts.join(' · ');
        }

        if (setupBtn) setupBtn.hidden = false;
        if (statusPubBtn) {
            var showPub = ps.is_live && (ps.has_unpublished_changes || state.dirty);
            statusPubBtn.hidden = !showPub;
        }
    }

    function updateStickyBar(data) {
        var ps = getPublishState(data);
        var msgEl = document.getElementById('ai-presence-message');
        var pubBtn = document.getElementById('ai-presence-publish-btn');
        var unpublishBtn = document.getElementById('ai-presence-unpublish-btn');
        if (msgEl) {
            if (ps.never_published) {
                msgEl.hidden = true;
                msgEl.textContent = '';
                msgEl.className = 'main-actions-message message';
            } else if (ps.has_unpublished_changes || state.dirty) {
                msgEl.hidden = false;
                msgEl.style.display = 'block';
                msgEl.textContent = t('ag_sticky_unpublished', 'You have unpublished changes.');
                msgEl.className = 'main-actions-message message message-warning';
            } else if (ps.is_live) {
                msgEl.hidden = false;
                msgEl.style.display = 'block';
                msgEl.textContent = t('ag_sticky_published', 'Published at {time} — no unpublished changes.')
                    .replace('{time}', formatDateTime(ps.last_published_at));
                msgEl.className = 'main-actions-message message message-success';
            } else {
                msgEl.hidden = false;
                msgEl.style.display = 'block';
                msgEl.textContent = t('ag_essentials_publish_off', 'Public publishing is turned off.');
                msgEl.className = 'main-actions-message message message-warning';
            }
        }
        if (pubBtn) {
            var disablePub = state.publishingInProgress || (!ps.never_published && !ps.has_unpublished_changes && !state.dirty);
            pubBtn.disabled = disablePub || !state.canEdit;
            pubBtn.textContent = ps.is_live && (ps.has_unpublished_changes || state.dirty)
                ? t('ag_publish_updates', 'Publish updates')
                : t('publish_ai_presence', 'Publish');
        }
        if (unpublishBtn) {
            var showUnpublish = !ps.never_published && (ps.is_live || ps.publishing_enabled);
            unpublishBtn.hidden = !showUnpublish;
            unpublishBtn.disabled = state.publishingInProgress || !state.canEdit;
        }
    }

    function showPublishSuccess(data, isFirst) {
        var ps = getPublishState(data);
        var urls = data.public_urls || state.publicUrls || {};
        var url = urls.well_known_url || '';
        var metaText = t('ag_wizard_success_meta', 'Version {version} · Published {time}')
            .replace('{version}', String(ps.version))
            .replace('{time}', formatDateTime(ps.last_published_at));

        if (isFirst) {
            var modal = document.getElementById('ap-publish-success-modal');
            var urlEl = document.getElementById('ap-success-url');
            var metaEl = document.getElementById('ap-success-meta');
            var openEl = document.getElementById('ap-success-open');
            if (urlEl) urlEl.textContent = url;
            if (metaEl) metaEl.textContent = metaText;
            if (openEl && url) openEl.href = url;
            openApModal('ap-publish-success-modal');
        } else {
            var banner = document.getElementById('ap-publish-banner');
            var titleEl = document.getElementById('ap-publish-banner-title');
            var bannerMeta = document.getElementById('ap-publish-banner-meta');
            var bannerOpen = document.getElementById('ap-publish-banner-open');
            if (titleEl) titleEl.textContent = isFirst
                ? t('ag_wizard_success_title', 'Agent Guide is live')
                : t('ag_publish_updated_title', 'Agent Guide updated');
            if (bannerMeta) bannerMeta.textContent = metaText;
            if (bannerOpen && url) bannerOpen.href = url;
            if (banner) banner.hidden = false;
        }
        toast(t('ag_publish_toast', 'Agent Guide published'), 'success');
    }

    function renderEssentials(data) {
        var statusEl = document.getElementById('ap-essentials-publish-status');
        if (statusEl) {
            var ps = getPublishState(data || { meta: state.meta, publish_state: state.publishState });
            var statusText;
            if (!ps.publishing_enabled) {
                statusText = t('ag_essentials_publish_off', 'Public publishing is turned off — use Unpublish or enable publishing in Advanced → Publish.');
            } else if (ps.never_published) {
                statusText = t('ag_essentials_publish_never', 'Not published yet. Review your content, then publish from the top of the page.');
            } else if (ps.has_unpublished_changes || state.dirty) {
                statusText = t('ag_essentials_publish_pending', 'Published, but you have unpublished changes.');
            } else {
                statusText = t('ag_essentials_publish_live', 'Published and up to date · v{version}').replace('{version}', String(ps.version));
            }
            statusEl.textContent = statusText;
        }
        var sourcesList = document.getElementById('ap-essentials-sources-list');
        var sourcesEmpty = document.getElementById('ap-essentials-sources-empty');
        var claimsList = document.getElementById('ap-essentials-claims-list');
        var claimsEmpty = document.getElementById('ap-essentials-claims-empty');
        var sources = (state.draft && state.draft.sources_of_truth) || [];
        if (sourcesList) {
            sourcesList.innerHTML = '';
            sources.slice(0, 3).forEach(function (src) {
                var li = document.createElement('li');
                li.innerHTML = '<strong>' + escapeHtml(src.label || src.type) + '</strong> ' +
                    '<span class="badge">' + escapeHtml(src.role || 'reference') + '</span>';
                sourcesList.appendChild(li);
            });
            if (sourcesEmpty) sourcesEmpty.hidden = sources.length > 0;
            sourcesList.hidden = sources.length === 0;
        }
        var claims = (state.draft && state.draft.claims) || [];
        if (claimsList) {
            claimsList.innerHTML = '';
            claims.slice(0, 3).forEach(function (claim) {
                var li = document.createElement('li');
                li.innerHTML = '<strong>' + escapeHtml(claim.statement || 'Untitled') + '</strong> ' +
                    '<span class="badge">' + escapeHtml(claim.status || 'pending_review') + '</span>';
                claimsList.appendChild(li);
            });
            if (claimsEmpty) claimsEmpty.hidden = claims.length > 0;
            claimsList.hidden = claims.length === 0;
        }
    }

    // P60 IA: the Essentials/Advanced split is gone. The Guide is a single
    // experience with a vertical sub-nav (Overview, Essentials, Sources, …).
    // showGuideTab() ensures the Guide zone is active and selects a sub-tab.
    function showGuideTab(tab) {
        switchZone('guide');
        switchTab(tab || 'overview');
    }

    function initWizard() {
        if (!global.AgentGuideWizard) return;
        state.wizard = new global.AgentGuideWizard({
            getDraft: function () { return state.draft; },
            setDraft: function (d) { state.draft = d; state.dirty = true; },
            getCollections: function () { return state.collections; },
            getPublicUrls: function () { return state.publicUrls; },
            saveDraft: function () { return saveDraft(); },
            publish: function () {
                return publishInternal(false).then(function (res) {
                    return { meta: state.meta, version: state.meta && state.meta.published_version };
                });
            },
            onSetupStart: function () {
                postJson({ action: 'setup_start' }).catch(function () { /* optional */ });
            },
            onSkip: function () {
                return postJson({ action: 'setup_skip' }).then(function () {
                    showGuideTab('essentials');
                });
            },
            onComplete: function () {
                return postJson({ action: 'setup_complete', draft: state.draft }).then(function () {
                    showGuideTab('essentials');
                    return load();
                });
            },
            onPublishSuccess: function (result, fromWizard) {
                showPublishSuccess({ meta: state.meta, public_urls: state.publicUrls, publish_state: state.publishState }, fromWizard);
            },
            onPreview: function () {
                switchZone('publish');
            },
            loadEntries: function (collection, sel) {
                return loadEntries(collection).then(function () {
                    var entries = state.entriesByCollection[collection] || [];
                    sel.innerHTML = '<option value="">Select entry</option>';
                    entries.forEach(function (e) {
                        var opt = document.createElement('option');
                        opt.value = e.id;
                        opt.textContent = e.title || e.name || e.id;
                        sel.appendChild(opt);
                    });
                });
            },
            runCopilot: function (mode) {
                var connId = getSelectedAiConnection();
                return postJson({
                    action: 'copilot',
                    ai_connection_id: connId,
                    mode: mode || 'fill_gaps'
                }).then(function (res) {
                    if (res.status !== 'ok') throw new Error(res.message || 'Assist failed');
                    return res.data.suggestions || [];
                });
            },
            acceptSuggestion: function (suggestion) {
                return postJson({ action: 'accept_suggestion', suggestion: suggestion }).then(function (res) {
                    if (res.status !== 'ok') throw new Error(res.message || 'Accept failed');
                    state.draft = res.data.draft;
                    bindFields();
                    state.dirty = true;
                });
            },
            toast: toast,
            confirm: confirmAsync
        });
    }

    function openSetupWizard() {
        if (state.wizard) state.wizard.open();
    }

    function initPublishFeedback() {
        bindEl('ap-publish-banner-copy', 'click', function () {
            var openEl = document.getElementById('ap-publish-banner-open');
            if (openEl && openEl.href && navigator.clipboard) {
                navigator.clipboard.writeText(openEl.href).then(function () {
                    toast(t('ag_wizard_url_copied', 'URL copied'), 'success');
                });
            }
        });
        document.querySelectorAll('.ap-publish-banner-dismiss').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var banner = document.getElementById('ap-publish-banner');
                if (banner) banner.hidden = true;
            });
        });
        bindEl('ap-success-copy', 'click', function () {
            var urlEl = document.getElementById('ap-success-url');
            if (urlEl && navigator.clipboard) {
                navigator.clipboard.writeText(urlEl.textContent.trim()).then(function () {
                    toast(t('ag_wizard_url_copied', 'URL copied'), 'success');
                });
            }
        });
        bindEl('ap-success-overview', 'click', function () {
            closeApModal('ap-publish-success-modal');
            showGuideTab('overview');
        });
        bindEl('ap-setup-guide-btn', 'click', openSetupWizard);
        bindEl('ap-status-publish-btn', 'click', function () {
            confirmAsync(t('ag_confirm_publish_updates', 'Publish updates to the live Agent Guide?')).then(function (ok) {
                if (ok) publish().catch(function (e) { toast(e.message, 'error'); });
            });
        });
        bindEl('ap-essentials-publish-btn', 'click', function () {
            confirmAsync(t('ag_confirm_publish', 'Publish Agent Guide to public endpoints?')).then(function (ok) {
                if (ok) publish().catch(function (e) { toast(e.message, 'error'); });
            });
        });
        bindEl('ap-essentials-save-draft', 'click', function () {
            saveDraft().catch(function (e) { toast(e.message, 'error'); });
        });
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
                    var hint = r.status >= 500
                        ? 'Server error (' + r.status + '). Check PHP error logs or try again.'
                        : (r.status === 504 ? 'Request timed out (504). Try again.' : 'Empty server response (HTTP ' + r.status + ').');
                    throw new Error(hint);
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
                toast(t('ag_js_ai_cancelled', 'AI request cancelled'), 'info');
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
                toast(t('ag_js_invalid_json', 'Invalid JSON in {path}', { path: path }), 'error');
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
        syncFieldPeers(path, el);
        renderStatusStrip({ meta: state.meta, public_urls: state.publicUrls, publish_state: state.publishState });
        updateStickyBar({ meta: state.meta, publish_state: state.publishState });
        renderEssentials({ meta: state.meta, publish_state: state.publishState });
    }

    // Some fields (e.g. publication_settings.enabled) are bound in more than
    // one place — the top-of-page switch and the Advanced > Publish tab
    // checkbox. Keep every bound element for a path in sync when one changes.
    function syncFieldPeers(path, sourceEl) {
        document.querySelectorAll('[data-field="' + path + '"]').forEach(function (peer) {
            if (peer !== sourceEl) writeFieldEl(peer);
        });
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
        document.querySelectorAll('.ap-guide-nav [data-tab]').forEach(function (btn) {
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
        var nav = root.querySelector('.ap-guide-nav');
        if (nav) {
            nav.setAttribute('role', 'tablist');
            nav.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-tab]');
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
            label.textContent = (c.percent || 0) + '% complete';
        }

        var cards = document.getElementById('ai-presence-status-cards');
        if (cards) {
            cards.innerHTML = '';
            (c.items || []).forEach(function (item) {
                var div = document.createElement('div');
                div.className = 'stat' + (item.complete ? ' ap-stat-complete' : '');
                div.innerHTML = '<span class="stat-label">' + escapeHtml(item.label) + '</span>' +
                    '<span class="stat-value u-text-lg u-numeric">' + item.score + '/' + item.max + '</span>';
                cards.appendChild(div);
            });
        }

        var stepsEl = document.getElementById('ai-presence-next-steps');
        if (stepsEl) {
            stepsEl.innerHTML = '';
            var steps = data.next_steps || [];
            if (!steps.length) {
                var doneLi = document.createElement('li');
                doneLi.className = 'u-fg-success';
                doneLi.textContent = t('ag_overview_ready', 'All checklist items complete — ready to publish.');
                stepsEl.appendChild(doneLi);
            } else {
                var ps = getPublishState(data);
                if (ps.never_published) {
                    var wizLi = document.createElement('li');
                    var wizBtn = document.createElement('button');
                    wizBtn.type = 'button';
                    wizBtn.className = 'btn-link';
                    wizBtn.textContent = t('ag_overview_start_wizard', 'Run guided setup to publish in ~15 minutes');
                    wizBtn.addEventListener('click', openSetupWizard);
                    wizLi.appendChild(wizBtn);
                    stepsEl.appendChild(wizLi);
                }
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
                    '<dt>Agent Guide</dt><dd><code>' + escapeHtml(urls.well_known_url || '—') + '</code></dd>' +
                    (urls.legacy_well_known_url ? '<dt>Legacy well-known</dt><dd><code>' + escapeHtml(urls.legacy_well_known_url) + '</code></dd>' : '') +
                    '<dt>Public API</dt><dd><code>' + escapeHtml(urls.api_url || '—') + '</code></dd>' +
                    (urls.legacy_api_url ? '<dt>Legacy API</dt><dd><code>' + escapeHtml(urls.legacy_api_url) + '</code></dd>' : '') +
                    '<dt>Last published</dt><dd>' +
                    escapeHtml((data.meta && data.meta.last_published_at) || 'Never') + '</dd>';
            }
        }
    }

    function renderSources() {
        ensureSourcesArray();
        var list = document.getElementById('ap-sources-list');
        var empty = document.getElementById('ap-sources-empty');
        var countEl = document.getElementById('ap-sources-count');
        if (!list) return;
        list.innerHTML = '';
        var sources = state.draft.sources_of_truth || [];
        if (countEl) countEl.textContent = String(sources.length);
        if (empty) empty.hidden = sources.length > 0;
        list.hidden = sources.length === 0;
        sources.forEach(function (source, idx) {
            var li = document.createElement('li');
            li.className = 'card card--sunken u-row-3 u-p-3';
            var detail = source.url || '';
            if (!detail && source.media_id) detail = 'Media: ' + source.media_id;
            if (!detail && source.ref) {
                detail = (source.ref.collection || '') + (source.ref.entry_id ? ' / ' + source.ref.entry_id : '');
            }
            li.innerHTML =
                '<div class="ap-source-card-icon" aria-hidden="true">' + escapeHtml(sourceTypeIcon(source.type)) + '</div>' +
                '<div class="u-fill u-min-w-0">' +
                '<p class="u-truncate u-text-sm u-weight-semibold u-m-0">' + escapeHtml(source.label || sourceTypeLabel(source.type)) + '</p>' +
                '<p class="u-truncate u-text-xs u-fg-secondary u-m-0">' + escapeHtml(sourceTypeLabel(source.type)) + ' · ' +
                escapeHtml(source.role || 'reference') +
                (detail ? ' · ' + escapeHtml(detail) : '') + '</p></div>' +
                (state.canEdit ?
                    '<div class="u-cluster u-gap-1">' +
                    '<button type="button" class="btn-icon ap-remove-source" data-idx="' +
                    idx + '" aria-label="Remove source" title="Remove">' + AP_ICONS.trash + '</button></div>' : '');
            list.appendChild(li);
        });
        renderConnected();
    }

    function renderConnected() {
        var list = document.getElementById('ap-connected-list');
        if (!list) return;
        list.innerHTML = '';
        (state.draft.connected_content || []).forEach(function (ref, idx) {
            var li = document.createElement('li');
            li.innerHTML = '<strong>' + escapeHtml(ref.label || ref.collection || ref.entry_id) + '</strong> ' +
                '<span class="badge">' + escapeHtml(ref.role || 'reference') + '</span> ' +
                '<span class="u-fg-secondary">(' + escapeHtml(ref.ref_type) + ')</span>' +
                (state.canEdit ? ' <button type="button" class="btn-link ap-remove-connected" data-idx="' +
                    idx + '">Remove</button>' : '');
            list.appendChild(li);
        });
    }

    function renderClaimDetail() {
        var el = document.getElementById('ap-claim-detail');
        if (!el) return;
        if (!state.selectedClaimId) {
            el.textContent = t('ag_js_select_claim', 'Select a claim to view details.');
            el.className = 'u-text-sm u-fg-secondary';
            return;
        }
        var claim = (state.draft.claims || []).find(function (c) { return c.id === state.selectedClaimId; });
        if (!claim) {
            el.textContent = t('ag_js_claim_not_found', 'Claim not found.');
            return;
        }
        el.className = 'u-stack-2 u-text-sm';
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
        if (claim.notes) html += '<p class="u-fg-secondary">' + escapeHtml(claim.notes) + '</p>';
        var srcCount = (claim.sources || []).length;
        if (srcCount) html += '<p class="u-fg-secondary">' + srcCount + ' direct source link(s)</p>';
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
                    '<div class="u-cluster u-gap-2 u-mt-2">' +
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
                (ev.rating ? ' <span class="u-fg-secondary">' + escapeHtml(String(ev.rating)) + '★</span>' : '') +
                (ev.link_status && ev.link_status !== 'unknown' ?
                    ' <span class="badge">' + escapeHtml(ev.link_status) + '</span>' : '') +
                (state.canEdit ?
                    '<div class="u-cluster u-gap-2 u-mt-2">' +
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
            card.className = 'card u-overflow-hidden';
            var thumbUrl = ctx.media_url || (ctx.media_id ? '/media/' + encodeURIComponent(ctx.media_id) : '');
            card.innerHTML =
                (thumbUrl ? '<img class="ap-media-card-thumb" src="' + escapeHtml(thumbUrl) + '" alt="">' :
                    '<div class="ap-media-card-thumb"></div>') +
                '<div class="u-p-3 u-stack-2">' +
                '<strong>' + escapeHtml(ctx.media_name || ctx.media_id || 'Media') + '</strong> ' +
                '<span class="badge">' + escapeHtml(rep) + '</span>' +
                (ctx.description ? '<p>' + escapeHtml(ctx.description) + '</p>' : '') +
                (state.canEdit ?
                    '<div class="u-cluster u-gap-2 u-mt-2">' +
                    '<button type="button" class="btn btn-sm btn-secondary ap-edit-media" data-idx="' + idx + '">Edit</button> ' +
                    '<button type="button" class="btn btn-sm btn-secondary ap-remove-media" data-idx="' + idx + '">Remove</button>' +
                    '</div>' : '') +
                '</div>';
            grid.appendChild(card);
        });
    }

    function renderPublishStatus(data) {
        var el = document.getElementById('ap-publish-status');
        var ps = getPublishState(data);
        if (el) {
            if (ps.never_published) {
                el.textContent = t('ag_publish_never', 'Not published yet. Enable publishing and use Publish when ready.');
            } else if (ps.has_unpublished_changes || state.dirty) {
                el.textContent = t('ag_publish_has_draft', 'Live version {v} — draft has unpublished changes.')
                    .replace('{v}', String(ps.version));
            } else {
                el.textContent = t('ag_publish_in_sync', 'Live version {v} — draft matches published snapshot.')
                    .replace('{v}', String(ps.version));
            }
        }
        var warn = document.getElementById('ap-staleness-warnings');
        if (warn) {
            warn.innerHTML = '';
            (data.staleness || []).forEach(function (w) {
                var p = document.createElement('p');
                p.className = 'callout callout-warning callout--block u-mb-2';
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
        list.className = 'ap-version-timeline';
        if (!state.versions.length) {
            list.innerHTML = '<li class="u-fg-secondary">' + escapeHtml(t('ag_no_versions', 'No published versions yet.')) + '</li>';
            return;
        }
        state.versions.forEach(function (v) {
            var li = document.createElement('li');
            li.className = 'ap-version-timeline-item';
            var when = v.published_at ? formatDateTime(v.published_at) : '—';
            var summary = v.summary ? ' · ' + escapeHtml(v.summary) : '';
            var urls = state.publicUrls || {};
            var jsonUrl = urls.well_known_url || '';
            li.innerHTML =
                '<div class="ap-version-timeline-marker" aria-hidden="true"></div>' +
                '<div class="ap-version-timeline-body">' +
                '<strong>' + escapeHtml(t('ag_version_label', 'Version {n}').replace('{n}', String(v.version))) + '</strong>' +
                '<span class="u-fg-secondary">' + escapeHtml(when) + summary + '</span>' +
                (jsonUrl ? ' <button type="button" class="btn-link ap-version-open-json" data-version="' +
                    escapeHtml(String(v.version)) + '">' + escapeHtml(t('ag_view_json', 'View JSON')) + '</button>' : '') +
                '</div>';
            list.appendChild(li);
        });
        list.querySelectorAll('.ap-version-open-json').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var urls = state.publicUrls || {};
                if (urls.well_known_url) {
                    global.open(urls.well_known_url, '_blank', 'noopener');
                }
            });
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
            card.className = 'card u-p-4 u-stack-2';
            card.innerHTML =
                '<h4>' + escapeHtml(prod.name || 'Unnamed') + '</h4>' +
                '<span class="badge">' + escapeHtml(prod.type || 'product') + '</span>' +
                (prod.category ? ' <span class="u-fg-secondary">' + escapeHtml(prod.category) + '</span>' : '') +
                (prod.media_url || prod.external_image_url ?
                    '<img class="ap-product-card-thumb" src="' +
                    escapeHtml(prod.media_url || prod.external_image_url) + '" alt="">' : '') +
                '<p class="u-text-sm u-fg-secondary u-clamp-3 u-m-0">' + escapeHtml(prod.short_description || prod.full_description || '') + '</p>' +
                (prod.price_display ? '<p class="u-text-sm u-weight-medium u-m-0">' + escapeHtml(prod.price_display) + '</p>' : '') +
                (countProductSources(prod) ?
                    '<p class="u-text-xs u-fg-tertiary u-m-0">' + countProductSources(prod) + ' linked source(s)</p>' : '') +
                (state.canEdit ?
                    '<div class="u-cluster u-gap-1 u-mt-auto u-pt-2">' +
                    '<button type="button" class="btn-icon ap-edit-product" data-idx="' + idx +
                    '" aria-label="Edit" title="Edit">' + AP_ICONS.pencil + '</button>' +
                    '<button type="button" class="btn-icon ap-delete-product" data-idx="' + idx +
                    '" aria-label="Delete" title="Delete">' + AP_ICONS.trash + '</button></div>' : '');
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
                (rel.description ? '<p class="u-fg-secondary">' + escapeHtml(rel.description) + '</p>' : '') +
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
            row.className = 'u-row-2 u-mb-2 ap-social-row';
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
            list.innerHTML = '<li class="u-fg-success">' + escapeHtml(inSyncMsg) + '</li>';
            return;
        }
        state.cmsDiff.forEach(function (diff) {
            var li = document.createElement('li');
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
            sources: ['sources_of_truth'],
            context: ['identity', 'description', 'positioning', 'terminology'],
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
        var acceptAll = document.getElementById('ap-accept-all-suggestions');
        if (acceptAll) acceptAll.hidden = count === 0;
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
            li.className = 'card card--sunken u-p-3 u-stack-2';
            var valPreview = typeof s.value === 'object' ? JSON.stringify(s.value) : String(s.value || '');
            if (valPreview.length > 180) valPreview = valPreview.slice(0, 180) + '…';
            li.innerHTML =
                '<strong>' + escapeHtml(s.label || s.field) + '</strong> ' +
                '<span class="badge">' + escapeHtml(s.confidence || 'medium') + '</span>' +
                '<p>' + escapeHtml(valPreview) + '</p>' +
                (s.source ? '<div class="u-text-xs u-fg-tertiary">Source: ' + escapeHtml(s.source) + '</div>' : '') +
                (state.canEdit ?
                    '<div class="u-cluster u-gap-2">' +
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
            hint.textContent = t('ag_js_no_collections_match', 'No collections match your search');
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
        renderStatusStrip(data);
        updateStickyBar(data);
        renderOverview(data);
        renderEssentials(data);
        renderSources();
        initFieldAiButtons();
        renderSocial();
        renderRelationships();
        renderProducts();
        renderClaims();
        renderEvidence();
        renderMedia();
        renderPublishStatus(data);
        renderCmsDiff();
        renderAssistSuggestions();
        updateSpine();
        renderPublishZone();
    }

    function initWorkflowNav() {
        document.querySelectorAll('.ap-workflow-step').forEach(function (step) {
            step.addEventListener('click', function () {
                var tab = step.getAttribute('data-workflow');
                if (!tab) return;
                if (tab === 'publish') {
                    switchZone('publish');
                } else {
                    switchTab(tab);
                }
            });
        });
    }

    function initAboutModal() {
        bindEl('ap-about-agent-guide-btn', 'click', function () {
            openApModal(ABOUT_MODAL_ID);
        });
        var modal = document.getElementById(ABOUT_MODAL_ID);
        if (modal) {
            modal.querySelectorAll('[data-ap-modal-close="' + ABOUT_MODAL_ID + '"]').forEach(function (el) {
                el.addEventListener('click', function () {
                    closeApModal(ABOUT_MODAL_ID);
                });
            });
        }
        // NOTE: the first-visit auto-open used to happen here, synchronously,
        // before load() resolves. That raced with maybeShowSetup()'s wizard
        // auto-open (fired 400ms after data loads) and the two stacked on top
        // of each other on a genuinely first-time page. See
        // maybeAutoShowAboutModal(), called once we know whether the wizard
        // is about to take that job instead.
    }

    function aboutModalSeen() {
        try { return localStorage.getItem(ABOUT_MODAL_SEEN_KEY) === '1'; } catch (e) { return false; }
    }

    function markAboutModalSeen() {
        try { localStorage.setItem(ABOUT_MODAL_SEEN_KEY, '1'); } catch (e) { /* ignore */ }
    }

    function shouldAutoOpenSetupWizard(data) {
        var draft = (data && data.draft) || state.draft || {};
        var empty = !(draft.identity && draft.identity.official_name);
        return !draft.setup_completed_at && !draft.setup_skipped_at && empty && !!state.wizard;
    }

    // First visit: show the explainer modal; guided setup opens after it closes.
    function maybeAutoShowAboutModal() {
        if (aboutModalSeen()) return;
        openApModal(ABOUT_MODAL_ID);
    }

    function scrollAssistResults() {
        var el = document.getElementById('ap-assist-results');
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    // Inside Discovery the editor is embedded on the Agent Guide tab only; its first-visit
    // explainer and setup wizard wait until that tab is showing.
    function agentGuideVisible() {
        var embed = document.getElementById('disc-ap-embed');
        if (!embed) return true;
        var tab = document.getElementById('disc-tab-agent-guide');
        return !embed.hidden && (!tab || tab.getAttribute('aria-selected') === 'true');
    }

    function maybeShowSetup(data) {
        if (!agentGuideVisible()) {
            state.deferredSetupData = data;
            if (!state.setupListenerBound) {
                state.setupListenerBound = true;
                document.addEventListener('birkly:discovery-zone', function (event) {
                    if (event.detail && event.detail.zone === 'agent-guide' && state.deferredSetupData && agentGuideVisible()) {
                        var pending = state.deferredSetupData;
                        state.deferredSetupData = null;
                        maybeShowSetup(pending);
                    }
                });
            }
            return;
        }
        state.pendingWizardAfterIntro = shouldAutoOpenSetupWizard(data);
        maybeAutoShowAboutModal();
        if (state.pendingWizardAfterIntro && aboutModalSeen()) {
            state.pendingWizardAfterIntro = false;
            setTimeout(function () {
                openSetupWizard();
            }, 400);
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
                state.publishState = data.publish_state || getPublishState(data);
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

    function saveDraft(options) {
        options = options || {};
        return postJson({ action: 'save_draft', draft: state.draft }).then(function (res) {
            if (res.status !== 'ok') throw new Error(res.message || 'Save failed');
            state.draft = res.data.draft;
            state.dirty = false;
            if (!options.silent) {
                toast(t('ag_js_draft_saved', 'Draft saved'), 'success');
            }
            if (options.skipReload) {
                return res;
            }
            return load().then(function () {
                updateStickyBar({ meta: state.meta, publish_state: state.publishState });
                return res;
            });
        });
    }

    function publishInternal(showFeedback) {
        if (state.publishingInProgress) return Promise.reject(new Error(t('ag_publish_in_progress', 'Publish already in progress')));
        state.publishingInProgress = true;
        var wasNeverPublished = getPublishState({ publish_state: state.publishState, meta: state.meta }).never_published;
        updateStickyBar({ meta: state.meta, publish_state: state.publishState });
        var savePromise = state.dirty
            ? saveDraft({ silent: true, skipReload: true })
            : Promise.resolve();
        return savePromise.then(function () {
            return postJson({ action: 'publish' }).then(function (res) {
                if (res.status !== 'ok') throw new Error(res.message || 'Publish failed');
                return load().then(function () {
                    if (showFeedback !== false) {
                        showPublishSuccess({
                            meta: state.meta,
                            public_urls: state.publicUrls,
                            publish_state: state.publishState
                        }, wasNeverPublished);
                    }
                    notifyDiscoveryRefresh();
                    return res;
                });
            });
        }).finally(function () {
            state.publishingInProgress = false;
            state.dirty = false;
            updateStickyBar({ meta: state.meta, publish_state: state.publishState });
        });
    }

    function publish() {
        return publishInternal(true);
    }

    function unpublish() {
        if (state.publishingInProgress) {
            return Promise.reject(new Error(t('ag_publish_in_progress', 'Publish already in progress')));
        }
        state.publishingInProgress = true;
        updateStickyBar({ meta: state.meta, publish_state: state.publishState });
        return postJson({ action: 'unpublish' }).then(function (res) {
            if (res.status !== 'ok') {
                throw new Error(res.message || 'Unpublish failed');
            }
            if (state.draft && state.draft.publication_settings) {
                state.draft.publication_settings.enabled = false;
            }
            state.meta = res.data.meta || state.meta;
            state.publishState = res.data.publish_state || getPublishState({ meta: state.meta });
            state.dirty = false;
            refreshAllRenders({
                draft: state.draft,
                meta: state.meta,
                publish_state: state.publishState
            });
            toast(t('ag_unpublish_toast', 'Agent Guide unpublished'), 'success');
            notifyDiscoveryRefresh();
            return res;
        }).finally(function () {
            state.publishingInProgress = false;
            updateStickyBar({ meta: state.meta, publish_state: state.publishState });
        });
    }

    function runCopilot(mode) {
        var connId = getSelectedAiConnection();
        if (!connId && mode !== 'fill_gaps') {
            toast(t('ag_js_select_connection_or_fill_gaps', 'Select an AI connection for this action, or use Fill gaps for basic suggestions'), 'warning');
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
                    toast(t('ag_js_suggestions_count', '{n} suggestion(s) to review', { n: state.suggestions.length }), 'info');
                } else if (!res.data.ai_error) {
                    toast(t('ag_js_no_suggestions_hint', 'No suggestions — try linking CMS content or running Auto-fill'), 'info');
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
            toast(t('ag_js_suggestion_accepted', 'Suggestion accepted'), 'success');
        });
    }

    function acceptAllSuggestions() {
        if (!state.suggestions.length) return Promise.resolve();
        return confirmAsync(t('ag_confirm_accept_all', 'Accept all {n} suggestions? Review each change before confirming.')
            .replace('{n}', String(state.suggestions.length))).then(function (ok) {
            if (!ok) return;
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
                    refreshAllRenders({ draft: state.draft, meta: state.meta, staleness: [], publish_state: state.publishState });
                    toast(t('ag_js_draft_proposal_applied', 'Draft proposal applied'), 'success');
                });
            }
            return postJson({ action: 'accept_suggestions', suggestions: state.suggestions })
                .then(function (res) {
                    if (res.status !== 'ok') throw new Error(res.message || 'Accept all failed');
                    state.draft = res.data.draft;
                    state.suggestions = [];
                    bindFields();
                    refreshAllRenders({ draft: state.draft, meta: state.meta, staleness: [], publish_state: state.publishState });
                    toast(t('ag_js_all_suggestions_accepted', 'All suggestions accepted'), 'success');
                });
        });
    }

    function runFieldAi(field) {
        var connId = getSelectedAiConnection();
        if (!connId) {
            toast(t('ag_js_select_connection_sidebar', 'Select an AI connection in the sidebar first'), 'warning');
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
                    toast(t('ag_js_no_suggestions_field', 'No suggestions returned for this field'), 'info');
                    return;
                }
                var suggestion = suggestions[0];
                return postJson({ action: 'accept_suggestion', suggestion: suggestion }).then(function (acceptRes) {
                    if (acceptRes.status !== 'ok') throw new Error(acceptRes.message || 'Apply failed');
                    state.draft = acceptRes.data.draft;
                    bindFields();
                    state.dirty = true;
                    toast(t('ag_js_field_updated', 'Field updated'), 'success');
                });
            })
            .finally(function () {
                if (btn) btn.disabled = false;
            });
    }

    function assistAutoFill() {
        var connId = getSelectedAiConnection();
        if (!connId) {
            toast(t('ag_js_select_connection_sidebar', 'Select an AI connection in the sidebar first'), 'warning');
            openAssistSidebar();
            return Promise.resolve();
        }
        if (!state.collections.length) {
            toast(t('ag_js_no_collections', 'No CMS collections found — create content collections first'), 'warning');
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
                    toast(t('ag_js_proposals_ready', '{n} proposal(s) ready to review', { n: state.suggestions.length }), 'success');
                } else {
                    toast(t('ag_js_no_proposals', 'No proposals generated'), 'info');
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
            toast(t('ag_js_select_one_collection', 'Select at least one collection'), 'warning');
            return Promise.resolve();
        }
        var connId = getSelectedAiConnection();
        if (!connId) {
            toast(t('ag_js_select_connection_cms', 'Select an AI connection for CMS generation'), 'warning');
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
                toast(t('ag_js_proposals_ready', '{n} proposal(s) ready to review', { n: state.suggestions.length }), 'success');
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
            toast(t('ag_js_select_connection_sidebar', 'Select an AI connection in the sidebar first'), 'warning');
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
                    toast(t('ag_js_proposals_profile', '{n} proposal(s) for a complete profile — review and accept', { n: state.suggestions.length }), 'success');
                } else {
                    toast(t('ag_js_no_proposals_context', 'No proposals generated — try Fill gaps or add more context first'), 'info');
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
            toast(t('ag_js_media_picker_unavailable', 'Media picker unavailable — reload the page'), 'error');
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

    function buildProductPriceSource() {
        var typeEl = document.getElementById('ap-product-price-source-type');
        var type = typeEl ? typeEl.value : 'manual';
        if (type === 'manual') {
            return { type: 'manual' };
        }
        if (type === 'external') {
            return {
                type: 'external',
                url: (document.getElementById('ap-product-price-url').value || '').trim()
            };
        }
        return {
            type: 'cms_field',
            collection: (document.getElementById('ap-product-price-collection').value || '').trim(),
            entry_id: (document.getElementById('ap-product-price-entry').value || '').trim(),
            field: (document.getElementById('ap-product-price-field').value || '').trim()
        };
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
        document.getElementById('ap-product-price-display').value = prod ? (prod.price_display || '') : '';
        var priceSource = prod && prod.price_source ? prod.price_source : {};
        var priceTypeEl = document.getElementById('ap-product-price-source-type');
        if (priceTypeEl) priceTypeEl.value = priceSource.type || 'manual';
        populateSourceCollectionSelect(document.getElementById('ap-product-price-collection'));
        document.getElementById('ap-product-price-field').value = priceSource.field || '';
        document.getElementById('ap-product-price-url').value = priceSource.url || '';
        if (priceSource.collection) {
            document.getElementById('ap-product-price-collection').value = priceSource.collection;
            loadCollectionEntries(priceSource.collection, document.getElementById('ap-product-price-entry')).then(function () {
                if (priceSource.entry_id) document.getElementById('ap-product-price-entry').value = priceSource.entry_id;
            });
        } else {
            var priceEntry = document.getElementById('ap-product-price-entry');
            if (priceEntry) priceEntry.innerHTML = '<option value="">Entry</option>';
        }
        toggleProductPriceSourceUi();
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
            price_display: document.getElementById('ap-product-price-display').value.trim(),
            price_source: buildProductPriceSource(),
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
        toast(t('ag_js_product_saved', 'Product saved — remember to save draft'), 'success');
    }

    function addProduct() {
        openProductModal(null);
    }

    function editProduct(idx) {
        openProductModal(idx);
    }

    function deleteProduct(idx) {
        confirmAsync(t('ag_js_confirm_delete_product', 'Delete this product/service?')).then(function (ok) {
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
            toast(t('ag_js_connect_cms_first', 'Connect CMS entries first (Context tab)'), 'warning');
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
            toast(t('ag_js_media_picker_unavailable', 'Media picker unavailable — reload the page'), 'error');
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
            var ps = state.publishState || getPublishState({ meta: state.meta });
            var msg = ps.is_live ? t('ag_confirm_publish_updates', 'Publish updates to the live Agent Guide?')
                : t('ag_confirm_publish', 'Publish Agent Guide to public endpoints?');
            confirmAsync(msg).then(function (ok) {
                if (ok) publish().catch(function (e) { toast(e.message, 'error'); });
            });
        });
        bindEl('ai-presence-unpublish-btn', 'click', function () {
            confirmAsync(t('ag_confirm_unpublish', 'Unpublish the Agent Guide? Public endpoints will stop serving your guide.'), { danger: true }).then(function (ok) {
                if (ok) unpublish().catch(function (e) { toast(e.message, 'error'); });
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
                var editBtn = e.target.closest('.ap-edit-product');
                var deleteBtn = e.target.closest('.ap-delete-product');
                if (!editBtn && !deleteBtn) return;
                var btn = editBtn || deleteBtn;
                var idx = parseInt(btn.getAttribute('data-idx'), 10);
                if (isNaN(idx)) return;
                if (editBtn) editProduct(idx);
                if (deleteBtn) deleteProduct(idx);
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

        var sourceTypeEl = document.getElementById('ap-source-type');
        if (sourceTypeEl) {
            /* hidden field synced by source mode tabs */
        }

        document.querySelectorAll('.ap-source-mode-tab').forEach(function (tab) {
            tab.addEventListener('click', function () {
                switchSourceMode(tab.getAttribute('data-source-mode') || 'cms');
            });
        });
        switchSourceMode('cms');

        bindEl('ap-add-cms-source', 'click', function () {
            ensureSourcesArray();
            var roleEl = document.getElementById('ap-source-role');
            var colEl = document.getElementById('ap-connect-collection');
            var entryEl = document.getElementById('ap-connect-entry');
            var col = colEl ? colEl.value : '';
            var entryId = entryEl ? entryEl.value : '';
            var role = roleEl ? roleEl.value : 'narrative';
            if (!col) return;
            var type = entryId ? 'cms_entry' : 'cms_collection';
            var label = entryId && entryEl.selectedOptions.length
                ? entryEl.selectedOptions[0].textContent : col;
            state.draft.sources_of_truth.push({
                id: newSourceId(),
                type: type,
                role: role,
                label: label,
                url: '',
                ref: {
                    collection: col,
                    entry_id: entryId || '',
                    slug: entryId || col,
                    label: label,
                    ref_type: type === 'cms_collection' ? 'collection' : 'entry'
                }
            });
            syncLegacyConnectedFromSources();
            renderSources();
            state.dirty = true;
            toast(t('ag_js_source_added', 'Source added'), 'success');
        });

        bindEl('ap-add-url-source', 'click', function () {
            ensureSourcesArray();
            var typeEl = document.getElementById('ap-source-url-type');
            var roleEl = document.getElementById('ap-source-role');
            var labelEl = document.getElementById('ap-source-label');
            var urlEl = document.getElementById('ap-source-url');
            var type = typeEl ? typeEl.value : 'website';
            var url = urlEl ? urlEl.value.trim() : '';
            if (!url) return;
            var label = labelEl && labelEl.value.trim() ? labelEl.value.trim() : url;
            state.draft.sources_of_truth.push({
                id: newSourceId(),
                type: type,
                role: roleEl ? roleEl.value : 'reference',
                label: label,
                url: url
            });
            if (labelEl) labelEl.value = '';
            if (urlEl) urlEl.value = '';
            renderSources();
            state.dirty = true;
            toast(t('ag_js_source_added', 'Source added'), 'success');
        });

        var sourcesList = document.getElementById('ap-sources-list');
        if (sourcesList) {
            sourcesList.addEventListener('click', function (e) {
                var btn = e.target.closest('.ap-remove-source');
                if (!btn) return;
                var idx = parseInt(btn.getAttribute('data-idx'), 10);
                ensureSourcesArray();
                state.draft.sources_of_truth.splice(idx, 1);
                syncLegacyConnectedFromSources();
                renderSources();
                state.dirty = true;
            });
        }

        var sourceFile = document.getElementById('ap-source-file');
        var uploadZone = document.getElementById('ap-source-upload-zone');
        if (sourceFile) {
            sourceFile.addEventListener('change', function () {
                var files = sourceFile.files ? Array.prototype.slice.call(sourceFile.files) : [];
                if (!files.length) return;
                Promise.all(files.map(function (file) { return uploadSourceDocument(file); }))
                    .finally(function () { sourceFile.value = ''; });
            });
        }
        if (uploadZone) {
            ['dragenter', 'dragover'].forEach(function (ev) {
                uploadZone.addEventListener(ev, function (e) {
                    e.preventDefault();
                    uploadZone.classList.add('is-dragover');
                });
            });
            ['dragleave', 'drop'].forEach(function (ev) {
                uploadZone.addEventListener(ev, function (e) {
                    e.preventDefault();
                    uploadZone.classList.remove('is-dragover');
                    if (ev === 'drop' && e.dataTransfer && e.dataTransfer.files) {
                        Promise.all(Array.prototype.map.call(e.dataTransfer.files, function (file) {
                            return uploadSourceDocument(file);
                        }));
                    }
                });
            });
        }

        bindEl('ap-product-price-source-type', 'change', toggleProductPriceSourceUi);
        bindEl('ap-product-price-collection', 'change', function (e) {
            loadCollectionEntries(e.target.value, document.getElementById('ap-product-price-entry'));
        });

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
                toast(t('ag_js_pick_media_first', 'Pick a media file first'), 'warning');
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
                    confirmAsync(t('ag_js_confirm_remove_media', 'Remove this media context?')).then(function (ok) {
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
                var tab = e.target.getAttribute('data-tab');
                if (tab === 'publish') {
                    switchZone('publish');
                } else {
                    showGuideTab(tab);
                }
            }
        });
    }

    // ===================================================================
    // Hub shell — zones, status spine, deep-linking (P60-M2)
    // ===================================================================

    function getInitialZone() {
        try {
            var params = new URLSearchParams(window.location.search);
            var z = (params.get('zone') || '').toLowerCase();
            // Legacy deep-link: Connections home is Settings → AI (P62-D2).
            if (z === 'connections') {
                window.location.replace(settingsAiUrl());
                return 'guide';
            }
            if (HUB_ZONES.indexOf(z) >= 0) return z;
        } catch (e) { /* ignore */ }
        return 'guide';
    }

    function setZoneInUrl(zone) {
        if (discoveryEmbed) return;
        try {
            var url = new URL(window.location.href);
            url.searchParams.set('zone', zone);
            window.history.replaceState({}, '', url.toString());
        } catch (e) { /* ignore */ }
    }

    function switchZone(zone, opts) {
        if (zone === 'connections') {
            window.location.assign(settingsAiUrl());
            return;
        }
        if (HUB_ZONES.indexOf(zone) < 0) zone = 'guide';
        opts = opts || {};
        state.hubZone = zone;

        document.querySelectorAll('.ap-hub .tab[data-hub-zone]').forEach(function (btn) {
            var active = btn.getAttribute('data-hub-zone') === zone;
            btn.classList.toggle('active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
            btn.tabIndex = active ? 0 : -1;
        });
        document.querySelectorAll('[data-hub-panel]').forEach(function (panel) {
            var active = panel.getAttribute('data-hub-panel') === zone;
            panel.classList.toggle('active', active);
            panel.hidden = !active;
        });

        // Sticky Publish bar stays visible on every zone (P60-M1 §2.2): it is the
        // one mint primary and the commit action for the relocated Publish controls.

        if (opts.updateUrl !== false) setZoneInUrl(zone);

        // Lazy-init heavier zones on first open.
        // WebMCP config is also prefetched on hub init so the Tools tab dot is correct.
        if (zone === 'tools' && !state.webmcpLoaded) {
            loadWebmcpConfig();
        }
        if (zone === 'publish') {
            renderPublishZone();
        }
    }

    function initHubZones() {
        var hub = document.querySelector('.ap-hub');
        if (!hub) return;

        if (discoveryEmbed) {
            var tablist = document.getElementById('ap-hub-tablist');
            if (tablist) tablist.hidden = true;
            hub.classList.add('ap-hub--discovery-embed');
        }

        document.addEventListener('click', function (e) {
            var zoneBtn = e.target.closest('[data-hub-zone]');
            if (zoneBtn && (zoneBtn.classList.contains('tab') ||
                zoneBtn.classList.contains('ap-goto-zone'))) {
                e.preventDefault();
                var z = zoneBtn.getAttribute('data-hub-zone');
                // Legacy Connections navigation → Settings → AI (P62-D2).
                if (z === 'connections') {
                    window.location.assign(settingsAiUrl());
                    return;
                }
                switchZone(z);
            }
        });

        var tablist = document.getElementById('ap-hub-tablist');
        if (tablist) {
            tablist.addEventListener('keydown', function (e) {
                var tabs = Array.prototype.slice.call(
                    tablist.querySelectorAll('.tab[data-hub-zone]')
                );
                if (!tabs.length) return;
                var current = document.activeElement;
                var idx = tabs.indexOf(current);
                if (idx < 0) return;
                var next = -1;
                if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
                    next = (idx + 1) % tabs.length;
                } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
                    next = (idx - 1 + tabs.length) % tabs.length;
                } else if (e.key === 'Home') {
                    next = 0;
                } else if (e.key === 'End') {
                    next = tabs.length - 1;
                }
                if (next < 0) return;
                e.preventDefault();
                tabs[next].focus();
                switchZone(tabs[next].getAttribute('data-hub-zone'));
            });
        }

        window.addEventListener('popstate', function () {
            switchZone(getInitialZone(), { updateUrl: false });
        });

        switchZone(getInitialZone(), { updateUrl: false });
    }

    function badge(el, text, tone) {
        if (!el) return;
        el.textContent = text;
        el.className = 'badge badge-' + (tone || 'neutral');
    }

    function setTabDot(id, ok, label) {
        var dot = document.getElementById(id);
        if (!dot) return;
        dot.setAttribute('data-ok', ok ? '1' : '0');
        // Mirror status onto the parent tab for screen readers.
        var tab = dot.closest('.tab');
        if (tab && label) {
            tab.setAttribute('title', label);
            tab.setAttribute('aria-description', label);
        }
    }

    function updateSpine() {
        var ps = getPublishState({ meta: state.meta, publish_state: state.publishState });
        // Guide tab: green when published & live; red otherwise.
        var guideOk = !!(ps.publishing_enabled && !ps.never_published);
        var guideLabel = !ps.publishing_enabled
            ? t('ag_status_off', 'Off')
            : (ps.never_published ? t('ag_status_draft', 'Draft') : t('ag_status_published', 'Published'));
        setTabDot('ap-tab-dot-guide', guideOk, guideLabel);

        // Tools tab: green when Website tools (WebMCP) are on.
        var cfg = state.webmcpConfig;
        var toolsOk = !!(cfg && cfg.enabled);
        setTabDot('ap-tab-dot-tools', toolsOk, toolsOk ? t('ag_webmcp_on', 'On') : t('ag_webmcp_off', 'Off'));

        // Publish tab: green when a public site mode is configured (project/external).
        var urls = state.publicUrls || {};
        var mode = urls.hosting_mode || 'none';
        var hostOk = mode === 'project' || mode === 'external';
        var hostLabel = mode === 'project' ? t('ag_hosted_project', 'On Birkly')
            : (mode === 'external' ? t('ag_hosted_external', 'External') : t('ag_hosted_none', 'None'));
        setTabDot('ap-tab-dot-publish', hostOk, hostLabel);
    }

    // ===================================================================
    // Tools zone — WebMCP master switch, catalog, allowlist, preview (P60-M6)
    // ===================================================================

    function webmcpApiUrl() {
        return apiUrl('/api/webmcp.php');
    }

    function webmcpSupportedInThisBrowser() {
        if (state.webmcpSupported !== null) return state.webmcpSupported;
        var mc = (typeof document !== 'undefined' && document.modelContext) ||
            (typeof navigator !== 'undefined' && navigator.modelContext) || null;
        state.webmcpSupported = !!(mc && ('registerTool' in mc));
        return state.webmcpSupported;
    }

    function loadWebmcpConfig() {
        return fetch(webmcpApiUrl() + '?action=get', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.status !== 'ok') throw new Error(res.message || t('ag_js_failed_load_tools', 'Failed to load tools config'));
                state.webmcpConfig = res.data.config || { enabled: false, tools: {}, form_allowlist: [] };
                state.webmcpCatalog = res.data.catalog || [];
                state.webmcpRuntimePacks = res.data.runtime_packs || [];
                state.webmcpPublicForms = res.data.public_forms || [];
                state.webmcpLoaded = true;
                renderTools();
                updateSpine();
                renderPublishZone();
            })
            .catch(function (err) {
                toast(err.message || t('ag_js_failed_load_tools', 'Failed to load tools config'), 'error');
                updateSpine();
            });
    }

    function saveWebmcp() {
        if (state.webmcpSaving) return Promise.resolve();
        state.webmcpSaving = true;
        return postJsonTo(webmcpApiUrl(), { action: 'save', config: state.webmcpConfig })
            .then(function (res) {
                if (res.status !== 'ok') throw new Error(res.message || 'Save failed');
                state.webmcpConfig = res.data.config || state.webmcpConfig;
                state.webmcpCatalog = res.data.catalog || state.webmcpCatalog;
                state.webmcpRuntimePacks = res.data.runtime_packs || state.webmcpRuntimePacks;
                renderTools();
                updateSpine();
                renderPublishZone();
                notifyDiscoveryRefresh();
                toast(t('ag_tools_saved', 'Website tools updated'), 'success');
            })
            .catch(function (err) {
                toast(err.message || 'Save failed', 'error');
                // Reload authoritative state on failure.
                return loadWebmcpConfig();
            })
            .finally(function () { state.webmcpSaving = false; });
    }

    function renderTools() {
        var cfg = state.webmcpConfig || { enabled: false, tools: {}, form_allowlist: [] };
        var master = document.getElementById('ap-webmcp-enabled');
        if (master) master.checked = !!cfg.enabled;

        var config = document.getElementById('ap-webmcp-config');
        if (config) config.hidden = !cfg.enabled;

        var supportNote = document.getElementById('ap-webmcp-support-note');
        if (supportNote) supportNote.hidden = webmcpSupportedInThisBrowser();

        if (cfg.enabled) {
            renderToolCatalog();
            renderAllowlist();
            renderToolsPreview();
        }
    }

    function renderToolCatalog() {
        var host = document.getElementById('ap-webmcp-catalog');
        if (!host) return;
        host.innerHTML = '';
        var cfg = state.webmcpConfig || { tools: {} };
        var byPack = {};
        var packOrder = [];
        (state.webmcpCatalog || []).forEach(function (tool) {
            var pack = tool.pack || tool.plugin_id || 'plugin';
            if (!byPack[pack]) {
                byPack[pack] = [];
                packOrder.push(pack);
            }
            byPack[pack].push(tool);
        });
        packOrder.sort(function (a, b) {
            if (a === 'core') return -1;
            if (b === 'core') return 1;
            return String(a).localeCompare(String(b));
        });
        packOrder.forEach(function (pack) {
            var tools = byPack[pack];
            if (!tools || !tools.length) return;
            var packMeta = webmcpPackMeta(pack);
            var group = document.createElement('div');
            group.className = 'card-section u-stack-2';
            var head = '<div class="u-split u-align-baseline"><h4 class="u-text-sm u-weight-semibold u-m-0">' +
                escapeHtml(packMeta.label) + '</h4></div>';
            if (packMeta.note) {
                head += '<p class="u-text-xs u-fg-secondary u-m-0">' + escapeHtml(packMeta.note) + '</p>';
            }
            group.innerHTML = head;
            var list = document.createElement('ul');
            list.className = 'data-list';
            tools.forEach(function (tool) {
                var label = tool.label || tool.id;
                var desc = tool.description || '';
                var li = document.createElement('li');
                li.className = 'data-list-item u-split u-align-start u-gap-3';
                var consent = webmcpConsentNote(tool);
                li.innerHTML =
                    '<div class="u-stack-1 u-fill">' +
                    '<strong class="u-text-sm">' + escapeHtml(label) + '</strong>' +
                    (desc ? '<span class="u-text-xs u-fg-secondary">' + escapeHtml(desc) + '</span>' : '') +
                    (consent ? '<span class="u-text-xs u-fg-tertiary">' + escapeHtml(consent) + '</span>' : '') +
                    '</div>' +
                    '<label class="switch"><input type="checkbox" class="ap-webmcp-tool" data-tool-id="' +
                    escapeHtml(tool.id) + '"' + (tool.enabled ? ' checked' : '') + '><span class="switch__slider"></span></label>';
                list.appendChild(li);
            });
            group.appendChild(list);
            host.appendChild(group);
        });

        host.querySelectorAll('.ap-webmcp-tool').forEach(function (input) {
            input.addEventListener('change', function () {
                var id = input.getAttribute('data-tool-id');
                if (!state.webmcpConfig.tools) state.webmcpConfig.tools = {};
                state.webmcpConfig.tools[id] = input.checked;
                saveWebmcp();
            });
        });
    }

    function renderAllowlist() {
        var host = document.getElementById('ap-webmcp-allowlist');
        var empty = document.getElementById('ap-webmcp-allowlist-empty');
        var warn = document.getElementById('ap-webmcp-allowlist-warn');
        if (!host) return;
        host.innerHTML = '';
        var forms = state.webmcpPublicForms || [];
        var cfg = state.webmcpConfig || { form_allowlist: [], tools: {} };
        var allow = cfg.form_allowlist || [];

        if (empty) empty.hidden = forms.length > 0;
        host.hidden = forms.length === 0;

        forms.forEach(function (form) {
            var row = document.createElement('label');
            row.className = 'u-row-2 u-text-sm';
            var checked = allow.indexOf(form.slug) >= 0;
            row.innerHTML = '<input type="checkbox" class="ap-webmcp-allow" data-slug="' + escapeHtml(form.slug) + '"' +
                (checked ? ' checked' : '') + '> <span>' + escapeHtml(form.name) +
                ' <code class="u-text-xs u-fg-secondary">' + escapeHtml(form.slug) + '</code></span>';
            host.appendChild(row);
        });

        host.querySelectorAll('.ap-webmcp-allow').forEach(function (input) {
            input.addEventListener('change', function () {
                var slug = input.getAttribute('data-slug');
                var list = state.webmcpConfig.form_allowlist || [];
                var idx = list.indexOf(slug);
                if (input.checked && idx < 0) list.push(slug);
                if (!input.checked && idx >= 0) list.splice(idx, 1);
                state.webmcpConfig.form_allowlist = list;
                saveWebmcp();
            });
        });

        // Warn if the submit tool is on but no forms are allowed.
        var submitOn = !!(cfg.tools && cfg.tools.birkly_submit_public_form);
        if (warn) warn.hidden = !(submitOn && (cfg.form_allowlist || []).length === 0 && forms.length > 0);
    }

    function renderToolsPreview() {
        var host = document.getElementById('ap-webmcp-preview');
        var empty = document.getElementById('ap-webmcp-preview-empty');
        if (!host) return;
        host.innerHTML = '';
        var cfg = state.webmcpConfig || { tools: {}, form_allowlist: [] };
        var exposed = [];
        (state.webmcpCatalog || []).forEach(function (tool) {
            if (!tool.enabled) return;
            // Submit tool only exposes when at least one form is allowlisted.
            if (tool.id === 'birkly_submit_public_form' && (cfg.form_allowlist || []).length === 0) return;
            exposed.push({ label: tool.label || tool.id, desc: tool.description || '' });
        });

        if (empty) empty.hidden = exposed.length > 0;
        host.hidden = exposed.length === 0;
        exposed.forEach(function (meta) {
            var li = document.createElement('li');
            li.className = 'data-list-item u-stack-1';
            li.innerHTML = '<strong class="u-text-sm">' + escapeHtml(meta.label) + '</strong>' +
                '<span class="u-text-xs u-fg-secondary">' + escapeHtml(meta.desc) + '</span>';
            host.appendChild(li);
        });
    }

    function initTools() {
        var master = document.getElementById('ap-webmcp-enabled');
        if (master) {
            master.addEventListener('change', function () {
                if (!state.webmcpConfig) state.webmcpConfig = { enabled: false, tools: {}, form_allowlist: [] };
                state.webmcpConfig.enabled = master.checked;
                var config = document.getElementById('ap-webmcp-config');
                if (config) config.hidden = !master.checked;
                saveWebmcp();
            });
        }
    }

    // ===================================================================
    // Publish zone — mode-aware guide/tools/MCP read-out (P60-M7)
    // ===================================================================

    function renderPublishZone() {
        var urls = state.publicUrls || {};
        var mode = urls.hosting_mode || 'none';
        var ps = getPublishState({ meta: state.meta, publish_state: state.publishState });

        // Mode badge
        var modeBadge = document.getElementById('ap-publish-mode-badge');
        var modeLabel = mode === 'project' ? t('ag_hosted_project', 'On Birkly')
            : (mode === 'external' ? t('ag_hosted_external', 'External') : t('ag_hosted_none', 'None'));
        badge(modeBadge, modeLabel, mode === 'none' ? 'neutral' : 'info');

        // Guide card
        var guideBadge = document.getElementById('ap-publish-guide-badge');
        if (!ps.publishing_enabled) badge(guideBadge, t('ag_status_off', 'Off'), 'neutral');
        else if (ps.never_published) badge(guideBadge, t('ag_status_draft', 'Draft'), 'warning');
        else badge(guideBadge, t('ag_status_published', 'Published'), 'success');

        var guideUrls = document.getElementById('ap-publish-guide-urls');
        if (guideUrls) {
            var rows = '';
            if (urls.birkly_serves_public && urls.public_site_url) {
                rows += '<dt>' + escapeHtml(t('ag_publish_public_url', 'Public URL')) + '</dt>' +
                    '<dd><code>' + escapeHtml(urls.public_site_url) + '</code></dd>';
            }
            rows += '<dt>' + escapeHtml(t('ag_publish_cms_url', 'Birkly URL (always works)')) + '</dt>' +
                '<dd><code>' + escapeHtml(urls.cms_canonical_url || '—') + '</code></dd>';
            guideUrls.innerHTML = rows;
        }

        // Mode-specific guide notes: Birkly-hosted is live automatically; "none"
        // needs site delivery configured first; external shows the options block.
        var guideProjectNote = document.getElementById('ap-publish-guide-project-note');
        if (guideProjectNote) guideProjectNote.hidden = mode !== 'project';
        var guideNoneNote = document.getElementById('ap-publish-guide-none-note');
        if (guideNoneNote) guideNoneNote.hidden = mode !== 'none';

        // External checklist
        var ext = document.getElementById('ap-publish-external');
        if (ext) ext.hidden = mode !== 'external';
        if (mode === 'external') {
            var clientSnippet = '<script src="' + (urls.cms_base_url || '') + '/birkly-client.js" data-cms-url="' +
                (urls.cms_base_url || '') + '"></' + 'script>';
            setSnippet('ap-publish-snippet-client', clientSnippet);
            var proxy = 'location /.well-known/agent-guide.json {\n    proxy_pass ' + (urls.cms_canonical_url || '') + ';\n}';
            setSnippet('ap-publish-snippet-proxy', proxy);
            var dl = document.getElementById('ap-publish-download-guide');
            if (dl && urls.cms_canonical_url) dl.href = urls.cms_canonical_url;
        }

        // WebMCP read-out
        var wmBadge = document.getElementById('ap-publish-webmcp-badge');
        var cfg = state.webmcpConfig;
        var wmOn = !!(cfg && cfg.enabled);
        badge(wmBadge, wmOn ? t('ag_webmcp_on', 'On') : t('ag_webmcp_off', 'Off'), wmOn ? 'success' : 'neutral');
        var wmHelp = document.getElementById('ap-publish-webmcp-help');
        if (wmHelp) {
            if (mode === 'project') {
                wmHelp.textContent = wmOn
                    ? t('ag_publish_webmcp_project_on', 'Automatically active on your Birkly site.')
                    : t('ag_publish_webmcp_off', 'Off. Turn on in the Tools tab.');
            } else if (mode === 'external') {
                wmHelp.textContent = wmOn
                    ? t('ag_publish_webmcp_external_on', 'Add the script tags below to every page.')
                    : t('ag_publish_webmcp_off', 'Off. Turn on in the Tools tab.');
            } else {
                wmHelp.textContent = t('ag_publish_webmcp_help', 'Whether AI helpers can act on your live site. Manage in the Tools tab.');
            }
        }
        var wmExt = document.getElementById('ap-publish-webmcp-external');
        if (wmExt) wmExt.hidden = !(mode === 'external' && wmOn);
        if (mode === 'external' && wmOn) {
            var wmSnippet = '<script src="' + (urls.cms_base_url || '') + '/birkly-client.js" data-cms-url="' +
                (urls.cms_base_url || '') + '"></' + 'script>\n' +
                '<script src="' + (urls.cms_base_url || '') + '/birkly-webmcp.js" data-cms-url="' +
                (urls.cms_base_url || '') + '"></' + 'script>';
            setSnippet('ap-publish-snippet-webmcp', wmSnippet);
        }

        // Birkly MCP URL (always CMS)
        var mcpEl = document.getElementById('ap-publish-mcp-url');
        if (mcpEl) mcpEl.textContent = urls.mcp_url || '—';

        loadDiscoveryStatus();
    }

    function discoveryStatusLabel(status) {
        if (status === 'pass') return t('ag_discovery_status_pass', 'OK');
        if (status === 'warn') return t('ag_discovery_status_warn', 'Review');
        if (status === 'fail') return t('ag_discovery_status_fail', 'Action needed');
        return t('ag_discovery_status_neutral', 'Optional');
    }

    function discoveryStatusBadgeKind(status) {
        if (status === 'pass') return 'success';
        if (status === 'warn') return 'warning';
        if (status === 'fail') return 'danger';
        return 'neutral';
    }

    function renderDiscoveryChecks(data) {
        var list = document.getElementById('ap-discovery-checks');
        if (!list || !data) return;
        var checks = data.checks || [];
        var html = '';
        checks.forEach(function (check) {
            var kind = discoveryStatusBadgeKind(check.status);
            var label = discoveryStatusLabel(check.status);
            var detail = check.detail || '';
            if (check.url) {
                detail = '<a href="' + escapeHtml(check.url) + '" target="_blank" rel="noopener">' + escapeHtml(check.url) + '</a>';
            } else {
                detail = escapeHtml(detail);
            }
            html += '<li class="u-split u-align-start u-gap-3 u-py-2">' +
                '<div class="u-stack-1"><strong>' + escapeHtml(check.label || '') + '</strong>' +
                '<p class="form-help u-m-0">' + detail + '</p></div>' +
                '<span class="badge badge-' + kind + '">' + escapeHtml(label) + '</span></li>';
        });
        list.innerHTML = html;
        var example = document.getElementById('ap-discovery-template-example');
        if (example && data.template_example) example.textContent = data.template_example;
    }

    function loadDiscoveryStatus() {
        var panel = document.getElementById('ap-discovery-panel');
        if (!panel) return;
        postJson('discovery_status', {}).then(function (res) {
            if (res && res.status === 'ok' && res.data) {
                renderDiscoveryChecks(res.data);
            }
        }).catch(function () { /* non-fatal */ });
    }

    function setSnippet(id, value) {
        var el = document.getElementById(id);
        if (el) el.value = value;
    }

    function initPublishZone() {
        bindEl('ap-discovery-refresh', 'click', function () {
            loadDiscoveryStatus();
        });
        document.addEventListener('click', function (e) {
            var copyBtn = e.target.closest('.ap-copy-btn');
            if (!copyBtn) return;
            var targetId = copyBtn.getAttribute('data-copy-target');
            var target = targetId ? document.getElementById(targetId) : null;
            if (!target) return;
            var text = target.value || target.textContent || '';
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function () {
                    var orig = copyBtn.textContent;
                    copyBtn.textContent = t('copied', 'Copied');
                    setTimeout(function () { copyBtn.textContent = orig; }, 1500);
                });
            }
        });
    }

    // Small POST helper for endpoints other than ai_presence.php.
    function postJsonTo(url, payload) {
        payload = payload || {};
        if (state.csrfToken && !payload._csrf) payload._csrf = state.csrfToken;
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        }).then(function (r) { return r.json(); });
    }

    function isAiPresencePage() {
        return !!document.querySelector('.ap-hub');
    }

    global.initAiPresence = function (config) {
        config = config || {};
        discoveryEmbed = !!config.discoveryEmbed;
        if (!isAiPresencePage()) return;
        state.csrfToken = getCsrfToken() || (config && config.csrfToken) || '';
        state.canEdit = !config || config.canEdit !== false;
        initTabs();
        initHubZones();
        initTools();
        initPublishZone();
        initWorkflowNav();
        initAboutModal();
        initWizard();
        initPublishFeedback();
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
            // Prefetch WebMCP so Tools tab status dot is correct without opening Tools (P62-A1).
            if (!state.webmcpLoaded) {
                return loadWebmcpConfig();
            }
        }).then(function () {
            bindActions();
            renderAssistSuggestions();
            updateSpine();
        }).catch(function (e) {
            toast(e.message || t('ag_js_failed_load_presence', 'Failed to load AI Presence'), 'error');
            loadAiConnections().finally(function () {
                if (!state.webmcpLoaded) {
                    loadWebmcpConfig().finally(function () {
                        bindActions();
                        updateSpine();
                    });
                    return;
                }
                bindActions();
                updateSpine();
            });
        });
    };

    global.switchApZone = switchZone;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            if (!document.getElementById('disc-ap-embed')) {
                global.initAiPresence({});
            }
        });
    } else if (isAiPresencePage() && !document.getElementById('disc-ap-embed')) {
        global.initAiPresence({});
    }
})(window);
