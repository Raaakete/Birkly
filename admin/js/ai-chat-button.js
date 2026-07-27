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
        return 'HTTP Error ' + status + ': ' + message;
    }

    // Storage keys
    const STORAGE_KEY_CONVERSATION = 'ai_chat_conversation_id';
    const STORAGE_KEY_CONNECTION = 'ai_chat_connection_id';
    const STORAGE_KEY_MESSAGES = 'ai_chat_messages';
    const STORAGE_KEY_RECENT = 'ai_chat_recent_chats';
    const STORAGE_KEY_CONSOLE = 'ai_chat_console_logs';
    const CONSOLE_LOG_LIMIT = 200;

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        loadConsoleLogs();
        loadConnections().then(() => {
            restoreChatState();
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
        wrap.className = 'assist-fab-wrap';

        const menu = document.createElement('div');
        menu.className = 'assist-fab-menu';
        menu.setAttribute('role', 'menu');
        menu.innerHTML = `
            <button type="button" class="assist-fab-action" id="assist-fab-ai" role="menuitem" title="AI Assistant" aria-label="AI Assistant">
                ${icon('sparkle', { size: 20 })}
                <span>AI Chat</span>
            </button>
            <button type="button" class="assist-fab-action" id="assist-fab-feedback" role="menuitem" title="Send feedback" aria-label="Send feedback">
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
        button.innerHTML = `
            ${icon('sparkle', { size: 22, class: 'assist-fab-icon assist-fab-icon-menu' })}
            ${icon('x', { size: 22, class: 'assist-fab-icon assist-fab-icon-close', ariaHidden: true })}
        `;

        const badge = document.createElement('span');
        badge.id = 'ai-chat-approval-badge';
        badge.className = 'ai-chat-fab-badge';
        badge.style.display = 'none';
        badge.setAttribute('aria-hidden', 'true');
        button.appendChild(badge);

        wrap.appendChild(menu);
        wrap.appendChild(button);
        document.body.appendChild(wrap);

        function setMenuOpen(open) {
            wrap.classList.toggle('open', open);
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        button.addEventListener('click', async function (e) {
            e.stopPropagation();
            setMenuOpen(!wrap.classList.contains('open'));
        });

        document.getElementById('assist-fab-ai').addEventListener('click', async function (e) {
            e.stopPropagation();
            setMenuOpen(false);
            toggleChatWindow();
        });

        document.getElementById('assist-fab-feedback').addEventListener('click', async function (e) {
            e.stopPropagation();
            setMenuOpen(false);
            if (window.Birkly && typeof window.Birkly.openFeedbackModal === 'function') {
                window.Birkly.openFeedbackModal();
            } else {
                document.getElementById('feedback-floating-btn')?.click();
            }
        });

        document.addEventListener('click', async function (e) {
            if (!wrap.contains(e.target)) {
                setMenuOpen(false);
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                setMenuOpen(false);
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
            badge.style.display = 'flex';
            badge.setAttribute('aria-hidden', 'false');
            if (button) button.setAttribute('aria-label', `AI Assistant, ${count} pending approval${count === 1 ? '' : 's'}`);
        } else {
            badge.textContent = '';
            badge.style.display = 'none';
            badge.setAttribute('aria-hidden', 'true');
            if (button) button.setAttribute('aria-label', 'AI Assistant');
        }
        const tabBadge = document.getElementById('ai-chat-approvals-tab-badge');
        if (tabBadge) {
            tabBadge.textContent = count > 0 ? String(count) : '';
            tabBadge.style.display = count > 0 ? 'inline-flex' : 'none';
        }
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
            <div class="ai-chat-approval-card ai-chat-queue-item" id="queue-approval-${escapeHtml(approval.id)}">
                <div class="ai-chat-approval-header">
                    <div class="ai-chat-approval-type">${escapeHtml(actionType)}</div>
                    <span class="ai-chat-approval-time">${formatTime(approval.created_at)}</span>
                </div>
                <div class="ai-chat-approval-description">${escapeHtml(approval.description || 'No description')}</div>
                <div class="ai-chat-approval-actions">
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
        if ((level === 'error' || level === 'warn') && chatView !== 'console') {
            consoleUnread++;
        }
        saveConsoleLogs();
        updateConsoleBadge();
        if (chatView === 'console') {
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
        const badge = document.getElementById('ai-chat-console-tab-badge');
        if (!badge) return;
        if (consoleUnread > 0) {
            badge.textContent = consoleUnread > 99 ? '99+' : String(consoleUnread);
            badge.style.display = 'inline-flex';
        } else {
            badge.style.display = 'none';
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
            return `
                <div class="ai-chat-console-entry ai-chat-console-entry--${escapeHtml(level)}">
                    <div class="ai-chat-console-entry-head">
                        <span class="ai-chat-console-level">${escapeHtml(levelLabel)}</span>
                        <time class="ai-chat-console-time">${escapeHtml(formatTime(entry.time))}</time>
                    </div>
                    <div class="ai-chat-console-message">${escapeHtml(entry.message || '')}</div>
                    ${detailsHtml}
                </div>`;
        }).join('');
    }

    function switchChatView(view) {
        chatView = view;
        const messagesPanel = document.getElementById('ai-chat-messages-panel');
        const approvalsPanel = document.getElementById('ai-chat-approvals-panel');
        const historyPanel = document.getElementById('ai-chat-history-panel');
        const consolePanel = document.getElementById('ai-chat-console-panel');
        const inputContainer = document.querySelector('.ai-chat-input-container');
        const tabMessages = document.getElementById('ai-chat-tab-messages');
        const tabApprovals = document.getElementById('ai-chat-tab-approvals');
        const tabHistory = document.getElementById('ai-chat-tab-history');
        const tabConsole = document.getElementById('ai-chat-tab-console');

        if (messagesPanel) messagesPanel.style.display = view === 'messages' ? 'flex' : 'none';
        if (approvalsPanel) approvalsPanel.style.display = view === 'approvals' ? 'block' : 'none';
        if (historyPanel) historyPanel.style.display = view === 'history' ? 'block' : 'none';
        if (consolePanel) consolePanel.style.display = view === 'console' ? 'block' : 'none';
        if (inputContainer) inputContainer.style.display = view === 'messages' ? 'block' : 'none';
        if (tabMessages) {
            tabMessages.classList.toggle('active', view === 'messages');
            tabMessages.setAttribute('aria-selected', view === 'messages' ? 'true' : 'false');
        }
        if (tabApprovals) {
            tabApprovals.classList.toggle('active', view === 'approvals');
            tabApprovals.setAttribute('aria-selected', view === 'approvals' ? 'true' : 'false');
        }
        if (tabHistory) {
            tabHistory.classList.toggle('active', view === 'history');
            tabHistory.setAttribute('aria-selected', view === 'history' ? 'true' : 'false');
        }
        if (tabConsole) {
            tabConsole.classList.toggle('active', view === 'console');
            tabConsole.setAttribute('aria-selected', view === 'console' ? 'true' : 'false');
        }
        if (view === 'approvals') renderApprovalQueuePanel();
        if (view === 'history') renderHistoryPanel();
        if (view === 'console') {
            consoleUnread = 0;
            updateConsoleBadge();
            renderConsolePanel();
        }
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

    // Toggle chat window
    function toggleChatWindow() {
        const chatWindow = document.getElementById('ai-chat-window');
        
        if (chatWindow) {
            // Toggle visibility
            if (chatWindow.style.display === 'none' || !chatWindow.style.display) {
                showChatWindow();
            } else {
                hideChatWindow();
            }
        } else {
            createChatWindow();
            showChatWindow();
            addNewChatButton();
            // Restore state if available
            if (currentConnection && chatMessages.length > 0) {
                updateChatUI();
            }
        }
    }

    // Create chat window
    function createChatWindow() {
        const chatWindow = document.createElement('div');
        chatWindow.id = 'ai-chat-window';
        chatWindow.className = 'ai-chat-window';
        chatWindow.innerHTML = `
            <div class="ai-chat-header">
                <div class="ai-chat-header-info">
                    <h3>AI Assistant</h3>
                    <div class="ai-chat-connection-picker">
                        <label for="ai-chat-connection-select" class="sr-only">AI connection</label>
                        <select id="ai-chat-connection-select" class="ai-chat-connection-select" title="Choose AI connection">
                            <option value="">Loading connections...</option>
                        </select>
                    </div>
                </div>
                <div class="ai-chat-header-actions">
                    <button class="btn-icon" id="ai-chat-fullscreen-btn" title="Toggle fullscreen">
                        ${icon('arrows-out', { size: 16 })}
                    </button>
                    <button class="btn-icon" id="ai-chat-close-btn" title="Close chat">
                        ${icon('x', { size: 16 })}
                    </button>
                </div>
            </div>
            <div class="ai-chat-body">
                <div class="ai-chat-view-tabs" role="tablist">
                    <button type="button" class="ai-chat-view-tab active" id="ai-chat-tab-messages" role="tab" aria-selected="true">Chat</button>
                    <button type="button" class="ai-chat-view-tab" id="ai-chat-tab-history" role="tab" aria-selected="false">History</button>
                    <button type="button" class="ai-chat-view-tab" id="ai-chat-tab-console" role="tab" aria-selected="false">
                        Console <span class="ai-chat-tab-badge" id="ai-chat-console-tab-badge" style="display: none;"></span>
                    </button>
                    <button type="button" class="ai-chat-view-tab" id="ai-chat-tab-approvals" role="tab" aria-selected="false">
                        Approvals <span class="ai-chat-tab-badge" id="ai-chat-approvals-tab-badge" style="display: none;"></span>
                    </button>
                </div>
                <div class="ai-chat-messages-panel" id="ai-chat-messages-panel">
                    <div class="ai-chat-messages" id="ai-chat-messages">
                        <div class="ai-chat-welcome">
                            <p>Select an AI connection to start chatting. The AI can help you create and edit content.</p>
                        </div>
                    </div>
                </div>
                <div class="ai-chat-history-panel" id="ai-chat-history-panel" style="display: none;">
                    <div class="ai-chat-history-list" id="ai-chat-history-list"></div>
                </div>
                <div class="ai-chat-console-panel" id="ai-chat-console-panel" style="display: none;">
                    <div class="ai-chat-console-toolbar">
                        <span class="ai-chat-console-hint">Action results and errors from this session</span>
                        <button type="button" class="btn btn-sm btn-secondary" id="ai-chat-console-clear">Clear</button>
                    </div>
                    <div class="ai-chat-console-list" id="ai-chat-console-list"></div>
                </div>
                <div class="ai-chat-approvals-panel" id="ai-chat-approvals-panel" style="display: none;">
                    <div class="loading">Loading approvals...</div>
                </div>
                <div class="ai-chat-input-container">
                    <div class="ai-chat-quick-actions" id="ai-chat-quick-actions" hidden></div>
                    <div class="ai-chat-input-wrapper">
                        <textarea 
                            id="ai-chat-input" 
                            placeholder="Ask the AI to create or edit content..."
                            rows="2"
                        ></textarea>
                        <button class="btn btn-primary" id="ai-chat-send-btn" type="button" aria-label="Send message">
                            <span class="ai-chat-send-icon">${icon('paper-plane-tilt', { size: 16 })}</span>
                            <span class="ai-chat-cancel-icon" style="display:none;">${icon('stop', { size: 16 })}</span>
                        </button>
                    </div>
                </div>
            </div>
            <div class="ai-chat-connection-selector" id="ai-chat-connection-selector" style="display: none;" aria-hidden="true">
                <div class="ai-chat-connection-list" id="ai-chat-connection-list">
                    <div class="loading">Loading connections...</div>
                </div>
            </div>
        `;
        document.body.appendChild(chatWindow);
        bindChatWindowActions(chatWindow);
        
        // Setup event listeners
        document.getElementById('ai-chat-close-btn')?.addEventListener('click', hideChatWindow);
        document.getElementById('ai-chat-fullscreen-btn')?.addEventListener('click', toggleFullscreen);
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

        document.getElementById('ai-chat-tab-messages')?.addEventListener('click', async () => switchChatView('messages'));
        document.getElementById('ai-chat-tab-history')?.addEventListener('click', async () => switchChatView('history'));
        document.getElementById('ai-chat-tab-console')?.addEventListener('click', async () => switchChatView('console'));
        document.getElementById('ai-chat-tab-approvals')?.addEventListener('click', async () => switchChatView('approvals'));
        document.getElementById('ai-chat-console-clear')?.addEventListener('click', clearConsoleLogs);

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
        
        // Add new chat button
        addNewChatButton();
        
        // Load connections into header dropdown
        renderConnectionDropdown();
        loadApprovalQueue();
        updateApprovalBadge();
        updateConsoleBadge();
        
        // Restore chat state if available
        if (currentConnection && chatMessages.length > 0) {
            updateChatUI();
        }
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
        renderInChatQuickActions();

        if (availableConnections.length === 0) {
            showNoConnectionMessage();
        } else if (!currentConnection) {
            selectConnection(availableConnections[0].id);
        } else {
            updateConnectionDropdownValue();
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
            const meta = `${conn.provider} • ${conn.role || 'admin'}`;
            return `<option value="${conn.id}">${escapeHtml(label)} (${escapeHtml(meta)})</option>`;
        }).join('');

        updateConnectionDropdownValue();
    }

    // Hide chat window
    function hideChatWindow() {
        const chatWindow = document.getElementById('ai-chat-window');
        if (chatWindow) {
            chatWindow.style.display = 'none';
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
        
        const inputContainer = document.querySelector('.ai-chat-input-container');
        if (inputContainer) {
            inputContainer.style.display = 'none';
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
            clearChatState();
        }
        
        // Load connection details
        fetch(apiUrl('/api/ai_connections.php?action=get&id=' + encodeURIComponent(connectionId)), { credentials: 'same-origin' })
            .then(r => r.json())
            .then(result => {
                if (result.status === 'ok') {
                    currentConnection = result.data.connection;
                    if (!conversationId) {
                        conversationId = 'conv_' + Date.now();
                    }
                    
                    updateConnectionDropdownValue();
                    
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
                    
                    // Show input
                    const inputContainer = document.querySelector('.ai-chat-input-container');
                    if (inputContainer) {
                        inputContainer.style.display = 'block';
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
    
    // Add "New Chat" button functionality
    function addNewChatButton() {
        const headerActions = document.querySelector('.ai-chat-header-actions');
        if (headerActions && !document.getElementById('ai-chat-new-btn')) {
            const newBtn = document.createElement('button');
            newBtn.id = 'ai-chat-new-btn';
            newBtn.className = 'btn-icon';
            newBtn.title = 'New Chat';
            newBtn.innerHTML = icon('plus', { size: 16 });
            newBtn.addEventListener('click', async function () {
                if (chatMessages.length > 0) {
                    indexRecentChat();
                }
                if (await BirklyFeedback.confirm('Start a new chat? This will clear the current conversation.')) {
                    clearChatState();
                    const messages = document.getElementById('ai-chat-messages');
                    if (messages) {
                        messages.innerHTML = '<div class="ai-chat-welcome"><p>New chat started. Ask the AI to help you create or edit content.</p></div>';
                    }
                    conversationId = 'conv_' + Date.now();
                    saveChatState();
                }
            });
            // Insert before the close button, or append if close button doesn't exist
            const closeBtn = document.getElementById('ai-chat-close-btn');
            if (closeBtn && closeBtn.parentNode === headerActions) {
                headerActions.insertBefore(newBtn, closeBtn);
            } else {
                headerActions.appendChild(newBtn);
            }
        }
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
        if (sendIcon) sendIcon.style.display = streaming ? 'none' : '';
        if (cancelIcon) cancelIcon.style.display = streaming ? '' : 'none';
    }

    function cancelChatRequest() {
        if (activeChatAbortController) {
            activeChatAbortController.abort();
            activeChatAbortController = null;
        }
        isChatRequestInFlight = false;
        setSendButtonStreaming(false);
        addChatMessage('system', 'Request cancelled.');
    }

    function updateStreamingMessage(messageId, content, isComplete) {
        const messageEl = document.getElementById(messageId);
        if (!messageEl) return;
        const contentEl = messageEl.querySelector('.ai-chat-message-content');
        if (!contentEl) return;
        contentEl.innerHTML = renderMarkdown(content);
        if (isComplete) {
            messageEl.classList.remove('ai-chat-message-streaming');
        }
        const messages = document.getElementById('ai-chat-messages');
        if (messages) {
            messages.scrollTop = messages.scrollHeight;
        }
    }

    function handleChatApiResult(result, assistantMessageId) {
        if (!result || (result.status !== 'ok' && result.status !== 'partial')) {
            const errorMsg = result?.message || result?.error || result?.data?.message || 'Unknown error';
            appendConsoleLog('error', errorMsg, result);
            if (assistantMessageId) {
                removeChatMessage(assistantMessageId);
            }
            addChatMessageWithRetry('error', 'Error: ' + errorMsg);
            return;
        }

        const responseText = result.data.response || result.data.message || '';
        if (assistantMessageId) {
            const messageEl = document.getElementById(assistantMessageId);
            const contentEl = messageEl ? messageEl.querySelector('.ai-chat-message-content') : null;
            const streamedText = contentEl ? contentEl.textContent.trim() : '';
            const finalText = responseText || (streamedText && streamedText !== 'Thinking...' ? streamedText : '');
            if (finalText) {
                updateStreamingMessage(assistantMessageId, finalText, true);
                chatMessages.push({ role: 'assistant', content: finalText });
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
                const actionLabel = actionResult.action_type ? actionResult.action_type + ' — ' : '';
                appendConsoleLog(
                    'error',
                    actionLabel + (actionResult.message || ('Action ' + (index + 1) + ' failed')),
                    actionResult
                );
            });
            executionResults.filter(function(r) { return r.success; }).forEach(function(actionResult, index) {
                const actionLabel = actionResult.action_type ? actionResult.action_type + ' — ' : '';
                appendConsoleLog(
                    'success',
                    actionLabel + (actionResult.message || ('Action ' + (index + 1) + ' succeeded')),
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
                    const actionLabel = actionResult.action_type ? actionResult.action_type + ' — ' : '';
                    appendConsoleLog(
                        'success',
                        actionLabel + (actionResult.message || ('Action ' + (index + 1) + ' succeeded')),
                        actionResult.data || actionResult
                    );
                });
                addChatMessage('system', successCount + ' action(s) executed successfully.');
            }
        }
    }

    async function parseSseChatResponse(response, assistantMessageId) {
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
                    streamedContent += payload.content;
                    updateStreamingMessage(assistantMessageId, streamedContent, false);
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
        const message = (typeof messageOverride === 'string' ? messageOverride : input?.value || '').trim();
        if (!message) return;

        lastFailedMessage = null;
        addChatMessage('user', message);
        if (input && typeof messageOverride !== 'string') {
            input.value = '';
        }

        const assistantMessageId = addChatMessage('assistant', '', true);
        const messageEl = document.getElementById(assistantMessageId);
        if (messageEl) {
            messageEl.classList.add('ai-chat-message-streaming');
            const contentEl = messageEl.querySelector('.ai-chat-message-content');
            if (contentEl) {
                contentEl.innerHTML = '<div class="ai-chat-loading">Thinking...</div>';
            }
        }

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
                    conversation_id: conversationId,
                    page_context: collectPageContext(),
                    stream: true,
                    _csrf: csrfToken
                })
            });

            if (!response.ok) {
                const errorText = await response.text();
                removeChatMessage(assistantMessageId);
                lastFailedMessage = message;
                appendConsoleLog('error', formatHttpError(response.status, errorText), { status: response.status, body: errorText });
                addChatMessageWithRetry('error', formatHttpError(response.status, errorText));
                return;
            }

            const contentType = (response.headers.get('content-type') || '').toLowerCase();
            let result;

            if (contentType.includes('text/event-stream')) {
                result = await parseSseChatResponse(response, assistantMessageId);
                if (!result) {
                    removeChatMessage(assistantMessageId);
                    lastFailedMessage = message;
                    appendConsoleLog('error', 'Stream ended without a response');
                    addChatMessageWithRetry('error', 'Stream ended without a response.');
                    return;
                }
            } else {
                const responseText = await response.text();
                try {
                    result = JSON.parse(responseText);
                } catch (parseError) {
                    removeChatMessage(assistantMessageId);
                    lastFailedMessage = message;
                    appendConsoleLog('error', 'Invalid response from server', parseError.message);
                    addChatMessageWithRetry('error', 'Invalid response from server.');
                    return;
                }
            }

            handleChatApiResult(result, assistantMessageId);
        } catch (error) {
            if (error.name === 'AbortError') {
                removeChatMessage(assistantMessageId);
                return;
            }
            console.error('Error sending message:', error);
            appendConsoleLog('error', 'Error sending message: ' + error.message, { name: error.name });
            removeChatMessage(assistantMessageId);
            lastFailedMessage = message;
            addChatMessageWithRetry('error', 'Error sending message: ' + error.message);
        } finally {
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
        retryBtn.className = 'ai-chat-retry-btn';
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
            return '<button type="button" class="ai-chat-quick-chip" data-prompt="' + escapeHtml(action.prompt) + '">' + escapeHtml(action.label) + '</button>';
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
        wrap.className = 'ai-page-quick-actions';
        wrap.setAttribute('role', 'group');
        wrap.setAttribute('aria-label', 'AI quick actions');
        wrap.innerHTML = actions.map(function(action) {
            return '<button type="button" class="ai-chat-quick-chip ai-page-quick-chip" data-prompt="' + escapeHtml(action.prompt) + '">' + escapeHtml(action.label) + '</button>';
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
            list.innerHTML = '<div class="ai-chat-welcome"><p>No recent chats yet. Start a conversation in the Chat tab.</p></div>';
            return;
        }
        list.innerHTML = recent.map(function (chat) {
            return `
            <button type="button" class="ai-chat-history-item" data-conv-id="${escapeHtml(chat.id)}" data-conn-id="${escapeHtml(chat.connection_id || '')}">
                <strong>${escapeHtml(chat.title || 'Chat')}</strong>
                <span class="ai-chat-history-meta">${escapeHtml(chat.connection_name || '')} · ${formatTime(chat.updated_at)}</span>
                <span class="ai-chat-history-preview">${escapeHtml(chat.preview || '')}</span>
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
            <div class="ai-chat-approval-card">
                <div class="ai-chat-approval-header">
                    <div class="ai-chat-approval-type">${escapeHtml(actionTypeLabel)}</div>
                    <div class="ai-chat-approval-meta">
                        <span class="ai-chat-approval-status-pill ${statusClass}">${escapeHtml(statusLabel)}</span>
                        <span class="ai-chat-approval-time">${formatTime(approval.created_at)}</span>
                    </div>
                </div>
                <div class="ai-chat-approval-description">${escapeHtml(description)}</div>
                ${actionDataHtml ? `<div class="ai-chat-approval-data">${actionDataHtml}</div>` : ''}
                <div class="ai-chat-approval-actions">
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
        
        const messageId = 'msg_' + Date.now();
        const messageDiv = document.createElement('div');
        messageDiv.className = `ai-chat-message ai-chat-message-${role}`;
        messageDiv.id = messageId;
        
        // Render markdown for assistant messages, plain text for user messages
        const renderedContent = isLoading 
            ? '<div class="ai-chat-loading">...</div>' 
            : (role === 'assistant' ? renderMarkdown(content) : escapeHtml(content));
        
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

