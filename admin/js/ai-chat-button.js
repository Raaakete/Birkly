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

    // Storage keys
    const STORAGE_KEY_CONVERSATION = 'ai_chat_conversation_id';
    const STORAGE_KEY_CONNECTION = 'ai_chat_connection_id';
    const STORAGE_KEY_MESSAGES = 'ai_chat_messages';

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        loadConnections().then(() => {
            restoreChatState();
        });
        createFloatingButton();

        window.addEventListener('birkly:ai-connection-changed', function() {
            loadConnections().then(() => {
                validateCurrentConnection();
                renderConnectionDropdown();
            });
        });
    });

    // Load available AI connections
    async function loadConnections() {
        try {
            const response = await fetch('/api/ai_connections.php?action=list');
            const result = await response.json();
            
            if (result && result.status === 'ok' && result.data) {
                // Filter out MCP connections (they don't support chat)
                availableConnections = (result.data.connections || []).filter(conn => conn.provider !== 'mcp');
                
                // Return promise for chaining
                return Promise.resolve();
            }
        } catch (error) {
            console.error('Error loading AI connections:', error);
            return Promise.reject(error);
        }
    }

    // Create floating AI button
    function createFloatingButton() {
        const button = document.createElement('button');
        button.id = 'ai-chat-floating-btn';
        button.className = 'ai-chat-floating-btn';
        button.innerHTML = `
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                <path d="M2 17l10 5 10-5"></path>
                <path d="M2 12l10 5 10-5"></path>
            </svg>
        `;
        button.title = 'AI Assistant';
        button.addEventListener('click', toggleChatWindow);
        document.body.appendChild(button);
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
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"></path>
                        </svg>
                    </button>
                    <button class="btn-icon" id="ai-chat-close-btn" title="Close chat">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
            </div>
            <div class="ai-chat-body">
                <div class="ai-chat-messages" id="ai-chat-messages">
                    <div class="ai-chat-welcome">
                        <p>Select an AI connection to start chatting. The AI can help you create and edit content.</p>
                    </div>
                </div>
                <div class="ai-chat-input-container">
                    <div class="ai-chat-input-wrapper">
                        <textarea 
                            id="ai-chat-input" 
                            placeholder="Ask the AI to create or edit content..."
                            rows="2"
                        ></textarea>
                        <button class="btn btn-primary" id="ai-chat-send-btn">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="22" y1="2" x2="11" y2="13"></line>
                                <polyline points="22 2 15 22 11 13 2 9 22 2"></polyline>
                            </svg>
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
        
        // Setup event listeners
        document.getElementById('ai-chat-close-btn')?.addEventListener('click', hideChatWindow);
        document.getElementById('ai-chat-fullscreen-btn')?.addEventListener('click', toggleFullscreen);
        document.getElementById('ai-chat-send-btn')?.addEventListener('click', sendChatMessage);
        document.getElementById('ai-chat-input')?.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendChatMessage();
            }
        });

        document.getElementById('ai-chat-connection-select')?.addEventListener('change', function(e) {
            const connectionId = e.target.value;
            if (!connectionId) return;
            if (currentConnection && currentConnection.id === connectionId) return;
            if (chatMessages.length > 0 && !confirm('Switch AI connection? This starts a new chat.')) {
                e.target.value = currentConnection?.id || '';
                return;
            }
            selectConnection(connectionId);
        });
        
        // Add new chat button
        addNewChatButton();
        
        // Load connections into header dropdown
        renderConnectionDropdown();
        
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
                    <button class="btn btn-primary" onclick="window.location.href = Birkly.url('/admin/index.php?page=settings#ai-connections')">
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
        fetch(`/api/ai_connections.php?action=get&id=${connectionId}`)
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
            newBtn.innerHTML = `
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
            `;
            newBtn.addEventListener('click', function() {
                if (confirm('Start a new chat? This will clear the current conversation.')) {
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

    // Send chat message
    async function sendChatMessage() {
        if (!currentConnection) {
            showError('No connection selected');
            return;
        }
        
        const input = document.getElementById('ai-chat-input');
        const message = input?.value.trim();
        if (!message) return;
        
        // Add user message to chat
        addChatMessage('user', message);
        input.value = '';
        
        // Show loading
        const loadingId = addChatMessage('assistant', 'Thinking...', true);
        
        try {
            const response = await fetch('/api/ai_chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'chat',
                    ai_connection_id: currentConnection.id,
                    message: message,
                    conversation_id: conversationId,
                    _csrf: csrfToken
                })
            });
            
            // Check if response is OK
            if (!response.ok) {
                const errorText = await response.text();
                removeChatMessage(loadingId);
                addChatMessage('error', `HTTP Error ${response.status}: ${errorText || 'Server error'}`);
                console.error('HTTP Error:', response.status, errorText);
                return;
            }
            
            // Get raw text first to debug
            const responseText = await response.text();
            console.log('Raw response:', responseText.substring(0, 200)); // Log first 200 chars
            
            // Try to parse JSON
            let result;
            try {
                result = JSON.parse(responseText);
            } catch (parseError) {
                console.error('JSON Parse Error:', parseError);
                console.error('Response text:', responseText);
                removeChatMessage(loadingId);
                addChatMessage('error', 'Invalid response from server. Check console for details.');
                return;
            }
            
            // Remove loading message
            removeChatMessage(loadingId);
            
            if (result && (result.status === 'ok' || result.status === 'partial')) {
                // Show the AI response even if status is 'partial' (some actions may have failed)
                addChatMessage('assistant', result.data.response || result.data.message || 'No response from AI');
                
                if (result.status === 'partial' && result.data.has_errors) {
                    // Show warning if some actions failed
                    const failedActions = result.data.execution_results?.filter(r => !r.success) || [];
                    if (failedActions.length > 0) {
                        addChatMessage('system', 
                            `Warning: ${failedActions.length} action(s) failed. Check console for details.`);
                        console.error('Failed actions:', failedActions);
                    }
                }
                
                if (result.data.requires_approval && result.data.actions_created > 0) {
                    addChatMessage('system',
                        `${result.data.actions_created} action(s) pending approval. Review below or in Settings → AI Approvals.`);
                    if (result.data.approvals && result.data.approvals.length > 0) {
                        result.data.approvals.forEach(approval => {
                            addApprovalCard(approval);
                        });
                    }
                }
                
                if (!result.data.requires_approval && result.data.actions_executed > 0) {
                    // Show success message if actions were executed directly
                    const successCount = result.data.execution_results?.filter(r => r.success).length || 0;
                    if (successCount > 0) {
                        addChatMessage('system', 
                            `${successCount} action(s) executed successfully.`);
                    }
                }
            } else {
                const errorMsg = result?.message || result?.error || result?.data?.message || 'Unknown error';
                addChatMessage('error', 'Error: ' + errorMsg);
                console.error('API Error:', result);
            }
        } catch (error) {
            console.error('Error sending message:', error);
            removeChatMessage(loadingId);
            addChatMessage('error', 'Error sending message: ' + error.message);
        }
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
            const response = await fetch('/api/ai_chat.php?' + params.toString());
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
        } catch (error) {
            console.error('Error saving chat state:', error);
        }
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
                        <span class="ai-chat-approval-badge ${statusClass}">${escapeHtml(statusLabel)}</span>
                        <span class="ai-chat-approval-time">${formatTime(approval.created_at)}</span>
                    </div>
                </div>
                <div class="ai-chat-approval-description">${escapeHtml(description)}</div>
                ${actionDataHtml ? `<div class="ai-chat-approval-data">${actionDataHtml}</div>` : ''}
                <div class="ai-chat-approval-actions">
                    ${status === 'pending' ? `
                    <button class="btn btn-sm btn-success" onclick="aiChatButton.approveAction('${approval.id}')">
                        ✓ Approve
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="aiChatButton.rejectAction('${approval.id}')">
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
        if (!confirm('Approve this action?')) return;
        
        try {
            const response = await fetch('/api/ai_approvals.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'approve',
                    id: approvalId,
                    _csrf: csrfToken
                })
            });
            
            const result = await response.json();
            
            if (result.status === 'ok') {
                // Update the approval card to show it was approved
                const card = document.getElementById('approval-' + approvalId);
                if (card) {
                    card.classList.add('ai-chat-approval-approved');
                    const actionsDiv = card.querySelector('.ai-chat-approval-actions');
                    if (actionsDiv) {
                        actionsDiv.innerHTML = '<div class="ai-chat-approval-status approved">✓ Approved and executed</div>';
                    }
                }
                addChatMessage('system', 'Action approved and executed successfully.');
            } else {
                addChatMessage('error', 'Failed to approve: ' + (result.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error approving action:', error);
            addChatMessage('error', 'Error approving action');
        }
    }
    
    // Reject action from chat
    async function rejectAction(approvalId) {
        const reason = prompt('Reason for rejection (optional):');
        
        try {
            const response = await fetch('/api/ai_approvals.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'reject',
                    id: approvalId,
                    reason: reason || 'No reason provided',
                    _csrf: csrfToken
                })
            });
            
            const result = await response.json();
            
            if (result.status === 'ok') {
                // Update the approval card to show it was rejected
                const card = document.getElementById('approval-' + approvalId);
                if (card) {
                    card.classList.add('ai-chat-approval-rejected');
                    const actionsDiv = card.querySelector('.ai-chat-approval-actions');
                    if (actionsDiv) {
                        actionsDiv.innerHTML = '<div class="ai-chat-approval-status rejected">✗ Rejected</div>';
                    }
                }
                addChatMessage('system', 'Action rejected.');
            } else {
                addChatMessage('error', 'Failed to reject: ' + (result.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error rejecting action:', error);
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
                    ? `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M8 3v3a2 2 0 0 1-2 2H3m18 0h-3a2 2 0 0 1-2-2V3m0 18v-3a2 2 0 0 1 2-2h3M3 16h3a2 2 0 0 1 2 2v3"></path>
                    </svg>`
                    : `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"></path>
                    </svg>`;
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
        rejectAction
    };

})();

