/**
 * Settings → Project tab — overview, file tree, activity.
 */
(function () {
    'use strict';

    var state = {
        currentPath: '',
        selectedFile: null,
        visitUrl: '',
        publicSiteUrl: '',
        hasIndex: false,
        dirty: false,
        renderSettingsLoaded: false,
        siteDeliveryMode: ''
    };

    function apiUrl(path) {
        if (window.Birkly && typeof window.Birkly.url === 'function') {
            return window.Birkly.url(path);
        }
        return path;
    }

    function projectPublicUrl(relativePath) {
        var path = '/project/' + String(relativePath || '').replace(/^\/+/, '');
        return apiUrl(path);
    }

    function getCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    function formatBytes(bytes) {
        if (!bytes) return '0 B';
        var units = ['B', 'KB', 'MB'];
        var i = 0;
        var value = bytes;
        while (value >= 1024 && i < units.length - 1) {
            value /= 1024;
            i++;
        }
        return value.toFixed(i === 0 ? 0 : 1) + ' ' + units[i];
    }

    function formatDate(iso) {
        if (!iso) return '—';
        try {
            return new Date(iso).toLocaleString();
        } catch (e) {
            return iso;
        }
    }

    function fetchJson(url, options) {
        return fetch(url, options || { credentials: 'same-origin' }).then(function (response) {
            return response.text().then(function (text) {
                var payload;
                try {
                    payload = text ? JSON.parse(text) : {};
                } catch (e) {
                    throw new Error('Invalid response from server');
                }
                if (!response.ok || payload.status !== 'ok') {
                    throw new Error(payload.message || ('Request failed (' + response.status + ')'));
                }
                return payload;
            });
        });
    }

    function syncVisitProjectButton(enabled) {
        var topbar = document.querySelector('.admin-topbar-actions');
        if (!topbar) return;

        var existing = document.getElementById('admin-visit-project-btn');
        if (!enabled || !state.visitUrl) {
            if (existing) existing.remove();
            return;
        }

        if (!existing) {
            existing = document.createElement('a');
            existing.id = 'admin-visit-project-btn';
            existing.className = 'btn btn-secondary btn-sm admin-visit-project-btn';
            existing.target = '_blank';
            existing.rel = 'noopener noreferrer';
            existing.textContent = 'Visit project';
            // Keep Developer mode badge to the left of Visit project when present.
            var badge = document.getElementById('developer-mode-badge');
            var insertBefore = topbar.querySelector('.topbar-control-group');
            if (badge && badge.parentNode === topbar) {
                topbar.insertBefore(existing, insertBefore || badge.nextSibling);
            } else if (insertBefore) {
                topbar.insertBefore(existing, insertBefore);
            } else {
                topbar.appendChild(existing);
            }
        }

        existing.href = state.visitUrl;
    }

    function formatActor(item) {
        var user = item.user || 'Unknown';
        var details = item.details || {};
        if (details.reviewed_by) {
            return details.reviewed_by + ' approved AI change';
        }
        if (details.source === 'ai') {
            return details.ai_user_id ? ('AI · ' + details.ai_user_id) : 'AI';
        }
        if (details.source === 'admin') {
            return user + ' (admin)';
        }
        return user;
    }

    function setLoading(containerId, message) {
        var container = document.getElementById(containerId);
        if (!container) return;
        container.innerHTML = '<div class="loading-state">' +
            (message ? '<p>' + escapeHtml(message) + '</p>' : '') + '</div>';
    }

    function setError(containerId, message) {
        var container = document.getElementById(containerId);
        if (!container) return;
        container.innerHTML = '<div class="error-state"><p>' + escapeHtml(message) + '</p></div>';
    }

    function syncHostingTypeFromSiteDelivery(mode) {
        // Site delivery public_site_mode is SoT: project→birkly, external→external, else birkly.
        var hostingType = mode === 'external' ? 'external' : 'birkly';
        var select = document.getElementById('project-hosting-type');
        var hidden = document.getElementById('project-hosting-type-value');
        if (select) {
            select.value = hostingType;
            select.disabled = true;
        }
        if (hidden) hidden.value = hostingType;
        state.siteDeliveryMode = mode || 'none';
        updateRenderingDisabledState();
    }

    function loadPublicSiteUrl() {
        return fetch(apiUrl('/api/hosting.php?action=get'), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (payload) {
                if (payload.status !== 'ok' || !payload.data) return;
                var hosting = payload.data.hosting || {};
                var mode = hosting.public_site_mode || 'none';
                var url = hosting.public_site_url || '';
                if (mode === 'project' && !url) {
                    url = (hosting.cms_base_url || window.location.origin).replace(/\/$/, '') + '/project/';
                }
                state.publicSiteUrl = mode !== 'none' ? url : '';
                syncHostingTypeFromSiteDelivery(mode);
                var link = document.getElementById('project-visit-public-link');
                if (link) {
                    if (state.publicSiteUrl) {
                        link.href = state.publicSiteUrl;
                        link.hidden = false;
                    } else {
                        link.hidden = true;
                    }
                }
            })
            .catch(function () { /* optional */ });
    }

    function clearFilePreview() {
        state.selectedFile = null;
        state.dirty = false;

        var placeholder = document.querySelector('.project-file-placeholder');
        var editor = document.getElementById('project-file-editor');
        var preview = document.getElementById('project-file-preview');
        var image = document.getElementById('project-file-image');
        var saveBtn = document.getElementById('project-save-file-btn');
        var deleteBtn = document.getElementById('project-delete-file-btn');
        var title = document.getElementById('project-preview-title');

        if (title) title.textContent = 'Preview';
        if (placeholder) placeholder.hidden = false;
        if (editor) { editor.hidden = true; editor.value = ''; }
        if (preview) { preview.hidden = true; preview.textContent = ''; }
        if (image) { image.hidden = true; image.removeAttribute('src'); }
        if (saveBtn) saveBtn.hidden = true;
        if (deleteBtn) deleteBtn.hidden = true;
    }

    function loadOverview() {
        setLoading('project-overview-content', 'Loading project info...');
        return fetchJson(apiUrl('/api/project_site.php?action=overview')).then(function (payload) {
            renderOverview(payload.data || {});
        }).catch(function (error) {
            setError('project-overview-content', error.message || 'Failed to load project overview');
        });
    }

    function renderOverview(data) {
        var container = document.getElementById('project-overview-content');
        if (!container) return;

        state.visitUrl = data.visit_url || data.public_url || '';
        state.hasIndex = !!data.has_index;

        var starter = data.starter ? escapeHtml(data.starter) : 'Unknown';
        var lastChange = data.last_change && data.last_change.timestamp
            ? formatDate(data.last_change.timestamp) + (data.last_change.path ? ' · ' + escapeHtml(data.last_change.path) : '')
            : '—';

        container.innerHTML =
            '<div class="kv"><div class="kv-key">Path</div><div class="kv-value"><code>' + escapeHtml(data.site_dir || 'project') + '/</code></div></div>' +
            '<div class="kv"><div class="kv-key">Public URL</div><div class="kv-value">' +
                (data.public_url ? '<a href="' + escapeHtml(apiUrl(data.public_url)) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(data.public_url) + '</a>' : '—') +
            '</div></div>' +
            '<div class="kv"><div class="kv-key">Starter</div><div class="kv-value">' + starter + '</div></div>' +
            '<div class="kv"><div class="kv-key">Last change</div><div class="kv-value">' + lastChange + '</div></div>' +
            '<div class="kv"><div class="kv-key">Index</div><div class="kv-value">' + (data.has_index ? 'index.html present' : 'No index.html') + '</div></div>';

        var toggle = document.getElementById('project-show-visit-button');
        if (toggle) {
            toggle.checked = !!data.show_visit_button;
        }
        syncVisitProjectButton(!!data.show_visit_button);
        applyRenderSettings(data);
    }

    var ssrRuntime = { ready: true, reason: null };

    function applyRenderSettings(data) {
        var hostingType = document.getElementById('project-hosting-type');
        var hidden = document.getElementById('project-hosting-type-value');
        var renderMode = document.getElementById('project-render-mode');
        var ttl = document.getElementById('project-ssr-cache-ttl');
        // Prefer Site delivery mode when known; otherwise show persisted hosting_type.
        if (state.siteDeliveryMode) {
            syncHostingTypeFromSiteDelivery(state.siteDeliveryMode);
        } else if (hostingType && data.hosting_type) {
            hostingType.value = data.hosting_type;
            hostingType.disabled = true;
            if (hidden) hidden.value = data.hosting_type;
        }
        if (renderMode && data.render_mode) renderMode.value = data.render_mode;
        if (ttl && data.ssr_cache_ttl != null) ttl.value = data.ssr_cache_ttl;
        if (data.ssr_runtime && typeof data.ssr_runtime === 'object') {
            ssrRuntime = data.ssr_runtime;
        }
        state.renderSettingsLoaded = true;
        updateRenderingDisabledState();
    }

    // P52: SSR runs on the built-in PHP template engine — no Node.js involved.
    // The only runtime failure worth surfacing is a missing/broken engine file.
    function ssrRuntimeMessage(reason) {
        if (reason === 'engine_missing') {
            return 'Server rendering cannot run: the built-in PHP template engine is missing from this install. Pages will fall back to client-side rendering.';
        }
        if (reason) {
            return 'Server rendering is not ready on this host (' + reason + '). Pages will fall back to client-side rendering.';
        }
        return 'Server rendering runtime is not ready. Pages will fall back to client-side rendering.';
    }

    // SSR only when Site delivery mode is project (Birkly-hosted). External/none → disabled.
    // Invalidate is also disabled when render mode is client (no SSR cache to clear).
    function updateRenderingDisabledState() {
        var hostingType = document.getElementById('project-hosting-type');
        var mode = state.siteDeliveryMode || '';
        var isExternal = (mode && mode !== 'project') ||
            (!!hostingType && hostingType.value === 'external');
        var ssrDisabled = mode ? mode !== 'project' : isExternal;
        var renderMode = document.getElementById('project-render-mode');
        var ttl = document.getElementById('project-ssr-cache-ttl');
        var invalidateBtn = document.getElementById('project-invalidate-ssr-btn');
        var note = document.getElementById('project-rendering-external-note');
        var runtimeNote = document.getElementById('project-rendering-runtime-note');
        var isClientMode = !!renderMode && renderMode.value === 'client';
        var wantsSsr = !isClientMode && !ssrDisabled;
        if (hostingType) hostingType.disabled = true;
        if (renderMode) renderMode.disabled = ssrDisabled;
        if (ttl) ttl.disabled = ssrDisabled;
        if (invalidateBtn) invalidateBtn.disabled = ssrDisabled || isClientMode;
        if (note) note.hidden = !ssrDisabled;
        if (runtimeNote) {
            var showRuntime = wantsSsr && ssrRuntime && ssrRuntime.ready === false;
            runtimeNote.hidden = !showRuntime;
            runtimeNote.textContent = showRuntime ? ssrRuntimeMessage(ssrRuntime.reason) : '';
        }
    }

    // Rendering settings save automatically on change — there is no dedicated
    // "Save rendering settings" button (removed P56-R2 #3: the top Save
    // Settings button only covers General/Security/System, so this section,
    // like Domains/Redirects/Webhooks elsewhere in Site delivery, saves
    // itself immediately instead of duplicating a page-level save action).
    function saveRenderingSettings() {
        var hostingType = document.getElementById('project-hosting-type');
        var renderMode = document.getElementById('project-render-mode');
        var ttl = document.getElementById('project-ssr-cache-ttl');

        // Prevent overwriting a persisted render mode (e.g. a saved "client")
        // with the HTML default ("server") if a change fires before the
        // overview finishes loading.
        if (!state.renderSettingsLoaded) {
            return Promise.resolve();
        }

        var ttlValue = parseInt(ttl ? ttl.value : '', 10);
        if (isNaN(ttlValue) || ttlValue < 0) ttlValue = 0;
        if (ttlValue > 86400) ttlValue = 86400;

        // hosting_type is SoT-synced from Site delivery (select is read-only).
        // Still send current value so API stays backward compatible.
        var hidden = document.getElementById('project-hosting-type-value');
        var hostingTypeValue = (hidden && hidden.value) ||
            (hostingType ? hostingType.value : 'birkly');
        // Never let the Project tab invent hosting_type — Site delivery syncs it.
        if (state.siteDeliveryMode === 'external') hostingTypeValue = 'external';
        else if (state.siteDeliveryMode === 'project') hostingTypeValue = 'birkly';

        return fetchJson(apiUrl('/api/project_site.php?action=save_settings'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'save_settings',
                hosting_type: hostingTypeValue,
                render_mode: renderMode ? renderMode.value : 'server',
                ssr_cache_ttl: ttlValue,
                _csrf: getCsrfToken()
            })
        }).then(function (payload) {
            applyRenderSettings(payload.data || {});
            BirklyFeedback.toast('Rendering settings saved', 'success');
        }).catch(function (error) {
            BirklyFeedback.toast(error.message || 'Failed to save rendering settings', 'error');
        }).then(function () {
            updateRenderingDisabledState();
        });
    }

    function invalidateSsrCache() {
        var btn = document.getElementById('project-invalidate-ssr-btn');
        if (btn) btn.disabled = true;
        return fetchJson(apiUrl('/api/project_site.php?action=invalidate_ssr_cache'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'invalidate_ssr_cache',
                _csrf: getCsrfToken()
            })
        }).then(function () {
            BirklyFeedback.toast('SSR cache invalidated', 'success');
        }).catch(function (error) {
            BirklyFeedback.toast(error.message || 'Failed to invalidate SSR cache', 'error');
        }).then(function () {
            updateRenderingDisabledState();
        });
    }

    function saveVisitButtonSetting(enabled) {
        return fetchJson(apiUrl('/api/project_site.php?action=save_settings'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'save_settings',
                show_visit_button: enabled,
                _csrf: getCsrfToken()
            })
        }).then(function (payload) {
            var data = payload.data || {};
            state.visitUrl = data.visit_url || state.visitUrl;
            state.hasIndex = !!data.has_index;
            syncVisitProjectButton(enabled);
            if (enabled && !state.visitUrl) {
                throw new Error('Visit URL is not available');
            }
        });
    }

    function loadFileTree(path) {
        return fetchJson(apiUrl('/api/project_site.php?action=list&path=' + encodeURIComponent(path || '')));
    }

    function renderBreadcrumb(path) {
        var el = document.getElementById('project-file-breadcrumb');
        if (!el) return;

        var parts = path ? path.split('/') : [];
        var html = '<button type="button" class="project-crumb" data-path="">project/</button>';
        var built = '';
        parts.forEach(function (part) {
            if (!part) return;
            built = built ? built + '/' + part : part;
            html += '<span class="project-crumb-sep">/</span>';
            html += '<button type="button" class="project-crumb" data-path="' + escapeHtml(built) + '">' + escapeHtml(part) + '</button>';
        });
        el.innerHTML = html;
    }

    function renderFileTree(entries) {
        var container = document.getElementById('project-file-tree');
        if (!container) return;

        if (!entries.length) {
            container.innerHTML = '<div class="empty-state"><p>This folder is empty.</p></div>';
            return;
        }

        container.innerHTML = '<ul class="project-file-list">' + entries.map(function (entry) {
            var icon = (window.Birkly && typeof Birkly.icon === 'function')
                ? Birkly.icon(entry.type === 'dir' ? 'folder' : 'file-text', { size: 16 })
                : '';
            var size = entry.type === 'file' && entry.size != null ? ' <span class="project-file-size">' + formatBytes(entry.size) + '</span>' : '';
            return '<li><button type="button" class="project-file-entry" data-type="' + escapeHtml(entry.type) + '" data-path="' + escapeHtml(entry.path) + '">' +
                icon + ' ' + escapeHtml(entry.name) + size + '</button></li>';
        }).join('') + '</ul>';
    }

    function refreshFiles() {
        setLoading('project-file-tree');
        return loadFileTree(state.currentPath).then(function (payload) {
            renderBreadcrumb(state.currentPath);
            renderFileTree((payload.data && payload.data.entries) || []);
        }).catch(function (error) {
            setError('project-file-tree', error.message || 'Failed to list files');
        });
    }

    function openFile(path, data) {
        var placeholder = document.querySelector('.project-file-placeholder');
        var editor = document.getElementById('project-file-editor');
        var preview = document.getElementById('project-file-preview');
        var image = document.getElementById('project-file-image');
        var saveBtn = document.getElementById('project-save-file-btn');
        var deleteBtn = document.getElementById('project-delete-file-btn');
        var title = document.getElementById('project-preview-title');

        state.selectedFile = path;
        state.dirty = false;
        if (title) title.textContent = path;
        if (placeholder) placeholder.hidden = true;
        if (deleteBtn) deleteBtn.hidden = false;

        if (data.editable && editor) {
            if (preview) preview.hidden = true;
            if (image) image.hidden = true;
            editor.hidden = false;
            editor.value = data.content || '';
            if (saveBtn) saveBtn.hidden = false;
            return;
        }

        if (saveBtn) saveBtn.hidden = true;
        if (editor) editor.hidden = true;

        if (data.image && image) {
            if (preview) preview.hidden = true;
            image.hidden = false;
            image.src = projectPublicUrl(path);
            image.alt = path;
            return;
        }

        if (image) image.hidden = true;
        if (preview) {
            preview.hidden = false;
            preview.textContent = data.content || '(Binary or unsupported preview)';
        }
    }

    function previewFile(path) {
        return fetchJson(apiUrl('/api/project_site.php?action=read&path=' + encodeURIComponent(path))).then(function (payload) {
            openFile(path, payload.data || {});
        }).catch(function (error) {
            BirklyFeedback.toast(error.message || 'Failed to read file', 'error')
        });
    }

    function saveCurrentFile() {
        if (!state.selectedFile) return;
        var editor = document.getElementById('project-file-editor');
        if (!editor || editor.hidden) return;

        return fetchJson(apiUrl('/api/project_site.php?action=write'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'write',
                path: state.selectedFile,
                content: editor.value,
                _csrf: getCsrfToken()
            })
        }).then(function () {
            state.dirty = false;
            loadActivity();
        }).catch(function (error) {
            BirklyFeedback.toast(error.message || 'Failed to save file', 'error')
        });
    }

    function uploadFiles(fileList) {
        if (!fileList || !fileList.length) return;

        var uploads = Array.prototype.map.call(fileList, function (file) {
            var formData = new FormData();
            formData.append('action', 'upload');
            formData.append('path', state.currentPath || '');
            formData.append('file', file);
            formData.append('_csrf', getCsrfToken());

            return fetch(apiUrl('/api/project_site.php?action=upload'), {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            }).then(function (response) {
                return response.text().then(function (text) {
                    var payload = text ? JSON.parse(text) : {};
                    if (!response.ok || payload.status !== 'ok') {
                        throw new Error(payload.message || ('Upload failed for ' + file.name));
                    }
                    return payload;
                });
            });
        });

        Promise.all(uploads).then(function () {
            refreshFiles();
            loadActivity();
        }).catch(function (error) {
            BirklyFeedback.toast(error.message, 'error')
        });
    }

    function loadActivity() {
        setLoading('project-activity-list');
        return fetchJson(apiUrl('/api/project_site.php?action=activity&limit=20')).then(function (payload) {
            var container = document.getElementById('project-activity-list');
            if (!container) return;

            var items = (payload.data && payload.data.activity) || [];
            if (!items.length) {
                container.innerHTML = '<div class="empty-state"><p>No project activity yet.</p></div>';
                return;
            }

            container.innerHTML = '<ul class="project-activity-items">' + items.map(function (item) {
                return '<li class="project-activity-item">' +
                    '<span class="project-activity-icon">' + escapeHtml(item.icon || '📌') + '</span>' +
                    '<div class="project-activity-body">' +
                        '<div class="project-activity-message">' + escapeHtml(item.message || item.event) + '</div>' +
                        '<div class="project-activity-meta">' + escapeHtml(formatActor(item)) + ' · ' + escapeHtml(formatDate(item.timestamp)) + '</div>' +
                    '</div>' +
                '</li>';
            }).join('') + '</ul>';
        }).catch(function (error) {
            setError('project-activity-list', error.message || 'Failed to load activity');
        });
    }

    function bindEvents() {
        var toggle = document.getElementById('project-show-visit-button');
        if (toggle && toggle.dataset.bound !== '1') {
            toggle.dataset.bound = '1';
            toggle.addEventListener('change', function () {
                saveVisitButtonSetting(toggle.checked).catch(function (error) {
                    BirklyFeedback.toast(error.message, 'error')
                    toggle.checked = !toggle.checked;
                    syncVisitProjectButton(toggle.checked);
                });
            });
        }

        // Hosting select is read-only (Site delivery SoT) — no change handler.

        var renderModeSelect = document.getElementById('project-render-mode');
        if (renderModeSelect && renderModeSelect.dataset.bound !== '1') {
            renderModeSelect.dataset.bound = '1';
            renderModeSelect.addEventListener('change', function () {
                updateRenderingDisabledState();
                saveRenderingSettings();
            });
        }

        var ttlInput = document.getElementById('project-ssr-cache-ttl');
        if (ttlInput && ttlInput.dataset.bound !== '1') {
            ttlInput.dataset.bound = '1';
            ttlInput.addEventListener('change', function () {
                saveRenderingSettings();
            });
        }

        var invalidateBtn = document.getElementById('project-invalidate-ssr-btn');
        if (invalidateBtn && invalidateBtn.dataset.bound !== '1') {
            invalidateBtn.dataset.bound = '1';
            invalidateBtn.addEventListener('click', function () {
                invalidateSsrCache();
            });
        }

        var editor = document.getElementById('project-file-editor');
        if (editor && editor.dataset.bound !== '1') {
            editor.dataset.bound = '1';
            editor.addEventListener('input', function () {
                state.dirty = true;
            });
        }

        var saveBtn = document.getElementById('project-save-file-btn');
        if (saveBtn && saveBtn.dataset.bound !== '1') {
            saveBtn.dataset.bound = '1';
            saveBtn.addEventListener('click', async function () {
                saveCurrentFile();
            });
        }

        var tree = document.getElementById('project-file-tree');
        if (tree && tree.dataset.bound !== '1') {
            tree.dataset.bound = '1';
            tree.addEventListener('click', async function (e) {
                var btn = e.target.closest('.project-file-entry');
                if (!btn) return;
                var type = btn.getAttribute('data-type');
                var path = btn.getAttribute('data-path') || '';
                if (type === 'dir') {
                    state.currentPath = path;
                    clearFilePreview();
                    refreshFiles();
                    return;
                }
                previewFile(path);
            });
        }

        var breadcrumb = document.getElementById('project-file-breadcrumb');
        if (breadcrumb && breadcrumb.dataset.bound !== '1') {
            breadcrumb.dataset.bound = '1';
            breadcrumb.addEventListener('click', async function (e) {
                var btn = e.target.closest('.project-crumb');
                if (!btn) return;
                state.currentPath = btn.getAttribute('data-path') || '';
                clearFilePreview();
                refreshFiles();
            });
        }

        var folderBtn = document.getElementById('project-new-folder-btn');
        if (folderBtn && folderBtn.dataset.bound !== '1') {
            folderBtn.dataset.bound = '1';
            folderBtn.addEventListener('click', async function () {
                var name = await BirklyFeedback.prompt('', '', { title: 'New folder', placeholder: 'Folder name', confirmLabel: 'Create' });
                if (!name) return;
                name = name.replace(/^\/+/, '').replace(/\/+$/, '');
                if (!name || name.indexOf('/') >= 0 || name.indexOf('..') >= 0) {
                    BirklyFeedback.toast('Enter a single folder name without slashes.', 'warning')
                    return;
                }
                var fullPath = state.currentPath ? state.currentPath + '/' + name : name;
                fetchJson(apiUrl('/api/project_site.php?action=create_folder'), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'create_folder',
                        path: fullPath,
                        _csrf: getCsrfToken()
                    })
                }).then(function () {
                    refreshFiles();
                    loadActivity();
                }).catch(function (error) {
                    BirklyFeedback.toast(error.message, 'error')
                });
            });
        }

        var uploadBtn = document.getElementById('project-upload-file-btn');
        var uploadInput = document.getElementById('project-upload-input');
        if (uploadBtn && uploadInput && uploadBtn.dataset.bound !== '1') {
            uploadBtn.dataset.bound = '1';
            uploadBtn.addEventListener('click', async function () {
                uploadInput.click();
            });
            uploadInput.addEventListener('change', function () {
                uploadFiles(uploadInput.files);
                uploadInput.value = '';
            });
        }

        var addBtn = document.getElementById('project-add-file-btn');
        if (addBtn && addBtn.dataset.bound !== '1') {
            addBtn.dataset.bound = '1';
            addBtn.addEventListener('click', async function () {
                var relative = await BirklyFeedback.prompt('', '', { title: 'New file', placeholder: 'about.html', confirmLabel: 'Continue' });
                if (!relative) return;
                relative = relative.replace(/^\/+/, '');
                if (state.currentPath) {
                    relative = state.currentPath + '/' + relative;
                }
                var content = await BirklyFeedback.prompt('', '', { title: 'Initial content', placeholder: '(optional)', confirmLabel: 'Create' }) || '';
                fetchJson(apiUrl('/api/project_site.php?action=create'), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'create',
                        path: relative,
                        content: content,
                        _csrf: getCsrfToken()
                    })
                }).then(function () {
                    var parent = relative.indexOf('/') >= 0 ? relative.split('/').slice(0, -1).join('/') : '';
                    state.currentPath = parent;
                    refreshFiles().then(function () {
                        previewFile(relative);
                    });
                    loadActivity();
                }).catch(function (error) {
                    BirklyFeedback.toast(error.message, 'error')
                });
            });
        }

        var deleteBtn = document.getElementById('project-delete-file-btn');
        if (deleteBtn && deleteBtn.dataset.bound !== '1') {
            deleteBtn.dataset.bound = '1';
            deleteBtn.addEventListener('click', async function () {
                if (!state.selectedFile) return;
                if (!(await BirklyFeedback.confirm('Delete ' + state.selectedFile + '? This cannot be undone.'))) return;
                fetchJson(apiUrl('/api/project_site.php?action=delete'), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'delete',
                        path: state.selectedFile,
                        _csrf: getCsrfToken()
                    })
                }).then(function () {
                    clearFilePreview();
                    refreshFiles();
                    loadActivity();
                }).catch(function (error) {
                    BirklyFeedback.toast(error.message, 'error')
                });
            });
        }

        var deleteProjectBtn = document.getElementById('project-delete-all-btn');
        if (deleteProjectBtn && deleteProjectBtn.dataset.bound !== '1') {
            deleteProjectBtn.dataset.bound = '1';
            deleteProjectBtn.addEventListener('click', async function () {
                var confirmed = await BirklyFeedback.confirm(
                    'Delete the entire project folder? All HTML, CSS, JS, and assets will be removed. This cannot be undone.',
                    { danger: true, title: 'Delete project', confirmLabel: 'Continue' }
                );
                if (!confirmed) return;
                var phrase = await BirklyFeedback.prompt(
                    'Type DELETE to confirm permanent removal of all project files.',
                    '',
                    { title: 'Confirm deletion', placeholder: 'DELETE', confirmLabel: 'Delete project' }
                );
                if (phrase !== 'DELETE') {
                    if (phrase !== null) {
                        BirklyFeedback.toast('Deletion cancelled — confirmation phrase did not match.', 'warning');
                    }
                    return;
                }
                fetchJson(apiUrl('/api/project_site.php?action=delete_project'), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'delete_project',
                        confirm_phrase: 'DELETE',
                        _csrf: getCsrfToken()
                    })
                }).then(function () {
                    clearFilePreview();
                    state.currentPath = '';
                    state.selectedFile = null;
                    refreshFiles();
                    loadOverview();
                    loadActivity();
                    BirklyFeedback.toast('Project contents deleted.', 'success');
                }).catch(function (error) {
                    BirklyFeedback.toast(error.message, 'error');
                });
            });
        }
    }

    function initProjectSettings() {
        if (!document.getElementById('project')) {
            return;
        }

        bindEvents();
        loadPublicSiteUrl();
        loadOverview();
        refreshFiles();
        loadActivity();

        if (typeof window.initProjectGit === 'function') {
            window.initProjectGit();
        }
    }

    window.initProjectSettings = initProjectSettings;
    // Git actions append to the same feed, so they can refresh it after a run.
    window.reloadProjectActivity = loadActivity;
    window.reloadProjectFiles = refreshFiles;

    if (document.getElementById('project') && document.getElementById('project').classList.contains('active')) {
        initProjectSettings();
    }
})();
