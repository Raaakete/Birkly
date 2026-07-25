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
        advancedMode: false,
        simpleInitialized: false
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
            topbar.insertBefore(existing, topbar.firstChild);
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
        container.innerHTML = '<div class="loading-state"><div class="spinner"></div>' +
            (message ? '<p>' + escapeHtml(message) + '</p>' : '') + '</div>';
    }

    function setError(containerId, message) {
        var container = document.getElementById(containerId);
        if (!container) return;
        container.innerHTML = '<div class="error-state"><p>' + escapeHtml(message) + '</p></div>';
    }

    function renderSimpleFileList(entries) {
        var container = document.getElementById('project-simple-file-list');
        if (!container) return;

        var files = (entries || []).filter(function (e) { return e.type === 'file'; });
        if (!files.length) {
            container.innerHTML = '<div class="empty-state empty-state--compact"><p class="empty-state__message">No files yet. Upload to get started.</p></div>';
            return;
        }

        container.innerHTML = '<ul class="project-file-list">' + files.map(function (entry) {
            return '<li><span class="project-simple-file-name">' + escapeHtml(entry.name) + '</span>' +
                ' <span class="project-file-size">' + formatBytes(entry.size) + '</span></li>';
        }).join('') + '</ul>';
    }

    function refreshSimpleFiles() {
        var container = document.getElementById('project-simple-file-list');
        if (!container) return Promise.resolve();

        container.innerHTML = '<div class="loading-state"><div class="spinner"></div></div>';
        return loadFileTree('').then(function (payload) {
            renderSimpleFileList((payload.data && payload.data.entries) || []);
        }).catch(function (error) {
            container.innerHTML = '<div class="error-state"><p>' + escapeHtml(error.message) + '</p></div>';
        });
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

    function setAdvancedMode(enabled) {
        state.advancedMode = !!enabled;
        var panel = document.getElementById('project-advanced-panel');
        if (panel) panel.hidden = !state.advancedMode;
        if (state.advancedMode) {
            loadOverview();
            refreshFiles();
            loadActivity();
        }
    }

    function bindSimpleEvents() {
        if (state.simpleInitialized) return;
        state.simpleInitialized = true;

        var toggle = document.getElementById('project-advanced-editor-toggle');
        if (toggle) {
            toggle.addEventListener('change', function () {
                setAdvancedMode(toggle.checked);
            });
        }

        var uploadBtn = document.getElementById('project-simple-upload-btn');
        var uploadInput = document.getElementById('project-simple-upload-input');
        if (uploadBtn && uploadInput) {
            uploadBtn.addEventListener('click', function () {
                uploadInput.click();
            });
            uploadInput.addEventListener('change', function () {
                uploadFiles(uploadInput.files);
                uploadInput.value = '';
            });
        }
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
        if (saveBtn) saveBtn.style.display = 'none';
        if (deleteBtn) deleteBtn.style.display = 'none';
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
            '<div class="project-overview-item"><strong>Path</strong><span><code>' + escapeHtml(data.site_dir || 'project') + '/</code></span></div>' +
            '<div class="project-overview-item"><strong>Public URL</strong><span>' +
                (data.public_url ? '<a href="' + escapeHtml(apiUrl(data.public_url)) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(data.public_url) + '</a>' : '—') +
            '</span></div>' +
            '<div class="project-overview-item"><strong>Starter</strong><span>' + starter + '</span></div>' +
            '<div class="project-overview-item"><strong>Last change</strong><span>' + lastChange + '</span></div>' +
            '<div class="project-overview-item"><strong>Index</strong><span>' + (data.has_index ? 'index.html present' : 'No index.html') + '</span></div>';

        var toggle = document.getElementById('project-show-visit-button');
        if (toggle) {
            toggle.checked = !!data.show_visit_button;
        }
        syncVisitProjectButton(!!data.show_visit_button);
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
            var icon = entry.type === 'dir' ? '📁' : '📄';
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
        if (deleteBtn) deleteBtn.style.display = '';

        if (data.editable && editor) {
            if (preview) preview.hidden = true;
            if (image) image.hidden = true;
            editor.hidden = false;
            editor.value = data.content || '';
            if (saveBtn) saveBtn.style.display = '';
            return;
        }

        if (saveBtn) saveBtn.style.display = 'none';
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
            alert(error.message || 'Failed to read file');
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
            alert(error.message || 'Failed to save file');
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
            refreshSimpleFiles();
            loadActivity();
        }).catch(function (error) {
            alert(error.message);
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
                    alert(error.message);
                    toggle.checked = !toggle.checked;
                    syncVisitProjectButton(toggle.checked);
                });
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
            saveBtn.addEventListener('click', function () {
                saveCurrentFile();
            });
        }

        var tree = document.getElementById('project-file-tree');
        if (tree && tree.dataset.bound !== '1') {
            tree.dataset.bound = '1';
            tree.addEventListener('click', function (e) {
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
            breadcrumb.addEventListener('click', function (e) {
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
            folderBtn.addEventListener('click', function () {
                var name = prompt('New folder name:');
                if (!name) return;
                name = name.replace(/^\/+/, '').replace(/\/+$/, '');
                if (!name || name.indexOf('/') >= 0 || name.indexOf('..') >= 0) {
                    alert('Enter a single folder name without slashes.');
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
                    alert(error.message);
                });
            });
        }

        var uploadBtn = document.getElementById('project-upload-file-btn');
        var uploadInput = document.getElementById('project-upload-input');
        if (uploadBtn && uploadInput && uploadBtn.dataset.bound !== '1') {
            uploadBtn.dataset.bound = '1';
            uploadBtn.addEventListener('click', function () {
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
            addBtn.addEventListener('click', function () {
                var relative = prompt('New file name (e.g. about.html):');
                if (!relative) return;
                relative = relative.replace(/^\/+/, '');
                if (state.currentPath) {
                    relative = state.currentPath + '/' + relative;
                }
                var content = prompt('Initial content (optional):', '') || '';
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
                    alert(error.message);
                });
            });
        }

        var deleteBtn = document.getElementById('project-delete-file-btn');
        if (deleteBtn && deleteBtn.dataset.bound !== '1') {
            deleteBtn.dataset.bound = '1';
            deleteBtn.addEventListener('click', function () {
                if (!state.selectedFile) return;
                if (!confirm('Delete ' + state.selectedFile + '? This cannot be undone.')) return;
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
                    alert(error.message);
                });
            });
        }
    }

    function initProjectSettings() {
        if (!document.getElementById('project')) {
            return;
        }

        bindEvents();
        bindSimpleEvents();
        loadPublicSiteUrl();
        refreshSimpleFiles();

        var toggle = document.getElementById('project-advanced-editor-toggle');
        if (toggle && toggle.checked) {
            setAdvancedMode(true);
        }
    }

    window.initProjectSettings = initProjectSettings;

    if (document.getElementById('project') && document.getElementById('project').classList.contains('active')) {
        initProjectSettings();
    }
})();
