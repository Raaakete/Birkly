/**
 * AI app connectors — copy Connector URL, paste in Claude/ChatGPT/Cursor, OAuth
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
        updateHttpsWarning();
        updateDevBridgeLink();
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

    function connectorPath() {
        return (typeof Birkly !== 'undefined' && Birkly.url) ? Birkly.url('/mcp') : '/mcp';
    }

    function resolveConnectorUrl(apiUrl) {
        const path = connectorPath();
        if (window.location.protocol === 'https:') {
            return window.location.origin + path;
        }
        if (apiUrl && apiUrl.startsWith('https:')) {
            return apiUrl;
        }
        return apiUrl || (window.location.origin + path);
    }

    function connectorUrlIsUsable(url) {
        if (!url) return false;
        try {
            const u = new URL(url);
            if (u.protocol !== 'https:') return false;
            const host = u.hostname;
            if (host === 'localhost' || host === '127.0.0.1' || host.endsWith('.local')) return false;
            return true;
        } catch (e) {
            return false;
        }
    }

    function isPublicHttps() {
        return connectorUrlIsUsable(resolveConnectorUrl(serverUrl));
    }

    function updateHttpsWarning() {
        const banner = document.getElementById('ai-https-warning');
        if (banner) {
            banner.style.display = isPublicHttps() ? 'none' : 'block';
        }
    }

    function updateDevBridgeLink() {
        const link = document.getElementById('mcp-bridge-dev-link');
        if (!link) return;
        const base = getBaseUrl().replace(/\/$/, '');
        const path = (typeof Birkly !== 'undefined' && Birkly.url) ? Birkly.url('/mcp-bridge.js') : '/mcp-bridge.js';
        try {
            link.href = path.startsWith('http') ? path : new URL(path, base + '/').href;
        } catch (e) {
            link.href = base + path;
        }
    }

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

        const endpointsList = document.getElementById('mcp-endpoints-list');
        if (endpointsList && !endpointsList.dataset.actionsBound) {
            endpointsList.dataset.actionsBound = '1';
            endpointsList.addEventListener('click', function(e) {
                const revokeBtn = e.target.closest('[data-revoke-endpoint]');
                if (revokeBtn) {
                    e.preventDefault();
                    if (revokeBtn.hasAttribute('data-revoke-agent')) {
                        revokeAgent(revokeBtn.getAttribute('data-revoke-endpoint'));
                    } else {
                        revokeEndpoint(revokeBtn.getAttribute('data-revoke-endpoint'));
                    }
                }
            });
        }
    }

    async function loadEndpoints() {
        try {
            const [agentsRes, mcpRes] = await Promise.all([
                fetch(Birkly.url('/api/ai_agents.php?action=list')),
                fetch(Birkly.url('/api/mcp_endpoints.php?action=list'))
            ]);
            const agentsResult = await agentsRes.json();
            const mcpResult = await mcpRes.json();

            const connectorUrl = mcpResult?.data?.server_url
                || agentsResult?.data?.agents?.[0]?.connector_url
                || (window.location.origin + connectorPath());
            serverUrl = resolveConnectorUrl(connectorUrl);
            updateHttpsWarning();

            const agents = (agentsResult?.status === 'ok' && agentsResult.data?.agents) ? agentsResult.data.agents : [];
            if (agents.length > 0) {
                renderAgents(agents);
                return;
            }

            if (mcpResult && mcpResult.status === 'ok' && mcpResult.data) {
                renderEndpoints(mcpResult.data.endpoints || []);
            } else {
                renderEndpoints([]);
            }
        } catch (error) {
            console.error('Error loading connections:', error);
            renderEndpoints([]);
        }
    }

    function renderAgents(agents) {
        const list = document.getElementById('mcp-endpoints-list');
        if (!list) return;

        list.innerHTML = agents.map(agent => {
            const status = agent.status || 'active';
            const statusClass = status === 'active' ? 'active' : 'inactive';
            const approvalLabel = agent.require_approval ? 'Asks before changes' : 'Auto-run';
            const url = resolveConnectorUrl(agent.connector_url || serverUrl);

            return `
            <div class="connection-item mcp-endpoint-item ai-agent-item" data-agent-id="${escapeHtml(agent.id)}">
                <div class="connection-info">
                    <div class="connection-name-row">
                        <div class="connection-name">${escapeHtml(agent.name || agent.id)}</div>
                        <span class="mcp-type-pill">AI agent</span>
                    </div>
                    ${agent.description ? `<p class="agent-description">${escapeHtml(agent.description)}</p>` : ''}
                    <div class="connection-meta">
                        <span class="connection-role">${escapeHtml(agent.role || 'viewer')}</span>
                        <span class="mcp-endpoint-status ${statusClass}">${status === 'active' ? 'Active' : 'Inactive'}</span>
                        <span>${escapeHtml(approvalLabel)}</span>
                    </div>
                    <div class="mcp-endpoint-actions">
                        <button type="button" class="btn btn-sm btn-primary mcp-toggle-setup" data-endpoint-id="${escapeHtml(agent.id)}" aria-expanded="false">
                            Show Connector URL
                        </button>
                        <button type="button" class="btn btn-sm btn-danger" data-revoke-endpoint="${escapeHtml(agent.id)}" data-revoke-agent="1">Remove</button>
                    </div>
                    <div class="mcp-setup-panel" id="mcp-setup-panel-${escapeHtml(agent.id)}" hidden>
                        <h5>Connect your AI app</h5>
                        ${renderSetupSteps(agent.id, url)}
                    </div>
                </div>
            </div>`;
        }).join('');

        bindSetupToggles(list);
    }

    function bindSetupToggles(list) {
        list.querySelectorAll('.mcp-toggle-setup').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.endpointId;
                const panel = document.getElementById('mcp-setup-panel-' + id);
                if (!panel) return;
                const open = panel.hasAttribute('hidden');
                if (open) {
                    panel.removeAttribute('hidden');
                    this.setAttribute('aria-expanded', 'true');
                    this.textContent = 'Hide Connector URL';
                } else {
                    panel.setAttribute('hidden', '');
                    this.setAttribute('aria-expanded', 'false');
                    this.textContent = 'Show Connector URL';
                }
            });
        });
    }

    function getBaseUrl() {
        return serverUrl.replace(/\/mcp$/, '').replace(/\/api\/mcp_server\.php$/, '') || window.location.origin;
    }

    function renderSetupSteps(endpointId, connectorUrlOverride) {
        const url = connectorUrlOverride || resolveConnectorUrl(serverUrl);
        const httpWarning = connectorUrlIsUsable(url) ? '' : `
        <p class="mcp-http-url-warning" role="alert">
            <strong>This URL uses HTTP.</strong> Claude will reject it — open Birkly with <code>https://</code> and copy the URL again.
        </p>`;
        return `
        <ol class="endpoint-setup-steps">
            <li>Copy this agent's <strong>Connector URL</strong> below — each agent has its own URL and role.</li>
            <li>Open your AI app (Claude, ChatGPT, or Cursor) → connector or integration settings.</li>
            <li>Paste the URL only — <strong>leave Client ID and Client Secret empty</strong>.</li>
            <li>Reconnect in your AI app if you changed agents or roles.</li>
            <li><a href="${escapeHtml(DOCS_HELP_URL)}">Step-by-step guide</a></li>
        </ol>
        ${httpWarning}
        <div class="mcp-snippet-block">
            <label for="mcp-connector-url-${escapeHtml(endpointId)}">Connector URL</label>
            <div class="copy-snippet-row">
                <input type="text" readonly value="${escapeHtml(url)}" id="mcp-connector-url-${escapeHtml(endpointId)}" class="${connectorUrlIsUsable(url) ? '' : 'mcp-url-insecure'}">
                <button type="button" class="btn btn-sm btn-primary" onclick="mcpConnections.copySnippet('mcp-connector-url-${escapeHtml(endpointId)}', true)">Copy URL</button>
            </div>
        </div>`;
    }

    function renderEndpoints(endpoints) {
        const list = document.getElementById('mcp-endpoints-list');
        if (!list) return;

        if (endpoints.length === 0) {
            list.innerHTML = `
            <div class="empty-state empty-state-friendly">
                <p><strong>No agents yet</strong></p>
                <p>Click <em>Add agent</em> above, then copy the Connector URL into Claude, ChatGPT, or Cursor.</p>
            </div>`;
            return;
        }

        list.innerHTML = endpoints.map(ep => {
            const status = ep.status || 'active';
            const statusClass = status === 'active' ? 'active' : 'inactive';
            const approvalLabel = ep.require_approval ? 'Asks before changes' : 'Auto-run';

            return `
            <div class="connection-item mcp-endpoint-item" data-endpoint-id="${escapeHtml(ep.id)}">
                <div class="connection-info">
                    <div class="connection-name-row">
                        <div class="connection-name">${escapeHtml(ep.name || ep.id)}</div>
                        <span class="mcp-type-pill">App connection</span>
                    </div>
                    <div class="connection-meta">
                        <span class="connection-role">${escapeHtml(ep.role || 'viewer')}</span>
                        <span class="mcp-endpoint-status ${statusClass}">${status === 'active' ? 'Active' : 'Inactive'}</span>
                        <span>${escapeHtml(approvalLabel)}</span>
                    </div>
                    <div class="mcp-endpoint-actions">
                        <button type="button" class="btn btn-sm btn-primary mcp-toggle-setup" data-endpoint-id="${escapeHtml(ep.id)}" aria-expanded="false">
                            Show Connector URL
                        </button>
                        <button type="button" class="btn btn-sm btn-danger" data-revoke-endpoint="${escapeHtml(ep.id)}">Remove</button>
                    </div>
                    <div class="mcp-setup-panel" id="mcp-setup-panel-${escapeHtml(ep.id)}" hidden>
                        <h5>Connect your AI app</h5>
                        ${renderSetupSteps(ep.id)}
                    </div>
                </div>
            </div>`;
        }).join('');

        bindSetupToggles(list);
    }

    function showAddMcpModal() {
        const modal = document.getElementById('add-mcp-modal');
        if (!modal) return;

        const form = document.getElementById('add-mcp-form');
        form?.reset();

        const successPanel = document.getElementById('mcp-create-success');
        if (successPanel) successPanel.style.display = 'none';

        const requireApproval = document.getElementById('mcp-require-approval');
        if (requireApproval) requireApproval.checked = true;

        const authMode = document.getElementById('mcp-auth-mode');
        if (authMode) authMode.value = 'oauth';

        const submitBtn = document.getElementById('mcp-submit-btn');
        if (submitBtn) {
            submitBtn.textContent = 'Create agent';
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

        const formData = new FormData(e.target);
        const payload = {
            action: 'create',
            name: formData.get('name') || '',
            role: formData.get('role') || 'editor',
            description: formData.get('description') || '',
            require_approval: document.getElementById('mcp-require-approval')?.checked ?? true,
            _csrf: csrfToken
        };

        const submitBtn = document.getElementById('mcp-submit-btn');
        if (submitBtn) submitBtn.disabled = true;

        try {
            const response = await fetch(Birkly.url('/api/ai_agents.php'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const result = await response.json();

            if (result && result.status === 'ok') {
                const agentId = result.data?.agent?.id || '';
                if (submitBtn) submitBtn.textContent = 'Done — close when ready';
                await loadEndpoints();
                const successPanel = document.getElementById('mcp-create-success');
                const urlInput = document.getElementById('mcp-new-connector-url');
                if (successPanel && urlInput) {
                    urlInput.value = serverUrl;
                    successPanel.style.display = 'block';
                }
                if (agentId) {
                    setTimeout(() => {
                        const panel = document.getElementById('mcp-setup-panel-' + agentId);
                        const toggle = document.querySelector(`.mcp-toggle-setup[data-endpoint-id="${agentId}"]`);
                        if (panel && toggle) {
                            panel.removeAttribute('hidden');
                            toggle.setAttribute('aria-expanded', 'true');
                            toggle.textContent = 'Hide Connector URL';
                            panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        }
                    }, 300);
                }
                showToast('AI agent created! Copy the Connector URL into your AI app.');
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

    async function refreshCsrfToken() {
        csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || csrfToken;
        return csrfToken;
    }

    async function revokeAgent(agentId) {
        if (!agentId) return;
        if (!confirm('Remove this AI agent? Your AI app will no longer access your site through it.')) {
            return;
        }

        try {
            await refreshCsrfToken();
            const response = await fetch(Birkly.url('/api/ai_agents.php'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'revoke', id: agentId, _csrf: csrfToken })
            });
            let result;
            try {
                result = await response.json();
            } catch (parseErr) {
                showToast('Server returned an invalid response. Try again.', true);
                return;
            }

            if (result.status === 'ok') {
                showToast('AI agent removed.');
                loadEndpoints();
            } else {
                showToast(result.message || 'Could not remove agent', true);
            }
        } catch (error) {
            showToast('Something went wrong.', true);
        }
    }

    async function revokeEndpoint(endpointId) {
        if (!endpointId) return;
        if (!confirm('Remove this connection? Your AI app will no longer access your site through it.')) {
            return;
        }

        try {
            await refreshCsrfToken();
            const response = await fetch(Birkly.url('/api/mcp_endpoints.php'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'revoke', id: endpointId, _csrf: csrfToken })
            });
            let result;
            try {
                result = await response.json();
            } catch (parseErr) {
                showToast('Server returned an invalid response. Try again.', true);
                return;
            }

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
        if (text.startsWith('http://')) {
            showToast('Claude requires https:// — open Birkly with HTTPS and copy again.', true);
            return;
        }

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
        revokeEndpoint,
        copySnippet
    };
})();
