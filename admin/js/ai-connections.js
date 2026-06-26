/**
 * AI Connections Management
 * Handles outbound BYOAI connection management and approval queue
 */

(function() {
    'use strict';

    let csrfToken = '';
    let initialized = false;
    let isEditMode = false;
    let currentEditingConnection = null;
    let wizardStep = 1;
    const WIZARD_MAX_STEP = 3;
    let providersCache = null;
    let connectionTestPassed = false;

    function initAiConnections() {
        if (initialized) return;
        initialized = true;

        csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        loadConnections();
        loadApprovalQueue();
        setupEventListeners();

        setInterval(loadApprovalQueue, 30000);
    }

    document.addEventListener('DOMContentLoaded', function() {
        const aiTab = document.querySelector('[data-tab="ai-connections"]');
        const aiPane = document.getElementById('ai-connections');

        if (aiTab && aiPane) {
            if (aiTab.classList.contains('active') || aiPane.classList.contains('active')) {
                initAiConnections();
            }
            aiTab.addEventListener('click', function() {
                setTimeout(initAiConnections, 100);
            });
        }

        window.addEventListener('hashchange', function() {
            if (window.location.hash === '#ai-connections') {
                setTimeout(initAiConnections, 100);
            }
        });
    });

    function setupEventListeners() {
        document.getElementById('add-connection-btn')?.addEventListener('click', showAddConnectionModal);

        document.querySelectorAll('#add-connection-modal .modal-close').forEach(btn => {
            btn.addEventListener('click', hideAddConnectionModal);
        });

        document.getElementById('add-connection-form')?.addEventListener('submit', handleConnectionSubmit);
        document.getElementById('wizard-next-btn')?.addEventListener('click', wizardNext);
        document.getElementById('wizard-back-btn')?.addEventListener('click', wizardBack);
        document.getElementById('test-connection-btn')?.addEventListener('click', testConnection);

        document.getElementById('connection-provider')?.addEventListener('change', function() {
            updateProviderModels();
            updateProviderFields();
            connectionTestPassed = false;
            clearTestStatus();
        });

        document.getElementById('connection-shared-type')?.addEventListener('change', updateSharedTypeFields);
        document.getElementById('toggle-queue-btn')?.addEventListener('click', toggleApprovalQueue);
    }

    async function loadConnections() {
        try {
            const response = await fetch('/api/ai_connections.php?action=list');
            const result = await response.json();

            if (result && result.status === 'ok' && result.data) {
                renderConnections(result.data.connections || []);
            } else {
                renderConnections([]);
            }
        } catch (error) {
            console.error('Error loading connections:', error);
            renderConnections([]);
        }
    }

    function renderConnections(connections) {
        const list = document.getElementById('connections-list');
        if (!list) return;

        const outbound = connections.filter(conn => conn.provider !== 'mcp');

        if (outbound.length === 0) {
            list.innerHTML = '<div class="empty-state"><p>No AI connections yet. Click "Add Connection" to connect your API provider.</p></div>';
            return;
        }

        list.innerHTML = `
            <p class="connection-mcp-note ai-tab-mcp-note">For Claude Desktop or other external MCP clients, use the <a href="#mcp-connections" class="mcp-tab-link">MCP Connections</a> tab.</p>
        ` + outbound.map(conn => `
            <div class="connection-item" data-connection-id="${conn.id}">
                <div class="connection-info">
                    <div class="connection-name">${escapeHtml(conn.name || conn.provider)}</div>
                    <div class="connection-meta">
                        <span class="connection-provider">${escapeHtml(conn.provider)}</span>
                        <span class="connection-role">${escapeHtml(conn.role)}</span>
                    </div>
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
        `).join('');

        list.querySelectorAll('.mcp-tab-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const mcpTab = document.querySelector('[data-tab="mcp-connections"]');
                if (mcpTab) mcpTab.click();
            });
        });
    }

    function showAddConnectionModal() {
        const modal = document.getElementById('add-connection-modal');
        if (!modal) return;

        isEditMode = false;
        currentEditingConnection = null;
        wizardStep = 1;
        connectionTestPassed = false;
        resetConnectionForm();
        setWizardMode(true);
        updateConnectionModalTitleAndButton();

        modal.style.display = 'flex';
        loadProviders().then(() => {
            updateProviderFields();
            showWizardStep(1);
        });
        loadRoles();
        loadUsers();
        updateSharedTypeFields();
    }

    function hideAddConnectionModal() {
        const modal = document.getElementById('add-connection-modal');
        if (modal) {
            modal.style.display = 'none';
            resetConnectionForm();
            hideWizardError();
        }
    }

    function resetConnectionForm() {
        const form = document.getElementById('add-connection-form');
        if (!form) return;

        form.reset();

        const idInput = document.getElementById('connection-id');
        if (idInput) idInput.value = '';

        const providerSelect = document.getElementById('connection-provider');
        if (providerSelect) providerSelect.disabled = false;

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

        const requireApprovalCheckbox = document.getElementById('connection-require-approval');
        if (requireApprovalCheckbox) requireApprovalCheckbox.checked = true;

        clearTestStatus();
        connectionTestPassed = false;
        updateConnectionModalTitleAndButton();
    }

    function setWizardMode(enabled) {
        const stepsEl = document.getElementById('connection-wizard-steps');
        const backBtn = document.getElementById('wizard-back-btn');
        const nextBtn = document.getElementById('wizard-next-btn');
        const submitBtn = document.getElementById('connection-submit-btn');

        if (stepsEl) stepsEl.style.display = enabled ? 'flex' : 'none';

        if (enabled) {
            if (backBtn) backBtn.style.display = wizardStep > 1 ? 'inline-block' : 'none';
            if (nextBtn) nextBtn.style.display = wizardStep < WIZARD_MAX_STEP ? 'inline-block' : 'none';
            if (submitBtn) submitBtn.style.display = wizardStep === WIZARD_MAX_STEP ? 'inline-block' : 'none';
        } else {
            if (backBtn) backBtn.style.display = 'none';
            if (nextBtn) nextBtn.style.display = 'none';
            if (submitBtn) submitBtn.style.display = 'inline-block';
            document.querySelectorAll('.wizard-panel').forEach(panel => {
                panel.style.display = 'block';
            });
        }
    }

    function showWizardStep(step) {
        wizardStep = step;
        document.querySelectorAll('.wizard-panel').forEach(panel => {
            const panelStep = parseInt(panel.getAttribute('data-wizard-step'), 10);
            panel.style.display = panelStep === step ? 'block' : 'none';
        });
        document.querySelectorAll('.wizard-step-indicator').forEach(ind => {
            const indStep = parseInt(ind.getAttribute('data-step'), 10);
            ind.classList.toggle('active', indStep === step);
            ind.classList.toggle('completed', indStep < step);
        });
        setWizardMode(true);
        hideWizardError();
    }

    function wizardNext() {
        hideWizardError();

        if (wizardStep === 1) {
            const provider = document.getElementById('connection-provider')?.value;
            if (!provider) {
                showWizardError('Please select a provider.');
                return;
            }
            updateProviderFields();
            updateProviderModels();
            showWizardStep(2);
            return;
        }

        if (wizardStep === 2) {
            const provider = document.getElementById('connection-provider')?.value;
            const apiKey = document.getElementById('connection-api-key')?.value || '';
            const apiUrl = document.getElementById('connection-api-url')?.value || '';

            if (provider === 'custom' && !apiUrl) {
                showWizardError('API URL is required for custom providers.');
                return;
            }
            if (provider !== 'ollama' && !apiKey) {
                showWizardError('API key is required. Consumer subscriptions (ChatGPT Plus, Claude Pro) do not include API access — get a key from your provider\'s developer console.');
                return;
            }
            if (provider !== 'ollama' && !connectionTestPassed) {
                showWizardError('Please test the connection before continuing, or fix any errors shown.');
                return;
            }

            loadRoles();
            updateProviderModels();
            showWizardStep(3);
        }
    }

    function wizardBack() {
        if (wizardStep > 1) {
            showWizardStep(wizardStep - 1);
        }
    }

    function showWizardError(message) {
        const el = document.getElementById('wizard-error');
        if (el) {
            el.textContent = message;
            el.style.display = 'block';
        }
    }

    function hideWizardError() {
        const el = document.getElementById('wizard-error');
        if (el) {
            el.textContent = '';
            el.style.display = 'none';
        }
    }

    function clearTestStatus() {
        const status = document.getElementById('test-connection-status');
        if (status) {
            status.textContent = '';
            status.className = 'test-connection-status';
        }
    }

    async function testConnection() {
        const provider = document.getElementById('connection-provider')?.value;
        const apiKey = document.getElementById('connection-api-key')?.value || '';
        const apiUrl = document.getElementById('connection-api-url')?.value || '';
        const statusEl = document.getElementById('test-connection-status');
        const testBtn = document.getElementById('test-connection-btn');

        if (!provider) {
            showWizardError('Select a provider first.');
            return;
        }

        if (provider === 'ollama') {
            connectionTestPassed = true;
            if (statusEl) {
                statusEl.textContent = 'Ollama does not require a key — ensure the server can reach your local Ollama URL.';
                statusEl.className = 'test-connection-status success';
            }
            return;
        }

        if (!apiKey) {
            showWizardError('Enter an API key before testing.');
            return;
        }

        if (testBtn) testBtn.disabled = true;
        if (statusEl) {
            statusEl.textContent = 'Testing…';
            statusEl.className = 'test-connection-status pending';
        }

        try {
            const response = await fetch('/api/ai_connections.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'test',
                    provider,
                    api_key: apiKey,
                    api_url: apiUrl,
                    _csrf: csrfToken
                })
            });
            const result = await response.json();

            if (result.status === 'ok' && result.data?.valid) {
                connectionTestPassed = true;
                if (statusEl) {
                    statusEl.textContent = result.data.message || 'Connection successful';
                    statusEl.className = 'test-connection-status success';
                }
            } else {
                connectionTestPassed = false;
                const msg = formatSaveError(result?.message || 'Connection test failed');
                if (statusEl) {
                    statusEl.textContent = msg;
                    statusEl.className = 'test-connection-status error';
                }
            }
        } catch (error) {
            connectionTestPassed = false;
            if (statusEl) {
                statusEl.textContent = 'Network error — could not reach Birkly server.';
                statusEl.className = 'test-connection-status error';
            }
        } finally {
            if (testBtn) testBtn.disabled = false;
        }
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

    async function loadProviders() {
        try {
            const response = await fetch('/api/ai_connections.php?action=providers');
            const result = await response.json();

            if (result.status === 'ok') {
                providersCache = result.data.providers;
                const select = document.getElementById('connection-provider');
                if (select) {
                    const outbound = Object.entries(providersCache)
                        .filter(([key]) => key !== 'mcp');
                    select.innerHTML = '<option value="">Select a provider...</option>' +
                        outbound.map(([key, provider]) =>
                            `<option value="${key}">${escapeHtml(provider.name)}</option>`
                        ).join('');
                }
                return providersCache;
            }
        } catch (error) {
            console.error('Error loading providers:', error);
        }
        return null;
    }

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
            if (selectedRole) select.value = selectedRole;
        }
    }

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

        const providers = providersCache;
        if (!providers) {
            fetch('/api/ai_connections.php?action=providers')
                .then(r => r.json())
                .then(result => {
                    if (result.status === 'ok') {
                        providersCache = result.data.providers;
                        populateModels(provider);
                    }
                })
                .catch(err => console.error('Error loading models:', err));
            return;
        }
        populateModels(provider);
    }

    function populateModels(provider) {
        const modelSelect = document.getElementById('connection-model');
        const modelCustom = document.getElementById('connection-model-custom');
        if (!modelSelect || !providersCache || !providersCache[provider]) return;

        const providerInfo = providersCache[provider];
        const models = providerInfo.models || [];
        const customModels = providerInfo.custom_models || false;

        if (customModels && models.length === 0) {
            modelSelect.style.display = 'none';
            if (modelCustom) {
                modelCustom.style.display = 'block';
                modelCustom.required = true;
            }
        } else {
            modelSelect.style.display = 'block';
            if (modelCustom) {
                modelCustom.style.display = 'none';
                modelCustom.required = false;
            }
            modelSelect.innerHTML = '<option value="">Default</option>' +
                models.map(model => `<option value="${escapeHtml(model)}">${escapeHtml(model)}</option>`).join('');

            if (customModels && modelCustom) {
                modelCustom.style.display = 'block';
                modelCustom.placeholder = 'Or enter custom model name';
                modelCustom.required = false;
            }
        }
    }

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
            if (usersList) usersList.innerHTML = '<div class="error">Error loading users</div>';
        }
    }

    function updateSharedTypeFields() {
        const sharedType = document.getElementById('connection-shared-type');
        const allowedUsersGroup = document.getElementById('allowed-users-group');

        if (!sharedType || !allowedUsersGroup) return;

        if (sharedType.value === 'specific') {
            allowedUsersGroup.style.display = 'block';
        } else {
            allowedUsersGroup.style.display = 'none';
            document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = false);
        }
    }

    function updateProviderFields() {
        const providerSelect = document.getElementById('connection-provider');
        const apiKeyGroup = document.getElementById('api-key-group');
        const apiUrlGroup = document.getElementById('api-url-group');
        const apiKeyInput = document.getElementById('connection-api-key');
        const ollamaHint = document.getElementById('ollama-hint');
        const testGroup = document.getElementById('test-connection-group');
        const apiUrlHint = document.getElementById('api-url-hint');

        if (!providerSelect) return;

        const provider = providerSelect.value;

        if (apiUrlGroup) {
            if (provider === 'custom' || provider === 'ollama') {
                apiUrlGroup.style.display = 'block';
                const apiUrlInput = document.getElementById('connection-api-url');
                if (apiUrlInput) {
                    apiUrlInput.required = provider === 'custom';
                    if (provider === 'ollama' && !apiUrlInput.value) {
                        apiUrlInput.placeholder = 'http://localhost:11434/api/chat';
                    }
                }
                if (apiUrlHint && provider === 'ollama') {
                    apiUrlHint.textContent = 'Local Ollama URL (default: http://localhost:11434/api/chat)';
                }
            } else {
                apiUrlGroup.style.display = 'none';
                const apiUrlInput = document.getElementById('connection-api-url');
                if (apiUrlInput) apiUrlInput.required = false;
            }
        }

        if (apiKeyGroup && apiKeyInput) {
            if (provider === 'ollama') {
                apiKeyGroup.style.display = 'none';
                apiKeyInput.required = false;
            } else {
                apiKeyGroup.style.display = 'block';
                apiKeyInput.required = true;
            }
        }

        if (ollamaHint) {
            ollamaHint.style.display = provider === 'ollama' ? 'block' : 'none';
        }

        if (testGroup) {
            testGroup.style.display = provider ? 'block' : 'none';
        }
    }

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

        const allowedUsers = Array.from(document.querySelectorAll('.user-checkbox:checked')).map(cb => cb.value);

        let payload;
        if (connectionId) {
            const requireApprovalCheckbox = document.getElementById('connection-require-approval');
            const requireApproval = requireApprovalCheckbox ? Boolean(requireApprovalCheckbox.checked) : false;

            const updates = {
                name: formData.get('name') || '',
                api_url: formData.get('api_url') || '',
                model: model || '',
                role: formData.get('role') || 'author',
                shared_type: formData.get('shared_type') || 'none',
                allowed_users: allowedUsers,
                require_approval: requireApproval
            };

            Object.keys(updates).forEach(key => {
                if (updates[key] === '' && key !== 'api_url' && key !== 'model') {
                    delete updates[key];
                }
            });

            const apiKey = formData.get('api_key') || '';
            if (apiKey) updates.api_key = apiKey;

            payload = {
                action: 'update',
                id: connectionId,
                updates: updates,
                _csrf: csrfToken
            };
        } else {
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
                setTimeout(() => loadConnections(), 100);
                showSuccess(connectionId ? 'AI connection updated successfully' : 'AI connection created successfully');
                window.dispatchEvent(new CustomEvent('birkly:ai-connection-changed', {
                    detail: { action: connectionId ? 'update' : 'create', id: result.data?.id || connectionId }
                }));
            } else {
                showError(formatSaveError(result?.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error saving connection:', error);
            showError('Could not reach the server. Check your network connection and try again.');
        }
    }

    function formatSaveError(message) {
        const m = String(message);
        if (m.includes('Invalid API key') || m.includes('HTTP 401') || m.includes('HTTP 403')) {
            return 'Invalid API key. Make sure you copied the key from your provider\'s developer console — ChatGPT Plus and Claude Pro subscriptions do not include API access.';
        }
        if (m.includes('rate limit') || m.includes('HTTP 429')) {
            return 'Rate limited by the provider. Wait a moment and try again, or check your API plan limits.';
        }
        if (m.includes('Unsupported provider')) {
            return 'This provider is not supported for outbound connections.';
        }
        if (m.includes('already exists')) {
            return 'You already have a connection for this provider. Edit the existing connection or delete it first.';
        }
        if (m.includes('API key is required')) {
            return 'API key is required. Get one from your provider\'s developer portal (not from a consumer subscription).';
        }
        if (m.includes('API URL is required')) {
            return 'API URL is required for custom providers.';
        }
        if (m.includes('validation failed')) {
            return m.replace('API key validation failed: ', 'Connection test failed: ');
        }
        return m;
    }

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

            resetConnectionForm();
            setWizardMode(false);

            await loadProviders();
            await loadRoles(conn.role);
            await loadUsers();

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
                providerSelect.disabled = true;
                updateProviderFields();
                updateProviderModels();
            }

            if (nameInput) nameInput.value = conn.name || '';
            if (apiUrlInput) apiUrlInput.value = conn.api_url || '';

            if (modelSelect && modelCustom) {
                const modelValue = conn.model || '';
                if (modelValue) {
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
                }
            }

            if (roleSelect && conn.role) roleSelect.value = conn.role;

            if (sharedTypeSelect) {
                const sharedType = conn.shared_type || (conn.shared ? 'all' : 'none');
                sharedTypeSelect.value = sharedType;
                updateSharedTypeFields();

                if (sharedType === 'specific' && Array.isArray(conn.allowed_users)) {
                    document.querySelectorAll('.user-checkbox').forEach(cb => {
                        cb.checked = conn.allowed_users.includes(cb.value);
                    });
                }
            }

            if (requireApprovalCheckbox) {
                const requireApproval = conn.require_approval;
                if (typeof requireApproval === 'string') {
                    requireApprovalCheckbox.checked = (requireApproval === '1' || requireApproval === 'true' || requireApproval === 'yes');
                } else {
                    requireApprovalCheckbox.checked = !!requireApproval;
                }
                requireApprovalCheckbox.value = '1';
            }

            updateConnectionModalTitleAndButton();
            modal.style.display = 'flex';
        } catch (error) {
            console.error('Error loading connection for edit:', error);
            showError('Error loading connection details');
        }
    }

    async function loadApprovalQueue() {
        try {
            const response = await fetch('/api/ai_approvals.php?action=list&status=pending');
            const result = await response.json();

            if (result && result.status === 'ok' && result.data) {
                renderApprovalQueue(result.data.approvals || []);
            } else {
                renderApprovalQueue([]);
            }
        } catch (error) {
            console.error('Error loading approval queue:', error);
            renderApprovalQueue([]);
        }
    }

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
                    <button class="btn btn-sm btn-success" onclick="aiConnections.approveAction('${approval.id}')">Approve</button>
                    <button class="btn btn-sm btn-danger" onclick="aiConnections.rejectAction('${approval.id}')">Reject</button>
                </div>
            </div>
        `).join('');
    }

    async function approveAction(approvalId) {
        if (!confirm('Approve this action?')) return;

        try {
            const response = await fetch('/api/ai_approvals.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'approve', id: approvalId, _csrf: csrfToken })
            });
            const result = await response.json();

            if (result.status === 'ok') {
                showSuccess('Action approved');
                loadApprovalQueue();
            } else {
                showError('Failed to approve: ' + (result.message || 'Unknown error'));
            }
        } catch (error) {
            showError('Error approving action');
        }
    }

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
            showError('Error rejecting action');
        }
    }

    async function deleteConnection(connectionId) {
        if (!confirm('Delete this AI connection? This cannot be undone.')) return;

        try {
            const response = await fetch('/api/ai_connections.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', id: connectionId, _csrf: csrfToken })
            });
            const result = await response.json();

            if (result.status === 'ok') {
                showSuccess('Connection deleted');
                loadConnections();
                window.dispatchEvent(new CustomEvent('birkly:ai-connection-changed', {
                    detail: { action: 'delete', id: connectionId }
                }));
            } else {
                showError('Failed to delete: ' + (result.message || 'Unknown error'));
            }
        } catch (error) {
            showError('Error deleting connection');
        }
    }

    function toggleApprovalQueue() {
        const content = document.getElementById('approval-queue-content');
        if (content) {
            content.style.display = content.style.display === 'none' ? 'block' : 'none';
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatTime(isoString) {
        return new Date(isoString).toLocaleString();
    }

    function showError(message) {
        alert('Error: ' + message);
    }

    function showSuccess(message) {
        alert('Success: ' + message);
    }

    window.aiConnections = {
        editConnection,
        deleteConnection,
        approveAction,
        rejectAction
    };
})();
