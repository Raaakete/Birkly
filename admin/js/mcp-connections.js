/**
 * MCP Connections Management (inbound MCP endpoints)
 */

(function() {
    'use strict';

    let csrfToken = '';
    let initialized = false;
    let serverUrl = '';

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
            const statusClass = status === 'active' ? 'active' : 'inactive';
            const approvalLabel = ep.require_approval ? 'Approval required' : 'Auto-execute';

            return `
            <div class="connection-item mcp-endpoint-item" data-endpoint-id="${escapeHtml(ep.id)}">
                <div class="connection-info">
                    <div class="connection-name">${escapeHtml(ep.name || ep.id)}</div>
                    <div class="connection-meta">
                        <span class="connection-role">${escapeHtml(ep.role || 'viewer')}</span>
                        <span class="mcp-endpoint-status ${statusClass}">${escapeHtml(status)}</span>
                        <span>${escapeHtml(approvalLabel)}</span>
                    </div>
                    <div class="mcp-snippet-block">
                        <label for="mcp-bridge-${escapeHtml(ep.id)}">Bridge command</label>
                        <div class="copy-snippet-row">
                            <input type="text" value="${escapeHtml(bridgeCmd)}" readonly id="mcp-bridge-${escapeHtml(ep.id)}">
                            <button type="button" class="btn btn-sm" aria-label="Copy bridge command" onclick="mcpConnections.copySnippet('mcp-bridge-${escapeHtml(ep.id)}')">Copy</button>
                        </div>
                        <small class="mcp-snippet-hint">Replace YOUR_MCP_API_KEY after revealing the key below. Server URL is shown above.</small>
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
    }

    function buildBridgeCommand(apiKey) {
        const baseUrl = serverUrl.replace(/\/api\/mcp_server\.php$/, '') || window.location.origin;
        return `BIRKLY_URL=${baseUrl} BIRKLY_MCP_API_KEY=${apiKey} node mcp-bridge.js`;
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
        const payload = {
            action: 'create',
            name: formData.get('name') || '',
            role: formData.get('role') || 'viewer',
            require_approval: document.getElementById('mcp-require-approval')?.checked ?? true,
            _csrf: csrfToken
        };

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
            const bridgeInput = document.getElementById('mcp-bridge-' + endpointId);
            if (bridgeInput) {
                bridgeInput.value = buildBridgeCommand(key);
            }
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
                const bridgeInput = document.getElementById('mcp-bridge-' + endpointId);
                if (bridgeInput && key) {
                    bridgeInput.value = buildBridgeCommand(key);
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

    function copySnippet(inputId) {
        const input = document.getElementById(inputId);
        if (!input) return;

        const text = input.value;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(() => {
                flashCopied(input);
            }).catch(() => fallbackCopy(input));
        } else {
            fallbackCopy(input);
        }
    }

    function fallbackCopy(input) {
        input.select();
        input.setSelectionRange(0, 99999);
        try {
            document.execCommand('copy');
            flashCopied(input);
        } catch (err) {
            console.error('Failed to copy:', err);
        }
    }

    function flashCopied(input) {
        const original = input.value;
        input.value = 'Copied!';
        setTimeout(() => { input.value = original; }, 1000);
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
        copySnippet
    };
})();
