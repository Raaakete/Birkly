/**
 * Floating AI Chat Button
 * Global AI chat interface accessible from anywhere in the admin panel
 */

(function() {
    'use strict';

    let currentConnection = null;
    let conversationId = null;
    let csrfToken = '';
    let availableConnections = [];
    let chatMessages = [];
    let pendingApprovals = [];
    let chatView = 'messages';
    let activeChatAbortController = null;
    let lastFailedMessage = null;
    let isChatRequestInFlight = false;
    let consoleLogs = [];
    let consoleUnread = 0;
    let assistFabState = 'idle';
    let sidePanelView = null;
    let chatModelOverride = '';
    let pendingAttachments = [];
    let chatModelOptions = [];
    let chatModelSearchQuery = '';
    let modelMenuAnchor = null;
    let modelMenuOutsideHandler = null;
    let chatTooltipEl = null;
    let chatTooltipTarget = null;
    let chatMessageSeq = 0;
    const MAX_CHAT_ATTACHMENTS = 6;
    const MAX_IMAGE_BYTES = 4 * 1024 * 1024;
    const DOCUMENT_EXTENSIONS = ['.txt', '.md', '.json', '.csv', '.html', '.xml', '.yaml', '.yml', '.log', '.js', '.ts', '.css', '.php'];

    function nextChatMessageId() {
        chatMessageSeq += 1;
        return 'msg_' + Date.now() + '_' + chatMessageSeq;
    }

    function setAssistFabState(state) {
        const wrap = document.getElementById('assist-fab-wrap');
        if (!wrap) return;
        assistFabState = state;
        wrap.classList.remove('fab-idle', 'fab-menu', 'fab-chat');
        wrap.classList.add('fab-' + state);

        const button = document.getElementById('ai-chat-floating-btn');
        if (!button) return;

        if (state === 'idle') {
            button.setAttribute('aria-expanded', 'false');
            button.setAttribute('aria-label', 'Open assist menu');
            button.title = 'Assist';
        } else if (state === 'menu') {
            button.setAttribute('aria-expanded', 'true');
            button.setAttribute('aria-label', 'Open AI Assistant');
            button.title = 'AI Assistant';
        } else if (state === 'chat') {
            button.setAttribute('aria-expanded', 'false');
            button.setAttribute('aria-label', 'Close AI Assistant');
            button.title = 'Close';
        }
    }

    function icon(name, opts) {
        return (window.Birkly && typeof Birkly.icon === 'function') ? Birkly.icon(name, opts) : '';
    }

    function apiUrl(path) {
        return (window.Birkly && typeof Birkly.url === 'function') ? Birkly.url(path) : path;
    }

    async function refreshCsrfToken() {
        try {
            const response = await fetch(apiUrl('/api/csrf.php'), { credentials: 'same-origin' });
            const data = await response.json();
            if (data.status === 'ok' && data.data && data.data.csrf_token) {
                csrfToken = data.data.csrf_token;
                const meta = document.querySelector('meta[name="csrf-token"]');
                if (meta) meta.content = csrfToken;
                return true;
            }
        } catch (error) {
            console.error('Failed to refresh CSRF token:', error);
        }
        return false;
    }

    function formatHttpError(status, bodyText) {
        let message = bodyText || 'Server error';
        try {
            const parsed = JSON.parse(bodyText);
            if (parsed && parsed.message) {
                message = parsed.message;
            }
        } catch (e) {
            // keep raw body
        }
        if (status === 401) {
            return 'Your session expired. Please refresh the page and log in again.';
        }
        if (status === 504) {
            return 'The request timed out (504). Large site builds can take a minute — try again, use a faster model, or ask for fewer files at once (e.g. "write index.html first").';
        }
        return 'HTTP Error ' + status + ': ' + message;
    }

    function normalizeProviderError(message) {
        let text = String(message || '');
        if (currentConnection && currentConnection.provider === 'openrouter') {
            text = text.replace(/OpenAI API error/gi, 'OpenRouter API error');
        }
        return text;
    }

    function formatChatErrorDetails(result) {
        const details = result?.error_details || result?.data?.error_details || null;
        let message = normalizeProviderError(
            result?.message || result?.error || result?.data?.message || 'Unknown error'
        );
        if (!details || typeof details !== 'object') {
            return message;
        }

        const lines = [message];
        if (details.provider_label || details.provider) {
            lines.push(
                'Provider: ' + (details.provider_label || details.provider)
                + (details.model ? ' · Model: ' + details.model : '')
                + (details.http_code ? ' · HTTP ' + details.http_code : '')
            );
        }
        if (details.provider_message && !message.includes(details.provider_message)) {
            lines.push('Details: ' + details.provider_message);
        }
        if (details.hint) {
            lines.push('Hint: ' + details.hint);
        }
        return lines.join('\n\n');
    }

    function buildConsoleErrorDetails(result) {
        const details = result?.error_details || result?.data?.error_details;
        if (details && typeof details === 'object') {
            return details;
        }
        if (result && typeof result === 'object') {
            return result;
        }
        return null;
    }

    function describeActionConsoleResult(actionResult, index) {
        const type = actionResult.action_type || 'action';
        const data = actionResult.action_data || actionResult.data || {};
        let target = '';
        if (type === 'write_project_file' || type === 'create_project_file') {
            target = data.path ? ' → project/' + data.path : '';
        } else if (type === 'create_entry') {
            target = data.collection ? ' → ' + data.collection : '';
            if (data.title) target += ' ("' + data.title + '")';
        } else if (type === 'update_collection') {
            target = data.slug ? ' → ' + data.slug : '';
        }
        const status = actionResult.success ? 'succeeded' : 'failed';
        const err = !actionResult.success && actionResult.message ? ' — ' + actionResult.message : '';
        return type + target + ' ' + status + err;
    }

    function getSavedChatModels() {
        try {
            const raw = localStorage.getItem(STORAGE_KEY_MODELS);
            return raw ? JSON.parse(raw) : {};
        } catch (e) {
            return {};
        }
    }

    function getSavedChatModel(connectionId) {
        if (!connectionId) return '';
        const map = getSavedChatModels();
        return map[connectionId] || '';
    }

    function saveChatModelForConnection(connectionId, modelId) {
        if (!connectionId) return;
        const map = getSavedChatModels();
        if (modelId) {
            map[connectionId] = modelId;
        } else {
            delete map[connectionId];
        }
        localStorage.setItem(STORAGE_KEY_MODELS, JSON.stringify(map));
    }

    function restoreChatModelForConnection(connectionId) {
        chatModelOverride = getSavedChatModel(connectionId);
        updateModelLabel();
    }

    function responseHasExecutionReport(text) {
        const value = String(text || '');
        return value.indexOf('### Completed this step') !== -1
            || value.indexOf('### Plan status') !== -1
            || value.indexOf('### Planned this step') !== -1;
    }

    function sanitizeChatDisplayText(text) {
        let message = String(text || '');
        if (!message) return '';

        message = message.replace(/```(?:json)?\s*[\s\S]*?```/gi, '');
        message = message.replace(/<tool_call\b[^>]*>[\s\S]*?<\/tool_call>/gi, '');
        message = message.replace(/<tool_call\b[^>]*>[\s\S]*/gi, '');
        message = message.replace(/\n*\[Session notes[^\]]*\]\s*/g, '');

        const needles = ['{"actions"', '{ "actions"', '{"type":"write_project_file"', '{ "type": "write_project_file"'];
        for (const needle of needles) {
            let searchFrom = 0;
            while (true) {
                const pos = message.toLowerCase().indexOf(needle.toLowerCase(), searchFrom);
                if (pos === -1) break;
                const end = findJsonObjectEnd(message, pos);
                if (end === null) break;
                message = message.slice(0, pos) + message.slice(end);
                searchFrom = pos;
            }
        }

        return message.replace(/\n{3,}/g, '\n\n').trim();
    }

    function findJsonObjectEnd(text, start) {
        let depth = 0;
        let inString = false;
        let escape = false;
        for (let i = start; i < text.length; i++) {
            const c = text[i];
            if (inString) {
                if (escape) { escape = false; continue; }
                if (c === '\\') { escape = true; continue; }
                if (c === '"') inString = false;
                continue;
            }
            if (c === '"') { inString = true; continue; }
            if (c === '{') { depth++; continue; }
            if (c === '}') {
                depth--;
                if (depth === 0) return i + 1;
            }
        }
        return null;
    }

    // Storage keys
    const STORAGE_KEY_CONVERSATION = 'ai_chat_conversation_id';
    const STORAGE_KEY_CONNECTION = 'ai_chat_connection_id';
    const STORAGE_KEY_MESSAGES = 'ai_chat_messages';
    const STORAGE_KEY_RECENT = 'ai_chat_recent_chats';
    const STORAGE_KEY_CONSOLE = 'ai_chat_console_logs';
    const STORAGE_KEY_WINDOW_OPEN = 'ai_chat_window_open';
    const STORAGE_KEY_MODELS = 'ai_chat_model_by_connection';
    const CONSOLE_LOG_LIMIT = 200;

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        loadConsoleLogs();
        loadConnections().then(() => {
            restoreChatState().then(() => restoreChatWindowOpen());
        });
        createFloatingButton();
        loadApprovalQueue();
        setInterval(loadApprovalQueue, 30000);
        injectQuickActionChips();

        window.addEventListener('birkly:ai-connection-changed', function(event) {
            const detail = event.detail || {};
            loadConnections().then(() => {
                if (detail.action === 'delete') {
                    validateCurrentConnection();
                    renderConnectionDropdown();
                    return;
                }

                if (detail.action === 'create' && detail.id) {
                    const created = availableConnections.find(c => c.id === detail.id)
                        || detail.connection;
                    if (created && (!currentConnection || detail.id === created.id)) {
                        currentConnection = created;
                        conversationId = conversationId || ('conv_' + Date.now());
                        saveChatState();
                    }
                    validateCurrentConnection();
                    renderConnectionDropdown();
                    updateConnectionDropdownValue();

                    const chatWindow = document.getElementById('ai-chat-window');
                    if (chatWindow && chatWindow.style.display !== 'none' && currentConnection) {
                        selectConnection(currentConnection.id);
                    }
                    return;
                }

                validateCurrentConnection();
                renderConnectionDropdown();
            });
        });
    });

    // Load available AI connections
    async function loadConnections() {
        try {
            const response = await fetch(apiUrl('/api/ai_connections.php?action=list'), { credentials: 'same-origin' });
            const result = await response.json();
            
            if (result && result.status === 'ok' && result.data) {
                availableConnections = (result.data.connections || []).filter(conn => conn.provider !== 'mcp');
            } else {
                availableConnections = [];
            }
            return Promise.resolve();
        } catch (error) {
            console.error('Error loading AI connections:', error);
            availableConnections = [];
            return Promise.reject(error);
        }
    }

    // Create unified assist FAB with speed-dial (AI chat + feedback)
    function createFloatingButton() {
        document.body.classList.add('assist-fab-active');

        const wrap = document.createElement('div');
        wrap.id = 'assist-fab-wrap';
        wrap.className = 'assist-fab-wrap fab-idle';

        const menu = document.createElement('div');
        menu.className = 'assist-fab-menu';
        menu.setAttribute('role', 'menu');
        menu.innerHTML = `
            <button type="button" class="btn assist-fab-action" id="assist-fab-feedback" role="menuitem" title="Send feedback" aria-label="Send feedback">
                ${icon('paper-plane-tilt', { size: 20 })}
                <span>Feedback</span>
            </button>
        `;

        const button = document.createElement('button');
        button.id = 'ai-chat-floating-btn';
        button.className = 'ai-chat-floating-btn assist-fab-main';
        button.type = 'button';
        button.setAttribute('aria-label', 'Open assist menu');
        button.setAttribute('aria-expanded', 'false');
        button.setAttribute('aria-haspopup', 'menu');
        button.title = 'Assist';
        button.innerHTML = `
            <span class="assist-fab-visual" aria-hidden="true">
                <span class="assist-fab-icon-slot assist-fab-icon-plus">${icon('plus', { size: 22 })}</span>
                <span class="assist-fab-icon-slot assist-fab-icon-sparkle">${icon('sparkle', { size: 22 })}</span>
                <span class="assist-fab-icon-slot assist-fab-icon-close">${icon('x', { size: 22 })}</span>
            </span>
            <span class="assist-fab-label">AI Assistant</span>
        `;

        const badge = document.createElement('span');
        badge.id = 'ai-chat-approval-badge';
        badge.className = 'ai-chat-fab-badge';
        badge.hidden = true;
        badge.setAttribute('aria-hidden', 'true');
        button.appendChild(badge);

        wrap.appendChild(menu);
        wrap.appendChild(button);
        document.body.appendChild(wrap);

        button.addEventListener('click', async function (e) {
            e.stopPropagation();
            if (assistFabState === 'chat') {
                hideChatWindow();
                setAssistFabState('idle');
                return;
            }
            if (assistFabState === 'menu') {
                await openAiChatFromFab();
                return;
            }
            setAssistFabState('menu');
        });

        document.getElementById('assist-fab-feedback').addEventListener('click', async function (e) {
            e.stopPropagation();
            setAssistFabState('idle');
            if (window.Birkly && typeof window.Birkly.openFeedbackModal === 'function') {
                window.Birkly.openFeedbackModal();
            } else {
                document.getElementById('feedback-floating-btn')?.click();
            }
        });

        document.addEventListener('click', function (e) {
            if (assistFabState === 'menu' && !wrap.contains(e.target)) {
                setAssistFabState('idle');
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && assistFabState === 'menu') {
                setAssistFabState('idle');
            }
        });
    }

    function updateApprovalBadge() {
        const badge = document.getElementById('ai-chat-approval-badge');
        const button = document.getElementById('ai-chat-floating-btn');
        const count = pendingApprovals.length;
        if (!badge) return;
        if (count > 0) {
            badge.textContent = count > 9 ? '9+' : String(count);
            badge.hidden = false;
            badge.setAttribute('aria-hidden', 'false');
            if (button && assistFabState === 'chat') {
                button.setAttribute('aria-label', `Close AI Assistant, ${count} pending approval${count === 1 ? '' : 's'}`);
            } else if (button && assistFabState === 'menu') {
                button.setAttribute('aria-label', `Open AI Assistant, ${count} pending approval${count === 1 ? '' : 's'}`);
            }
        } else {
            badge.textContent = '';
            badge.hidden = true;
            badge.setAttribute('aria-hidden', 'true');
            if (button) {
                if (assistFabState === 'chat') {
                    button.setAttribute('aria-label', 'Close AI Assistant');
                } else if (assistFabState === 'menu') {
                    button.setAttribute('aria-label', 'Open AI Assistant');
                } else {
                    button.setAttribute('aria-label', 'Open assist menu');
                }
            }
        }
        const tabBadge = document.getElementById('ai-chat-approvals-header-badge');
        if (tabBadge) {
            tabBadge.textContent = count > 0 ? (count > 9 ? '9+' : String(count)) : '';
            tabBadge.hidden = count === 0;
        }
    }

    function updateApprovalsHeaderButton() {
        const btn = document.getElementById('ai-chat-approvals-btn');
        if (!btn || !currentConnection) return;
        const show = currentConnection.require_approval === true || currentConnection.require_approval === '1';
        btn.hidden = !show;
    }

    async function loadApprovalQueue() {
        try {
            const response = await fetch(apiUrl('/api/ai_approvals.php?action=list&status=pending'), { credentials: 'same-origin' });
            const result = await response.json();
            pendingApprovals = (result && result.status === 'ok' && result.data)
                ? (result.data.approvals || [])
                : [];
            updateApprovalBadge();
            renderApprovalQueuePanel();
        } catch (error) {
            console.error('Error loading approval queue:', error);
        }
    }

    function renderApprovalQueuePanel() {
        const panel = document.getElementById('ai-chat-approvals-panel');
        if (!panel) return;

        if (pendingApprovals.length === 0) {
            panel.innerHTML = '<div class="ai-chat-welcome"><p>No pending approvals. When AI wants to change content, requests will appear here.</p></div>';
            return;
        }

        panel.innerHTML = pendingApprovals.map(approval => {
            const actionType = (approval.action_type || 'unknown').replace(/_/g, ' ');
            return `
            <div class="card ai-chat-approval-card ai-chat-queue-item" id="queue-approval-${escapeHtml(approval.id)}">
                <div class="ai-chat-approval-header u-split">
                    <div class="ai-chat-approval-type">${escapeHtml(actionType)}</div>
                    <span class="ai-chat-approval-time u-text-xs u-fg-tertiary">${formatTime(approval.created_at)}</span>
                </div>
                <div class="ai-chat-approval-description">${escapeHtml(approval.description || 'No description')}</div>
                <div class="ai-chat-approval-actions u-row-2 u-mt-3">
                    <button type="button" class="btn btn-sm btn-success" data-ai-approve="${escapeHtml(approval.id)}">Approve</button>
                    <button type="button" class="btn btn-sm btn-danger" data-ai-reject="${escapeHtml(approval.id)}">Reject</button>
                </div>
            </div>`;
        }).join('');
    }

    function appendConsoleLog(level, message, details) {
        consoleLogs.unshift({
            id: 'log_' + Date.now() + '_' + Math.random().toString(36).slice(2, 7),
            level: level || 'info',
            message: String(message || ''),
            details: details !== undefined && details !== null ? details : null,
            time: new Date().toISOString(),
        });
        if (consoleLogs.length > CONSOLE_LOG_LIMIT) {
            consoleLogs.length = CONSOLE_LOG_LIMIT;
        }
        if ((level === 'error' || level === 'warn') && sidePanelView !== 'console') {
            consoleUnread++;
        }
        saveConsoleLogs();
        updateConsoleBadge();
        if (sidePanelView === 'console') {
            renderConsolePanel();
        }
        if (level === 'error' || level === 'warn') {
            console.error('[AI Chat]', message, details || '');
        }
    }

    function saveConsoleLogs() {
        try {
            localStorage.setItem(STORAGE_KEY_CONSOLE, JSON.stringify(consoleLogs));
        } catch (error) {
            /* ignore quota errors */
        }
    }

    function loadConsoleLogs() {
        try {
            const raw = localStorage.getItem(STORAGE_KEY_CONSOLE);
            if (!raw) {
                consoleLogs = [];
                return;
            }
            const parsed = JSON.parse(raw);
            consoleLogs = Array.isArray(parsed) ? parsed.slice(0, CONSOLE_LOG_LIMIT) : [];
        } catch (error) {
            consoleLogs = [];
        }
    }

    function clearConsoleLogs() {
        consoleLogs = [];
        consoleUnread = 0;
        saveConsoleLogs();
        updateConsoleBadge();
        renderConsolePanel();
    }

    function updateConsoleBadge() {
        const badge = document.getElementById('ai-chat-console-header-badge');
        if (!badge) return;
        if (consoleUnread > 0) {
            badge.textContent = consoleUnread > 99 ? '99+' : String(consoleUnread);
            badge.hidden = false;
        } else {
            badge.hidden = true;
        }
    }

    function formatConsoleDetails(details) {
        if (details === null || details === undefined) return '';
        if (typeof details === 'string') return details;
        try {
            return JSON.stringify(details, null, 2);
        } catch (error) {
            return String(details);
        }
    }

    function renderConsolePanel() {
        const list = document.getElementById('ai-chat-console-list');
        if (!list) return;

        if (!consoleLogs.length) {
            list.innerHTML = '<div class="ai-chat-console-empty">No log entries yet. Failed actions and errors appear here.</div>';
            return;
        }

        list.innerHTML = consoleLogs.map(function (entry) {
            const level = entry.level || 'info';
            const levelLabel = level.charAt(0).toUpperCase() + level.slice(1);
            const detailsText = formatConsoleDetails(entry.details);
            const detailsHtml = detailsText
                ? '<pre class="ai-chat-console-details">' + escapeHtml(detailsText) + '</pre>'
                : '';
            const messageHtml = (entry.message || '').split('\n').map(function (line) {
                return escapeHtml(line);
            }).join('<br>');
            return `
                <div class="card card--compact ai-chat-console-entry ai-chat-console-entry--${escapeHtml(level)}">
                    <div class="ai-chat-console-entry-head u-split u-gap-2 u-mb-1">
                        <span class="ai-chat-console-level">${escapeHtml(levelLabel)}</span>
                        <time class="ai-chat-console-time u-text-2xs u-fg-tertiary u-numeric u-nowrap">${escapeHtml(formatTime(entry.time))}</time>
                    </div>
                    <div class="ai-chat-console-message">${messageHtml}</div>
                    ${detailsHtml}
                </div>`;
        }).join('');
    }

    function toggleSidePanel(view) {
        if (sidePanelView === view) {
            closeSidePanel();
            return;
        }
        openSidePanel(view);
    }

    function openSidePanel(view) {
        hideChatTooltip();
        closeModelMenu();
        const overlay = document.getElementById('ai-chat-overlay');
        const titleEl = document.getElementById('ai-chat-side-panel-title');
        const historyPanel = document.getElementById('ai-chat-history-panel');
        const consolePanel = document.getElementById('ai-chat-console-panel');
        const approvalsPanel = document.getElementById('ai-chat-approvals-panel');
        const buttons = {
            history: document.getElementById('ai-chat-history-btn'),
            console: document.getElementById('ai-chat-console-btn'),
            approvals: document.getElementById('ai-chat-approvals-btn'),
        };

        sidePanelView = view;
        chatView = view;
        if (overlay) {
            overlay.classList.add('is-open');
            overlay.setAttribute('aria-hidden', 'false');
        }
        if (historyPanel) historyPanel.hidden = view !== 'history';
        if (consolePanel) consolePanel.hidden = view !== 'console';
        if (approvalsPanel) approvalsPanel.hidden = view !== 'approvals';

        const titles = { history: 'History', console: 'Console', approvals: 'Approvals' };
        if (titleEl) titleEl.textContent = titles[view] || 'Panel';

        Object.keys(buttons).forEach(function (key) {
            const btn = buttons[key];
            if (!btn) return;
            const active = key === view;
            btn.classList.toggle('active', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });

        if (view === 'approvals') renderApprovalQueuePanel();
        if (view === 'history') renderHistoryPanel();
        if (view === 'console') {
            consoleUnread = 0;
            updateConsoleBadge();
            renderConsolePanel();
        }
    }

    function closeSidePanel() {
        hideChatTooltip();
        sidePanelView = null;
        chatView = 'messages';
        const overlay = document.getElementById('ai-chat-overlay');
        if (overlay) {
            overlay.classList.remove('is-open');
            overlay.setAttribute('aria-hidden', 'true');
        }
        ['ai-chat-history-btn', 'ai-chat-console-btn', 'ai-chat-approvals-btn'].forEach(function (id) {
            const btn = document.getElementById(id);
            if (!btn) return;
            btn.classList.remove('active');
            btn.setAttribute('aria-pressed', 'false');
        });
    }

    function switchChatView(view) {
        if (view === 'messages') {
            closeSidePanel();
            return;
        }
        openSidePanel(view);
    }

    function bindChatWindowActions(chatWindow) {
        chatWindow.addEventListener('click', function (e) {
            const approveBtn = e.target.closest('[data-ai-approve]');
            if (approveBtn) {
                e.preventDefault();
                approveAction(approveBtn.getAttribute('data-ai-approve'));
                return;
            }
            const rejectBtn = e.target.closest('[data-ai-reject]');
            if (rejectBtn) {
                e.preventDefault();
                rejectAction(rejectBtn.getAttribute('data-ai-reject'));
            }
        });
    }

    async function openAiChatFromFab() {
        if (!document.getElementById('ai-chat-window')) {
            createChatWindow();
        }
        await showChatWindow();
    }

    // Toggle chat window (header close uses hideChatWindow only)
    function toggleChatWindow() {
        const chatWindow = document.getElementById('ai-chat-window');
        
        if (chatWindow) {
            if (chatWindow.style.display === 'none' || !chatWindow.style.display) {
                showChatWindow();
            } else {
                hideChatWindow();
            }
        } else {
            openAiChatFromFab();
        }
    }

    // Create chat window
    function createChatWindow() {
        if (document.getElementById('ai-chat-window')) {
            return;
        }

        const chatWindow = document.createElement('div');
        chatWindow.id = 'ai-chat-window';
        chatWindow.className = 'ai-chat-window';
        chatWindow.innerHTML = `
            <div class="ai-chat-header">
                <div class="ai-chat-header-brand u-row-1 u-fill">
                    <span class="ai-chat-header-prefix">AI:</span>
                    <label for="ai-chat-connection-select" class="u-sr-only">AI connection</label>
                    <select id="ai-chat-connection-select" class="ai-chat-connection-inline" title="Choose AI connection">
                        <option value="">Loading connections...</option>
                    </select>
                </div>
                <div class="ai-chat-header-actions u-row-1 u-shrink-0">
                    <button type="button" class="btn-icon ai-chat-header-icon-btn" id="ai-chat-history-btn" data-tooltip="History" aria-label="History" aria-pressed="false">
                        ${icon('clock', { size: 18 })}
                    </button>
                    <button type="button" class="btn-icon ai-chat-header-icon-btn" id="ai-chat-console-btn" data-tooltip="Console" aria-label="Console" aria-pressed="false">
                        ${icon('code', { size: 18 })}
                        <span class="ai-chat-header-badge" id="ai-chat-console-header-badge" hidden></span>
                    </button>
                    <button type="button" class="btn-icon ai-chat-header-icon-btn" id="ai-chat-approvals-btn" data-tooltip="Approvals" aria-label="Approvals" aria-pressed="false" hidden>
                        ${icon('list-checks', { size: 18 })}
                        <span class="ai-chat-header-badge" id="ai-chat-approvals-header-badge" hidden></span>
                    </button>
                    <button type="button" class="btn-icon ai-chat-header-icon-btn" id="ai-chat-new-btn" data-tooltip="New chat" aria-label="New chat">
                        ${icon('plus', { size: 18 })}
                    </button>
                    <button type="button" class="btn-icon ai-chat-header-icon-btn" id="ai-chat-fullscreen-btn" data-tooltip="Fullscreen" aria-label="Toggle fullscreen">
                        ${icon('arrows-out', { size: 18 })}
                    </button>
                </div>
            </div>
            <div class="ai-chat-body">
                <div class="ai-chat-main">
                    <div class="ai-chat-messages-panel" id="ai-chat-messages-panel">
                        <div class="ai-chat-messages" id="ai-chat-messages">
                            <div class="ai-chat-welcome">
                                <p>Select an AI connection to start chatting. The AI can help you create and edit content.</p>
                            </div>
                        </div>
                    </div>
                    <div class="ai-chat-overlay" id="ai-chat-overlay" aria-hidden="true">
                        <button type="button" class="ai-chat-overlay-backdrop" id="ai-chat-overlay-backdrop" aria-label="Close panel"></button>
                        <div class="ai-chat-side-panel" id="ai-chat-side-panel" role="dialog" aria-modal="true">
                            <div class="ai-chat-side-panel-head u-split">
                                <span id="ai-chat-side-panel-title">History</span>
                                <button type="button" class="btn-icon ai-chat-header-icon-btn" id="ai-chat-side-panel-back" data-tooltip="Back to chat" aria-label="Back to chat">
                                    ${icon('x', { size: 16 })}
                                </button>
                            </div>
                            <div class="ai-chat-history-panel ai-chat-side-panel-body" id="ai-chat-history-panel" hidden>
                                <div class="ai-chat-history-list u-stack-2" id="ai-chat-history-list"></div>
                            </div>
                            <div class="ai-chat-console-panel ai-chat-side-panel-body" id="ai-chat-console-panel" hidden>
                                <div class="ai-chat-console-toolbar u-split u-gap-2 u-shrink-0">
                                    <span class="ai-chat-console-hint u-text-xs u-fg-secondary">Action results and errors from this session</span>
                                    <button type="button" class="btn btn-sm btn-secondary" id="ai-chat-console-clear">Clear</button>
                                </div>
                                <div class="ai-chat-console-list u-stack-2" id="ai-chat-console-list"></div>
                            </div>
                            <div class="ai-chat-approvals-panel ai-chat-side-panel-body" id="ai-chat-approvals-panel" hidden>
                                <div class="loading-state">Loading approvals…</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="ai-chat-activity" id="ai-chat-activity" hidden aria-live="polite">
                    <div class="ai-chat-activity-inner u-row-3 u-align-start">
                        <span class="ai-chat-activity-icon spinner" aria-hidden="true"></span>
                        <div class="ai-chat-activity-copy u-stack-1 u-min-w-0">
                            <span class="ai-chat-activity-status u-text-sm u-fg-secondary" id="ai-chat-activity-status">Waiting for AI…</span>
                            <span class="ai-chat-activity-tip u-text-xs u-fg-tertiary" id="ai-chat-activity-tip"></span>
                        </div>
                    </div>
                </div>
                <div class="ai-chat-composer">
                    <div class="ai-chat-attachment-list u-cluster u-mb-2" id="ai-chat-attachment-list" hidden></div>
                    <div class="ai-chat-composer-box">
                        <textarea
                            id="ai-chat-input"
                            placeholder="Ask the AI to create or edit content… Paste or drop images and files here."
                            rows="1"
                        ></textarea>
                        <div class="ai-chat-composer-footer u-split u-gap-2">
                            <div class="ai-chat-composer-tools u-cluster u-gap-1 u-min-w-0">
                                <input type="file" id="ai-chat-image-input" accept="image/*" hidden>
                                <input type="file" id="ai-chat-doc-input" accept=".txt,.md,.json,.csv,.pdf" hidden>
                                <button type="button" class="btn btn-ghost btn-sm ai-chat-tool-btn" id="ai-chat-attach-image" data-tooltip="Add image" aria-label="Add image" hidden>
                                    ${icon('image', { size: 18 })}
                                </button>
                                <button type="button" class="btn btn-ghost btn-sm ai-chat-tool-btn" id="ai-chat-attach-doc" data-tooltip="Add document" aria-label="Add document" hidden>
                                    ${icon('file-text', { size: 18 })}
                                </button>
                                <div class="ai-chat-model-picker" id="ai-chat-model-picker" hidden>
                                    <button type="button" class="btn btn-ghost btn-sm ai-chat-tool-btn ai-chat-model-trigger" id="ai-chat-model-trigger" data-tooltip="Choose model" aria-haspopup="listbox" aria-expanded="false">
                                        ${icon('sparkle', { size: 16 })}
                                        <span id="ai-chat-model-label">Model</span>
                                        ${icon('caret-down', { size: 12 })}
                                    </button>
                                    <div class="popover ai-chat-model-menu" id="ai-chat-model-menu" role="listbox" hidden></div>
                                </div>
                            </div>
                            <button type="button" class="btn-icon btn-primary ai-chat-send-btn" id="ai-chat-send-btn" aria-label="Send message">
                                <span class="ai-chat-send-icon">${icon('paper-plane-tilt', { size: 18 })}</span>
                                <span class="ai-chat-cancel-icon" hidden>${icon('stop', { size: 18 })}</span>
                            </button>
                        </div>
                    </div>
                    <div class="ai-chat-quick-actions u-cluster u-mt-2" id="ai-chat-quick-actions" hidden></div>
                </div>
            </div>
            <div class="ai-chat-connection-selector" id="ai-chat-connection-selector" hidden aria-hidden="true">
                <div class="ai-chat-connection-list u-stack-2" id="ai-chat-connection-list">
                    <div class="loading-state">Loading connections…</div>
                </div>
            </div>
        `;
        document.body.appendChild(chatWindow);
        bindChatWindowActions(chatWindow);
        bindChatTooltips(chatWindow);
        
        document.getElementById('ai-chat-fullscreen-btn')?.addEventListener('click', toggleFullscreen);
        document.getElementById('ai-chat-history-btn')?.addEventListener('click', function (e) {
            e.stopPropagation();
            toggleSidePanel('history');
        });
        document.getElementById('ai-chat-console-btn')?.addEventListener('click', function (e) {
            e.stopPropagation();
            toggleSidePanel('console');
        });
        document.getElementById('ai-chat-approvals-btn')?.addEventListener('click', function (e) {
            e.stopPropagation();
            toggleSidePanel('approvals');
        });
        document.getElementById('ai-chat-overlay-backdrop')?.addEventListener('click', closeSidePanel);
        document.getElementById('ai-chat-side-panel-back')?.addEventListener('click', closeSidePanel);
        document.getElementById('ai-chat-new-btn')?.addEventListener('click', startNewChat);
        document.getElementById('ai-chat-send-btn')?.addEventListener('click', async function () {
            if (isChatRequestInFlight) {
                cancelChatRequest();
            } else {
                sendChatMessage();
            }
        });
        document.getElementById('ai-chat-input')?.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendChatMessage();
            }
        });
        document.getElementById('ai-chat-input')?.addEventListener('input', autoResizeChatInput);
        document.getElementById('ai-chat-console-clear')?.addEventListener('click', clearConsoleLogs);
        document.getElementById('ai-chat-attach-image')?.addEventListener('click', () => document.getElementById('ai-chat-image-input')?.click());
        document.getElementById('ai-chat-attach-doc')?.addEventListener('click', () => document.getElementById('ai-chat-doc-input')?.click());
        document.getElementById('ai-chat-image-input')?.addEventListener('change', handleImageAttachment);
        document.getElementById('ai-chat-doc-input')?.addEventListener('change', handleDocumentAttachment);
        bindComposerAttachmentHandlers();
        document.getElementById('ai-chat-model-trigger')?.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            toggleModelMenu();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                if (sidePanelView) closeSidePanel();
                closeModelMenu();
            }
        });
        window.addEventListener('resize', function () {
            if (!document.getElementById('ai-chat-model-menu')?.hidden) {
                positionModelMenu();
            }
            if (chatTooltipTarget) {
                positionChatTooltip(chatTooltipTarget);
            }
        });

        document.getElementById('ai-chat-connection-select')?.addEventListener('change', async function(e) {
            const connectionId = e.target.value;
            if (!connectionId) return;
            if (currentConnection && currentConnection.id === connectionId) return;
            if (chatMessages.length > 0 && !(await BirklyFeedback.confirm('Switch AI connection? This starts a new chat.'))) {
                e.target.value = currentConnection?.id || '';
                return;
            }
            selectConnection(connectionId);
        });
        
        renderConnectionDropdown();
        loadApprovalQueue();
        updateApprovalBadge();
        updateConsoleBadge();
        
        if (currentConnection && chatMessages.length > 0) {
            updateChatUI();
        }
    }

    function autoResizeChatInput() {
        const input = document.getElementById('ai-chat-input');
        if (!input) return;
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 160) + 'px';
    }

    async function startNewChat() {
        if (chatMessages.length > 0) {
            indexRecentChat();
        }
        if (await BirklyFeedback.confirm('Start a new chat? This will clear the current conversation.')) {
            clearChatState();
            closeSidePanel();
            const messages = document.getElementById('ai-chat-messages');
            if (messages) {
                messages.innerHTML = '<div class="ai-chat-welcome"><p>New chat started. Ask the AI to help you create or edit content.</p></div>';
            }
            conversationId = 'conv_' + Date.now();
            saveChatState();
        }
    }

    function updateChatComposerCapabilities() {
        const caps = currentConnection?.capabilities || {};
        const imageBtn = document.getElementById('ai-chat-attach-image');
        const docBtn = document.getElementById('ai-chat-attach-doc');
        const modelPicker = document.getElementById('ai-chat-model-picker');
        if (imageBtn) {
            imageBtn.hidden = !caps.vision;
            imageBtn.title = caps.vision ? 'Add image — or paste / drop into the chat box' : 'Images not supported for this provider';
        }
        if (docBtn) {
            docBtn.hidden = !caps.documents;
            docBtn.title = caps.documents ? 'Add document — or paste / drop into the chat box' : 'Documents not supported for this provider';
        }
        if (modelPicker) modelPicker.hidden = !caps.model_picker;
        updateApprovalsHeaderButton();
        if (caps.model_picker) {
            loadChatModels();
        } else {
            restoreChatModelForConnection(currentConnection?.id);
        }
    }

    function updateModelLabel() {
        const label = document.getElementById('ai-chat-model-label');
        if (!label || !currentConnection) return;
        const model = chatModelOverride || currentConnection.model || 'Model';
        const short = model.includes('/') ? model.split('/').pop() : model;
        label.textContent = short.length > 22 ? short.slice(0, 20) + '…' : short;
    }

    function normalizeChatModel(model) {
        if (!model) return null;
        if (typeof model === 'string') {
            return { id: model, name: model, description: '', context_length: null };
        }
        const id = String(model.id || model.name || '').trim();
        if (!id) return null;
        return {
            id: id,
            name: model.name || id,
            description: model.description || '',
            context_length: model.context_length || null,
        };
    }

    function normalizeChatModelList(models) {
        return (models || []).map(normalizeChatModel).filter(Boolean);
    }

    function filterChatModels(models, filter) {
        const query = String(filter || '').trim().toLowerCase();
        if (!query) return models || [];
        return (models || []).filter(function (model) {
            const hay = [
                model.id || '',
                model.name || '',
                model.description || '',
            ].join(' ').toLowerCase();
            return hay.indexOf(query) >= 0;
        });
    }

    function ensureChatTooltip() {
        if (chatTooltipEl) return chatTooltipEl;
        chatTooltipEl = document.createElement('div');
        chatTooltipEl.id = 'ai-chat-tooltip';
        chatTooltipEl.className = 'tooltip ai-chat-tooltip';
        chatTooltipEl.hidden = true;
        chatTooltipEl.setAttribute('role', 'tooltip');
        document.body.appendChild(chatTooltipEl);
        return chatTooltipEl;
    }

    function positionChatTooltip(el) {
        if (!chatTooltipEl || chatTooltipEl.hidden || !el) return;
        chatTooltipEl.style.left = '0';
        chatTooltipEl.style.top = '0';
        const rect = el.getBoundingClientRect();
        const tipRect = chatTooltipEl.getBoundingClientRect();
        let left = rect.left + rect.width / 2 - tipRect.width / 2;
        left = Math.max(8, Math.min(left, window.innerWidth - tipRect.width - 8));
        let top = rect.top - tipRect.height - 8;
        if (top < 8) {
            top = rect.bottom + 8;
        }
        chatTooltipEl.style.left = left + 'px';
        chatTooltipEl.style.top = top + 'px';
    }

    function showChatTooltip(el) {
        const text = el?.getAttribute('data-tooltip');
        if (!text) return;
        const tooltip = ensureChatTooltip();
        chatTooltipTarget = el;
        tooltip.textContent = text;
        tooltip.hidden = false;
        positionChatTooltip(el);
    }

    function hideChatTooltip() {
        if (chatTooltipEl) chatTooltipEl.hidden = true;
        chatTooltipTarget = null;
    }

    function bindChatTooltips(root) {
        if (!root || root.dataset.tooltipsBound === '1') return;
        root.dataset.tooltipsBound = '1';
        ensureChatTooltip();

        root.addEventListener('mouseover', function (e) {
            const el = e.target.closest('[data-tooltip]');
            if (!el || !root.contains(el)) return;
            showChatTooltip(el);
        });
        root.addEventListener('mouseout', function (e) {
            const el = e.target.closest('[data-tooltip]');
            if (!el || chatTooltipTarget !== el) return;
            const related = e.relatedTarget;
            if (related && el.contains(related)) return;
            hideChatTooltip();
        });
        root.addEventListener('focusin', function (e) {
            const el = e.target.closest('[data-tooltip]');
            if (!el || !root.contains(el)) return;
            showChatTooltip(el);
        });
        root.addEventListener('focusout', function (e) {
            const el = e.target.closest('[data-tooltip]');
            if (!el || chatTooltipTarget !== el) return;
            const related = e.relatedTarget;
            if (related && el.contains(related)) return;
            hideChatTooltip();
        });
        root.addEventListener('scroll', hideChatTooltip, true);
    }

    function ensureModelMenuShell(menu) {
        if (menu.querySelector('.ai-chat-model-menu-list')) return;
        menu.innerHTML =
            '<div class="ai-chat-model-menu-search-wrap">' +
            '<input type="search" class="ai-chat-model-menu-search" placeholder="Search models…" aria-label="Search models" autocomplete="off">' +
            '</div>' +
            '<div class="ai-chat-model-menu-list" role="presentation"></div>';
        const search = menu.querySelector('.ai-chat-model-menu-search');
        search?.addEventListener('input', function () {
            chatModelSearchQuery = search.value;
            renderModelMenuList();
            positionModelMenu();
        });
        search?.addEventListener('keydown', function (e) {
            e.stopPropagation();
            if (e.key === 'Escape') {
                closeModelMenu();
            }
        });
    }

    function renderModelMenuList() {
        const menu = document.getElementById('ai-chat-model-menu');
        const list = menu?.querySelector('.ai-chat-model-menu-list');
        if (!menu || !list) return;

        const active = chatModelOverride || currentConnection?.model || '';
        const filtered = filterChatModels(chatModelOptions, chatModelSearchQuery);

        if (chatModelOptions.length === 0) {
            list.innerHTML = '<div class="ai-chat-model-empty">No models listed</div>';
            return;
        }

        if (!filtered.length) {
            list.innerHTML = '<div class="ai-chat-model-empty">No models match your search.</div>';
            return;
        }

        list.innerHTML = filtered.map(function (model) {
            const id = escapeHtml(model.id);
            const name = escapeHtml(model.name || model.id);
            const description = escapeHtml(model.description || '');
            const context = model.context_length
                ? '<span class="ai-chat-model-option-meta">' + Number(model.context_length).toLocaleString() + ' tokens</span>'
                : '';
            const meta = description
                ? '<span class="ai-chat-model-option-meta">' + description + '</span>'
                : context;
            const selectedClass = active && active === model.id ? ' selected' : '';
            const selectedAttr = active && active === model.id ? ' aria-selected="true"' : '';
            return '<button type="button" role="option" class="dropdown-item ai-chat-model-option' + selectedClass + '"' + selectedAttr + ' data-model="' + id + '">' +
                '<span class="ai-chat-model-option-name">' + name + '</span>' +
                (name !== id ? '<span class="ai-chat-model-option-meta">' + id + '</span>' : '') +
                meta +
                '</button>';
        }).join('');

        list.querySelectorAll('[data-model]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                chatModelOverride = btn.getAttribute('data-model') || '';
                saveChatModelForConnection(currentConnection?.id, chatModelOverride);
                updateModelLabel();
                closeModelMenu();
            });
        });
    }

    async function loadChatModels() {
        if (!currentConnection) return;
        const menu = document.getElementById('ai-chat-model-menu');
        const menuWasOpen = menu && !menu.hidden;
        if (menu && menuWasOpen) {
            menu.innerHTML = '<div class="ai-chat-model-loading">Loading models…</div>';
        }
        try {
            if (!csrfToken) {
                await refreshCsrfToken();
            }
            const response = await fetch(apiUrl('/api/ai_connections.php?action=list_models'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    action: 'list_models',
                    id: currentConnection.id,
                    _csrf: csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '',
                }),
            });
            const result = await response.json();
            chatModelOptions = normalizeChatModelList(
                (result.status === 'ok' && result.data?.models) ? result.data.models : []
            );
            if (chatModelOptions.length === 0 && currentConnection.model) {
                chatModelOptions = normalizeChatModelList([currentConnection.model]);
            }
            renderModelMenu();
            updateModelLabel();
            if (menuWasOpen) {
                positionModelMenu();
            }
        } catch (error) {
            if (menu && menuWasOpen) {
                menu.innerHTML = '<div class="ai-chat-model-empty">Could not load models</div>';
            }
        }
    }

    function renderModelMenu() {
        const menu = document.getElementById('ai-chat-model-menu');
        if (!menu) return;
        ensureModelMenuShell(menu);
        const search = menu.querySelector('.ai-chat-model-menu-search');
        if (search && search.value !== chatModelSearchQuery) {
            search.value = chatModelSearchQuery;
        }
        renderModelMenuList();
        if (!menu.hidden) {
            positionModelMenu();
        }
    }

    function attachModelMenuPortal() {
        const menu = document.getElementById('ai-chat-model-menu');
        const picker = document.getElementById('ai-chat-model-picker');
        if (!menu || !picker || menu.dataset.portaled === '1') return;
        modelMenuAnchor = document.createComment('ai-chat-model-menu-anchor');
        picker.insertBefore(modelMenuAnchor, menu);
        document.body.appendChild(menu);
        menu.dataset.portaled = '1';
    }

    function restoreModelMenuPortal() {
        const menu = document.getElementById('ai-chat-model-menu');
        const picker = document.getElementById('ai-chat-model-picker');
        if (!menu || !picker || menu.dataset.portaled !== '1' || !modelMenuAnchor) return;
        picker.insertBefore(menu, modelMenuAnchor.nextSibling);
        modelMenuAnchor.remove();
        modelMenuAnchor = null;
        delete menu.dataset.portaled;
    }

    function bindModelMenuOutsideClose() {
        if (modelMenuOutsideHandler) return;
        modelMenuOutsideHandler = function (e) {
            const menu = document.getElementById('ai-chat-model-menu');
            if (!menu || menu.hidden) return;
            if (e.target.closest('#ai-chat-model-menu') || e.target.closest('#ai-chat-model-trigger')) {
                return;
            }
            closeModelMenu();
        };
        document.addEventListener('mousedown', modelMenuOutsideHandler);
    }

    function unbindModelMenuOutsideClose() {
        if (!modelMenuOutsideHandler) return;
        document.removeEventListener('mousedown', modelMenuOutsideHandler);
        modelMenuOutsideHandler = null;
    }

    async function toggleModelMenu() {
        const menu = document.getElementById('ai-chat-model-menu');
        const trigger = document.getElementById('ai-chat-model-trigger');
        if (!menu || !trigger) return;

        if (!menu.hidden) {
            closeModelMenu();
            return;
        }

        if (chatModelOptions.length === 0) {
            await loadChatModels();
        } else {
            renderModelMenu();
        }

        hideChatTooltip();
        attachModelMenuPortal();
        menu.hidden = false;
        menu.classList.add('is-open');
        trigger.setAttribute('aria-expanded', 'true');
        positionModelMenu();
        bindModelMenuOutsideClose();
        const search = menu.querySelector('.ai-chat-model-menu-search');
        if (search) {
            window.requestAnimationFrame(function () {
                search.focus();
                search.select();
            });
        }
    }

    function positionModelMenu() {
        const menu = document.getElementById('ai-chat-model-menu');
        const trigger = document.getElementById('ai-chat-model-trigger');
        if (!menu || !trigger || menu.hidden) return;
        const rect = trigger.getBoundingClientRect();
        const width = Math.min(360, Math.max(260, rect.width + 80));
        const left = Math.max(8, Math.min(rect.left, window.innerWidth - width - 8));
        menu.style.position = 'fixed';
        menu.style.left = left + 'px';
        menu.style.width = width + 'px';
        menu.style.minWidth = width + 'px';
        menu.style.maxWidth = width + 'px';
        menu.style.zIndex = '1101';
        menu.style.visibility = 'hidden';
        menu.style.top = 'auto';
        menu.style.bottom = 'auto';
        const menuHeight = menu.offsetHeight;
        const spaceAbove = rect.top - 12;
        const spaceBelow = window.innerHeight - rect.bottom - 12;
        if (spaceAbove >= menuHeight || spaceAbove >= spaceBelow) {
            menu.style.bottom = (window.innerHeight - rect.top + 8) + 'px';
            menu.style.top = 'auto';
        } else {
            menu.style.top = (rect.bottom + 8) + 'px';
            menu.style.bottom = 'auto';
        }
        menu.style.visibility = '';
    }

    function closeModelMenu() {
        const menu = document.getElementById('ai-chat-model-menu');
        const trigger = document.getElementById('ai-chat-model-trigger');
        chatModelSearchQuery = '';
        if (menu) {
            const search = menu.querySelector('.ai-chat-model-menu-search');
            if (search) search.value = '';
            menu.hidden = true;
            menu.classList.remove('is-open');
            menu.style.position = '';
            menu.style.left = '';
            menu.style.top = '';
            menu.style.bottom = '';
            menu.style.width = '';
            menu.style.minWidth = '';
            menu.style.maxWidth = '';
            menu.style.zIndex = '';
            menu.style.visibility = '';
        }
        restoreModelMenuPortal();
        unbindModelMenuOutsideClose();
        if (trigger) trigger.setAttribute('aria-expanded', 'false');
    }

    function bindComposerAttachmentHandlers() {
        const input = document.getElementById('ai-chat-input');
        const composerBox = document.querySelector('.ai-chat-composer-box');
        if (input && input.dataset.pasteBound !== '1') {
            input.dataset.pasteBound = '1';
            input.addEventListener('paste', handleComposerPaste);
        }
        if (composerBox && composerBox.dataset.dropBound !== '1') {
            composerBox.dataset.dropBound = '1';
            composerBox.addEventListener('dragover', handleComposerDragOver);
            composerBox.addEventListener('dragleave', handleComposerDragLeave);
            composerBox.addEventListener('drop', handleComposerDrop);
        }
    }

    function canAttachImages() {
        return !!(currentConnection?.capabilities?.vision);
    }

    function canAttachDocuments() {
        return !!(currentConnection?.capabilities?.documents);
    }

    function isTextDocumentFile(file) {
        if (!file) return false;
        const name = String(file.name || '').toLowerCase();
        const mime = String(file.type || '').toLowerCase();
        if (mime.startsWith('text/')) return true;
        return DOCUMENT_EXTENSIONS.some(function (ext) { return name.endsWith(ext); });
    }

    function defaultNameForFile(file, fallback) {
        if (file?.name) return file.name;
        return fallback;
    }

    function notifyAttachmentIssue(message) {
        appendConsoleLog('warn', message);
        if (typeof BirklyFeedback !== 'undefined' && BirklyFeedback.toast) {
            BirklyFeedback.toast(message, 'warning');
        }
    }

    function readFileAsDataUrl(file) {
        return new Promise(function (resolve, reject) {
            const reader = new FileReader();
            reader.onload = function () { resolve(String(reader.result || '')); };
            reader.onerror = reject;
            reader.readAsDataURL(file);
        });
    }

    function readFileAsText(file) {
        return new Promise(function (resolve, reject) {
            const reader = new FileReader();
            reader.onload = function () { resolve(String(reader.result || '')); };
            reader.onerror = reject;
            reader.readAsText(file);
        });
    }

    async function queueImageFile(file, suggestedName) {
        if (!file) return false;
        if (!canAttachImages()) {
            notifyAttachmentIssue('Image paste is not supported for this connection. Use a vision-capable provider.');
            return false;
        }
        if (!String(file.type || '').startsWith('image/')) {
            notifyAttachmentIssue('Only image files can be attached as images.');
            return false;
        }
        if (file.size > MAX_IMAGE_BYTES) {
            notifyAttachmentIssue('Image is too large (max 4 MB).');
            return false;
        }
        if (pendingAttachments.length >= MAX_CHAT_ATTACHMENTS) {
            notifyAttachmentIssue('Maximum ' + MAX_CHAT_ATTACHMENTS + ' attachments per message.');
            return false;
        }
        try {
            const dataUrl = await readFileAsDataUrl(file);
            const base64 = dataUrl.replace(/^data:[^;]+;base64,/, '');
            pendingAttachments.push({
                type: 'image',
                name: defaultNameForFile(file, suggestedName || 'pasted-image.png'),
                mime: file.type || 'image/png',
                base64: base64,
            });
            renderAttachmentList();
            return true;
        } catch (error) {
            notifyAttachmentIssue('Could not read image: ' + error.message);
            return false;
        }
    }

    async function queueDocumentFile(file) {
        if (!file) return false;
        if (!canAttachDocuments()) {
            notifyAttachmentIssue('Document attachments are not supported for this connection.');
            return false;
        }
        if (!isTextDocumentFile(file)) {
            notifyAttachmentIssue('Only text-based documents are supported (.txt, .md, .json, .csv, etc.).');
            return false;
        }
        if (pendingAttachments.length >= MAX_CHAT_ATTACHMENTS) {
            notifyAttachmentIssue('Maximum ' + MAX_CHAT_ATTACHMENTS + ' attachments per message.');
            return false;
        }
        try {
            const content = await readFileAsText(file);
            pendingAttachments.push({
                type: 'document',
                name: defaultNameForFile(file, 'document.txt'),
                content: content.slice(0, 12000),
            });
            renderAttachmentList();
            return true;
        } catch (error) {
            notifyAttachmentIssue('Could not read document: ' + error.message);
            return false;
        }
    }

    async function ingestAttachmentFiles(files, sourceLabel) {
        const list = Array.from(files || []);
        if (!list.length) return;
        let added = 0;
        for (const file of list) {
            if (String(file.type || '').startsWith('image/')) {
                if (await queueImageFile(file)) added++;
            } else if (isTextDocumentFile(file)) {
                if (await queueDocumentFile(file)) added++;
            } else {
                notifyAttachmentIssue('Unsupported file: ' + (file.name || 'unknown') + '. Paste images or text documents.');
            }
        }
        if (added > 0 && sourceLabel) {
            appendConsoleLog('info', added + ' file(s) attached via ' + sourceLabel + '.');
        }
    }

    async function handleComposerPaste(e) {
        const clipboard = e.clipboardData;
        if (!clipboard) return;

        const imageItems = [];
        const fileItems = [];
        for (let i = 0; i < clipboard.items.length; i++) {
            const item = clipboard.items[i];
            if (item.kind === 'file') {
                const file = item.getAsFile();
                if (file) fileItems.push(file);
            } else if (item.kind === 'string' && item.type.indexOf('image/') === 0) {
                const file = item.getAsFile();
                if (file) imageItems.push(file);
            }
        }

        const files = fileItems.length ? fileItems : imageItems;
        if (!files.length) return;

        e.preventDefault();
        await ingestAttachmentFiles(files, 'paste');
    }

    function handleComposerDragOver(e) {
        if (!e.dataTransfer?.types?.includes('Files')) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'copy';
        e.currentTarget.classList.add('is-drag-over');
    }

    function handleComposerDragLeave(e) {
        e.currentTarget.classList.remove('is-drag-over');
    }

    async function handleComposerDrop(e) {
        e.preventDefault();
        e.currentTarget.classList.remove('is-drag-over');
        const files = e.dataTransfer?.files;
        if (!files?.length) return;
        await ingestAttachmentFiles(files, 'drop');
    }

    function handleImageAttachment(e) {
        const file = e.target.files?.[0];
        if (!file) return;
        queueImageFile(file);
        e.target.value = '';
    }

    function handleDocumentAttachment(e) {
        const file = e.target.files?.[0];
        if (!file) return;
        queueDocumentFile(file);
        e.target.value = '';
    }

    function renderAttachmentList() {
        const list = document.getElementById('ai-chat-attachment-list');
        if (!list) return;
        if (pendingAttachments.length === 0) {
            list.hidden = true;
            list.innerHTML = '';
            return;
        }
        list.hidden = false;
        list.innerHTML = pendingAttachments.map(function (item, index) {
            const iconName = item.type === 'image' ? 'image' : 'file-text';
            return '<span class="chip ai-chat-attachment-chip">' + icon(iconName, { size: 14 }) +
                '<span class="u-truncate">' + escapeHtml(item.name) + '</span>' +
                '<button type="button" class="chip-remove" data-remove-attachment="' + index + '" aria-label="Remove">×</button></span>';
        }).join('');
        list.querySelectorAll('[data-remove-attachment]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const idx = parseInt(btn.getAttribute('data-remove-attachment'), 10);
                pendingAttachments.splice(idx, 1);
                renderAttachmentList();
            });
        });
    }

    function buildMessageWithAttachments(message) {
        return String(message || '').trim();
    }

    function serializeAttachmentsForApi() {
        return pendingAttachments.map(function (item) {
            if (item.type === 'image') {
                return {
                    type: 'image',
                    name: item.name,
                    mime: item.mime || 'image/png',
                    base64: item.base64 || '',
                };
            }
            if (item.type === 'document') {
                return {
                    type: 'document',
                    name: item.name,
                    content: item.content || '',
                };
            }
            return null;
        }).filter(Boolean);
    }

    function buildDisplayMessage(rawMessage) {
        const text = String(rawMessage || '').trim();
        if (text) return text;
        if (pendingAttachments.length === 0) return '';
        return 'Attached: ' + pendingAttachments.map(function (a) { return a.name; }).join(', ');
    }

    // Show chat window
    async function showChatWindow() {
        const chatWindow = document.getElementById('ai-chat-window');
        if (!chatWindow) {
            createChatWindow();
            return showChatWindow();
        }

        await loadConnections();
        validateCurrentConnection();
        renderConnectionDropdown();

        chatWindow.style.display = 'flex';
        document.body.classList.add('ai-chat-window-open');
        closeSidePanel();
        closeModelMenu();
        setAssistFabState('chat');
        try {
            localStorage.setItem(STORAGE_KEY_WINDOW_OPEN, '1');
        } catch (e) { /* ignore */ }
        renderInChatQuickActions();

        if (availableConnections.length === 0) {
            showNoConnectionMessage();
        } else if (!currentConnection) {
            selectConnection(availableConnections[0].id);
        } else {
            updateConnectionDropdownValue();
            updateChatComposerCapabilities();
        }
    }

    function validateCurrentConnection() {
        if (!currentConnection) {
            return;
        }

        const stillExists = availableConnections.some(conn => conn.id === currentConnection.id);
        if (!stillExists) {
            clearChatState();
            currentConnection = null;
            const messages = document.getElementById('ai-chat-messages');
            if (messages) {
                messages.innerHTML = '<div class="ai-chat-welcome"><p>Your previous AI connection was removed. Choose a connection to continue.</p></div>';
            }
        }
    }

    function updateConnectionDropdownValue() {
        const select = document.getElementById('ai-chat-connection-select');
        if (select && currentConnection) {
            select.value = currentConnection.id;
        }
    }

    function renderConnectionDropdown() {
        const select = document.getElementById('ai-chat-connection-select');
        if (!select) return;

        if (availableConnections.length === 0) {
            select.innerHTML = '<option value="">No connections available</option>';
            select.disabled = true;
            currentConnection = null;
            return;
        }

        select.disabled = false;
        select.innerHTML = availableConnections.map(conn => {
            const label = conn.name || conn.provider;
            return `<option value="${conn.id}">${escapeHtml(label)}</option>`;
        }).join('');

        updateConnectionDropdownValue();
    }

    // Hide chat window
    function hideChatWindow() {
        const chatWindow = document.getElementById('ai-chat-window');
        closeSidePanel();
        closeModelMenu();
        if (chatWindow) {
            chatWindow.style.display = 'none';
        }
        document.body.classList.remove('ai-chat-window-open');
        if (assistFabState === 'chat') {
            setAssistFabState('idle');
        }
        try {
            localStorage.setItem(STORAGE_KEY_WINDOW_OPEN, '0');
        } catch (e) { /* ignore */ }
    }

    async function restoreChatWindowOpen() {
        let shouldOpen = false;
        try {
            shouldOpen = localStorage.getItem(STORAGE_KEY_WINDOW_OPEN) === '1';
        } catch (e) { /* ignore */ }
        if (!shouldOpen) {
            return;
        }
        if (!document.getElementById('ai-chat-window')) {
            createChatWindow();
        }
        await showChatWindow();
        if (chatMessages.length > 0) {
            updateChatUI();
        }
    }

    // Show no connection message
    function showNoConnectionMessage() {
        const messages = document.getElementById('ai-chat-messages');
        if (messages) {
            messages.innerHTML = `
                <div class="ai-chat-welcome">
                    <h3>No AI Connection Found</h3>
                    <p>You need to set up an AI connection first to use the AI assistant.</p>
                    <button class="btn btn-primary" onclick="window.location.href = Birkly.url('/admin/index.php?page=settings&tab=ai')">
                        Go to Settings
                    </button>
                </div>
            `;
        }
        
        const composer = document.querySelector('.ai-chat-composer');
        if (composer) {
            composer.hidden = true;
        }
    }

    // Legacy bottom connection list (kept hidden; header dropdown is primary)
    function renderConnectionSelector() {
        renderConnectionDropdown();
    }

    // Select connection
    function selectConnection(connectionId) {
        const switching = currentConnection && currentConnection.id !== connectionId;

        // If switching connections, start a new conversation
        if (switching) {
            if (currentConnection?.id && chatModelOverride) {
                saveChatModelForConnection(currentConnection.id, chatModelOverride);
            }
            clearChatState();
        }
        
        // Load connection details
        fetch(apiUrl('/api/ai_connections.php?action=get&id=' + encodeURIComponent(connectionId)), { credentials: 'same-origin' })
            .then(r => r.json())
            .then(result => {
                if (result.status === 'ok') {
                    currentConnection = result.data.connection;
                    restoreChatModelForConnection(currentConnection.id);
                    if (!conversationId) {
                        conversationId = 'conv_' + Date.now();
                    }
                    
                    updateConnectionDropdownValue();
                    updateChatComposerCapabilities();
                    
                    // Restore or clear messages
                    const messages = document.getElementById('ai-chat-messages');
                    if (messages) {
                        if (chatMessages.length > 0) {
                            // Restore messages
                            updateChatUI();
                        } else {
                            // Show welcome
                            messages.innerHTML = '<div class="ai-chat-welcome"><p>Chat started with <strong>' + escapeHtml(currentConnection.name || currentConnection.provider) + '</strong>. Ask the AI to help you create or edit content.</p></div>';
                        }
                    }
                    
                    // Show composer
                    const composer = document.querySelector('.ai-chat-composer');
                    if (composer) {
                        composer.hidden = false;
                    }
                    
                    // Focus input
                    document.getElementById('ai-chat-input')?.focus();
                    
                    saveChatState();
                } else {
                    showError('Failed to load connection: ' + (result.message || 'Unknown error'));
                    validateCurrentConnection();
                    renderConnectionDropdown();
                }
            })
            .catch(err => {
                console.error('Error loading connection:', err);
                showError('Failed to load connection');
            });
    }

    // Collect current admin page context for AI (P39-M11)
    function collectPageContext() {
        const params = new URLSearchParams(window.location.search);
        const route = params.get('page') || '';
        const context = {
            route: route,
            collection: params.get('collection') || window.collectionSlug || '',
            entry_id: params.get('entry') || '',
            sub_collection: params.get('sub_collection') || '',
            sub_entry_id: params.get('sub_entry') || '',
            field_names: []
        };

        const entryIdInput = document.querySelector('input[name="entry_id"]');
        if (entryIdInput && entryIdInput.value) {
            context.entry_id = entryIdInput.value;
        }

        const collectionInput = document.querySelector('input[name="collection"]');
        if (collectionInput && collectionInput.value) {
            context.collection = collectionInput.value;
        }

        document.querySelectorAll('.field-block[data-field-name]').forEach(function(el) {
            const name = el.getAttribute('data-field-name');
            if (name) {
                context.field_names.push(name);
            }
        });

        if (context.field_names.length === 0 && Array.isArray(window.collectionFields)) {
            context.field_names = window.collectionFields
                .map(function(f) { return f && f.name ? f.name : ''; })
                .filter(Boolean);
        }

        return context;
    }

    function setSendButtonStreaming(streaming) {
        const btn = document.getElementById('ai-chat-send-btn');
        if (!btn) return;
        btn.classList.toggle('ai-chat-send-btn--streaming', streaming);
        btn.setAttribute('aria-label', streaming ? 'Cancel response' : 'Send message');
        btn.title = streaming ? 'Cancel' : 'Send';
        const sendIcon = btn.querySelector('.ai-chat-send-icon');
        const cancelIcon = btn.querySelector('.ai-chat-cancel-icon');
        if (sendIcon) sendIcon.hidden = streaming;
        if (cancelIcon) cancelIcon.hidden = !streaming;
    }

    const CHAT_ACTIVITY_STATUS_CYCLE = [
        'Waiting for AI…',
        'AI is thinking…',
        'AI is working…',
        'Still working…',
    ];

    const CHAT_ACTIVITY_TIPS = [
        {
            text: 'Did you know Birkly can run with MySQL or PostgreSQL instead of flat files?',
            href: 'https://docs.birkly.com/content/01-admin/settings.html#storage-backends',
            label: 'Learn more',
        },
        {
            text: 'The AI can read and edit files in your project/ folder for site builds.',
            href: 'https://docs.birkly.com/content/03-ai/ai-assistant.html',
            label: 'Learn more',
        },
        {
            text: 'You can attach images and documents to chat messages for context.',
        },
        {
            text: 'Use the Approvals tab to review AI-proposed changes before they run.',
            href: 'https://docs.birkly.com/content/03-ai/approvals.html',
            label: 'Learn more',
        },
        {
            text: 'Collections and entries live under content/ — back them up regularly.',
            href: 'https://docs.birkly.com/content/07-operations/backup-and-restore.html',
            label: 'Learn more',
        },
        {
            text: 'Switch models from the sparkle menu in the composer for faster or smarter replies.',
        },
        {
            text: 'Birkly templates use {for each entry in \'collection\'} syntax for dynamic pages.',
            href: 'https://docs.birkly.com/content/02-content/templating.html',
            label: 'Learn more',
        },
    ];

    let chatActivityStatusTimer = null;
    let chatActivityTipTimer = null;
    let chatActivityStatusIndex = 0;
    let chatActivityTipIndex = 0;
    let chatActivityServerStatus = '';

    function stripChatStatusMarkdown(text) {
        return String(text || '')
            .replace(/\*\*([^*]+)\*\*/g, '$1')
            .replace(/[_*`]/g, '')
            .trim();
    }

    function renderChatActivityTip(tip) {
        if (!tip) return '';
        const label = tip.label ? escapeHtml(tip.label) : 'Learn more';
        if (tip.href) {
            return escapeHtml(tip.text) + ' <a href="' + escapeHtml(tip.href) + '" target="_blank" rel="noopener noreferrer">' + label + '</a>.';
        }
        return escapeHtml(tip.text) + '.';
    }

    function rotateChatActivityStatus() {
        if (chatActivityServerStatus) return;
        const statusEl = document.getElementById('ai-chat-activity-status');
        if (!statusEl) return;
        chatActivityStatusIndex = (chatActivityStatusIndex + 1) % CHAT_ACTIVITY_STATUS_CYCLE.length;
        statusEl.textContent = CHAT_ACTIVITY_STATUS_CYCLE[chatActivityStatusIndex];
    }

    function rotateChatActivityTip() {
        const tipEl = document.getElementById('ai-chat-activity-tip');
        if (!tipEl) return;
        chatActivityTipIndex = (chatActivityTipIndex + 1) % CHAT_ACTIVITY_TIPS.length;
        tipEl.innerHTML = renderChatActivityTip(CHAT_ACTIVITY_TIPS[chatActivityTipIndex]);
    }

    function showChatActivity(initialStatus) {
        const activityEl = document.getElementById('ai-chat-activity');
        const statusEl = document.getElementById('ai-chat-activity-status');
        const tipEl = document.getElementById('ai-chat-activity-tip');
        if (!activityEl || !statusEl || !tipEl) return;

        chatActivityServerStatus = '';
        chatActivityStatusIndex = 0;
        chatActivityTipIndex = Math.floor(Math.random() * CHAT_ACTIVITY_TIPS.length);

        statusEl.textContent = initialStatus || CHAT_ACTIVITY_STATUS_CYCLE[0];
        tipEl.innerHTML = renderChatActivityTip(CHAT_ACTIVITY_TIPS[chatActivityTipIndex]);
        activityEl.hidden = false;

        clearInterval(chatActivityStatusTimer);
        clearInterval(chatActivityTipTimer);
        chatActivityStatusTimer = setInterval(rotateChatActivityStatus, 2500);
        chatActivityTipTimer = setInterval(rotateChatActivityTip, 5000);

        const messages = document.getElementById('ai-chat-messages');
        if (messages) {
            messages.scrollTop = messages.scrollHeight;
        }
    }

    function updateChatActivityStatus(statusText) {
        const statusEl = document.getElementById('ai-chat-activity-status');
        const activityEl = document.getElementById('ai-chat-activity');
        if (!statusEl || !activityEl || activityEl.hidden) return;

        const cleaned = stripChatStatusMarkdown(statusText);
        if (!cleaned) return;

        chatActivityServerStatus = cleaned;
        statusEl.textContent = cleaned;
    }

    function hideChatActivity() {
        const activityEl = document.getElementById('ai-chat-activity');
        if (activityEl) activityEl.hidden = true;
        chatActivityServerStatus = '';
        clearInterval(chatActivityStatusTimer);
        clearInterval(chatActivityTipTimer);
        chatActivityStatusTimer = null;
        chatActivityTipTimer = null;
    }

    function ensureAssistantMessage(assistantRef) {
        if (assistantRef.id) return assistantRef.id;
        hideChatActivity();
        assistantRef.id = addChatMessage('assistant', '', true);
        const messageEl = document.getElementById(assistantRef.id);
        if (messageEl) {
            messageEl.classList.add('ai-chat-message-streaming');
            const contentEl = messageEl.querySelector('.ai-chat-message-content');
            if (contentEl) contentEl.innerHTML = '';
        }
        return assistantRef.id;
    }

    function cancelChatRequest() {
        if (activeChatAbortController) {
            activeChatAbortController.abort();
            activeChatAbortController = null;
        }
        isChatRequestInFlight = false;
        setSendButtonStreaming(false);
        hideChatActivity();
        addChatMessage('system', 'Request cancelled.');
    }

    function updateStreamingMessage(messageId, content, isComplete) {
        const messageEl = document.getElementById(messageId);
        if (!messageEl) return;
        if (messageEl.dataset.chatRole === 'user') {
            console.error('Refusing to stream assistant content into a user message bubble:', messageId);
            return;
        }
        const contentEl = messageEl.querySelector('.ai-chat-message-content');
        if (!contentEl) return;
        contentEl.innerHTML = renderMarkdown(sanitizeChatDisplayText(content));
        if (isComplete) {
            messageEl.classList.remove('ai-chat-message-streaming');
        }
        const messages = document.getElementById('ai-chat-messages');
        if (messages) {
            messages.scrollTop = messages.scrollHeight;
        }
    }

    function handleChatApiResult(result, assistantMessageId) {
        if (result && !result.status && result.data) {
            if ((result.data.actions_executed || 0) > 0 || (result.data.actions_created || 0) > 0 || result.data.response) {
                result.status = result.data.has_errors ? 'partial' : 'ok';
            }
        }
        if (!result || (result.status !== 'ok' && result.status !== 'partial')) {
            const errorMsg = formatChatErrorDetails(result);
            appendConsoleLog('error', errorMsg, buildConsoleErrorDetails(result));
            if (assistantMessageId) {
                removeChatMessage(assistantMessageId);
            }
            addChatMessageWithRetry('error', errorMsg);
            return;
        }

        const responseText = sanitizeChatDisplayText(result.data.response || result.data.message || '');
        if (assistantMessageId) {
            const messageEl = document.getElementById(assistantMessageId);
            const contentEl = messageEl ? messageEl.querySelector('.ai-chat-message-content') : null;
            const streamedText = contentEl ? contentEl.textContent.trim() : '';
            const finalText = responseText || (streamedText && streamedText !== 'Thinking...' ? streamedText : '');
            if (finalText) {
                updateStreamingMessage(assistantMessageId, finalText, true);
                const assistantEntry = { role: 'assistant', content: finalText };
                const last = chatMessages[chatMessages.length - 1];
                if (last && last.role === 'assistant' && last.content === '') {
                    chatMessages[chatMessages.length - 1] = assistantEntry;
                } else {
                    chatMessages.push(assistantEntry);
                }
                saveChatState();
            } else if (result.data.requires_approval) {
                updateStreamingMessage(assistantMessageId, 'Prepared actions for your review (see below).', true);
                chatMessages.push({ role: 'assistant', content: 'Prepared actions for your review.' });
                saveChatState();
            } else {
                messageEl?.classList.remove('ai-chat-message-streaming');
            }
        } else if (responseText) {
            addChatMessage('assistant', responseText);
        }

        if (result.status === 'partial' && result.data.has_errors) {
            const executionResults = result.data.execution_results || [];
            const failedActions = executionResults.filter(function(r) { return !r.success; });
            failedActions.forEach(function(actionResult, index) {
                appendConsoleLog(
                    'error',
                    describeActionConsoleResult(actionResult, index),
                    actionResult
                );
            });
            executionResults.filter(function(r) { return r.success; }).forEach(function(actionResult, index) {
                appendConsoleLog(
                    'success',
                    describeActionConsoleResult(actionResult, index),
                    actionResult.data || actionResult
                );
            });
            if (failedActions.length > 0) {
                addChatMessage('system', 'Warning: ' + failedActions.length + ' action(s) failed. See the Console tab for details.');
            }
        }

        if (result.data.requires_approval && result.data.actions_created > 0) {
            addChatMessage('system', result.data.actions_created + ' action(s) pending approval. Open the Approvals tab to review.');
            if (result.data.approvals && result.data.approvals.length > 0) {
                result.data.approvals.forEach(function(approval) {
                    addApprovalCard(approval);
                });
            }
            loadApprovalQueue();
        }

        if (!result.data.requires_approval && result.data.actions_executed > 0) {
            const executionResults = result.data.execution_results || [];
            const successCount = executionResults.filter(function(r) { return r.success; }).length || 0;
            if (successCount > 0) {
                executionResults.filter(function(r) { return r.success; }).forEach(function(actionResult, index) {
                    appendConsoleLog(
                        'success',
                        describeActionConsoleResult(actionResult, index),
                        actionResult.data || actionResult
                    );
                });
                if (!responseHasExecutionReport(responseText)) {
                    addChatMessage('system', successCount + ' action(s) executed successfully.');
                }
            }
        }
    }

    async function parseSseChatResponse(response, assistantRef) {
        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';
        let streamedContent = '';
        let finalResult = null;

        while (true) {
            const chunk = await reader.read();
            if (chunk.done) break;
            buffer += decoder.decode(chunk.value, { stream: true });

            let eventEnd;
            while ((eventEnd = buffer.indexOf('\n\n')) !== -1) {
                const rawEvent = buffer.slice(0, eventEnd);
                buffer = buffer.slice(eventEnd + 2);
                const lines = rawEvent.split('\n');
                let eventName = 'message';
                let dataLine = '';
                lines.forEach(function(line) {
                    if (line.startsWith('event:')) {
                        eventName = line.slice(6).trim();
                    } else if (line.startsWith('data:')) {
                        dataLine += line.slice(5).trim();
                    }
                });
                if (!dataLine) continue;
                let payload;
                try {
                    payload = JSON.parse(dataLine);
                } catch (e) {
                    continue;
                }
                if (eventName === 'token' && payload.content) {
                    ensureAssistantMessage(assistantRef);
                    streamedContent += payload.content;
                    updateStreamingMessage(assistantRef.id, streamedContent, false);
                } else if (eventName === 'status' && payload.message) {
                    updateChatActivityStatus(payload.message);
                } else if (eventName === 'done') {
                    finalResult = payload;
                }
            }
        }

        return finalResult;
    }

    // Send chat message (supports streaming, cancel, retry — P39-M12)
    async function sendChatMessage(messageOverride) {
        if (!currentConnection) {
            showError('No connection selected');
            return;
        }

        const input = document.getElementById('ai-chat-input');
        const rawMessage = (typeof messageOverride === 'string' ? messageOverride : input?.value || '').trim();
        if (!rawMessage && pendingAttachments.length === 0) return;

        const message = buildMessageWithAttachments(rawMessage || 'Please review the attached file(s).');
        const displayMessage = buildDisplayMessage(rawMessage);
        const attachmentsPayload = serializeAttachmentsForApi();

        lastFailedMessage = null;
        if (!conversationId) {
            conversationId = 'conv_' + Date.now();
            saveChatState();
        }
        addChatMessage('user', displayMessage);
        if (input && typeof messageOverride !== 'string') {
            input.value = '';
            autoResizeChatInput();
        }
        pendingAttachments = [];
        renderAttachmentList();

        const assistantRef = { id: null };
        showChatActivity('Waiting for AI…');

        activeChatAbortController = new AbortController();
        isChatRequestInFlight = true;
        setSendButtonStreaming(true);

        if (!csrfToken) {
            await refreshCsrfToken();
        }

        try {
            const response = await fetch(apiUrl('/api/ai_chat.php'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                signal: activeChatAbortController.signal,
                body: JSON.stringify({
                    action: 'chat',
                    ai_connection_id: currentConnection.id,
                    message: message,
                    attachments: attachmentsPayload,
                    conversation_id: conversationId,
                    page_context: collectPageContext(),
                    stream: true,
                    model: chatModelOverride || undefined,
                    _csrf: csrfToken
                })
            });

            if (!response.ok) {
                const errorText = await response.text();
                hideChatActivity();
                lastFailedMessage = message;
                appendConsoleLog('error', formatHttpError(response.status, errorText), { status: response.status, body: errorText });
                addChatMessageWithRetry('error', formatHttpError(response.status, errorText));
                return;
            }

            const contentType = (response.headers.get('content-type') || '').toLowerCase();
            let result;

            if (contentType.includes('text/event-stream')) {
                result = await parseSseChatResponse(response, assistantRef);
                hideChatActivity();
                if (!result) {
                    if (assistantRef.id) {
                        removeChatMessage(assistantRef.id);
                    }
                    lastFailedMessage = message;
                    appendConsoleLog('error', 'Stream ended without a response');
                    addChatMessageWithRetry('error', 'Stream ended without a response.');
                    return;
                }
            } else {
                hideChatActivity();
                const responseText = await response.text();
                try {
                    result = JSON.parse(responseText);
                } catch (parseError) {
                    lastFailedMessage = message;
                    appendConsoleLog('error', 'Invalid response from server', parseError.message);
                    addChatMessageWithRetry('error', 'Invalid response from server.');
                    return;
                }
            }

            if (!assistantRef.id && result && (result.status === 'ok' || result.status === 'partial')) {
                assistantRef.id = ensureAssistantMessage(assistantRef);
            }

            handleChatApiResult(result, assistantRef.id);
        } catch (error) {
            hideChatActivity();
            if (error.name === 'AbortError') {
                return;
            }
            console.error('Error sending message:', error);
            appendConsoleLog('error', 'Error sending message: ' + error.message, { name: error.name });
            if (assistantRef.id) {
                removeChatMessage(assistantRef.id);
            }
            lastFailedMessage = message;
            addChatMessageWithRetry('error', 'Error sending message: ' + error.message);
        } finally {
            hideChatActivity();
            activeChatAbortController = null;
            isChatRequestInFlight = false;
            setSendButtonStreaming(false);
        }
    }

    function retryLastMessage() {
        if (!lastFailedMessage) return;
        const message = lastFailedMessage;
        lastFailedMessage = null;
        sendChatMessage(message);
    }

    function addChatMessageWithRetry(role, content) {
        const messageId = addChatMessage(role, content, false, true);
        if (!messageId || !lastFailedMessage) return messageId;
        const messageEl = document.getElementById(messageId);
        if (!messageEl) return messageId;
        const retryBtn = document.createElement('button');
        retryBtn.type = 'button';
        retryBtn.className = 'btn btn-sm ai-chat-retry-btn';
        retryBtn.textContent = 'Retry';
        retryBtn.addEventListener('click', retryLastMessage);
        messageEl.querySelector('.ai-chat-message-content')?.appendChild(retryBtn);
        return messageId;
    }

    function getQuickActionsForPage() {
        const params = new URLSearchParams(window.location.search);
        const page = params.get('page') || '';
        const collection = params.get('collection') || window.collectionSlug || '';
        const entryId = params.get('entry') || document.querySelector('input[name="entry_id"]')?.value || '';

        if (page === 'entry-editor' && collection) {
            const actions = [
                { label: 'Summarize entry', prompt: 'Summarize this entry in 2-3 sentences.' },
                { label: 'Suggest title', prompt: 'Suggest a clearer, SEO-friendly title for this entry.' },
                { label: 'Fix grammar', prompt: 'Fix grammar and spelling in the main text fields of this entry. List suggested changes before applying.' }
            ];
            if (entryId) {
                actions.push({ label: 'Improve excerpt', prompt: 'Write a short excerpt or meta description for this entry.' });
            } else {
                actions.unshift({ label: 'Draft from outline', prompt: 'Help me draft this new entry. Ask what topic I want, then suggest field content.' });
            }
            return actions;
        }

        if (page === 'entries' && collection) {
            return [
                { label: 'Summarize collection', prompt: 'Summarize the entries in this collection. Highlight themes and gaps.' },
                { label: 'Suggest entries', prompt: 'Suggest 5 new entry ideas for this collection with titles and brief outlines.' },
                { label: 'Find duplicates', prompt: 'Check this collection for possible duplicate or very similar entries.' }
            ];
        }

        return [];
    }

    function renderInChatQuickActions() {
        const container = document.getElementById('ai-chat-quick-actions');
        if (!container) return;
        const actions = getQuickActionsForPage();
        if (actions.length === 0) {
            container.hidden = true;
            container.innerHTML = '';
            return;
        }
        container.hidden = false;
        container.innerHTML = actions.map(function(action) {
            return '<button type="button" class="chip chip--interactive ai-chat-quick-chip" data-prompt="' + escapeHtml(action.prompt) + '">' + escapeHtml(action.label) + '</button>';
        }).join('');
        container.querySelectorAll('.ai-chat-quick-chip').forEach(function(chip) {
            chip.addEventListener('click', async function () {
                runQuickAction(chip.getAttribute('data-prompt') || '');
            });
        });
    }

    function injectQuickActionChips() {
        const params = new URLSearchParams(window.location.search);
        const page = params.get('page') || '';
        if (page !== 'entry-editor') {
            return;
        }

        const actions = getQuickActionsForPage();
        if (actions.length === 0) return;

        const bar = document.querySelector('.main-actions-bar') || document.querySelector('.page-header');
        if (!bar || document.getElementById('ai-page-quick-actions')) {
            return;
        }

        const wrap = document.createElement('div');
        wrap.id = 'ai-page-quick-actions';
        wrap.className = 'ai-page-quick-actions u-cluster u-mt-3';
        wrap.setAttribute('role', 'group');
        wrap.setAttribute('aria-label', 'AI quick actions');
        wrap.innerHTML = actions.map(function(action) {
            return '<button type="button" class="chip chip--interactive ai-chat-quick-chip ai-page-quick-chip" data-prompt="' + escapeHtml(action.prompt) + '">' + escapeHtml(action.label) + '</button>';
        }).join('');

        bar.appendChild(wrap);
        wrap.querySelectorAll('.ai-page-quick-chip').forEach(function(chip) {
            chip.addEventListener('click', async function () {
                runQuickAction(chip.getAttribute('data-prompt') || '');
            });
        });
    }

    async function runQuickAction(prompt) {
        if (!prompt) return;
        await loadConnections();
        await showChatWindow();
        if (!currentConnection && availableConnections.length > 0) {
            currentConnection = availableConnections[0];
            if (!conversationId) {
                conversationId = 'conv_' + Date.now();
            }
            updateConnectionDropdownValue();
            saveChatState();
        }
        if (!currentConnection) {
            showError('Select an AI connection first');
            return;
        }
        const input = document.getElementById('ai-chat-input');
        if (input) {
            input.value = prompt;
        }
        sendChatMessage(prompt);
    }

    // Restore chat state from localStorage and server history when available
    async function restoreChatState() {
        try {
            const savedConnectionId = localStorage.getItem(STORAGE_KEY_CONNECTION);
            const savedConversationId = localStorage.getItem(STORAGE_KEY_CONVERSATION);
            const savedMessages = localStorage.getItem(STORAGE_KEY_MESSAGES);
            
            if (savedConnectionId && availableConnections.find(c => c.id === savedConnectionId)) {
                currentConnection = availableConnections.find(c => c.id === savedConnectionId);
                restoreChatModelForConnection(savedConnectionId);
                conversationId = savedConversationId || 'conv_' + Date.now();
                
                // Prefer server history when conversation id exists
                if (conversationId) {
                    await loadHistoryFromServer(conversationId, savedConnectionId);
                }

                // Fall back to localStorage if server had no messages
                if (chatMessages.length === 0 && savedMessages) {
                    try {
                        chatMessages = JSON.parse(savedMessages);
                    } catch (e) {
                        console.error('Error parsing saved messages:', e);
                        chatMessages = [];
                    }
                }
            } else if (savedConnectionId) {
                clearChatState();
            }

            renderConnectionDropdown();
        } catch (error) {
            console.error('Error restoring chat state:', error);
        }
    }

    async function loadHistoryFromServer(convId, connectionId) {
        try {
            const params = new URLSearchParams({
                action: 'get_history',
                conversation_id: convId,
                ai_connection_id: connectionId
            });
            const response = await fetch(apiUrl('/api/ai_chat.php?' + params.toString()), { credentials: 'same-origin' });
            const result = await response.json();
            if (result.status === 'ok' && Array.isArray(result.data?.messages) && result.data.messages.length > 0) {
                chatMessages = result.data.messages
                    .filter(m => m.role === 'user' || m.role === 'assistant')
                    .map(m => ({ role: m.role, content: m.content }));
            }
        } catch (error) {
            console.error('Error loading chat history from server:', error);
        }
    }

    // Save chat state to localStorage
    function saveChatState() {
        try {
            if (currentConnection) {
                localStorage.setItem(STORAGE_KEY_CONNECTION, currentConnection.id);
            }
            if (conversationId) {
                localStorage.setItem(STORAGE_KEY_CONVERSATION, conversationId);
            }
            localStorage.setItem(STORAGE_KEY_MESSAGES, JSON.stringify(chatMessages));
            indexRecentChat();
        } catch (error) {
            console.error('Error saving chat state:', error);
        }
    }

    function getRecentChats() {
        try {
            const raw = localStorage.getItem(STORAGE_KEY_RECENT);
            return raw ? JSON.parse(raw) : [];
        } catch (e) {
            return [];
        }
    }

    function indexRecentChat() {
        if (!conversationId || !chatMessages.length) return;
        const firstUser = chatMessages.find(m => m.role === 'user');
        const title = firstUser ? String(firstUser.content).slice(0, 72) : 'Chat';
        const entry = {
            id: conversationId,
            title: title,
            connection_id: currentConnection?.id || '',
            connection_name: currentConnection?.name || '',
            updated_at: new Date().toISOString(),
            preview: chatMessages.slice(-2).map(m => m.content).join(' · ').slice(0, 120)
        };
        let recent = getRecentChats().filter(c => c.id !== conversationId);
        recent.unshift(entry);
        recent = recent.slice(0, 20);
        localStorage.setItem(STORAGE_KEY_RECENT, JSON.stringify(recent));
    }

    function renderHistoryPanel() {
        const list = document.getElementById('ai-chat-history-list');
        if (!list) return;
        const recent = getRecentChats();
        if (!recent.length) {
            list.innerHTML = '<div class="ai-chat-welcome"><p>No recent chats yet. Start a conversation to see it here.</p></div>';
            return;
        }
        list.innerHTML = recent.map(function (chat) {
            return `
            <button type="button" class="card card--interactive ai-chat-history-item" data-conv-id="${escapeHtml(chat.id)}" data-conn-id="${escapeHtml(chat.connection_id || '')}">
                <strong>${escapeHtml(chat.title || 'Chat')}</strong>
                <span class="ai-chat-history-meta u-text-2xs u-fg-tertiary">${escapeHtml(chat.connection_name || '')} · ${formatTime(chat.updated_at)}</span>
                <span class="ai-chat-history-preview u-clamp-2 u-text-xs u-fg-secondary">${escapeHtml(chat.preview || '')}</span>
            </button>`;
        }).join('');
        list.querySelectorAll('.ai-chat-history-item').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                const convId = btn.getAttribute('data-conv-id');
                const connId = btn.getAttribute('data-conn-id');
                if (connId && availableConnections.find(c => c.id === connId)) {
                    currentConnection = availableConnections.find(c => c.id === connId);
                    updateConnectionDropdownValue();
                }
                conversationId = convId;
                chatMessages = [];
                await loadHistoryFromServer(convId, currentConnection?.id || connId);
                if (chatMessages.length === 0) {
                    try {
                        const saved = localStorage.getItem(STORAGE_KEY_MESSAGES);
                        if (saved && conversationId === localStorage.getItem(STORAGE_KEY_CONVERSATION)) {
                            chatMessages = JSON.parse(saved);
                        }
                    } catch (e) { /* ignore */ }
                }
                updateChatUI();
                saveChatState();
                switchChatView('messages');
            });
        });
    }

    // Clear chat state
    function clearChatState() {
        localStorage.removeItem(STORAGE_KEY_CONVERSATION);
        localStorage.removeItem(STORAGE_KEY_CONNECTION);
        localStorage.removeItem(STORAGE_KEY_MESSAGES);
        conversationId = null;
        chatMessages = [];
    }

    // Update chat UI with messages
    function updateChatUI() {
        const messagesEl = document.getElementById('ai-chat-messages');
        if (!messagesEl) return;
        
        // Clear existing messages (except welcome)
        const existingMessages = messagesEl.querySelectorAll('.ai-chat-message');
        existingMessages.forEach(msg => msg.remove());
        
        // Remove welcome if we have messages
        if (chatMessages.length > 0) {
            const welcome = messagesEl.querySelector('.ai-chat-welcome');
            if (welcome) welcome.remove();
        }
        
        // Add all messages
        chatMessages.forEach(msg => {
            addChatMessage(msg.role, msg.content, false, false); // false = not loading, false = don't save (already saved)
        });
    }

    // Render markdown to HTML (simple implementation)
    function renderMarkdown(text) {
        if (!text) return '';
        
        // Escape HTML first to prevent XSS, then process markdown
        let html = escapeHtml(text);
        
        // Process code blocks (```json ... ``` or ``` ... ```)
        html = html.replace(/```(\w+)?\n([\s\S]*?)```/g, (match, lang, code) => {
            const language = lang || 'text';
            const escapedCode = code.trim();
            return `<pre class="ai-chat-code-block"><code class="language-${language}">${escapedCode}</code></pre>`;
        });
        
        // Process inline code (`code`)
        html = html.replace(/`([^`]+)`/g, '<code class="ai-chat-inline-code">$1</code>');
        
        // Process tables (| col1 | col2 |)
        html = html.replace(/\|(.+)\|\n\|([-|: ]+)\|\n((?:\|.+\|\n?)+)/g, (match, header, separator, rows) => {
            const headers = header.split('|').map(h => h.trim()).filter(h => h);
            const rowLines = rows.trim().split('\n');
            
            let tableHtml = '<table class="ai-chat-table"><thead><tr>';
            headers.forEach(h => {
                tableHtml += `<th>${h}</th>`;
            });
            tableHtml += '</tr></thead><tbody>';
            
            rowLines.forEach(row => {
                const cells = row.split('|').map(c => c.trim()).filter(c => c);
                if (cells.length > 0) {
                    tableHtml += '<tr>';
                    cells.forEach(cell => {
                        tableHtml += `<td>${cell}</td>`;
                    });
                    tableHtml += '</tr>';
                }
            });
            
            tableHtml += '</tbody></table>';
            return tableHtml;
        });
        
        // Process bold (**text**)
        html = html.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        
        // Process italic (*text*)
        html = html.replace(/\*([^*]+)\*/g, '<em>$1</em>');
        
        // Process line breaks (double newline = paragraph, single = <br>)
        html = html.split('\n\n').map(para => {
            if (para.trim()) {
                // Check if it's already a block element (table, pre, etc.)
                if (para.trim().startsWith('<table') || para.trim().startsWith('<pre')) {
                    return para;
                }
                return `<p>${para.replace(/\n/g, '<br>')}</p>`;
            }
            return '';
        }).join('');
        
        return html;
    }
    
    // Add approval card to chat
    function addApprovalCard(approval) {
        const messages = document.getElementById('ai-chat-messages');
        if (!messages) return;
        
        // Remove welcome message if exists
        const welcome = messages.querySelector('.ai-chat-welcome');
        if (welcome) welcome.remove();
        
        const cardId = 'approval-' + approval.id;
        const cardDiv = document.createElement('div');
        cardDiv.className = 'ai-chat-message ai-chat-message-approval';
        cardDiv.id = cardId;
        
        const actionType = approval.action_type || 'unknown';
        const actionTypeLabel = actionType.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        const description = approval.description || 'No description';
        const status = approval.status || 'pending';
        const statusLabel = status.charAt(0).toUpperCase() + status.slice(1);
        const statusClass = 'ai-chat-approval-status-' + status;
        
        // Format action data for display
        let actionDataHtml = '';
        if (approval.action_data) {
            const data = approval.action_data;
            if (data.name) actionDataHtml += `<div><strong>Name:</strong> ${escapeHtml(data.name)}</div>`;
            if (data.slug) actionDataHtml += `<div><strong>Slug:</strong> ${escapeHtml(data.slug)}</div>`;
            if (data.collection) actionDataHtml += `<div><strong>Collection:</strong> ${escapeHtml(data.collection)}</div>`;
            if (data.title) actionDataHtml += `<div><strong>Title:</strong> ${escapeHtml(data.title)}</div>`;
            if (data.fields && Array.isArray(data.fields)) {
                actionDataHtml += `<div><strong>Fields:</strong> ${data.fields.length} field(s)</div>`;
            }
        }
        
        cardDiv.innerHTML = `
            <div class="card ai-chat-approval-card">
                <div class="ai-chat-approval-header u-split">
                    <div class="ai-chat-approval-type">${escapeHtml(actionTypeLabel)}</div>
                    <div class="ai-chat-approval-meta">
                        <span class="badge ai-chat-approval-status-pill ${statusClass}">${escapeHtml(statusLabel)}</span>
                        <span class="ai-chat-approval-time u-text-xs u-fg-tertiary">${formatTime(approval.created_at)}</span>
                    </div>
                </div>
                <div class="ai-chat-approval-description">${escapeHtml(description)}</div>
                ${actionDataHtml ? `<div class="ai-chat-approval-data">${actionDataHtml}</div>` : ''}
                <div class="ai-chat-approval-actions u-row-2 u-mt-3">
                    ${status === 'pending' ? `
                    <button type="button" class="btn btn-sm btn-success" data-ai-approve="${escapeHtml(approval.id)}">
                        ✓ Approve
                    </button>
                    <button type="button" class="btn btn-sm btn-danger" data-ai-reject="${escapeHtml(approval.id)}">
                        ✗ Reject
                    </button>
                    ` : `<div class="ai-chat-approval-status ${statusClass}">${status === 'approved' ? '✓ Approved' : status === 'rejected' ? '✗ Rejected' : escapeHtml(statusLabel)}</div>`}
                </div>
            </div>
        `;
        
        messages.appendChild(cardDiv);
        messages.scrollTop = messages.scrollHeight;
    }
    
    // Approve action from chat
    async function approveAction(approvalId) {
        if (!(await BirklyFeedback.confirm('Approve this action?'))) return;
        
        try {
            const response = await fetch(apiUrl('/api/ai_approvals.php'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    action: 'approve',
                    id: approvalId,
                    _csrf: csrfToken
                })
            });
            
            const result = await response.json();
            
            if (result.status === 'ok') {
                appendConsoleLog('success', 'Action approved and executed successfully.', result.data || null);
                const card = document.getElementById('approval-' + approvalId) || document.getElementById('queue-approval-' + approvalId);
                if (card) {
                    card.classList.add('ai-chat-approval-approved');
                    const actionsDiv = card.querySelector('.ai-chat-approval-actions');
                    if (actionsDiv) {
                        actionsDiv.innerHTML = '<div class="ai-chat-approval-status approved">✓ Approved and executed</div>';
                    }
                }
                addChatMessage('system', 'Action approved and executed successfully.');
                loadApprovalQueue();
            } else {
                appendConsoleLog('error', 'Failed to approve action', result);
                addChatMessage('error', 'Failed to approve: ' + (result.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error approving action:', error);
            appendConsoleLog('error', 'Error approving action: ' + error.message);
            addChatMessage('error', 'Error approving action');
        }
    }
    
    // Reject action from chat
    async function rejectAction(approvalId) {
        const reason = await BirklyFeedback.prompt('', '', {
            title: 'Reject action',
            placeholder: 'Reason (optional)',
            confirmLabel: 'Reject',
        });
        
        try {
            const response = await fetch(apiUrl('/api/ai_approvals.php'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    action: 'reject',
                    id: approvalId,
                    reason: reason || 'No reason provided',
                    _csrf: csrfToken
                })
            });
            
            const result = await response.json();
            
            if (result.status === 'ok') {
                const card = document.getElementById('approval-' + approvalId) || document.getElementById('queue-approval-' + approvalId);
                if (card) {
                    card.classList.add('ai-chat-approval-rejected');
                    const actionsDiv = card.querySelector('.ai-chat-approval-actions');
                    if (actionsDiv) {
                        actionsDiv.innerHTML = '<div class="ai-chat-approval-status rejected">✗ Rejected</div>';
                    }
                }
                addChatMessage('system', 'Action rejected.');
                loadApprovalQueue();
            } else {
                appendConsoleLog('error', 'Failed to reject action', result);
                addChatMessage('error', 'Failed to reject: ' + (result.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error rejecting action:', error);
            appendConsoleLog('error', 'Error rejecting action: ' + error.message);
            addChatMessage('error', 'Error rejecting action');
        }
    }
    
    // Format time helper
    function formatTime(isoString) {
        if (!isoString) return '';
        const date = new Date(isoString);
        return date.toLocaleString();
    }

    // Add chat message
    function addChatMessage(role, content, isLoading = false, saveToStorage = true) {
        const messages = document.getElementById('ai-chat-messages');
        if (!messages) return null;
        
        // Remove welcome message if exists
        const welcome = messages.querySelector('.ai-chat-welcome');
        if (welcome) welcome.remove();
        
        const messageId = nextChatMessageId();
        const messageDiv = document.createElement('div');
        messageDiv.className = `ai-chat-message ai-chat-message-${role}`;
        messageDiv.id = messageId;
        messageDiv.dataset.chatRole = role;
        
        // Render markdown for assistant messages, plain text for user messages
        const renderedContent = isLoading 
            ? '<span class="spinner" role="status" aria-label="Loading"></span>' 
            : (role === 'assistant'
                ? renderMarkdown(sanitizeChatDisplayText(content))
                : (role === 'error'
                    ? escapeHtml(content).split('\n').join('<br>')
                    : escapeHtml(content)));
        
        messageDiv.innerHTML = `
            <div class="ai-chat-message-content">
                ${renderedContent}
            </div>
        `;
        
        messages.appendChild(messageDiv);
        messages.scrollTop = messages.scrollHeight;
        
        // Save to storage if not loading and saveToStorage is true
        if (!isLoading && saveToStorage && role !== 'system') {
            chatMessages.push({ role, content });
            saveChatState();
        }
        
        return messageId;
    }

    // Remove chat message
    function removeChatMessage(messageId) {
        const message = document.getElementById(messageId);
        if (message) message.remove();
    }

    // Toggle fullscreen
    function toggleFullscreen() {
        const chatWindow = document.getElementById('ai-chat-window');
        if (chatWindow) {
            chatWindow.classList.toggle('ai-chat-fullscreen');
            const fullscreenBtn = document.getElementById('ai-chat-fullscreen-btn');
            if (fullscreenBtn) {
                const isFullscreen = chatWindow.classList.contains('ai-chat-fullscreen');
                fullscreenBtn.innerHTML = isFullscreen
                    ? icon('arrows-in', { size: 16 })
                    : icon('arrows-out', { size: 16 });
                fullscreenBtn.title = isFullscreen ? 'Exit fullscreen' : 'Enter fullscreen';
            }
        }
    }

    // Utility functions
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function showError(message) {
        addChatMessage('error', message);
    }

    // Export functions
    window.aiChatButton = {
        selectConnection,
        toggleChatWindow,
        approveAction,
        rejectAction,
        switchChatView,
        loadApprovalQueue,
        runQuickAction,
        collectPageContext,
        cancelChatRequest,
        retryLastMessage
    };

})();

