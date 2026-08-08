/**
 * AI app connectors — personal + service agents (P5.1)
 */

(function() {
    'use strict';

    let csrfToken = '';
    let initialized = false;
    let serverUrl = '';
    let isAdmin = false;
    let personalAgentUrl = '';
    const DOCS_HELP_URL = typeof Birkly !== 'undefined' && Birkly.adminUrl
        ? Birkly.adminUrl('help') + '#mcp-connections'
        : '/admin/index.php?page=help#mcp-connections';

    function isAiIntegrationsContext() {
        const pane = document.getElementById('ai-integrations');
        if (!pane) return false;
        const hash = window.location.hash.replace('#', '');
        const aiHashes = [
            'ai-integrations',
            'ai-connections',
            'mcp-connections',
            'outbound-connections',
            'inbound-mcp'
        ];
        if (aiHashes.indexOf(hash) >= 0) {
            return true;
        }
        const aiTab = document.getElementById('ai');
        return !!(aiTab && aiTab.classList.contains('active'));
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
        maybeInitMcpConnections();
        window.addEventListener('hashchange', maybeInitMcpConnections);
        window.addEventListener('birkly:ai-integrations-tab', maybeInitMcpConnections);
    });

    function connectorPath() {
        return (typeof Birkly !== 'undefined' && Birkly.url) ? Birkly.url('/mcp') : '/mcp';
    }

    function resolveConnectorUrl(apiUrl) {
        if (apiUrl) {
            try {
                const parsed = apiUrl.startsWith('http')
                    ? new URL(apiUrl)
                    : new URL(apiUrl, window.location.origin);
                if (parsed.pathname.includes('/mcp/agents/')) {
                    return parsed.origin + parsed.pathname;
                }
                if (parsed.protocol === 'https:') {
                    return parsed.href;
                }
            } catch (e) {
                // Fall through to legacy resolution.
            }
        }

        const path = connectorPath();
        if (window.location.protocol === 'https:') {
            return window.location.origin + path;
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
            banner.hidden = isPublicHttps();
        }
    }

    function updateDevBridgeLink() {
        const links = document.querySelectorAll('#mcp-bridge-dev-link, a[data-mcp-bridge-link]');
        if (!links.length) return;
        const base = getBaseUrl().replace(/\/$/, '');
        const path = (typeof Birkly !== 'undefined' && Birkly.url) ? Birkly.url('/mcp-bridge.js') : '/mcp-bridge.js';
        let href;
        try {
            href = path.startsWith('http') ? path : new URL(path, base + '/').href;
        } catch (e) {
            href = base + path;
        }
        links.forEach(function (link) {
            link.href = href;
        });
    }

    function initHelpTips() {
        document.querySelectorAll('#ai-integrations .help-tip[data-tip]').forEach(btn => {
            if (btn.dataset.tipBound) return;
            btn.dataset.tipBound = '1';
            btn.addEventListener('click', async function (e) {
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

        document.querySelectorAll('input[name="agent_type"]').forEach(radio => {
            radio.addEventListener('change', updateAgentTypePanels);
        });

        document.getElementById('mcp-personal-done-btn')?.addEventListener('click', async function (e) {
            e.preventDefault();
            hideAddMcpModal();
            focusPersonalConnector();
        });

        document.querySelectorAll('.mcp-preset-btn').forEach(btn => {
            btn.addEventListener('click', async function (e) {
                e.preventDefault();
                applyMcpPreset(btn.getAttribute('data-mcp-preset'));
            });
        });

        document.getElementById('mcp-copy-personal-url-btn')?.addEventListener('click', async function (e) {
            e.preventDefault();
            copySnippet('mcp-modal-personal-url', true);
        });

        const serviceList = document.getElementById('ai-service-agents-list');
        if (serviceList && !serviceList.dataset.actionsBound) {
            serviceList.dataset.actionsBound = '1';
            serviceList.addEventListener('click', async function (e) {
                const revokeBtn = e.target.closest('[data-revoke-endpoint]');
                if (revokeBtn) {
                    e.preventDefault();
                    revokeAgent(revokeBtn.getAttribute('data-revoke-endpoint'));
                }
                const toggleBtn = e.target.closest('[data-toggle-personal-status]');
                if (toggleBtn) {
                    e.preventDefault();
                    togglePersonalStatus(toggleBtn.getAttribute('data-toggle-personal-status'));
                }
            });
        }

        const personalCard = document.getElementById('ai-personal-agent-card');
        if (personalCard && !personalCard.dataset.actionsBound) {
            personalCard.dataset.actionsBound = '1';
            personalCard.addEventListener('click', async function (e) {
                const toggleBtn = e.target.closest('[data-toggle-personal-status]');
                if (toggleBtn) {
                    e.preventDefault();
                    togglePersonalStatus(toggleBtn.getAttribute('data-toggle-personal-status'));
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

            const data = agentsResult?.data || {};
            isAdmin = !!data.is_admin;

            const personal = data.personal_agent || null;
            const serviceAgents = Array.isArray(data.service_agents) ? data.service_agents : [];

            personalAgentUrl = personal?.connector_url
                ? resolveConnectorUrl(personal.connector_url)
                : '';

            const connectorUrl = personalAgentUrl
                || mcpResult?.data?.server_url
                || (window.location.origin + connectorPath());
            serverUrl = resolveConnectorUrl(connectorUrl);
            updateHttpsWarning();

            renderPersonalAgent(personal);
            renderServiceAgents(serviceAgents);
            updateAdminUi(serviceAgents);
        } catch (error) {
            console.error('Error loading connections:', error);
            renderPersonalAgent(null);
            renderServiceAgents([]);
        }
    }

    function typeBadge(type) {
        if (type === 'personal') {
            return '<span class="badge badge-success">Personal</span>';
        }
        return '<span class="badge badge-info">Service</span>';
    }

    function visibilitySummary(agent) {
        const roles = agent.visible_to_roles || [];
        const users = agent.allowed_users || [];
        const parts = [];
        if (roles.length) {
            parts.push('Roles: ' + roles.join(', '));
        }
        if (users.length) {
            parts.push(users.length + ' user(s)');
        }
        if (!parts.length) {
            return 'Admin only';
        }
        return parts.join(' · ');
    }

    function renderPersonalAgent(agent) {
        const card = document.getElementById('ai-personal-agent-card');
        if (!card) return;

        if (!agent) {
            card.innerHTML = `
            <div class="empty-state empty-state-friendly">
                <p><strong>Personal connector unavailable</strong></p>
                <p>Refresh the page. If this persists, contact your administrator.</p>
            </div>`;
            return;
        }

        const status = agent.status || 'active';
        const statusClass = status === 'active' ? 'active' : 'inactive';
        const url = resolveConnectorUrl(agent.connector_url || serverUrl);
        const agentId = agent.id || 'ai_agent_personal';
        const disabledNote = status === 'disabled'
            ? '<p class="agent-disabled-note" role="alert">Personal connector is disabled. Only admins can re-enable it.</p>'
            : '';

        card.innerHTML = `
            <div class="ai-connection-entry" data-agent-id="${escapeHtml(agentId)}">
                <div class="ai-connection-entry-header">
                    <strong>${escapeHtml(agent.name || 'Your personal connector')}</strong>
                    <span class="mcp-endpoint-status ${statusClass}">${status === 'active' ? 'Active' : 'Disabled'}</span>
                </div>
                <p class="settings-help">${escapeHtml(agent.description || 'Connect Claude or ChatGPT with your own Birkly permissions. Every team member uses this same URL but signs in with their own account.')}</p>
                <div class="ai-connection-entry-meta">
                    <span class="connection-role">Your role when you connect</span>
                </div>
                ${disabledNote}
                <div class="ai-connection-entry-setup">
                    ${renderSetupSteps(agentId, url, true)}
                </div>
                ${isAdmin ? `<div class="ai-connection-entry-actions"><button type="button" class="btn btn-sm btn-secondary" data-toggle-personal-status="${status === 'active' ? 'disabled' : 'active'}">${status === 'active' ? 'Disable' : 'Enable'}</button></div>` : ''}
            </div>`;
    }

    function renderServiceAgents(agents) {
        const list = document.getElementById('ai-service-agents-list');
        if (!list) return;

        if (agents.length === 0) {
            list.innerHTML = `
            <div class="empty-state empty-state-friendly">
                <p><strong>${isAdmin ? 'No service agents yet' : 'No service agents assigned to you'}</strong></p>
                <p>${isAdmin
                    ? 'Click <em>Add agent</em> to create a shared connection with a fixed role.'
                    : 'Ask an admin to assign you a service agent, or use the personal connector.'}</p>
            </div>`;
            return;
        }

        list.innerHTML = agents.map(agent => {
            const status = agent.status || 'active';
            const statusClass = status === 'active' ? 'active' : 'inactive';
            const url = resolveConnectorUrl(agent.connector_url || serverUrl);

            return `
            <div class="ai-connection-entry" data-agent-id="${escapeHtml(agent.id)}">
                <div class="ai-connection-entry-header">
                    <strong>${escapeHtml(agent.name || agent.id)}</strong>
                    <span class="mcp-endpoint-status ${statusClass}">${status === 'active' ? 'Active' : 'Inactive'}</span>
                </div>
                ${agent.description ? `<p class="settings-help">${escapeHtml(agent.description)}</p>` : ''}
                <div class="ai-connection-entry-meta">
                    <span class="connection-role">Fixed role: ${escapeHtml(agent.role || 'viewer')}</span>
                    ${isAdmin ? `<span class="agent-visibility">${escapeHtml(visibilitySummary(agent))}</span>` : ''}
                </div>
                <div class="ai-connection-entry-setup">
                    ${renderSetupSteps(agent.id, url, false)}
                </div>
                ${isAdmin ? `<div class="ai-connection-entry-actions"><button type="button" class="btn btn-sm btn-danger" data-revoke-endpoint="${escapeHtml(agent.id)}">Remove</button></div>` : ''}
            </div>`;
        }).join('');
    }

    function updateAdminUi(serviceAgents) {
        const addBtn = document.getElementById('add-mcp-endpoint-btn');
        const intro = document.getElementById('ai-service-intro');
        const banner = document.getElementById('ai-agents-migration-banner');

        if (addBtn) {
            addBtn.hidden = !isAdmin;
        }
        if (intro) {
            intro.hidden = !isAdmin;
        }

        if (banner && isAdmin) {
            const hasAdminOnly = serviceAgents.some(agent => {
                const roles = agent.visible_to_roles || [];
                const users = agent.allowed_users || [];
                return roles.length === 1 && roles[0] === 'admin' && users.length === 0;
            });
            banner.hidden = !hasAdminOnly;
        } else if (banner) {
            banner.hidden = true;
        }
    }

    function bindSetupToggles(container) {
        container.querySelectorAll('.mcp-toggle-setup').forEach(btn => {
            if (btn.dataset.toggleBound) return;
            btn.dataset.toggleBound = '1';
            btn.addEventListener('click', async function () {
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

    function renderSetupSteps(endpointId, connectorUrlOverride, isPersonal) {
        const url = connectorUrlOverride || resolveConnectorUrl(serverUrl);
        const httpWarning = connectorUrlIsUsable(url) ? '' : `
        <p class="mcp-http-url-warning" role="alert">
            <strong>This URL uses HTTP.</strong> Claude will reject it — open Birkly with <code>https://</code> and copy the URL again.
        </p>`;
        const personalNote = isPersonal
            ? 'Sign in with <strong>your</strong> Birkly account — permissions follow your role.'
            : 'This service agent uses a <strong>fixed role</strong> set by your admin.';

        // Kept intentionally brief: exact menu paths in Claude/ChatGPT/Cursor
        // change often, so the canonical steps live in the linked guide
        // instead of going stale here (P56-R2 #7).
        return `
        <ol class="endpoint-setup-steps">
            <li>Copy the <strong>Connector URL</strong> below and add it as a remote MCP server in your AI app — leave Client ID/Secret empty (OAuth is automatic).</li>
            <li>${personalNote}</li>
            <li><a href="${escapeHtml(DOCS_HELP_URL)}">Full setup guide</a> for Claude, ChatGPT, and Cursor.</li>
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

    function getSelectedAgentType() {
        return document.querySelector('input[name="agent_type"]:checked')?.value || 'personal';
    }

    function updateAgentTypePanels() {
        const type = getSelectedAgentType();
        const personalPanel = document.getElementById('mcp-personal-agent-panel');
        const servicePanel = document.getElementById('mcp-service-agent-panel');
        const personalDoneBtn = document.getElementById('mcp-personal-done-btn');
        const submitBtn = document.getElementById('mcp-submit-btn');
        const nameInput = document.getElementById('mcp-endpoint-name');
        const roleSelect = document.getElementById('mcp-endpoint-role');

        const isPersonal = type === 'personal';
        if (personalPanel) personalPanel.hidden = !isPersonal;
        if (servicePanel) servicePanel.hidden = isPersonal;
        if (personalDoneBtn) personalDoneBtn.hidden = !isPersonal;
        if (submitBtn) submitBtn.hidden = isPersonal;

        if (nameInput) nameInput.required = !isPersonal;
        if (roleSelect) roleSelect.required = !isPersonal;

        const modalUrl = document.getElementById('mcp-modal-personal-url');
        if (modalUrl) {
            modalUrl.value = personalAgentUrl || '';
        }
    }

    function focusPersonalConnector() {
        const card = document.getElementById('ai-personal-agent-card');
        const agentId = 'ai_agent_personal';
        const panel = document.getElementById('mcp-setup-panel-' + agentId);
        const toggle = document.querySelector(`.mcp-toggle-setup[data-endpoint-id="${agentId}"]`);
        if (panel && toggle) {
            panel.removeAttribute('hidden');
            toggle.setAttribute('aria-expanded', 'true');
            toggle.textContent = 'Hide Connector URL';
        }
        card?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function showAddMcpModal() {
        if (!isAdmin) return;

        const modal = document.getElementById('add-mcp-modal');
        if (!modal) return;

        const form = document.getElementById('add-mcp-form');
        form?.reset();

        const personalRadio = document.getElementById('mcp-agent-type-personal');
        if (personalRadio) personalRadio.checked = true;

        const successPanel = document.getElementById('mcp-create-success');
        if (successPanel) successPanel.hidden = true;

        const submitBtn = document.getElementById('mcp-submit-btn');
        if (submitBtn) {
            submitBtn.textContent = 'Create service agent';
            submitBtn.disabled = false;
        }

        updateAgentTypePanels();

        modal.style.display = 'flex';
        loadRoles();
        loadVisibilityPickers();
        initHelpTips();
    }

    function hideAddMcpModal() {
        const modal = document.getElementById('add-mcp-modal');
        if (modal) modal.style.display = 'none';
    }

    function applyMcpPreset(preset) {
        const serviceRadio = document.getElementById('mcp-agent-type-service');
        if (serviceRadio) serviceRadio.checked = true;
        updateAgentTypePanels();

        const nameInput = document.getElementById('mcp-endpoint-name');
        const descInput = document.getElementById('mcp-agent-description');
        const roleSelect = document.getElementById('mcp-endpoint-role');

        if (preset === 'site-builder') {
            if (nameInput) nameInput.value = 'Site builder';
            if (descInput) descInput.value = 'Build and edit project/ HTML, CSS, and JS with CMS content tools';
            if (roleSelect) roleSelect.value = 'admin';
            return;
        }

        if (preset === 'content') {
            if (nameInput) nameInput.value = 'Content assistant';
            if (descInput) descInput.value = 'Draft and edit CMS collections and entries';
            if (roleSelect) roleSelect.value = 'editor';
        }
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

            return roles;
        } catch (error) {
            select.innerHTML = legacyRoles.map(r =>
                `<option value="${r.id}">${escapeHtml(r.name)}</option>`
            ).join('');
            select.value = 'editor';
            return legacyRoles;
        }
    }

    async function loadVisibilityPickers() {
        const rolesContainer = document.getElementById('mcp-visible-roles');
        const usersContainer = document.getElementById('mcp-allowed-users');
        if (!rolesContainer || !usersContainer) return;

        rolesContainer.innerHTML = '<div class="loading-inline">Loading roles...</div>';
        usersContainer.innerHTML = '<div class="loading-inline">Loading users...</div>';

        const roles = await loadRoles();
        rolesContainer.innerHTML = roles.map(role => {
            const roleId = role.id || role.name || '';
            const checked = roleId === 'admin' ? ' checked' : '';
            return `<label class="mcp-checkbox-label"><input type="checkbox" name="visible_to_roles" value="${escapeHtml(roleId)}"${checked}> ${escapeHtml(role.name || roleId)}</label>`;
        }).join('');

        try {
            const response = await fetch(Birkly.url('/api/ai_agents.php?action=users'));
            const result = await response.json();
            const users = (result.status === 'ok' && result.data?.users) ? result.data.users : [];

            if (!users.length) {
                usersContainer.innerHTML = '<p class="u-fg-secondary">No users available.</p>';
                return;
            }

            usersContainer.innerHTML = users.map(user => {
                const label = user.name || user.email || user.id;
                return `<label class="mcp-checkbox-label"><input type="checkbox" name="allowed_users" value="${escapeHtml(user.id)}"> ${escapeHtml(label)} <span class="u-fg-secondary">(${escapeHtml(user.role || '')})</span></label>`;
            }).join('');
        } catch (error) {
            usersContainer.innerHTML = '<p class="u-fg-secondary">Could not load users.</p>';
        }
    }

    function collectCheckedValues(container, name) {
        if (!container) return [];
        return Array.from(container.querySelectorAll(`input[name="${name}"]:checked`))
            .map(input => input.value)
            .filter(Boolean);
    }

    async function handleMcpCreate(e) {
        e.preventDefault();

        if (getSelectedAgentType() === 'personal') {
            hideAddMcpModal();
            focusPersonalConnector();
            return;
        }

        const formData = new FormData(e.target);
        const rolesContainer = document.getElementById('mcp-visible-roles');
        const usersContainer = document.getElementById('mcp-allowed-users');

        const payload = {
            action: 'create',
            name: formData.get('name') || '',
            role: formData.get('role') || 'editor',
            description: formData.get('description') || '',
            visible_to_roles: collectCheckedValues(rolesContainer, 'visible_to_roles'),
            allowed_users: collectCheckedValues(usersContainer, 'allowed_users'),
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
                const agentUrl = result.data?.agent?.connector_url || serverUrl;
                if (submitBtn) submitBtn.textContent = 'Done — close when ready';
                await loadEndpoints();
                const successPanel = document.getElementById('mcp-create-success');
                const urlInput = document.getElementById('mcp-new-connector-url');
                if (successPanel && urlInput) {
                    urlInput.value = resolveConnectorUrl(agentUrl);
                    successPanel.hidden = false;
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
                showToast('Service agent created! Copy the Connector URL into your AI app.');
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

    async function togglePersonalStatus(newStatus) {
        if (!isAdmin || !newStatus) return;

        try {
            await refreshCsrfToken();
            const response = await fetch(Birkly.url('/api/ai_agents.php'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'update',
                    id: 'ai_agent_personal',
                    status: newStatus,
                    _csrf: csrfToken
                })
            });
            const result = await response.json();
            if (result.status === 'ok') {
                showToast(newStatus === 'active' ? 'Personal connector enabled.' : 'Personal connector disabled.');
                loadEndpoints();
            } else {
                showToast(result.message || 'Could not update personal connector', true);
            }
        } catch (error) {
            showToast('Something went wrong.', true);
        }
    }

    async function refreshCsrfToken() {
        csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || csrfToken;
        return csrfToken;
    }

    async function revokeAgent(agentId) {
        if (!agentId || !isAdmin) return;
        if (!(await BirklyFeedback.confirm('Remove this service agent? Your AI app will no longer access your site through it.'))) {
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
                showToast('Service agent removed.');
                loadEndpoints();
            } else {
                showToast(result.message || 'Could not remove agent', true);
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
        copySnippet
    };

    // Explicit mount entry point (P60-M4) so surfaces other than Agent Guide → Connections —
    // e.g. the Agent Guide → Connections zone — can initialize the same module.
    // `initMcpConnections` guards against double-init, so this is safe to call
    // repeatedly. The two surfaces are separate pages, so the shared DOM ids
    // never collide at runtime.
    window.McpConnections = {
        init: initMcpConnections,
        copySnippet: copySnippet
    };
})();
