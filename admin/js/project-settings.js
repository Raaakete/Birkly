/**
 * Settings → Project tab — overview, file tree, AI activity.
 */
(function () {
    'use strict';

    var state = {
        overview: null,
        currentPath: '',
        selectedFile: null,
        initialized: false
    };

    function apiUrl(path) {
        return (window.Birkly && window.Birkly.url) ? window.Birkly.url(path) : path;
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

    async function fetchOverview() {
        var response = await fetch(apiUrl('/api/project_site.php?action=overview'), { credentials: 'same-origin' });
        var payload = await response.json();
        if (!response.ok || payload.status !== 'ok') {
            throw new Error(payload.message || 'Failed to load project overview');
        }
        return payload.data;
    }

    function renderOverview(data) {
        var container = document.getElementById('project-overview-content');
        if (!container) return;

        var starter = data.starter ? escapeHtml(data.starter) : 'Unknown';
        var lastChange = data.last_change && data.last_change.timestamp
            ? formatDate(data.last_change.timestamp) + (data.last_change.path ? ' · ' + escapeHtml(data.last_change.path) : '')
            : '—';

        container.innerHTML =
            '<div class="project-overview-item"><strong>Path</strong><span><code>' + escapeHtml(data.site_dir || 'project') + '/</code></span></div>' +
            '<div class="project-overview-item"><strong>Public URL</strong><span>' +
                (data.public_url ? '<a href="' + escapeHtml(data.public_url) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(data.public_url) + '</a>' : '—') +
            '</span></div>' +
            '<div class="project-overview-item"><strong>Starter</strong><span>' + starter + '</span></div>' +
            '<div class="project-overview-item"><strong>Last change</strong><span>' + lastChange + '</span></div>' +
            '<div class="project-overview-item"><strong>Index</strong><span>' + (data.has_index ? 'index.html present' : 'No index.html') + '</span></div>';

        var toggle = document.getElementById('project-show-visit-button');
        if (toggle) {
            toggle.checked = !!data.show_visit_button;
        }
    }

    async function saveVisitButtonSetting(enabled) {
        var response = await fetch(apiUrl('/api/project_site.php?action=save_settings'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'save_settings',
                show_visit_button: enabled,
                _csrf: getCsrfToken()
            })
        });
        var payload = await response.json();
        if (!response.ok || payload.status !== 'ok') {
            throw new Error(payload.message || 'Failed to save setting');
        }
    }

    async function loadFileTree(path) {
        var response = await fetch(apiUrl('/api/project_site.php?action=list&path=' + encodeURIComponent(path || '')), {
            credentials: 'same-origin'
        });
        var payload = await response.json();
        if (!response.ok || payload.status !== 'ok') {
            throw new Error(payload.message || 'Failed to list files');
        }
        return payload.data;
    }

    function renderBreadcrumb(path) {
        var el = document.getElementById('project-file-breadcrumb');
        if (!el) return;

        var parts = path ? path.split('/') : [];
        var html = '<button type="button" class="project-crumb" data-path="">project/</button>';
        var built = '';
        parts.forEach(function (part, index) {
            if (!part) return;
            built = built ? built + '/' + part : part;
            var crumbPath = built;
            html += '<span class="project-crumb-sep">/</span>';
            html += '<button type="button" class="project-crumb" data-path="' + escapeHtml(crumbPath) + '">' + escapeHtml(part) + '</button>';
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

    async function previewFile(path) {
        var response = await fetch(apiUrl('/api/project_site.php?action=read&path=' + encodeURIComponent(path)), {
            credentials: 'same-origin'
        });
        var payload = await response.json();
        if (!response.ok || payload.status !== 'ok') {
            throw new Error(payload.message || 'Failed to read file');
        }

        var preview = document.getElementById('project-file-preview');
        var title = document.getElementById('project-preview-title');
        var deleteBtn = document.getElementById('project-delete-file-btn');
        if (title) title.textContent = path;
        if (preview) preview.textContent = payload.data.content || '';
        if (deleteBtn) deleteBtn.style.display = '';
        state.selectedFile = path;
    }

    async function refreshFiles() {
        var data = await loadFileTree(state.currentPath);
        renderBreadcrumb(state.currentPath);
        renderFileTree(data.entries || []);
    }

    async function loadActivity() {
        var container = document.getElementById('project-activity-list');
        if (!container) return;

        try {
            var response = await fetch(apiUrl('/api/project_site.php?action=activity&limit=20'), { credentials: 'same-origin' });
            var payload = await response.json();
            if (!response.ok || payload.status !== 'ok') {
                throw new Error(payload.message || 'Failed to load activity');
            }

            var items = payload.data.activity || [];
            if (!items.length) {
                container.innerHTML = '<div class="empty-state"><p>No project file activity yet.</p></div>';
                return;
            }

            container.innerHTML = '<ul class="project-activity-items">' + items.map(function (item) {
                return '<li class="project-activity-item">' +
                    '<span class="project-activity-icon">' + escapeHtml(item.icon || '📌') + '</span>' +
                    '<div class="project-activity-body">' +
                        '<div class="project-activity-message">' + escapeHtml(item.message || item.event) + '</div>' +
                        '<div class="project-activity-meta">' + escapeHtml(item.user || '') + ' · ' + escapeHtml(formatDate(item.timestamp)) + '</div>' +
                    '</div>' +
                '</li>';
            }).join('') + '</ul>';
        } catch (error) {
            container.innerHTML = '<div class="error-state"><p>' + escapeHtml(error.message) + '</p></div>';
        }
    }

    function bindEvents() {
        var toggle = document.getElementById('project-show-visit-button');
        if (toggle && toggle.dataset.bound !== '1') {
            toggle.dataset.bound = '1';
            toggle.addEventListener('change', function () {
                saveVisitButtonSetting(toggle.checked).catch(function (error) {
                    alert(error.message);
                    toggle.checked = !toggle.checked;
                });
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
                    state.selectedFile = null;
                    refreshFiles().catch(function (error) { alert(error.message); });
                    return;
                }
                previewFile(path).catch(function (error) { alert(error.message); });
            });
        }

        var breadcrumb = document.getElementById('project-file-breadcrumb');
        if (breadcrumb && breadcrumb.dataset.bound !== '1') {
            breadcrumb.dataset.bound = '1';
            breadcrumb.addEventListener('click', function (e) {
                var btn = e.target.closest('.project-crumb');
                if (!btn) return;
                state.currentPath = btn.getAttribute('data-path') || '';
                state.selectedFile = null;
                refreshFiles().catch(function (error) { alert(error.message); });
            });
        }

        var addBtn = document.getElementById('project-add-file-btn');
        if (addBtn && addBtn.dataset.bound !== '1') {
            addBtn.dataset.bound = '1';
            addBtn.addEventListener('click', function () {
                var relative = prompt('New file path (relative to project/, e.g. pages/about.html):');
                if (!relative) return;
                relative = relative.replace(/^\/+/, '');
                var content = prompt('Initial content (optional):', '') || '';
                fetch(apiUrl('/api/project_site.php?action=create'), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'create',
                        path: relative,
                        content: content,
                        _csrf: getCsrfToken()
                    })
                }).then(function (response) { return response.json(); })
                    .then(function (payload) {
                        if (payload.status !== 'ok') throw new Error(payload.message || 'Create failed');
                        var parent = relative.indexOf('/') >= 0 ? relative.split('/').slice(0, -1).join('/') : '';
                        state.currentPath = parent;
                        return Promise.all([refreshFiles(), loadActivity()]);
                    })
                    .catch(function (error) { alert(error.message); });
            });
        }

        var deleteBtn = document.getElementById('project-delete-file-btn');
        if (deleteBtn && deleteBtn.dataset.bound !== '1') {
            deleteBtn.dataset.bound = '1';
            deleteBtn.addEventListener('click', function () {
                if (!state.selectedFile) return;
                if (!confirm('Delete ' + state.selectedFile + '? This cannot be undone.')) return;
                fetch(apiUrl('/api/project_site.php?action=delete'), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'delete',
                        path: state.selectedFile,
                        _csrf: getCsrfToken()
                    })
                }).then(function (response) { return response.json(); })
                    .then(function (payload) {
                        if (payload.status !== 'ok') throw new Error(payload.message || 'Delete failed');
                        state.selectedFile = null;
                        var preview = document.getElementById('project-file-preview');
                        var title = document.getElementById('project-preview-title');
                        if (preview) preview.innerHTML = '<span class="text-muted">Select a file to preview</span>';
                        if (title) title.textContent = 'Preview';
                        deleteBtn.style.display = 'none';
                        return Promise.all([refreshFiles(), loadActivity()]);
                    })
                    .catch(function (error) { alert(error.message); });
            });
        }
    }

    async function initProjectSettings() {
        bindEvents();

        try {
            state.overview = await fetchOverview();
            renderOverview(state.overview);
            await refreshFiles();
            await loadActivity();
            state.initialized = true;
        } catch (error) {
            var overview = document.getElementById('project-overview-content');
            if (overview) {
                overview.innerHTML = '<div class="error-state"><p>' + escapeHtml(error.message) + '</p></div>';
            }
        }
    }

    window.initProjectSettings = initProjectSettings;

    if (document.getElementById('project') && document.getElementById('project').classList.contains('active')) {
        initProjectSettings();
    }
})();
