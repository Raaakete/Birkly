/**
 * AI Connections Management
 * Handles AI connection management, chat interface, and approval queue
 */

(function() {
    'use strict';

    let csrfToken = '';
    let initialized = false;
    let isEditMode = false;
    let currentEditingConnection = null;

    // Initialize only when AI Connections tab is shown
    function initAiConnections() {
        if (initialized) return;
        initialized = true;
        
        csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        loadConnections();
        loadApprovalQueue();
        setupEventListeners();
        
        // Auto-refresh approval queue every 30 seconds
        setInterval(loadApprovalQueue, 30000);
    }

    // Watch for tab changes
    document.addEventListener('DOMContentLoaded', function() {
        // Check if AI Connections tab is active
        const aiTab = document.querySelector('[data-tab="ai-connections"]');
        const aiPane = document.getElementById('ai-connections');
        
        if (aiTab && aiPane) {
            // Initialize if tab is already active
            if (aiTab.classList.contains('active') || aiPane.classList.contains('active')) {
                initAiConnections();
            }
            
            // Watch for tab clicks
            aiTab.addEventListener('click', function() {
                setTimeout(initAiConnections, 100);
            });
        }
        
        // Also watch for hash changes (deep linking)
        window.addEventListener('hashchange', function() {
            if (window.location.hash === '#ai-connections') {
                setTimeout(initAiConnections, 100);
            }
        });
    });

    // Setup event listeners
    function setupEventListeners() {
        // Add connection button
        document.getElementById('add-connection-btn')?.addEventListener('click', showAddConnectionModal);
        
        // Close modal buttons
        document.querySelectorAll('.modal-close').forEach(btn => {
            btn.addEventListener('click', hideAddConnectionModal);
        });
        
        // Add / Edit connection form
        document.getElementById('add-connection-form')?.addEventListener('submit', handleConnectionSubmit);
        
    // Provider change - update models and show/hide fields
    document.getElementById('connection-provider')?.addEventListener('change', function() {
        updateProviderModels();
        updateProviderFields();
    });
    
    // Shared type change - show/hide user selection
    document.getElementById('connection-shared-type')?.addEventListener('change', function() {
        updateSharedTypeFields();
    });
        
        // Toggle approval queue
        document.getElementById('toggle-queue-btn')?.addEventListener('click', toggleApprovalQueue);
    }

    // Load AI connections
    async function loadConnections() {
        try {
            const response = await fetch('/api/ai_connections.php?action=list');
            const result = await response.json();
            
            if (result && result.status === 'ok' && result.data) {
                renderConnections(result.data.connections || []);
            } else {
                const errorMsg = result?.message || 'Unknown error';
                console.error('Failed to load connections:', errorMsg, result);
                renderConnections([]); // Show empty state instead of error popup
            }
        } catch (error) {
            console.error('Error loading connections:', error);
            renderConnections([]); // Show empty state instead of error popup
        }
    }

    // Render connections list
    function renderConnections(connections) {
        const list = document.getElementById('connections-list');
        if (!list) return;
        
        if (connections.length === 0) {
            list.innerHTML = '<div class="empty-state"><p>No AI connections yet. Click "Add Connection" to get started.</p></div>';
            return;
        }
        
        list.innerHTML = connections.map(conn => {
            const isMCP = conn.provider === 'mcp';
            const mcpInfo = isMCP && conn.mcp_url ? `
                <div class="connection-mcp-info" style="font-size: 0.75rem; color: #666; margin-top: 0.5rem; padding: 0.5rem; background: #f5f5f5; border-radius: 4px;">
                    <div style="margin-bottom: 0.5rem;">
                        <label style="font-weight: 600; display: block; margin-bottom: 0.25rem;">MCP Server URL:</label>
                        <div style="display: flex; gap: 0.25rem; align-items: center;">
                            <input type="text" value="${escapeHtml(conn.mcp_url)}" readonly style="flex: 1; padding: 0.25rem; font-size: 0.75rem; border: 1px solid #ddd; border-radius: 4px; background: white;" id="mcp-url-${conn.id}">
                            <button class="btn-icon" onclick="copyToClipboard('mcp-url-${conn.id}')" title="Copy URL" style="padding: 0.25rem;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                    ${conn.mcp_api_key ? `
                    <div>
                        <label style="font-weight: 600; display: block; margin-bottom: 0.25rem;">MCP API Key:</label>
                        <div style="display: flex; gap: 0.25rem; align-items: center;">
                            <input type="text" value="${escapeHtml(conn.mcp_api_key)}" readonly style="flex: 1; padding: 0.25rem; font-size: 0.75rem; border: 1px solid #ddd; border-radius: 4px; background: white; font-family: monospace;" id="mcp-key-${conn.id}">
                            <button class="btn-icon" onclick="copyToClipboard('mcp-key-${conn.id}')" title="Copy API Key" style="padding: 0.25rem;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                    ` : ''}
                </div>
            ` : '';
            
            return `
            <div class="connection-item" data-connection-id="${conn.id}" data-is-mcp="${isMCP}">
                <div class="connection-info">
                    <div class="connection-name">${escapeHtml(conn.name || conn.provider)}</div>
                    <div class="connection-meta">
                        <span class="connection-provider">${escapeHtml(conn.provider)}</span>
                        <span class="connection-role">${escapeHtml(conn.role)}</span>
                    </div>
                    ${mcpInfo}
                </div>
                <div class="connection-status ${conn.status === 'active' ? 'active' : 'inactive'}">
                    ${conn.status === 'active' ? '●' : '○'}
                </div>
                <div class="connection-actions">
                    <button class="btn-icon" onclick="aiConnections.editConnection('${conn.id}')" title="Edit connection settings">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 20h9"></path>
                            <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"></path>
                        </svg>
                    </button>
                    <button class="btn-icon" onclick="aiConnections.deleteConnection('${conn.id}')" title="Delete">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        </svg>
                    </button>
                </div>
            </div>
        `;
        }).join('');
        
        // No click handlers needed - chat is now in floating button
    }

    // Show add connection modal (create mode)
    function showAddConnectionModal() {
        const modal = document.getElementById('add-connection-modal');
        if (!modal) return;

        isEditMode = false;
        currentEditingConnection = null;
        resetConnectionForm(); // This will reset checkbox to default (checked)
        updateConnectionModalTitleAndButton();

        modal.style.display = 'flex';
        loadProviders().then(() => {
            // Initialize field visibility after providers are loaded
            updateProviderFields();
        });
        loadRoles();
        loadUsers();
        updateSharedTypeFields();
    }

    // Hide add connection modal
    function hideAddConnectionModal() {
        const modal = document.getElementById('add-connection-modal');
        if (modal) {
            modal.style.display = 'none';
            resetConnectionForm();
        }
    }

    function resetConnectionForm() {
        const form = document.getElementById('add-connection-form');
        if (!form) return;

        form.reset();

        const idInput = document.getElementById('connection-id');
        if (idInput) idInput.value = '';

        const providerSelect = document.getElementById('connection-provider');
        if (providerSelect) {
            providerSelect.disabled = false;
        }

        const apiUrlGroup = document.getElementById('api-url-group');
        const apiKeyGroup = document.getElementById('api-key-group');
        const modelCustom = document.getElementById('connection-model-custom');
        if (apiUrlGroup) apiUrlGroup.style.display = 'none';
        if (apiKeyGroup) apiKeyGroup.style.display = 'block';
        if (modelCustom) modelCustom.style.display = 'none';
        const modelSelect = document.getElementById('connection-model');
        if (modelSelect) {
            modelSelect.style.display = 'block';
            modelSelect.innerHTML = '<option value="">Default</option>';
        }

        // Reset require_approval checkbox to default (checked = true)
        const requireApprovalCheckbox = document.getElementById('connection-require-approval');
        if (requireApprovalCheckbox) {
            requireApprovalCheckbox.checked = true;
        }

        updateConnectionModalTitleAndButton();
    }

    function updateConnectionModalTitleAndButton() {
        const title = document.getElementById('connection-modal-title');
        const submitBtn = document.getElementById('connection-submit-btn');
        if (!title || !submitBtn) return;

        if (isEditMode) {
            title.textContent = 'Edit AI Connection';
            submitBtn.textContent = 'Save Changes';
        } else {
            title.textContent = 'Add AI Connection';
            submitBtn.textContent = 'Connect';
        }
    }

    // Load providers
    async function loadProviders() {
        try {
            const response = await fetch('/api/ai_connections.php?action=providers');
            const result = await response.json();
            
            if (result.status === 'ok') {
                const select = document.getElementById('connection-provider');
                if (select) {
                    select.innerHTML = '<option value="">Select a provider...</option>' +
                        Object.entries(result.data.providers).map(([key, provider]) =>
                            `<option value="${key}">${escapeHtml(provider.name)}</option>`
                        ).join('');
                }
                return result.data.providers;
            }
        } catch (error) {
            console.error('Error loading providers:', error);
        }
        return null;
    }

    // Load roles from settings/roles.json for AI connection role picker
    async function loadRoles(selectedRole = null) {
        const select = document.getElementById('connection-role');
        if (!select) return;

        const legacyRoles = [
            { id: 'viewer', name: 'Viewer', description: 'Read-only' },
            { id: 'author', name: 'Author', description: 'Create content' },
            { id: 'editor', name: 'Editor', description: 'Create & Edit' },
            { id: 'admin', name: 'Admin', description: 'Full access' }
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
                const desc = role.description ? ` (${role.description})` : '';
                return `<option value="${escapeHtml(roleId)}">${escapeHtml(label)}${escapeHtml(desc)}</option>`;
            }).join('');

            if (selectedRole) {
                select.value = selectedRole;
            } else if (!select.value && roles.length > 0) {
                const authorRole = roles.find(r => (r.id || r.name) === 'author');
                select.value = authorRole ? 'author' : (roles[0].id || roles[0].name || '');
            }
        } catch (error) {
            console.error('Error loading roles:', error);
            select.innerHTML = legacyRoles.map(role =>
                `<option value="${role.id}">${escapeHtml(role.name)} (${escapeHtml(role.description)})</option>`
            ).join('');
            if (selectedRole) {
                select.value = selectedRole;
            }
        }
    }

// Update provider models
function updateProviderModels() {
    const providerSelect = document.getElementById('connection-provider');
    const modelSelect = document.getElementById('connection-model');
    const modelCustom = document.getElementById('connection-model-custom');
    if (!providerSelect || !modelSelect) return;
    
    const provider = providerSelect.value;
    if (!provider) {
        modelSelect.innerHTML = '<option value="">Default</option>';
        if (modelCustom) modelCustom.style.display = 'none';
        return;
    }
    
    // Load models for selected provider
    fetch('/api/ai_connections.php?action=providers')
        .then(r => r.json())
        .then(result => {
            if (result.status === 'ok' && result.data.providers[provider]) {
                const providerInfo = result.data.providers[provider];
                const models = providerInfo.models || [];
                const customModels = providerInfo.custom_models || false;
                
                if (customModels && models.length === 0) {
                    // Custom provider - show text input
                    modelSelect.style.display = 'none';
                    if (modelCustom) {
                        modelCustom.style.display = 'block';
                        modelCustom.required = true;
                    }
                } else {
                    // Standard provider - show dropdown
                    modelSelect.style.display = 'block';
                    if (modelCustom) {
                        modelCustom.style.display = 'none';
                        modelCustom.required = false;
                    }
                    modelSelect.innerHTML = '<option value="">Default</option>' +
                        models.map(model => `<option value="${escapeHtml(model)}">${escapeHtml(model)}</option>`).join('');
                    
                    // If custom_models is true, also allow custom input
                    if (customModels && modelCustom) {
                        modelCustom.style.display = 'block';
                        modelCustom.placeholder = 'Or enter custom model name';
                        modelCustom.required = false;
                    }
                }
            }
        })
        .catch(err => console.error('Error loading models:', err));
}

// Load users for selection
async function loadUsers() {
    try {
        const response = await fetch('/api/ai_connections.php?action=get_users');
        const result = await response.json();
        
        if (result.status === 'ok') {
            const usersList = document.getElementById('allowed-users-list');
            if (usersList) {
                if (result.data.users && result.data.users.length > 0) {
                    usersList.innerHTML = result.data.users.map(user => `
                        <label style="display: block; padding: 0.5rem; cursor: pointer;">
                            <input type="checkbox" name="allowed_users[]" value="${escapeHtml(user.id)}" class="user-checkbox">
                            <span>${escapeHtml(user.name || user.email)} (${escapeHtml(user.role)})</span>
                        </label>
                    `).join('');
                } else {
                    usersList.innerHTML = '<div class="empty-state"><p>No users found</p></div>';
                }
            }
        }
    } catch (error) {
        console.error('Error loading users:', error);
        const usersList = document.getElementById('allowed-users-list');
        if (usersList) {
            usersList.innerHTML = '<div class="error">Error loading users</div>';
        }
    }
}

// Update shared type fields visibility
function updateSharedTypeFields() {
    const sharedType = document.getElementById('connection-shared-type');
    const allowedUsersGroup = document.getElementById('allowed-users-group');
    
    if (!sharedType || !allowedUsersGroup) return;
    
    if (sharedType.value === 'specific') {
        allowedUsersGroup.style.display = 'block';
    } else {
        allowedUsersGroup.style.display = 'none';
        // Uncheck all user checkboxes
        document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = false);
    }
}

// Update provider-specific fields visibility
function updateProviderFields() {
    const providerSelect = document.getElementById('connection-provider');
    const apiKeyGroup = document.getElementById('api-key-group');
    const apiUrlGroup = document.getElementById('api-url-group');
    const apiKeyInput = document.getElementById('connection-api-key');
    
    if (!providerSelect) return;
    
    const provider = providerSelect.value;
    
    // Show/hide API URL field
    if (apiUrlGroup) {
        if (provider === 'custom' || provider === 'ollama') {
            apiUrlGroup.style.display = 'block';
            const apiUrlInput = document.getElementById('connection-api-url');
            if (apiUrlInput) apiUrlInput.required = provider === 'custom';
        } else {
            apiUrlGroup.style.display = 'none';
            const apiUrlInput = document.getElementById('connection-api-url');
            if (apiUrlInput) apiUrlInput.required = false;
        }
    }
    
    // Show/hide API key field
    if (apiKeyGroup && apiKeyInput) {
        if (provider === 'mcp') {
            apiKeyGroup.style.display = 'none';
            apiKeyInput.required = false;
        } else {
            apiKeyGroup.style.display = 'block';
            apiKeyInput.required = provider !== 'ollama'; // Ollama doesn't need API key
        }
    }
}

    // Handle create / update connection submit
    async function handleConnectionSubmit(e) {
        e.preventDefault();

        const formData = new FormData(e.target);
        const modelSelect = document.getElementById('connection-model');
        const modelCustom = document.getElementById('connection-model-custom');
        const model = modelCustom && modelCustom.style.display !== 'none' 
            ? modelCustom.value 
            : (formData.get('model') || '');
        const idInput = document.getElementById('connection-id');
        const connectionId = idInput ? (idInput.value || '') : '';

        // Get selected users
        const allowedUsers = Array.from(document.querySelectorAll('.user-checkbox:checked')).map(cb => cb.value);

        let payload;
        if (connectionId) {
            // Update existing connection
            // Get require_approval - read checkbox state directly
            const requireApprovalCheckbox = document.getElementById('connection-require-approval');
            if (!requireApprovalCheckbox) {
                console.error('ERROR: connection-require-approval checkbox not found!');
            }
            const requireApproval = requireApprovalCheckbox ? Boolean(requireApprovalCheckbox.checked) : false;
            
            console.log('Checkbox element:', requireApprovalCheckbox);
            console.log('Checkbox checked state:', requireApprovalCheckbox ? requireApprovalCheckbox.checked : 'checkbox not found');
            console.log('requireApproval value:', requireApproval, 'type:', typeof requireApproval);
            
            const updates = {
                name: formData.get('name') || '',
                api_url: formData.get('api_url') || '',
                model: model || '',
                role: formData.get('role') || 'author',
                shared_type: formData.get('shared_type') || 'none',
                allowed_users: allowedUsers,
                require_approval: requireApproval  // Explicitly ensure it's a boolean
            };
            
            // Verify require_approval is actually a boolean before sending
            if (typeof updates.require_approval !== 'boolean') {
                console.error('ERROR: require_approval is not a boolean! Value:', updates.require_approval, 'Type:', typeof updates.require_approval);
                updates.require_approval = Boolean(updates.require_approval);
            }
            
            // Remove empty string values (but keep booleans and other valid values)
            Object.keys(updates).forEach(key => {
                if (updates[key] === '' && key !== 'api_url' && key !== 'model') {
                    delete updates[key];
                }
            });
            
            console.log('Updating connection:', connectionId);
            console.log('Updates object:', updates);
            console.log('Updates JSON:', JSON.stringify(updates, null, 2));

            const apiKey = formData.get('api_key') || '';
            if (apiKey) {
                updates.api_key = apiKey;
            }

            payload = {
                action: 'update',
                id: connectionId,
                updates: updates,
                _csrf: csrfToken
            };
        } else {
            // Create new connection
            payload = {
                action: 'create',
                provider: formData.get('provider'),
                name: formData.get('name'),
                api_key: formData.get('api_key') || '',
                api_url: formData.get('api_url') || '',
                model: model,
                role: formData.get('role'),
                shared_type: formData.get('shared_type') || 'none',
                allowed_users: allowedUsers,
                require_approval: formData.get('require_approval') === '1',
                _csrf: csrfToken
            };
        }

        try {
            const response = await fetch('/api/ai_connections.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            
            const result = await response.json();
            
            if (result && result.status === 'ok') {
                hideAddConnectionModal();
                // Reload connections after a short delay to ensure it's saved
                setTimeout(() => {
                    loadConnections();
                }, 100);
                showSuccess(connectionId ? 'AI connection updated successfully' : 'AI connection created successfully');
            } else {
                const errorMsg = result?.message || 'Unknown error';
                console.error('Connection save error:', result);
                showError('Failed to ' + (connectionId ? 'update' : 'create') + ' connection: ' + errorMsg);
            }
        } catch (error) {
            console.error('Error saving connection:', error);
            showError('Error saving connection');
        }
    }

    // Edit existing connection (load and open modal)
    async function editConnection(connectionId) {
        try {
            const response = await fetch(`/api/ai_connections.php?action=get&id=${encodeURIComponent(connectionId)}`);
            const result = await response.json();

            if (result.status !== 'ok' || !result.data?.connection) {
                showError('Failed to load connection details');
                return;
            }

            const conn = result.data.connection;
            isEditMode = true;
            currentEditingConnection = conn;

            const modal = document.getElementById('add-connection-modal');
            if (!modal) return;

            // Reset form first (but we'll override checkbox value below)
            resetConnectionForm();

            // Ensure providers and users are loaded before populating fields
            await loadProviders();
            await loadRoles(conn.role);
            await loadUsers();

            // Populate form fields
            const idInput = document.getElementById('connection-id');
            const providerSelect = document.getElementById('connection-provider');
            const nameInput = document.getElementById('connection-name');
            const apiUrlInput = document.getElementById('connection-api-url');
            const modelSelect = document.getElementById('connection-model');
            const modelCustom = document.getElementById('connection-model-custom');
            const roleSelect = document.getElementById('connection-role');
            const sharedTypeSelect = document.getElementById('connection-shared-type');
            const requireApprovalCheckbox = document.getElementById('connection-require-approval');

            if (idInput) idInput.value = conn.id || '';

            if (providerSelect) {
                providerSelect.value = conn.provider || '';
                providerSelect.disabled = true; // Provider cannot be changed after creation
                updateProviderFields();
                updateProviderModels();
            }

            if (nameInput) nameInput.value = conn.name || '';
            if (apiUrlInput) apiUrlInput.value = conn.api_url || '';

            // Model: attempt to select from list, fallback to custom
            if (modelSelect && modelCustom) {
                const modelValue = conn.model || '';
                if (modelValue) {
                    // Wait a tick for models to be populated
                    setTimeout(() => {
                        const option = Array.from(modelSelect.options).find(opt => opt.value === modelValue);
                        if (option) {
                            modelSelect.value = modelValue;
                            modelSelect.style.display = 'block';
                            modelCustom.style.display = 'none';
                        } else {
                            modelSelect.value = '';
                            modelSelect.style.display = 'none';
                            modelCustom.style.display = 'block';
                            modelCustom.value = modelValue;
                        }
                    }, 150);
                } else {
                    modelSelect.value = '';
                }
            }

            if (roleSelect && conn.role) {
                roleSelect.value = conn.role;
            }

            if (sharedTypeSelect) {
                const sharedType = conn.shared_type || (conn.shared ? 'all' : 'none');
                sharedTypeSelect.value = sharedType;
                updateSharedTypeFields();

                // Preselect allowed users if specific
                if (sharedType === 'specific' && Array.isArray(conn.allowed_users)) {
                    document.querySelectorAll('.user-checkbox').forEach(cb => {
                        cb.checked = conn.allowed_users.includes(cb.value);
                    });
                }
            }

            if (requireApprovalCheckbox) {
                // Handle both boolean and string values
                const requireApproval = conn.require_approval;
                if (typeof requireApproval === 'string') {
                    requireApprovalCheckbox.checked = (requireApproval === '1' || requireApproval === 'true' || requireApproval === 'yes');
                } else {
                    requireApprovalCheckbox.checked = !!requireApproval;
                }
                // Ensure the value is set correctly for form submission
                requireApprovalCheckbox.value = '1';
            }

            updateConnectionModalTitleAndButton();
            modal.style.display = 'flex';
        } catch (error) {
            console.error('Error loading connection for edit:', error);
            showError('Error loading connection details');
        }
    }

    // Open chat with connection
    function openChat(connectionId) {
        // Load connection details
        fetch(`/api/ai_connections.php?action=get&id=${connectionId}`)
            .then(r => r.json())
            .then(result => {
                if (result.status === 'ok') {
                    currentConnection = result.data.connection;
                    conversationId = 'conv_' + Date.now();
                    
                    // Show chat panel
                    const chatPanel = document.getElementById('chat-panel');
                    if (chatPanel) {
                        chatPanel.style.display = 'flex';
                    }
                    
                    // Update chat header
                    document.getElementById('chat-connection-name').textContent = 
                        currentConnection.name || currentConnection.provider;
                    document.getElementById('chat-status').textContent = 
                        currentConnection.status === 'active' ? 'Active' : 'Inactive';
                    
                    // Clear messages
                    const messages = document.getElementById('chat-messages');
                    if (messages) {
                        messages.innerHTML = '<div class="chat-welcome"><p>Chat started. Ask the AI to help you create or edit content.</p></div>';
                    }
                    
                    // Focus input
                    document.getElementById('chat-input')?.focus();
                }
            })
            .catch(err => {
                console.error('Error loading connection:', err);
                showError('Failed to open chat');
            });
    }

    // Close chat
    function closeChat() {
        currentConnection = null;
        conversationId = null;
        const chatPanel = document.getElementById('chat-panel');
        if (chatPanel) {
            chatPanel.style.display = 'none';
        }
    }

    // Send chat message
    async function sendChatMessage() {
        if (!currentConnection) {
            showError('No connection selected');
            return;
        }
        
        const input = document.getElementById('chat-input');
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
            
            const result = await response.json();
            
            // Remove loading message
            removeChatMessage(loadingId);
            
            if (result.status === 'ok') {
                addChatMessage('assistant', result.data.response);
                
                if (result.data.requires_approval && result.data.actions_created > 0) {
                    addChatMessage('system', 
                        `${result.data.actions_created} action(s) created and waiting for approval. Check the approval queue.`);
                    loadApprovalQueue();
                }
            } else {
                addChatMessage('error', 'Error: ' + (result.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error sending message:', error);
            removeChatMessage(loadingId);
            addChatMessage('error', 'Error sending message');
        }
    }

    // Add chat message
    function addChatMessage(role, content, isLoading = false) {
        const messages = document.getElementById('chat-messages');
        if (!messages) return null;
        
        // Remove welcome message if exists
        const welcome = messages.querySelector('.chat-welcome');
        if (welcome) welcome.remove();
        
        const messageId = 'msg_' + Date.now();
        const messageDiv = document.createElement('div');
        messageDiv.className = `chat-message chat-message-${role}`;
        messageDiv.id = messageId;
        messageDiv.innerHTML = `
            <div class="chat-message-content">
                ${isLoading ? '<div class="chat-loading">...</div>' : escapeHtml(content)}
            </div>
        `;
        
        messages.appendChild(messageDiv);
        messages.scrollTop = messages.scrollHeight;
        
        return messageId;
    }

    // Remove chat message
    function removeChatMessage(messageId) {
        const message = document.getElementById(messageId);
        if (message) message.remove();
    }

    // Load approval queue
    async function loadApprovalQueue() {
        try {
            const response = await fetch('/api/ai_approvals.php?action=list&status=pending');
            const result = await response.json();
            
            if (result && result.status === 'ok' && result.data) {
                renderApprovalQueue(result.data.approvals || []);
            } else {
                // Silently fail - just show empty queue
                renderApprovalQueue([]);
            }
        } catch (error) {
            console.error('Error loading approval queue:', error);
            renderApprovalQueue([]); // Show empty state
        }
    }

    // Render approval queue
    function renderApprovalQueue(approvals) {
        const queue = document.getElementById('approval-queue-content');
        if (!queue) return;
        
        if (approvals.length === 0) {
            queue.innerHTML = '<div class="empty-state"><p>No pending approvals</p></div>';
            return;
        }
        
        queue.innerHTML = approvals.map(approval => `
            <div class="approval-item">
                <div class="approval-header">
                    <div class="approval-info">
                        <strong>${escapeHtml(approval.action_type)}</strong>
                        <span class="approval-time">${formatTime(approval.created_at)}</span>
                    </div>
                </div>
                <div class="approval-description">${escapeHtml(approval.description || 'No description')}</div>
                <div class="approval-actions">
                    <button class="btn btn-sm btn-success" onclick="aiConnections.approveAction('${approval.id}')">
                        Approve
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="aiConnections.rejectAction('${approval.id}')">
                        Reject
                    </button>
                </div>
            </div>
        `).join('');
    }

    // Approve action
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
                showSuccess('Action approved');
                loadApprovalQueue();
            } else {
                showError('Failed to approve: ' + (result.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error approving action:', error);
            showError('Error approving action');
        }
    }

    // Reject action
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
                showSuccess('Action rejected');
                loadApprovalQueue();
            } else {
                showError('Failed to reject: ' + (result.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error rejecting action:', error);
            showError('Error rejecting action');
        }
    }

    // Delete connection
    async function deleteConnection(connectionId) {
        if (!confirm('Delete this AI connection? This cannot be undone.')) return;
        
        try {
            const response = await fetch('/api/ai_connections.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'delete',
                    id: connectionId,
                    _csrf: csrfToken
                })
            });
            
            const result = await response.json();
            
            if (result.status === 'ok') {
                showSuccess('Connection deleted');
                loadConnections();
            } else {
                showError('Failed to delete: ' + (result.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error deleting connection:', error);
            showError('Error deleting connection');
        }
    }

    // Toggle approval queue
    function toggleApprovalQueue() {
        const content = document.getElementById('approval-queue-content');
        if (content) {
            content.style.display = content.style.display === 'none' ? 'block' : 'none';
        }
    }

    // Utility functions
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatTime(isoString) {
        const date = new Date(isoString);
        return date.toLocaleString();
    }

    function showError(message) {
        // Simple alert for now - could be enhanced with a toast system
        alert('Error: ' + message);
    }

    function showSuccess(message) {
        // Simple alert for now - could be enhanced with a toast system
        alert('Success: ' + message);
    }

    // Copy to clipboard function
    function copyToClipboard(inputId) {
        const input = document.getElementById(inputId);
        if (input) {
            input.select();
            input.setSelectionRange(0, 99999); // For mobile devices
            try {
                document.execCommand('copy');
                // Show temporary success message
                const originalValue = input.value;
                input.value = 'Copied!';
                setTimeout(() => {
                    input.value = originalValue;
                }, 1000);
            } catch (err) {
                console.error('Failed to copy:', err);
            }
        }
    }

    // Export functions for onclick handlers
    window.aiConnections = {
        editConnection,
        deleteConnection,
        approveAction,
        rejectAction
    };
    
    // Export copy function globally
    window.copyToClipboard = copyToClipboard;

})();

