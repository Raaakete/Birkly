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

    var PROVIDER_META = {
        github: {
            label: 'GitHub', group: 'Popular',
            placeholder: 'https://github.com/your-org/your-site.git',
            help: 'Use a fine-grained or classic personal access token with repository contents read/write.',
            default_auth: 'token', default_username: 'git'
        },
        gitlab: {
            label: 'GitLab', group: 'Popular',
            placeholder: 'https://gitlab.com/your-group/your-site.git',
            help: 'Works with gitlab.com or self-hosted GitLab. Use a project or personal access token (api or write_repository).',
            default_auth: 'token', default_username: 'oauth2'
        },
        bitbucket: {
            label: 'Bitbucket', group: 'Popular',
            placeholder: 'https://bitbucket.org/your-workspace/your-site.git',
            help: 'Use an Atlassian app password or repository access token. Username is usually your Bitbucket username (or x-token-auth for access tokens).',
            default_auth: 'token', default_username: 'x-token-auth'
        },
        codeberg: {
            label: 'Codeberg', group: 'Open source & European',
            placeholder: 'https://codeberg.org/your-org/your-site.git',
            help: 'European, free Forgejo hosting. Create an access token under Settings → Applications.',
            default_auth: 'token', default_username: 'git'
        },
        gitea: {
            label: 'Gitea', group: 'Open source & European',
            placeholder: 'https://gitea.example.com/your-org/your-site.git',
            help: 'Self-hosted Gitea. Use an access token with write:repository scope.',
            default_auth: 'token', default_username: 'git'
        },
        forgejo: {
            label: 'Forgejo', group: 'Open source & European',
            placeholder: 'https://forgejo.example.com/your-org/your-site.git',
            help: 'Self-hosted Forgejo (Codeberg’s soft-fork lineage). Token auth matches Gitea.',
            default_auth: 'token', default_username: 'git'
        },
        sourcehut: {
            label: 'SourceHut', group: 'Open source & European',
            placeholder: 'https://git.sr.ht/~you/your-site',
            help: 'Open-source git hosting (sr.ht). Use an OAuth2 personal access token as the password.',
            default_auth: 'token', default_username: 'git'
        },
        buzz: {
            label: 'Buzz', group: 'AI-native',
            placeholder: 'https://your-relay.example/git/<owner-pubkey>/<repo>',
            help: 'Block’s open-source Slack/GitHub alternative. Standard git Smart HTTP on the relay; auth is usually Nostr (NIP-98) via git-credential-nostr on this server.',
            default_auth: 'nostr', default_username: ''
        },
        generic: {
            label: 'Other git remote', group: 'Other',
            placeholder: 'https://git.example.com/your-org/your-site.git',
            help: 'Any HTTPS or SSH remote that speaks standard git. Content, media, and plugins are never included.',
            default_auth: 'token', default_username: 'birkly'
        }
    };

    var PROVIDER_GROUPS = [
        { id: 'Popular', keys: ['github', 'gitlab', 'bitbucket'] },
        { id: 'Open source & European', keys: ['codeberg', 'gitea', 'forgejo', 'sourcehut'] },
        { id: 'AI-native', keys: ['buzz'] },
        { id: 'Other', keys: ['generic'] }
    ];

    function providerMeta(provider) {
        return PROVIDER_META[provider] || PROVIDER_META.generic;
    }

    function renderProviderOptions(selected) {
        return PROVIDER_GROUPS.map(function (group) {
            return '<optgroup label="' + escapeHtml(group.id) + '">' +
                group.keys.map(function (key) {
                    var meta = providerMeta(key);
                    return '<option value="' + escapeHtml(key) + '"' + (selected === key ? ' selected' : '') + '>' +
                        escapeHtml(meta.label) + '</option>';
                }).join('') +
                '</optgroup>';
        }).join('');
    }

    function renderConnectForm(config) {
        var provider = (config && config.provider) || 'github';
        if (!PROVIDER_META[provider]) provider = 'generic';
        var meta = providerMeta(provider);
        var authMode = (config && config.auth_mode) || meta.default_auth || 'token';
        var isEdit = !!(config && config.configured);
        var username = (config && config.auth_username) || meta.default_username || '';
        var nostrHelper = !!(state.data && state.data.capability && state.data.capability.nostr_helper);

        return '<form id="project-git-form" class="u-stack-4">' +
            '<div class="form-grid">' +
                '<div class="form-group">' +
                    '<label for="project-git-provider">Provider</label>' +
                    '<select id="project-git-provider" class="input">' +
                        renderProviderOptions(provider) +
                    '</select>' +
                '</div>' +
                '<div class="form-group">' +
                    '<label for="project-git-branch">Branch</label>' +
                    '<input type="text" id="project-git-branch" class="input" value="' + escapeHtml((config && config.branch) || 'main') + '" placeholder="main">' +
                '</div>' +
            '</div>' +
            '<div class="form-group">' +
                '<label for="project-git-remote">Repository URL</label>' +
                '<input type="text" id="project-git-remote" class="input" value="' + escapeHtml(isEdit ? config.remote_url : '') + '" placeholder="' + escapeHtml(meta.placeholder) + '" autocomplete="off" spellcheck="false">' +
                '<small class="form-help" id="project-git-remote-help">' + escapeHtml(meta.help) + ' Only files under <code>project/</code> sync — CMS content, media, users and plugins stay in Birkly.</small>' +
            '</div>' +
            '<div class="form-group">' +
                '<label for="project-git-auth-mode">Authentication</label>' +
                '<select id="project-git-auth-mode" class="input">' +
                    '<option value="token"' + (authMode === 'token' ? ' selected' : '') + '>Access token (HTTPS)</option>' +
                    '<option value="ssh"' + (authMode === 'ssh' ? ' selected' : '') + '>SSH private key</option>' +
                    '<option value="nostr"' + (authMode === 'nostr' ? ' selected' : '') + '>Nostr key (Buzz / NIP-98)</option>' +
                    '<option value="none"' + (authMode === 'none' ? ' selected' : '') + '>None (public or local remote)</option>' +
                '</select>' +
                '<small class="form-help" id="project-git-auth-help">' +
                    (authMode === 'nostr'
                        ? (nostrHelper
                            ? 'Uses git-credential-nostr on this server with a stored nsec. Never written into project/.'
                            : 'This server does not have git-credential-nostr on PATH yet — install the Buzz helper before connecting with Nostr auth.')
                        : 'Stored encrypted on this server and never written into <code>project/</code>.') +
                '</small>' +
            '</div>' +
            '<div class="form-group" id="project-git-token-group"' + (authMode === 'token' ? '' : ' hidden') + '>' +
                '<label for="project-git-token">Personal access token</label>' +
                '<input type="password" id="project-git-token" class="input" placeholder="' + (config && config.has_token ? 'Stored — leave blank to keep ' + escapeHtml(config.token_hint) : 'ghp_… / glpat-… / app password') + '" autocomplete="new-password" spellcheck="false">' +
                '<small class="form-help">Needs read and write access to the repository.</small>' +
            '</div>' +
            '<div class="form-group" id="project-git-username-group"' + (authMode === 'token' ? '' : ' hidden') + '>' +
                '<label for="project-git-username">Username (optional)</label>' +
                '<input type="text" id="project-git-username" class="input" value="' + escapeHtml(username) + '" placeholder="' + escapeHtml(meta.default_username || 'git') + '" autocomplete="off">' +
                '<small class="form-help">Some hosts require a specific username alongside the token (e.g. Bitbucket <code>x-token-auth</code>, GitLab <code>oauth2</code>).</small>' +
            '</div>' +
            '<div class="form-group" id="project-git-ssh-group"' + (authMode === 'ssh' ? '' : ' hidden') + '>' +
                '<label for="project-git-ssh-key">SSH private key</label>' +
                '<textarea id="project-git-ssh-key" class="input" rows="5" spellcheck="false" placeholder="' + (config && config.has_ssh_key ? 'Stored — leave blank to keep the current key' : '-----BEGIN OPENSSH PRIVATE KEY-----') + '"></textarea>' +
                '<small class="form-help">A deploy key with write access. Written to a temporary file only while git runs.</small>' +
            '</div>' +
            '<div class="form-group" id="project-git-nostr-group"' + (authMode === 'nostr' ? '' : ' hidden') + '>' +
                '<label for="project-git-nostr-key">Nostr private key</label>' +
                '<input type="password" id="project-git-nostr-key" class="input" placeholder="' + (config && config.has_nostr_key ? 'Stored — leave blank to keep ' + escapeHtml(config.nostr_hint || '••••') : 'nsec1… or hex') + '" autocomplete="new-password" spellcheck="false">' +
                '<small class="form-help">Buzz signs git requests with NIP-98. Prefer a relay-scoped key. Encrypted at rest; never committed under <code>project/</code>.</small>' +
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

        var summary = '<div class="u-stack-3">' +
            '<div class="kv"><div class="kv-key">Remote</div><div class="kv-value"><code>' + escapeHtml(config.remote_url || '—') + '</code></div></div>' +
            '<div class="kv"><div class="kv-key">Branch</div><div class="kv-value"><code>' + escapeHtml(repo.branch || config.branch || 'main') + '</code></div></div>' +
            '<div class="kv"><div class="kv-key">Authentication</div><div class="kv-value">' + escapeHtml(authLabel(config)) + '</div></div>' +
            '<div class="kv"><div class="kv-key">Local changes</div><div class="kv-value">' + (dirty ? dirty + ' file' + (dirty === 1 ? '' : 's') : 'None') + '</div></div>' +
            '<div class="kv"><div class="kv-key">To push</div><div class="kv-value">' + (ahead ? ahead + ' commit' + (ahead === 1 ? '' : 's') : 'Nothing') + '</div></div>' +
            '<div class="kv"><div class="kv-key">To pull</div><div class="kv-value">' + (behind ? behind + ' commit' + (behind === 1 ? '' : 's') : 'Nothing') + '</div></div>' +
            '<div class="kv"><div class="kv-key">Last commit</div><div class="kv-value">' + (repo.last_commit ? escapeHtml(repo.last_commit.message) + ' · ' + escapeHtml(repo.last_commit.sha) : '—') + '</div></div>' +
            '<div class="kv"><div class="kv-key">Last push</div><div class="kv-value">' + (config.last_push ? formatDate(config.last_push.at) : '—') + '</div></div>' +
            '<div class="kv"><div class="kv-key">Last pull</div><div class="kv-value">' + (config.last_pull ? formatDate(config.last_pull.at) : '—') + '</div></div>' +
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
        if (config.auth_mode === 'nostr') {
            return config.has_nostr_key ? 'Nostr key ' + (config.nostr_hint || '') : 'Nostr key (missing)';
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
                (config.configured ? '' : '<p class="section-description">Connect a git repository to push the files in <code>project/</code> to GitHub, GitLab, Bitbucket, Codeberg, Buzz, or any other git remote — and pull changes back.</p>') +
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

    function syncAuthGroups() {
        var authMode = document.getElementById('project-git-auth-mode');
        if (!authMode) return;
        var mode = authMode.value;
        var tokenGroup = document.getElementById('project-git-token-group');
        var usernameGroup = document.getElementById('project-git-username-group');
        var sshGroup = document.getElementById('project-git-ssh-group');
        var nostrGroup = document.getElementById('project-git-nostr-group');
        var authHelp = document.getElementById('project-git-auth-help');
        var nostrHelper = !!(state.data && state.data.capability && state.data.capability.nostr_helper);
        if (tokenGroup) tokenGroup.hidden = mode !== 'token';
        if (usernameGroup) usernameGroup.hidden = mode !== 'token';
        if (sshGroup) sshGroup.hidden = mode !== 'ssh';
        if (nostrGroup) nostrGroup.hidden = mode !== 'nostr';
        if (authHelp) {
            if (mode === 'nostr') {
                authHelp.textContent = nostrHelper
                    ? 'Uses git-credential-nostr on this server with a stored nsec. Never written into project/.'
                    : 'This server does not have git-credential-nostr on PATH yet — install the Buzz helper before connecting with Nostr auth.';
            } else {
                authHelp.textContent = 'Stored encrypted on this server and never written into project/.';
            }
        }
    }

    function applyProviderDefaults(forceAuth) {
        var providerEl = document.getElementById('project-git-provider');
        if (!providerEl) return;
        var meta = providerMeta(providerEl.value);
        var remote = document.getElementById('project-git-remote');
        var help = document.getElementById('project-git-remote-help');
        var username = document.getElementById('project-git-username');
        var authMode = document.getElementById('project-git-auth-mode');
        if (remote) remote.placeholder = meta.placeholder;
        if (help) {
            help.textContent = meta.help + ' Only files under project/ sync — CMS content, media, users and plugins stay in Birkly.';
        }
        if (username && !username.value) {
            username.placeholder = meta.default_username || 'git';
            if (!state.editing || forceAuth) {
                username.value = meta.default_username || '';
            }
        }
        if (authMode && forceAuth) {
            authMode.value = meta.default_auth || 'token';
        }
        syncAuthGroups();
    }

    function bindForm() {
        var form = document.getElementById('project-git-form');
        if (!form) return;

        var authMode = document.getElementById('project-git-auth-mode');
        if (authMode) {
            authMode.addEventListener('change', syncAuthGroups);
        }

        var provider = document.getElementById('project-git-provider');
        if (provider) {
            provider.addEventListener('change', function () {
                applyProviderDefaults(true);
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
        bindClick('project-git-push-btn', push);
        bindClick('project-git-pull-btn', pullPreview);
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
            ssh_key: value('project-git-ssh-key'),
            nostr_key: value('project-git-nostr-key')
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

    // ---------------------------------------------------------------------
    // Push / pull
    // ---------------------------------------------------------------------

    function panel() {
        return document.getElementById('project-git-panel');
    }

    function showPanel(html) {
        var el = panel();
        if (!el) return;
        el.innerHTML = html;
        el.hidden = false;
    }

    function hidePanel() {
        var el = panel();
        if (!el) return;
        el.hidden = true;
        el.innerHTML = '';
    }

    function afterAction(result, successMessage) {
        state.data = result.data || state.data;
        state.editing = false;
        render();

        var conflict = result.data && result.data.conflict;
        if (conflict) {
            showConflict(conflict);
            toast(result.message || 'Conflict — choose which side to keep.', 'warning');
            return false;
        }

        toast(result.message || successMessage, 'success');
        refreshActivity();
        return true;
    }

    function push() {
        var promptPromise = (window.BirklyFeedback && typeof BirklyFeedback.prompt === 'function')
            ? BirklyFeedback.prompt('', '', {
                title: 'Push to remote',
                placeholder: 'Update project files from Birkly',
                confirmLabel: 'Push'
            })
            : Promise.resolve('');

        Promise.resolve(promptPromise).then(function (message) {
            if (message === null) return;
            setBusy(true);
            return request('push', { message: message || '' }).then(function (result) {
                afterAction(result, 'Pushed');
            }).catch(function (error) {
                setBusy(false);
                toast(error.message, 'error');
            });
        });
    }

    function pullPreview() {
        setBusy(true);
        request('pull_preview', {}).then(function (result) {
            state.data = result.data || state.data;
            render();

            var data = result.data || {};
            if (data.conflict) {
                showConflict(data.conflict);
                return;
            }

            var incoming = data.incoming || [];
            if (!incoming.length) {
                toast('Already up to date — nothing to pull.', 'info');
                return;
            }

            showPanel(
                '<div class="u-stack-3">' +
                    '<p class="u-label">Incoming changes (' + incoming.length + ')</p>' +
                    '<p class="u-text-sm u-fg-secondary">Applying replaces these files in <code>project/</code> with the remote version. CMS content and media are not affected.</p>' +
                    renderChangeList(incoming, 'No incoming changes.') +
                    '<div class="u-cluster">' +
                        '<button type="button" class="btn btn-primary btn-sm" id="project-git-pull-apply-btn">Apply changes</button>' +
                        '<button type="button" class="btn btn-secondary btn-sm" id="project-git-pull-cancel-btn">Cancel</button>' +
                    '</div>' +
                '</div>'
            );
            bindClick('project-git-pull-apply-btn', pullApply);
            bindClick('project-git-pull-cancel-btn', hidePanel);
        }).catch(function (error) {
            setBusy(false);
            toast(error.message, 'error');
        });
    }

    function pullApply() {
        setBusy(true);
        request('pull_apply', {}).then(function (result) {
            if (afterAction(result, 'Pulled')) {
                hidePanel();
                if (typeof window.reloadProjectFiles === 'function') {
                    window.reloadProjectFiles();
                }
            }
        }).catch(function (error) {
            setBusy(false);
            toast(error.message, 'error');
        });
    }

    function renderPathList(paths, emptyMessage) {
        if (!paths || !paths.length) {
            return '<p class="u-text-sm u-fg-tertiary">' + escapeHtml(emptyMessage) + '</p>';
        }
        return '<ul class="project-git-file-list">' + paths.slice(0, 200).map(function (path) {
            return '<li class="project-git-file"><code>' + escapeHtml(path) + '</code></li>';
        }).join('') + '</ul>' +
        (paths.length > 200 ? '<p class="u-text-sm u-fg-tertiary">…and ' + (paths.length - 200) + ' more</p>' : '');
    }

    function showConflict(conflict) {
        var overlapping = conflict.overlapping_files || [];
        var unmerged = conflict.unmerged_files || [];
        var local = conflict.local_files || [];
        var remote = conflict.remote_files || [];

        showPanel(
            '<div class="u-stack-4">' +
                '<div class="u-stack-2">' +
                    '<p class="u-label">Conflict on ' + escapeHtml(conflict.branch || 'the branch') + '</p>' +
                    '<p class="u-text-sm">' + escapeHtml(conflict.message || 'Birkly and the remote both changed.') + '</p>' +
                    '<p class="u-text-sm u-fg-secondary">Nothing has been changed yet. Pick a side — Birkly keeps the files in <code>project/</code> and pushes them, or the remote replaces them. Files that cannot be merged, including images, always take the whole side you choose.</p>' +
                '</div>' +
                (unmerged.length
                    ? '<div class="u-stack-2"><p class="u-label">Unfinished merge (' + unmerged.length + ')</p>' + renderPathList(unmerged, '') + '</div>'
                    : '') +
                (overlapping.length
                    ? '<div class="u-stack-2"><p class="u-label">Changed on both sides (' + overlapping.length + ')</p>' + renderPathList(overlapping, '') + '</div>'
                    : '') +
                '<div class="u-stack-2">' +
                    '<p class="u-label">Changed in Birkly (' + local.length + ')</p>' +
                    renderPathList(local, 'No uncommitted local changes — Birkly has newer commits instead.') +
                '</div>' +
                '<div class="u-stack-2">' +
                    '<p class="u-label">Changed on the remote (' + remote.length + ')</p>' +
                    renderPathList(remote, 'No incoming file changes.') +
                '</div>' +
                '<div class="u-cluster">' +
                    '<button type="button" class="btn btn-secondary btn-sm" id="project-git-keep-birkly-btn">Keep Birkly</button>' +
                    '<button type="button" class="btn btn-secondary btn-sm" id="project-git-keep-remote-btn">Keep remote</button>' +
                    '<button type="button" class="btn btn-secondary btn-sm" id="project-git-conflict-cancel-btn">Cancel</button>' +
                '</div>' +
            '</div>'
        );

        bindClick('project-git-keep-birkly-btn', function () {
            resolveConflict('keep_birkly');
        });
        bindClick('project-git-keep-remote-btn', function () {
            resolveConflict('keep_remote');
        });
        bindClick('project-git-conflict-cancel-btn', function () {
            hidePanel();
            load(true);
        });
    }

    function resolveConflict(strategy) {
        var question = strategy === 'keep_birkly'
            ? 'Keep the Birkly project files and push them over the remote branch? The remote versions of the conflicting files are replaced.'
            : 'Replace project/ with the remote version? Local changes to those files, and local-only files, are discarded.';

        var confirmPromise = (window.BirklyFeedback && typeof BirklyFeedback.confirm === 'function')
            ? BirklyFeedback.confirm(question, {
                danger: strategy === 'keep_remote',
                title: strategy === 'keep_birkly' ? 'Keep Birkly' : 'Keep remote',
                confirmLabel: strategy === 'keep_birkly' ? 'Keep Birkly' : 'Keep remote'
            })
            : Promise.resolve(true);

        Promise.resolve(confirmPromise).then(function (confirmed) {
            if (!confirmed) return;
            setBusy(true);
            return request('resolve_conflict', { strategy: strategy }).then(function (result) {
                if (afterAction(result, 'Conflict resolved')) {
                    hidePanel();
                    if (typeof window.reloadProjectFiles === 'function') {
                        window.reloadProjectFiles();
                    }
                }
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
