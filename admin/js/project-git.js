/**
 * Settings → Project → Git sync (P59-M3).
 *
 * Renders the whole Git section from the API state so the connect form,
 * status, and empty/error states never drift apart.
 */
(function () {
    'use strict';

    var state = {
        loaded: false,
        busy: false,
        editing: false,
        data: null
    };

    function apiUrl(path) {
        if (window.Birkly && typeof window.Birkly.url === 'function') {
            return window.Birkly.url(path);
        }
        return path;
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

    function formatDate(iso) {
        if (!iso) return '—';
        try {
            return new Date(iso).toLocaleString();
        } catch (e) {
            return String(iso);
        }
    }

    function toast(message, kind) {
        if (window.BirklyFeedback && typeof BirklyFeedback.toast === 'function') {
            BirklyFeedback.toast(message, kind || 'info');
        }
    }

    function request(action, body) {
        var options = { credentials: 'same-origin' };
        if (body) {
            options.method = 'POST';
            options.headers = { 'Content-Type': 'application/json' };
            body.action = action;
            body._csrf = getCsrfToken();
            options.body = JSON.stringify(body);
        }

        return fetch(apiUrl('/api/project_git.php?action=' + encodeURIComponent(action)), options)
            .then(function (response) {
                return response.text().then(function (text) {
                    var payload;
                    try {
                        payload = text ? JSON.parse(text) : {};
                    } catch (e) {
                        throw new Error('Invalid response from server');
                    }
                    if (payload.status !== 'ok') {
                        var error = new Error(payload.message || ('Request failed (' + response.status + ')'));
                        error.payload = payload;
                        throw error;
                    }
                    return payload;
                });
            });
    }

    function body() {
        return document.getElementById('project-git-body');
    }

    function setBusy(busy) {
        state.busy = busy;
        var container = body();
        if (!container) return;
        Array.prototype.forEach.call(container.querySelectorAll('button, input, select, textarea'), function (el) {
            el.disabled = busy;
        });
    }

    // ---------------------------------------------------------------------
    // Rendering
    // ---------------------------------------------------------------------

    function renderUnavailable(capability) {
        var reason = capability && capability.reason;
        var message = 'Git sync is not available on this server.';
        if (reason === 'git_missing') {
            message = 'The <code>git</code> command is not installed on this server. Install git and reload this page to sync the project folder with GitHub, GitLab, or any other remote.';
        } else if (reason === 'proc_open_disabled') {
            message = 'This server blocks PHP from running external programs (<code>proc_open</code> is disabled), so Birkly cannot drive git. Ask your host to allow it, or deploy project files another way.';
        } else if (reason === 'project_not_writable') {
            message = 'The <code>project/</code> folder is not writable by the web server, so git cannot manage it.';
        }

        return '<div class="empty-state">' +
            '<p class="empty-state__title">Git sync unavailable</p>' +
            '<p class="empty-state__description">' + message + '</p>' +
            '</div>';
    }

    function renderConnectForm(config) {
        var provider = (config && config.provider) || 'github';
        var authMode = (config && config.auth_mode) || 'token';
        var isEdit = !!(config && config.configured);

        return '<form id="project-git-form" class="u-stack-4">' +
            '<div class="form-grid">' +
                '<div class="form-group">' +
                    '<label for="project-git-provider">Provider</label>' +
                    '<select id="project-git-provider" class="input">' +
                        '<option value="github"' + (provider === 'github' ? ' selected' : '') + '>GitHub</option>' +
                        '<option value="gitlab"' + (provider === 'gitlab' ? ' selected' : '') + '>GitLab</option>' +
                        '<option value="generic"' + (provider === 'generic' ? ' selected' : '') + '>Other git remote</option>' +
                    '</select>' +
                '</div>' +
                '<div class="form-group">' +
                    '<label for="project-git-branch">Branch</label>' +
                    '<input type="text" id="project-git-branch" class="input" value="' + escapeHtml((config && config.branch) || 'main') + '" placeholder="main">' +
                '</div>' +
            '</div>' +
            '<div class="form-group">' +
                '<label for="project-git-remote">Repository URL</label>' +
                '<input type="text" id="project-git-remote" class="input" value="' + escapeHtml(isEdit ? config.remote_url : '') + '" placeholder="https://github.com/your-org/your-site.git" autocomplete="off" spellcheck="false">' +
                '<small class="form-help">HTTPS or SSH URL. Only files under <code>project/</code> are pushed and pulled — CMS content, media, users and plugins stay in Birkly.</small>' +
            '</div>' +
            '<div class="form-group">' +
                '<label for="project-git-auth-mode">Authentication</label>' +
                '<select id="project-git-auth-mode" class="input">' +
                    '<option value="token"' + (authMode === 'token' ? ' selected' : '') + '>Access token (HTTPS)</option>' +
                    '<option value="ssh"' + (authMode === 'ssh' ? ' selected' : '') + '>SSH private key</option>' +
                    '<option value="none"' + (authMode === 'none' ? ' selected' : '') + '>None (public or local remote)</option>' +
                '</select>' +
            '</div>' +
            '<div class="form-group" id="project-git-token-group"' + (authMode === 'token' ? '' : ' hidden') + '>' +
                '<label for="project-git-token">Personal access token</label>' +
                '<input type="password" id="project-git-token" class="input" placeholder="' + (config && config.has_token ? 'Stored — leave blank to keep ' + escapeHtml(config.token_hint) : 'ghp_… / glpat-…') + '" autocomplete="new-password" spellcheck="false">' +
                '<small class="form-help">Needs read and write access to the repository. Stored encrypted on this server and never written into <code>project/</code>.</small>' +
            '</div>' +
            '<div class="form-group" id="project-git-username-group"' + (authMode === 'token' ? '' : ' hidden') + '>' +
                '<label for="project-git-username">Username (optional)</label>' +
                '<input type="text" id="project-git-username" class="input" value="' + escapeHtml((config && config.auth_username) || '') + '" placeholder="birkly" autocomplete="off">' +
                '<small class="form-help">Some hosts require a specific username alongside the token. Leave blank unless yours does.</small>' +
            '</div>' +
            '<div class="form-group" id="project-git-ssh-group"' + (authMode === 'ssh' ? '' : ' hidden') + '>' +
                '<label for="project-git-ssh-key">SSH private key</label>' +
                '<textarea id="project-git-ssh-key" class="input" rows="5" spellcheck="false" placeholder="' + (config && config.has_ssh_key ? 'Stored — leave blank to keep the current key' : '-----BEGIN OPENSSH PRIVATE KEY-----') + '"></textarea>' +
                '<small class="form-help">A deploy key with write access. Stored encrypted and written to a temporary file only while git runs.</small>' +
            '</div>' +
            '<div class="u-cluster">' +
                '<button type="submit" class="btn btn-primary btn-sm" id="project-git-connect-btn">' + (isEdit ? 'Save connection' : 'Connect') + '</button>' +
                (isEdit ? '<button type="button" class="btn btn-secondary btn-sm" id="project-git-test-btn">Test connection</button>' : '') +
                (isEdit ? '<button type="button" class="btn btn-secondary btn-sm" id="project-git-cancel-edit-btn">Cancel</button>' : '') +
            '</div>' +
        '</form>';
    }

    function renderChangeList(changes, emptyMessage) {
        if (!changes || !changes.length) {
            return '<p class="u-text-sm u-fg-tertiary">' + escapeHtml(emptyMessage) + '</p>';
        }
        return '<ul class="project-git-file-list">' + changes.slice(0, 200).map(function (change) {
            return '<li class="project-git-file">' +
                '<span class="badge badge-neutral">' + escapeHtml(change.state) + '</span>' +
                '<code>' + escapeHtml(change.path) + '</code>' +
            '</li>';
        }).join('') + '</ul>' +
        (changes.length > 200 ? '<p class="u-text-sm u-fg-tertiary">…and ' + (changes.length - 200) + ' more</p>' : '');
    }

    function renderStatus(data) {
        var config = data.config || {};
        var repo = data.repository || {};
        var ahead = repo.ahead || 0;
        var behind = repo.behind || 0;
        var dirty = repo.dirty || 0;
        var conflicts = repo.conflicts || [];

        var summary = '<div class="u-grid-auto-sm">' +
            '<div class="project-overview-item"><strong>Remote</strong><span><code>' + escapeHtml(config.remote_url || '—') + '</code></span></div>' +
            '<div class="project-overview-item"><strong>Branch</strong><span><code>' + escapeHtml(repo.branch || config.branch || 'main') + '</code></span></div>' +
            '<div class="project-overview-item"><strong>Authentication</strong><span>' + escapeHtml(authLabel(config)) + '</span></div>' +
            '<div class="project-overview-item"><strong>Local changes</strong><span>' + (dirty ? dirty + ' file' + (dirty === 1 ? '' : 's') : 'None') + '</span></div>' +
            '<div class="project-overview-item"><strong>To push</strong><span>' + (ahead ? ahead + ' commit' + (ahead === 1 ? '' : 's') : 'Nothing') + '</span></div>' +
            '<div class="project-overview-item"><strong>To pull</strong><span>' + (behind ? behind + ' commit' + (behind === 1 ? '' : 's') : 'Nothing') + '</span></div>' +
            '<div class="project-overview-item"><strong>Last commit</strong><span>' + (repo.last_commit ? escapeHtml(repo.last_commit.message) + ' · ' + escapeHtml(repo.last_commit.sha) : '—') + '</span></div>' +
            '<div class="project-overview-item"><strong>Last push</strong><span>' + (config.last_push ? formatDate(config.last_push.at) : '—') + '</span></div>' +
            '<div class="project-overview-item"><strong>Last pull</strong><span>' + (config.last_pull ? formatDate(config.last_pull.at) : '—') + '</span></div>' +
        '</div>';

        var warning = '';
        if (conflicts.length) {
            warning = '<p class="form-help" role="alert">This repository has an unresolved merge. Use Pull to review and choose a side.</p>';
        }

        var actions = '<div class="u-cluster">' +
            '<button type="button" class="btn btn-primary btn-sm" id="project-git-push-btn">Push to remote</button>' +
            '<button type="button" class="btn btn-secondary btn-sm" id="project-git-pull-btn">Pull from remote</button>' +
            '<button type="button" class="btn btn-secondary btn-sm" id="project-git-test-btn">Test connection</button>' +
            '<button type="button" class="btn btn-secondary btn-sm" id="project-git-edit-btn">Edit connection</button>' +
            '<button type="button" class="btn btn-danger btn-sm" id="project-git-disconnect-btn">Disconnect</button>' +
        '</div>';

        var changes = '<div class="u-stack-2"><p class="u-label">Local changes not yet pushed</p>' +
            renderChangeList(repo.changes, 'Nothing to push — project/ matches the last commit.') +
            '</div>';

        return summary + warning + changes + actions +
            '<div id="project-git-panel" class="project-git-panel" hidden></div>';
    }

    function authLabel(config) {
        if (config.auth_mode === 'token') {
            return config.has_token ? 'Access token ' + (config.token_hint || '') : 'Access token (missing)';
        }
        if (config.auth_mode === 'ssh') {
            return config.has_ssh_key ? 'SSH key' : 'SSH key (missing)';
        }
        return 'None';
    }

    function render() {
        var container = body();
        if (!container || !state.data) return;

        var data = state.data;
        var capability = data.capability || {};
        var config = data.config || {};

        var actions = document.getElementById('project-git-status-actions');
        if (actions) {
            actions.hidden = !(capability.available && config.configured);
        }

        if (!capability.available) {
            container.innerHTML = renderUnavailable(capability);
            return;
        }

        if (!config.configured || state.editing) {
            container.innerHTML =
                (config.configured ? '' : '<p class="section-description">Connect a git repository to push the files in <code>project/</code> to GitHub, GitLab, or any other git remote — and pull changes back.</p>') +
                renderConnectForm(config);
            bindForm();
            return;
        }

        container.innerHTML = renderStatus(data);
        bindStatus();
    }

    // ---------------------------------------------------------------------
    // Events
    // ---------------------------------------------------------------------

    function bindForm() {
        var form = document.getElementById('project-git-form');
        if (!form) return;

        var authMode = document.getElementById('project-git-auth-mode');
        if (authMode) {
            authMode.addEventListener('change', function () {
                var tokenGroup = document.getElementById('project-git-token-group');
                var usernameGroup = document.getElementById('project-git-username-group');
                var sshGroup = document.getElementById('project-git-ssh-group');
                if (tokenGroup) tokenGroup.hidden = authMode.value !== 'token';
                if (usernameGroup) usernameGroup.hidden = authMode.value !== 'token';
                if (sshGroup) sshGroup.hidden = authMode.value !== 'ssh';
            });
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            connect();
        });

        bindClick('project-git-test-btn', testConnection);
        bindClick('project-git-cancel-edit-btn', function () {
            state.editing = false;
            render();
        });
    }

    function bindStatus() {
        bindClick('project-git-edit-btn', function () {
            state.editing = true;
            render();
        });
        bindClick('project-git-test-btn', testConnection);
        bindClick('project-git-disconnect-btn', disconnect);
        bindClick('project-git-push-btn', function () {
            if (typeof window.birklyProjectGitPush === 'function') {
                window.birklyProjectGitPush();
            }
        });
        bindClick('project-git-pull-btn', function () {
            if (typeof window.birklyProjectGitPull === 'function') {
                window.birklyProjectGitPull();
            }
        });
    }

    function bindClick(id, handler) {
        var el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('click', function (event) {
            event.preventDefault();
            if (state.busy) return;
            handler();
        });
    }

    function readForm() {
        function value(id) {
            var el = document.getElementById(id);
            return el ? String(el.value || '').trim() : '';
        }
        return {
            provider: value('project-git-provider') || 'generic',
            remote_url: value('project-git-remote'),
            branch: value('project-git-branch') || 'main',
            auth_mode: value('project-git-auth-mode') || 'none',
            token: value('project-git-token'),
            auth_username: value('project-git-username'),
            ssh_key: value('project-git-ssh-key')
        };
    }

    function connect() {
        var payload = readForm();
        if (!payload.remote_url) {
            toast('Enter the repository URL.', 'warning');
            return;
        }

        setBusy(true);
        request('connect', payload).then(function (result) {
            state.data = result.data || state.data;
            state.editing = false;
            render();
            toast(result.message || 'Connected', 'success');
            refreshActivity();
        }).catch(function (error) {
            if (error.payload && error.payload.data) {
                state.data = error.payload.data;
            }
            setBusy(false);
            toast(error.message, 'error');
        });
    }

    function testConnection() {
        setBusy(true);
        request('test', {}).then(function (result) {
            state.data = result.data || state.data;
            state.editing = false;
            render();
            toast(result.message || 'Connection works', 'success');
        }).catch(function (error) {
            setBusy(false);
            toast(error.message, 'error');
        });
    }

    function disconnect() {
        var confirmPromise = (window.BirklyFeedback && typeof BirklyFeedback.confirm === 'function')
            ? BirklyFeedback.confirm(
                'Disconnect this repository? The stored token or SSH key is deleted and the remote is removed. Local git history stays in project/.git.',
                { danger: true, title: 'Disconnect git', confirmLabel: 'Disconnect' }
            )
            : Promise.resolve(true);

        Promise.resolve(confirmPromise).then(function (confirmed) {
            if (!confirmed) return;
            setBusy(true);
            return request('disconnect', {}).then(function (result) {
                state.data = result.data || state.data;
                state.editing = false;
                render();
                toast(result.message || 'Disconnected', 'success');
                refreshActivity();
            }).catch(function (error) {
                setBusy(false);
                toast(error.message, 'error');
            });
        });
    }

    function refreshActivity() {
        if (typeof window.reloadProjectActivity === 'function') {
            window.reloadProjectActivity();
        }
    }

    function load(force) {
        var container = body();
        if (!container) return Promise.resolve();
        if (state.loaded && !force) {
            render();
            return Promise.resolve();
        }

        container.innerHTML = '<div class="loading-state"><p>Loading git status...</p></div>';
        return request('status').then(function (result) {
            state.loaded = true;
            state.data = result.data || {};
            render();
        }).catch(function (error) {
            container.innerHTML = '<div class="error-state"><p>' + escapeHtml(error.message) + '</p></div>';
        });
    }

    function initProjectGit() {
        if (!body()) return;

        var refresh = document.getElementById('project-git-refresh-btn');
        if (refresh && refresh.dataset.bound !== '1') {
            refresh.dataset.bound = '1';
            refresh.addEventListener('click', function () {
                load(true);
            });
        }

        load(false);
    }

    window.initProjectGit = initProjectGit;
    window.birklyProjectGit = {
        reload: function () { return load(true); },
        request: request,
        getState: function () { return state; },
        setBusy: setBusy,
        render: render,
        escapeHtml: escapeHtml,
        renderChangeList: renderChangeList,
        toast: toast,
        refreshActivity: refreshActivity
    };
})();
