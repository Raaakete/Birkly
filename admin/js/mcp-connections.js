/**
 * MCP Connections Management (inbound MCP endpoints)
 */

(function() {
    'use strict';

    let csrfToken = '';
    let initialized = false;
    let serverUrl = '';
    const BRIDGE_PATH_PLACEHOLDER = '/absolute/path/to/your/Birkly/mcp-bridge.js';

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

    function setupEventListeners() {
        document.getElementById('add-mcp-endpoint-btn')?.addEventListener('click', showAddMcpModal);

        document.querySelectorAll('[data-mcp-modal-close]').forEach(btn => {
            btn.addEventListener('click', hideAddMcpModal);
        });

        document.getElementById('add-mcp-form')?.addEventListener('submit', handleMcpCreate);

        const authModeSelect = document.getElementById('mcp-auth-mode');
        authModeSelect?.addEventListener('change', updateOAuthClientIdVisibility);
    }

    function updateOAuthClientIdVisibility() {
        const authMode = document.getElementById('mcp-auth-mode')?.value || 'api_key';
        const group = document.getElementById('mcp-oauth-client-id-group');
        if (group) {
            group.style.display = (authMode === 'oauth' || authMode === 'both') ? 'block' : 'none';
        }
    }

    function authModeLabel(mode) {
        const labels = {
            api_key: 'API key',
            oauth: 'OAuth',
            both: 'API key + OAuth'
        };
        return labels[mode] || mode || 'API key';
    }

    async function loadEndpoints() {
        try {
            const response = await fetch('/api/mcp_endpoints.php?action=list');
            const result = await response.json();

            if (result && result.status === 'ok' && result.data) {
                serverUrl = result.data.server_url || (window.location.origin + '/api/mcp_server.php');
                updateGlobalServerUrl();
                renderEndpoints(result.data.endpoints || []);
            } else {
                renderEndpoints([]);
            }
        } catch (error) {
            console.error('Error loading MCP endpoints:', error);
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

    function renderEndpoints(endpoints) {
        const list = document.getElementById('mcp-endpoints-list');
        if (!list) return;

        if (endpoints.length === 0) {
            list.innerHTML = '<div class="empty-state"><p>No MCP endpoints yet. Click "Add Endpoint" to let external AI tools connect to Birkly.</p></div>';
            return;
        }

        list.innerHTML = endpoints.map(ep => {
            const status = ep.status || 'active';
            const bridgeCmd = buildBridgeCommand('YOUR_MCP_API_KEY');
            const claudeConfig = buildClaudeDesktopConfig('YOUR_MCP_API_KEY', ep.name || ep.id);
            const statusClass = status === 'active' ? 'active' : 'inactive';
            const approvalLabel = ep.require_approval ? 'Approval required' : 'Auto-execute';
            const authMode = ep.auth_mode || 'api_key';
            const oauthClientId = ep.oauth_client_id || '';
            const showOAuthFields = authMode === 'oauth' || authMode === 'both';

            return `
            <div class="connection-item mcp-endpoint-item" data-endpoint-id="${escapeHtml(ep.id)}">
                <div class="connection-info">
                    <div class="connection-name">${escapeHtml(ep.name || ep.id)}</div>
                    <div class="connection-meta">
                        <span class="connection-role">${escapeHtml(ep.role || 'viewer')}</span>
                        <span class="mcp-endpoint-status ${statusClass}">${escapeHtml(status)}</span>
                        <span>${escapeHtml(approvalLabel)}</span>
                        <span class="mcp-auth-mode-label">${escapeHtml(authModeLabel(authMode))}</span>
                    </div>
                    <div class="mcp-snippet-block mcp-auth-edit-block">
                        <label for="mcp-auth-mode-${escapeHtml(ep.id)}">Auth mode</label>
                        <div class="copy-snippet-row">
                            <select id="mcp-auth-mode-${escapeHtml(ep.id)}" class="mcp-auth-mode-select" data-endpoint-id="${escapeHtml(ep.id)}">
                                <option value="api_key" ${authMode === 'api_key' ? 'selected' : ''}>API key</option>
                                <option value="oauth" ${authMode === 'oauth' ? 'selected' : ''}>OAuth</option>
                                <option value="both" ${authMode === 'both' ? 'selected' : ''}>Both</option>
                            </select>
                            <input type="text"
                                id="mcp-oauth-client-${escapeHtml(ep.id)}"
                                class="mcp-oauth-client-input"
                                placeholder="OAuth client ID (optional)"
                                value="${escapeHtml(oauthClientId)}"
                                style="display: ${showOAuthFields ? 'block' : 'none'};">
                            <button type="button" class="btn btn-sm" onclick="mcpConnections.saveAuthSettings('${escapeHtml(ep.id)}')">Save auth</button>
                        </div>
                        <small class="mcp-snippet-hint">OAuth or Both required for Claude remote connector.</small>
                    </div>
                    <div class="mcp-snippet-block">
                        <label for="mcp-claude-config-${escapeHtml(ep.id)}">Claude Desktop config (stdio bridge)</label>
                        <div class="copy-snippet-row mcp-config-snippet-row">
                            <textarea readonly id="mcp-claude-config-${escapeHtml(ep.id)}" class="mcp-config-snippet" rows="10">${escapeHtml(claudeConfig)}</textarea>
                            <button type="button" class="btn btn-sm" aria-label="Copy Claude Desktop config" onclick="mcpConnections.copySnippet('mcp-claude-config-${escapeHtml(ep.id)}')">Copy</button>
                        </div>
                        <small class="mcp-snippet-hint">Replace the bridge path and API key after revealing the key below. Paste into claude_desktop_config.json.</small>
                    </div>
                    <div class="mcp-snippet-block">
                        <label for="mcp-bridge-${escapeHtml(ep.id)}">Bridge command (shell)</label>
                        <div class="copy-snippet-row">
                            <input type="text" value="${escapeHtml(bridgeCmd)}" readonly id="mcp-bridge-${escapeHtml(ep.id)}">
                            <button type="button" class="btn btn-sm" aria-label="Copy bridge command" onclick="mcpConnections.copySnippet('mcp-bridge-${escapeHtml(ep.id)}')">Copy</button>
                        </div>
                    </div>
                    <div class="mcp-snippet-block">
                        <label for="mcp-key-${escapeHtml(ep.id)}">API Key</label>
                        <div class="copy-snippet-row">
                            <input type="password" value="" placeholder="Click Reveal to show key" readonly id="mcp-key-${escapeHtml(ep.id)}" class="mcp-key-input">
                            <button type="button" class="btn btn-sm" onclick="mcpConnections.revealKey('${escapeHtml(ep.id)}')">Reveal</button>
                            <button type="button" class="btn btn-sm" onclick="mcpConnections.rotateKey('${escapeHtml(ep.id)}')">Rotate</button>
                            <button type="button" class="btn btn-sm btn-danger" onclick="mcpConnections.revokeEndpoint('${escapeHtml(ep.id)}')">Revoke</button>
                        </div>
                    </div>
                </div>
            </div>`;
        }).join('');

        list.querySelectorAll('.mcp-auth-mode-select').forEach(select => {
            select.addEventListener('change', function() {
                const endpointId = this.dataset.endpointId;
                const oauthInput = document.getElementById('mcp-oauth-client-' + endpointId);
                if (oauthInput) {
                    const show = this.value === 'oauth' || this.value === 'both';
                    oauthInput.style.display = show ? 'block' : 'none';
                }
            });
        });
    }

    function getBaseUrl() {
        return serverUrl.replace(/\/api\/mcp_server\.php$/, '') || window.location.origin;
    }

    function buildBridgeCommand(apiKey) {
        return `BIRKLY_URL=${getBaseUrl()} BIRKLY_MCP_API_KEY=${apiKey} node mcp-bridge.js`;
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
                    args: [BRIDGE_PATH_PLACEHOLDER],
                    env: {
                        BIRKLY_URL: getBaseUrl(),
                        BIRKLY_MCP_API_KEY: apiKey
                    }
                }
            }
        };
        return JSON.stringify(config, null, 2);
    }

    function updateEndpointSnippets(endpointId, apiKey, endpointName) {
        const bridgeInput = document.getElementById('mcp-bridge-' + endpointId);
        if (bridgeInput) {
            bridgeInput.value = buildBridgeCommand(apiKey);
        }
        const configTextarea = document.getElementById('mcp-claude-config-' + endpointId);
        if (configTextarea) {
            configTextarea.value = buildClaudeDesktopConfig(apiKey, endpointName);
        }
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

        const authMode = document.getElementById('mcp-auth-mode');
        if (authMode) authMode.value = 'api_key';

        const oauthClientId = document.getElementById('mcp-oauth-client-id');
        if (oauthClientId) oauthClientId.value = '';

        updateOAuthClientIdVisibility();

        const submitBtn = document.getElementById('mcp-submit-btn');
        if (submitBtn) {
            submitBtn.textContent = 'Create Endpoint';
            submitBtn.disabled = false;
        }

        modal.style.display = 'flex';
        loadRoles();
    }

    function hideAddMcpModal() {
        const modal = document.getElementById('add-mcp-modal');
        if (modal) {
            modal.style.display = 'none';
        }
    }

    async function loadRoles(selectedRole = null) {
        const select = document.getElementById('mcp-endpoint-role');
        if (!select) return;

        const legacyRoles = [
            { id: 'viewer', name: 'Viewer' },
            { id: 'author', name: 'Author' },
            { id: 'editor', name: 'Editor' },
            { id: 'admin', name: 'Admin' }
        ];

        try {
            const response = await fetch('/api/roles.php?action=list_roles');
            const result = await response.json();
            const roles = (result.status === 'ok' && Array.isArray(result.roles) && result.roles.length > 0)
                ? result.roles
                : legacyRoles;

            select.innerHTML = roles.map(role => {
                const roleId = role.id || role.name || '';
                const label = role.name || roleId;
                return `<option value="${escapeHtml(roleId)}">${escapeHtml(label)}</option>`;
            }).join('');

            if (selectedRole) {
                select.value = selectedRole;
            } else {
                const viewerRole = roles.find(r => (r.id || r.name) === 'viewer');
                select.value = viewerRole ? 'viewer' : (roles[0].id || roles[0].name || '');
            }
        } catch (error) {
            console.error('Error loading roles:', error);
            select.innerHTML = legacyRoles.map(r =>
                `<option value="${r.id}">${escapeHtml(r.name)}</option>`
            ).join('');
        }
    }

    async function handleMcpCreate(e) {
        e.preventDefault();

        const formData = new FormData(e.target);
        const authMode = formData.get('auth_mode') || 'api_key';
        const payload = {
            action: 'create',
            name: formData.get('name') || '',
            role: formData.get('role') || 'viewer',
            require_approval: document.getElementById('mcp-require-approval')?.checked ?? true,
            auth_mode: authMode,
            _csrf: csrfToken
        };

        if (authMode === 'oauth' || authMode === 'both') {
            const clientId = (formData.get('oauth_client_id') || '').trim();
            if (clientId) {
                payload.oauth_client_id = clientId;
            }
        }

        const submitBtn = document.getElementById('mcp-submit-btn');
        if (submitBtn) submitBtn.disabled = true;

        try {
            const response = await fetch('/api/mcp_endpoints.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const result = await response.json();

            if (result && result.status === 'ok') {
                const key = result.data?.mcp_api_key || '';
                const keyReveal = document.getElementById('mcp-create-key-reveal');
                const keyDisplay = document.getElementById('mcp-new-key-display');
                if (keyReveal && keyDisplay && key) {
                    keyDisplay.value = key;
                    keyReveal.style.display = 'block';
                }
                if (submitBtn) submitBtn.textContent = 'Created — close when done';
                loadEndpoints();
                showSuccess('MCP endpoint created. Copy the API key before closing.');
            } else {
                showError(result?.message || 'Failed to create endpoint');
                if (submitBtn) submitBtn.disabled = false;
            }
        } catch (error) {
            console.error('Error creating MCP endpoint:', error);
            showError('Error creating MCP endpoint');
            if (submitBtn) submitBtn.disabled = false;
        }
    }

    async function saveAuthSettings(endpointId) {
        const authModeSelect = document.getElementById('mcp-auth-mode-' + endpointId);
        const oauthInput = document.getElementById('mcp-oauth-client-' + endpointId);
        if (!authModeSelect) return;

        const payload = {
            action: 'update',
            id: endpointId,
            auth_mode: authModeSelect.value,
            oauth_client_id: oauthInput ? oauthInput.value.trim() : '',
            _csrf: csrfToken
        };

        try {
            const response = await fetch('/api/mcp_endpoints.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const result = await response.json();

            if (result.status === 'ok') {
                showSuccess('Auth settings updated');
                loadEndpoints();
            } else {
                showError('Failed to update auth: ' + (result.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error updating MCP auth:', error);
            showError('Error updating auth settings');
        }
    }

    async function revealKey(endpointId) {
        try {
            const response = await fetch(`/api/mcp_endpoints.php?action=get_key&id=${encodeURIComponent(endpointId)}`);
            const result = await response.json();
            if (result.status !== 'ok' || !result.data?.mcp_api_key) {
                showError('API key not available');
                return;
            }
            const key = result.data.mcp_api_key;
            const input = document.getElementById('mcp-key-' + endpointId);
            if (input) {
                input.type = 'text';
                input.value = key;
            }
            const item = document.querySelector(`.mcp-endpoint-item[data-endpoint-id="${endpointId}"]`);
            const endpointName = item?.querySelector('.connection-name')?.textContent || endpointId;
            updateEndpointSnippets(endpointId, key, endpointName);
        } catch (error) {
            console.error('Error revealing MCP key:', error);
            showError('Failed to load API key');
        }
    }

    async function rotateKey(endpointId) {
        if (!confirm('Rotate API key? External tools using the old key will stop working until updated.')) {
            return;
        }

        try {
            const response = await fetch('/api/mcp_endpoints.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'rotate',
                    id: endpointId,
                    _csrf: csrfToken
                })
            });
            const result = await response.json();

            if (result.status === 'ok') {
                const key = result.data?.mcp_api_key || '';
                const input = document.getElementById('mcp-key-' + endpointId);
                if (input && key) {
                    input.type = 'text';
                    input.value = key;
                }
                const item = document.querySelector(`.mcp-endpoint-item[data-endpoint-id="${endpointId}"]`);
                const endpointName = item?.querySelector('.connection-name')?.textContent || endpointId;
                if (key) {
                    updateEndpointSnippets(endpointId, key, endpointName);
                }
                showSuccess('API key rotated. Update your bridge config with the new key.');
            } else {
                showError('Failed to rotate key: ' + (result.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error rotating MCP key:', error);
            showError('Error rotating API key');
        }
    }

    async function revokeEndpoint(endpointId) {
        if (!confirm('Revoke this endpoint? External tools will no longer be able to connect.')) {
            return;
        }

        try {
            const response = await fetch('/api/mcp_endpoints.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'revoke',
                    id: endpointId,
                    _csrf: csrfToken
                })
            });
            const result = await response.json();

            if (result.status === 'ok') {
                showSuccess('Endpoint revoked');
                loadEndpoints();
            } else {
                showError('Failed to revoke: ' + (result.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error revoking endpoint:', error);
            showError('Error revoking endpoint');
        }
    }

    function copySnippet(elementId) {
        const el = document.getElementById(elementId);
        if (!el) return;

        const text = el.value;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(() => {
                flashCopied(el);
            }).catch(() => fallbackCopy(el));
        } else {
            fallbackCopy(el);
        }
    }

    function fallbackCopy(el) {
        if (el.select) {
            el.select();
            if (el.setSelectionRange) {
                el.setSelectionRange(0, 99999);
            }
        }
        try {
            document.execCommand('copy');
            flashCopied(el);
        } catch (err) {
            console.error('Failed to copy:', err);
        }
    }

    function flashCopied(el) {
        const original = el.value;
        el.value = 'Copied!';
        setTimeout(() => { el.value = original; }, 1000);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
    }

    function showError(message) {
        alert('Error: ' + message);
    }

    function showSuccess(message) {
        alert('Success: ' + message);
    }

    window.mcpConnections = {
        revealKey,
        rotateKey,
        revokeEndpoint,
        copySnippet,
        saveAuthSettings
    };
})();
