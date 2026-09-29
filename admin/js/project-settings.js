/**
 * Settings → Project tab — overview, file tree, activity.
 */
(function () {
    'use strict';

    function t(key, fallback, params) {
        if ((window.Birkly && window.Birkly.i18n)) {
            return window.Birkly.i18n.t('admin.' + key, fallback || key, params);
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

    var state = {
        currentPath: '',
        selectedFile: null,
        visitUrl: '',
        visitUrlFormat: 'auto',
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
                    throw new Error(t('project_err_invalid_response', 'Invalid response from server'));
                }
                if (!response.ok || payload.status !== 'ok') {
                    throw new Error(payload.message || t('project_err_request_failed', 'Request failed ({status})', { status: String(response.status) }));
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
            existing.className = 'button is-small admin-visit-project-btn';
            existing.target = '_blank';
            existing.rel = 'noopener noreferrer';
            existing.textContent = t('project_btn_visit', 'Visit project');
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
        var user = item.user || t('project_actor_unknown', 'Unknown');
        var details = item.details || {};
        if (details.reviewed_by) {
            return t('project_actor_ai_approved', '{reviewer} approved AI change', { reviewer: details.reviewed_by });
        }
        if (details.source === 'ai') {
            return details.ai_user_id ? (t('project_actor_ai_user', 'AI · {id}', { id: details.ai_user_id })) : t('project_actor_ai', 'AI');
        }
        if (details.source === 'admin') {
            return t('project_actor_admin', '{user} (admin)', { user: user });
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

        if (title) title.textContent = t('project_preview_title', 'Preview');
        if (placeholder) placeholder.hidden = false;
        if (editor) { editor.hidden = true; editor.value = ''; }
        if (preview) { preview.hidden = true; preview.textContent = ''; }
        if (image) { image.hidden = true; image.removeAttribute('src'); }
        if (saveBtn) saveBtn.hidden = true;
        if (deleteBtn) deleteBtn.hidden = true;
    }

    function loadOverview() {
        setLoading('project-overview-content', t('project_loading_overview', 'Loading project info...'));
        return fetchJson(apiUrl('/api/project_site.php?action=overview')).then(function (payload) {
            renderOverview(payload.data || {});
        }).catch(function (error) {
            setError('project-overview-content', error.message || t('project_load_overview_failed', 'Failed to load project overview'));
        });
    }

    function renderOverview(data) {
        var container = document.getElementById('project-overview-content');
        if (!container) return;

        state.visitUrl = data.visit_url || data.public_url || '';
        state.hasIndex = !!data.has_index;
        state.visitUrlFormat = data.visit_url_format || 'auto';

        var starter = data.starter ? escapeHtml(data.starter) : escapeHtml(t('project_starter_unknown', 'Unknown'));
        var lastChange = data.last_change && data.last_change.timestamp
            ? formatDate(data.last_change.timestamp) + (data.last_change.path ? ' · ' + escapeHtml(data.last_change.path) : '')
            : '—';

        function overviewRow(label, valueHtml) {
            return '<div class="setting-row"><div class="setting-label"><span>' + escapeHtml(label) + '</span></div><div class="setting-value">' + valueHtml + '</div></div>';
        }
        container.innerHTML = '<div class="setting-rows">' +
            overviewRow(t('project_kv_path', 'Path'), '<code>' + escapeHtml(data.site_dir || 'project') + '/</code>') +
            overviewRow(t('project_kv_public_url', 'Public URL'), data.public_url
                ? '<a href="' + escapeHtml(apiUrl(data.public_url)) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(data.public_url) + '</a>'
                : '—') +
            (data.also_served_on && data.also_served_on.length
                ? overviewRow(t('project_kv_also_served', 'Also served on'), escapeHtml(data.also_served_on.join(', ')) +
                    ' <span class="u-fg-tertiary">' + escapeHtml(t('project_kv_also_served_hint', '(read-only — edit in Settings → Site delivery)')) + '</span>')
                : '') +
            overviewRow(t('project_kv_starter', 'Starter'), starter) +
            overviewRow(t('project_kv_last_change', 'Last change'), lastChange) +
            overviewRow(t('project_kv_index', 'Index'), data.has_index ? escapeHtml(t('project_index_present', 'index.html present')) : escapeHtml(t('project_index_missing', 'No index.html'))) +
            (data.visit_url
                ? overviewRow(t('project_kv_visit_url', 'Visit URL'), '<code>' + escapeHtml(data.visit_url) + '</code>' +
                    (data.visit_url_format_resolved ? ' <span class="u-fg-tertiary">(' + escapeHtml(data.visit_url_format || 'auto') + ' → ' + escapeHtml(data.visit_url_format_resolved) + ')</span>' : ''))
                : '') +
            '</div>';

        var toggle = document.getElementById('project-show-visit-button');
        if (toggle) {
            toggle.checked = !!data.show_visit_button;
        }
        var formatSelect = document.getElementById('project-visit-url-format');
        if (formatSelect) {
            formatSelect.value = data.visit_url_format || 'auto';
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
            return t('project_ssr_engine_missing', 'Server rendering cannot run: the built-in PHP template engine is missing from this install. Pages will fall back to client-side rendering.');
        }
        if (reason) {
            return t('project_ssr_not_ready_reason', 'Server rendering is not ready on this host ({reason}). Pages will fall back to client-side rendering.', { reason: reason });
        }
        return t('project_ssr_not_ready', 'Server rendering runtime is not ready. Pages will fall back to client-side rendering.');
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
            BirklyFeedback.toast(t('project_rendering_saved', 'Rendering settings saved'), 'success');
        }).catch(function (error) {
            BirklyFeedback.toast(error.message || t('project_rendering_save_failed', 'Failed to save rendering settings'), 'error');
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
            BirklyFeedback.toast(t('project_ssr_cache_invalidated', 'SSR cache invalidated'), 'success');
        }).catch(function (error) {
            BirklyFeedback.toast(error.message || t('project_ssr_cache_invalidate_failed', 'Failed to invalidate SSR cache'), 'error');
        }).then(function () {
            updateRenderingDisabledState();
        });
    }

    function saveVisitButtonSetting(enabled, visitUrlFormat) {
        var payload = {
            action: 'save_settings',
            show_visit_button: enabled,
            _csrf: getCsrfToken()
        };
        if (visitUrlFormat !== undefined && visitUrlFormat !== null) {
            payload.visit_url_format = visitUrlFormat;
        }
        return fetchJson(apiUrl('/api/project_site.php?action=save_settings'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        }).then(function (payload) {
            var data = payload.data || {};
            state.visitUrl = data.visit_url || state.visitUrl;
            state.hasIndex = !!data.has_index;
            if (data.visit_url_format) {
                state.visitUrlFormat = data.visit_url_format;
            }
            syncVisitProjectButton(enabled);
            if (enabled && !state.visitUrl) {
                throw new Error(t('project_visit_url_unavailable', 'Visit URL is not available'));
            }
            return data;
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
            container.innerHTML = '<div class="empty"><p class="empty-text">' + escapeHtml(t('project_folder_empty', 'This folder is empty.')) + '</p></div>';
            var emptyCount = document.getElementById('project-file-count');
            if (emptyCount) emptyCount.textContent = t('settings_project_file_count', '{count} files', { count: 0 });
            return;
        }

        container.innerHTML = '<ul class="project-file-list">' + entries.map(function (entry) {
            var icon = (window.Birkly && typeof Birkly.icon === 'function')
                ? Birkly.icon(entry.type === 'dir' ? 'folder' : 'file-text', { size: 16 })
                : '';
            var size = entry.type === 'file' && entry.size != null ? ' <span class="project-file-size">' + formatBytes(entry.size) + '</span>' : '';
            return '<li><button type="button" class="project-file-entry" data-type="' + escapeHtml(entry.type) + '" data-name="' + escapeHtml(entry.name) + '" data-path="' + escapeHtml(entry.path) + '">' +
                icon + ' ' + escapeHtml(entry.name) + size + '</button></li>';
        }).join('') + '</ul>';
        var count = document.getElementById('project-file-count');
        if (count) {
            count.textContent = t('settings_project_file_count', '{count} files', { count: entries.length });
        }
        applyFileFilter();
    }

    function applyFileFilter() {
        var input = document.getElementById('project-file-filter');
        var query = input ? String(input.value || '').trim().toLowerCase() : '';
        var shown = 0;
        document.querySelectorAll('#project-file-tree .project-file-entry').forEach(function (button) {
            var item = button.closest('li');
            if (!item) return;
            var name = String(button.getAttribute('data-name') || '').toLowerCase();
            var match = query === '' || name.indexOf(query) !== -1;
            item.hidden = !match;
            if (match) shown += 1;
        });
        var count = document.getElementById('project-file-count');
        if (count && query !== '') {
            count.textContent = t('settings_project_file_count', '{count} files', { count: shown });
        }
    }

    function bindFileFilter() {
        var input = document.getElementById('project-file-filter');
        if (!input || input.dataset.bound === '1') return;
        input.dataset.bound = '1';
        input.addEventListener('input', applyFileFilter);
    }

    function refreshFiles() {
        setLoading('project-file-tree');
        return loadFileTree(state.currentPath).then(function (payload) {
            renderBreadcrumb(state.currentPath);
            renderFileTree((payload.data && payload.data.entries) || []);
        }).catch(function (error) {
            setError('project-file-tree', error.message || t('project_list_files_failed', 'Failed to list files'));
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
            preview.textContent = data.content || t('project_preview_binary', '(Binary or unsupported preview)');
        }
    }

    function previewFile(path) {
        return fetchJson(apiUrl('/api/project_site.php?action=read&path=' + encodeURIComponent(path))).then(function (payload) {
            openFile(path, payload.data || {});
        }).catch(function (error) {
            BirklyFeedback.toast(error.message || t('project_read_file_failed', 'Failed to read file'), 'error')
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
            BirklyFeedback.toast(error.message || t('project_save_file_failed', 'Failed to save file'), 'error')
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
                        throw new Error(payload.message || t('project_upload_failed', 'Upload failed for {name}', { name: file.name }));
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

    var projectActivityFilter = 'all';
    var projectActivityCache = [];

    function filterProjectActivity(items) {
        if (projectActivityFilter === 'all') {
            return items;
        }
        return items.filter(function (item) {
            return (item.project_group || 'other') === projectActivityFilter;
        });
    }

    function bindProjectActivityFilters() {
        var group = document.getElementById('project-activity-filters');
        if (!group || group.dataset.bound === '1') {
            return;
        }
        group.dataset.bound = '1';
        group.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-project-activity-filter]');
            if (!btn) return;
            projectActivityFilter = btn.getAttribute('data-project-activity-filter') || 'all';
            group.querySelectorAll('[data-project-activity-filter]').forEach(function (el) {
                var selected = el === btn;
                el.classList.toggle('is-active', selected);
                el.setAttribute('aria-pressed', selected ? 'true' : 'false');
            });
            renderProjectActivity(projectActivityCache);
        });
    }

    function renderProjectActivity(items) {
        var container = document.getElementById('project-activity-list');
        if (!container) return;

        var filtered = filterProjectActivity(items);
        var api = window.BirklyActivityRows;

        if (!filtered.length) {
            container.innerHTML = '<div class="empty-state"><p>' + escapeHtml(t('project_activity_empty', 'No project activity yet.')) + '</p></div>';
            return;
        }

        if (api) {
            container.innerHTML = api.renderList(filtered, { compact: true, expandable: true });
            api.bindToggle(container);
            return;
        }

        container.innerHTML = '<ul class="project-activity-items">' + filtered.map(function (item) {
            return '<li class="project-activity-item">' +
                '<span class="project-activity-icon">' + escapeHtml(item.icon || '📌') + '</span>' +
                '<div class="project-activity-body">' +
                    '<div class="project-activity-message">' + escapeHtml(item.message || item.event) + '</div>' +
                    '<div class="project-activity-meta">' + escapeHtml(formatActor(item)) + ' · ' + escapeHtml(formatDate(item.timestamp)) + '</div>' +
                '</div>' +
            '</li>';
        }).join('') + '</ul>';
    }

    function loadActivity() {
        setLoading('project-activity-list');
        bindProjectActivityFilters();
        return fetchJson(apiUrl('/api/project_site.php?action=activity&limit=20')).then(function (payload) {
            projectActivityCache = (payload.data && payload.data.activity) || [];
            renderProjectActivity(projectActivityCache);
        }).catch(function (error) {
            setError('project-activity-list', error.message || t('project_activity_load_failed', 'Failed to load activity'));
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

        var visitFormat = document.getElementById('project-visit-url-format');
        if (visitFormat && visitFormat.dataset.bound !== '1') {
            visitFormat.dataset.bound = '1';
            visitFormat.addEventListener('change', function () {
                var toggleEl = document.getElementById('project-show-visit-button');
                var enabled = toggleEl ? toggleEl.checked : true;
                saveVisitButtonSetting(enabled, visitFormat.value).then(function (data) {
                    if (data && data.visit_url) {
                        var container = document.getElementById('project-overview-content');
                        if (container) {
                            loadOverview();
                        }
                    }
                    BirklyFeedback.toast(t('project_visit_url_updated', 'Visit URL updated'), 'success');
                }).catch(function (error) {
                    BirklyFeedback.toast(error.message || t('project_visit_url_save_failed', 'Failed to save visit URL setting'), 'error');
                    visitFormat.value = state.visitUrlFormat || 'auto';
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
                var name = await BirklyFeedback.prompt('', '', {
                    title: t('project_new_folder_title', 'New folder'),
                    placeholder: t('project_new_folder_placeholder', 'Folder name'),
                    confirmLabel: t('project_btn_create', 'Create')
                });
                if (!name) return;
                name = name.replace(/^\/+/, '').replace(/\/+$/, '');
                if (!name || name.indexOf('/') >= 0 || name.indexOf('..') >= 0) {
                    BirklyFeedback.toast(t('project_new_folder_invalid', 'Enter a single folder name without slashes.'), 'warning')
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
                var relative = await BirklyFeedback.prompt('', '', {
                    title: t('project_new_file_title', 'New file'),
                    placeholder: 'about.html',
                    confirmLabel: t('project_btn_continue', 'Continue')
                });
                if (!relative) return;
                relative = relative.replace(/^\/+/, '');
                if (state.currentPath) {
                    relative = state.currentPath + '/' + relative;
                }
                var content = await BirklyFeedback.prompt('', '', {
                    title: t('project_initial_content_title', 'Initial content'),
                    placeholder: t('project_initial_content_optional', '(optional)'),
                    confirmLabel: t('project_btn_create', 'Create')
                }) || '';
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
                if (!(await BirklyFeedback.confirm(t('project_delete_file_confirm', 'Delete {path}? This cannot be undone.', { path: state.selectedFile })))) return;
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
                    t('project_delete_all_confirm', 'Delete the entire project folder? All HTML, CSS, JS, and assets will be removed. This cannot be undone.'),
                    { danger: true, title: t('project_delete_all_title', 'Delete project'), confirmLabel: t('project_btn_continue', 'Continue') }
                );
                if (!confirmed) return;
                var phrase = await BirklyFeedback.prompt(
                    t('project_delete_all_phrase_prompt', 'Type DELETE to confirm permanent removal of all project files.'),
                    '',
                    { title: t('project_delete_all_confirm_title', 'Confirm deletion'), placeholder: 'DELETE', confirmLabel: t('project_delete_all_btn', 'Delete project') }
                );
                if (phrase !== 'DELETE') {
                    if (phrase !== null) {
                        BirklyFeedback.toast(t('project_delete_cancelled', 'Deletion cancelled — confirmation phrase did not match.'), 'warning');
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
                    BirklyFeedback.toast(t('project_deleted_success', 'Project contents deleted.'), 'success');
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
        bindFileFilter();
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
