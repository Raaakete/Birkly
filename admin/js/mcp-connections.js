/**
 * External AI connections (Claude Desktop, etc.) — plain-language UI
 */

(function() {
    'use strict';

    let csrfToken = '';
    let initialized = false;
    let serverUrl = '';
    const DOCS_HELP_URL = typeof Birkly !== 'undefined' && Birkly.adminUrl
        ? Birkly.adminUrl('help') + '#mcp-connections'
        : '/admin/index.php?page=help#mcp-connections';

    function isAiIntegrationsContext() {
        const pane = document.getElementById('ai-integrations');
        if (!pane) return false;
        const hash = window.location.hash.replace('#', '');
        if (hash === 'ai-integrations' || hash === 'ai-connections' || hash === 'mcp-connections') {
            return true;
        }
        return pane.classList.contains('active');
    }

    function initMcpConnections() {
        if (initialized) return;
        initialized = true;

        csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        initHelpTips();
        loadEndpoints();
        setupEventListeners();
    }

    function maybeInitMcpConnections() {
        if (isAiIntegrationsContext()) {
            setTimeout(initMcpConnections, 100);
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const mcpTab = document.querySelector('[data-tab="ai-integrations"]');
        const mcpPane = document.getElementById('ai-integrations');

        if (mcpTab && mcpPane) {
            maybeInitMcpConnections();
            mcpTab.addEventListener('click', function() {
                setTimeout(initMcpConnections, 100);
            });
        }

        window.addEventListener('hashchange', maybeInitMcpConnections);
        window.addEventListener('birkly:ai-integrations-tab', maybeInitMcpConnections);
    });

    function initHelpTips() {
        document.querySelectorAll('#ai-integrations .help-tip[data-tip]').forEach(btn => {
            if (btn.dataset.tipBound) return;
            btn.dataset.tipBound = '1';
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
            });
        });
    }

    function setupEventListeners() {
        document.getElementById('add-mcp-endpoint-btn')?.addEventListener('click', showAddMcpModal);

        document.querySelectorAll('[data-mcp-modal-close]').forEach(btn => {
            btn.addEventListener('click', hideAddMcpModal);
        });

        document.getElementById('add-mcp-form')?.addEventListener('submit', handleMcpCreate);

        document.getElementById('mcp-connection-type')?.addEventListener('change', syncConnectionTypeFields);
        document.getElementById('mcp-auth-mode-select')?.addEventListener('change', syncAuthModeFromSelect);
    }

    function syncConnectionTypeFields() {
        const type = document.getElementById('mcp-connection-type')?.value || 'desktop';
        const authHidden = document.getElementById('mcp-auth-mode');
        const authVisibleGroup = document.getElementById('mcp-auth-mode-visible-group');
        const oauthGroup = document.getElementById('mcp-oauth-client-id-group');
        const authSelect = document.getElementById('mcp-auth-mode-select');

        if (type === 'web') {
            if (authHidden) authHidden.value = authSelect?.value || 'oauth';
            if (authVisibleGroup) authVisibleGroup.style.display = 'block';
            if (oauthGroup) oauthGroup.style.display = 'block';
        } else {
            if (authHidden) authHidden.value = 'api_key';
            if (authVisibleGroup) authVisibleGroup.style.display = 'none';
            if (oauthGroup) oauthGroup.style.display = 'none';
        }
    }

    function syncAuthModeFromSelect() {
        const authSelect = document.getElementById('mcp-auth-mode-select');
        const authHidden = document.getElementById('mcp-auth-mode');
        if (authHidden && authSelect) {
            authHidden.value = authSelect.value;
        }
        const oauthGroup = document.getElementById('mcp-oauth-client-id-group');
        if (oauthGroup) {
            const show = authSelect && (authSelect.value === 'oauth' || authSelect.value === 'both');
            oauthGroup.style.display = show ? 'block' : 'none';
        }
    }

    function connectionTypeLabel(authMode) {
        if (authMode === 'oauth') return 'Claude on the web';
        if (authMode === 'both') return 'Desktop + web';
        return 'Claude Desktop';
    }

    async function loadEndpoints() {
        try {
            const response = await fetch(Birkly.url('/api/mcp_endpoints.php?action=list'));
            const result = await response.json();

            if (result && result.status === 'ok' && result.data) {
                serverUrl = result.data.server_url || (window.location.origin + Birkly.url('/api/mcp_server.php'));
                updateGlobalServerUrl();
                renderEndpoints(result.data.endpoints || []);
            } else {
                renderEndpoints([]);
            }
        } catch (error) {
            console.error('Error loading connections:', error);
            renderEndpoints([]);
        }
    }

    function updateGlobalServerUrl() {
        const block = document.getElementById('mcp-global-server-url');
        const input = document.getElementById('mcp-server-url-input');
        if (block && input) {
            input.value = serverUrl;
            block.style.display = 'block';
        }
    }

    function getBaseUrl() {
        return serverUrl.replace(/\/api\/mcp_server\.php$/, '') || window.location.origin;
    }

    function getBridgeDownloadUrl() {
        const base = getBaseUrl().replace(/\/$/, '');
        const path = (typeof Birkly !== 'undefined' && Birkly.url) ? Birkly.url('/mcp-bridge.js') : '/mcp-bridge.js';
        if (path.startsWith('http')) return path;
        try {
            return new URL(path, base + '/').href;
        } catch (e) {
            return base + path;
        }
    }

    function defaultBridgePath() {
        const ua = navigator.userAgent || '';
        if (/Win/i.test(ua)) {
            return 'C:\\Users\\YOUR_NAME\\Documents\\Birkly\\mcp-bridge.js';
        }
        return '/Users/YOUR_NAME/Documents/Birkly/mcp-bridge.js';
    }

    function slugifyServerName(name) {
        const slug = String(name || 'birkly')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
        return slug || 'birkly';
    }

    function buildClaudeDesktopConfig(apiKey, endpointName) {
        const config = {
            mcpServers: {
                [slugifyServerName(endpointName)]: {
                    command: 'node',
                    args: [defaultBridgePath()],
                    env: {
                        BIRKLY_URL: getBaseUrl(),
                        BIRKLY_MCP_API_KEY: apiKey
                    }
                }
            }
        };
        return JSON.stringify(config, null, 2);
    }

    function renderSetupSteps(endpointId, authMode) {
        if (authMode === 'oauth' || authMode === 'both') {
            return `
            <ol class="endpoint-setup-steps">
                <li>Copy the <strong>server URL</strong> below.</li>
                <li>Open <a href="https://claude.ai" target="_blank" rel="noopener noreferrer">claude.ai</a> → Settings → Connectors → Add connector.</li>
                <li>Paste the URL and sign in when Claude asks.</li>
                <li>Need help? <a href="${escapeHtml(DOCS_HELP_URL)}">Open the guide</a></li>
            </ol>
            <div class="mcp-snippet-block">
                <label>Server URL</label>
                <div class="copy-snippet-row">
                    <input type="text" readonly value="${escapeHtml(serverUrl)}" id="mcp-web-url-${escapeHtml(endpointId)}">
                    <button type="button" class="btn btn-sm btn-primary" onclick="mcpConnections.copySnippet('mcp-web-url-${escapeHtml(endpointId)}', true)">Copy URL</button>
                </div>
            </div>`;
        }

        return `
        <ol class="endpoint-setup-steps">
            <li>
                <a href="${escapeHtml(getBridgeDownloadUrl())}" download="mcp-bridge.js" class="btn btn-sm btn-secondary">Download connection helper</a>
                <span class="step-hint">Save it to a folder you'll keep, e.g. <em>Documents/Birkly</em></span>
            </li>
            <li>Click <strong>Prepare my Claude setup</strong> below — we'll fill in your secret key automatically.</li>
            <li>Click <strong>Copy setup for Claude</strong> and paste into Claude Desktop:
                <ul class="sub-steps">
                    <li>Open Claude Desktop → <strong>Settings</strong> (gear icon)</li>
                    <li>Go to <strong>Developer</strong> → <strong>Edit Config</strong></li>
                    <li>Paste and save — then restart Claude Desktop</li>
                </ul>
            </li>
            <li>Still stuck? <a href="${escapeHtml(DOCS_HELP_URL)}">Read the full guide with pictures</a></li>
        </ol>
        <div class="mcp-setup-actions">
            <button type="button" class="btn btn-primary" onclick="mcpConnections.prepareDesktopSetup('${escapeHtml(endpointId)}')">Prepare my Claude setup</button>
            <button type="button" class="btn btn-secondary" onclick="mcpConnections.copyClaudeConfig('${escapeHtml(endpointId)}')" id="mcp-copy-config-btn-${escapeHtml(endpointId)}" disabled>Copy setup for Claude</button>
            <button type="button" class="btn btn-sm" onclick="mcpConnections.downloadClaudeConfig('${escapeHtml(endpointId)}')" id="mcp-download-config-btn-${escapeHtml(endpointId)}" disabled>Download setup file</button>
        </div>
        <textarea class="mcp-config-snippet mcp-config-hidden" readonly id="mcp-claude-config-${escapeHtml(endpointId)}" rows="8" aria-hidden="true"></textarea>
        <p class="setup-path-note" id="mcp-path-note-${escapeHtml(endpointId)}">
            <strong>Note:</strong> After downloading the helper, if you saved it somewhere other than <code>${escapeHtml(defaultBridgePath())}</code>, open the copied setup in a text editor and update the file path on the line with <code>mcp-bridge.js</code>.
        </p>`;
    }

    function renderEndpoints(endpoints) {
        const list = document.getElementById('mcp-endpoints-list');
        if (!list) return;

        if (endpoints.length === 0) {
            list.innerHTML = `
            <div class="empty-state empty-state-friendly">
                <p><strong>No connections yet</strong></p>
                <p>Click <em>Add connection for Claude Desktop</em> above. We'll walk you through the rest — no technical skills needed.</p>
            </div>`;
            return;
        }

        list.innerHTML = endpoints.map(ep => {
            const status = ep.status || 'active';
            const authMode = ep.auth_mode || 'api_key';
            const statusClass = status === 'active' ? 'active' : 'inactive';
            const approvalLabel = ep.require_approval ? 'Asks before changes' : 'Auto-run (not recommended)';
            const typeLabel = connectionTypeLabel(authMode);

            return `
            <div class="connection-item mcp-endpoint-item" data-endpoint-id="${escapeHtml(ep.id)}">
                <div class="connection-info">
                    <div class="connection-name-row">
                        <div class="connection-name">${escapeHtml(ep.name || ep.id)}</div>
                        <span class="mcp-type-pill">${escapeHtml(typeLabel)}</span>
                    </div>
                    <div class="connection-meta">
                        <span class="connection-role">${escapeHtml(ep.role || 'viewer')}</span>
                        <span class="mcp-endpoint-status ${statusClass}">${status === 'active' ? 'Active' : 'Inactive'}</span>
                        <span>${escapeHtml(approvalLabel)}</span>
                    </div>
                    <div class="mcp-endpoint-actions">
                        <button type="button" class="btn btn-sm btn-primary mcp-toggle-setup" data-endpoint-id="${escapeHtml(ep.id)}" aria-expanded="false">
                            Show my setup steps
                        </button>
                        <button type="button" class="btn btn-sm" onclick="mcpConnections.revealKey('${escapeHtml(ep.id)}')">Show secret key</button>
                        <button type="button" class="btn btn-sm" onclick="mcpConnections.rotateKey('${escapeHtml(ep.id)}')">New secret key</button>
                        <button type="button" class="btn btn-sm btn-danger" onclick="mcpConnections.revokeEndpoint('${escapeHtml(ep.id)}')">Remove connection</button>
                    </div>
                    <div class="mcp-setup-panel" id="mcp-setup-panel-${escapeHtml(ep.id)}" hidden>
                        <h5>Your setup steps</h5>
                        ${renderSetupSteps(ep.id, authMode)}
                    </div>
                    <input type="password" value="" readonly id="mcp-key-${escapeHtml(ep.id)}" class="mcp-key-hidden" tabindex="-1" aria-hidden="true">
                </div>
            </div>`;
        }).join('');

        list.querySelectorAll('.mcp-toggle-setup').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.endpointId;
                const panel = document.getElementById('mcp-setup-panel-' + id);
                if (!panel) return;
                const open = panel.hasAttribute('hidden');
                if (open) {
                    panel.removeAttribute('hidden');
                    this.setAttribute('aria-expanded', 'true');
                    this.textContent = 'Hide setup steps';
                } else {
                    panel.setAttribute('hidden', '');
                    this.setAttribute('aria-expanded', 'false');
                    this.textContent = 'Show my setup steps';
                }
            });
        });
    }

    function showAddMcpModal() {
        const modal = document.getElementById('add-mcp-modal');
        if (!modal) return;

        const form = document.getElementById('add-mcp-form');
        form?.reset();

        const keyReveal = document.getElementById('mcp-create-key-reveal');
        if (keyReveal) keyReveal.style.display = 'none';

        const requireApproval = document.getElementById('mcp-require-approval');
        if (requireApproval) requireApproval.checked = true;

        const connType = document.getElementById('mcp-connection-type');
        if (connType) connType.value = 'desktop';

        syncConnectionTypeFields();

        const submitBtn = document.getElementById('mcp-submit-btn');
        if (submitBtn) {
            submitBtn.textContent = 'Create connection';
            submitBtn.disabled = false;
        }

        modal.style.display = 'flex';
        loadRoles();
        initHelpTips();
    }

    function hideAddMcpModal() {
        const modal = document.getElementById('add-mcp-modal');
        if (modal) modal.style.display = 'none';
    }

    async function loadRoles(selectedRole = null) {
        const select = document.getElementById('mcp-endpoint-role');
        if (!select) return;

        const legacyRoles = [
            { id: 'viewer', name: 'Viewer — read only' },
            { id: 'editor', name: 'Editor — suggest edits' },
            { id: 'admin', name: 'Admin — full access' }
        ];

        try {
            const response = await fetch(Birkly.url('/api/roles.php?action=list_roles'));
            const result = await response.json();
            let roles = (result.status === 'ok' && Array.isArray(result.roles) && result.roles.length > 0)
                ? result.roles
                : legacyRoles;

            select.innerHTML = roles.map(role => {
                const roleId = role.id || role.name || '';
                let label = role.name || roleId;
                if (roleId === 'viewer') label = 'Viewer — read only';
                if (roleId === 'editor') label = 'Editor — suggest edits';
                if (roleId === 'admin') label = 'Admin — full access';
                return `<option value="${escapeHtml(roleId)}">${escapeHtml(label)}</option>`;
            }).join('');

            if (selectedRole) {
                select.value = selectedRole;
            } else {
                const editor = roles.find(r => (r.id || r.name) === 'editor');
                select.value = editor ? 'editor' : (roles[0].id || roles[0].name || 'viewer');
            }
        } catch (error) {
            select.innerHTML = legacyRoles.map(r =>
                `<option value="${r.id}">${escapeHtml(r.name)}</option>`
            ).join('');
            select.value = 'editor';
        }
    }

    async function handleMcpCreate(e) {
        e.preventDefault();
        syncConnectionTypeFields();

        const formData = new FormData(e.target);
        const authMode = document.getElementById('mcp-auth-mode')?.value || 'api_key';
        const payload = {
            action: 'create',
            name: formData.get('name') || '',
            role: formData.get('role') || 'editor',
            require_approval: document.getElementById('mcp-require-approval')?.checked ?? true,
            auth_mode: authMode,
            _csrf: csrfToken
        };

        if (authMode === 'oauth' || authMode === 'both') {
            const clientId = (formData.get('oauth_client_id') || '').trim();
            if (clientId) payload.oauth_client_id = clientId;
        }

        const submitBtn = document.getElementById('mcp-submit-btn');
        if (submitBtn) submitBtn.disabled = true;

        try {
            const response = await fetch(Birkly.url('/api/mcp_endpoints.php'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const result = await response.json();

            if (result && result.status === 'ok') {
                const key = result.data?.mcp_api_key || '';
                const endpointId = result.data?.id || '';
                const keyReveal = document.getElementById('mcp-create-key-reveal');
                const keyDisplay = document.getElementById('mcp-new-key-display');
                if (keyReveal && keyDisplay && key) {
                    keyDisplay.value = key;
                    keyReveal.style.display = 'block';
                }
                if (submitBtn) submitBtn.textContent = 'Done — close when ready';
                await loadEndpoints();
                if (endpointId) {
                    setTimeout(() => {
                        const panel = document.getElementById('mcp-setup-panel-' + endpointId);
                        const toggle = document.querySelector(`.mcp-toggle-setup[data-endpoint-id="${endpointId}"]`);
                        if (panel && toggle) {
                            panel.removeAttribute('hidden');
                            toggle.setAttribute('aria-expanded', 'true');
                            toggle.textContent = 'Hide setup steps';
                            panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        }
                    }, 300);
                }
                showToast('Connection created! Follow the setup steps on your new card.');
            } else {
                showToast(result?.message || 'Could not create connection', true);
                if (submitBtn) submitBtn.disabled = false;
            }
        } catch (error) {
            console.error('Error creating connection:', error);
            showToast('Something went wrong. Please try again.', true);
            if (submitBtn) submitBtn.disabled = false;
        }
    }

    async function prepareDesktopSetup(endpointId) {
        try {
            const response = await fetch(Birkly.url(`/api/mcp_endpoints.php?action=get_key&id=${encodeURIComponent(endpointId)}`));
            const result = await response.json();
            if (result.status !== 'ok' || !result.data?.mcp_api_key) {
                showToast('Could not load your secret key. Try again.', true);
                return;
            }
            const key = result.data.mcp_api_key;
            const item = document.querySelector(`.mcp-endpoint-item[data-endpoint-id="${endpointId}"]`);
            const endpointName = item?.querySelector('.connection-name')?.textContent || endpointId;
            const textarea = document.getElementById('mcp-claude-config-' + endpointId);
            const keyInput = document.getElementById('mcp-key-' + endpointId);
            if (textarea) textarea.value = buildClaudeDesktopConfig(key, endpointName);
            if (keyInput) keyInput.value = key;
            document.getElementById('mcp-copy-config-btn-' + endpointId)?.removeAttribute('disabled');
            document.getElementById('mcp-download-config-btn-' + endpointId)?.removeAttribute('disabled');
            showToast('Ready! Now click "Copy setup for Claude".');
        } catch (error) {
            showToast('Could not prepare setup. Please try again.', true);
        }
    }

    function copyClaudeConfig(endpointId) {
        copySnippet('mcp-claude-config-' + endpointId, true);
    }

    function downloadClaudeConfig(endpointId) {
        const textarea = document.getElementById('mcp-claude-config-' + endpointId);
        if (!textarea || !textarea.value) {
            showToast('Click "Prepare my Claude setup" first.', true);
            return;
        }
        const blob = new Blob([textarea.value], { type: 'application/json' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'claude_desktop_config.json';
        a.click();
        URL.revokeObjectURL(a.href);
        showToast('Download started — paste this into Claude Desktop or merge with your existing config.');
    }

    async function revealKey(endpointId) {
        await prepareDesktopSetup(endpointId);
        showToast('Secret key loaded into your setup. Use "Copy setup for Claude" next.');
    }

    async function rotateKey(endpointId) {
        if (!confirm('Create a new secret key? You will need to update Claude Desktop with the new setup.')) {
            return;
        }

        try {
            const response = await fetch(Birkly.url('/api/mcp_endpoints.php'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'rotate', id: endpointId, _csrf: csrfToken })
            });
            const result = await response.json();

            if (result.status === 'ok') {
                await prepareDesktopSetup(endpointId);
                showToast('New secret key created. Copy the updated setup to Claude Desktop.');
            } else {
                showToast(result.message || 'Could not create new key', true);
            }
        } catch (error) {
            showToast('Something went wrong.', true);
        }
    }

    async function revokeEndpoint(endpointId) {
        if (!confirm('Remove this connection? Claude will no longer access your site through it.')) {
            return;
        }

        try {
            const response = await fetch(Birkly.url('/api/mcp_endpoints.php'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'revoke', id: endpointId, _csrf: csrfToken })
            });
            const result = await response.json();

            if (result.status === 'ok') {
                showToast('Connection removed.');
                loadEndpoints();
            } else {
                showToast(result.message || 'Could not remove connection', true);
            }
        } catch (error) {
            showToast('Something went wrong.', true);
        }
    }

    function copySnippet(elementId, useToast) {
        const el = document.getElementById(elementId);
        if (!el) return;

        const text = el.value || el.textContent;
        const done = () => {
            if (useToast) showToast('Copied to clipboard!');
        };

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done).catch(() => fallbackCopy(el, done));
        } else {
            fallbackCopy(el, done);
        }
    }

    function fallbackCopy(el, done) {
        if (el.select) {
            el.removeAttribute('aria-hidden');
            el.classList.remove('mcp-config-hidden');
            el.select();
            if (el.setSelectionRange) el.setSelectionRange(0, 99999);
        }
        try {
            document.execCommand('copy');
            if (done) done();
        } catch (err) {
            showToast('Could not copy — please select the text manually.', true);
        }
    }

    function showToast(message, isError) {
        let toast = document.getElementById('mcp-ui-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'mcp-ui-toast';
            toast.className = 'mcp-ui-toast';
            toast.setAttribute('role', 'status');
            toast.setAttribute('aria-live', 'polite');
            document.body.appendChild(toast);
        }
        toast.textContent = message;
        toast.classList.toggle('mcp-ui-toast-error', !!isError);
        toast.classList.add('mcp-ui-toast-visible');
        clearTimeout(toast._hideTimer);
        toast._hideTimer = setTimeout(() => toast.classList.remove('mcp-ui-toast-visible'), 4000);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
    }

    window.mcpConnections = {
        revealKey,
        rotateKey,
        revokeEndpoint,
        copySnippet,
        prepareDesktopSetup,
        copyClaudeConfig,
        downloadClaudeConfig
    };
})();
