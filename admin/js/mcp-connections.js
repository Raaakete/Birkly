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

    function mcpT(key, fallback) {
        if (typeof Birkly !== 'undefined' && Birkly.i18n && typeof Birkly.i18n.t === 'function') {
            return Birkly.i18n.t('admin.' + key, fallback);
        }
        return fallback != null ? fallback : key;
    }

    const DOCS_HELP_URL = typeof Birkly !== 'undefined' && Birkly.adminUrl
        ? Birkly.adminUrl('help') + '&topic=mcp-connections'
        : '/admin/index.php?page=help&topic=mcp-connections';

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
        const publicOk = isPublicHttps();
        const localCallout = document.getElementById('ai-local-address-callout');
        if (localCallout) {
            localCallout.hidden = publicOk;
        }
        const desktopCard = document.getElementById('ai-desktop-connector-card');
        if (desktopCard) {
            desktopCard.hidden = publicOk;
        }
        const legacyBanner = document.getElementById('ai-https-warning');
        if (legacyBanner) {
            legacyBanner.hidden = true;
        }
    }

    function desktopApiUrl() {
        return (typeof Birkly !== 'undefined' && Birkly.url)
            ? Birkly.url('/api/admin_mcp_desktop.php')
            : '/api/admin_mcp_desktop.php';
    }

    async function loadDesktopConnector() {
        const body = document.getElementById('ai-desktop-connector-body');
        if (!body || isPublicHttps()) {
            return;
        }
        try {
            const res = await fetch(desktopApiUrl() + '?action=status', { credentials: 'same-origin' });
            const result = await res.json();
            const data = result?.data || {};
            renderDesktopConnector(data);
        } catch (err) {
            console.error('Desktop connector status:', err);
            renderDesktopConnector({ paired: false, error: true });
        }
    }

    function detectDesktopOsHint() {
        const ua = navigator.userAgent || '';
        const platform = navigator.platform || '';
        if (/Win/i.test(platform) || /Windows/i.test(ua)) {
            return 'win';
        }
        if (/Mac/i.test(platform) || /Macintosh/i.test(ua)) {
            return 'mac';
        }
        return 'mac';
    }

    function downloadDesktopHelper(target, os) {
        const url = desktopApiUrl()
            + '?action=download_helper'
            + '&target=' + encodeURIComponent(target)
            + '&os=' + encodeURIComponent(os);
        window.location.assign(url);
    }

    function bindDesktopSetupUi(body, config) {
        const osHint = detectDesktopOsHint();
        body.querySelectorAll('[data-desktop-target]').forEach(btn => {
            btn.addEventListener('click', function () {
                body.querySelectorAll('[data-desktop-target]').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                const panel = body.querySelector('[data-desktop-panel="' + btn.getAttribute('data-desktop-target') + '"]');
                body.querySelectorAll('[data-desktop-panel]').forEach(p => { p.hidden = true; });
                if (panel) panel.hidden = false;
            });
        });
        body.querySelectorAll('[data-download-desktop-helper]').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                const target = btn.getAttribute('data-download-desktop-helper') || 'claude';
                const os = btn.getAttribute('data-download-os') || osHint;
                downloadDesktopHelper(target, os);
            });
        });
        body.querySelector('[data-copy-desktop-config]')?.addEventListener('click', function () {
            const ta = document.getElementById('mcp-desktop-config-json');
            if (!ta) return;
            fallbackCopy(ta, function () {
                showToast(mcpT('ai_conn_desktop_config_copied', 'Config copied'));
            });
        });
    }

    function renderDesktopSetupBlock(config) {
        if (!config) return '';
        const json = JSON.stringify(config, null, 2);
        const osHint = detectDesktopOsHint();
        const macPrimary = osHint === 'mac';
        const winPrimary = osHint === 'win';

        return `
        <div class="u-stack-4" id="mcp-desktop-setup-root">
            <p class="settings-help">${escapeHtml(mcpT('ai_conn_desktop_setup_intro', 'Choose your desktop app, download the small helper, and open it once. No copying JSON.'))}</p>
            <div class="u-cluster-3" role="tablist">
                <button type="button" class="btn btn-sm btn-secondary active" data-desktop-target="claude">${escapeHtml(mcpT('ai_conn_desktop_app_claude', 'Claude Desktop'))}</button>
                <button type="button" class="btn btn-sm btn-secondary" data-desktop-target="cursor">${escapeHtml(mcpT('ai_conn_desktop_app_cursor', 'Cursor'))}</button>
            </div>
            <div data-desktop-panel="claude" class="u-stack-3">
                <ol class="endpoint-setup-steps">
                    <li>${escapeHtml(mcpT('ai_conn_desktop_step_download', 'Download the helper for your computer.'))}</li>
                    <li>${escapeHtml(mcpT('ai_conn_desktop_step_open_mac', 'Double-click the file. If macOS asks, choose Open.'))}</li>
                    <li>${escapeHtml(mcpT('ai_conn_desktop_step_restart', 'Fully quit Claude Desktop, then open it again.'))}</li>
                </ol>
                <div class="u-cluster-3">
                    <button type="button" class="btn ${macPrimary ? 'btn-primary' : 'btn-secondary'}" data-download-desktop-helper="claude" data-download-os="mac">${escapeHtml(mcpT('ai_conn_desktop_download_mac_claude', 'Download for Mac'))}</button>
                    <button type="button" class="btn ${winPrimary ? 'btn-primary' : 'btn-secondary'}" data-download-desktop-helper="claude" data-download-os="win">${escapeHtml(mcpT('ai_conn_desktop_download_win_claude', 'Download for Windows'))}</button>
                </div>
            </div>
            <div data-desktop-panel="cursor" class="u-stack-3" hidden>
                <ol class="endpoint-setup-steps">
                    <li>${escapeHtml(mcpT('ai_conn_desktop_step_download', 'Download the helper for your computer.'))}</li>
                    <li>${escapeHtml(mcpT('ai_conn_desktop_step_open_mac', 'Double-click the file. If macOS asks, choose Open.'))}</li>
                    <li>${escapeHtml(mcpT('ai_conn_desktop_step_restart_cursor', 'Fully quit Cursor, then open it again.'))}</li>
                </ol>
                <div class="u-cluster-3">
                    <button type="button" class="btn ${macPrimary ? 'btn-primary' : 'btn-secondary'}" data-download-desktop-helper="cursor" data-download-os="mac">${escapeHtml(mcpT('ai_conn_desktop_download_mac_cursor', 'Download for Mac'))}</button>
                    <button type="button" class="btn ${winPrimary ? 'btn-primary' : 'btn-secondary'}" data-download-desktop-helper="cursor" data-download-os="win">${escapeHtml(mcpT('ai_conn_desktop_download_win_cursor', 'Download for Windows'))}</button>
                </div>
            </div>
            <details class="u-text-sm">
                <summary class="u-clickable">${escapeHtml(mcpT('ai_conn_desktop_advanced_summary', 'Advanced: copy JSON manually'))}</summary>
                <div class="mcp-snippet-block u-stack-2 u-pt-2">
                    <textarea id="mcp-desktop-config-json" class="input input-mono" rows="8" readonly>${escapeHtml(json)}</textarea>
                    <button type="button" class="btn btn-sm btn-secondary" data-copy-desktop-config>${escapeHtml(mcpT('ai_mcp_click_copy', 'Click to copy'))}</button>
                </div>
            </details>
        </div>`;
    }

    function renderDesktopConnector(data) {
        const body = document.getElementById('ai-desktop-connector-body');
        if (!body) return;

        if (data.error) {
            body.innerHTML = `<p class="message message-error" role="alert">${escapeHtml(mcpT('ai_conn_desktop_status_error', 'Could not load desktop pairing status. Refresh the page.'))}</p>`;
            return;
        }

        if (!data.paired) {
            body.innerHTML = `
            <p class="settings-help">${escapeHtml(mcpT('ai_conn_desktop_unpaired_body', 'Pair this Birkly site with Claude Desktop or Cursor on the same computer. Uses PHP only — no Node or extra installs.'))}</p>
            <button type="button" class="btn btn-primary" id="mcp-desktop-pair-btn">${escapeHtml(mcpT('ai_conn_desktop_pair_btn', 'Pair this computer'))}</button>`;
            document.getElementById('mcp-desktop-pair-btn')?.addEventListener('click', pairDesktopConnector);
            return;
        }

        const pairedAt = data.paired_at
            ? mcpT('ai_conn_desktop_paired_at', 'Paired %s').replace('%s', data.paired_at)
            : mcpT('ai_conn_desktop_paired', 'Paired for this user on this server');
        const invalidNote = data.invalid
            ? `<p class="message message-warning" role="alert">${escapeHtml(mcpT('ai_conn_desktop_invalid', 'Credentials file is invalid. Pair again.'))}</p>`
            : '';

        body.innerHTML = `
            ${invalidNote}
            <p class="settings-help">${escapeHtml(pairedAt)}</p>
            ${renderDesktopSetupBlock(data.claude_desktop_config)}
            <div class="u-cluster-3">
                <button type="button" class="btn btn-sm btn-secondary" id="mcp-desktop-repair-btn">${escapeHtml(mcpT('ai_conn_desktop_repair_btn', 'Re-pair'))}</button>
                <button type="button" class="btn btn-sm btn-danger" id="mcp-desktop-revoke-btn">${escapeHtml(mcpT('ai_conn_desktop_revoke_btn', 'Revoke'))}</button>
            </div>`;

        bindDesktopSetupUi(body, data.claude_desktop_config);
        document.getElementById('mcp-desktop-repair-btn')?.addEventListener('click', pairDesktopConnector);
        document.getElementById('mcp-desktop-revoke-btn')?.addEventListener('click', revokeDesktopConnector);
    }

    async function pairDesktopConnector() {
        const body = document.getElementById('ai-desktop-connector-body');
        if (body) {
            body.innerHTML = `<div class="loading-state"><p>${escapeHtml(mcpT('ai_conn_desktop_pairing', 'Pairing…'))}</p></div>`;
        }
        try {
            const res = await fetch(desktopApiUrl(), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({ action: 'pair', _csrf: csrfToken })
            });
            const result = await res.json();
            if (result.status !== 'ok') {
                throw new Error(result.message || 'pair failed');
            }
            const data = result.data || {};
            renderDesktopConnector({
                paired: true,
                paired_at: new Date().toISOString(),
                claude_desktop_config: data.claude_desktop_config
            });
            showToast(mcpT('ai_conn_desktop_pair_success', 'Paired. Download the helper below and open it once.'));
        } catch (err) {
            console.error(err);
            renderDesktopConnector({ paired: false });
            showToast(mcpT('ai_conn_desktop_pair_failed', 'Pairing failed. Check that your personal connector is active.'), true);
        }
    }

    async function revokeDesktopConnector() {
        if (!window.confirm(mcpT('ai_conn_desktop_revoke_confirm', 'Revoke desktop pairing? The desktop app will stop working until you pair again.'))) {
            return;
        }
        try {
            const res = await fetch(desktopApiUrl(), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({ action: 'revoke', _csrf: csrfToken })
            });
            const result = await res.json();
            if (result.status !== 'ok') {
                throw new Error(result.message || 'revoke failed');
            }
            renderDesktopConnector({ paired: false });
            showToast(mcpT('ai_conn_desktop_revoked', 'Desktop pairing revoked.'));
        } catch (err) {
            showToast(mcpT('ai_conn_desktop_revoke_failed', 'Could not revoke pairing.'), true);
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

        bindConnectorUrlCopyHandlers(document.getElementById('add-mcp-modal'));
        bindConnectorUrlCopyHandlers(document.getElementById('ai-integrations'));

        document.querySelectorAll('.mcp-preset-btn').forEach(btn => {
            btn.addEventListener('click', async function (e) {
                e.preventDefault();
                applyMcpPreset(btn.getAttribute('data-mcp-preset'));
            });
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
            loadDesktopConnector();

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
            return '<span class="badge badge-success">' + escapeHtml(mcpT('ai_mcp_type_badge_personal', 'Personal')) + '</span>';
        }
        return '<span class="badge badge-info">' + escapeHtml(mcpT('ai_mcp_type_badge_service', 'Service')) + '</span>';
    }

    function roleLabel(roleId) {
        if (roleId === 'viewer') return mcpT('ai_mcp_role_viewer', 'Viewer — read only');
        if (roleId === 'editor') return mcpT('ai_mcp_role_editor', 'Editor — suggest edits');
        if (roleId === 'admin') return mcpT('ai_mcp_role_admin', 'Admin — full access');
        return roleId;
    }

    function visibilitySummary(agent) {
        const roles = agent.visible_to_roles || [];
        const users = agent.allowed_users || [];
        const parts = [];
        if (roles.length) {
            parts.push(mcpT('ai_mcp_visibility_roles_prefix', 'Roles:') + ' ' + roles.join(', '));
        }
        if (users.length) {
            parts.push(mcpT('ai_mcp_visibility_user_count', '%n% user(s)').replace('%n%', String(users.length)));
        }
        if (!parts.length) {
            return mcpT('ai_mcp_admin_only', 'Admin only');
        }
        return parts.join(' · ');
    }

    function renderPersonalAgent(agent) {
        const card = document.getElementById('ai-personal-agent-card');
        if (!card) return;

        if (!agent) {
            card.innerHTML = `
            <div class="empty-state empty-state-friendly">
                <p><strong>${escapeHtml(mcpT('ai_mcp_personal_unavailable_title', 'Personal connector unavailable'))}</strong></p>
                <p>${escapeHtml(mcpT('ai_mcp_personal_unavailable_body', 'Refresh the page. If this persists, contact your administrator.'))}</p>
            </div>`;
            return;
        }

        const status = agent.status || 'active';
        const statusClass = status === 'active' ? 'active' : 'inactive';
        const url = resolveConnectorUrl(agent.connector_url || serverUrl);
        const agentId = agent.id || 'ai_agent_personal';
        const statusLabel = status === 'active'
            ? mcpT('ai_mcp_status_active', 'Active')
            : mcpT('ai_mcp_status_disabled', 'Disabled');
        const toggleLabel = status === 'active'
            ? mcpT('ai_mcp_btn_disable', 'Disable')
            : mcpT('ai_mcp_btn_enable', 'Enable');
        const disabledNote = status === 'disabled'
            ? `<p class="agent-disabled-note" role="alert">${escapeHtml(mcpT('ai_mcp_personal_disabled_note', 'Personal connector is disabled. Only admins can re-enable it.'))}</p>`
            : '';

        card.innerHTML = `
            <div class="ai-connection-entry" data-agent-id="${escapeHtml(agentId)}">
                <div class="ai-connection-entry-header">
                    <strong>${escapeHtml(agent.name || mcpT('ai_mcp_personal_default_name', 'Your personal connector'))}</strong>
                    <span class="mcp-endpoint-status ${statusClass}">${escapeHtml(statusLabel)}</span>
                </div>
                <p class="settings-help">${escapeHtml(agent.description || mcpT('ai_mcp_personal_default_desc', 'Connect Claude or ChatGPT with your own Birkly permissions. Every team member uses this same URL but signs in with their own account.'))}</p>
                <p class="settings-help mcp-approval-note">${escapeHtml(mcpT('ai_mcp_write_approvals_note', 'Write approvals: MCP write confirmations happen in your AI app (Cursor, Claude) — not in the Birkly Approvals tab. The built-in assistant uses a separate approval flow under Settings → Built-in assistant.'))}</p>
                <div class="ai-connection-entry-meta">
                    <span class="connection-role">${escapeHtml(mcpT('ai_mcp_role_when_connect', 'Your role when you connect'))}</span>
                </div>
                ${disabledNote}
                <div class="ai-connection-entry-setup">
                    ${renderSetupSteps(agentId, url, true)}
                </div>
                ${isAdmin ? `<div class="ai-connection-entry-actions"><button type="button" class="btn btn-sm btn-secondary" data-toggle-personal-status="${status === 'active' ? 'disabled' : 'active'}">${escapeHtml(toggleLabel)}</button></div>` : ''}
            </div>`;
    }

    function renderServiceAgents(agents) {
        const list = document.getElementById('ai-service-agents-list');
        if (!list) return;

        if (agents.length === 0) {
            const emptyTitle = isAdmin
                ? mcpT('ai_mcp_empty_service_admin_title', 'No service agents yet')
                : mcpT('ai_mcp_empty_service_user_title', 'No service agents assigned to you');
            const emptyBody = isAdmin
                ? mcpT('ai_mcp_empty_service_admin_body', 'Click Add agent to create a shared connection with a fixed role.')
                : mcpT('ai_mcp_empty_service_user_body', 'Ask an admin to assign you a service agent, or use the personal connector.');
            list.innerHTML = `
            <div class="empty-state empty-state-friendly">
                <p><strong>${escapeHtml(emptyTitle)}</strong></p>
                <p>${escapeHtml(emptyBody)}</p>
            </div>`;
            return;
        }

        list.innerHTML = agents.map(agent => {
            const status = agent.status || 'active';
            const statusClass = status === 'active' ? 'active' : 'inactive';
            const url = resolveConnectorUrl(agent.connector_url || serverUrl);
            const statusLabel = status === 'active'
                ? mcpT('ai_mcp_status_active', 'Active')
                : mcpT('ai_mcp_status_inactive', 'Inactive');
            const fixedRole = mcpT('ai_mcp_fixed_role_prefix', 'Fixed role:') + ' ' + (agent.role || 'viewer');

            return `
            <div class="ai-connection-entry" data-agent-id="${escapeHtml(agent.id)}">
                <div class="ai-connection-entry-header">
                    <strong>${escapeHtml(agent.name || agent.id)}</strong>
                    <span class="mcp-endpoint-status ${statusClass}">${escapeHtml(statusLabel)}</span>
                </div>
                ${agent.description ? `<p class="settings-help">${escapeHtml(agent.description)}</p>` : ''}
                <div class="ai-connection-entry-meta">
                    <span class="connection-role">${escapeHtml(fixedRole)}</span>
                    ${isAdmin ? `<span class="agent-visibility">${escapeHtml(visibilitySummary(agent))}</span>` : ''}
                </div>
                <div class="ai-connection-entry-setup">
                    ${renderSetupSteps(agent.id, url, false)}
                </div>
                ${isAdmin ? `<div class="ai-connection-entry-actions"><button type="button" class="btn btn-sm btn-danger" data-revoke-endpoint="${escapeHtml(agent.id)}">${escapeHtml(mcpT('ai_mcp_btn_remove', 'Remove'))}</button></div>` : ''}
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
                    this.textContent = mcpT('ai_mcp_hide_connector_url', 'Hide Connector URL');
                } else {
                    panel.setAttribute('hidden', '');
                    this.setAttribute('aria-expanded', 'false');
                    this.textContent = mcpT('ai_mcp_show_connector_url', 'Show Connector URL');
                }
            });
        });
    }

    function getBaseUrl() {
        return serverUrl.replace(/\/mcp$/, '').replace(/\/api\/mcp_server\.php$/, '') || window.location.origin;
    }

    function copyIconHtml() {
        if (typeof Birkly !== 'undefined' && Birkly.icon) {
            return Birkly.icon('copy', { size: 16 });
        }
        return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>';
    }

    function renderConnectorUrlRow(endpointId, url) {
        const textId = 'mcp-connector-url-text-' + endpointId;
        const insecureClass = connectorUrlIsUsable(url) ? '' : ' mcp-url-insecure';
        return `
        <div class="connector-url-row${insecureClass}">
            <button type="button" class="connector-url-value" data-copy-source="${escapeHtml(textId)}" title="${escapeHtml(mcpT('ai_mcp_click_copy', 'Click to copy'))}">
                <span id="${escapeHtml(textId)}" class="connector-url-text">${escapeHtml(url)}</span>
            </button>
            <button type="button" class="btn-icon connector-url-copy-icon" data-copy-source="${escapeHtml(textId)}" aria-label="${escapeHtml(mcpT('ai_mcp_copy_url_aria', 'Copy connector URL'))}">
                ${copyIconHtml()}
            </button>
        </div>`;
    }

    function bindConnectorUrlCopyHandlers(root) {
        if (!root || root.dataset.connectorUrlCopyBound === '1') {
            return;
        }
        root.dataset.connectorUrlCopyBound = '1';
        root.addEventListener('click', function (e) {
            const trigger = e.target.closest('[data-copy-source]');
            if (!trigger || !root.contains(trigger)) {
                return;
            }
            e.preventDefault();
            const sourceId = trigger.getAttribute('data-copy-source');
            const source = sourceId ? document.getElementById(sourceId) : null;
            if (!source) {
                return;
            }
            copyConnectorUrlText((source.textContent || '').trim(), true);
        });
    }

    function renderSetupSteps(endpointId, connectorUrlOverride, isPersonal) {
        const url = connectorUrlOverride || resolveConnectorUrl(serverUrl);
        const httpWarning = connectorUrlIsUsable(url) ? '' : `
        <p class="mcp-http-url-warning" role="alert">
            ${escapeHtml(mcpT('ai_mcp_http_url_warning', 'This URL uses HTTP. Claude will reject it — open Birkly with https:// and copy the URL again.'))}
        </p>`;
        const personalNote = isPersonal
            ? mcpT('ai_mcp_setup_personal_note', 'Sign in with your Birkly account — permissions follow your role.')
            : mcpT('ai_mcp_setup_service_note', 'This service agent uses a fixed role set by your admin.');

        // Kept intentionally brief: exact menu paths in Claude/ChatGPT/Cursor
        // change often, so the canonical steps live in the linked guide
        // instead of going stale here (P56-R2 #7).
        return `
        <ol class="endpoint-setup-steps">
            <li>${escapeHtml(mcpT('ai_mcp_setup_step1', 'Copy the Connector URL below and add it as a remote MCP server in your AI app — leave Client ID/Secret empty (OAuth is automatic).'))}</li>
            <li>${escapeHtml(personalNote)}</li>
            <li><a href="${escapeHtml(DOCS_HELP_URL)}">${escapeHtml(mcpT('ai_mcp_setup_guide', 'Full setup guide'))}</a></li>
        </ol>
        ${httpWarning}
        <div class="mcp-snippet-block">
            <span class="u-label u-text-sm">${escapeHtml(mcpT('ai_mcp_connector_url_label', 'Connector URL'))}</span>
            ${renderConnectorUrlRow(endpointId, url)}
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

        const modalUrl = document.getElementById('mcp-modal-personal-url-text');
        const modalRow = document.getElementById('mcp-modal-personal-url-row');
        if (modalUrl) {
            modalUrl.textContent = personalAgentUrl || mcpT('ai_mcp_loading', 'Loading…');
        }
        if (modalRow) {
            modalRow.classList.toggle('mcp-url-insecure', personalAgentUrl && !connectorUrlIsUsable(personalAgentUrl));
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
            toggle.textContent = mcpT('ai_mcp_hide_connector_url', 'Hide Connector URL');
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
            submitBtn.textContent = mcpT('ai_mcp_create_service', 'Create service agent');
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
            if (nameInput) nameInput.value = mcpT('ai_mcp_preset_site_builder_name', 'Site builder');
            if (descInput) descInput.value = mcpT('ai_mcp_preset_site_builder_desc', 'Build and edit project/ HTML, CSS, and JS with CMS content tools');
            if (roleSelect) roleSelect.value = 'admin';
            return;
        }

        if (preset === 'content') {
            if (nameInput) nameInput.value = mcpT('ai_mcp_preset_content', 'Content assistant');
            if (descInput) descInput.value = mcpT('ai_mcp_preset_content_desc', 'Draft and edit CMS collections and entries');
            if (roleSelect) roleSelect.value = 'editor';
        }
    }

    async function loadRoles(selectedRole = null) {
        const select = document.getElementById('mcp-endpoint-role');
        if (!select) return;

        const legacyRoles = [
            { id: 'viewer', name: roleLabel('viewer') },
            { id: 'editor', name: roleLabel('editor') },
            { id: 'admin', name: roleLabel('admin') }
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
                if (roleId === 'viewer' || roleId === 'editor' || roleId === 'admin') {
                    label = roleLabel(roleId);
                }
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

        rolesContainer.innerHTML = '<div class="loading-inline">' + escapeHtml(mcpT('ai_conn_loading_roles', 'Loading roles…')) + '</div>';
        usersContainer.innerHTML = '<div class="loading-inline">' + escapeHtml(mcpT('ai_mcp_loading_users', 'Loading users…')) + '</div>';

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
                usersContainer.innerHTML = '<p class="u-fg-secondary">' + escapeHtml(mcpT('ai_mcp_no_users', 'No users available.')) + '</p>';
                return;
            }

            usersContainer.innerHTML = users.map(user => {
                const label = user.name || user.email || user.id;
                return `<label class="mcp-checkbox-label"><input type="checkbox" name="allowed_users" value="${escapeHtml(user.id)}"> ${escapeHtml(label)} <span class="u-fg-secondary">(${escapeHtml(user.role || '')})</span></label>`;
            }).join('');
        } catch (error) {
            usersContainer.innerHTML = '<p class="u-fg-secondary">' + escapeHtml(mcpT('ai_mcp_users_load_failed', 'Could not load users.')) + '</p>';
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
                if (submitBtn) submitBtn.textContent = mcpT('ai_mcp_done_close', 'Done — close when ready');
                await loadEndpoints();
                const successPanel = document.getElementById('mcp-create-success');
                const urlText = document.getElementById('mcp-new-connector-url-text');
                const urlRow = document.getElementById('mcp-new-connector-url-row');
                if (successPanel && urlText) {
                    const resolvedUrl = resolveConnectorUrl(agentUrl);
                    urlText.textContent = resolvedUrl;
                    if (urlRow) {
                        urlRow.classList.toggle('mcp-url-insecure', !connectorUrlIsUsable(resolvedUrl));
                    }
                    successPanel.hidden = false;
                }
                if (agentId) {
                    setTimeout(() => {
                        const panel = document.getElementById('mcp-setup-panel-' + agentId);
                        const toggle = document.querySelector(`.mcp-toggle-setup[data-endpoint-id="${agentId}"]`);
                        if (panel && toggle) {
                            panel.removeAttribute('hidden');
                            toggle.setAttribute('aria-expanded', 'true');
                            toggle.textContent = mcpT('ai_mcp_hide_connector_url', 'Hide Connector URL');
                            panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        }
                    }, 300);
                }
                showToast(mcpT('ai_mcp_service_toast_created', 'Service agent created! Copy the Connector URL into your AI app.'));
            } else {
                showToast(result?.message || mcpT('ai_mcp_toast_create_failed', 'Could not create connection'), true);
                if (submitBtn) submitBtn.disabled = false;
            }
        } catch (error) {
            console.error('Error creating connection:', error);
            showToast(mcpT('ai_mcp_toast_generic_error', 'Something went wrong. Please try again.'), true);
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
                showToast(newStatus === 'active'
                    ? mcpT('ai_mcp_personal_toast_enabled', 'Personal connector enabled.')
                    : mcpT('ai_mcp_personal_toast_disabled', 'Personal connector disabled.'));
                loadEndpoints();
            } else {
                showToast(result.message || mcpT('ai_mcp_toast_update_personal_failed', 'Could not update personal connector'), true);
            }
        } catch (error) {
            showToast(mcpT('ai_mcp_toast_generic_error', 'Something went wrong. Please try again.'), true);
        }
    }

    async function refreshCsrfToken() {
        csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || csrfToken;
        return csrfToken;
    }

    async function revokeAgent(agentId) {
        if (!agentId || !isAdmin) return;
        if (!(await BirklyFeedback.confirm(mcpT('ai_mcp_revoke_confirm', 'Remove this service agent? Your AI app will no longer access your site through it.')))) {
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
                showToast(mcpT('ai_mcp_toast_invalid_response', 'Server returned an invalid response. Try again.'), true);
                return;
            }

            if (result.status === 'ok') {
                showToast(mcpT('ai_mcp_service_toast_removed', 'Service agent removed.'));
                loadEndpoints();
            } else {
                showToast(result.message || mcpT('ai_mcp_toast_remove_failed', 'Could not remove agent'), true);
            }
        } catch (error) {
            showToast(mcpT('ai_mcp_toast_generic_error', 'Something went wrong. Please try again.'), true);
        }
    }

    function copyConnectorUrlText(text, useToast) {
        if (!text) {
            return;
        }

        const isHttp = text.startsWith('http://');
        const done = () => {
            if (!useToast) {
                return;
            }
            if (isHttp) {
                showToast(mcpT('ai_mcp_toast_copied_https', 'Copied. Claude requires HTTPS for remote connectors.'));
            } else {
                showToast(mcpT('ai_mcp_toast_copied', 'Copied to clipboard!'));
            }
        };

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done).catch(() => fallbackCopyText(text, done));
        } else {
            fallbackCopyText(text, done);
        }
    }

    function copySnippet(elementId, useToast) {
        const el = document.getElementById(elementId);
        if (!el) return;

        const text = (el.value || el.textContent || '').trim();
        copyConnectorUrlText(text, useToast);
    }

    function fallbackCopyText(text, done) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'fixed';
        textarea.style.left = '-9999px';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
            if (done) done();
        } catch (err) {
            showToast(mcpT('ai_mcp_copy_failed', 'Could not copy — please select the text manually.'), true);
        }
        document.body.removeChild(textarea);
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
            showToast(mcpT('ai_mcp_copy_failed', 'Could not copy — please select the text manually.'), true);
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

    // Explicit mount entry point (P60-M4) so surfaces other than Settings → AI —
    // e.g. the Settings → AI zone — can initialize the same module.
    // `initMcpConnections` guards against double-init, so this is safe to call
    // repeatedly. The two surfaces are separate pages, so the shared DOM ids
    // never collide at runtime.
    window.McpConnections = {
        init: initMcpConnections,
        copySnippet: copySnippet
    };
})();
